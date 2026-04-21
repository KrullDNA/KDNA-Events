/**
 * KDNA Events front-end bundle.
 *
 * Wires up the Event Filter widget (pill toggles, debounced search,
 * AJAX submit + pushState, and reload fallback), numbered pagination
 * clicks, and Load More buttons on Event Grids. Shares the nonce and
 * AJAX URL provided by wp_localize_script as window.kdnaEvents.
 */
(function () {
	'use strict';

	var config = window.kdnaEvents || {};

	/**
	 * Find the grid element a filter targets, scoping to same-page elements.
	 *
	 * @param {HTMLElement} filter
	 * @returns {HTMLElement|null}
	 */
	function findTargetGrid(filter) {
		var selector = filter.getAttribute('data-target') || '.kdna-events-grid__wrapper';
		var targetEl = document.querySelector(selector);
		if (!targetEl) {
			return null;
		}
		return targetEl.closest('[data-kdna-events-grid]');
	}

	/**
	 * Read filter state into a plain object.
	 *
	 * @param {HTMLElement} filter
	 * @returns {Object}
	 */
	function collectFilterState(filter) {
		var state = { type: '', price: '', category: 0, date_from: '', date_to: '', search: '', page: 1 };

		var groups = filter.querySelectorAll('.kdna-events-filter__group');
		for (var i = 0; i < groups.length; i++) {
			var group = groups[i];
			var key = group.getAttribute('data-filter-key');
			if (key === 'type' || key === 'price') {
				var active = group.querySelector('.kdna-events-filter__pill.is-active');
				state[key] = active ? (active.getAttribute('data-value') || '') : '';
			} else if (key === 'category') {
				var select = group.querySelector('select');
				state.category = select ? parseInt(select.value, 10) || 0 : 0;
			} else if (key === 'date_range') {
				var fromEl = group.querySelector('input[name="date_from"]');
				var toEl = group.querySelector('input[name="date_to"]');
				state.date_from = fromEl ? fromEl.value : '';
				state.date_to = toEl ? toEl.value : '';
			} else if (key === 'search') {
				var searchEl = group.querySelector('input[name="search"]');
				state.search = searchEl ? searchEl.value : '';
			}
		}

		return state;
	}

	/**
	 * Push current filter state onto history so sharing a URL preserves filters.
	 *
	 * @param {Object} state
	 */
	function updateHistory(state) {
		if (typeof window.history === 'undefined' || typeof window.history.pushState !== 'function') {
			return;
		}
		var url = new URL(window.location.href);
		var params = url.searchParams;
		['type', 'price', 'category', 'date_from', 'date_to', 'search', 'page'].forEach(function (key) {
			var val = state[key];
			if (val === '' || val === 0 || val === null || typeof val === 'undefined' || (key === 'page' && val === 1)) {
				params.delete(key);
			} else {
				params.set(key, val);
			}
		});
		url.search = params.toString();
		window.history.pushState({ kdnaEventsFilter: state }, '', url.toString());
	}

	/**
	 * Post the current filter + grid settings to the AJAX endpoint and
	 * replace the target grid's contents.
	 *
	 * @param {HTMLElement} filter
	 * @param {Object}      overrides  Optional overrides (e.g. { page: 2 } for Load More).
	 * @param {boolean}     append     Whether to append cards rather than replace.
	 */
	function runFilter(filter, overrides, append) {
		var gridEl = findTargetGrid(filter);
		if (!gridEl) {
			return;
		}

		var wrapper = gridEl.querySelector('.kdna-events-grid__wrapper');
		if (!wrapper) {
			return;
		}

		var gridSettings = {};
		try {
			gridSettings = JSON.parse(gridEl.getAttribute('data-grid-settings') || '{}');
		} catch (e) {
			gridSettings = {};
		}

		var state = collectFilterState(filter);
		if (overrides && typeof overrides === 'object') {
			Object.keys(overrides).forEach(function (key) {
				state[key] = overrides[key];
			});
		}

		gridEl.classList.add('is-loading');

		var body = new URLSearchParams();
		body.append('action', 'kdna_events_filter_grid');
		body.append('nonce', config.nonce || '');
		body.append('grid_settings', JSON.stringify(gridSettings));
		body.append('filters', JSON.stringify(state));

		fetch(config.ajaxUrl || '', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (response) {
			return response.json();
		}).then(function (json) {
			gridEl.classList.remove('is-loading');
			if (!json || !json.success || !json.data) {
				return;
			}
			var html = json.data.cards || json.data.empty_html || '';
			if (append) {
				wrapper.insertAdjacentHTML('beforeend', html);
			} else {
				wrapper.innerHTML = html;
			}
			var paginationContainer = gridEl.querySelector('.kdna-events-grid__pagination');
			if (paginationContainer) {
				paginationContainer.parentNode.removeChild(paginationContainer);
			}
			if (json.data.pagination) {
				gridEl.insertAdjacentHTML('beforeend', json.data.pagination);
			}
			updateHistory(state);
		}).catch(function () {
			gridEl.classList.remove('is-loading');
		});
	}

	/**
	 * Attach event handlers for a single filter widget.
	 *
	 * @param {HTMLElement} filter
	 */
	function wireFilter(filter) {
		if (filter.getAttribute('data-kdna-events-filter-wired') === '1') {
			return;
		}
		filter.setAttribute('data-kdna-events-filter-wired', '1');

		var mode = filter.getAttribute('data-mode') || 'ajax';
		var searchTimer = null;

		filter.addEventListener('click', function (event) {
			var pill = event.target.closest('.kdna-events-filter__pill');
			if (pill) {
				event.preventDefault();
				var group = pill.closest('.kdna-events-filter__group');
				if (group) {
					var pills = group.querySelectorAll('.kdna-events-filter__pill');
					for (var i = 0; i < pills.length; i++) {
						pills[i].classList.remove('is-active');
					}
					pill.classList.add('is-active');
					var hidden = group.querySelector('input[type="hidden"]');
					if (hidden) {
						hidden.value = pill.getAttribute('data-value') || '';
					}
					if (mode === 'ajax') {
						runFilter(filter, { page: 1 }, false);
					}
				}
			}
		});

		filter.addEventListener('change', function (event) {
			if (event.target && (event.target.matches('select') || event.target.matches('input[type="date"]'))) {
				if (mode === 'ajax') {
					runFilter(filter, { page: 1 }, false);
				}
			}
		});

		filter.addEventListener('input', function (event) {
			if (event.target && event.target.matches('input[type="search"]')) {
				if (mode !== 'ajax') {
					return;
				}
				window.clearTimeout(searchTimer);
				searchTimer = window.setTimeout(function () {
					runFilter(filter, { page: 1 }, false);
				}, 350);
			}
		});

		filter.addEventListener('submit', function (event) {
			if (mode === 'ajax') {
				event.preventDefault();
				runFilter(filter, { page: 1 }, false);
			}
		});

		filter.addEventListener('reset', function () {
			window.setTimeout(function () {
				var groups = filter.querySelectorAll('.kdna-events-filter__group--pills');
				for (var i = 0; i < groups.length; i++) {
					var pills = groups[i].querySelectorAll('.kdna-events-filter__pill');
					for (var j = 0; j < pills.length; j++) {
						pills[j].classList.toggle('is-active', (pills[j].getAttribute('data-value') || '') === '');
					}
					var hidden = groups[i].querySelector('input[type="hidden"]');
					if (hidden) {
						hidden.value = '';
					}
				}
				if (mode === 'ajax') {
					runFilter(filter, { page: 1 }, false);
				}
			}, 0);
		});
	}

	/**
	 * Delegate click handlers for numbered pagination and Load More.
	 *
	 * @param {HTMLElement} grid
	 */
	function wireGrid(grid) {
		if (grid.getAttribute('data-kdna-events-grid-wired') === '1') {
			return;
		}
		grid.setAttribute('data-kdna-events-grid-wired', '1');

		grid.addEventListener('click', function (event) {
			var pageBtn = event.target.closest('[data-kdna-events-page]');
			if (pageBtn) {
				event.preventDefault();
				var page = parseInt(pageBtn.getAttribute('data-kdna-events-page'), 10) || 1;
				var filter = findFilterForGrid(grid);
				if (filter) {
					runFilter(filter, { page: page }, false);
				} else {
					runFilterStandalone(grid, { page: page }, false);
				}
				return;
			}

			var loadMore = event.target.closest('[data-kdna-events-load-more]');
			if (loadMore) {
				event.preventDefault();
				var currentPage = parseInt(loadMore.getAttribute('data-current-page'), 10) || 1;
				var maxPages = parseInt(loadMore.getAttribute('data-max-pages'), 10) || 1;
				if (currentPage >= maxPages) {
					return;
				}
				var nextPage = currentPage + 1;
				var filter2 = findFilterForGrid(grid);
				if (filter2) {
					runFilter(filter2, { page: nextPage }, true);
				} else {
					runFilterStandalone(grid, { page: nextPage }, true);
				}
			}
		});
	}

	/**
	 * Find a filter on the page whose target points at this grid.
	 *
	 * @param {HTMLElement} grid
	 * @returns {HTMLElement|null}
	 */
	function findFilterForGrid(grid) {
		var filters = document.querySelectorAll('[data-kdna-events-filter]');
		for (var i = 0; i < filters.length; i++) {
			var target = findTargetGrid(filters[i]);
			if (target === grid) {
				return filters[i];
			}
		}
		return null;
	}

	/**
	 * Run an AJAX refresh for a grid that has no filter wired to it.
	 *
	 * Used by pagination and Load More when the grid stands alone.
	 *
	 * @param {HTMLElement} grid
	 * @param {Object}      overrides
	 * @param {boolean}     append
	 */
	function runFilterStandalone(grid, overrides, append) {
		var wrapper = grid.querySelector('.kdna-events-grid__wrapper');
		if (!wrapper) {
			return;
		}

		var gridSettings = {};
		try {
			gridSettings = JSON.parse(grid.getAttribute('data-grid-settings') || '{}');
		} catch (e) {
			gridSettings = {};
		}

		var state = { page: 1 };
		if (overrides) {
			Object.keys(overrides).forEach(function (key) {
				state[key] = overrides[key];
			});
		}

		grid.classList.add('is-loading');

		var body = new URLSearchParams();
		body.append('action', 'kdna_events_filter_grid');
		body.append('nonce', config.nonce || '');
		body.append('grid_settings', JSON.stringify(gridSettings));
		body.append('filters', JSON.stringify(state));

		fetch(config.ajaxUrl || '', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (r) {
			return r.json();
		}).then(function (json) {
			grid.classList.remove('is-loading');
			if (!json || !json.success || !json.data) {
				return;
			}
			var html = json.data.cards || json.data.empty_html || '';
			if (append) {
				wrapper.insertAdjacentHTML('beforeend', html);
			} else {
				wrapper.innerHTML = html;
			}
			var paginationContainer = grid.querySelector('.kdna-events-grid__pagination');
			if (paginationContainer) {
				paginationContainer.parentNode.removeChild(paginationContainer);
			}
			if (json.data.pagination) {
				grid.insertAdjacentHTML('beforeend', json.data.pagination);
			}
		}).catch(function () {
			grid.classList.remove('is-loading');
		});
	}

	/**
	 * Wire the Upcoming / Past tabs on a My Tickets widget.
	 *
	 * @param {HTMLElement} root
	 */
	function wireMyTickets(root) {
		if (root.getAttribute('data-kdna-events-my-tickets-wired') === '1') {
			return;
		}
		root.setAttribute('data-kdna-events-my-tickets-wired', '1');

		var tabs = root.querySelectorAll('.kdna-events-my-tickets__tab');
		var panels = root.querySelectorAll('.kdna-events-my-tickets__panel');
		if (!tabs.length || !panels.length) {
			return;
		}

		function activate(target) {
			for (var i = 0; i < tabs.length; i++) {
				var active = tabs[i].getAttribute('data-target') === target;
				tabs[i].classList.toggle('is-active', active);
				tabs[i].setAttribute('aria-selected', active ? 'true' : 'false');
			}
			for (var j = 0; j < panels.length; j++) {
				panels[j].classList.toggle('is-active', panels[j].getAttribute('data-panel') === target);
			}
		}

		for (var k = 0; k < tabs.length; k++) {
			tabs[k].addEventListener('click', function (event) {
				event.preventDefault();
				var target = event.currentTarget.getAttribute('data-target') || 'upcoming';
				activate(target);
			});
		}
	}

	/**
	 * Scan the document for filter and grid widgets and wire them up.
	 */
	function initAll() {
		var filters = document.querySelectorAll('[data-kdna-events-filter]');
		for (var i = 0; i < filters.length; i++) {
			wireFilter(filters[i]);
		}
		var grids = document.querySelectorAll('[data-kdna-events-grid]');
		for (var j = 0; j < grids.length; j++) {
			wireGrid(grids[j]);
		}
		var tickets = document.querySelectorAll('[data-kdna-events-my-tickets]');
		for (var k = 0; k < tickets.length; k++) {
			wireMyTickets(tickets[k]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}

	if (typeof window.jQuery !== 'undefined') {
		window.jQuery(window).on('elementor/frontend/init', function () {
			if (!window.elementorFrontend || !window.elementorFrontend.hooks) {
				return;
			}
			window.elementorFrontend.hooks.addAction(
				'frontend/element_ready/kdna-events-event-filter.default',
				function ($scope) {
					if (!$scope || !$scope[0]) { return; }
					var filter = $scope[0].querySelector('[data-kdna-events-filter]');
					if (filter) { wireFilter(filter); }
				}
			);
			window.elementorFrontend.hooks.addAction(
				'frontend/element_ready/kdna-events-event-grid.default',
				function ($scope) {
					if (!$scope || !$scope[0]) { return; }
					var grid = $scope[0].querySelector('[data-kdna-events-grid]');
					if (grid) { wireGrid(grid); }
				}
			);
			window.elementorFrontend.hooks.addAction(
				'frontend/element_ready/kdna-events-my-tickets.default',
				function ($scope) {
					if (!$scope || !$scope[0]) { return; }
					var root = $scope[0].querySelector('[data-kdna-events-my-tickets]');
					if (root) { wireMyTickets(root); }
				}
			);
		});
	}

	window.KDNAEvents = window.KDNAEvents || {};
	window.KDNAEvents.initFilters = initAll;
})();
