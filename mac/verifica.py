#!/usr/bin/env python3
"""Verificarea orară a știrilor, pe Mac, fără inteligență artificială.

1. Aduce ultimele modificări din depozit (git pull).
2. Citește Google News (în română) și fluxurile RSS din mac/config.json.
3. Păstrează titlurile din ultimele ore pe care nu le-am mai văzut și le dă un scor:
   ponderea nivelului din ACOPERIRE.md (România 3, Europa 2, America de Sud 1)
   + câte publicații diferite scriu despre același lucru (un subiect preluat de multe surse e important).
4. Numai dacă cel mai bun scor trece de prag, pornește Claude Code cu mac/prompt.md și cu cei mai buni candidați.

Rulare manuală:  python3 mac/verifica.py             (verificare + Claude dacă e cazul)
                 python3 mac/verifica.py --doar-lista  (arată candidații și scorurile, fără Claude)
"""
import collections
import datetime as dt
import email.utils
import fcntl
import html
import json
import os
import re
import subprocess
import sys
import time
import unicodedata
import urllib.parse
import xml.etree.ElementTree as ET

DEPOZIT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
STARE = os.path.join(DEPOZIT, '.stare-mac')
VAZUTE_LOCAL = os.path.join(STARE, 'vazute-local.json')
CANDIDATI = os.path.join(STARE, 'candidati.json')
JURNAL = os.path.join(STARE, 'jurnal.log')
UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36'
CONFIG = json.load(open(os.path.join(DEPOZIT, 'mac', 'config.json'), encoding='utf-8'))


def jurnal(msg):
    os.makedirs(STARE, exist_ok=True)
    rand = f"{dt.datetime.now():%Y-%m-%d %H:%M} {msg}"
    print(rand)
    with open(JURNAL, 'a', encoding='utf-8') as f:
        f.write(rand + '\n')


def fara_diacritice(s):
    return ''.join(c for c in unicodedata.normalize('NFD', s.lower()) if unicodedata.category(c) != 'Mn')


def are_cheia(text, cheie):
    return re.search(r'(?<![a-z0-9])' + re.escape(cheie) + r'(?![a-z0-9])', text) is not None


def curl(url):
    r = subprocess.run(['curl', '-s', '-L', '--max-time', '30', '-A', UA, url], capture_output=True, text=True, errors='ignore')
    return r.stdout


def citeste_flux(url, limba):
    try:
        root = ET.fromstring(curl(url).encode('utf-8', 'ignore'))
    except ET.ParseError:
        jurnal(f'Flux ilizibil: {url}')
        return []
    out = []
    for it in root.iter('item'):
        titlu = html.unescape((it.findtext('title') or '').strip())
        link = (it.findtext('link') or '').strip()
        try:
            cand = email.utils.parsedate_to_datetime(it.findtext('pubDate') or '')
            if cand.tzinfo is None:
                cand = cand.replace(tzinfo=dt.timezone.utc)
        except (TypeError, ValueError):
            cand = None
        sursa = it.findtext('source') or urllib.parse.urlparse(url).netloc
        out.append({'titlu': titlu, 'link': link, 'data': cand, 'sursa': sursa, 'limba': limba})
    return out


def incarca(cale, implicit):
    try:
        with open(cale, encoding='utf-8') as f:
            return json.load(f)
    except (OSError, ValueError):
        return implicit


def salveaza(cale, obiect):
    os.makedirs(os.path.dirname(cale), exist_ok=True)
    with open(cale, 'w', encoding='utf-8') as f:
        json.dump(obiect, f, ensure_ascii=False, indent=1)


def decodeaza(link):
    sys.path.insert(0, os.path.join(DEPOZIT, 'unelte'))
    from gndecode import decode
    try:
        return decode(link)
    except Exception:
        return None


def clasifica(titlu):
    """Nivelul (1, 2, 3) și cheile găsite în titlu; (None, []) dacă titlul nu ne privește."""
    t = fara_diacritice(titlu)
    if any(are_cheia(t, k) for k in CONFIG['ignora']):
        return None, []
    for nivel in sorted(CONFIG['niveluri']):
        chei = [k for k in CONFIG['niveluri'][nivel]['chei'] if are_cheia(t, k)]
        if chei:
            return nivel, chei
    return None, []


def candidati():
    limita = dt.datetime.now(dt.timezone.utc) - dt.timedelta(hours=CONFIG['ore'])
    vazute_local = set(incarca(VAZUTE_LOCAL, []))
    vazute_depozit = incarca(os.path.join(DEPOZIT, 'presa', 'vazute.json'), {})
    articole = []
    for q in CONFIG['cautari_google_news']:
        articole += citeste_flux('https://news.google.com/rss/search?q=' + urllib.parse.quote(q + ' when:1d') + '&hl=ro&gl=RO&ceid=RO:ro', 'ro')
    for limba, lista in CONFIG['fluxuri'].items():
        for f in lista:
            articole += citeste_flux(f, limba)

    # Câte publicații diferite pomenesc fiecare cheie: măsura grosieră a importanței subiectului.
    pastrate, surse_pe_cheie = [], collections.defaultdict(set)
    for a in articole:
        if not a['link'] or a['link'] in vazute_local or (a['data'] and a['data'] < limita):
            continue
        nivel, chei = clasifica(a['titlu'])
        if not nivel:
            continue
        # Presa străină intră doar pentru subiectele mari (nivelurile 2-3 cer o cheie specifică, nu doar „transfer”/„derby”).
        if a['limba'] != 'ro' and nivel != '1' and set(chei) <= {'transfer', 'derby', 'uefa', 'inter', 'river', 'boca', 'racing'}:
            continue
        a['nivel'], a['chei'] = nivel, chei
        pastrate.append(a)
        for k in chei:
            surse_pe_cheie[k].add(a['sursa'])

    noi, vazute_titluri, vazute_url = [], set(), set()
    for a in pastrate:
        cheie_titlu = fara_diacritice(re.sub(r'\s+-\s+[^-]+$', '', a['titlu']))[:70]
        if cheie_titlu in vazute_titluri:
            continue
        vazute_titluri.add(cheie_titlu)
        ecou = max(len(surse_pe_cheie[k]) for k in a['chei'])
        scor = CONFIG['niveluri'][a['nivel']]['pondere'] + min(ecou, 6)
        noi.append({**a, 'scor': scor})
    noi.sort(key=lambda a: (-a['scor'], a['nivel']))

    # Linkurile Google News se decodează doar pentru cei aleși (decodarea e lentă).
    # Cote pe niveluri, ca România să nu ocupe toate locurile; locurile rămase libere trec la celelalte niveluri.
    cote = CONFIG.get('cote_nivel', {})
    pe_nivel = collections.Counter()
    ordine = [a for a in noi if pe_nivel.update([a['nivel']]) or pe_nivel[a['nivel']] <= cote.get(a['nivel'], 99)]
    ordine += [a for a in noi if a not in ordine]
    alesi = []
    for a in ordine:
        if len(alesi) >= CONFIG['max_candidati']:
            break
        url = decodeaza(a['link']) if 'news.google.com' in a['link'] else a['link']
        if url:
            url = url.replace('\\u003d', '=').replace('\\u0026', '&')
        if url in vazute_url:
            continue
        vazute_url.add(url)
        if url and url in vazute_depozit:
            vazute_local.add(a['link'])
            continue
        alesi.append({'titlu': a['titlu'], 'sursa': a['sursa'], 'limba': a['limba'], 'url': url or a['link'],
                      'link_original': a['link'], 'nivel': int(a['nivel']), 'scor': a['scor'], 'chei': a['chei'],
                      'data': a['data'].isoformat() if a['data'] else ''})
    salveaza(VAZUTE_LOCAL, sorted(vazute_local)[-10000:])
    return alesi


def notifica(text):
    text = text.replace('"', "'")[:200]
    subprocess.run(['osascript', '-e', f'display notification "{text}" with title "Eurogol"'], capture_output=True)


def porneste_importul():
    """Pornește importul pe site (wp-cron), ca știrile să apară imediat, chiar dacă cron-ul găzduirii întârzie."""
    curl(CONFIG.get('site', 'https://eurogol.ro').rstrip('/') + '/wp-cron.php?doing_wp_cron')


def main():
    os.makedirs(STARE, exist_ok=True)
    porneste_importul()
    blocare = open(os.path.join(STARE, '.blocare'), 'w')
    try:
        fcntl.flock(blocare, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except OSError:
        jurnal('O verificare anterioară încă rulează, ies.')
        return
    subprocess.run(['git', '-C', DEPOZIT, 'pull', '-q', '--rebase', 'origin', 'main'], capture_output=True)
    noi = candidati()
    if '--doar-lista' in sys.argv:
        for c in sorted(noi, key=lambda c: (c['nivel'], -c['scor'])):
            print(f"{c['scor']:>2} N{c['nivel']} {c['titlu'][:90]} | {c['sursa']} | {', '.join(c['chei'])}")
        return
    if not noi or noi[0]['scor'] < CONFIG['prag_pornire']:
        jurnal(f'Nimic important ({len(noi)} candidați sub prag).')
        return
    salveaza(CANDIDATI, noi)
    jurnal(f'{len(noi)} candidați (cel mai bun scor {noi[0]["scor"]}), pornesc Claude.')
    with open(os.path.join(DEPOZIT, 'mac', 'prompt.md'), encoding='utf-8') as f:
        prompt = f.read()
    unelte = ['Read', 'Write', 'Edit', 'Glob', 'Grep', 'WebSearch', 'Bash(git:*)', 'Bash(python3:*)', 'Bash(node:*)',
              'Bash(curl:*)', 'Bash(ls:*)', 'Bash(cat:*)', 'Bash(head:*)', 'Bash(grep:*)', 'Bash(wc:*)', 'Bash(date:*)']
    start = time.time()
    r = subprocess.run(['claude', '-p', prompt, '--allowedTools', *unelte, '--max-turns', '80', '--output-format', 'text'],
                       cwd=DEPOZIT, capture_output=True, text=True, timeout=3600)
    raport = (r.stdout or r.stderr or '').strip()
    prima = raport.splitlines()[0] if raport else 'Fără raport'
    jurnal(f'Claude a terminat în {int(time.time() - start)}s: {prima}')
    with open(os.path.join(STARE, 'ultimul-raport.txt'), 'w', encoding='utf-8') as f:
        f.write(raport)
    vazute_local = set(incarca(VAZUTE_LOCAL, []))
    vazute_local.update(c['link_original'] for c in noi)
    salveaza(VAZUTE_LOCAL, sorted(vazute_local)[-10000:])
    if not prima.lower().startswith('nimic nou'):
        notifica(prima)
        time.sleep(90)  # GitHub servește fișierele noi după un minut-două
        porneste_importul()


if __name__ == '__main__':
    main()
