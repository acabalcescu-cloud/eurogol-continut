#!/usr/bin/env python3
"""Găsește rezumatul video OFICIAL al unui meci pe YouTube.

Folosire:
  python3 unelte/video.py "Echipa A" "Echipa B" [scor, ex. 2-1] [--data AAAA-LL-ZZ]

Caută pe YouTube, apoi verifică fiecare clip prin oEmbed: canalul trebuie să fie în lista
oficială de mai jos, iar titlul să conțină ambele echipe (și scorul, dacă e dat).
Afișează JSON: {"video": url, "video_sursa": canal, "titlu": ...} sau {} dacă nu găsește.

Regula redacției: doar clipuri oficiale (televiziuni cu drepturi, ligi, competiții, cluburi).
Fără fani, fără Shorts, fără reîncărcări. Coperta articolului rămâne cea generată de noi;
thumbnailul YouTube apare doar în playerul încorporat.
"""
import json
import re
import sys
import unicodedata
import urllib.parse
import urllib.request

# Canale oficiale (numele exact întors de oEmbed „author_name”, comparat fără diacritice și majuscule).
OFICIALE = {
    # România
    'digi sport', 'digisport', 'digi sport romania', 'prima sport', 'primasport', 'orange sport',
    'liga profesionista de fotbal', 'lpf', 'frf', 'echipa nationala de fotbal a romaniei',
    'fcsb', 'fc fcsb', 'cfr 1907 cluj', 'cfr cluj', 'universitatea craiova', 'fc universitatea craiova', 'fc rapid 1923',
    'rapid bucuresti', 'fc dinamo bucuresti', 'dinamo bucuresti', 'fc u craiova 1948', 'farul constanta', 'fc farul constanta',
    'u cluj', 'universitatea cluj', 'petrolul ploiesti', 'sepsi osk', 'fc botosani', 'uta arad', 'otelul galati',
    'antena sport', 'pro tv', 'protv',
    # Competiții și ligi
    'uefa', 'uefa champions league', 'uefa europa league', 'uefa conference league', 'fifa',
    'premier league', 'laliga', 'laliga easports', 'la liga', 'serie a', 'lega serie a', 'bundesliga', 'ligue 1',
    'ligue 1 mcdonalds', 'ligue 1 uber eats', 'conmebol', 'conmebol libertadores', 'conmebol sudamericana',
    'brasileirao', 'liga profesional de futbol', 'liga portugal', 'eredivisie', 'super lig', 'trendyol super lig',
    # Televiziuni cu drepturi
    'dazn', 'dazn italia', 'dazn espana', 'sky sports', 'sky sport', 'bt sport', 'tnt sports', 'cbs sports golazo',
    'espn', 'espn fc', 'nbc sports', 'beinsports', 'bein sports', 'canal+ sport', 'movistar plus+', 'sport tv',
    'eleven sports', 'ge tv', 'globo esporte', 'tyc sports', 'espn brasil', 'cazetv',
}


# Canale verificate după adresă (numele „Superliga” e folosit și de canale străine sau neoficiale).
OFICIALE_URL = {
    '@superligaromaniei',   # Superliga România – canalul oficial al ligii (rezumate „… | REZUMAT”)
    '@digisportoficial', '@primasporttv',
}


def oficial(o):
    url = (o.get('author_url') or '').lower().rstrip('/')
    if url.rsplit('/', 1)[-1] in OFICIALE_URL:
        return True
    return plat(o.get('author_name', '')) in OFICIALE


def plat(s):
    s = unicodedata.normalize('NFKD', s or '')
    return re.sub(r'\s+', ' ', ''.join(c for c in s if not unicodedata.combining(c)).lower()).strip()


def get(url, timeout=20):
    req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0', 'Accept-Language': 'ro,en;q=0.8'})
    with urllib.request.urlopen(req, timeout=timeout) as r:
        return r.read().decode('utf-8', 'replace')


def cauta(q, n=12):
    html = get('https://www.youtube.com/results?search_query=' + urllib.parse.quote(q))
    ids = []
    for m in re.finditer(r'"videoId":"([A-Za-z0-9_-]{11})"', html):
        if m.group(1) not in ids:
            ids.append(m.group(1))
    return ids[:n]


def oembed(vid):
    try:
        return json.loads(get('https://www.youtube.com/oembed?format=json&url=' +
                              urllib.parse.quote('https://www.youtube.com/watch?v=' + vid)))
    except Exception:
        return None


def cuvinte_echipa(nume):
    """Cuvintele distinctive ale unei echipe (fără FC, CS, AFC etc.)."""
    stop = {'fc', 'cs', 'afc', 'sc', 'cf', 'ac', 'as', 'sv', 'fk', 'sk', 'cfr', 'u', 'de', 'la', 'club', 'real', 'city', 'united'}
    w = [x for x in re.split(r'[^a-z0-9]+', plat(nume)) if len(x) > 2 and x not in stop]
    return w or [plat(nume)]


def potriveste(titlu, a, b, scor):
    t = plat(titlu)
    if '#shorts' in t or 'shorts' in t.split():
        return False
    for e in (a, b):
        if not any(w in t for w in cuvinte_echipa(e)):
            return False
    if scor:
        x, y = re.split(r'[-:]', scor)
        if not re.search(rf'\b{x}\s*[-:]\s*{y}\b|\b{y}\s*[-:]\s*{x}\b', t):
            return False
    return True


def gaseste(a, b, scor=None, data=None):
    cereri = [f'{a} {b} rezumat', f'{a} - {b} {scor or ""} highlights', f'{a} {b} goluri']
    vazute = set()
    for q in cereri:
        try:
            ids = cauta(q)
        except Exception:
            continue
        for vid in ids:
            if vid in vazute:
                continue
            vazute.add(vid)
            o = oembed(vid)
            if not o:
                continue
            canal = o.get('author_name', '')
            if not oficial(o):
                continue
            if potriveste(o.get('title', ''), a, b, scor):
                return {'video': 'https://www.youtube.com/watch?v=' + vid, 'video_sursa': canal, 'titlu': o.get('title', '')}
    return {}


if __name__ == '__main__':
    arg = [x for x in sys.argv[1:] if not x.startswith('--')]
    data = None
    if '--data' in sys.argv:
        data = sys.argv[sys.argv.index('--data') + 1]
        arg = [x for x in arg if x != data]
    if len(arg) < 2:
        sys.exit(__doc__)
    print(json.dumps(gaseste(arg[0], arg[1], arg[2] if len(arg) > 2 else None, data), ensure_ascii=False))
