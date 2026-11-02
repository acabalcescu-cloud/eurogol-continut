# Știrile pe Mac

`mac/verifica.py` rulează la fiecare oră, la minutul 41, fără AI. Citește Google News și fluxurile RSS din `mac/config.json`, dă fiecărui titlu un scor și pornește Claude Code (`claude -p` cu `mac/prompt.md`) numai dacă apare ceva important. Claude scrie cel mult 3 știri, le salvează în depozit și le trimite pe GitHub. Site-ul le preia în cel mult 15 minute.

**Scorul** = ponderea nivelului (România 3, Europa 2, America de Sud 1) + câte publicații diferite scriu despre același lucru (maximum 6). Claude pornește doar dacă cel mai bun scor e cel puțin `prag_pornire`. Candidații sunt împărțiți pe niveluri (`cote_nivel`), ca România să nu ocupe toate locurile.

**Reglaje** (toate în `mac/config.json`): cuvintele-cheie pe niveluri (stranierii și antrenorii români din străinătate se țin la zi de mână), căutările Google News, fluxurile românești și străine, cuvintele de ignorat.

## Instalare (o singură dată)
1. `brew install git node gh python`, apoi `npm install -g @anthropic-ai/claude-code` și o rulare `claude` pentru login.
2. `gh auth login`, cu un cont care are drept de scriere în depozitul de conținut.
3. `git clone https://github.com/<cont>/eurogol-continut.git && cd eurogol-continut`
4. `npm install playwright && npx playwright install chromium` (pentru coperți).
5. `python3 mac/verifica.py --doar-lista` – arată candidații și scorurile, fără să pornească Claude.
6. `bash mac/instaleaza.sh` – pornește verificarea orară.

Jurnalul: `.stare-mac/jurnal.log`. Ultimul raport al lui Claude: `.stare-mac/ultimul-raport.txt`.

## Rutinele de noapte și de povești
Cronicile (01:13) și poveștile (luni, miercuri, vineri la 10:23) rulează ca sesiuni programate Claude Code, cu pașii din `INSTRUCTIUNI.md`.
