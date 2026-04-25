/**
 * PressVideo — Picture-in-Picture player
 * When a viewer scrolls past the video embed on a single video page,
 * a floating mini-player appears in the bottom-right corner.
 * The iframe is repositioned via CSS (no DOM move = no reload).
 */
(function () {
	'use strict';

	var wrap = document.querySelector('.pv-watch-embed-wrap');
	if (!wrap || !wrap.querySelector('iframe')) return;

	// Placeholder sibling holds the vertical space in normal flow when wrap is fixed.
	var placeholder = document.createElement('div');
	placeholder.className = 'pv-pip-placeholder';
	wrap.parentNode.insertBefore(placeholder, wrap);

	// PIP chrome — hover-revealed controls inside the floating player.
	var chrome = document.createElement('div');
	chrome.className = 'pv-pip-chrome';
	chrome.innerHTML =
		'<div class="pv-pip-btns">' +
		'<button class="pv-pip-btn pv-pip-back" title="Return to player" aria-label="Return to player">' +
		'<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>' +
		'</button>' +
		'<button class="pv-pip-btn pv-pip-close" title="Close" aria-label="Close picture-in-picture">' +
		'<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>' +
		'</button>' +
		'</div>';
	wrap.appendChild(chrome);

	var active    = false;
	var dismissed = false; // user explicitly closed PIP this session
	var skipNext  = false; // debounce flag — ignore observer callbacks during transitions

	function openPip() {
		if (active || dismissed) return;
		active   = true;
		skipNext = true;
		placeholder.style.height = wrap.offsetHeight + 'px';
		wrap.classList.add('pv-pip-active');
		setTimeout(function () { skipNext = false; }, 600);
	}

	function closePip(scrollBack) {
		if (!active) return;
		active   = false;
		skipNext = true;
		wrap.classList.remove('pv-pip-active');
		placeholder.style.height = '0';
		if (!scrollBack) dismissed = true;
		setTimeout(function () {
			skipNext = false;
			// If user returned to the player they may scroll away again — allow PIP.
			if (scrollBack) dismissed = false;
		}, 600);
		if (scrollBack) {
			setTimeout(function () {
				wrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
			}, 50);
		}
	}

	chrome.querySelector('.pv-pip-back').addEventListener('click', function (e) {
		e.stopPropagation();
		closePip(true);
	});
	chrome.querySelector('.pv-pip-close').addEventListener('click', function (e) {
		e.stopPropagation();
		closePip(false);
	});

	// Open PIP when the embed wrap exits the viewport.
	// skipNext guards against IntersectionObserver re-firing when position:fixed
	// causes the element to become "visible" again immediately after activation.
	var observer = new IntersectionObserver(function (entries) {
		if (skipNext) return;
		if (!entries[0].isIntersecting && !active && !dismissed) {
			openPip();
		}
	}, { threshold: 0.3 });

	observer.observe(wrap);
}());
