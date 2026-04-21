/**
 * PressVideo — Offcanvas drawer controller.
 * Vanilla JS. No jQuery, no Bootstrap.
 */
(function () {
	'use strict';

	var cfg     = window.pvOffcanvas || {};
	var ajaxUrl = cfg.ajaxUrl || '';

	var CANVAS_ID = 'pv-canvas';

	var canvas, panel, backdrop, closeBtn, titleEl, descEl, badgeEl, iframeHolder, navCount, prevBtn, nextBtn, railEl;

	var playlist     = [];
	var current      = 0;
	var anchorId     = '';
	var railPage     = 0;
	var railMaxPages = 0;
	var railBusy     = false;
	var railSentinel = null;
	var railObserver = null;

	function init() {
		canvas = document.getElementById(CANVAS_ID);
		if (!canvas) return;

		panel        = canvas.querySelector('.pv-panel');
		backdrop     = canvas.querySelector('.pv-backdrop');
		closeBtn     = canvas.querySelector('.pv-close');
		titleEl      = canvas.querySelector('.pv-title');
		descEl       = canvas.querySelector('.pv-desc');
		badgeEl      = canvas.querySelector('.pv-badge');
		iframeHolder = canvas.querySelector('.pv-iframe-holder');
		navCount     = canvas.querySelector('.pv-nav-count');
		prevBtn      = canvas.querySelector('.pv-prev');
		nextBtn      = canvas.querySelector('.pv-next');
		railEl       = canvas.querySelector('.pv-rail');

		document.addEventListener('click', onTriggerClick);

		if (closeBtn) closeBtn.addEventListener('click', close);
		if (backdrop) backdrop.addEventListener('click', close);

		document.addEventListener('keydown', function (e) {
			if (!canvas.classList.contains('pv-open')) return;
			if (e.key === 'Escape')     { e.preventDefault(); close(); }
			if (e.key === 'ArrowRight') { e.preventDefault(); navigate(1); }
			if (e.key === 'ArrowLeft')  { e.preventDefault(); navigate(-1); }
		});

		if (prevBtn) prevBtn.addEventListener('click', function () { navigate(-1); });
		if (nextBtn) nextBtn.addEventListener('click', function () { navigate(1); });
	}

	// ── Trigger click ─────────────────────────────────────────────────

	function onTriggerClick(e) {
		var trigger = e.target.closest('.pv-trigger');
		if (!trigger) return;
		e.preventDefault();
		e.stopPropagation();

		anchorId = trigger.dataset.youtubeId || '';

		// Seed with just the clicked video so the drawer opens immediately
		playlist = [{
			youtubeId: anchorId,
			embedUrl:  trigger.dataset.embedUrl    || '',
			title:     trigger.dataset.title       || '',
			desc:      trigger.dataset.description || '',
			accent:    trigger.dataset.accent      || '#4f46e5',
			thumb:     '',
			duration:  '',
		}];
		current = 0;

		loadSlide(0);
		open();
		resetRail();
		fetchRailPage(1);
	}

	// ── Rail ──────────────────────────────────────────────────────────

	function resetRail() {
		railPage     = 0;
		railMaxPages = 0;
		railBusy     = false;
		detachSentinel();

		if (!railEl) return;
		railEl.innerHTML = '';
		railEl.style.display = '';

		var heading = document.createElement('p');
		heading.className = 'pv-rail__heading';
		heading.textContent = 'Up Next';
		railEl.appendChild(heading);

		var loadingEl = document.createElement('div');
		loadingEl.className = 'pv-rail__loading';
		loadingEl.innerHTML = '<span class="pv-rail__spinner"></span>';
		railEl.appendChild(loadingEl);
	}

	function fetchRailPage(page) {
		if (railBusy) return;
		if (railMaxPages > 0 && page > railMaxPages) return;
		if (!ajaxUrl) { if (railEl) railEl.style.display = 'none'; return; }

		railBusy = true;

		fetch(ajaxUrl + '?action=pv_playlist_page&page=' + page)
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data || !data.success) return;
				var res      = data.data;
				railPage     = res.page;
				railMaxPages = res.maxPages;

				if (page === 1) {
					playlist = res.items;
					current  = playlist.findIndex(function (v) { return v.youtubeId === anchorId; });
					if (current < 0) current = 0;

					if (!railEl) return;
					railEl.innerHTML = '';

					if (playlist.length <= 1) { railEl.style.display = 'none'; return; }

					var heading = document.createElement('p');
					heading.className = 'pv-rail__heading';
					heading.textContent = 'Up Next';
					railEl.appendChild(heading);

					playlist.forEach(function (slide, i) {
						railEl.appendChild(makeRailItem(slide, i));
					});
				} else {
					var offset = playlist.length;
					playlist   = playlist.concat(res.items);
					res.items.forEach(function (slide, i) {
						var before = railSentinel || null;
						railEl.insertBefore(makeRailItem(slide, offset + i), before);
					});
				}

				updateNav();
				updateRail(current);

				if (railPage < railMaxPages) {
					attachSentinel();
				} else {
					detachSentinel();
					var endEl = document.createElement('p');
					endEl.className = 'pv-rail__end';
					endEl.textContent = res.total + ' videos total';
					railEl.appendChild(endEl);
				}
			})
			.catch(function () {
				if (railEl) railEl.style.display = 'none';
			})
			.finally(function () { railBusy = false; });
	}

	function makeRailItem(slide, idx) {
		var item = document.createElement('div');
		item.className = 'pv-rail__item' + (idx === current ? ' pv-rail__item--active' : '');
		item.setAttribute('role', 'listitem');
		item.setAttribute('tabindex', '0');
		item.setAttribute('aria-label', slide.title || ('Video ' + (idx + 1)));
		item.setAttribute('aria-current', idx === current ? 'true' : 'false');

		var thumbHtml = slide.thumb
			? '<img class="pv-rail__thumb" src="' + esc(slide.thumb) + '" alt="" loading="lazy">'
			: '<div class="pv-rail__thumb pv-rail__thumb--placeholder"></div>';
		var durHtml = slide.duration
			? '<span class="pv-rail__dur">' + esc(slide.duration) + '</span>'
			: '';

		item.innerHTML = thumbHtml
			+ '<div class="pv-rail__info"><p class="pv-rail__title">' + esc(slide.title) + '</p>' + durHtml + '</div>';

		item.addEventListener('click', (function (i) { return function () { navigateTo(i); }; }(idx)));
		item.addEventListener('keydown', (function (i) {
			return function (e) {
				if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); navigateTo(i); }
			};
		}(idx)));

		return item;
	}

	function attachSentinel() {
		if (!railEl) return;
		if (!railSentinel) {
			railSentinel = document.createElement('div');
			railSentinel.className = 'pv-rail__sentinel';
		}
		railEl.appendChild(railSentinel);

		if ('IntersectionObserver' in window && panel) {
			if (railObserver) railObserver.disconnect();
			railObserver = new IntersectionObserver(function (entries) {
				if (entries[0].isIntersecting && !railBusy) {
					fetchRailPage(railPage + 1);
				}
			}, { root: panel, rootMargin: '150px' });
			railObserver.observe(railSentinel);
		}
	}

	function detachSentinel() {
		if (railObserver) { railObserver.disconnect(); railObserver = null; }
		if (railSentinel && railSentinel.parentNode) {
			railSentinel.parentNode.removeChild(railSentinel);
			railSentinel = null;
		}
	}

	// ── Drawer open / close ───────────────────────────────────────────

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

	// ── Slide / navigation ────────────────────────────────────────────

	function loadSlide(index) {
		var slide = playlist[index];
		if (!slide) return;

		canvas.style.setProperty('--pv-accent', slide.accent || '#4f46e5');
		if (titleEl)     titleEl.textContent = slide.title || '';
		if (descEl)      descEl.textContent  = slide.desc  || '';
		if (badgeEl)     badgeEl.textContent = 'Video';
		if (iframeHolder) {
			iframeHolder.dataset.embedUrl = slide.embedUrl || '';
			delete iframeHolder.dataset.loaded;
		}

		updateNav();
		updateRail(index);
		canvas.dispatchEvent(new CustomEvent('pv:slide-changed', { bubbles: true, detail: { index: index, slide: slide } }));
	}

	function navigate(delta) { navigateTo(current + delta); }

	function navigateTo(index) {
		if (index === current || index < 0 || index >= playlist.length) return;
		current = index;
		canvas.dispatchEvent(new CustomEvent('pv:closed', { bubbles: false }));
		loadSlide(current);
		canvas.dispatchEvent(new CustomEvent('pv:opened', { bubbles: false }));
	}

	function updateNav() {
		if (navCount) {
			navCount.textContent = playlist.length > 1 ? (current + 1) + ' / ' + playlist.length : '';
		}
		if (prevBtn) prevBtn.disabled = (current === 0);
		if (nextBtn) nextBtn.disabled = (current >= playlist.length - 1);
	}

	function updateRail(index) {
		if (!railEl || railEl.style.display === 'none') return;
		railEl.querySelectorAll('.pv-rail__item').forEach(function (el, i) {
			var active = (i === index);
			el.classList.toggle('pv-rail__item--active', active);
			el.setAttribute('aria-current', active ? 'true' : 'false');
		});
		var activeItem = railEl.querySelectorAll('.pv-rail__item')[index];
		if (activeItem) activeItem.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
	}

	function esc(str) {
		return String(str || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
