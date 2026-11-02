# Tema Eurogol

Tema WordPress a site-ului eurogol.ro, scrisă de la zero, fără temă părinte și fără module obligatorii.

- `eurogol/` – sursa temei.
- `eurogol.zip` – arhiva gata de instalat. Se reface cu `bash tema/construieste.sh` după fiecare modificare.

## Cum arată
- Fundal colorat pe toată pagina și conținutul într-o cutie la mijloc, ca la sempremilan.com.
- Sus: un articol mare și patru mai mici, cu titlul peste imagine.
- Secțiuni pe categorii (2 carduri mari, 4 mici și butoanele „Citește mai mult”).
- Coloana din dreapta: „Pe scurt” (cu ora sau ziua), „Urmărește-ne” și „Competiții”.
- Pe telefon: meniu lateral, logo la mijloc, căutare în dreapta.
- Datele sunt mereu în română („luni, 2 noiembrie 2026”), indiferent de limba WordPress-ului.

## Instalare
1. WordPress → **Aspect → Teme → Adaugă temă → Încarcă tema** → alege `eurogol.zip` → **Instalează** → **Activează**.
2. **Setări → Legături permanente** → „Nume articol” → Salvează.
3. **Setări → Generale**: fusul orar „București”, limba „Română”.
4. **Instrumente → Import Eurogol**: depozitul e deja completat (`acabalcescu-cloud/eurogol-continut`). Apasă **Importă acum** pentru prima rulare; apoi importul rulează singur la 15 minute.
5. **Aspect → Personalizează → Eurogol**: culoarea principală, textul benzii, secțiunile primei pagini, lista de competiții și rețelele sociale. Culoarea implicită e albastru `#1546A8`; pentru roșu ca la SempreMilan, pune `#C41529`.
6. **Aspect → Meniuri**: meniul principal, cu submeniuri (de ex. „Europa” → Champions League, Premier League…), și meniul din subsol (Despre noi, Contact, Confidențialitate).

**Important:** WP-Cron rulează doar când cineva vizitează site-ul. Ca importul să meargă la 15 minute și noaptea, pune la găzduire un cron real:
`*/5 * * * * curl -s https://eurogol.ro/wp-cron.php?doing_wp_cron > /dev/null`, apoi adaugă în `wp-config.php` linia `define( 'DISABLE_WP_CRON', true );`.

## Importul
`inc/import.php` citește `index.json` din depozit și creează articolele: titlul, rezumatul, conținutul, categoria (creată dacă lipsește), etichetele, data și coperta ca imagine reprezentativă.
- `"status": "draft"` → ciornă.
- `data_publicare` în viitor → articol programat.
- Legăturile `ps:<id>` / `eg:<id>` devin linkuri reale abia după ce articolul-țintă e publicat; până atunci rămâne doar textul.
- Lista `actualizate` din `index.json` înlocuiește textul articolelor publicate, când câmpul `actualizat` se schimbă.

Manual: Instrumente → Import Eurogol → „Importă acum”, sau `POST /wp-json/eg/v1/import` (administratori).

## Slug-urile categoriilor
Categoria vine din câmpul `categorie` al articolului. Slug-ul se face automat, fără diacritice: „Povești” → `povesti`, „Știri” → `stiri`, „Champions League” → `champions-league`, „Brasileirão” → `brasileirao`. Secțiunile primei pagini și lista „Competiții” folosesc aceste slug-uri.

## Coperțile
Pe site, titlul stă peste imagine, deci coperțile nu au titlul scris pe ele. Sunt afișe cu rubrica scrisă mare: `node unelte/coperta-stire.js <date.json> <iesire.jpg>`, cu `{ "titlu", "rubrica", "subtitlu", "data" }`. Detalii în `INSTRUCTIUNI.md`.
