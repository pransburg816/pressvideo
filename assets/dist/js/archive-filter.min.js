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

	/* ── AJAX page loading ──────────────────────────────────────────────── */

	var pvLoading = false;
	var pvCurrentPerPage = new URLSearchParams(window.location.search).get('per_page') || '20';
	var pvAjaxUrl = (window.pvBroadcast && window.pvBroadcast.ajaxUrl)
		|| (window.pvOffcanvas && window.pvOffcanvas.ajaxUrl)
		|| '';

	function pvLoadPage(page, perPage) {
		if (pvLoading) return;
		pvLoading = true;

		var wrap = document.getElementById('pv-layout-wrap');
		if (!wrap) { pvLoading = false; return; }

		wrap.innerHTML = '<div class="pv-ajax-spinner"><span class="pv-spin"></span></div>';

		var fd = new FormData();
		fd.append('action', 'pv_load_page');
		fd.append('nonce', (window.pvBroadcast && window.pvBroadcast.loadPageNonce) || '');
		fd.append('page', String(page));
		fd.append('per_page', String(perPage));

		fetch(pvAjaxUrl, { method: 'POST', body: fd })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				pvLoading = false;
				if (!data || !data.success) {
					wrap.innerHTML = '<p class="pv-no-videos">Failed to load videos. Please try again.</p>';
					return;
				}
				var d = data.data;
				wrap.innerHTML = d.html || '';

				var topPag = document.getElementById('pv-top-pagination');
				if (topPag) topPag.innerHTML = d.pagination || '';

				var botPag = document.getElementById('pv-bottom-pagination');
				if (botPag) botPag.innerHTML = d.pagination || '';

				pvCurrentPerPage = String(perPage);
				document.querySelectorAll('.pv-per-page__btn').forEach(function (btn) {
					var pp = btn.dataset.perPage || '';
					var active = pp === pvCurrentPerPage;
					btn.classList.toggle('pv-per-page__btn--active', active);
					btn.setAttribute('aria-current', active ? 'true' : 'false');
				});

				var url = new URL(window.location.href);
				url.searchParams.set('per_page', perPage);
				if (page > 1) {
					url.searchParams.set('paged', String(page));
				} else {
					url.searchParams.delete('paged');
				}
				history.pushState({ page: page, perPage: perPage }, '', url.toString());

				reIndexWall();
			})
			.catch(function () {
				pvLoading = false;
				if (wrap) wrap.innerHTML = '<p class="pv-no-videos">Failed to load videos. Please try again.</p>';
			});
	}

	window.addEventListener('popstate', function () {
		window.location.reload();
	});

	/* ── Click interceptors (per-page + pagination) ──────────────────── */
	document.addEventListener('click', function (e) {
		// Skip broadcast's own per-page buttons — handled inside setupBroadcast
		var perPageBtn = e.target.closest('.pv-per-page__btn');
		if (perPageBtn && !perPageBtn.closest('.pv-broadcast') && !perPageBtn.classList.contains('pv-per-page__btn--active')) {
			e.preventDefault();
			pvLoadPage(1, perPageBtn.dataset.perPage || '20');
			return;
		}

		var pageLink = e.target.closest(
			'#pv-top-pagination .page-numbers:not(.current):not(.dots),' +
			'#pv-bottom-pagination .page-numbers:not(.current):not(.dots)'
		);
		if (pageLink && pageLink.href) {
			e.preventDefault();
			var match = pageLink.href.match(/[?&]paged=(\d+)/);
			pvLoadPage(match ? parseInt(match[1], 10) : 1, pvCurrentPerPage);
		}
	});

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
		if (!pvAjaxUrl || !searchResults) return;

		if (!searchActive) {
			searchActive = true;
			main.classList.add('pv-archive-main--searching');
		}

		searchResults.hidden = false;
		searchResults.innerHTML = '<div class="pv-search-loading"><span class="pv-scroll-spinner"></span></div>';
		if (searchMsg) { searchMsg.textContent = 'Searching\u2026'; searchMsg.hidden = false; }

		fetch(pvAjaxUrl + '?action=pv_search_videos&q=' + encodeURIComponent(q))
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

		// ── Tab state ─────────────────────────────────────────────────
		var bcTabs      = bc.querySelectorAll('.pv-bc-tab');
		var bcPanels    = bc.querySelectorAll('.pv-bc-panel');
		var bcIndicator = bc.querySelector('.pv-bc-tab-indicator');
		var bcTabBar    = bc.querySelector('.pv-bc-tabs');

		var bcAjaxUrl = (window.pvBroadcast && window.pvBroadcast.ajaxUrl) || (window.pvOffcanvas && window.pvOffcanvas.ajaxUrl) || '';
		var bcNonce   = (window.pvBroadcast && window.pvBroadcast.nonce)   || '';

		// ── Home tab state ────────────────────────────────────────────
		var bcHomeGrid    = bc.querySelector('.pv-bc-home-grid');
		var bcHomeTopPag  = bc.querySelector('#pv-bc-home-top-pag');
		var bcHomeBotPag  = bc.querySelector('#pv-bc-home-bot-pag');
		var bcHomeToolbar = bc.querySelector('#pv-bc-home-toolbar');
		var bcLatestSec   = bc.querySelector('#pv-bc-latest-section');
		var bcPlRows      = bc.querySelector('#pv-bc-pl-rows');
		var bcHomePerPage = '20';
		var bcHomePage    = 1;
		var bcSelectedPls = [];

		function bcPlRowId(plId) {
			return 'pv-bc-pl-row-' + plId.replace(/[^a-zA-Z0-9]/g, '-');
		}
		function bcEnterPlMode() {
			if (bcLatestSec)   bcLatestSec.hidden   = true;
			if (bcHomeToolbar) bcHomeToolbar.hidden = true;
			if (bcPlRows)      bcPlRows.hidden      = false;
		}
		function bcExitPlMode() {
			if (bcLatestSec)   bcLatestSec.hidden   = false;
			if (bcHomeToolbar) bcHomeToolbar.hidden = false;
			if (bcPlRows) { bcPlRows.hidden = true; bcPlRows.innerHTML = ''; }
		}
		function addBcPlRow(plId, plTitle) {
			if (!bcPlRows || !bcAjaxUrl) return;
			var rowId = bcPlRowId(plId);
			if (bc.querySelector('#' + rowId)) return;
			var row = document.createElement('div');
			row.className = 'pv-bc-section pv-bc-pl-row';
			row.id = rowId;
			row.innerHTML = '<div class="pv-bc-section__head"><h2 class="pv-bc-section__title">' + escHtml(plTitle) + '</h2></div>'
				+ '<div class="pv-bc-pl-row__cards pv-bc-row"><div class="pv-bc-lazy-spinner"><span class="pv-scroll-spinner"></span></div></div>'
				+ '<div class="pv-bc-pl-row__foot"></div>';
			bcPlRows.appendChild(row);
			var cardsEl = row.querySelector('.pv-bc-pl-row__cards');
			var footEl  = row.querySelector('.pv-bc-pl-row__foot');
			var fd = new FormData();
			fd.append('action', 'pv_bc_videos');
			fd.append('nonce',    bcNonce);
			fd.append('page',     '1');
			fd.append('per_page', '4');
			fd.append('pv_yt_pl', plId);
			fetch(bcAjaxUrl, { method: 'POST', body: fd })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (!data.success) { cardsEl.innerHTML = '<p class="pv-no-videos">Could not load playlist.</p>'; return; }
					var d = data.data;
					cardsEl.innerHTML = d.html || '<p class="pv-no-videos">No videos found.</p>';
					if (d.total > 4) {
						footEl.innerHTML = '<button class="pv-bc-section__view-all pv-bc-home-expand-btn" data-yt-pl="' + plId + '">Show All (' + d.total + ') \u2192</button>';
					}
				})
				.catch(function () { cardsEl.innerHTML = '<p class="pv-no-videos">Could not load playlist.</p>'; });
		}
		function removeBcPlRow(plId) {
			var row = bc.querySelector('#' + bcPlRowId(plId));
			if (row) row.remove();
		}

		// ── Videos tab state ─────────────────────────────────────────
		var bcVideosGrid    = bc.querySelector('.pv-bc-video-grid');
		var bcVideosTopPag  = bc.querySelector('#pv-bc-videos-top-pag');
		var bcVideosBotPag  = bc.querySelector('#pv-bc-videos-bot-pag');
		var bcVideosPerPage = '20';
		var bcVideosPage    = 1;
		var bcVideosCategory = '';
		var bcVideosYtPl     = '';

		function updateBcPerPageBtns(panel, perPage) {
			if (!panel) return;
			panel.querySelectorAll('[data-bc-per-page]').forEach(function (btn) {
				btn.classList.toggle('pv-per-page__btn--active', btn.dataset.bcPerPage === String(perPage));
			});
		}

		function loadBcHomeGrid(page, perPage) {
			if (!bcHomeGrid || !bcAjaxUrl) return;
			bcHomePage    = page;
			bcHomePerPage = perPage;

			bcHomeGrid.innerHTML = '<div class="pv-bc-lazy-spinner"><span class="pv-scroll-spinner"></span></div>';

			var fd = new FormData();
			fd.append('action', 'pv_bc_videos');
			fd.append('nonce',    bcNonce);
			fd.append('page',     String(page));
			fd.append('per_page', String(perPage));

			fetch(bcAjaxUrl, { method: 'POST', body: fd })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (!data.success) {
						bcHomeGrid.innerHTML = '<p class="pv-no-videos">Could not load videos.</p>';
						return;
					}
					var d = data.data;
					bcHomeGrid.innerHTML = d.html || '<p class="pv-no-videos">No videos found.</p>';
					if (bcHomeTopPag) bcHomeTopPag.innerHTML = d.pagination || '';
					if (bcHomeBotPag) bcHomeBotPag.innerHTML = d.pagination || '';
					var homePanel = bc.querySelector('.pv-bc-panel[data-bc-panel="home"]');
					updateBcPerPageBtns(homePanel, perPage);
				})
				.catch(function () {
					bcHomeGrid.innerHTML = '<p class="pv-no-videos">Could not load videos.</p>';
				});
		}

		function loadBcVideosGrid(page, perPage, category, ytPlId) {
			if (!bcVideosGrid || !bcAjaxUrl) return;
			bcVideosPage     = page;
			bcVideosPerPage  = perPage;
			bcVideosCategory = category || '';
			bcVideosYtPl     = ytPlId   || '';

			bcVideosGrid.innerHTML = '<div class="pv-bc-lazy-spinner"><span class="pv-scroll-spinner"></span></div>';

			var fd = new FormData();
			fd.append('action',   'pv_bc_videos');
			fd.append('nonce',    bcNonce);
			fd.append('page',     String(page));
			fd.append('per_page', String(perPage));
			if (bcVideosCategory) fd.append('category', bcVideosCategory);
			if (bcVideosYtPl)     fd.append('pv_yt_pl', bcVideosYtPl);

			fetch(bcAjaxUrl, { method: 'POST', body: fd })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (!data.success) {
						bcVideosGrid.innerHTML = '<p class="pv-no-videos">Could not load videos.</p>';
						return;
					}
					var d = data.data;
					bcVideosGrid.innerHTML = d.html || '<p class="pv-no-videos">No videos found.</p>';
					if (bcVideosTopPag) bcVideosTopPag.innerHTML = d.pagination || '';
					if (bcVideosBotPag) bcVideosBotPag.innerHTML = d.pagination || '';
					var videosPanel = bc.querySelector('.pv-bc-panel[data-bc-panel="videos"]');
					updateBcPerPageBtns(videosPanel, perPage);
				})
				.catch(function () {
					bcVideosGrid.innerHTML = '<p class="pv-no-videos">Could not load videos.</p>';
				});
		}

		function loadBcLazy(container, type) {
			if (type === 'bc_home') {
				loadBcHomeGrid(1, bcHomePerPage);
				return;
			}
			if (type === 'videos') {
				loadBcVideosGrid(1, bcVideosPerPage, '', '');
				return;
			}
			// Playlists tab
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

		// ── Delegated click handler ───────────────────────────────────
		bc.addEventListener('click', function (e) {

			// Per-page buttons (home + videos panels)
			var ppBtn = e.target.closest('[data-bc-per-page]');
			if (ppBtn) {
				e.preventDefault();
				var pp    = ppBtn.dataset.bcPerPage;
				var panel = ppBtn.closest('.pv-bc-panel');
				var pk    = panel && panel.dataset.bcPanel;
				if (pk === 'home')   loadBcHomeGrid(1,   pp);
				if (pk === 'videos') loadBcVideosGrid(1, pp, bcVideosCategory, bcVideosYtPl);
				return;
			}

			// Pagination links (home + videos panels)
			var pageLink = e.target.closest('.pv-pagination .page-numbers:not(.current):not(.dots)');
			if (pageLink && pageLink.href) {
				var panel = pageLink.closest('.pv-bc-panel');
				var pk    = panel && panel.dataset.bcPanel;
				if (pk === 'home' || pk === 'videos') {
					e.preventDefault();
					var match = pageLink.href.match(/[?&]paged=(\d+)/);
					var pg    = match ? parseInt(match[1], 10) : 1;
					if (pk === 'home')   loadBcHomeGrid(pg,   bcHomePerPage);
					if (pk === 'videos') loadBcVideosGrid(pg, bcVideosPerPage, bcVideosCategory, bcVideosYtPl);
					return;
				}
			}

			// Playlist chip toggle (home panel — multi-select)
			var plChip = e.target.closest('.pv-bc-pl-chip');
			if (plChip) {
				var plId    = plChip.dataset.plId;
				var plTitle = plChip.dataset.plTitle || plChip.textContent.trim();
				var isActive = plChip.classList.contains('pv-bc-pl-chip--active');
				if (isActive) {
					plChip.classList.remove('pv-bc-pl-chip--active');
					var idx = bcSelectedPls.indexOf(plId);
					if (idx > -1) bcSelectedPls.splice(idx, 1);
					removeBcPlRow(plId);
					if (bcSelectedPls.length === 0) bcExitPlMode();
				} else {
					plChip.classList.add('pv-bc-pl-chip--active');
					bcSelectedPls.push(plId);
					if (bcSelectedPls.length === 1) bcEnterPlMode();
					addBcPlRow(plId, plTitle);
				}
				return;
			}

			// "View All" button on playlist preview → Videos tab
			var expandBtn = e.target.closest('.pv-bc-home-expand-btn');
			if (expandBtn) {
				var ytPl = expandBtn.dataset.ytPl;
				if (bcVideosGrid) bcVideosGrid.dataset.bcLoaded = 'true';
				var vTab = bc.querySelector('.pv-bc-tab[data-bc-tab="videos"]');
				if (vTab) activateBcTab(vTab);
				loadBcVideosGrid(1, bcVideosPerPage, '', ytPl);
				return;
			}

			// Playlist card "View All" → Videos tab
			var ytPlLink = e.target.closest('[data-pv-yt-pl]');
			if (ytPlLink) {
				e.preventDefault();
				var ytPl2 = ytPlLink.dataset.pvYtPl;
				if (bcVideosGrid) bcVideosGrid.dataset.bcLoaded = 'true';
				var vTab2 = bc.querySelector('.pv-bc-tab[data-bc-tab="videos"]');
				if (vTab2) activateBcTab(vTab2);
				loadBcVideosGrid(1, bcVideosPerPage, '', ytPl2);
				return;
			}

			// Tab-switch button (any residual use)
			var switchBtn = e.target.closest('.pv-bc-tab-switch');
			if (switchBtn) {
				var targetTab = bc.querySelector('.pv-bc-tab[data-bc-tab="' + switchBtn.dataset.targetTab + '"]');
				if (targetTab) activateBcTab(targetTab);
			}
		});

		var activeTab = bc.querySelector('.pv-bc-tab--active');
		if (activeTab) { requestAnimationFrame(function () { activateBcTab(activeTab); }); }

		// ── Sort bar (Videos tab, client-side) ───────────────────────
		var bcSortBar = bc.querySelector('.pv-bc-sort-bar');
		if (bcSortBar) {
			bcSortBar.addEventListener('click', function (e) {
				var btn = e.target.closest('.pv-bc-sort-btn');
				if (!btn) return;
				bcSortBar.querySelectorAll('.pv-bc-sort-btn').forEach(function (b) { b.classList.remove('pv-bc-sort-btn--active'); });
				btn.classList.add('pv-bc-sort-btn--active');
				var sort  = btn.dataset.sort;
				var grid  = bc.querySelector('.pv-bc-video-grid');
				if (!grid) return;
				var cards = Array.from(grid.querySelectorAll('.pv-bc-card'));
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
				loadBcVideosGrid(1, bcVideosPerPage, filter === '*' ? '' : filter, '');
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
			if (!pvAjaxUrl || !bcResults) return;
			if (!bcActive) { bcActive = true; bc.classList.add('pv-bc-searching'); }
			bcResults.hidden = false;
			bcResults.innerHTML = '<div class="pv-search-loading"><span class="pv-scroll-spinner"></span></div>';
			if (bcMsg) { bcMsg.textContent = 'Searching\u2026'; bcMsg.hidden = false; }
			fetch(pvAjaxUrl + '?action=pv_search_videos&q=' + encodeURIComponent(q))
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
