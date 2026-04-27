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

	// ── Nav rail open/close toggle ────────────────────────────
	var navRail       = document.getElementById('pvc-nav-rail');
	var navToggleBtn  = document.getElementById('pvc-nav-toggle');
	var navOpen       = localStorage.getItem('pvc_nav_open') === '1';

	function setNavOpen(open) {
		navOpen = open;
		if (navRail) navRail.classList.toggle('pvc-nav-rail--open', open);
		localStorage.setItem('pvc_nav_open', open ? '1' : '0');
	}

	setNavOpen(navOpen);

	if (navToggleBtn) {
		navToggleBtn.addEventListener('click', function () {
			setNavOpen(!navOpen);
		});
	}

	// ── Nav rail navigation ───────────────────────────────────
	document.querySelectorAll('.pvc-nav-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			document.querySelectorAll('.pvc-nav-btn').forEach(function (b) {
				b.classList.remove('pvc-nav-btn--active');
			});
			document.querySelectorAll('.pvc-panel').forEach(function (p) {
				p.classList.remove('pvc-panel--active');
			});
			this.classList.add('pvc-nav-btn--active');
			var panel = document.getElementById('pvc-panel-' + this.dataset.tab);
			if (panel) panel.classList.add('pvc-panel--active');
			// Auto-check live status when opening notifications panel
			if (this.dataset.tab === 'notifications') {
				checkLiveStatus();
				if (!notifTourDone) {
					notifTourDone = true;
					setTimeout(startTour, 500);
				}
			}
		});
	});

	// ── Live status checker ───────────────────────────────────
	var liveStatusEl  = document.getElementById('pvc-live-status');
	var liveDotEl     = document.getElementById('pvc-live-dot');
	var liveTextEl    = document.getElementById('pvc-live-text');
	var liveCheckBtn  = document.getElementById('pvc-live-check-btn');

	function checkLiveStatus() {
		if (!liveStatusEl) return;
		liveStatusEl.className = 'pvc-live-status';
		if (liveTextEl) liveTextEl.textContent = 'Checking live status\u2026';

		var fd = new FormData();
		fd.append('action', 'pv_check_live_status');
		fd.append('nonce', cfg.nonce);

		fetch(cfg.ajaxUrl, { method: 'POST', body: fd })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data.success) {
					if (liveTextEl) liveTextEl.textContent = data.data && data.data.message ? data.data.message : 'Could not check. Configure your API key and Channel ID in Settings.';
					liveStatusEl.classList.add('pvc-live-status--off');
					return;
				}
				if (data.data.live) {
					liveStatusEl.classList.add('pvc-live-status--on');
					if (liveTextEl) liveTextEl.textContent = 'Live now: ' + (data.data.title || 'Stream detected');
				} else {
					liveStatusEl.classList.add('pvc-live-status--off');
					if (liveTextEl) liveTextEl.textContent = 'Not currently live';
				}
			})
			.catch(function () {
				liveStatusEl.classList.add('pvc-live-status--off');
				if (liveTextEl) liveTextEl.textContent = 'Could not reach server';
			});
	}

	if (liveCheckBtn) {
		liveCheckBtn.addEventListener('click', checkLiveStatus);
	}

	// ── Test Mode toggle ──────────────────────────────────────
	var testModeBtn   = document.getElementById('pvc-test-mode-btn');
	var testModeLabel = testModeBtn ? testModeBtn.querySelector('.pvc-test-mode-btn__label') : null;
	var testModeOn      = false;
	var testIndicator   = document.getElementById('pvc-test-mode-indicator');

	var ALL_NOTIF_SECTIONS = ['pvc-section-live-feed', 'pvc-section-live-banner', 'pvc-section-new-video'];
	var testVideoField = document.getElementById('pvc-test-video-field');
	var testVideoInput = document.getElementById('pvc-test-video-id');

	// Reload iframe when a valid 11-char video ID is typed (debounced)
	if (testVideoInput) {
		testVideoInput.addEventListener('input', function () {
			if (!testModeOn) return;
			clearTimeout(saveTimer);
			saveTimer = setTimeout(function () { reloadIframe(); }, 800);
		});
	}

	function setTestMode(on) {
		testModeOn = on;
		if (testModeBtn) {
			testModeBtn.classList.toggle('pvc-test-mode-btn--active', on);
			if (testModeLabel) testModeLabel.textContent = on ? 'Disable Test Mode' : 'Enable Test Mode';
		}
		if (testIndicator) testIndicator.classList.toggle('pvc-test-mode-indicator--visible', on);

		// Disable "Check now" refresh during test mode so it can't wipe the status
		if (liveCheckBtn) liveCheckBtn.disabled = on;

		// Reflect test state in the live status indicator
		if (liveStatusEl) {
			if (on) {
				liveStatusEl.className = 'pvc-live-status pvc-live-status--on';
				if (liveTextEl) liveTextEl.textContent = 'Currently Live (Test Mode)';
			} else {
				// Restore real status, end any active tour
				endTour();
				checkLiveStatus();
			}
		}

		// Show/hide test video ID field
		if (testVideoField) testVideoField.classList.toggle('pvc-test-video-field--visible', on);

		// Mute/unmute all notification feature sections
		ALL_NOTIF_SECTIONS.forEach(function (id) {
			var el = document.getElementById(id);
			if (el) el.classList.toggle('pvc-aside-section--muted', on);
		});

		reloadIframe();
	}

	if (testModeBtn) {
		testModeBtn.addEventListener('click', function () {
			setTestMode(!testModeOn);
		});
	}

	// ── Notifications feature tour ───────────────────────────────
	var notifTourDone     = localStorage.getItem('pvc_notif_tour_done') === '1';
	var tourCalloutEl     = null;
	var tourReplayBtn     = document.getElementById('pvc-tour-replay-btn');
	var previewCalloutEl  = document.getElementById('pvc-preview-callout');
	var previewCalloutLbl = document.getElementById('pvc-preview-callout-label');
	var frameDimEl        = document.getElementById('pvc-frame-dim');

	var TOUR_STEPS = [
		{
			sectionId    : 'pvc-section-live-banner',
			icon         : '🔴',
			title        : 'Sitewide Live Banner',
			body         : 'When your YouTube channel goes live, a full-width strip automatically appears at the top of every page on your site with a Watch Live link. Visitors dismiss it per-stream and it won\'t appear again for the same broadcast.',
			step         : '1 of 2',
			isLast       : false,
			calloutClass : 'pvc-preview-callout--banner',
			calloutLabel : '↑ Live Banner'
		},
		{
			sectionId    : 'pvc-section-new-video',
			icon         : '🎬',
			title        : 'New Video Alerts',
			body         : 'Whenever new videos are imported to your library, a small toast notification slides in once per visitor as a gentle nudge to check out your latest content. They dismiss it on their own and won\'t see it again.',
			step         : '2 of 2',
			isLast       : true,
			calloutClass : 'pvc-preview-callout--toast',
			calloutLabel : '🎬 New Video Alert'
		}
	];

	var TOUR_SECTION_IDS = TOUR_STEPS.map(function (s) { return s.sectionId; });

	function setPreviewCallout(step) {
		if (!previewCalloutEl) return;
		previewCalloutEl.className = 'pvc-preview-callout';
		if (frameDimEl) frameDimEl.classList.remove('pvc-frame-dim--visible');
		if (!step) return;
		previewCalloutEl.classList.add(step.calloutClass);
		if (previewCalloutLbl) previewCalloutLbl.textContent = step.calloutLabel;
		requestAnimationFrame(function () {
			requestAnimationFrame(function () {
				previewCalloutEl.classList.add('pvc-preview-callout--visible');
				if (frameDimEl) frameDimEl.classList.add('pvc-frame-dim--visible');
			});
		});
	}

	function startTour() {
		// Sections are already muted by setTestMode; just ensure tour sections are muted
		TOUR_SECTION_IDS.forEach(function (id) {
			var el = document.getElementById(id);
			if (el) el.classList.add('pvc-aside-section--muted');
		});
		if (tourCalloutEl) { tourCalloutEl.remove(); tourCalloutEl = null; }
		showTourStep(0);
	}

	function showTourStep(stepIdx) {
		var step = TOUR_STEPS[stepIdx];

		// Deactivate all tour sections, then spotlight current
		TOUR_SECTION_IDS.forEach(function (id) {
			var el = document.getElementById(id);
			if (el) {
				el.classList.add('pvc-aside-section--muted');
				el.classList.remove('pvc-aside-section--tour-active');
			}
		});
		var activeEl = document.getElementById(step.sectionId);
		if (activeEl) {
			activeEl.classList.remove('pvc-aside-section--muted');
			activeEl.classList.add('pvc-aside-section--tour-active');
		}

		// Update preview callout
		setPreviewCallout(step);

		// Build sidebar callout card
		var callout = document.createElement('div');
		callout.className = 'pvc-tour-callout';
		callout.innerHTML =
			'<span class="pvc-tour-callout__icon">' + step.icon + '</span>'
			+ '<div class="pvc-tour-callout__title">' + step.title + '</div>'
			+ '<p class="pvc-tour-callout__body">' + step.body + '</p>'
			+ '<div class="pvc-tour-callout__footer">'
			+ '<span class="pvc-tour-callout__step">' + step.step + '</span>'
			+ (step.isLast
				? '<button class="pvc-tour-callout__btn pvc-tour-callout__btn--done" type="button">Got it!</button>'
				: '<button class="pvc-tour-callout__btn" type="button">Next &rarr;</button>')
			+ '</div>';

		// Exit-animate old callout, insert new
		if (tourCalloutEl) {
			tourCalloutEl.classList.add('pvc-tour-callout--exit');
			var old = tourCalloutEl;
			setTimeout(function () { if (old.parentNode) old.remove(); }, 300);
		}

		tourCalloutEl = callout;
		if (activeEl) activeEl.insertAdjacentElement('afterend', callout);

		requestAnimationFrame(function () {
			requestAnimationFrame(function () {
				callout.classList.add('pvc-tour-callout--visible');
			});
		});

		var actionBtn = callout.querySelector('.pvc-tour-callout__btn');
		if (actionBtn) {
			actionBtn.addEventListener('click', function () {
				if (step.isLast) endTour();
				else showTourStep(stepIdx + 1);
			});
		}
	}

	function endTour() {
		// Hide sidebar callout
		if (tourCalloutEl) {
			tourCalloutEl.classList.remove('pvc-tour-callout--visible');
			var c = tourCalloutEl;
			setTimeout(function () { if (c.parentNode) c.remove(); }, 300);
			tourCalloutEl = null;
		}
		// Hide preview callout
		setPreviewCallout(null);
		// Re-apply muting based on current test mode state
		TOUR_SECTION_IDS.forEach(function (id) {
			var el = document.getElementById(id);
			if (el) {
				el.classList.toggle('pvc-aside-section--muted', testModeOn);
				el.classList.remove('pvc-aside-section--tour-active');
			}
		});
		localStorage.setItem('pvc_notif_tour_done', '1');
		notifTourDone = true;
	}

	if (tourReplayBtn) {
		tourReplayBtn.addEventListener('click', startTour);
	}

	var PVC_TIPS = {
		'detect-theme': 'Scans your active theme\'s stylesheets and Customizer settings for brand colors. CSS custom properties declared as --variable-name: #hex are picked up automatically. Click any swatch to apply it as your accent color.',
	};

	var pvcTipPop    = document.getElementById('pvc-tip-pop');
	var pvcTipText   = pvcTipPop ? pvcTipPop.querySelector('.pvc-tip-pop__text')  : null;
	var pvcTipArrow  = pvcTipPop ? pvcTipPop.querySelector('.pvc-tip-pop__arrow') : null;
	var pvcActiveBtn = null;

	function showPvcTip(btn, key) {
		if (!pvcTipPop || !pvcTipText) return;
		if (pvcActiveBtn === btn && !pvcTipPop.hidden) {
			pvcTipPop.hidden = true;
			btn.classList.remove('pvc-tooltip-btn--active');
			pvcActiveBtn = null;
			return;
		}
		if (pvcActiveBtn) pvcActiveBtn.classList.remove('pvc-tooltip-btn--active');
		pvcActiveBtn = btn;
		btn.classList.add('pvc-tooltip-btn--active');
		pvcTipText.textContent = PVC_TIPS[key] || '';
		pvcTipPop.hidden = false;

		var rect = btn.getBoundingClientRect();
		var popW = 260;
		var left = rect.left + rect.width / 2 - popW / 2;
		left = Math.max(8, Math.min(left, document.documentElement.clientWidth - popW - 8));
		pvcTipPop.style.top  = (rect.bottom + 8) + 'px';
		pvcTipPop.style.left = left + 'px';
		pvcTipPop.style.width = popW + 'px';
		if (pvcTipArrow) pvcTipArrow.style.left = Math.max(8, (rect.left + rect.width / 2) - left - 5) + 'px';
	}

	// Close popover on any outside click
	document.addEventListener('click', function () {
		if (pvcTipPop && !pvcTipPop.hidden) {
			pvcTipPop.hidden = true;
			if (pvcActiveBtn) { pvcActiveBtn.classList.remove('pvc-tooltip-btn--active'); pvcActiveBtn = null; }
		}
	});

	document.querySelectorAll('.pvc-tooltip-btn').forEach(function (btn) {
		btn.addEventListener('click', function (e) {
			e.stopPropagation(); // prevent document listener from immediately closing the popover
			if (btn.dataset.tip) {
				showPvcTip(btn, btn.dataset.tip);
				return;
			}
			var idx = parseInt(btn.dataset.tourStep, 10);
			if (!isNaN(idx)) showTourStep(idx);
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

	// ── Archive layout: refresh playlist list when layout changes ──
	document.querySelectorAll('[data-setting="archive_layout"]').forEach(function (radio) {
		radio.addEventListener('change', function () {
			fetchYtPlaylists();
		});
	});

	// ── Broadcast playlists: event-delegated checkboxes → hidden JSON ──
	var bcPlaylistsInput      = document.getElementById('pvc-bc-playlists-val');
	var bcPlaylistTitlesInput = document.getElementById('pvc-bc-playlist-titles-val');
	var bcField               = document.getElementById('pvc-broadcast-field');

	function syncBcPlaylists() {
		if (!bcPlaylistsInput) return;
		var checked = [];
		var titles  = {};
		document.querySelectorAll('.pvc-bc-playlist-cb:checked').forEach(function (c) {
			checked.push(c.value);
			var plId    = c.value.replace(/^yt:/, '');
			var plTitle = c.dataset.plTitle || '';
			if (plId && plTitle) titles[plId] = plTitle;
		});
		bcPlaylistsInput.value = JSON.stringify(checked);
		if (bcPlaylistTitlesInput) bcPlaylistTitlesInput.value = JSON.stringify(titles);
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

	function renderYtPlaylists(playlists) {
		// Read saved state fresh at render time so timing of the fetch doesn't matter.
		var savedRaw = bcPlaylistsInput ? bcPlaylistsInput.value : '[]';
		var savedArr = [];
		try { savedArr = JSON.parse(savedRaw); } catch (e) {}

		var html = '';
		playlists.forEach(function (pl) {
			if (!pl.count) return; // skip 0-item playlists
			var checked = savedArr.indexOf('yt:' + pl.id) !== -1 ? ' checked' : '';
			html += '<label class="pvc-bc-playlist-item">'
				+ '<input type="checkbox" class="pvc-bc-playlist-cb"'
				+ ' value="yt:' + escAttr(pl.id) + '"'
				+ ' data-pl-title="' + escAttr(pl.title) + '"'
				+ checked + '>'
				+ '<span class="pvc-bc-playlist-name">' + escAttr(pl.title) + ' (' + pl.count + ')</span>'
				+ '</label>';
		});
		ytListEl.innerHTML = html || '<span class="pvc-hint">No YouTube playlists with videos found.</span>';
	}

	function fetchYtPlaylists() {
		if (!ytListEl || ytFetched) return;
		ytFetched = true;

		// Serve from sessionStorage if available (avoids repeat API round-trip)
		var cacheKey = 'pv_yt_playlists';
		try {
			var cached = sessionStorage.getItem(cacheKey);
			if (cached) {
				var cachedList = JSON.parse(cached);
				if (cachedList && cachedList.length) {
					renderYtPlaylists(cachedList);
					return;
				}
			}
		} catch (e) {}

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
				try { sessionStorage.setItem(cacheKey, JSON.stringify(data.data)); } catch (e) {}
				renderYtPlaylists(data.data);
			})
			.catch(function () {
				ytListEl.innerHTML = '<span class="pvc-hint">Could not load YouTube playlists.</span>';
			});
	}

	function escAttr(str) {
		return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	}

	// Fetch playlists on load (section is always visible now)
	if (bcField) {
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
					if (key === 'page_bg_color')       { msg.page_bg           = hex; }
					if (key === 'sidebar_bg_color')    { msg.sidebar_bg        = hex; }
					sendMessage(msg);
					triggerSave(true); // color: save only, no reload
				},
				clear: function () {
					var msg = { type: 'pv-update' };
					if (key === 'default_accent')      { msg.accent           = '#4f46e5'; }
					if (key === 'hero_title_color')    { msg.hero_title_color = '#ffffff'; }
					if (key === 'hero_subtitle_color') { msg.hero_sub_color   = ''; }
					if (key === 'page_bg_color')       { msg.page_bg          = '#0c0c18'; }
					if (key === 'sidebar_bg_color')    { msg.sidebar_bg       = '#0f0f1e'; }
					sendMessage(msg);
					triggerSave(true);
				},
			});
		});
	}

	// ── Detect theme colors ──────────────────────────────────────────
	var detectBtn  = document.getElementById('pvc-detect-colors-btn');
	var swatchesEl = document.getElementById('pvc-color-swatches');

	if (detectBtn && swatchesEl) {
		var detectBtnHTML = detectBtn.innerHTML;

		detectBtn.addEventListener('click', function () {
			if (detectBtn.disabled) return;
			detectBtn.disabled   = true;
			detectBtn.textContent = 'Detecting…';

			var fd = new FormData();
			fd.append('action', 'pv_detect_theme_colors');
			fd.append('nonce',  cfg.nonce);

			fetch(cfg.ajaxUrl, { method: 'POST', body: fd })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					detectBtn.disabled  = false;
					detectBtn.innerHTML = detectBtnHTML;

					if (!res.success || !res.data || !res.data.length) {
						showToast('No theme colors detected.', true);
						return;
					}

					swatchesEl.innerHTML = '';
					res.data.forEach(function (item) {
						var btn = document.createElement('button');
						btn.type      = 'button';
						btn.className = 'pvc-swatch';
						btn.title     = item.name + ': ' + item.color;
						btn.style.background = item.color;
						btn.setAttribute('data-color', item.color);
						btn.addEventListener('click', function () {
							var $accentPicker = $('[data-setting="default_accent"]');
							if ($accentPicker.length && $.fn.wpColorPicker) {
								$accentPicker.wpColorPicker('color', item.color);
							}
							sendMessage({ type: 'pv-update', accent: item.color });
							triggerSave(true);
							swatchesEl.hidden = true;
						});
						swatchesEl.appendChild(btn);
					});

					swatchesEl.hidden = false;
				})
				.catch(function () {
					detectBtn.disabled  = false;
					detectBtn.innerHTML = detectBtnHTML;
					showToast('Could not detect theme colors.', true);
				});
		});

		document.addEventListener('click', function (e) {
			if (!swatchesEl.hidden && !swatchesEl.contains(e.target) && e.target !== detectBtn) {
				swatchesEl.hidden = true;
			}
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
						// Reload iframe to the live archive URL (no preview params) so
						// the user immediately sees the published state.
						iframeReady = false;
						frameLoader.classList.add('pvc-frame-loading--visible');
						setStatus('loading', 'Loading live page\u2026');
						iframe.src = cfg.archiveUrl + '?_r=' + Date.now();
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
		var url = cfg.previewUrl + '&_r=' + Date.now();
		if (testModeOn) {
			url += '&pv_force_live=1';
			var vid = testVideoInput ? testVideoInput.value.trim() : '';
			if (vid) url += '&pv_test_video_id=' + encodeURIComponent(vid);
		}
		iframe.src = url;
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
