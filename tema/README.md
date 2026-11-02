# Tema site-ului

Noul site poate folosi aceeași temă copil ca povestilestiintei.ro (`atleticos-child`), cu câteva schimbări:

1. **Tema părinte Atleticos** e comercială: e nevoie de o licență pentru noul domeniu.
2. În `functions.php` din tema copil:
   - `PS_CONTENT_REPO` → depozitul nou (ex. `<cont>/povestilefotbalului-continut`);
   - adresa de la `/ps/v1/tema` (linia cu `raw.githubusercontent.com/.../povestilestiintei-continut/main/tema/`) → depozitul nou;
   - categoriile și textele despre Universitatea Craiova (Cronici meciuri, clasamentul SuperLigii, lotul, marcatorii, „Următorul meci”, scorul live) → categoriile din `INSTRUCTIUNI.md`: Știri, Povești și câte o categorie pe competiție.
   - Funcțiile legate de un singur club (lotul, fișele jucătorilor, live-ul pentru un singur meci) se scot sau se lasă oprite la început.
3. Culorile, sigla, imaginile din `assets/` → identitatea noului site (fără stema și pozele Craiovei).

Arhiva temei adaptate se pune aici (`atleticos-child.zip`, `versiune.txt`, `sha256.txt`), ca la povestilestiintei.
