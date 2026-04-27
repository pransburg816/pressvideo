/**
 * PressVideo — Modal Popup Player controller.
 * Vanilla JS. No jQuery, no Bootstrap.
 */
(function () {
	'use strict';

	var overlay, card, titleEl, descEl, counterEl, stripEl, iframeEl;
	var prevBtn, nextBtn, mobilePrevBtn, mobileNextBtn, mobileNav, mobileCounter;
	var playlist = [], current = 0;

	function init() {
		overlay = document.getElementById('pv-modal-overlay');
		if (!overlay) return;

		card           = overlay.querySelector('.pv-modal-card');
		titleEl        = overlay.querySelector('.pv-modal-title');
		descEl         = overlay.querySelector('.pv-modal-desc');
		counterEl      = overlay.querySelector('.pv-modal-counter');
		stripEl        = overlay.querySelector('.pv-modal-strip');
		iframeEl       = overlay.querySelector('.pv-modal-iframe');
		prevBtn        = overlay.querySelector('.pv-modal-nav--prev');
		nextBtn        = overlay.querySelector('.pv-modal-nav--next');
		mobilePrevBtn  = overlay.querySelector('.pv-modal-mobile-nav-btn--prev');
		mobileNextBtn  = overlay.querySelector('.pv-modal-mobile-nav-btn--next');
		mobileNav      = overlay.querySelector('.pv-modal-mobile-nav');
		mobileCounter  = overlay.querySelector('.pv-modal-mobile-counter');

		var closeBtn = overlay.querySelector('.pv-modal-close');
		if (closeBtn) closeBtn.addEventListener('click', closeModal);

		overlay.addEventListener('click', function (e) {
			if (e.target === overlay) closeModal();
		});

		if (prevBtn)       prevBtn.addEventListener('click',       function () { navigate(-1); });
		if (nextBtn)       nextBtn.addEventListener('click',       function () { navigate(1); });
		if (mobilePrevBtn) mobilePrevBtn.addEventListener('click', function () { navigate(-1); });
		if (mobileNextBtn) mobileNextBtn.addEventListener('click', function () { navigate(1); });

		document.addEventListener('keydown', function (e) {
			if (!overlay.classList.contains('pv-modal--open')) return;
			if (e.key === 'Escape')     { e.preventDefault(); closeModal(); }
			if (e.key === 'ArrowLeft')  { e.preventDefault(); navigate(-1); }
			if (e.key === 'ArrowRight') { e.preventDefault(); navigate(1); }
		});

		document.addEventListener('click', function (e) {
			var trigger = e.target.closest('.pv-trigger');
			if (!trigger) return;
			e.preventDefault();
			e.stopPropagation();

			var ytId  = trigger.dataset.youtubeId || '';
			var plRaw = trigger.dataset.playlist   || '';
			var plData = null;
			try { plData = plRaw ? JSON.parse(plRaw) : null; } catch (err) {}

			if (plData && plData.length > 0) {
				playlist = plData;
				current  = playlist.findIndex(function (v) { return v.youtubeId === ytId; });
				if (current < 0) current = 0;
			} else {
				// No playlist JSON on the button — try the container's full playlist (broadcast AJAX).
				var container = trigger.closest(
					'.pv-bc-video-grid, .pv-bc-home-grid, .pv-bc-search-results, .pv-grid'
				);
				var containerPlRaw = container ? (container.dataset.pvPlaylist || '') : '';
				var containerPl = null;
				try { containerPl = containerPlRaw ? JSON.parse(containerPlRaw) : null; } catch (err) {}

				if (containerPl && containerPl.length > 0) {
					playlist = containerPl;
					current  = playlist.findIndex(function (v) { return v.youtubeId === ytId; });
					if (current < 0) current = 0;
				} else {
					// Final fallback: collect visible sibling triggers.
					var siblings = container
						? Array.from(container.querySelectorAll('.pv-trigger[data-youtube-id]'))
						: [];
					if (siblings.length > 1) {
						playlist = siblings.map(function (btn) {
							return {
								youtubeId : btn.dataset.youtubeId   || '',
								embedUrl  : btn.dataset.embedUrl    || '',
								title     : btn.dataset.title       || '',
								desc      : btn.dataset.description || '',
								accent    : btn.dataset.accent      || '#4f46e5',
								thumb     : btn.dataset.thumb       || '',
							};
						});
						current = playlist.findIndex(function (v) { return v.youtubeId === ytId; });
						if (current < 0) current = 0;
					} else {
						playlist = [{
							youtubeId : ytId,
							embedUrl  : trigger.dataset.embedUrl    || '',
							title     : trigger.dataset.title       || '',
							desc      : trigger.dataset.description || '',
							accent    : trigger.dataset.accent      || '#4f46e5',
							thumb     : trigger.dataset.thumb       || ''
						}];
						current = 0;
					}
				}
			}

			loadSlide(current);
			openModal();
		});
	}

	function openModal() {
		overlay.classList.add('pv-modal--open');
		overlay.setAttribute('aria-hidden', 'false');
		document.body.style.overflow = 'hidden';
		var closeBtn = overlay.querySelector('.pv-modal-close');
		if (closeBtn) requestAnimationFrame(function () { closeBtn.focus(); });
	}

	function closeModal() {
		overlay.classList.remove('pv-modal--open');
		overlay.setAttribute('aria-hidden', 'true');
		document.body.style.overflow = '';
		if (iframeEl) iframeEl.src = 'about:blank';
	}

	function navigate(delta) {
		var next = current + delta;
		if (next < 0 || next >= playlist.length) return;
		current = next;
		loadSlide(current);
	}

	function loadSlide(index) {
		var slide = playlist[index];
		if (!slide) return;
		current = index;

		var accent = slide.accent || '#4f46e5';
		if (overlay) overlay.style.setProperty('--pv-accent', accent);

		if (titleEl)  titleEl.textContent = slide.title || '';
		if (descEl)   descEl.textContent  = slide.desc  || '';
		if (iframeEl) iframeEl.src        = slide.embedUrl || '';

		var multi = playlist.length > 1;

		// Counter badge
		if (counterEl) {
			counterEl.textContent = multi ? (index + 1) + ' of ' + playlist.length : '';
			counterEl.hidden = !multi;
		}
		if (mobileCounter) {
			mobileCounter.textContent = multi ? (index + 1) + ' / ' + playlist.length : '';
		}

		// Side arrows
		if (prevBtn) { prevBtn.hidden = !multi; prevBtn.disabled = index === 0; }
		if (nextBtn) { nextBtn.hidden = !multi; nextBtn.disabled = index >= playlist.length - 1; }

		// Mobile nav row
		if (mobileNav) mobileNav.hidden = !multi;
		if (mobilePrevBtn) { mobilePrevBtn.hidden = !multi; mobilePrevBtn.disabled = index === 0; }
		if (mobileNextBtn) { mobileNextBtn.hidden = !multi; mobileNextBtn.disabled = index >= playlist.length - 1; }

		buildStrip(index);
	}

	function buildStrip(active) {
		if (!stripEl) return;
		if (playlist.length <= 1) { stripEl.hidden = true; return; }
		stripEl.hidden = false;
		stripEl.innerHTML = '';

		playlist.forEach(function (slide, i) {
			var chip = document.createElement('button');
			chip.type      = 'button';
			chip.className = 'pv-modal-chip' + (i === active ? ' pv-modal-chip--active' : '');
			chip.setAttribute('aria-label',   slide.title || ('Video ' + (i + 1)));
			chip.setAttribute('aria-current', i === active ? 'true' : 'false');

			if (slide.thumb) {
				var img    = document.createElement('img');
				img.src     = slide.thumb;
				img.alt     = '';
				img.loading = 'lazy';
				chip.appendChild(img);
			}

			chip.addEventListener('click', (function (idx) {
				return function () { if (idx !== current) { current = idx; loadSlide(idx); } };
			})(i));

			stripEl.appendChild(chip);
		});

		var activeChip = stripEl.querySelector('.pv-modal-chip--active');
		if (activeChip) {
			requestAnimationFrame(function () {
				activeChip.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
