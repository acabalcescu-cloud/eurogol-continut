# Instrucțiuni pentru rutinele automate

Trei rutine scriu în acest depozit. Site-ul preia la 15 minute tot ce apare în `index.json`.

| Rutina | Când | Ce face |
|---|---|---|
| Știrile | la fiecare oră, pe Mac (`mac/verifica.py`) | cel mult 3 știri, numai dacă verificarea fără AI găsește ceva important |
| Cronicile | în fiecare noapte la 01:13 (ora României) | cronicile meciurilor alese din ziua precedentă + cronicile de etapă |
| Poveștile | luni, miercuri și vineri la 10:23 | o poveste lungă, legată de calendar |

Toate urmează `STIL.md` (vocea) și `ACOPERIRE.md` (ce intră și în ce ordine).

## Formatul articolului

`cronici/<id>.json`, cu `<id>` fără diacritice, cu cratime:

```json
{
  "id": "<id>",
  "titlu": "…",
  "rezumat": "1-2 fraze pentru lista de articole",
  "continut": "<p>…</p>",
  "categorie": "Știri | Povești | SuperLiga | Naționala | Champions League | Europa League | Conference League | Premier League | LaLiga | Serie A | Bundesliga | Ligue 1 | Copa Libertadores | Brasileirão | Liga Profesional",
  "etichete": ["<competiția>", "<echipele>", "<oamenii principali>", "<sezonul, ex. 2026/2027>"],
  "imagine": "<id>.jpg",
  "data_publicare": "AAAA-LL-ZZ hh:mm",
  "anunt": false
}
```

- **Știri:** `<AAAA-LL-ZZ>-stire-<subiect>`. `categorie` = competiția despre care e știrea („SuperLiga”, „Serie A”, „Champions League”…), ca să apară în secțiunea ei; „Știri” doar când nu ține de nicio competiție (ex. un român în Qatar). Site-ul adaugă oricum și categoria competiției, după echipele din text. `data_publicare` e ora evenimentului sau a primei surse, niciodată în viitor.
- **Cronici:** `<AAAA-LL-ZZ>-<gazde>-<oaspeti>`, plus câmpul `meci`: `{ "data", "scor": [gazde, oaspeti], "marcatori": { "gazde": [{"nume","minut"}], "oaspeti": [...] } }`. Numărul de goluri trebuie să dea exact scorul.
- **Cronici de etapă:** `<AAAA-LL-ZZ>-etapa-<nr>-<competitie>` (ex. `2026-11-03-etapa-11-serie-a`), fără câmpul `meci`.
- **Povești:** `<AAAA-LL-ZZ>-poveste-<subiect>`, `"categorie": "Povești"`, plus `"serie": "Legende | Meciuri care n-au murit niciodată | Stadioane | Ce s-a întâmplat cu… | Generația de Aur | Derby-uri"`.

**Coperta** e un afiș fără titlu (site-ul scrie titlul peste partea de jos a imaginii). `cronici/<id>.coperta.json`:

```json
{ "titlu": "…", "data": "AAAA-LL-ZZ hh:mm",
  "rubrica": "Champions League", "subtitlu": "Feyenoord – Inter",
  "competitie": "champions-league", "cluburi": [2959, 2697], "scor": [0, 2], "fata": "cristi-chivu", "fundal": "stadion-noaptea" }
```
- `rubrica` – textul de pe bandă și culoarea copertei: competiția („SuperLiga”, „Champions League”) sau departamentul („Transferuri”, „Declarații”, „Povești”).
- `subtitlu` – un rând scurt, cel mult 25 de caractere: etapa, omul, locul și anul. Nu apare când e scor.
- `competitie` – slug din `unelte/competitii.json` (emblema competiției). Pune-l mereu când știrea ține de o competiție.
- `cluburi` – ID-urile Sofascore ale cluburilor (1 sau 2), din `unelte/cluburi.tsv` (`python3 unelte/sigla.py id "FCSB"`). Club nou: `python3 unelte/sigla.py cauta "Nume"`, apoi `python3 unelte/sigla.py adauga <id> "Nume" "alias1,alias2"` și verifică sigla. La cronici: gazdele primele.
- `scor` – doar la cronici și la știrile despre un rezultat.
- `fata` – portretul omului din centrul știrii (vezi mai jos). Când e portret, pune cel mult un club.
- `fundal` – o fotografie de atmosferă (stadion, tribună, gazon, minge) din `unelte/fotografii/fotografii.json`. Refolosește fundalurile existente; adaugă unul nou doar când e potrivit subiectului (stadionul meciului, de exemplu).
- Apoi `node unelte/coperta-stire.js cronici/<id>.coperta.json cronici/<id>.jpg`. **Uită-te la imagine** înainte de commit.

**Portretele și fotografiile.** Doar fotografii cu licență liberă sau primite cu acord. Nu folosi niciodată fotografii de agenție sau de pe site-urile de știri.
- Căutare în toate sursele (Openverse = Flickr Creative Commons + Wikimedia Commons; Unsplash, Pexels, Pixabay dacă există chei): `python3 unelte/fotografii.py cauta "Cristian Chivu"`. Rezultatele sunt numerotate, cu licența, autorul și adresa.
- Portret dintr-un rezultat: `python3 unelte/fotografii.py portret <nr> "Nume Prenume" ["alias"]`. Sau direct din Wikipedia: `python3 unelte/fete.py wiki "Nume Prenume" "Titlul paginii" [ro|en] ["alias"]`. Rezultatul: `unelte/fete/<slug>.png` plus intrarea din `unelte/fete/fete.json` (credit, sursă, fotografia originală).
- Fundal de copertă: `python3 unelte/fotografii.py cauta "football stadium night" --surse unsplash,pexels,pixabay,openverse`, apoi `python3 unelte/fotografii.py fundal <nr> "nume-scurt"` → `unelte/fotografii/`.
- Verifică fotografia: omul trebuie să fie chiar cel căutat (pe Unsplash, Pexels și Pixabay aproape niciodată nu e), iar fundalul să nu arate sigle de sponsori sau oameni prezentați drept altcineva.
- Verifică `unelte/fete/fete.json` înainte: dacă omul are deja portret, folosește-l.
- În articol: `"fata": "<slug>"` (portretul de pe copertă), opțional `"persoane": ["<slug>", …]` (cel mult 2) și `"fundal": "<slug>"` (aceleași valori ca în coperta.json). Site-ul pune singur fotografia în articol, după al doilea paragraf, cu autorul și licența, plus creditele copertei la final. Nu scrie tu creditele.
- Dacă pagina Wikipedia nu are o fotografie liberă, coperta rămâne fără portret: sigle și emblemă.

**Clasamentul** apare singur la finalul articolului (date LiveScore): site-ul găsește competiția după categorie, etichete sau echipele din titlu și evidențiază echipele pomenite. Când competiția nu reiese clar (ex. o știre despre un jucător, cu categoria „Știri”), pune `"clasament": "<slug>"` – unul dintre `superliga`, `premier-league`, `laliga`, `serie-a`, `bundesliga`, `ligue-1`, `champions-league`, `europa-league`, `conference-league`, `brasileirao`, `liga-profesional`. `"clasament": false` = fără clasament (ex. o știre despre echipa națională). Poveștile nu au clasament.

**Rezumatul video** (la cronici și la știrile despre un meci): `"video": "https://www.youtube.com/watch?v=<ID>"` și `"video_sursa": "<canalul>"`. Doar rezumatul oficial: Orange Sport, Prima Sport, Digi Sport, SuperLiga România (SuperLiga); canalele oficiale ale competițiilor și cluburilor (Serie A, LaLiga, Bundesliga, UEFA etc.). Verifică prin oEmbed (`https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v=<ID>&format=json`): canalul trebuie să fie unul oficial, iar titlul să aibă ambele echipe și scorul meciului (atenție la meciuri mai vechi cu același scor). Fără clipuri de la fani sau Shorts. Dacă nu există încă, rutina de noapte îl adaugă mai târziu, cu `"actualizat"` și lista `"actualizate"` din `index.json`.

**Jucătorii și antrenorii („În acest articol”).** Site-ul găsește singur, în text, oamenii din `unelte/persoane/persoane.json`, le pune bulinele după primul paragraf și pe imaginea de sus și îi adaugă ca etichete. Pentru un om important din știre care nu e în registru: `python3 unelte/persoane.py cauta "Nume Prenume"`, apoi `python3 unelte/persoane.py adauga <nr> "Nume Prenume" jucator|antrenor "Club" ["alias"]` (doar fotografii cu licență liberă; uită-te la fotografie: trebuie să fie chiar omul căutat). La prima pomenire scrie numele complet.

**Semnătura:** nu pune câmpul `autor`. Site-ul alege singur redactorul după subiect (`tema/eurogol/inc/redactori.php`): SuperLiga – Andrei Toma, Naționala și stranierii – Răzvan Petrescu, Cupele europene – Mihai Georgescu, Marile campionate – Vlad Ene, Transferuri – Cătălin Dumitru, America de Sud – Raul Neagu, Povești – Sorin Avram, Declarații și opinii – Cristian Manolache. Câmpul `autor: { login, nume }` se folosește doar pentru texte trimise de oameni reali, care semnează cu numele lor.

**`index.json`:** fiecare articol nou se adaugă la începutul listei `cronici` (cel mult 200). Articolele completate intră în lista `actualizate`.

## Rutina orară de știri

Promptul complet e în `mac/prompt.md`. Pe scurt:
1. `mac/verifica.py` citește, fără AI, Google News (16 căutări în română) și fluxurile RSS românești și străine din `mac/config.json`. Dă fiecărui titlu un scor (nivelul din ACOPERIRE.md + câte publicații scriu despre același subiect) și pornește Claude numai dacă cel mai bun scor trece de prag.
2. Claude grupează pe subiecte, alege cel mult 3 și scrie știrile.
3. Sursele folosite sau respinse se trec în `presa/vazute.json` (`{ "<url>": "<id> | ignorat" }`).

**Surse:** presa română (GSP, ProSport, Sport.ro, Digi Sport, Fanatik, Golazo, Antena Sport) și, pentru confirmare, presa din țara subiectului: BBC Sport, The Athletic (Anglia), Marca, AS, Mundo Deportivo (Spania), Gazzetta dello Sport, Corriere dello Sport (Italia), Kicker (Germania), L'Équipe (Franța), ge.globo (Brazilia), Olé, TyC Sports (Argentina). Plus site-urile oficiale: UEFA, CONMEBOL, FRF, LPF, cluburile. Linkurile Google News se decodează cu `python3 -I unelte/gndecode.py <link>`.

## Rutina de noapte: cronicile

1. **Ce meciuri s-au jucat ieri?** Caută pe web (Flashscore, Sofascore, site-urile competițiilor) rezultatele din competițiile din ACOPERIRE.md.
2. **Alege** după ACOPERIRE.md: meciurile cu cronică separată (derby-uri, naționala, echipele românești în Europa, Champions League în eliminatorii, Libertadores din optimi), apoi campionatele care au terminat etapa și primesc cronica etapei (top 5 luni dimineața, SuperLiga după ultimul meci al etapei, Brazilia și Argentina o dată la două săptămâni).
3. **Există deja?** Dacă `cronici/<data>-<gazde>-<oaspeti>.json` sau cronica etapei există, sari peste.
4. **Faptele** din cel puțin două surse: scorul final și la pauză, marcatorii cu minute, eliminările, momentele-cheie (VAR, penalty-uri), clasamentul, următorul meci.
5. **Scrie** după STIL.md, apoi coperta, `index.json`, commit și push: `Cronici: <lista scurtă>`.
6. **Maximum 10 articole** pe noapte. Dacă sunt mai multe, păstrează ordinea priorităților și lasă restul.

## Rutina de povești

1. Uită-te în `calendar-povesti.json` și în programul săptămânii: o aniversare rotundă (10, 20, 25, 30… ani), un derby important în weekend sau adversarul european al unei echipe românești.
2. Alege **un subiect** care nu a mai fost scris (caută în `cronici/*-poveste-*.json`).
3. Strânge faptele din surse serioase: arhive de presă, RSSSF, site-urile oficiale, Wikipedia doar ca punct de plecare, niciodată ca singură sursă. Fiecare scor, dată și citat se confirmă în două surse.
4. Scrie 1.200–2.500 de cuvinte după STIL.md, cu o scenă la început și cu legătura spre prezent la final.
5. Commit: `Poveste: <titlu>`.

## Reguli comune
- Nimic inventat. Ce nu se poate confirma nu se scrie.
- Textul e scris de la zero. Nu copiem fraze din surse și nu urmăm structura unui articol-sursă.
- Înainte de commit verifică încă o dată numele, cifrele, datele și zilele săptămânii.
- Dacă faptele nu pot fi confirmate, nu face commit și spune asta în raport.
