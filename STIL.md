# Vocea – Poveștile Fotbalului

Un povestitor care iubește fotbalul, nu o agenție de presă și nu un suporter al unei singure echipe. Scrie pentru cititorul român care se uită la SuperLiga sâmbăta, la Champions League marțea și la Libertadores noaptea.

## Ton
- **Cald, curios, imparțial.** Ne bucurăm de fotbalul frumos oricine l-ar juca. Nu ținem cu nimeni pe față. Nu folosim „noi”, „ai noștri”, „băieții noștri”, cu o singură excepție: **naționala României** („tricolorii”, „naționala noastră”).
- **Povestea înaintea cifrelor.** Fiecare articol are un fir: un om, o revanșă, o serie, o întoarcere. Cifrele susțin povestea, nu o înlocuiesc.
- **Puntea românească.** Când există, spunem legătura cu România: stranierii, antrenorii români din străinătate, adversarii echipelor noastre în cupele europene, foștii jucători din SuperLiga, meciurile istorice cu echipe românești.
- **Respect.** Fără atacuri la jucători, arbitri, cluburi sau suporteri. Fără limbaj vulgar. Fără ironii pe seama înfrângerilor.
- **Fără clickbait.** Titlul promite exact ce aduce articolul.
- Finalul privește înainte: următorul meci, ce e în joc, ce urmărim. Fără formulă fixă de încheiere.

## Tipuri de articole

### 1. Știri (`"categorie": "Știri"` + subcategoria competiției în `etichete`)
Transferuri, accidentări, antrenori, declarații, decizii UEFA/FRF/LPF, recorduri.
- **Lungime: 500–900 de cuvinte.** Mai scurt decât la povestilestiintei, pentru că volumul e mai mare. Lungimea vine numai din fapte și context, niciodată din umplutură.
- **Structură:** titlu · 1–2 fraze cu vestea · 2–3 secțiuni `<h3>` (ce s-a întâmplat, cine e omul/contextul, ce înseamnă) · un final care privește înainte.

### 2. Cronici (`"categorie"`: competiția, ex. „SuperLiga”, „Champions League”, „Premier League”, „Copa Libertadores”)
Doar pentru meciurile alese în `ACOPERIRE.md`, nu pentru toate meciurile.
1. **Titlu** – scurt, cu emoție.
2. **Linia de rezultat** (paragraf separat, bold): `Gazde – Oaspeți X-Y (pauză), Competiție, etapa/faza`.
3. **Deschidere** – ce a însemnat meciul.
4. **Prima repriză**, **a doua repriză** – ocazii, goluri cu minute, momente-cheie.
5. **„Ce înseamnă rezultatul”** – clasament, calcule, următorul meci.
Lungime: 400–650 de cuvinte.

### 3. Cronica etapei (`"categorie"`: competiția)
Un singur articol pentru o etapă întreagă dintr-un campionat (ex. „Etapa 15 în Serie A: …”): rezultatele, 2–3 meciuri povestite mai pe larg, clasamentul de sus și de jos. 600–1.000 de cuvinte. Se folosește pentru campionatele unde nu scriem cronică la fiecare meci.

### 4. Povești (`"categorie": "Povești"`)
Inima site-ului: legende, meciuri istorice, derby-uri, stadioane, rivalități, „ce s-a întâmplat cu…”, aniversări (ex. „Steaua – Barcelona, Sevilla 1986” sau „Maradona la Napoli”).
- **Lungime: 1.200–2.500 de cuvinte.**
- Pornește de la o scenă, apoi spune povestea în ordine, cu oameni, locuri și date.
- Fiecare fapt (scor, dată, marcator, citat) se verifică în cel puțin două surse serioase (arhive de presă, RSSSF, site-urile oficiale ale cluburilor și federațiilor, cărți citate cu titlu și autor). Ce nu se poate verifica nu se scrie sau se prezintă ca legendă („se spune că…”).
- Poveștile legate de calendar (aniversări, derby-ul săptămânii, adversarul european al unei echipe românești) au prioritate.

## Fapte
- Faptele (scoruri, transferuri, sume, accidentări, decizii) se confirmă în **cel puțin două surse**.
- **Declarațiile, interviurile și editorialele** se pot scrie dintr-o singură sursă, cu citate scurte, exacte și atribuite („a declarat pentru Marca”).
- **Zvonuri de transfer:** doar din două surse serioase, prezentate ca zvon („potrivit presei italiene”), fără sume neconfirmate.
- Sursele străine (spaniolă, portugheză, italiană, engleză etc.) se traduc cu grijă. Citatele traduse se marchează: „a spus, potrivit Olé”.
- Când sursele se contrazic, scriem varianta sigură sau omitem detaliul. Nimic inventat.

## Scrise de la zero
Faptele vin din surse. Textul, ordinea ideilor, unghiul și titlul sunt ale noastre. Nu reformulăm frază cu frază un articol și nu îi păstrăm structura. Când mai multe surse au același subiect, le adunăm într-un singur articol.

## Nume și limbă
- Diacritice corecte (ș, ț cu virgulă).
- Numele cluburilor în forma folosită în presa română: „Bayern München”, „Inter”, „Atlético Madrid”, „Boca Juniors”, „FCSB”, „Dinamo”, „Rapid”.
- Numele jucătorilor cu diacriticele lor originale („Vinícius Júnior”, „Dušan Vlahović”), prenume + nume la prima apariție.
- Competițiile: SuperLiga, Cupa României, Champions League, Europa League, Conference League, Premier League, LaLiga, Serie A, Bundesliga, Ligue 1, Copa Libertadores, Copa Sudamericana, Brasileirão, Liga Profesional (Argentina).

## Format HTML (`continut`)
Doar `<p>`, `<strong>`, `<em>`, `<h3>`, `<ul>/<li>`, `<a>`. Fără stiluri inline, fără imagini în text.
- Un `<h3>` e urmat mereu de cel puțin un paragraf.
- La final: `<p><em>Surse:</em> …</p>` cu linkuri.
- Legături interne: `<a href="ps:<id>">text</a>` spre articolele noastre (verifică întâi că fișierul există în `cronici/`).
