/**
 * PressVideo — Admin JS
 *
 * 1. wp-color-picker  (jQuery / WordPress core dependency)
 * 2. Visual card picker  (vanilla JS, no dependencies)
 */

/* ── Color Picker ─────────────────────────────────────────────── */
(function ($) {
	'use strict';
	$(function () {
		$('.pv-color-picker').wpColorPicker();
	});
}(jQuery));

/* ── Visual Card Picker ───────────────────────────────────────── */
(function () {
	'use strict';

	/**
	 * Wire up all cards inside a single .pv-visual-picker container.
	 * Clicking any card checks its hidden radio and toggles .is-selected.
	 */
	function initPicker(container) {
		var cards = container.querySelectorAll('.pv-pick-card');

		cards.forEach(function (card) {
			var radio = card.querySelector('input[type="radio"]');
			if (!radio) return;

			// Reflect initial state on page load.
			if (radio.checked) {
				card.classList.add('is-selected');
			}

			card.addEventListener('click', function () {
				if (card.classList.contains('is-locked')) return;

				// Deselect every card in the same radio group.
				var name = radio.name;
				document.querySelectorAll('input[name="' + name + '"]').forEach(function (r) {
					r.checked = false;
					var c = r.closest('.pv-pick-card');
					if (c) c.classList.remove('is-selected');
				});

				// Select this card.
				radio.checked = true;
				card.classList.add('is-selected');

				// Bubble a change event so other listeners (sub-layout reveal) fire.
				radio.dispatchEvent(new Event('change', { bubbles: true }));
			});
		});
	}

	/**
	 * Show / hide the watch-layout sub-picker depending on whether the
	 * "page" display-mode card is selected.
	 */
	function initSubLayoutReveal() {
		var displayPicker = document.querySelector('.pv-visual-picker[data-controls="display-mode"]');
		var sublayout     = document.querySelector('.pv-sublayout[data-for="display-mode"]');
		if (!displayPicker || !sublayout) return;

		function sync() {
			var checked = displayPicker.querySelector('input[type="radio"]:checked');
			if (checked && checked.value === 'page') {
				sublayout.classList.remove('is-hidden');
			} else {
				sublayout.classList.add('is-hidden');
			}
		}

		// Re-sync whenever any radio in the display-mode picker changes.
		displayPicker.addEventListener('change', sync);
		sync(); // Reflect saved state on load.
	}

	/** Copy shortcode to clipboard and flash the button. */
	function initCopyButtons() {
		document.querySelectorAll('.pv-copy-btn').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var text = btn.dataset.copy;
				if (!text) return;
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text).then(function () { flash(btn); });
				} else {
					// Fallback for non-secure contexts.
					var ta = document.createElement('textarea');
					ta.value = text;
					ta.style.position = 'fixed';
					ta.style.left = '-9999px';
					document.body.appendChild(ta);
					ta.select();
					document.execCommand('copy');
					document.body.removeChild(ta);
					flash(btn);
				}
			});
		});
	}

	function flash(btn) {
		var orig = btn.textContent;
		btn.textContent = '✓ Copied!';
		btn.classList.add('is-copied');
		setTimeout(function () {
			btn.textContent = orig;
			btn.classList.remove('is-copied');
		}, 1800);
	}

	/** AJAX save for the Content Width picker — runs on Dashboard and video edit screen. */
	function initContentWidthAjax() {
		var widthPicker = document.querySelector('.pv-visual-picker[data-controls="content-width"]');
		var mNonce      = document.getElementById('pv_mode_nonce');
		var wStatus     = document.getElementById('pv-width-status');
		if (!widthPicker || !mNonce) return;

		widthPicker.addEventListener('change', function (e) {
			var radio = e.target;
			if (radio.type !== 'radio') return;
			var data = new FormData();
			data.append('action', 'pv_save_content_width');
			data.append('nonce',  mNonce.value);
			data.append('width',  radio.value);
			fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: data })
				.then(function (r) { return r.json(); })
				.then(function (json) {
					if (!wStatus) return;
					wStatus.textContent = json.success ? '\u2713 Saved' : 'Save failed';
					wStatus.className   = 'pv-mode-status ' + (json.success ? 'pv-mode-status--ok' : 'pv-mode-status--err');
					setTimeout(function () {
						wStatus.textContent = '';
						wStatus.className   = 'pv-mode-status';
					}, 2500);
				})
				.catch(function () {});
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.pv-visual-picker').forEach(initPicker);
		initSubLayoutReveal();
		initCopyButtons();
		initContentWidthAjax();
	});
}());
