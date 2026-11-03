#!/usr/bin/env python3
"""Bulinele cu jucători și antrenori („În acest articol”), ca la Poveștile Științei.

Registrul: unelte/persoane/persoane.json  – [{slug, nume, alias[], rol, club, fisier, credit, sursa, licenta_url}]
Fotografiile: unelte/persoane/<slug>.jpg  – pătrat 360×360, centrat pe față (site-ul le face rotunde).
Doar fotografii cu licență liberă (Openverse = Flickr CC + Wikimedia Commons; CC0, PD, CC BY, CC BY-SA) sau primite cu acord.

  python3 unelte/persoane.py cauta "Cristian Chivu"                # rezultate numerotate (Openverse)
  python3 unelte/persoane.py adauga <nr> "Cristi Chivu" antrenor "Inter" ["Chivu,Cristian Chivu"]
  python3 unelte/persoane.py fisier <cale|url> "Nume" jucator "Club" "Credit" ["alias"]
  python3 unelte/persoane.py lista

rol: jucator | antrenor. Aliasurile sunt variantele după care site-ul recunoaște omul în text (numele de familie
se adaugă singur, dacă e distinctiv). Uită-te la fotografie după `adauga`: omul trebuie să fie chiar cel căutat.
"""
import json, os, re, subprocess, sys, unicodedata, urllib.parse

DIR = os.path.dirname(os.path.abspath(__file__))
PDIR = os.path.join(DIR, 'persoane')
INDEX = os.path.join(PDIR, 'persoane.json')
sys.path.insert(0, DIR)
import fotografii  # noqa: E402

# Nume de familie prea comune ca să fie recunoscute singure.
AMBIGUE = {'popescu', 'ionescu', 'pop', 'marin', 'man', 'radu', 'matei', 'mora', 'stan', 'rus', 'silva', 'santos', 'costa',
           'martinez', 'garcia', 'rodriguez', 'fernandez', 'gomez', 'lopez', 'gonzalez', 'perez', 'alves', 'ferreira',
           'hagi', 'lucescu', 'puscas', 'iordanescu', 'petrescu', 'dumitrescu', 'stanciu', 'coman'}  # tată/fiu, nume des întâlnite


def slug(s):
    s = unicodedata.normalize('NFD', s).encode('ascii', 'ignore').decode().lower()
    return re.sub(r'[^a-z0-9]+', '-', s).strip('-')


def incarca():
    return json.load(open(INDEX, encoding='utf-8')) if os.path.exists(INDEX) else []


def decupeaza(src, dst):
    """Pătrat pe față (detecția OpenCV din fete.py), 360×360, JPEG."""
    from PIL import Image, ImageOps
    import fete
    im = ImageOps.exif_transpose(Image.open(src)).convert('RGB')
    f = fete.fata(im)
    f.resize((360, 360), Image.LANCZOS).save(dst, quality=88, optimize=True)


def salveaza(nume, src, rol, club, credit, sursa, licenta_url='', alias=''):
    os.makedirs(PDIR, exist_ok=True)
    s = slug(nume)
    dst = os.path.join(PDIR, s + '.jpg')
    decupeaza(src, dst)
    parti = nume.split()
    al = [nume] + [a.strip() for a in alias.split(',') if a.strip()]
    if len(parti) >= 2:
        fam = ' '.join(parti[1:])
        if len(fam) >= 4 and slug(fam) not in AMBIGUE:
            al.append(fam)
    idx = [x for x in incarca() if x['slug'] != s]
    idx.append({'slug': s, 'nume': nume, 'alias': sorted(set(al)), 'rol': rol, 'club': club, 'fisier': s + '.jpg',
                'credit': credit, 'sursa': sursa, 'licenta_url': licenta_url})
    json.dump(sorted(idx, key=lambda x: x['slug']), open(INDEX, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    print('OK', dst, '|', credit)


if __name__ == '__main__':
    a = sys.argv[1:]
    if not a:
        sys.exit(__doc__)
    if a[0] == 'cauta':
        fotografii.cauta(a[1], ['openverse'])
    elif a[0] == 'adauga':
        x = fotografii.alege(a[1])
        tmp = os.path.join(PDIR, '_tmp')
        os.makedirs(PDIR, exist_ok=True)
        open(tmp, 'wb').write(fotografii.get(x['url'], binar=True))
        salveaza(a[2], tmp, a[3], a[4], fotografii.credit(x), x['sursa'], x.get('licenta_url', ''), a[5] if len(a) > 5 else '')
        os.remove(tmp)
    elif a[0] == 'fisier':
        src = a[1]
        if src.startswith('http'):
            tmp = os.path.join(PDIR, '_tmp')
            os.makedirs(PDIR, exist_ok=True)
            open(tmp, 'wb').write(fotografii.get(src, binar=True))
            src = tmp
        salveaza(a[2], src, a[3], a[4], a[5], a[1] if a[1].startswith('http') else '', '', a[6] if len(a) > 6 else '')
        if src.endswith('_tmp'):
            os.remove(src)
    elif a[0] == 'lista':
        for x in incarca():
            print(f"{x['slug']:28} {x['rol']:9} {x['club']:20} {x['credit']}")
    else:
        sys.exit(__doc__)
