#!/usr/bin/env python3
"""Siglele cluburilor, după ID-ul Sofascore (unelte/sigle/<id>.png + rândul din unelte/cluburi.tsv).

  python3 unelte/sigla.py cauta "Feyenoord"                 # caută ID-ul (API-ul Sofascore; merge de pe Mac)
  python3 unelte/sigla.py adauga 2959 "Feyenoord" ["alias1,alias2"]
  python3 unelte/sigla.py id "FCSB"                           # ID-ul unui club deja salvat (după nume sau alias)

ID-ul se vede și în adresa paginii clubului pe sofascore.com: /team/football/<nume>/<ID>.
După `adauga`, uită-te la imagine: sigla trebuie să fie a clubului cerut.
"""
import json, os, subprocess, sys, unicodedata, urllib.parse

DIR = os.path.dirname(os.path.abspath(__file__))
TSV = os.path.join(DIR, 'cluburi.tsv')
UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36'


def fara(s):
    return ''.join(c for c in unicodedata.normalize('NFD', s.lower()) if unicodedata.category(c) != 'Mn')


def citeste():
    rows = []
    for l in open(TSV, encoding='utf-8').read().splitlines()[1:]:
        p = (l.split('\t') + ['', ''])[:3]
        rows.append({'id': p[0], 'nume': p[1], 'alias': [a for a in p[2].split(',') if a]})
    return rows


def scrie(rows):
    out = ['sofa_id\tnume\talias'] + [f"{r['id']}\t{r['nume']}\t{','.join(r['alias'])}" for r in rows]
    open(TSV, 'w', encoding='utf-8').write('\n'.join(out) + '\n')


def curl(url, iesire=None):
    cmd = ['curl', '-s', '-L', '--max-time', '30', '-A', UA, '-w', '%{http_code}', url]
    if iesire:
        cmd[1:1] = ['-o', iesire]
    r = subprocess.run(cmd, capture_output=True, text=True)
    return r.stdout


def cauta(q):
    raspuns = curl('https://api.sofascore.com/api/v1/search/teams?q=' + urllib.parse.quote(q))
    corp, cod = raspuns[:-3], raspuns[-3:]
    if cod != '200':
        sys.exit(f'Căutarea Sofascore a răspuns {cod}. Caută pe web „sofascore {q}” și ia ID-ul din adresa paginii clubului.')
    for t in json.loads(corp).get('results', [])[:10]:
        e = t.get('entity', t)
        if e.get('sport', {}).get('slug', 'football') != 'football':
            continue
        print(f"{e.get('id')}\t{e.get('name')}\t{e.get('country', {}).get('name', '')}")


def adauga(i, nume, alias=''):
    dst = os.path.join(DIR, 'sigle', f'{i}.png')
    tmp = dst + '.tmp'
    cod = curl(f'https://img.sofascore.com/api/v1/team/{i}/image', tmp)
    if cod != '200':
        os.path.exists(tmp) and os.remove(tmp)
        sys.exit(f'Sigla {i} nu există ({cod}).')
    from PIL import Image
    Image.open(tmp).convert('RGBA').save(dst, optimize=True)
    os.remove(tmp)
    rows = [r for r in citeste() if r['id'] != str(i)]
    rows.append({'id': str(i), 'nume': nume, 'alias': [a.strip() for a in alias.split(',') if a.strip()]})
    scrie(rows)
    print(f'OK {dst} – verifică imaginea înainte de commit.')


def gaseste(q):
    q = fara(q)
    for r in citeste():
        if q in [fara(r['nume'])] + [fara(a) for a in r['alias']]:
            print(r['id'], r['nume'])
            return
    sys.exit('Nu e în cluburi.tsv. Folosește `cauta`, apoi `adauga`.')


if __name__ == '__main__':
    if len(sys.argv) < 3:
        sys.exit(__doc__)
    cmd = sys.argv[1]
    if cmd == 'cauta':
        cauta(sys.argv[2])
    elif cmd == 'adauga':
        adauga(sys.argv[2], sys.argv[3], sys.argv[4] if len(sys.argv) > 4 else '')
    elif cmd == 'id':
        gaseste(sys.argv[2])
    else:
        sys.exit(__doc__)
