/**
 * PressVideo — Offcanvas drawer controller.
 * Vanilla JS. No jQuery, no Bootstrap.
 */
(function () {
	'use strict';

	const CANVAS_ID = 'pv-canvas';
	let   canvas, panel, backdrop, closeBtn, titleEl, descEl, badgeEl, iframeHolder, navCount, prevBtn, nextBtn;

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

		// Trigger clicks — delegated to document.
		document.addEventListener('click', onTriggerClick);

		// Close controls.
		if (closeBtn)  closeBtn.addEventListener('click',  close);
		if (backdrop)  backdrop.addEventListener('click',  close);

		// Keyboard.
		document.addEventListener('keydown', function (e) {
			if (!canvas.classList.contains('pv-open')) return;
			if (e.key === 'Escape')      { e.preventDefault(); close(); }
			if (e.key === 'ArrowRight')  { e.preventDefault(); navigate(1); }
			if (e.key === 'ArrowLeft')   { e.preventDefault(); navigate(-1); }
		});

		// Nav buttons.
		if (prevBtn) prevBtn.addEventListener('click', function () { navigate(-1); });
		if (nextBtn) nextBtn.addEventListener('click', function () { navigate(1);  });
	}

	function onTriggerClick(e) {
		const trigger = e.target.closest('.pv-trigger');
		if (!trigger) return;

		e.preventDefault();
		e.stopPropagation();

		const data = {
			videoId:  trigger.dataset.videoId   || '',
			youtubeId:trigger.dataset.youtubeId || '',
			embedUrl: trigger.dataset.embedUrl  || '',
			title:    trigger.dataset.title     || '',
			desc:     trigger.dataset.description || '',
			accent:   trigger.dataset.accent    || '#4f46e5',
			playlist: trigger.dataset.playlist  || '[]',
		};

		// Parse the playlist and find the current index.
		try {
			playlist = JSON.parse(data.playlist);
		} catch (_) {
			playlist = [{ youtubeId: data.youtubeId, embedUrl: data.embedUrl, title: data.title, desc: data.desc, accent: data.accent }];
		}
		if (!playlist.length) {
			playlist = [{ youtubeId: data.youtubeId, embedUrl: data.embedUrl, title: data.title, desc: data.desc, accent: data.accent }];
		}

		current = playlist.findIndex(function (v) { return v.youtubeId === data.youtubeId; });
		if (current < 0) current = 0;

		loadSlide(current);
		open();
	}

	function open() {
		canvas.classList.add('pv-open');
		canvas.setAttribute('aria-hidden', 'false');
		document.body.style.overflow = 'hidden';

		// Move focus inside panel.
		if (closeBtn) {
			requestAnimationFrame(function () { closeBtn.focus(); });
		}

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

		// Update accent.
		canvas.style.setProperty('--pv-accent', slide.accent || '#4f46e5');

		// Update meta.
		if (titleEl)  titleEl.textContent  = slide.title || '';
		if (descEl)   descEl.textContent   = slide.desc  || '';
		if (badgeEl)  badgeEl.textContent  = 'Video';

		// Signal to lazy-video.js which URL to load.
		if (iframeHolder) {
			iframeHolder.dataset.embedUrl = slide.embedUrl || '';
			delete iframeHolder.dataset.loaded;
		}

		// Nav controls.
		updateNav();

		canvas.dispatchEvent(new CustomEvent('pv:slide-changed', { bubbles: true, detail: { index, slide } }));
	}

	function navigate(delta) {
		const next = current + delta;
		if (next < 0 || next >= playlist.length) return;
		current = next;
		// Close current iframe first so lazy-video re-fires.
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

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
