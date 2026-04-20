/**
 * PressVideo — Archive category filter.
 * Vanilla JS. Filters .pv-card elements by data-category attribute.
 */
(function () {
	'use strict';

	var bar = document.querySelector('.pv-filter-bar');
	if (!bar) return;

	bar.addEventListener('click', function (e) {
		var btn = e.target.closest('.pv-filter-btn');
		if (!btn) return;

		var filter = btn.dataset.filter;

		// Update active state.
		bar.querySelectorAll('.pv-filter-btn').forEach(function (b) {
			b.classList.toggle('pv-filter-btn--active', b === btn);
		});

		// Show / hide cards.
		document.querySelectorAll('.pv-grid .pv-card').forEach(function (card) {
			card.hidden = !(filter === '*' || card.dataset.category === filter);
		});
	});
}());
