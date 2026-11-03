# Seria mare de povești (plan-povesti.json)

Cererea lui George (3 noiembrie 2026): câte o poveste pentru fiecare sezon al Craiovei (1979-80 – 1990-91), al Stelei și al lui Dinamo (toată istoria), o poveste pentru fiecare realizare mare a celorlalte echipe, câte un sumar pentru fiecare an din 1990 până azi și câte o poveste pentru fiecare jucător care a fost într-un lot al naționalei la un Campionat Mondial sau European.

## Cum se scrie o poveste din plan

1. Ia subiectul din `plan-povesti.json` (câmpul `subiect`). Citește `STIL.md` (secțiunea Povești) și `INSTRUCTIUNI.md` (formatul).
2. **Documentare.** Surse serioase: RSSSF (rsssf.org), labtof.ro, romaniansoccer.ro, eu-football.info, worldfootball.net, transfermarkt (doar pentru loturi/date), uefa.com, fifa.com, arhivele GSP/ProSport, site-urile cluburilor, Wikipedia doar ca punct de plecare. **Fiecare scor, clasament, dată, marcator și citat se confirmă în două surse.** Ce nu se poate confirma nu se scrie. Fără citate inventate; citatele doar dacă sunt găsite într-o sursă, atribuite.
3. **Ce conține:**
   - *Sezon de club:* locul final, punctele, golaverajul, antrenorul (antrenorii), oamenii importanți (golgheterul echipei, debutanți, transferuri), meciurile-cheie din campionat, parcursul în Cupa României și în cupele europene (adversari, scoruri), contextul epocii, legătura cu sezonul următor.
   - *Anul (1990 →):* campioana și Cupa, naționala (meciuri, calificări, antrenor), echipele românești în Europa, transferurile importante în străinătate, oamenii anului; un fir narativ, nu o listă seacă.
   - *Jucător al naționalei:* cariera (cluburi, ani, titluri), debutul și numărul de selecții/goluri la națională (din două surse), turneele finale la care a fost și ce a făcut acolo, un moment definitoriu, ce face acum (doar dacă e confirmat).
   - *Realizarea unei alte echipe:* povestea sezonului/meciului, oamenii, contextul.
4. **Lungime:** 1.200–2.000 de cuvinte (sezoanele mai puțin bogate în fapte pot avea 900–1.200; nu umple). Pornește de la o scenă reală, documentată. `<h3>` pentru secțiuni. La final `<p><em>Surse:</em> …</p>` cu linkuri.
5. **Steaua după 2003:** echipa din Liga 1 se numește din 2017 FCSB; CSA Steaua a reluat activitatea din Liga 4. Palmaresul e disputat în instanță: spune neutru, fără să iei partea cuiva. Pentru 1947–2003 numele din epocă: ASA (1947–48), CSCA (1948–50), CCA (1950–61), Steaua (din 1961) – verifică.
6. **Fișierele** (id: `<data de azi>-poveste-<cheie>`, fără diacritice):
   - `cronici/<id>.json` cu `"categorie": "Povești"`, `"serie"`: `Legende` (sezoane, ani, alte echipe) sau `Generația de Aur` (jucători din loturile 1990–2000) sau `Legende` (ceilalți jucători); `etichete`: echipa/naționala, oamenii principali, sezonul (ex. „1985-86”), „Povești”; `"clasament": false`; `data_publicare` = acum (ora României, `AAAA-LL-ZZ hh:mm`); `"anunt": false`; fără `autor`.
   - `cronici/<id>.coperta.json`: `{"titlu": "…", "data": "…", "rubrica": "Povești", "subtitlu": "Craiova, 1980-81", "fundal": "stadion-noaptea"}`. **Fără sigle de club la sezoanele istorice** (siglele actuale ale FCSB/CSU Craiova nu sunt ale echipelor din epocă). Pentru jucători: `"fata": "<slug>"` doar dacă portretul există deja în `unelte/fete/fete.json` sau îl poți adăuga cu `python3 unelte/fete.py wiki …` / `python3 unelte/fotografii.py portret …` (licență liberă, verificat că e omul).
   - `node unelte/coperta-stire.js cronici/<id>.coperta.json cronici/<id>.jpg` și uită-te la imagine.
7. În `plan-povesti.json`: `"stare": "scris"`, `"id": "<id>"` (sau `"sarit"` + `"motiv"` dacă faptele nu pot fi confirmate).
8. `index.json`: id-ul nou la începutul listei `cronici` (`"cronici/<id>.json"`), lista rămâne de cel mult 200.
9. Commit `Povesti: <lista scurta>`, apoi `git fetch origin main && git merge FETCH_HEAD` și push pe `main`.
