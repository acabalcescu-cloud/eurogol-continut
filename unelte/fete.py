#!/usr/bin/env python3
"""Portretele de pe coperți și din articole (Eurogol). Doar fotografii cu licență liberă (Wikimedia Commons) sau proprii.

  python3 unelte/fete.py wiki "Nume Prenume" "Titlu_pagina_Wikipedia" [ro|en]   # poză liberă de pe Wikipedia/Commons
  python3 unelte/fete.py fisier "Nume Prenume" <cale_sau_url> "Credit"            # poză proprie / a clubului
  python3 unelte/fete.py stil <slug>                                              # refă stilizarea

Rezultat: unelte/fete/<slug>.png (implicit „gravura”: linii albe, se așază pe orice culoare a copertei) + intrarea în unelte/fete/fete.json
(nume, alias, fisier, credit, sursa, foto, licenta). Site-ul pune fotografia (cu creditul) în articol și creditul portretului la final.
"""
import json, os, re, subprocess, sys, time, unicodedata, urllib.parse
import numpy as np
from PIL import Image, ImageDraw, ImageFilter, ImageOps

DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'fete')
SURSA = os.path.join(DIR, 'sursa')
INDEX = os.path.join(DIR, 'fete.json')
UA = 'Eurogol/1.0 (https://eurogol.ro)'
LIC_URL = ['']
FOTO_URL = ['']

def slug(s):
    s = unicodedata.normalize('NFD', s).encode('ascii', 'ignore').decode().lower()
    return re.sub(r'[^a-z0-9]+', '-', s).strip('-')

def get(url, binar=False):
    for i in range(6):
        r = subprocess.run(['curl', '-sL', '--max-time', '40', '-A', UA, url], capture_output=True)
        if r.returncode == 0 and r.stdout and not r.stdout.startswith(b'You are making too many') and not (binar and r.stdout.lstrip()[:15].lower().startswith((b'<!doctype', b'<html'))):
            return r.stdout if binar else r.stdout.decode('utf-8', 'ignore')
        time.sleep(4 + 4 * i)
    raise SystemExit('Nu pot descărca: ' + url)

def wiki(titlu, lang):
    api = f'https://{lang}.wikipedia.org/w/api.php'
    q = urllib.parse.urlencode({'action': 'query', 'titles': titlu, 'prop': 'pageimages', 'piprop': 'original|name', 'format': 'json', 'redirects': 1})
    p = list(json.loads(get(api + '?' + q))['query']['pages'].values())[0]
    if 'original' not in p:
        raise SystemExit('Pagina nu are imagine: ' + titlu)
    fis = p['pageimage']
    q = urllib.parse.urlencode({'action': 'query', 'titles': 'File:' + fis, 'prop': 'imageinfo', 'iiprop': 'extmetadata|url', 'iiurlwidth': 800, 'format': 'json'})
    ii = list(json.loads(get(api + '?' + q))['query']['pages'].values())[0]['imageinfo'][0]
    m = ii.get('extmetadata', {})
    autor = re.sub('<[^>]+>', '', m.get('Artist', {}).get('value', '')).strip() or 'autor necunoscut'
    lic = m.get('LicenseShortName', {}).get('value', '').strip()
    if not re.search(r'CC|Public domain|PD|GFDL', lic, re.I):
        raise SystemExit(f'Licență nepotrivită ({lic}) pentru {fis}')
    orig = p['original']['source'].split('?')[0]
    m2 = re.match(r'(https://upload\.wikimedia\.org/wikipedia/commons/)(\w/\w\w/)(.+)$', orig)
    th = f'{m2.group(1)}thumb/{m2.group(2)}{m2.group(3)}/960px-{m2.group(3)}' if m2 else orig
    if th.lower().endswith(('.tif', '.tiff')):
        th += '.jpg'
    LIC_URL[0] = m.get('LicenseUrl', {}).get('value', '')
    return th, f'{autor}, Wikimedia Commons, {lic}', ii.get('descriptionurl', '')

def fata(img):
    """Pătratul din jurul feței (detecție OpenCV), altfel partea de sus, la mijloc."""
    w, h = img.size
    try:
        import cv2
        g = cv2.cvtColor(np.array(img.convert('RGB')), cv2.COLOR_RGB2GRAY)
        c = cv2.CascadeClassifier(cv2.data.haarcascades + 'haarcascade_frontalface_default.xml')
        f = c.detectMultiScale(g, 1.1, 5, minSize=(max(30, w // 12), max(30, w // 12)))
        if len(f):
            x, y, fw, fh = max(f, key=lambda r: r[2] * r[3])
            lat = int(fw * 2.4)
            cx, cy = x + fw // 2, y + int(fh * 0.62)
            x0, y0 = max(0, cx - lat // 2), max(0, cy - lat // 2)
            lat = min(lat, w - x0, h - y0)
            return img.crop((x0, y0, x0 + lat, y0 + lat))
    except Exception:
        pass
    lat = min(w, int(h * 0.75))
    x0 = (w - lat) // 2
    return img.crop((x0, int(h * 0.02), x0 + lat, int(h * 0.02) + lat))

def schita(src, dst):
    """Filigran: desen de mână în creion (linii bleumarin pe hârtie bleu-pal), fără cerc; marginile se topesc în fundalul copertei."""
    S = 700
    im = fata(ImageOps.exif_transpose(Image.open(src)).convert('RGB')).resize((S, S), Image.LANCZOS)
    # fundalul fotografiei se estompează, ca să rămână doar omul
    clar = Image.new('L', (S, S), 0)
    ImageDraw.Draw(clar).ellipse((S * .2, S * .05, S * .8, S * .95), fill=255)
    clar = clar.filter(ImageFilter.GaussianBlur(50))
    im = Image.composite(im, Image.new('RGB', (S, S), (235, 235, 235)), clar)
    g = np.asarray(ImageOps.autocontrast(im.convert('L'), cutoff=1)).astype(np.float32)
    b = np.asarray(Image.fromarray((255 - g).astype(np.uint8)).filter(ImageFilter.GaussianBlur(9))).astype(np.float32)
    dodge = np.clip(g * 255.0 / np.maximum(1.0, 255.0 - b), 0, 255)   # „color dodge”: tehnica clasică de schiță în creion
    ton = np.asarray(Image.fromarray(g.astype(np.uint8)).filter(ImageFilter.GaussianBlur(3))).astype(np.float32)
    creion = np.clip(dodge - (255 - ton) * 0.18, 0, 255) / 255.0       # linii + umbre ușoare, ca hașura
    creion = creion ** 1.6
    hartie, mina = np.array([238, 242, 250.0]), np.array([11, 42, 111.0])  # bleumarinul logo-ului Eurogol
    rgb = (mina * (1 - creion[..., None]) + hartie * creion[..., None]).clip(0, 255).astype(np.uint8)
    # estompare radială: zero la margini (stânga, dreapta, sus), plină pe față; jos rămâne tăiat de marginea copertei
    yy, xx = np.mgrid[0:S, 0:S].astype(np.float32) / S
    d = np.sqrt(((xx - .5) / .48) ** 2 + ((yy - .55) / .55) ** 2)
    alfa = Image.fromarray((np.clip((1 - d) / .5, 0, 1) ** 1.3 * 0.9 * 255).astype(np.uint8))
    rez = Image.fromarray(rgb).convert('RGBA')
    rez.putalpha(alfa)
    rez.save(dst, optimize=True)

def gravura(src, dst):
    """Filigran ca pe bancnote: linii albe orizontale, ușor ondulate, mai groase unde fotografia e luminoasă;
    fără fond deschis, fără cerc — omul apare direct pe albastrul copertei, iar marginile se subțiază până dispar."""
    S, PAS = 700, 7
    im = fata(ImageOps.exif_transpose(Image.open(src)).convert('RGB')).resize((S, S), Image.LANCZOS)
    # decupăm omul de pe fundal (rembg, model pentru persoane); dacă nu e disponibil, rămâne doar estomparea radială
    om = np.ones((S, S), np.float32)
    try:
        from rembg import remove, new_session
        om = np.asarray(remove(im, session=new_session('u2net_human_seg')).getchannel('A').filter(ImageFilter.GaussianBlur(3))).astype(np.float32) / 255.0
    except Exception as e:
        print('fără decupaj:', e)
    g = np.asarray(ImageOps.autocontrast(im.convert('L').filter(ImageFilter.GaussianBlur(1.2)), cutoff=1)).astype(np.float32) / 255.0
    g = ImageOps.equalize(Image.fromarray((g * 255).astype(np.uint8)))
    g = (np.asarray(g).astype(np.float32) / 255.0) ** 0.75
    yy, xx = np.mgrid[0:S, 0:S].astype(np.float32)
    # estompare doar sus și în lateral; jos portretul merge drept până la marginea copertei
    dy = np.where(yy / S < .55, (yy / S - .55) / .55, 0)
    d = np.sqrt(((xx / S - .5) / .46) ** 2 + dy ** 2)
    m = np.clip((1 - d) / .45, 0, 1) ** 1.2
    v = g * m * om                                               # cât de „aprins” e fiecare punct
    unda = yy + 2.2 * np.sin(xx / 38.0) + 1.5 * np.sin((xx + yy) / 23.0)
    faza = np.abs(((unda / PAS) % 1.0) - .5) * 2                 # 0 pe axa liniei, 1 între linii
    gros = v * 0.95                                              # grosimea liniei (fracțiune din pas)
    alfa = np.clip((gros - faza) * 3.0 + .5, 0, 1) * np.clip(v * 3, 0, 1)
    rez = Image.new('RGBA', (S, S), (240, 247, 255, 0))
    rez.putalpha(Image.fromarray((alfa * 0.92 * 255).astype(np.uint8)))
    rez.save(dst, optimize=True)

def stil(src, dst, varianta='schita'):
    """varianta: 'schita' (implicit, filigran), 'bulina' (bicolor în cerc) sau 'difuz' (bulina estompată)."""
    if varianta == 'schita':
        return schita(src, dst)
    if varianta == 'gravura':
        return gravura(src, dst)
    difuz = varianta == 'difuz'
    """Amprenta noastră: bicolor (bleumarin → bleu → alb), textură de puncte, decupaj rotund, inel dublu."""
    S = 600
    im = fata(ImageOps.exif_transpose(Image.open(src)).convert('RGB')).resize((S, S), Image.LANCZOS)
    if difuz:
        # fundal estompat: doar zona feței (elipsă moale, sus-centru) rămâne clară
        clar = Image.new('L', (S, S), 0)
        ImageDraw.Draw(clar).ellipse((S * .22, S * .08, S * .78, S * .78), fill=255)
        im = Image.composite(im, im.filter(ImageFilter.GaussianBlur(14)), clar.filter(ImageFilter.GaussianBlur(60)))
    g = ImageOps.autocontrast(im.convert('L'), cutoff=2)
    if difuz:
        g = Image.blend(g, Image.new('L', (S, S), 120), 0.22)
    a = np.asarray(g).astype(np.float32) / 255.0
    stops = [(0.0, (7, 26, 51)), (0.45, (0, 88, 176)), (0.78, (126, 190, 255)), (1.0, (245, 250, 255))]
    out = np.zeros((S, S, 3), np.float32)
    for (p0, c0), (p1, c1) in zip(stops, stops[1:]):
        m = (a >= p0) & (a <= p1)
        t = ((a - p0) / (p1 - p0))[..., None]
        out = np.where(m[..., None], np.array(c0) * (1 - t) + np.array(c1) * t, out)
    duo = Image.fromarray(out.clip(0, 255).astype(np.uint8))
    # textura de puncte (semiton), discretă
    pt = Image.new('L', (S, S), 0)
    d = ImageDraw.Draw(pt)
    for yy in range(0, S, 9):
        for xx in range((yy // 9 % 2) * 4, S, 9):
            r = 1.6 * (1 - a[min(yy, S - 1), min(xx, S - 1)])
            if r > .3:
                d.ellipse((xx - r, yy - r, xx + r, yy + r), fill=255)
    duo = Image.composite(Image.new('RGB', (S, S), (7, 26, 51)), duo, pt.point(lambda v: v * 0.35))
    if difuz:
        # strălucire moale (ca un filtru „glow”) + margini topite spre bleumarin
        duo = Image.blend(duo, duo.filter(ImageFilter.GaussianBlur(8)), 0.45)
        vig = Image.new('L', (S, S), 0)
        ImageDraw.Draw(vig).ellipse((S * .1, S * .06, S * .9, S * .94), fill=255)
        duo = Image.composite(duo, Image.new('RGB', (S, S), (7, 26, 51)), vig.filter(ImageFilter.GaussianBlur(55)))
    masca = Image.new('L', (S, S), 0)
    ImageDraw.Draw(masca).ellipse((22, 22, S - 22, S - 22), fill=255)
    rez = Image.new('RGBA', (S, S), (0, 0, 0, 0))
    rez.paste(duo, (0, 0), masca)
    dr = ImageDraw.Draw(rez)
    dr.ellipse((14, 14, S - 14, S - 14), outline=(255, 255, 255, 255), width=10)
    dr.ellipse((2, 2, S - 2, S - 2), outline=(59, 160, 255, 255), width=6)
    rez.save(dst, optimize=True)

def salveaza(nume, src, credit, sursa_url, alias=()):
    os.makedirs(SURSA, exist_ok=True)
    s = slug(nume)
    idx = json.load(open(INDEX)) if os.path.exists(INDEX) else []
    var = next((x.get('stil', 'gravura') for x in idx if x['slug'] == s), 'gravura')
    stil(src, os.path.join(DIR, s + '.png'), var)
    idx = [x for x in idx if x['slug'] != s]
    parti = nume.split()
    al = sorted(set([nume] + list(alias) + ([' '.join(parti[1:])] if len(parti) > 1 and len(' '.join(parti[1:])) > 4 else [])))
    idx.append({'slug': s, 'nume': nume, 'alias': al, 'fisier': s + '.png', 'credit': credit, 'sursa': sursa_url, 'stil': var,
                'foto': FOTO_URL[0], 'licenta': LIC_URL[0]})
    json.dump(sorted(idx, key=lambda x: x['slug']), open(INDEX, 'w'), ensure_ascii=False, indent=1)
    print('OK', s, '|', credit)

if __name__ == '__main__':
    mod = sys.argv[1]
    if mod == 'wiki':
        nume, titlu = sys.argv[2], sys.argv[3]
        lang = sys.argv[4] if len(sys.argv) > 4 else 'ro'
        url, credit, desc = wiki(titlu, lang)
        FOTO_URL[0] = url
        src = os.path.join(SURSA, slug(nume) + os.path.splitext(urllib.parse.urlparse(url).path)[1].lower())
        open(src, 'wb').write(get(url, True))
        salveaza(nume, src, credit, desc, sys.argv[5:])
    elif mod == 'fisier':
        nume, cale, credit = sys.argv[2], sys.argv[3], sys.argv[4]
        src = cale
        if cale.startswith('http'):
            src = os.path.join(SURSA, slug(nume) + os.path.splitext(urllib.parse.urlparse(cale).path)[1].lower())
            open(src, 'wb').write(get(cale, True))
        else:
            import shutil
            os.makedirs(SURSA, exist_ok=True)
            src = os.path.join(SURSA, slug(nume) + os.path.splitext(cale)[1].lower())
            shutil.copyfile(cale, src)
        FOTO_URL[0] = cale if cale.startswith('http') else ''
        salveaza(nume, src, credit, cale if cale.startswith('http') else '', sys.argv[5:])
    elif mod == 'stil':
        s = sys.argv[2]
        src = [f for f in os.listdir(SURSA) if f.startswith(s + '.')][0]
        idx = json.load(open(INDEX))
        for x in idx:  # stil <slug> [schita|bulina|difuz]
            if x['slug'] == s:
                x.pop('difuz', None)
                x['stil'] = sys.argv[3] if len(sys.argv) > 3 else x.get('stil', 'gravura')
                var = x['stil']
        json.dump(idx, open(INDEX, 'w'), ensure_ascii=False, indent=1)
        stil(os.path.join(SURSA, src), os.path.join(DIR, s + '.png'), var)
        print('OK', s)
