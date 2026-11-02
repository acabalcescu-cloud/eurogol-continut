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

- **Știri:** `<AAAA-LL-ZZ>-stire-<subiect>`. `data_publicare` e ora evenimentului sau a primei surse, niciodată în viitor.
- **Cronici:** `<AAAA-LL-ZZ>-<gazde>-<oaspeti>`, plus câmpul `meci`: `{ "data", "scor": [gazde, oaspeti], "marcatori": { "gazde": [{"nume","minut"}], "oaspeti": [...] } }`. Numărul de goluri trebuie să dea exact scorul.
- **Cronici de etapă:** `<AAAA-LL-ZZ>-etapa-<nr>-<competitie>` (ex. `2026-11-03-etapa-11-serie-a`), fără câmpul `meci`.
- **Povești:** `<AAAA-LL-ZZ>-poveste-<subiect>`, `"categorie": "Povești"`, plus `"serie": "Legende | Meciuri care n-au murit niciodată | Stadioane | Ce s-a întâmplat cu… | Generația de Aur | Derby-uri"`.

**Coperta:** `cronici/<id>.coperta.json` = `{ "titlu", "rubrica", "data" }`, unde rubrica e competiția sau seria. Apoi `node unelte/coperta-stire.js cronici/<id>.coperta.json cronici/<id>.jpg`. Culoarea copertei se alege după rubrică. Uită-te la imagine înainte de commit.

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
