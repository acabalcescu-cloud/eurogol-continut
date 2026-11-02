# Poveștile Fotbalului – conținut automat

Știrile, cronicile și poveștile pentru **Poveștile Fotbalului** (nume de lucru): fotbal românesc, european și sud-american, spus ca o poveste.

Funcționează la fel ca povestilestiintei.ro: site-ul WordPress preia singur, la 15 minute, ce apare în acest depozit.

- `cronici/` – articolele (JSON), coperțile (JPG) și datele coperților. Numele folderului rămâne `cronici` pentru că așa îl citește importul din temă.
- `index.json` – lista articolelor pe care site-ul le preia (`cronici`) și a celor actualizate (`actualizate`).
- `STIL.md` – vocea site-ului.
- `ACOPERIRE.md` – ce competiții acoperim, în ce ordine și câte articole pe zi.
- `INSTRUCTIUNI.md` – pașii rutinelor: știrile orare, cronicile de noapte și poveștile săptămânale.
- `presa/vazute.json` – sursele deja citite, ca să nu le tratăm de două ori.
- `mac/` – verificarea orară a știrilor, fără AI. Pornește Claude Code numai când apare ceva important.
- `unelte/` – generatorul de coperte (`coperta-stire.js`), decodorul Google News (`gndecode.py`) și cititorul de articole (`citeste.py`).

## Pornire

1. Creează pe GitHub depozitul `povestilefotbalului-continut`, public, ca să-l poată citi site-ul prin raw.githubusercontent.com.
2. Pune în el conținutul acestui folder.
3. Pe noul WordPress instalează tema părinte (Atleticos, cu licență separată pentru noul domeniu) și tema copil adaptată (vezi `tema/README.md`).
4. Pe Mac: `mac/README.md`.
