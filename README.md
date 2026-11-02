# Eurogol.ro – conținut automat

Știrile, cronicile și poveștile pentru **Eurogol** (eurogol.ro): fotbal românesc, european și sud-american, spus ca o poveste.

Funcționează la fel ca povestilestiintei.ro: site-ul WordPress preia singur, la 15 minute, ce apare în acest depozit.

- `cronici/` – articolele (JSON), coperțile (JPG) și datele coperților. Numele folderului rămâne `cronici` pentru că așa îl citește importul din temă.
- `index.json` – lista articolelor pe care site-ul le preia (`cronici`) și a celor actualizate (`actualizate`).
- `STIL.md` – vocea site-ului.
- `ACOPERIRE.md` – ce competiții acoperim, în ce ordine și câte articole pe zi.
- `INSTRUCTIUNI.md` – pașii rutinelor: știrile orare, cronicile de noapte și poveștile săptămânale.
- `presa/vazute.json` – sursele deja citite, ca să nu le tratăm de două ori.
- `mac/` – verificarea orară a știrilor, fără AI. Pornește Claude Code numai când apare ceva important.
- `tema/` – tema WordPress a site-ului (sursa și arhiva .zip).
- `unelte/` – generatorul de coperte (`coperta-stire.js`), decodorul Google News (`gndecode.py`) și cititorul de articole (`citeste.py`).

## Pornire

1. Depozitul: `github.com/acabalcescu-cloud/eurogol-continut`. Trebuie să rămână public, ca să-l poată citi site-ul.
2. Pe WordPress instalează tema Eurogol (`tema/eurogol.zip`), scrisă de la zero. Pașii sunt în `tema/README.md`.
3. Pe Mac: `mac/README.md`.
