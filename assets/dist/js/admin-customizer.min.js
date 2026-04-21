/**
 * PressVideo — Live Customizer JS.
 * Manages settings panel ↔ iframe preview bridge.
 */
/* global pvCustomizer, jQuery, wp */
(function ($) {
	'use strict';

	var cfg         = window.pvCustomizer || {};
	var iframe      = document.getElementById('pvc-preview-iframe');
	var frameWrap   = document.getElementById('pvc-frame-wrap');
	var frameLoader = document.getElementById('pvc-frame-loading');
	var statusEl    = document.getElementById('pvc-status');
	var toast       = document.getElementById('pvc-toast');
	var publishBtn  = document.getElementById('pvc-publish-btn');
	var refreshBtn  = document.getElementById('pvc-refresh-btn');
	var saveTimer   = null;
	var toastTimer  = null;
	var iframeReady = false;

	// ── Tab navigation ────────────────────────────────────────
	document.querySelectorAll('.pvc-tab').forEach(function (tab) {
		tab.addEventListener('click', function () {
			document.querySelectorAll('.pvc-tab').forEach(function (t) {
				t.classList.remove('pvc-tab--active');
			});
			document.querySelectorAll('.pvc-panel').forEach(function (p) {
				p.classList.remove('pvc-panel--active');
			});
			this.classList.add('pvc-tab--active');
			var panel = document.getElementById('pvc-panel-' + this.dataset.tab);
			if (panel) panel.classList.add('pvc-panel--active');
		});
	});

	// ── Segmented controls (generic — uses data-for to find hidden input) ──
	document.querySelectorAll('.pvc-segment').forEach(function (seg) {
		seg.querySelectorAll('.pvc-seg-btn').forEach(function (btn) {
			btn.addEventListener('click', function () {
				seg.querySelectorAll('.pvc-seg-btn').forEach(function (b) {
					b.classList.remove('pvc-seg-btn--active');
				});
				this.classList.add('pvc-seg-btn--active');
				var targetId = seg.dataset.for;
				var hidden = targetId ? document.getElementById(targetId) : null;
				if (hidden) hidden.value = this.dataset.value;
				triggerSave(false);
			});
		});
	});

	// ── Stepper buttons ───────────────────────────────────────
	document.querySelectorAll('.pvc-step-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var key   = this.dataset.target;
			var dir   = parseInt(this.dataset.direction, 10);
			var input = document.querySelector('.pvc-step-input[data-setting="' + key + '"]');
			if (!input) return;
			var val = parseInt(input.value, 10) + dir;
			val = Math.max(parseInt(input.min, 10), Math.min(parseInt(input.max, 10), val));
			input.value = val;
			triggerSave(false);
		});
	});

	// ── Toggle: show/hide sub-fields ─────────────────────────
	document.querySelectorAll('.pvc-aside-section input[type="checkbox"]').forEach(function (cb) {
		cb.addEventListener('change', function () {
			var subFields = this.closest('.pvc-aside-section').querySelector('.pvc-sub-fields');
			if (subFields) subFields.classList.toggle('pvc-collapsed', !this.checked);
		});
	});

	// ── Display mode: show/hide watch layout sub-section ─────
	document.querySelectorAll('[data-setting="display_mode"]').forEach(function (radio) {
		radio.addEventListener('change', function () {
			var watchField = document.getElementById('pvc-watch-layout-field');
			if (watchField) watchField.classList.toggle('pvc-collapsed', this.value !== 'page');
		});
	});

	// ── Archive layout: show/hide broadcast playlist section ──
	document.querySelectorAll('[data-setting="archive_layout"]').forEach(function (radio) {
		radio.addEventListener('change', function () {
			var _bcField = document.getElementById('pvc-broadcast-field');
			if (_bcField) _bcField.classList.toggle('pvc-collapsed', this.value !== 'broadcast');
			if (this.value === 'broadcast') fetchYtPlaylists();
		});
	});

	// ── Broadcast playlists: event-delegated checkboxes → hidden JSON ──
	var bcPlaylistsInput = document.getElementById('pvc-bc-playlists-val');
	var bcField          = document.getElementById('pvc-broadcast-field');

	function syncBcPlaylists() {
		if (!bcPlaylistsInput) return;
		var checked = [];
		document.querySelectorAll('.pvc-bc-playlist-cb:checked').forEach(function (c) {
			checked.push(c.value);
		});
		bcPlaylistsInput.value = JSON.stringify(checked);
		triggerSave(false);
	}

	if (bcField) {
		bcField.addEventListener('change', function (e) {
			if (e.target.classList.contains('pvc-bc-playlist-cb')) syncBcPlaylists();
		});
	}

	// ── YouTube playlist fetcher ──────────────────────────────
	var ytListEl  = document.getElementById('pvc-yt-playlists');
	var ytFetched = false;

	function fetchYtPlaylists() {
		if (!ytListEl || ytFetched) return;
		ytFetched = true;

		var savedRaw = bcPlaylistsInput ? bcPlaylistsInput.value : '[]';
		var savedArr = [];
		try { savedArr = JSON.parse(savedRaw); } catch (e) {}

		var fd = new FormData();
		fd.append('action', 'pv_fetch_yt_playlists');
		fd.append('nonce', cfg.nonce);

		fetch(cfg.ajaxUrl, { method: 'POST', body: fd })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.success || !data.data || !data.data.length) {
					ytListEl.innerHTML = '<span class="pvc-hint">' + (data.data || 'No YouTube playlists found.') + '</span>';
					return;
				}
				var html = '';
				data.data.forEach(function (pl) {
					var checked = savedArr.indexOf('yt:' + pl.id) !== -1 ? ' checked' : '';
					html += '<label class="pvc-bc-playlist-item">'
						+ '<input type="checkbox" class="pvc-bc-playlist-cb" value="yt:' + pl.id + '"' + checked + '>'
						+ '<span class="pvc-bc-playlist-name">' + escAttr(pl.title) + ' (' + pl.count + ')</span>'
						+ '</label>';
				});
				ytListEl.innerHTML = html;
			})
			.catch(function () {
				ytListEl.innerHTML = '<span class="pvc-hint">Could not load YouTube playlists.</span>';
			});
	}

	function escAttr(str) {
		return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	}

	// Fetch when broadcast layout is already active on load
	if (bcField && !bcField.classList.contains('pvc-collapsed')) {
		fetchYtPlaylists();
	}

	// ── WP Color Picker (one init per picker element) ────────
	if ($.fn.wpColorPicker) {
		$('.pvc-color-picker').each(function () {
			var $el  = $(this);
			var key  = $el.data('setting');
			$el.wpColorPicker({
				change: function (event, ui) {
					var hex = ui.color.toString();
					var msg = { type: 'pv-update' };
					if (key === 'default_accent')      { msg.accent            = hex; }
					if (key === 'hero_title_color')    { msg.hero_title_color  = hex; }
					if (key === 'hero_subtitle_color') { msg.hero_sub_color    = hex; }
					sendMessage(msg);
					triggerSave(true); // color: save only, no reload
				},
				clear: function () {
					var msg = { type: 'pv-update' };
					if (key === 'default_accent')      { msg.accent           = '#4f46e5'; }
					if (key === 'hero_title_color')    { msg.hero_title_color = '#ffffff'; }
					if (key === 'hero_subtitle_color') { msg.hero_sub_color   = ''; }
					sendMessage(msg);
					triggerSave(true);
				},
			});
		});
	}

	// ── WP Media Uploader (hero background image) ────────────
	var mediaFrame    = null;
	var heroBgSelect  = document.getElementById('pvc-hero-bg-select');
	var heroBgClear   = document.getElementById('pvc-hero-bg-clear');
	var heroBgInput   = document.getElementById('pvc-hero-bg-input');
	var heroBgPreview = document.getElementById('pvc-hero-bg-preview');
	var overlayField  = document.getElementById('pvc-hero-overlay-field');

	if (heroBgSelect) {
		heroBgSelect.addEventListener('click', function () {
			if (!window.wp || !wp.media) return;
			if (mediaFrame) { mediaFrame.open(); return; }
			mediaFrame = wp.media({
				title:   'Select Hero Background Image',
				button:  { text: 'Use this image' },
				multiple: false,
				library: { type: 'image' },
			});
			mediaFrame.on('select', function () {
				var attachment = mediaFrame.state().get('selection').first().toJSON();
				var url = (attachment.sizes && attachment.sizes.full)
					? attachment.sizes.full.url
					: attachment.url;
				heroBgInput.value = url;
				heroBgPreview.style.backgroundImage = 'url(' + url + ')';
				heroBgPreview.classList.remove('pvc-media-preview--empty');
				var icon = heroBgPreview.querySelector('svg');
				if (icon) icon.remove();
				heroBgSelect.textContent = 'Change Image';
				if (heroBgClear) heroBgClear.classList.remove('pvc-hidden');
				if (overlayField) overlayField.style.display = '';
				triggerSave(false);
			});
			mediaFrame.open();
		});
	}

	if (heroBgClear) {
		heroBgClear.addEventListener('click', function () {
			if (heroBgInput) heroBgInput.value = '';
			if (heroBgPreview) {
				heroBgPreview.style.backgroundImage = '';
				heroBgPreview.classList.add('pvc-media-preview--empty');
				if (!heroBgPreview.querySelector('svg')) {
					heroBgPreview.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>';
				}
			}
			if (heroBgSelect) heroBgSelect.textContent = 'Upload / Select';
			heroBgClear.classList.add('pvc-hidden');
			if (overlayField) overlayField.style.display = 'none';
			triggerSave(false);
		});
	}

	// ── Settings change listeners ─────────────────────────────
	document.querySelector('.pvc-settings').addEventListener('change', function (e) {
		var el = e.target.closest('[data-setting]');
		if (!el) return;
		triggerSave(false);
	});

	document.querySelector('.pvc-settings').addEventListener('input', function (e) {
		var el = e.target.closest('[data-setting]');
		if (!el || el.classList.contains('pvc-color-picker')) return;
		var key = el.dataset.setting;
		if (key === 'hero_title' || key === 'hero_subtitle') {
			sendMessage({ type: 'pv-update', hero_title: getField('hero_title'), hero_subtitle: getField('hero_subtitle') });
			triggerSave(true);
		} else {
			triggerSave(false);
		}
	});

	// ── Device preview ────────────────────────────────────────
	document.querySelectorAll('.pvc-device-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			document.querySelectorAll('.pvc-device-btn').forEach(function (b) {
				b.classList.remove('pvc-device-btn--active');
			});
			this.classList.add('pvc-device-btn--active');
			frameWrap.dataset.device = this.dataset.device;
		});
	});

	// ── Refresh button ────────────────────────────────────────
	if (refreshBtn) {
		refreshBtn.addEventListener('click', function () {
			reloadIframe();
		});
	}

	// ── Publish button ────────────────────────────────────────
	if (publishBtn) {
		publishBtn.addEventListener('click', function () {
			var btn = this;
			btn.disabled = true;
			btn.textContent = 'Saving\u2026';

			var fd = new FormData();
			fd.append('action', 'pv_publish_settings');
			fd.append('nonce', cfg.nonce);
			fd.append('settings', JSON.stringify(collectSettings()));

			fetch(cfg.ajaxUrl, { method: 'POST', body: fd })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (data.success) {
						btn.classList.add('pvc-publish-btn--saved');
						btn.textContent = '\u2713 Published';
						showToast('Settings published successfully!', false);
					} else {
						btn.textContent = '\u2717 Error';
						showToast('Something went wrong. Please try again.', true);
					}
					setTimeout(function () {
						btn.classList.remove('pvc-publish-btn--saved');
						btn.textContent = 'Publish';
						btn.disabled = false;
					}, 2200);
				})
				.catch(function () {
					btn.textContent = 'Publish';
					btn.disabled = false;
					showToast('Network error. Please try again.', true);
				});
		});
	}

	// ── Iframe load tracking ──────────────────────────────────
	iframe.addEventListener('load', function () {
		iframeReady = true;
		frameLoader.classList.remove('pvc-frame-loading--visible');
		setStatus('ready', 'Ready');
	});

	// ── Init: load preview ────────────────────────────────────
	reloadIframe();

	// ────────────────────────────────────────────────────────
	// Helpers
	// ────────────────────────────────────────────────────────

	function collectSettings() {
		var s = {};
		document.querySelectorAll('[data-setting]').forEach(function (el) {
			var key = el.dataset.setting;
			if (el.type === 'checkbox') {
				s[key] = el.checked;
			} else if (el.type === 'radio') {
				if (el.checked) s[key] = el.value;
			} else {
				s[key] = el.value;
			}
		});
		return s;
	}

	function getField(key) {
		var el = document.querySelector('[data-setting="' + key + '"]');
		return el ? el.value : '';
	}

	function triggerSave(noReload) {
		clearTimeout(saveTimer);
		var delay = noReload ? 300 : 700;
		saveTimer = setTimeout(function () {
			var fd = new FormData();
			fd.append('action', 'pv_save_preview');
			fd.append('nonce', cfg.nonce);
			fd.append('settings', JSON.stringify(collectSettings()));

			setStatus('loading', 'Updating\u2026');

			fetch(cfg.ajaxUrl, { method: 'POST', body: fd })
				.then(function () {
					if (!noReload) {
						reloadIframe();
					} else {
						setStatus('ready', 'Ready');
					}
				});
		}, delay);
	}

	function reloadIframe() {
		iframeReady = false;
		frameLoader.classList.add('pvc-frame-loading--visible');
		setStatus('loading', 'Loading\u2026');
		iframe.src = cfg.previewUrl + '&_r=' + Date.now();
	}

	function sendMessage(data) {
		if (!iframe || !iframeReady) return;
		try {
			iframe.contentWindow.postMessage(data, '*');
		} catch (e) { /* cross-origin guard */ }
	}

	function setStatus(state, label) {
		if (!statusEl) return;
		statusEl.className = 'pvc-preview-status pvc-preview-status--' + state;
		var txt = statusEl.querySelector('.pvc-preview-status__text');
		if (txt) txt.textContent = label;
	}

	function showToast(msg, isError) {
		if (!toast) return;
		clearTimeout(toastTimer);
		toast.textContent = msg;
		toast.classList.toggle('pvc-toast--error', !!isError);
		toast.classList.add('pvc-toast--visible');
		toastTimer = setTimeout(function () {
			toast.classList.remove('pvc-toast--visible');
		}, 3000);
	}

}(jQuery));
