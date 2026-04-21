/**
 * KDNA Events success page polling.
 *
 * Handles the race between the buyer's return from Stripe and the
 * webhook that actually flips the order to 'paid'. When the
 * Success Confirmation widget renders in a loading state (the URL
 * carries session_id and the order is still pending), this script
 * polls /kdna-events/v1/confirm-order?ref=X up to five times at
 * 500ms intervals. As soon as the endpoint returns finalised=true
 * the page reloads so the Success widgets pick up the tickets the
 * webhook just inserted.
 */
(function () {
	'use strict';

	var MAX_ATTEMPTS = 5;
	var INTERVAL_MS = 500;

	function buildConfirmUrl(ref) {
		// Build a sane REST URL without requiring the route to be localised.
		var base = window.location.origin + '/wp-json/kdna-events/v1/confirm-order';
		return base + '?ref=' + encodeURIComponent(ref);
	}

	function poll(root, attempt) {
		if (attempt >= MAX_ATTEMPTS) {
			// Give up and refresh anyway. If the webhook has still not
			// fired, the server-rendered success page will show the
			// still-pending state and the buyer can refresh manually.
			window.location.reload();
			return;
		}

		var ref = root.getAttribute('data-order-ref') || '';
		if (!ref) {
			return;
		}

		fetch(buildConfirmUrl(ref), { credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data && data.finalised) {
					window.location.reload();
					return;
				}
				window.setTimeout(function () { poll(root, attempt + 1); }, INTERVAL_MS);
			})
			.catch(function () {
				window.setTimeout(function () { poll(root, attempt + 1); }, INTERVAL_MS);
			});
	}

	function init() {
		var nodes = document.querySelectorAll('[data-kdna-events-success-confirmation][data-should-poll="1"]');
		for (var i = 0; i < nodes.length; i++) {
			poll(nodes[i], 0);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
