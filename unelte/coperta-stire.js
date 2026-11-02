// Coperta unui articol, 1200×630 JPG.
// Rulare: node unelte/coperta-stire.js <date.json> <iesire.jpg>
//
// date: {
//   titlu, data: "AAAA-LL-ZZ hh:mm",
//   rubrica:    textul de pe bandă ("Champions League", "Transferuri", "Povești"...); alege și culoarea,
//   subtitlu:   un rând scurt sub bandă (opțional),
//   competitie: slug din unelte/competitii.json ("superliga", "champions-league"...) – emblema competiției (opțional;
//               dacă lipsește, se caută după rubrică),
//   cluburi:    [sofa_id, sofa_id] din unelte/cluburi.tsv – siglele cluburilor (1 sau 2, opțional),
//   scor:       [gazde, oaspeti] – scorul dintre cele două sigle (opțional),
//   fata:       slug din unelte/fete/fete.json – portretul din stânga (opțional),
//   fundal:     slug din unelte/fotografii/fotografii.json – fotografie de fundal (stadion, tribună), întunecată (opțional),
//   cu_titlu:   true = vechea copertă, cu titlul scris pe ea
// }
// Coperta e un AFIȘ: site-ul pune titlul peste partea de jos a imaginii, deci tot ce contează stă în jumătatea de sus.
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const [, , dataFile, outFile] = process.argv;
if (!dataFile || !outFile) {
	console.error('Rulare: node unelte/coperta-stire.js <date.json> <iesire.jpg>');
	process.exit(1);
}

const LUNI = ['ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];
const d = JSON.parse(fs.readFileSync(dataFile, 'utf8'));
const [an, luna, zi] = d.data.slice(0, 10).split('-').map(Number);
const dir = __dirname;
const uri = (f, t) => `data:${t || (f.endsWith('.png') ? 'image/png' : 'image/jpeg')};base64,${fs.readFileSync(path.join(dir, f)).toString('base64')}`;
const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const fara = (s) => String(s || '').normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();
const slug = (s) => fara(s).replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

// [potrivire, culoare de început, culoare de final, accent]
const PALETE = [
	[/superliga|liga 1|cupa romaniei|nationala|tricolor|romania|strani/, '#1B3FA0', '#0A1A4A', '#FFD23F'],
	[/champions|liga campionilor/, '#0E1E5B', '#030A26', '#9FC3FF'],
	[/europa league/, '#E86A10', '#5A1F00', '#FFE0B8'],
	[/conference/, '#0B8F4A', '#022412', '#B8FFD4'],
	[/premier league/, '#4B0F6B', '#1C0330', '#00F0A8'],
	[/laliga|la liga|spania/, '#D0172F', '#4A0610', '#FFD9DE'],
	[/serie a|italia/, '#0F4FA8', '#051B3D', '#7FE3FF'],
	[/bundesliga|germania/, '#C8102E', '#1A1A1A', '#FFFFFF'],
	[/ligue 1|franta/, '#0B2340', '#020A14', '#D9F04A'],
	[/libertadores|sudamericana|conmebol|copa america|brasil|argentin|liga profesional/, '#C9971C', '#3B2400', '#FFF1C2'],
	[/transfer/, '#0B6E6E', '#03282B', '#9FF2E8'],
	[/declarat|opinii|interviu/, '#3A3F4B', '#14171D', '#FFD23F'],
	[/povest|legend|istori|stadion|derby|generatia/, '#5B3A1E', '#1E1108', '#F4D9A6'],
];
const rubrica = d.rubrica || 'Știri';
const [, c1, c2, accent] = PALETE.find(([re]) => re.test(fara(d.competitie || '') + ' ' + fara(rubrica))) || [null, '#0F6B3A', '#06200F', '#C9F5D6'];

// Emblema competiției: din câmpul „competitie” sau, dacă lipsește, după rubrică.
const COMPETITII = JSON.parse(fs.readFileSync(path.join(dir, 'competitii.json'), 'utf8'));
const compCheie = (() => {
	const c = slug(d.competitie || '');
	if (COMPETITII[c]) return c;
	const r = slug(rubrica);
	return COMPETITII[r] ? r : Object.keys(COMPETITII).find((k) => r && r.includes(k)) || '';
})();
const emblema = compCheie && fs.existsSync(path.join(dir, COMPETITII[compCheie].fisier)) ? uri(COMPETITII[compCheie].fisier) : '';

// Siglele cluburilor (unelte/sigle/<sofa_id>.png).
const cluburi = (d.cluburi || []).slice(0, 2).map((id) => {
	const f = path.join('sigle', `${id}.png`);
	if (!fs.existsSync(path.join(dir, f))) {
		console.error(`Atenție: lipsește sigla ${id} (python3 unelte/sigla.py ${id} "Nume club").`);
		return '';
	}
	return uri(f);
}).filter(Boolean);
const scor = Array.isArray(d.scor) && d.scor.length === 2 && cluburi.length === 2 ? d.scor : null;

// Portretul (unelte/fete/<slug>.png, făcut cu fete.py).
const fata = (() => {
	if (!d.fata) return null;
	const lista = JSON.parse(fs.readFileSync(path.join(dir, 'fete', 'fete.json'), 'utf8'));
	const x = lista.find((p) => p.slug === d.fata);
	if (!x) {
		console.error(`Atenție: nu există portretul „${d.fata}” în unelte/fete/fete.json (python3 unelte/fete.py wiki ...).`);
		return null;
	}
	return { ...x, src: uri(path.join('fete', x.fisier)) };
})();

// Fotografia de fundal (unelte/fotografii/<slug>.jpg, făcută cu fotografii.py).
const fundal = (() => {
	if (!d.fundal) return '';
	const idx = path.join(dir, 'fotografii', 'fotografii.json');
	const x = fs.existsSync(idx) ? JSON.parse(fs.readFileSync(idx, 'utf8')).find((p) => p.slug === d.fundal) : null;
	if (!x) {
		console.error(`Atenție: nu există fundalul „${d.fundal}” (python3 unelte/fotografii.py fundal ...).`);
		return '';
	}
	return uri(path.join('fotografii', x.fisier), 'image/jpeg');
})();

const afis = `<!doctype html><html><head><meta charset="utf-8"><style>
@font-face { font-family: Sofia; font-weight: 800; src: url(${uri('fonturi/sofia-sans-extra-condensed-latin-800-normal.woff2', 'font/woff2')}); }
@font-face { font-family: Sofia; font-weight: 800; src: url(${uri('fonturi/sofia-sans-extra-condensed-latin-ext-800-normal.woff2', 'font/woff2')}); unicode-range: U+0100-024F, U+0218-021B; }
* { margin: 0; box-sizing: border-box; }
body { width: 1200px; height: 630px; overflow: hidden; color: #fff; font-family: Sofia;
	background: radial-gradient(ellipse 55% 75% at 70% 30%, rgba(255,255,255,.20), transparent 62%), linear-gradient(125deg, ${c1} 0%, ${c2} 100%); }
.teren { position: absolute; inset: 0; width: 1200px; height: 630px; opacity: .13; }
.fundal { position: absolute; inset: 0; background-size: cover; background-position: center; filter: saturate(.85); }
.fundal::after { content: ''; position: absolute; inset: 0; background: linear-gradient(125deg, ${c1}e6 0%, ${c2}cc 55%, ${c2}99 100%); }
.marca { position: absolute; left: 44px; top: 36px; height: 70px; z-index: 3; filter: drop-shadow(0 6px 16px rgba(0,0,0,.35)); }
.fata { position: absolute; left: -10px; bottom: 0; width: 600px; height: 600px; z-index: 1; }
.dreapta { position: absolute; right: 56px; top: 50%; transform: translateY(-46%); z-index: 2; display: flex; flex-direction: column; align-items: flex-end; gap: 22px; max-width: ${fata ? 640 : 1000}px; }
.rand-banda { display: flex; align-items: center; gap: 22px; }
.emblema { width: 132px; height: 132px; flex: none; border-radius: 50%; background: #fff; display: flex; align-items: center; justify-content: center; box-shadow: 0 14px 34px rgba(0,0,0,.35); }
.emblema img { width: 74%; height: 74%; object-fit: contain; }
.banda { font-weight: 800; font-size: 160px; line-height: .95; text-transform: uppercase; text-align: right; white-space: nowrap;
	padding: 16px 44px 20px; background: ${accent}; color: ${c2}; transform: skewX(-12deg); box-shadow: 0 18px 50px rgba(0,0,0,.35); }
.banda > span, .sub > span { display: inline-block; transform: skewX(12deg); }
.sub { font-weight: 800; font-size: 68px; text-transform: uppercase; letter-spacing: 1px; text-shadow: 0 4px 18px rgba(0,0,0,.35);
	background: rgba(0,0,0,.35); border: 4px solid rgba(255,255,255,.9); padding: 6px 28px 10px; transform: skewX(-12deg); }
.cluburi { display: flex; align-items: center; gap: 26px; }
.sigla { width: 196px; height: 196px; border-radius: 50%; background: rgba(255,255,255,.96); display: flex; align-items: center; justify-content: center; box-shadow: 0 14px 34px rgba(0,0,0,.35); }
.sigla img { width: 74%; height: 74%; object-fit: contain; }
.scor { display: flex; gap: 10px; font-weight: 800; font-size: 128px; line-height: 1; }
.scor span { background: rgba(0,0,0,.45); border: 3px solid rgba(255,255,255,.3); padding: 4px 22px 8px; }
.vs { font-weight: 800; font-size: 80px; opacity: .85; }
/* Cu sigle, ele vin primele (sus), banda dedesubt, mai mică. */
.cu-cluburi .banda { font-size: 104px; padding: 12px 38px 16px; }
.cu-cluburi .emblema { width: 108px; height: 108px; }
.cu-cluburi .sub { font-size: 56px; }
/* Fără portret: totul la mijloc și mai mare, ca să umple coperta (pe telefon cardurile sunt mici). */
.centrat .dreapta { right: auto; left: 50%; transform: translate(-50%, -44%); align-items: center; max-width: 1080px; }
.centrat .banda { font-size: 176px; }
.centrat .sub { font-size: 76px; }
.centrat .sigla { width: 230px; height: 230px; }
.centrat .scor { font-size: 150px; }
.centrat .emblema { width: 150px; height: 150px; }
.centrat.cu-cluburi .banda { font-size: 116px; }
.centrat.cu-cluburi .emblema { width: 118px; height: 118px; }
.centrat .cluburi { gap: 34px; }
/* Sigle + subtitlu: siglele se micșorează, ca banda să rămână mare. */
.centrat.cu-cluburi.cu-sub .sigla { width: 168px; height: 168px; }
.centrat.cu-cluburi.cu-sub .cluburi { gap: 26px; }
.centrat.cu-cluburi.cu-sub .vs { font-size: 64px; }
.centrat.cu-cluburi.cu-sub .banda { font-size: 104px; }
.centrat.cu-cluburi.cu-sub .dreapta { gap: 16px; }
</style></head><body class="${[cluburi.length ? 'cu-cluburi' : '', fata ? '' : 'centrat', d.subtitlu && !scor ? 'cu-sub' : ''].join(' ')}">
${fundal ? `<div class="fundal" style="background-image:url(${fundal})"></div>` : ''}
<svg class="teren" viewBox="0 0 1200 630" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="#fff" stroke-width="4">
	<rect x="30" y="30" width="1140" height="570"/><line x1="600" y1="30" x2="600" y2="600"/>
	<circle cx="600" cy="315" r="95"/><circle cx="600" cy="315" r="6" fill="#fff"/>
	<rect x="30" y="150" width="170" height="330"/><rect x="30" y="230" width="60" height="170"/>
	<rect x="1000" y="150" width="170" height="330"/><rect x="1110" y="230" width="60" height="170"/>
	<path d="M200 255 A 95 95 0 0 1 200 375"/><path d="M1000 255 A 95 95 0 0 0 1000 375"/>
</svg>
${fs.existsSync(path.join(dir, 'logo.png')) ? `<img class="marca" src="${uri('logo.png')}">` : ''}
${fata ? `<img class="fata" src="${fata.src}">` : ''}
<div class="dreapta">
	${cluburi.length ? `<div class="cluburi">${cluburi.map((c, k) => `<div class="sigla"><img src="${c}"></div>${k === 0 && cluburi.length === 2 ? (scor ? `<div class="scor"><span>${esc(scor[0])}</span><span>${esc(scor[1])}</span></div>` : '<div class="vs">–</div>') : ''}`).join('')}</div>` : ''}
	<div class="rand-banda">${emblema ? `<div class="emblema"><img src="${emblema}"></div>` : ''}<div class="banda"><span>${esc(rubrica)}</span></div></div>
	${d.subtitlu && !scor ? `<div class="sub"><span>${esc(d.subtitlu)}</span></div>` : ''}
</div>
</body></html>`;

// Liniile terenului pentru coperta cu titlu (jumătate de teren, în dreapta).
const teren = `<svg class="teren" viewBox="0 0 600 630" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="#fff" stroke-width="4">
	<rect x="40" y="30" width="600" height="570"/><line x1="40" y1="30" x2="40" y2="600"/>
	<circle cx="40" cy="315" r="95"/><circle cx="40" cy="315" r="6" fill="#fff"/>
	<rect x="440" y="150" width="200" height="330"/><rect x="560" y="230" width="80" height="170"/>
	<circle cx="490" cy="315" r="5" fill="#fff"/><path d="M440 255 A 95 95 0 0 0 440 375"/>
</svg>`;

const html = `<!doctype html><html><head><meta charset="utf-8"><style>
@font-face { font-family: Sofia; font-weight: 800; src: url(${uri('fonturi/sofia-sans-extra-condensed-latin-800-normal.woff2', 'font/woff2')}); }
@font-face { font-family: Sofia; font-weight: 800; src: url(${uri('fonturi/sofia-sans-extra-condensed-latin-ext-800-normal.woff2', 'font/woff2')}); unicode-range: U+0100-024F, U+0218-021B; }
* { margin: 0; box-sizing: border-box; }
body { width: 1200px; height: 630px; overflow: hidden; font-family: Inter, Helvetica, Arial, sans-serif; color: #fff;
	background: radial-gradient(ellipse 60% 80% at 85% 35%, rgba(255,255,255,.16), transparent 60%), linear-gradient(120deg, ${c1} 0%, ${c2} 100%); }
.teren { position: absolute; right: 0; top: 0; width: 600px; height: 630px; opacity: .14; }
.text { position: absolute; left: 70px; top: 64px; width: 760px; height: 500px; display: flex; flex-direction: column; }
.rubrica { font-family: Sofia; font-weight: 800; font-size: 30px; letter-spacing: 2px; text-transform: uppercase; color: ${accent};
	display: flex; align-items: center; gap: 14px; }
.rubrica::before { content: ''; width: 46px; height: 6px; background: ${accent}; }
h1 { font-family: Sofia; font-weight: 800; font-size: 84px; line-height: 1; margin-top: 28px; text-shadow: 0 3px 14px rgba(0,0,0,.3); overflow-wrap: break-word; }
.jos { margin-top: auto; display: flex; justify-content: space-between; align-items: flex-end; width: 1060px; }
.autor { font-size: 22px; opacity: .9; } .autor b { font-weight: 700; }
.site { font-family: Sofia; font-weight: 800; font-size: 34px; letter-spacing: 2px; text-transform: uppercase; }
.site span { color: ${accent}; }
</style></head><body>
${teren}
<div class="text">
	<div class="rubrica">${esc(rubrica)}</div>
	<h1>${esc(d.titlu)}</h1>
	<div class="jos">
		<div class="autor">${d.autor ? `de <b>${esc(d.autor)}</b> · ` : ''}${zi} ${LUNI[luna - 1]} ${an}</div>
		<div class="site">Euro<span>gol</span>.ro</div>
	</div>
</div>
</body></html>`;

(async () => {
	const browser = await chromium.launch();
	const page = await browser.newPage({ viewport: { width: 1200, height: 630 } });
	await page.setContent(d.cu_titlu ? html : afis, { waitUntil: 'load' });
	await page.evaluate(() => document.fonts.ready);
	// Afișul: banda cu rubrica se micșorează până încape (cel mult 2 rânduri, în lățimea blocului).
	if (!d.cu_titlu) await page.evaluate(() => {
		const b = document.querySelector('.banda');
		const start = parseInt(getComputedStyle(b).fontSize, 10);
		const stanga = document.querySelector('.fata') ? 560 : 50; // banda nu intră peste portret
		const bloc = document.querySelector('.dreapta');
		const sub = document.querySelector('.sub');
		for (let px = start; px >= 56; px -= 4) {
			b.style.fontSize = px + 'px';
			if (sub) sub.style.fontSize = Math.max(50, Math.min(parseInt(getComputedStyle(sub).fontSize, 10), Math.round(px * 0.62))) + 'px';
			const r = b.getBoundingClientRect(), z = bloc.getBoundingClientRect();
			if (r.left >= stanga && r.right <= 1160 && z.top >= 110 && z.bottom <= 605) break;
		}
	});
	// Coperta cu titlu: cea mai mare mărime la care titlul încape în cel mult 5 rânduri și nu atinge rândul de jos.
	if (d.cu_titlu) await page.evaluate(() => {
		const h1 = document.querySelector('h1');
		const jos = document.querySelector('.jos');
		for (let px = 84; px >= 36; px -= 2) {
			h1.style.fontSize = px + 'px';
			const randuri = Math.round(h1.getBoundingClientRect().height / px);
			if (randuri <= 5 && h1.getBoundingClientRect().bottom <= jos.getBoundingClientRect().top - 24) break;
		}
	});
	await page.screenshot({ path: outFile, type: 'jpeg', quality: 88 });
	await browser.close();
	console.log('Coperta: ' + outFile);
})();
