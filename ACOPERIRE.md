# Ce acoperim și în ce ordine

Nu putem scrie despre tot. Prioritățile de mai jos hotărăsc ce intră, ce se grupează și ce se ignoră. Verificarea automată (`mac/verifica.py`) folosește aceleași niveluri din `mac/config.json`.

## Nivelul 1 – România (mereu)
- **SuperLiga**: cronică la meciurile mari (derby-uri: FCSB, Dinamo, Rapid, CFR Cluj, Universitatea Craiova, U Cluj, Farul) și **cronica etapei** pentru restul.
- **Naționala României**: cronică la fiecare meci oficial, știri la convocări.
- **Echipele românești în cupele europene**: cronică la fiecare meci.
- **Stranierii** (lista la zi în `mac/config.json`, ex. Radu Drăgușin, Ianis Hagi, Răzvan Marin, Nicolae Stanciu): știri când joacă un rol (gol, transfer, accidentare, convocare).
- **Antrenorii români din străinătate** (ex. Cristi Chivu la Inter, Răzvan Lucescu). Lista la zi stă în `mac/config.json`.
- Cupa României: din optimi încolo.

## Nivelul 2 – Europa mare
- **Champions League**: cronică la meciurile mari din faza principală și la toate meciurile din eliminatorii.
- **Europa League / Conference League**: când joacă o echipă românească (nivelul 1) sau din faza sferturilor încolo.
- **Top 5 campionate** (Premier League, LaLiga, Serie A, Bundesliga, Ligue 1): **o cronică a etapei pe campionat** (luni) și cronică separată doar pentru derby-urile mari (El Clásico, Derby della Madonnina, Der Klassiker, North London Derby, Le Classique…).
- Știri: transferuri confirmate de top, schimbări de antrenor la cluburile mari, accidentări grave ale vedetelor, Balonul de Aur, recorduri.
- Echipele naționale mari la Euro / Mondiale / Nations League.

## Nivelul 3 – America de Sud
- **Copa Libertadores** din optimi încolo: cronică. Finala: cronică + poveste.
- **Brasileirão** și **Liga Profesional (Argentina)**: cronica etapei o dată la două săptămâni și derby-urile (Superclásico Boca – River, Fla-Flu, Grenal, Paulista).
- Preliminariile CONMEBOL și Copa América.
- Știri doar când e vorba de vedete, tinerii vânduți în Europa sau legături cu România.

## Nivelul 4 – Povești (săptămânal)
- **2–3 povești pe săptămână**, legate de calendar: aniversări (din `calendar-povesti.json`), derby-ul săptămânii, adversarii europeni ai echipelor românești, legende care împlinesc ani.
- Seriile: „Legende”, „Meciuri care n-au murit niciodată”, „Stadioane”, „Ce s-a întâmplat cu…”, „Generația de Aur”, „Derby-uri”.

## Limite pe zi
| | Țintă | Maximum |
|---|---|---|
| Știri | 8–12 | 20 |
| Cronici + cronici de etapă | după calendar | 10 |
| Povești | 2–3 pe săptămână | 1 pe zi |

O rutină orară scrie **cel mult 3 articole**. Când sunt mai multe subiecte, întâi nivelul 1, apoi 2, apoi 3. Restul se lasă pentru ora următoare sau se grupează („Transferurile zilei în Europa”).

## Ce nu intră
Fotbalul feminin, futsalul, ligile inferioare și restul lumii, **doar până când hotărâm altfel**. Nu intră nici: pariurile și cotele, bârfele despre viața personală, disputele juridice în detaliu.
