# Știrile pe Mac

`mac/verifica.py` rulează la fiecare oră, la minutul 41, fără AI. Citește Google News și fluxurile RSS din `mac/config.json`, dă fiecărui titlu un scor și pornește Claude Code (`claude -p` cu `mac/prompt.md`) numai dacă apare ceva important. Claude scrie cel mult 3 știri, le salvează în depozit și le trimite pe GitHub. Site-ul le preia în cel mult 15 minute.

**Scorul** = ponderea nivelului (România 3, Europa 2, America de Sud 1) + câte publicații diferite scriu despre același lucru (maximum 6). Claude pornește doar dacă cel mai bun scor e cel puțin `prag_pornire`. Candidații sunt împărțiți pe niveluri (`cote_nivel`), ca România să nu ocupe toate locurile.

**Reglaje** (toate în `mac/config.json`): cuvintele-cheie pe niveluri (stranierii și antrenorii români din străinătate se țin la zi de mână), căutările Google News, fluxurile românești și străine, cuvintele de ignorat.

## Instalare (o singură dată)
Dacă pe Mac rulează deja rutinele de la povestilestiintei.ro, programele necesare (git, node, gh, python3, Claude Code) sunt instalate. În Terminal:

```
cd ~
git clone https://github.com/acabalcescu-cloud/eurogol-continut.git
cd eurogol-continut
npm install
npx playwright install chromium
python3 mac/verifica.py --doar-lista
bash mac/instaleaza.sh
```

- `npm install` și `npx playwright install chromium` – pentru coperți.
- `pip3 install numpy pillow opencv-python-headless rembg` – pentru portrete (`unelte/fete.py`); dacă rulează deja la povestilestiintei, sunt instalate.
- Opțional, cheile gratuite pentru fotografii (Unsplash, Pexels, Pixabay), în `~/.config/eurogol/chei.env`, câte una pe rând: `UNSPLASH_ACCESS_KEY=…`, `PEXELS_API_KEY=…`, `PIXABAY_API_KEY=…`. Fără ele, `unelte/fotografii.py` caută doar în Openverse (Flickr + Wikimedia Commons). Fișierul nu intră în depozit.
- `python3 mac/verifica.py --doar-lista` – arată candidații și scorurile, fără să pornească Claude.
- `bash mac/instaleaza.sh` – pornește verificarea orară (la minutul 41; povestilestiintei rulează la 17 și 47, deci nu se suprapun).

Pe un Mac nou: `brew install git node gh python`, `npm install -g @anthropic-ai/claude-code` (apoi o rulare `claude` pentru login) și `gh auth login` cu un cont care are drept de scriere în depozit.

Jurnalul: `.stare-mac/jurnal.log`. Ultimul raport al lui Claude: `.stare-mac/ultimul-raport.txt`.
Oprire: `launchctl bootout gui/$(id -u) ~/Library/LaunchAgents/ro.eurogol.stiri.plist`.

## Rutinele de noapte și de povești
Cronicile (01:13) și poveștile (luni, miercuri, vineri la 10:23) rulează ca sesiuni programate Claude Code, cu pașii din `INSTRUCTIUNI.md`.
