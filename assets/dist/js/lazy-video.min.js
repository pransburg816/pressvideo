/**
 * PressVideo — Lazy iframe injector.
 * Listens for pv:opened / pv:closed custom events on #pv-canvas.
 * Injects the YouTube iframe on open, clears it on close.
 */
(function () {
	'use strict';

	const CANVAS_ID    = 'pv-canvas';
	const AUTOPLAY_MS  = 2500; // fallback spinner hide timeout

	let canvas, holder, spinner;
	let spinnerTimer = null;

	function init() {
		canvas  = document.getElementById(CANVAS_ID);
		if (!canvas) return;

		holder  = canvas.querySelector('.pv-iframe-holder');
		spinner = canvas.querySelector('.pv-spinner');

		canvas.addEventListener('pv:opened',  onOpen);
		canvas.addEventListener('pv:closed',  onClose);
	}

	function onOpen() {
		if (!holder) return;

		const embedUrl = holder.dataset.embedUrl;
		if (!embedUrl) return;

		// Already loaded — don't re-inject.
		if (holder.dataset.loaded) return;

		showSpinner();

		const url = new URL(embedUrl.replace('watch?v=', 'embed/'));
		url.searchParams.set('autoplay',        '1');
		url.searchParams.set('rel',             '0');
		url.searchParams.set('modestbranding',  '1');
		url.searchParams.set('playsinline',     '1');

		const iframe = document.createElement('iframe');
		iframe.src              = url.toString();
		iframe.allow            = 'autoplay; fullscreen; picture-in-picture';
		iframe.allowFullscreen  = true;
		iframe.title            = canvas.querySelector('.pv-title')?.textContent || 'Video';

		iframe.addEventListener('load', function () {
			hideSpinner();
			holder.dataset.loaded = '1';
		});

		// Fallback: hide spinner after timeout even if load never fires.
		spinnerTimer = setTimeout(function () {
			hideSpinner();
			holder.dataset.loaded = '1';
		}, AUTOPLAY_MS);

		// Clear any existing iframe.
		holder.innerHTML = '';
		holder.appendChild(iframe);
	}

	function onClose() {
		if (!holder) return;
		clearTimeout(spinnerTimer);
		holder.innerHTML = '';
		delete holder.dataset.loaded;
		hideSpinner();
	}

	function showSpinner() {
		if (spinner) {
			spinner.removeAttribute('hidden');
			spinner.style.display = '';
		}
	}

	function hideSpinner() {
		if (spinner) {
			spinner.style.display = 'none';
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
