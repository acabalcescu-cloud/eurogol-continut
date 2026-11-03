/* Eurogol: meniul pe mobil, căutarea, submeniurile și butonul „Înapoi sus”. */
(function () {
	'use strict';
	var body = document.body;

	function comuta(buton, deschis) {
		buton.setAttribute('aria-expanded', deschis ? 'true' : 'false');
	}

	// Meniul (pe ecrane mici).
	var butonMeniu = document.querySelector('.eg-deschide-meniu');
	if (butonMeniu) {
		butonMeniu.addEventListener('click', function () {
			var deschis = !body.classList.contains('eg-meniu-deschis');
			body.classList.toggle('eg-meniu-deschis', deschis);
			comuta(butonMeniu, deschis);
		});
	}

	// Submeniurile: pe mobil se deschid la apăsare, pe desktop la trecerea mouse-ului (din CSS).
	document.querySelectorAll('.eg-meniu .menu-item-has-children > a').forEach(function (a) {
		var buton = document.createElement('button');
		buton.className = 'eg-sub-buton';
		buton.setAttribute('aria-expanded', 'false');
		buton.setAttribute('aria-label', 'Deschide submeniul ' + a.textContent.trim());
		a.after(buton);
		buton.addEventListener('click', function () {
			var li = buton.parentElement;
			var deschis = !li.classList.contains('eg-sub-deschis');
			li.classList.toggle('eg-sub-deschis', deschis);
			comuta(buton, deschis);
		});
	});

	// Căutarea din antet.
	var butonCautare = document.querySelector('.eg-deschide-cautare');
	var cautare = document.getElementById('eg-cautare');
	if (butonCautare && cautare) {
		butonCautare.addEventListener('click', function () {
			var deschis = cautare.hidden;
			cautare.hidden = !deschis;
			comuta(butonCautare, deschis);
			if (deschis) {
				var camp = cautare.querySelector('input[type="search"]');
				if (camp) camp.focus();
			}
		});
	}

	// Esc închide meniul și căutarea.
	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape') return;
		if (body.classList.contains('eg-meniu-deschis') && butonMeniu) {
			body.classList.remove('eg-meniu-deschis');
			comuta(butonMeniu, false);
			butonMeniu.focus();
		}
		if (cautare && !cautare.hidden && butonCautare) {
			cautare.hidden = true;
			comuta(butonCautare, false);
			butonCautare.focus();
		}
	});

	// Rezumatul video: playerul YouTube (fără cookie-uri) se încarcă doar la apăsare.
	document.querySelectorAll('.eg-video-play').forEach(function (b) {
		b.addEventListener('click', function () {
			var f = document.createElement('iframe');
			f.src = 'https://www.youtube-nocookie.com/embed/' + b.dataset.id + '?autoplay=1&rel=0';
			f.title = 'Rezumatul meciului';
			f.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
			f.allowFullscreen = true;
			f.className = 'eg-video-frame';
			b.replaceWith(f);
		});
	});

	// „Înapoi sus”, după primul ecran.
	var sus = document.querySelector('.eg-inapoi-sus');
	if (sus) {
		var verifica = function () { sus.hidden = window.scrollY < 900; };
		window.addEventListener('scroll', verifica, { passive: true });
		verifica();
		sus.addEventListener('click', function (e) {
			e.preventDefault();
			window.scrollTo({ top: 0, behavior: 'smooth' });
		});
	}
})();
