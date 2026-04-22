/**
 * PressVideo — Sitewide notifications JS.
 * Handles live flash banner dismiss + new-video toast.
 */
(function () {
	'use strict';

	var cfg = window.pvNotify || {};

	/* ── Live Flash Banner ──────────────────────────────────────────── */
	var banner = document.getElementById('pv-live-banner');
	if (banner) {
		var streamId  = banner.dataset.streamId || '';
		var storageKey = 'pv_live_banner_' + streamId;

		// If already dismissed for this stream, hide immediately
		if (streamId && localStorage.getItem(storageKey)) {
			banner.style.display = 'none';
		} else {
			// Push page content down by banner height
			function applyBannerOffset() {
				document.body.style.paddingTop = banner.offsetHeight + 'px';
			}
			applyBannerOffset();
			window.addEventListener('resize', applyBannerOffset);

			var closeBtn = banner.querySelector('.pv-live-banner__close');
			if (closeBtn) {
				closeBtn.addEventListener('click', function () {
					banner.classList.add('pv-live-banner--hidden');
					document.body.style.paddingTop = '';
					if (streamId) localStorage.setItem(storageKey, '1');
				});
			}
		}
	}

	/* ── New-video Toast ────────────────────────────────────────────── */
	if (!cfg.newVideoOn && !cfg.forceTest) return;

	var seenTs = parseInt(localStorage.getItem('pv_seen_ts') || '0', 10);
	if (!cfg.forceTest && cfg.latestTs <= seenTs) return;

	// Build toast element
	var toast = document.createElement('div');
	toast.id        = 'pv-new-video-toast';
	toast.className = 'pv-new-video-toast';
	toast.innerHTML =
		'<span class="pv-new-video-toast__icon" aria-hidden="true">&#127916;</span>'
		+ '<span class="pv-new-video-toast__text">'
		+ escapeHtml(cfg.message || 'New videos have been added!')
		+ '</span>'
		+ '<button class="pv-new-video-toast__close" aria-label="Dismiss">&#x2715;</button>';
	document.body.appendChild(toast);

	// Show after short delay
	var showTimer = setTimeout(function () {
		toast.classList.add('pv-new-video-toast--visible');
	}, 800);

	// Auto-dismiss after 8s
	var hideTimer = setTimeout(function () {
		dismissToast();
	}, 8800);

	toast.querySelector('.pv-new-video-toast__close').addEventListener('click', function () {
		clearTimeout(showTimer);
		clearTimeout(hideTimer);
		dismissToast();
	});

	function dismissToast() {
		toast.classList.remove('pv-new-video-toast--visible');
		localStorage.setItem('pv_seen_ts', String(Date.now()));
		setTimeout(function () { toast.remove(); }, 400);
	}

	function escapeHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

}());
