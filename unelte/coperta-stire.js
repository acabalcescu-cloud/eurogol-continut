// Coperta unui articol, 1200×630 JPG.
// Rulare: node unelte/coperta-stire.js <date.json> <iesire.jpg>
// date: { titlu, rubrica, data: "AAAA-LL-ZZ hh:mm", subtitlu?, autor?, cu_titlu? }
// Implicit, coperta e un AFIȘ fără titlu: rubrica scrisă mare, pe o bandă înclinată, peste liniile terenului.
// Site-ul pune titlul peste imagine (ca la sempremilan.com), deci titlul nu trebuie să apară și în imagine.
// subtitlu = un rând scurt sub bandă (ex. „Inter – Milan 1-3” sau „Etapa 14”). cu_titlu: true = vechea copertă, cu titlul scris.
// Culoarea se alege după rubrică (competiție sau serie). Fără sigle oficiale: doar culori, liniile terenului și numele site-ului.
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
const uri = (f, t) => `data:${t};base64,${fs.readFileSync(path.join(__dirname, f)).toString('base64')}`;
const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const fara = (s) => String(s || '').normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();

// [potrivire, culoare de început, culoare de final, accent]
const PALETE = [
	[/superliga|liga 1|cupa romaniei|nationala|tricolor|romania/, '#1B3FA0', '#0A1A4A', '#FFD23F'],
	[/champions|liga campionilor/, '#0E1E5B', '#030A26', '#9FC3FF'],
	[/europa league/, '#E86A10', '#5A1F00', '#FFE0B8'],
	[/conference/, '#0B8F4A', '#022412', '#B8FFD4'],
	[/premier league/, '#4B0F6B', '#1C0330', '#00F0A8'],
	[/laliga|la liga|spania/, '#D0172F', '#4A0610', '#FFD9DE'],
	[/serie a|italia/, '#0F4FA8', '#051B3D', '#7FE3FF'],
	[/bundesliga|germania/, '#C8102E', '#1A1A1A', '#FFFFFF'],
	[/ligue 1|franta/, '#0B2340', '#020A14', '#D9F04A'],
	[/libertadores|sudamericana|conmebol|copa america|brasil|argentina|liga profesional/, '#C9971C', '#3B2400', '#FFF1C2'],
	[/povest|legend|istori|stadion|derby|generatia/, '#5B3A1E', '#1E1108', '#F4D9A6'],
];
const rubrica = d.rubrica || 'Știri';
const [, c1, c2, accent] = PALETE.find(([re]) => re.test(fara(rubrica))) || [null, '#0F6B3A', '#06200F', '#C9F5D6'];

// Liniile terenului (jumătate de teren, în dreapta), desenate în SVG.
const teren = `<svg class="teren" viewBox="0 0 600 630" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="#fff" stroke-width="4">
	<rect x="40" y="30" width="600" height="570"/><line x1="40" y1="30" x2="40" y2="600"/>
	<circle cx="40" cy="315" r="95"/><circle cx="40" cy="315" r="6" fill="#fff"/>
	<rect x="440" y="150" width="200" height="330"/><rect x="560" y="230" width="80" height="170"/>
	<circle cx="490" cy="315" r="5" fill="#fff"/><path d="M440 255 A 95 95 0 0 0 440 375"/>
</svg>`;

const afis = `<!doctype html><html><head><meta charset="utf-8"><style>
@font-face { font-family: Sofia; font-weight: 800; src: url(${uri('fonturi/sofia-sans-extra-condensed-latin-800-normal.woff2', 'font/woff2')}); }
@font-face { font-family: Sofia; font-weight: 800; src: url(${uri('fonturi/sofia-sans-extra-condensed-latin-ext-800-normal.woff2', 'font/woff2')}); unicode-range: U+0100-024F, U+0218-021B; }
* { margin: 0; box-sizing: border-box; }
body { width: 1200px; height: 630px; overflow: hidden; color: #fff; font-family: Sofia;
	background: radial-gradient(ellipse 55% 75% at 70% 30%, rgba(255,255,255,.20), transparent 62%), linear-gradient(125deg, ${c1} 0%, ${c2} 100%); }
.teren { position: absolute; inset: 0; width: 1200px; height: 630px; opacity: .13; }
.marca { position: absolute; left: 56px; top: 44px; font-weight: 800; font-size: 40px; letter-spacing: 1px; text-transform: uppercase; }
.marca span { color: ${accent}; }
.bloc { position: absolute; right: 60px; top: 70px; display: flex; flex-direction: column; align-items: flex-end; gap: 18px; max-width: 900px; }
.banda { font-weight: 800; font-size: 130px; line-height: .95; text-transform: uppercase; text-align: right;
	padding: 16px 44px 20px; background: ${accent}; color: ${c2}; transform: skewX(-12deg); box-shadow: 0 18px 50px rgba(0,0,0,.35); }
.banda > span { display: inline-block; transform: skewX(12deg); }
.sub { font-weight: 800; font-size: 54px; text-transform: uppercase; letter-spacing: 1px; text-shadow: 0 4px 18px rgba(0,0,0,.35);
	border: 3px solid rgba(255,255,255,.85); padding: 6px 26px 8px; transform: skewX(-12deg); }
.sub > span { display: inline-block; transform: skewX(12deg); }
</style></head><body>
<svg class="teren" viewBox="0 0 1200 630" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="#fff" stroke-width="4">
	<rect x="30" y="30" width="1140" height="570"/><line x1="600" y1="30" x2="600" y2="600"/>
	<circle cx="600" cy="315" r="95"/><circle cx="600" cy="315" r="6" fill="#fff"/>
	<rect x="30" y="150" width="170" height="330"/><rect x="30" y="230" width="60" height="170"/>
	<rect x="1000" y="150" width="170" height="330"/><rect x="1110" y="230" width="60" height="170"/>
	<path d="M200 255 A 95 95 0 0 1 200 375"/><path d="M1000 255 A 95 95 0 0 0 1000 375"/>
</svg>
<div class="marca">Euro<span>gol</span></div>
<div class="bloc">
	<div class="banda"><span>${esc(rubrica)}</span></div>
	${d.subtitlu ? `<div class="sub"><span>${esc(d.subtitlu)}</span></div>` : ''}
</div>
</body></html>`;

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
		for (let px = 130; px >= 60; px -= 4) {
			b.style.fontSize = px + 'px';
			if (b.getBoundingClientRect().width <= 920 && b.getBoundingClientRect().height <= px * 2.2) break;
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
