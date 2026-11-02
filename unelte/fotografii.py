#!/usr/bin/env python3
"""Fotografii cu licență liberă pentru coperți și articole.

Surse:
  openverse  – fără cheie. Adună Flickr Creative Commons, Wikimedia Commons și alte arhive deschise.
               Cerem doar licențe care permit folosirea comercială și prelucrarea: CC0, domeniu public, CC BY, CC BY-SA.
  unsplash   – cheie gratuită: UNSPLASH_ACCESS_KEY  (https://unsplash.com/developers)
  pexels     – cheie gratuită: PEXELS_API_KEY       (https://www.pexels.com/api/)
  pixabay    – cheie gratuită: PIXABAY_API_KEY      (https://pixabay.com/api/docs/)
Cheile se pun în variabile de mediu sau în ~/.config/eurogol/chei.env (rânduri CHEIE=valoare). Nu se pun niciodată în depozit.

  python3 unelte/fotografii.py cauta "Cristian Chivu"                    # toate sursele disponibile
  python3 unelte/fotografii.py cauta "football stadium night" --surse unsplash,pexels
  python3 unelte/fotografii.py portret 3 "Cristi Chivu" ["alias"]       # rezultatul 3 → portret (unelte/fete/)
  python3 unelte/fotografii.py fundal 2 stadion-noaptea                  # rezultatul 2 → fundal de copertă (unelte/fotografii/)

Reguli:
  - Portrete: Openverse (Wikimedia/Flickr). Pe Unsplash, Pexels și Pixabay oamenii sunt rareori cei căutați: verifică.
  - Fundaluri: stadioane, tribune, mingi, terenuri. Fără sigle de club sau sponsori la vedere, fără oameni recognoscibili
    prezentați ca fiind altcineva.
  - Uită-te mereu la fotografie înainte de folosire (python3 unelte/fotografii.py cauta ... afișează și adresa).
"""
import json, os, re, subprocess, sys, unicodedata, urllib.parse

DIR = os.path.dirname(os.path.abspath(__file__))
STARE = os.path.join(os.path.dirname(DIR), '.stare-mac')
ULTIMA = os.path.join(STARE, 'ultima-cautare-foto.json')
FOTO_DIR = os.path.join(DIR, 'fotografii')
FOTO_INDEX = os.path.join(FOTO_DIR, 'fotografii.json')
UA = 'Eurogol/1.0 (https://eurogol.ro)'
LICENTE_OK = {'cc0', 'pdm', 'by', 'by-sa'}
NUME_LICENTA = {'cc0': 'CC0', 'pdm': 'domeniu public', 'by': 'CC BY', 'by-sa': 'CC BY-SA'}


def slug(s):
    s = unicodedata.normalize('NFD', s).encode('ascii', 'ignore').decode().lower()
    return re.sub(r'[^a-z0-9]+', '-', s).strip('-')


def chei():
    k = dict(os.environ)
    f = os.path.expanduser('~/.config/eurogol/chei.env')
    if os.path.exists(f):
        for l in open(f, encoding='utf-8'):
            if '=' in l and not l.strip().startswith('#'):
                a, b = l.strip().split('=', 1)
                k.setdefault(a.strip(), b.strip().strip('"\''))
    return k


def get(url, antete=(), binar=False, incercari=3):
    for i in range(incercari):
        cmd = ['curl', '-s', '-L', '--max-time', '90', '-A', UA, '-w', '\n%{http_code}']
        for h in antete:
            cmd += ['-H', h]
        r = subprocess.run(cmd + [url], capture_output=True)
        corp, _, cod = r.stdout.rpartition(b'\n')
        if cod == b'200':
            return corp if binar else corp.decode('utf-8', 'ignore')
    raise RuntimeError(f'{url} → {cod.decode()}')


def openverse(q, n):
    p = urllib.parse.urlencode({'q': q, 'license_type': 'commercial,modification', 'page_size': n, 'mature': 'false'})
    out = []
    for x in json.loads(get('https://api.openverse.org/v1/images/?' + p))['results']:
        if x.get('license') not in LICENTE_OK:
            continue
        lic = NUME_LICENTA[x['license']] + (' ' + x['license_version'] if x.get('license_version') and x['license'] not in ('cc0', 'pdm') else '')
        out.append({'platforma': {'wikimedia': 'Wikimedia Commons', 'flickr': 'Flickr'}.get(x.get('source'), x.get('source', 'Openverse')),
                    'titlu': x.get('title') or '', 'autor': x.get('creator') or 'autor necunoscut', 'licenta': lic,
                    'licenta_url': x.get('license_url', ''), 'sursa': x.get('foreign_landing_url', ''), 'url': x.get('url', ''),
                    'dim': f"{x.get('width')}×{x.get('height')}"})
    return out


def unsplash(q, n, k):
    d = json.loads(get('https://api.unsplash.com/search/photos?' + urllib.parse.urlencode({'query': q, 'per_page': n}),
                       [f"Authorization: Client-ID {k['UNSPLASH_ACCESS_KEY']}", 'Accept-Version: v1']))
    return [{'platforma': 'Unsplash', 'titlu': x.get('alt_description') or '', 'autor': x['user']['name'], 'licenta': 'licența Unsplash',
             'licenta_url': 'https://unsplash.com/license', 'sursa': x['links']['html'] + '?utm_source=eurogol&utm_medium=referral',
             'url': x['urls']['regular'], 'descarcare': x['links'].get('download_location', ''), 'dim': f"{x['width']}×{x['height']}"}
            for x in d['results']]


def pexels(q, n, k):
    d = json.loads(get('https://api.pexels.com/v1/search?' + urllib.parse.urlencode({'query': q, 'per_page': n}), [f"Authorization: {k['PEXELS_API_KEY']}"]))
    return [{'platforma': 'Pexels', 'titlu': x.get('alt') or '', 'autor': x['photographer'], 'licenta': 'licența Pexels',
             'licenta_url': 'https://www.pexels.com/license/', 'sursa': x['url'], 'url': x['src']['large2x'], 'dim': f"{x['width']}×{x['height']}"}
            for x in d['photos']]


def pixabay(q, n, k):
    d = json.loads(get('https://pixabay.com/api/?' + urllib.parse.urlencode({'key': k['PIXABAY_API_KEY'], 'q': q, 'per_page': max(3, n), 'image_type': 'photo', 'safesearch': 'true'})))
    return [{'platforma': 'Pixabay', 'titlu': x.get('tags') or '', 'autor': x['user'], 'licenta': 'licența Pixabay',
             'licenta_url': 'https://pixabay.com/service/license-summary/', 'sursa': x['pageURL'], 'url': x['largeImageURL'], 'dim': f"{x['imageWidth']}×{x['imageHeight']}"}
            for x in d['hits']]


def cauta(q, surse, n=8):
    k = chei()
    disponibile = {'openverse': True, 'unsplash': 'UNSPLASH_ACCESS_KEY' in k, 'pexels': 'PEXELS_API_KEY' in k, 'pixabay': 'PIXABAY_API_KEY' in k}
    rez = []
    for s in surse:
        if not disponibile.get(s):
            print(f'– {s}: lipsește cheia API (vezi începutul fișierului)')
            continue
        try:
            rez += {'openverse': lambda: openverse(q, n), 'unsplash': lambda: unsplash(q, n, k),
                    'pexels': lambda: pexels(q, n, k), 'pixabay': lambda: pixabay(q, n, k)}[s]()
        except Exception as e:
            print(f'– {s}: {e}')
    os.makedirs(STARE, exist_ok=True)
    json.dump(rez, open(ULTIMA, 'w'), ensure_ascii=False, indent=1)
    for i, x in enumerate(rez, 1):
        print(f"{i:>2}. [{x['platforma']}] {x['licenta']} | {x['autor']} | {x['dim']} | {x['titlu'][:60]}\n    {x['sursa']}\n    {x['url']}")
    if not rez:
        print('Niciun rezultat.')


def alege(nr):
    rez = json.load(open(ULTIMA))
    x = rez[int(nr) - 1]
    if x.get('descarcare'):  # Unsplash cere semnalarea descărcării
        try:
            get(x['descarcare'], [f"Authorization: Client-ID {chei()['UNSPLASH_ACCESS_KEY']}"])
        except Exception:
            pass
    return x


def credit(x):
    return f"{x['autor']}, {x['platforma']}, {x['licenta']}"


def portret(nr, nume, alias=()):
    x = alege(nr)
    sys.path.insert(0, DIR)
    import fete
    os.makedirs(fete.SURSA, exist_ok=True)
    ext = os.path.splitext(urllib.parse.urlparse(x['url']).path)[1].lower() or '.jpg'
    src = os.path.join(fete.SURSA, fete.slug(nume) + ext)
    open(src, 'wb').write(get(x['url'], binar=True))
    fete.FOTO_URL[0] = x['url']
    fete.LIC_URL[0] = x.get('licenta_url', '')
    fete.salveaza(nume, src, credit(x), x['sursa'], alias)


def fundal(nr, nume):
    from PIL import Image, ImageOps
    x = alege(nr)
    os.makedirs(FOTO_DIR, exist_ok=True)
    s = slug(nume)
    tmp = os.path.join(FOTO_DIR, s + '.tmp')
    open(tmp, 'wb').write(get(x['url'], binar=True))
    im = ImageOps.exif_transpose(Image.open(tmp)).convert('RGB')
    im.thumbnail((1600, 1600))
    im.save(os.path.join(FOTO_DIR, s + '.jpg'), quality=86, optimize=True)
    os.remove(tmp)
    idx = json.load(open(FOTO_INDEX)) if os.path.exists(FOTO_INDEX) else []
    idx = [y for y in idx if y['slug'] != s] + [{'slug': s, 'fisier': s + '.jpg', 'titlu': x['titlu'], 'credit': credit(x),
                                                 'sursa': x['sursa'], 'licenta_url': x.get('licenta_url', ''), 'platforma': x['platforma']}]
    json.dump(sorted(idx, key=lambda y: y['slug']), open(FOTO_INDEX, 'w'), ensure_ascii=False, indent=1)
    print('OK', os.path.join(FOTO_DIR, s + '.jpg'), '|', credit(x))


if __name__ == '__main__':
    a = sys.argv[1:]
    if not a:
        sys.exit(__doc__)
    if a[0] == 'cauta':
        surse = ['openverse', 'unsplash', 'pexels', 'pixabay']
        if '--surse' in a:
            surse = a[a.index('--surse') + 1].split(',')
        cauta(a[1], surse)
    elif a[0] == 'portret':
        portret(a[1], a[2], a[3:])
    elif a[0] == 'fundal':
        fundal(a[1], a[2])
    else:
        sys.exit(__doc__)
