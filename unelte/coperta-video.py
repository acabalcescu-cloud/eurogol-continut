#!/usr/bin/env python3
"""Coperta unui articol video, din imaginea clipului YouTube (1200×630 JPG).

Folosire:  python3 unelte/coperta-video.py <url-sau-id-youtube> cronici/<id>.jpg ["Rubrica"]

Ia thumbnailul clipului (maxresdefault, altfel hqdefault), îl decupează la 1200×630 și pune
peste el butonul ▶, o bandă cu rubrica („Video” implicit) și sigla EuroGol în colț.
Decizia redacției (14.11.2026): la articolele video coperta e imaginea clipului, cu sursa
menționată în articol („video_sursa”). Doar clipuri de pe canale oficiale (unelte/video.py).
"""
import io
import re
import sys
import urllib.request
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

DIR = Path(__file__).resolve().parent
W, H = 1200, 630


def vid_id(s):
    m = re.search(r'(?:youtu\.be/|v=|/embed/|/shorts/)([A-Za-z0-9_-]{11})', s)
    return m.group(1) if m else s if re.fullmatch(r'[A-Za-z0-9_-]{11}', s) else None


def descarca(vid):
    for nume in ('maxresdefault', 'sddefault', 'hqdefault'):
        try:
            req = urllib.request.Request(f'https://i.ytimg.com/vi/{vid}/{nume}.jpg', headers={'User-Agent': 'Mozilla/5.0'})
            data = urllib.request.urlopen(req, timeout=20).read()
            img = Image.open(io.BytesIO(data)).convert('RGB')
            if img.width >= 480:
                return img
        except Exception:
            continue
    sys.exit('Nu am putut descărca imaginea clipului.')


def decupeaza(img):
    # hqdefault/sddefault au benzi negre sus și jos (4:3 cu imagine 16:9): le tăiem
    if abs(img.width / img.height - 4 / 3) < 0.02:
        h = int(img.width * 9 / 16)
        top = (img.height - h) // 2
        img = img.crop((0, top, img.width, top + h))
    r = max(W / img.width, H / img.height)
    img = img.resize((round(img.width * r), round(img.height * r)), Image.LANCZOS)
    x, y = (img.width - W) // 2, (img.height - H) // 2
    return img.crop((x, y, x + W, y + H))


def font(m):
    for f in sorted((DIR / 'fonturi').glob('*')):
        if f.suffix.lower() in ('.ttf', '.otf') and ('bold' in f.name.lower() or 'black' in f.name.lower()):
            return ImageFont.truetype(str(f), m)
    try:
        return ImageFont.truetype('DejaVuSans-Bold.ttf', m)
    except Exception:
        return ImageFont.load_default()


def main():
    if len(sys.argv) < 3:
        sys.exit(__doc__)
    vid = vid_id(sys.argv[1])
    if not vid:
        sys.exit('Link YouTube invalid.')
    rubrica = sys.argv[3] if len(sys.argv) > 3 else 'Video'
    img = decupeaza(descarca(vid))
    strat = Image.new('RGBA', (W, H), (0, 0, 0, 0))
    d = ImageDraw.Draw(strat)
    # umbră jos, unde site-ul pune titlul
    for i in range(260):
        d.line([(0, H - i), (W, H - i)], fill=(0, 0, 0, int(150 * (1 - i / 260))))
    # butonul ▶ în centru
    cx, cy, r = W // 2, H // 2 - 30, 62
    d.ellipse((cx - r, cy - r, cx + r, cy + r), fill=(0, 0, 0, 150), outline=(255, 255, 255, 230), width=5)
    d.polygon([(cx - 20, cy - 32), (cx - 20, cy + 32), (cx + 36, cy)], fill=(245, 197, 24, 255))
    # banda cu rubrica, stânga sus
    f = font(30)
    t = '▶ ' + rubrica.upper()
    tw = d.textlength(t, font=f)
    d.rectangle((40, 36, 40 + tw + 36, 36 + 52), fill=(245, 197, 24, 240))
    d.text((58, 44), t, font=f, fill=(10, 20, 50, 255))
    img = Image.alpha_composite(img.convert('RGBA'), strat)
    # sigla EuroGol, dreapta sus
    logo = DIR / 'logo.png'
    if logo.exists():
        lg = Image.open(logo).convert('RGBA')
        lg.thumbnail((190, 70))
        img.alpha_composite(lg, (W - lg.width - 40, 36))
    img.convert('RGB').save(sys.argv[2], 'JPEG', quality=86)
    print('OK', sys.argv[2])


if __name__ == '__main__':
    main()
