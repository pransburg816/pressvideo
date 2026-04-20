/**
 * PressVideo — Offcanvas drawer controller.
 * Vanilla JS. No jQuery, no Bootstrap.
 */
(function () {
	'use strict';

	const CANVAS_ID = 'pv-canvas';
	let   canvas, panel, backdrop, closeBtn, titleEl, descEl, badgeEl, iframeHolder, navCount, prevBtn, nextBtn, railEl;

	let playlist   = [];
	let current    = 0;

	function init() {
		canvas      = document.getElementById(CANVAS_ID);
		if (!canvas) return;

		panel       = canvas.querySelector('.pv-panel');
		backdrop    = canvas.querySelector('.pv-backdrop');
		closeBtn    = canvas.querySelector('.pv-close');
		titleEl     = canvas.querySelector('.pv-title');
		descEl      = canvas.querySelector('.pv-desc');
		badgeEl     = canvas.querySelector('.pv-badge');
		iframeHolder = canvas.querySelector('.pv-iframe-holder');
		navCount    = canvas.querySelector('.pv-nav-count');
		prevBtn     = canvas.querySelector('.pv-prev');
		nextBtn     = canvas.querySelector('.pv-next');
		railEl      = canvas.querySelector('.pv-rail');

		document.addEventListener('click', onTriggerClick);

		if (closeBtn)  closeBtn.addEventListener('click',  close);
		if (backdrop)  backdrop.addEventListener('click',  close);

		document.addEventListener('keydown', function (e) {
			if (!canvas.classList.contains('pv-open')) return;
			if (e.key === 'Escape')      { e.preventDefault(); close(); }
			if (e.key === 'ArrowRight')  { e.preventDefault(); navigate(1); }
			if (e.key === 'ArrowLeft')   { e.preventDefault(); navigate(-1); }
		});

		if (prevBtn) prevBtn.addEventListener('click', function () { navigate(-1); });
		if (nextBtn) nextBtn.addEventListener('click', function () { navigate(1);  });
	}

	function onTriggerClick(e) {
		const trigger = e.target.closest('.pv-trigger');
		if (!trigger) return;

		e.preventDefault();
		e.stopPropagation();

		const data = {
			videoId:  trigger.dataset.videoId    || '',
			youtubeId:trigger.dataset.youtubeId  || '',
			embedUrl: trigger.dataset.embedUrl   || '',
			title:    trigger.dataset.title      || '',
			desc:     trigger.dataset.description || '',
			accent:   trigger.dataset.accent     || '#4f46e5',
			playlist: trigger.dataset.playlist   || '[]',
		};

		try {
			playlist = JSON.parse(data.playlist);
		} catch (_) {
			playlist = [];
		}
		if (!playlist.length) {
			playlist = [{ youtubeId: data.youtubeId, embedUrl: data.embedUrl, title: data.title, desc: data.desc, accent: data.accent, thumb: '', duration: '' }];
		}

		current = playlist.findIndex(function (v) { return v.youtubeId === data.youtubeId; });
		if (current < 0) current = 0;

		buildRail();
		loadSlide(current);
		open();
	}

	function open() {
		canvas.classList.add('pv-open');
		canvas.setAttribute('aria-hidden', 'false');
		document.body.style.overflow = 'hidden';
		if (closeBtn) requestAnimationFrame(function () { closeBtn.focus(); });
		canvas.dispatchEvent(new CustomEvent('pv:opened', { bubbles: true }));
	}

	function close() {
		canvas.classList.remove('pv-open');
		canvas.setAttribute('aria-hidden', 'true');
		document.body.style.overflow = '';
		canvas.dispatchEvent(new CustomEvent('pv:closed', { bubbles: true }));
	}

	function loadSlide(index) {
		const slide = playlist[index];
		if (!slide) return;

		canvas.style.setProperty('--pv-accent', slide.accent || '#4f46e5');

		if (titleEl)  titleEl.textContent  = slide.title || '';
		if (descEl)   descEl.textContent   = slide.desc  || '';
		if (badgeEl)  badgeEl.textContent  = 'Video';

		if (iframeHolder) {
			iframeHolder.dataset.embedUrl = slide.embedUrl || '';
			delete iframeHolder.dataset.loaded;
		}

		updateNav();
		updateRail(index);

		canvas.dispatchEvent(new CustomEvent('pv:slide-changed', { bubbles: true, detail: { index, slide } }));
	}

	function navigate(delta) {
		navigateTo(current + delta);
	}

	function navigateTo(index) {
		if (index === current || index < 0 || index >= playlist.length) return;
		current = index;
		canvas.dispatchEvent(new CustomEvent('pv:closed', { bubbles: false }));
		loadSlide(current);
		canvas.dispatchEvent(new CustomEvent('pv:opened', { bubbles: false }));
	}

	function updateNav() {
		if (navCount) {
			navCount.textContent = playlist.length > 1
				? (current + 1) + ' / ' + playlist.length
				: '';
		}
		if (prevBtn) prevBtn.disabled = (current === 0);
		if (nextBtn) nextBtn.disabled = (current === playlist.length - 1);
	}

	// ── Playlist rail ────────────────────────────────────────────────

	function esc(str) {
		return String(str || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function buildRail() {
		if (!railEl) return;
		railEl.innerHTML = '';

		if (playlist.length <= 1) {
			railEl.style.display = 'none';
			return;
		}
		railEl.style.display = '';

		const heading = document.createElement('p');
		heading.className = 'pv-rail__heading';
		heading.textContent = 'Up Next';
		railEl.appendChild(heading);

		playlist.forEach(function (slide, i) {
			const item = document.createElement('div');
			item.className = 'pv-rail__item' + (i === current ? ' pv-rail__item--active' : '');
			item.setAttribute('role', 'listitem');
			item.setAttribute('tabindex', '0');
			item.setAttribute('aria-label', slide.title || ('Video ' + (i + 1)));
			item.setAttribute('aria-current', i === current ? 'true' : 'false');

			const thumbSrc = slide.thumb || '';
			const thumbHtml = thumbSrc
				? '<img class="pv-rail__thumb" src="' + esc(thumbSrc) + '" alt="" loading="lazy">'
				: '<div class="pv-rail__thumb pv-rail__thumb--placeholder"></div>';

			const durHtml = slide.duration
				? '<span class="pv-rail__dur">' + esc(slide.duration) + '</span>'
				: '';

			item.innerHTML = thumbHtml
				+ '<div class="pv-rail__info">'
				+ '<p class="pv-rail__title">' + esc(slide.title) + '</p>'
				+ durHtml
				+ '</div>';

			item.addEventListener('click', (function (idx) {
				return function () { navigateTo(idx); };
			}(i)));

			item.addEventListener('keydown', (function (idx) {
				return function (e) {
					if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); navigateTo(idx); }
				};
			}(i)));

			railEl.appendChild(item);
		});
	}

	function updateRail(index) {
		if (!railEl || railEl.style.display === 'none') return;
		railEl.querySelectorAll('.pv-rail__item').forEach(function (el, i) {
			const active = (i === index);
			el.classList.toggle('pv-rail__item--active', active);
			el.setAttribute('aria-current', active ? 'true' : 'false');
		});
		const activeItem = railEl.querySelectorAll('.pv-rail__item')[index];
		if (activeItem) activeItem.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
