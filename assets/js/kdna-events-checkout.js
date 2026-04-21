/**
 * KDNA Events checkout orchestrator.
 *
 * Ties the five checkout widgets together:
 *   - Quantity widget: wires +/- buttons and the input, clamps to min/max,
 *     dispatches 'kdna-events-quantity-changed' on every change.
 *   - Attendees widget: listens for quantity change, renders one fieldset
 *     per ticket, runs inline blur validation, offers 'copy from ticket 1'.
 *   - Order Summary widget: listens for quantity change, updates lines
 *     and the total, swaps to the Free label when the event is free.
 *   - Pay Button widget: updates its label live, handles loading state,
 *     submits the AJAX create-order request.
 *
 * The server is authoritative. Client-side validation is a fast feedback
 * layer only. The PHP handler re-validates registration window, min/max
 * and capacity before every order insert.
 */
(function () {
	'use strict';

	var config = window.kdnaEvents || {};

	/**
	 * Format a monetary amount for display.
	 *
	 * Matches the PHP kdna_events_format_price helper's lightweight
	 * currency symbol map. Falls back to the code prefix for unknown
	 * currencies.
	 *
	 * @param {number} amount
	 * @param {string} currency
	 * @returns {string}
	 */
	function formatPrice(amount, currency) {
		var symbols = { AUD: '$', USD: '$', NZD: '$', CAD: '$', GBP: '£', EUR: '€' };
		var symbol = symbols[currency] || (currency + ' ');
		var value = Number(amount || 0);
		return symbol + value.toFixed(2);
	}

	/**
	 * Dispatch the quantity change event on the document.
	 *
	 * @param {Object} detail
	 */
	function dispatchQuantityChange(detail) {
		var event;
		try {
			event = new CustomEvent('kdna-events-quantity-changed', { detail: detail, bubbles: true });
		} catch (e) {
			event = document.createEvent('CustomEvent');
			event.initCustomEvent('kdna-events-quantity-changed', true, false, detail);
		}
		document.dispatchEvent(event);
	}

	/**
	 * Wire a single Quantity widget.
	 *
	 * @param {HTMLElement} root
	 */
	function wireQuantity(root) {
		if (root.getAttribute('data-kdna-events-checkout-quantity-wired') === '1') {
			return;
		}
		root.setAttribute('data-kdna-events-checkout-quantity-wired', '1');

		var input = root.querySelector('.kdna-events-checkout-quantity__input');
		var minusBtn = root.querySelector('.kdna-events-checkout-quantity__button--minus');
		var plusBtn = root.querySelector('.kdna-events-checkout-quantity__button--plus');
		if (!input) {
			return;
		}

		var min = parseInt(root.getAttribute('data-min'), 10) || 1;
		var max = parseInt(root.getAttribute('data-max'), 10) || 1;
		var price = parseFloat(root.getAttribute('data-price')) || 0;
		var currency = root.getAttribute('data-currency') || 'AUD';
		var isFree = root.getAttribute('data-is-free') === '1';
		var eventId = parseInt(root.getAttribute('data-event-id'), 10) || 0;

		function clamp(value) {
			if (isNaN(value)) { return min; }
			if (value < min) { return min; }
			if (value > max) { return max; }
			return value;
		}

		function broadcast() {
			var qty = clamp(parseInt(input.value, 10));
			if (String(qty) !== input.value) {
				input.value = String(qty);
			}
			if (minusBtn) { minusBtn.disabled = qty <= min; }
			if (plusBtn)  { plusBtn.disabled  = qty >= max; }
			dispatchQuantityChange({
				eventId: eventId,
				quantity: qty,
				min: min,
				max: max,
				price: price,
				currency: currency,
				isFree: isFree
			});
		}

		if (minusBtn) {
			minusBtn.addEventListener('click', function () {
				input.value = String(clamp(parseInt(input.value, 10) - 1));
				broadcast();
			});
		}
		if (plusBtn) {
			plusBtn.addEventListener('click', function () {
				input.value = String(clamp(parseInt(input.value, 10) + 1));
				broadcast();
			});
		}
		input.addEventListener('input', broadcast);
		input.addEventListener('change', broadcast);

		// Fire initial event so listeners populate.
		broadcast();
	}

	/**
	 * Build one attendee fieldset's HTML.
	 *
	 * @param {Object} cfg     Parsed widget config from data-config.
	 * @param {number} index   Zero-based ticket index.
	 * @param {number} total   Total ticket count for this order.
	 * @returns {string}
	 */
	function attendeeFieldsetHtml(cfg, index, total) {
		var n = index + 1;
		var heading = (cfg.headingTpl || 'Attendee {n}').replace(/\{n\}/g, String(n));
		var mark = cfg.required || '*';
		var phoneReq = !!cfg.phoneRequired;
		var showCopy = !!cfg.allowCopy && index > 0 && total > 1;

		var parts = [];
		parts.push('<fieldset class="kdna-events-checkout-attendees__fieldset" data-index="' + index + '">');
		parts.push('<legend class="kdna-events-checkout-attendees__heading">' + escapeHtml(heading) + '</legend>');

		if (showCopy) {
			parts.push('<label class="kdna-events-checkout-attendees__copy">');
			parts.push('<input type="checkbox" data-kdna-events-copy-first />');
			parts.push('<span>' + escapeHtml(cfg.copyLabel || 'Copy details from ticket 1') + '</span>');
			parts.push('</label>');
		}

		parts.push(renderField({
			index: index,
			key: 'name',
			label: cfg.i18n.name,
			type: 'text',
			required: true,
			placeholder: cfg.i18n.namePlace,
			errorText: cfg.i18n.nameMissing,
			mark: mark
		}));
		parts.push(renderField({
			index: index,
			key: 'email',
			label: cfg.i18n.email,
			type: 'email',
			required: true,
			placeholder: cfg.i18n.emailPlace,
			errorText: cfg.i18n.emailMissing,
			mark: mark
		}));
		parts.push(renderField({
			index: index,
			key: 'phone',
			label: cfg.i18n.phone,
			type: 'tel',
			required: phoneReq,
			placeholder: cfg.i18n.phonePlace,
			errorText: cfg.i18n.phoneMissing,
			mark: mark
		}));

		(cfg.customFields || []).forEach(function (field) {
			parts.push(renderField({
				index: index,
				key: 'custom[' + field.key + ']',
				label: field.label,
				type: field.type || 'text',
				required: !!field.required,
				errorText: cfg.i18n.fieldMissing,
				mark: mark,
				dataKey: field.key,
				isCustom: true
			}));
		});

		parts.push('</fieldset>');
		return parts.join('');
	}

	/**
	 * Render one input row.
	 *
	 * @param {Object} opts
	 * @returns {string}
	 */
	function renderField(opts) {
		var input;
		var requiredAttr = opts.required ? ' required aria-required="true"' : '';
		var nameAttr = 'attendees[' + opts.index + '][' + opts.key + ']';
		var placeholder = opts.placeholder ? ' placeholder="' + escapeAttr(opts.placeholder) + '"' : '';
		if (opts.type === 'select') {
			input = '<select class="kdna-events-checkout-attendees__input" name="' + escapeAttr(nameAttr) + '"' + requiredAttr + '></select>';
		} else {
			var type = ['text', 'email', 'tel'].indexOf(opts.type) !== -1 ? opts.type : 'text';
			input = '<input class="kdna-events-checkout-attendees__input" type="' + type + '"' + placeholder + requiredAttr + ' name="' + escapeAttr(nameAttr) + '" />';
		}

		var labelText = escapeHtml(opts.label);
		var markHtml = opts.required ? ' <span class="kdna-events-checkout-attendees__required">' + escapeHtml(opts.mark) + '</span>' : '';

		return [
			'<div class="kdna-events-checkout-attendees__field"',
			opts.isCustom ? ' data-custom-key="' + escapeAttr(opts.dataKey || '') + '"' : '',
			' data-key="', escapeAttr(opts.key), '">',
			'<label class="kdna-events-checkout-attendees__label">', labelText, markHtml, '</label>',
			input,
			'<div class="kdna-events-checkout-attendees__error" data-error>', escapeHtml(opts.errorText || ''), '</div>',
			'</div>'
		].join('');
	}

	/**
	 * Minimal HTML escape for user-controlled strings being injected into a template.
	 */
	function escapeHtml(str) {
		return String(str || '').replace(/[&<>"']/g, function (c) {
			return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
		});
	}

	function escapeAttr(str) {
		return escapeHtml(str).replace(/"/g, '&quot;');
	}

	/**
	 * Wire an Attendees widget.
	 *
	 * @param {HTMLElement} root
	 */
	function wireAttendees(root) {
		if (root.getAttribute('data-kdna-events-checkout-attendees-wired') === '1') {
			return;
		}
		root.setAttribute('data-kdna-events-checkout-attendees-wired', '1');

		var list = root.querySelector('.kdna-events-checkout-attendees__list');
		var cfg;
		try {
			cfg = JSON.parse(root.getAttribute('data-config') || '{}');
		} catch (e) {
			cfg = {};
		}

		var currentQty = 0;

		function render(qty) {
			qty = Math.max(0, qty | 0);
			if (qty === currentQty) { return; }

			// Preserve existing input values so changing quantity does not wipe fields.
			var preserved = collectExistingValues(list);

			var html = '';
			for (var i = 0; i < qty; i++) {
				html += attendeeFieldsetHtml(cfg, i, qty);
			}
			list.innerHTML = html;
			currentQty = qty;

			// Restore values where fieldset still exists.
			restoreExistingValues(list, preserved);
			wireAttendeeEvents(list, cfg);
		}

		document.addEventListener('kdna-events-quantity-changed', function (ev) {
			if (!ev.detail) { return; }
			render(ev.detail.quantity);
		});
	}

	function collectExistingValues(list) {
		var out = {};
		var inputs = list.querySelectorAll('input, select');
		for (var i = 0; i < inputs.length; i++) {
			out[inputs[i].getAttribute('name') || ''] = inputs[i].value;
		}
		return out;
	}

	function restoreExistingValues(list, preserved) {
		var inputs = list.querySelectorAll('input, select');
		for (var i = 0; i < inputs.length; i++) {
			var name = inputs[i].getAttribute('name') || '';
			if (Object.prototype.hasOwnProperty.call(preserved, name)) {
				inputs[i].value = preserved[name];
			}
		}
	}

	function wireAttendeeEvents(list, cfg) {
		var fieldsets = list.querySelectorAll('.kdna-events-checkout-attendees__fieldset');
		for (var i = 0; i < fieldsets.length; i++) {
			wireFieldset(fieldsets[i], i, list, cfg);
		}
	}

	function wireFieldset(fieldset, index, list, cfg) {
		var copy = fieldset.querySelector('[data-kdna-events-copy-first]');
		if (copy) {
			copy.addEventListener('change', function () {
				applyCopyFromFirst(list, fieldset, copy.checked);
			});
			if (copy.checked) {
				applyCopyFromFirst(list, fieldset, true);
			}
		}

		var inputs = fieldset.querySelectorAll('input, select');
		for (var j = 0; j < inputs.length; j++) {
			inputs[j].addEventListener('blur', function (ev) {
				validateField(ev.target);
			});
			inputs[j].addEventListener('input', function (ev) {
				if (ev.target.closest('.kdna-events-checkout-attendees__field').classList.contains('has-error')) {
					validateField(ev.target);
				}
			});
		}
	}

	function applyCopyFromFirst(list, fieldset, isOn) {
		var first = list.querySelector('.kdna-events-checkout-attendees__fieldset[data-index="0"]');
		if (!first) { return; }
		var inputs = fieldset.querySelectorAll('.kdna-events-checkout-attendees__field input, .kdna-events-checkout-attendees__field select');
		for (var i = 0; i < inputs.length; i++) {
			var fieldWrap = inputs[i].closest('.kdna-events-checkout-attendees__field');
			var key = fieldWrap.getAttribute('data-key');
			if (!key) { continue; }
			var source = first.querySelector('.kdna-events-checkout-attendees__field[data-key="' + cssEscape(key) + '"] input, .kdna-events-checkout-attendees__field[data-key="' + cssEscape(key) + '"] select');
			if (source) {
				inputs[i].value = source.value;
			}
			inputs[i].disabled = isOn;
			fieldWrap.classList.toggle('is-disabled', isOn);
		}
	}

	function cssEscape(value) {
		if (window.CSS && typeof window.CSS.escape === 'function') {
			return window.CSS.escape(value);
		}
		return String(value || '').replace(/([^\w-])/g, '\\$1');
	}

	function validateField(input) {
		var fieldWrap = input.closest('.kdna-events-checkout-attendees__field');
		if (!fieldWrap) { return true; }
		var ok = true;
		var value = (input.value || '').trim();
		if (input.hasAttribute('required') && '' === value) {
			ok = false;
		}
		if (ok && 'email' === input.type && '' !== value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
			ok = false;
		}
		fieldWrap.classList.toggle('has-error', !ok);
		return ok;
	}

	function validateAllAttendees(root) {
		if (!root) { return { valid: true, first: null }; }
		var inputs = root.querySelectorAll('.kdna-events-checkout-attendees__field input, .kdna-events-checkout-attendees__field select');
		var allValid = true;
		var firstInvalid = null;
		for (var i = 0; i < inputs.length; i++) {
			var valid = validateField(inputs[i]);
			if (!valid && allValid) {
				firstInvalid = inputs[i];
				allValid = false;
			}
		}
		return { valid: allValid, first: firstInvalid };
	}

	/**
	 * Wire the Order Summary.
	 *
	 * @param {HTMLElement} root
	 */
	function wireOrderSummary(root) {
		if (root.getAttribute('data-kdna-events-checkout-order-summary-wired') === '1') {
			return;
		}
		root.setAttribute('data-kdna-events-checkout-order-summary-wired', '1');

		var subtotalEl = root.querySelector('[data-subtotal]');
		var totalEl = root.querySelector('[data-total]');
		var lineRow = root.querySelector('.kdna-events-checkout-summary-row:first-child .kdna-events-checkout-summary-row__label');
		var lineValue = root.querySelector('.kdna-events-checkout-summary-row:first-child .kdna-events-checkout-summary-row__value');
		var taxEl = root.querySelector('[data-tax]');

		var price = parseFloat(root.getAttribute('data-price')) || 0;
		var currency = root.getAttribute('data-currency') || 'AUD';
		var isFree = root.getAttribute('data-is-free') === '1';
		var title = root.getAttribute('data-event-title') || '';
		var lineTpl = root.getAttribute('data-line-template') || '{qty} x {event_title}';
		var freeLabel = root.getAttribute('data-free-label') || 'Free';
		var showCurrency = root.getAttribute('data-show-currency') === '1';

		document.addEventListener('kdna-events-quantity-changed', function (ev) {
			if (!ev.detail) { return; }
			var qty = ev.detail.quantity || 0;
			var subtotal = isFree ? 0 : price * qty;
			var lineLabel = lineTpl.replace('{qty}', String(qty)).replace('{event_title}', title);

			if (lineRow) { lineRow.textContent = lineLabel; }
			if (lineValue) { lineValue.textContent = isFree ? freeLabel : formatPrice(subtotal, currency); }
			if (subtotalEl) { subtotalEl.textContent = isFree ? freeLabel : formatPrice(subtotal, currency); }
			if (taxEl) { taxEl.textContent = isFree ? freeLabel : formatPrice(0, currency); }
			if (totalEl) {
				if (isFree) {
					totalEl.textContent = freeLabel;
				} else {
					totalEl.textContent = formatPrice(subtotal, currency) + (showCurrency ? ' ' + currency : '');
				}
			}
		});
	}

	/**
	 * Wire the Pay Button.
	 *
	 * @param {HTMLElement} button
	 */
	function wirePayButton(button) {
		if (button.getAttribute('data-kdna-events-checkout-pay-wired') === '1') {
			return;
		}
		button.setAttribute('data-kdna-events-checkout-pay-wired', '1');

		var labelEl = button.querySelector('[data-label]');
		var eventId = parseInt(button.getAttribute('data-event-id'), 10) || 0;
		var price = parseFloat(button.getAttribute('data-price')) || 0;
		var currency = button.getAttribute('data-currency') || 'AUD';
		var isFree = button.getAttribute('data-is-free') === '1';
		var paidTpl = button.getAttribute('data-paid-template') || 'Pay {total}';
		var freeLabel = button.getAttribute('data-free-label') || 'Reserve Free Spot';
		var loadingLabel = button.getAttribute('data-loading-label') || 'Processing...';
		var errorEl = document.querySelector('[data-kdna-events-checkout-error]');

		var currentQty = 0;

		document.addEventListener('kdna-events-quantity-changed', function (ev) {
			if (!ev.detail) { return; }
			currentQty = ev.detail.quantity || 0;
			updateLabel();
		});

		function updateLabel() {
			if (!labelEl) { return; }
			if (isFree) {
				labelEl.textContent = freeLabel;
				return;
			}
			var total = price * currentQty;
			labelEl.textContent = paidTpl
				.replace('{total}', formatPrice(total, currency))
				.replace('{currency}', currency);
		}

		function setLoading(loading) {
			button.classList.toggle('is-loading', loading);
			button.disabled = loading;
			if (labelEl) {
				labelEl.textContent = loading ? loadingLabel : labelEl.textContent;
			}
			if (loading && labelEl) {
				labelEl.textContent = loadingLabel;
			} else {
				updateLabel();
			}
		}

		function showError(message) {
			if (!errorEl) {
				window.alert(message);
				return;
			}
			errorEl.textContent = message;
			errorEl.hidden = false;
		}

		function clearError() {
			if (errorEl) {
				errorEl.hidden = true;
				errorEl.textContent = '';
			}
		}

		button.addEventListener('click', function () {
			clearError();

			var attendeesRoot = document.querySelector('[data-kdna-events-checkout-attendees]');
			var validation = validateAllAttendees(attendeesRoot);
			if (!validation.valid) {
				if (validation.first && typeof validation.first.focus === 'function') {
					validation.first.focus();
				}
				return;
			}

			var payload = gatherAttendees(attendeesRoot);
			if (!currentQty || !eventId) { return; }

			var form = new URLSearchParams();
			form.append('action', 'kdna_events_create_order');
			form.append('nonce', config.nonce || '');
			form.append('event_id', String(eventId));
			form.append('quantity', String(currentQty));
			form.append('phone_required', attendeesRoot && attendeesRoot.getAttribute('data-phone-required') === '1' ? '1' : '0');
			// Attendees as a stringified JSON payload the handler decodes.
			form.append('attendees', JSON.stringify(payload));

			setLoading(true);

			fetch(config.ajaxUrl || '', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: form.toString()
			}).then(function (r) {
				return r.json().then(function (body) { return { status: r.status, body: body }; });
			}).then(function (res) {
				setLoading(false);
				if (res.body && res.body.success && res.body.data) {
					var data = res.body.data;
					if (data.redirect_url) {
						window.location.href = data.redirect_url;
						return;
					}
					if (data.stripe_session_url) {
						window.location.href = data.stripe_session_url;
						return;
					}
				}
				var message = 'Something went wrong. Please try again.';
				if (res.body && res.body.data && res.body.data.message) {
					message = res.body.data.message;
				}
				showError(message);
			}).catch(function () {
				setLoading(false);
				showError('Network error. Please try again.');
			});
		});

		// Initial label now (in case quantity event has not fired yet).
		updateLabel();
	}

	/**
	 * Collect attendee data from a widget root.
	 *
	 * @param {HTMLElement|null} root
	 * @returns {Array<Object>}
	 */
	function gatherAttendees(root) {
		var out = [];
		if (!root) { return out; }
		var fieldsets = root.querySelectorAll('.kdna-events-checkout-attendees__fieldset');
		for (var i = 0; i < fieldsets.length; i++) {
			var fieldset = fieldsets[i];
			var row = { name: '', email: '', phone: '', custom: {} };
			var inputs = fieldset.querySelectorAll('input, select');
			for (var j = 0; j < inputs.length; j++) {
				var input = inputs[j];
				var fieldWrap = input.closest('.kdna-events-checkout-attendees__field');
				if (!fieldWrap) { continue; }
				var key = fieldWrap.getAttribute('data-key') || '';
				var customKey = fieldWrap.getAttribute('data-custom-key') || '';
				if (customKey) {
					row.custom[customKey] = input.value;
				} else if (key === 'name' || key === 'email' || key === 'phone') {
					row[key] = input.value;
				}
			}
			out.push(row);
		}
		return out;
	}

	/**
	 * Scan the DOM for checkout widgets and wire them up.
	 */
	function initAll() {
		var quantities = document.querySelectorAll('[data-kdna-events-checkout-quantity]');
		for (var i = 0; i < quantities.length; i++) { wireQuantity(quantities[i]); }
		var attendees = document.querySelectorAll('[data-kdna-events-checkout-attendees]');
		for (var j = 0; j < attendees.length; j++) { wireAttendees(attendees[j]); }
		var summaries = document.querySelectorAll('[data-kdna-events-checkout-order-summary]');
		for (var k = 0; k < summaries.length; k++) { wireOrderSummary(summaries[k]); }
		var payButtons = document.querySelectorAll('[data-kdna-events-checkout-pay]');
		for (var m = 0; m < payButtons.length; m++) { wirePayButton(payButtons[m]); }
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
			var widgets = [
				'kdna-events-checkout-quantity',
				'kdna-events-checkout-attendees',
				'kdna-events-checkout-order-summary',
				'kdna-events-checkout-pay-button'
			];
			widgets.forEach(function (name) {
				window.elementorFrontend.hooks.addAction(
					'frontend/element_ready/' + name + '.default',
					function () { initAll(); }
				);
			});
		});
	}

	window.KDNAEvents = window.KDNAEvents || {};
	window.KDNAEvents.initCheckout = initAll;
})();
