/**
 * PressVideo — Archive filter (animated) + search + infinite scroll.
 * Vanilla JS. No dependencies.
 */
(function () {
	'use strict';

	/* ── Wall layout: re-assign bento span classes by visible index ── */

	function reIndexWall() {
		var wall = document.querySelector('.pv-wall');
		if (!wall) return;
		var cards = Array.from(wall.querySelectorAll('.pv-card:not([hidden])'));
		cards.forEach(function (card, i) {
			card.classList.remove('pv-wall-span-2x2', 'pv-wall-span-2x1');
			var pos = i % 5;
			if (pos === 0) card.classList.add('pv-wall-span-2x2');
			else if (pos === 3) card.classList.add('pv-wall-span-2x1');
		});
	}

	reIndexWall();

	/* ── Shared state ─────────────────────────────────────────────── */

	var main = document.querySelector('.pv-archive-main');
	if (!main) return;

	var ajaxUrl = (window.pvOffcanvas && window.pvOffcanvas.ajaxUrl) || '';

	setupBroadcast();

	function getContainer() {
		return main.querySelector('.pv-wall')
			|| main.querySelector('.pv-spotlight-grid')
			|| main.querySelector('.pv-list')
			|| main.querySelector('.pv-grid');
	}

	/* ── Search ───────────────────────────────────────────────────── */

	var searchInput   = main.querySelector('.pv-search-input');
	var searchClear   = main.querySelector('.pv-search-clear');
	var searchResults = main.querySelector('.pv-search-results');
	var searchMsg     = main.querySelector('.pv-search-results-msg');
	var searchTimer   = null;
	var searchActive  = false;

	if (searchInput) {
		searchInput.addEventListener('input', function () {
			var q = this.value.trim();
			if (searchClear) searchClear.hidden = !q;
			clearTimeout(searchTimer);
			if (!q) { clearSearch(); return; }
			searchTimer = setTimeout(function () { doSearch(q); }, 380);
		});

		if (searchClear) {
			searchClear.addEventListener('click', function () {
				searchInput.value = '';
				searchClear.hidden = true;
				clearSearch();
				searchInput.focus();
			});
		}

		var searchMicBtn = main.querySelector('.pv-search-mic-btn');
		var SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
		if (searchMicBtn && SpeechRec) {
			var recognition = null;
			var micOn = false;
			searchMicBtn.addEventListener('click', function () {
				if (micOn) { if (recognition) recognition.stop(); return; }
				recognition = new SpeechRec();
				recognition.lang = 'en-US';
				recognition.interimResults = false;
				recognition.maxAlternatives = 1;
				recognition.onstart  = function () { micOn = true;  searchMicBtn.classList.add('pv-search-mic-btn--active'); };
				recognition.onresult = function (e) {
					var t = e.results[0][0].transcript;
					searchInput.value = t;
					if (searchClear) searchClear.hidden = false;
					doSearch(t);
				};
				recognition.onend    = function () { micOn = false; searchMicBtn.classList.remove('pv-search-mic-btn--active'); };
				recognition.onerror  = function () { micOn = false; searchMicBtn.classList.remove('pv-search-mic-btn--active'); };
				try { recognition.start(); } catch (err) { micOn = false; }
			});
		} else if (searchMicBtn) {
			searchMicBtn.style.display = 'none';
		}
	}

	function doSearch(q) {
		if (!ajaxUrl || !searchResults) return;

		if (!searchActive) {
			searchActive = true;
			main.classList.add('pv-archive-main--searching');
		}

		searchResults.hidden = false;
		searchResults.innerHTML = '<div class="pv-search-loading"><span class="pv-scroll-spinner"></span></div>';
		if (searchMsg) { searchMsg.textContent = 'Searching\u2026'; searchMsg.hidden = false; }

		fetch(ajaxUrl + '?action=pv_search_videos&q=' + encodeURIComponent(q))
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (!data || !data.success) return;
				var res = data.data;
				if (res.html) {
					searchResults.innerHTML = res.html;
				} else {
					searchResults.innerHTML = '<p class="pv-no-videos">No videos found for \u201c' + escHtml(res.query) + '\u201d.</p>';
				}
				if (searchMsg) {
					if (res.count > 0) {
						var label = res.count > 50
							? 'Showing 50 of ' + res.count + ' results for \u201c' + res.query + '\u201d'
							: res.count + ' result' + (res.count !== 1 ? 's' : '') + ' for \u201c' + res.query + '\u201d';
						searchMsg.textContent = label;
						searchMsg.hidden = false;
					} else {
						searchMsg.hidden = true;
					}
				}
			})
			.catch(function () {
				searchResults.innerHTML = '<p class="pv-no-videos">Search failed. Please try again.</p>';
				if (searchMsg) searchMsg.hidden = true;
			});
	}

	function clearSearch() {
		if (!searchActive) return;
		searchActive = false;
		main.classList.remove('pv-archive-main--searching');
		if (searchResults) { searchResults.hidden = true; searchResults.innerHTML = ''; }
		if (searchMsg) searchMsg.hidden = true;
		reIndexWall();
	}

	function escHtml(str) {
		return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
	}

	/* ── Filter with transition animations ─────────────────────────── */

	var bar = document.querySelector('.pv-filter-bar');

	if (bar) {
		bar.addEventListener('click', function (e) {
			var btn = e.target.closest('.pv-filter-btn');
			if (!btn) return;

			var filter = btn.dataset.filter;

			bar.querySelectorAll('.pv-filter-btn').forEach(function (b) {
				b.classList.toggle('pv-filter-btn--active', b === btn);
			});

			var items  = document.querySelectorAll('[data-category]');
			var toHide = [], toShow = [];

			items.forEach(function (item) {
				var matches = filter === '*' || item.dataset.category === filter;
				if ( !item.hidden && !matches ) toHide.push(item);
				if (  item.hidden &&  matches ) toShow.push(item);
			});

			// Phase 1: exit animation on items being hidden
			toHide.forEach(function (item) {
				item.classList.add('pv-filter-exit');
			});

			setTimeout(function () {
				toHide.forEach(function (item) {
					item.hidden = true;
					item.classList.remove('pv-filter-exit');
				});

				reIndexWall();

				toShow.forEach(function (item) {
					item.hidden = false;
					item.classList.add('pv-filter-enter');
				});

				requestAnimationFrame(function () {
					requestAnimationFrame(function () {
						toShow.forEach(function (item, i) {
							item.style.transitionDelay = Math.min(i * 35, 280) + 'ms';
							item.classList.remove('pv-filter-enter');
						});
					});
				});

				setTimeout(function () {
					toShow.forEach(function (item) { item.style.transitionDelay = ''; });
				}, Math.min(toShow.length * 35, 280) + 450);

			}, 200);
		});
	}

	/* ── Broadcast layout ───────────────────────────────────────────── */

	function setupBroadcast() {
		var bc = document.querySelector('.pv-broadcast');
		if (!bc) return;

		// ── Tab switching with sliding indicator ──────────────────────
		var bcTabs      = bc.querySelectorAll('.pv-bc-tab');
		var bcPanels    = bc.querySelectorAll('.pv-bc-panel');
		var bcIndicator = bc.querySelector('.pv-bc-tab-indicator');
		var bcTabBar    = bc.querySelector('.pv-bc-tabs');

		// ── Broadcast lazy tab loader ─────────────────────────────────
		var bcAjaxUrl = (window.pvBroadcast && window.pvBroadcast.ajaxUrl) || (window.pvOffcanvas && window.pvOffcanvas.ajaxUrl) || '';
		var bcNonce   = (window.pvBroadcast && window.pvBroadcast.nonce)   || '';

		function loadBcLazy(container, type) {
			if (!bcAjaxUrl) return;
			var fd = new FormData();
			fd.append('action', 'pv_bc_' + type);
			fd.append('nonce',  bcNonce);
			fd.append('page',   '1');
			fetch(bcAjaxUrl, { method: 'POST', body: fd })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (!data.success) { container.innerHTML = '<p class="pv-no-videos">Could not load content.</p>'; return; }
					container.innerHTML = data.data.html || '<p class="pv-no-videos">No content found.</p>';
				})
				.catch(function () {
					container.innerHTML = '<p class="pv-no-videos">Could not load content.</p>';
				});
		}

		function activateBcTab(tab) {
			bcTabs.forEach(function (t) {
				t.classList.remove('pv-bc-tab--active');
				t.setAttribute('aria-selected', 'false');
			});
			tab.classList.add('pv-bc-tab--active');
			tab.setAttribute('aria-selected', 'true');
			var key = tab.dataset.bcTab;
			bcPanels.forEach(function (p) {
				p.classList.toggle('pv-bc-panel--active', p.dataset.bcPanel === key);
			});
			if (bcIndicator && bcTabBar) {
				var tabRect = tab.getBoundingClientRect();
				var barRect = bcTabBar.getBoundingClientRect();
				bcIndicator.style.left  = (tabRect.left - barRect.left) + 'px';
				bcIndicator.style.width = tabRect.width + 'px';
			}
			// Lazy-load Videos / Playlists panel content on first activation
			var panel = bc.querySelector('.pv-bc-panel[data-bc-panel="' + key + '"]');
			if (panel) {
				var lazy = panel.querySelector('[data-bc-lazy]');
				if (lazy && !lazy.dataset.bcLoaded) {
					lazy.dataset.bcLoaded = 'true';
					loadBcLazy(lazy, lazy.dataset.bcLazy);
				}
			}
		}

		bcTabs.forEach(function (tab) {
			tab.addEventListener('click', function () { activateBcTab(this); });
		});

		// "View All" buttons on Home sections switch to the target tab
		bc.addEventListener('click', function (e) {
			var switchBtn = e.target.closest('.pv-bc-tab-switch');
			if (!switchBtn) return;
			var targetTab = bc.querySelector('.pv-bc-tab[data-bc-tab="' + switchBtn.dataset.targetTab + '"]');
			if (targetTab) activateBcTab(targetTab);
		});

		var activeTab = bc.querySelector('.pv-bc-tab--active');
		if (activeTab) { requestAnimationFrame(function () { activateBcTab(activeTab); }); }

		// ── Sort bar (Videos tab) ────────────────────────────────────
		var bcSortBar = bc.querySelector('.pv-bc-sort-bar');
		if (bcSortBar) {
			bcSortBar.addEventListener('click', function (e) {
				var btn = e.target.closest('.pv-bc-sort-btn');
				if (!btn) return;
				bcSortBar.querySelectorAll('.pv-bc-sort-btn').forEach(function (b) {
					b.classList.remove('pv-bc-sort-btn--active');
				});
				btn.classList.add('pv-bc-sort-btn--active');
				var sort = btn.dataset.sort;
				var grid = bc.querySelector('.pv-bc-video-grid');
				if (!grid) return;
				var cards = Array.from(grid.children);
				cards.sort(function (a, b) {
					if (sort === 'latest')  return parseInt(b.dataset.date  || 0, 10) - parseInt(a.dataset.date  || 0, 10);
					if (sort === 'oldest')  return parseInt(a.dataset.date  || 0, 10) - parseInt(b.dataset.date  || 0, 10);
					if (sort === 'popular') return parseInt(b.dataset.views || 0, 10) - parseInt(a.dataset.views || 0, 10);
					return 0;
				});
				cards.forEach(function (card) { grid.appendChild(card); });
			});
		}

		// ── Chip filter (Videos tab) ──────────────────────────────────
		var bcChips = bc.querySelector('.pv-bc-chips');
		if (bcChips) {
			bcChips.addEventListener('click', function (e) {
				var chip = e.target.closest('.pv-bc-chip');
				if (!chip) return;
				var filter = chip.dataset.filter;
				bcChips.querySelectorAll('.pv-bc-chip').forEach(function (c) {
					c.classList.toggle('pv-bc-chip--active', c === chip);
				});
				var grid = bc.querySelector('.pv-bc-video-grid');
				if (!grid) return;
				var items = grid.querySelectorAll('[data-category]');
				var toHide = [], toShow = [];
				items.forEach(function (item) {
					var matches = filter === '*' || item.dataset.category === filter;
					if (!item.hidden && !matches) toHide.push(item);
					if (item.hidden  &&  matches) toShow.push(item);
				});
				toHide.forEach(function (item) { item.classList.add('pv-filter-exit'); });
				setTimeout(function () {
					toHide.forEach(function (item) { item.hidden = true; item.classList.remove('pv-filter-exit'); });
					toShow.forEach(function (item) { item.hidden = false; item.classList.add('pv-filter-enter'); });
					requestAnimationFrame(function () {
						requestAnimationFrame(function () {
							toShow.forEach(function (item, i) {
								item.style.transitionDelay = Math.min(i * 35, 280) + 'ms';
								item.classList.remove('pv-filter-enter');
							});
						});
					});
					setTimeout(function () {
						toShow.forEach(function (item) { item.style.transitionDelay = ''; });
					}, Math.min(toShow.length * 35, 280) + 450);
				}, 200);
			});
		}

		// ── Broadcast search ──────────────────────────────────────────
		var bcInput   = bc.querySelector('.pv-bc-search-input');
		var bcClear   = bc.querySelector('.pv-bc-search-clear');
		var bcResults = bc.querySelector('.pv-bc-search-results');
		var bcMsg     = bc.querySelector('.pv-bc-search-msg');
		var bcActive  = false;
		var bcTimer   = null;

		if (bcInput) {
			bcInput.addEventListener('input', function () {
				var q = this.value.trim();
				if (bcClear) bcClear.hidden = !q;
				clearTimeout(bcTimer);
				if (!q) { clearBcSearch(); return; }
				bcTimer = setTimeout(function () { doBcSearch(q); }, 380);
			});
			if (bcClear) {
				bcClear.addEventListener('click', function () {
					bcInput.value = '';
					bcClear.hidden = true;
					clearBcSearch();
					bcInput.focus();
				});
			}
		}

		function doBcSearch(q) {
			if (!ajaxUrl || !bcResults) return;
			if (!bcActive) { bcActive = true; bc.classList.add('pv-bc-searching'); }
			bcResults.hidden = false;
			bcResults.innerHTML = '<div class="pv-search-loading"><span class="pv-scroll-spinner"></span></div>';
			if (bcMsg) { bcMsg.textContent = 'Searching\u2026'; bcMsg.hidden = false; }
			fetch(ajaxUrl + '?action=pv_search_videos&q=' + encodeURIComponent(q))
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (!data || !data.success) return;
					var res = data.data;
					bcResults.innerHTML = res.html || '<p class="pv-no-videos">No videos found for \u201c' + escHtml(res.query) + '\u201d.</p>';
					if (bcMsg) {
						if (res.count > 0) {
							var label = res.count > 50
								? 'Showing 50 of ' + res.count + ' results for \u201c' + res.query + '\u201d'
								: res.count + ' result' + (res.count !== 1 ? 's' : '') + ' for \u201c' + res.query + '\u201d';
							bcMsg.textContent = label; bcMsg.hidden = false;
						} else { bcMsg.hidden = true; }
					}
				})
				.catch(function () {
					bcResults.innerHTML = '<p class="pv-no-videos">Search failed. Please try again.</p>';
					if (bcMsg) bcMsg.hidden = true;
				});
		}

		function clearBcSearch() {
			if (!bcActive) return;
			bcActive = false;
			bc.classList.remove('pv-bc-searching');
			if (bcResults) { bcResults.hidden = true; bcResults.innerHTML = ''; }
			if (bcMsg) bcMsg.hidden = true;
		}

		// ── Mic button (Web Speech API) ───────────────────────────────
		var micBtn = bc.querySelector('.pv-bc-mic-btn');
		var SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
		if (micBtn && SpeechRec) {
			var recognition = null;
			var micOn = false;
			micBtn.addEventListener('click', function () {
				if (micOn) { if (recognition) recognition.stop(); return; }
				recognition = new SpeechRec();
				recognition.lang = 'en-US';
				recognition.interimResults = false;
				recognition.maxAlternatives = 1;
				recognition.onstart = function () { micOn = true; micBtn.classList.add('pv-bc-mic-btn--active'); };
				recognition.onresult = function (e) {
					var t = e.results[0][0].transcript;
					if (bcInput) { bcInput.value = t; if (bcClear) bcClear.hidden = false; doBcSearch(t); }
				};
				recognition.onend  = function () { micOn = false; micBtn.classList.remove('pv-bc-mic-btn--active'); };
				recognition.onerror = function () { micOn = false; micBtn.classList.remove('pv-bc-mic-btn--active'); };
				try { recognition.start(); } catch (err) { micOn = false; }
			});
		} else if (micBtn) {
			micBtn.style.display = 'none';
		}
	}

	/* ── Live customizer preview bridge ──────────────────────────────── */
	window.addEventListener('message', function (e) {
		var d = e.data;
		if (!d || d.type !== 'pv-update') return;

		if (d.page_bg) {
			var wrap    = document.querySelector('.pv-archive-wrap');
			var content = document.querySelector('.pv-archive-content');
			if (wrap)    { wrap.style.background    = d.page_bg; wrap.style.backgroundColor    = d.page_bg; }
			if (content) { content.style.background = d.page_bg; content.style.backgroundColor = d.page_bg; }
		}
		if (d.sidebar_bg) {
			var aside = document.querySelector('.pv-archive-aside');
			if (aside) aside.style.setProperty('--pv-sidebar-bg', d.sidebar_bg);
		}
		if (d.accent) {
			document.querySelectorAll('.pv-archive-wrap').forEach(function (el) {
				el.style.setProperty('--pv-accent', d.accent);
			});
		}
		if (d.hero_title) {
			var ht = document.querySelector('[data-pv-hero-title]');
			if (ht) ht.textContent = d.hero_title;
		}
		if (d.hero_subtitle !== undefined) {
			var hs = document.querySelector('[data-pv-hero-sub]');
			if (hs) hs.textContent = d.hero_subtitle;
		}
		if (d.hero_title_color) {
			var htEl = document.querySelector('[data-pv-hero-title]');
			if (htEl) htEl.style.color = d.hero_title_color;
		}
		if (d.hero_sub_color !== undefined) {
			var hsEl = document.querySelector('[data-pv-hero-sub]');
			if (hsEl) hsEl.style.color = d.hero_sub_color || '';
		}
	});

	/* ── Live feed: pause when offcanvas opens, resume when it closes ── */
	(function () {
		function sendYT(iframe, func) {
			if (!iframe) return;
			try {
				iframe.contentWindow.postMessage(
					JSON.stringify({ event: 'command', func: func, args: '' }), '*'
				);
			} catch (e) { /* cross-origin guard */ }
		}

		function getLiveIframe() {
			return document.querySelector('.pv-live-now__embed iframe');
		}

		document.addEventListener('pv:opened', function () {
			sendYT(getLiveIframe(), 'pauseVideo');
		});

		document.addEventListener('pv:closed', function () {
			sendYT(getLiveIframe(), 'playVideo');
		});
	}());

}());
