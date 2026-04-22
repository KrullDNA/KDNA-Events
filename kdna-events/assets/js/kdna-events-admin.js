/**
 * KDNA Events admin UI behaviours.
 *
 * Drives the Event Details meta box (type section toggles) and the
 * attendee-fields repeater on both the event edit screen and the
 * Settings, Attendees tab. Each repeater declares its own input name
 * prefix via data-kdna-events-attendee-fields-name so the reindex
 * step keeps POST keys tidy per form.
 */
(function ($) {
	'use strict';

	$(function () {

		// Event Details meta box: show the venue / virtual sections per type.
		var $metabox = $('.kdna-events-metabox');
		if ($metabox.length) {

			/**
			 * Show or hide the venue / virtual sections based on the selected type.
			 *
			 * in-person: venue only.
			 * virtual:   virtual only.
			 * hybrid:    both.
			 */
			function refreshTypeSections() {
				var type = $metabox.find('input[name="kdna_event_type"]:checked').val() || 'in-person';
				$metabox.attr('data-event-type', type);

				var showVenue = ('in-person' === type || 'hybrid' === type);
				var showVirtual = ('virtual' === type || 'hybrid' === type);

				$metabox.find('[data-kdna-events-location]').toggle(showVenue);
				$metabox.find('[data-kdna-events-virtual]').toggle(showVirtual);
			}

			$metabox.on('change', 'input[name="kdna_event_type"]', refreshTypeSections);
			refreshTypeSections();
		}

		// Hide the manual Venue table when a saved Location is picked; same for Organiser.
		bindReferenceToggle('#kdna_event_location_ref', '[data-kdna-events-location-manual]');
		bindReferenceToggle('#kdna_event_organiser_ref', '[data-kdna-events-organiser-manual]');

		// Every attendee-fields repeater on the page.
		$('[data-kdna-events-attendee-fields]').each(function () {
			wireAttendeeRepeater($(this));
		});

		// Every Email Header Image / Email Design media picker on the page.
		$('[data-kdna-events-email-image]').each(function () {
			wireEmailImagePicker($(this));
		});

		// Email Design tab colour pickers.
		if ($.fn && $.fn.wpColorPicker) {
			$('.kdna-events-color-picker').wpColorPicker({
				change: function () {
					var form = this.closest ? this.closest('form') : $(this).closest('form').get(0);
					if (form && 'function' === typeof window.Event) {
						form.dispatchEvent(new window.Event('change', { bubbles: true }));
					}
				}
			});
		}
	});

	/**
	 * Google Maps lazy-loader callback.
	 *
	 * Maps JS is enqueued with `&callback=kdnaEventsAdminPlacesReady`
	 * so we can wire autocomplete the moment the library is available.
	 * The callback is exposed on window because Google's loader fires
	 * it globally.
	 */
	window.kdnaEventsAdminPlacesReady = function () {
		wirePlacesAutocomplete();
	};

	/**
	 * Attach Places Autocomplete to the venue + location CPT address inputs.
	 *
	 * On place_changed we populate the address, venue name (only if
	 * empty so we never stomp a deliberate override), and latitude /
	 * longitude fields. Works on both the event meta box's inline
	 * venue fieldset and the Location CPT edit screen's address row.
	 */
	function wirePlacesAutocomplete() {
		if (!window.google || !window.google.maps || !window.google.maps.places) {
			return;
		}

		var targets = [
			// Event meta box, manual venue section.
			{
				address: document.getElementById('kdna_event_location_address'),
				name:    document.getElementById('kdna_event_location_name'),
				lat:     document.getElementById('kdna_event_location_lat'),
				lng:     document.getElementById('kdna_event_location_lng')
			},
			// Location CPT meta box.
			{
				address: document.getElementById('kdna_event_loc_address'),
				name:    null,
				lat:     document.getElementById('kdna_event_loc_lat'),
				lng:     document.getElementById('kdna_event_loc_lng')
			}
		];

		targets.forEach(function (t) {
			if (!t.address) {
				return;
			}
			if (t.address.getAttribute('data-kdna-events-places-wired') === '1') {
				return;
			}
			t.address.setAttribute('data-kdna-events-places-wired', '1');

			var ac = new window.google.maps.places.Autocomplete(t.address, {
				types: ['establishment', 'geocode'],
				fields: ['name', 'formatted_address', 'geometry']
			});

			ac.addListener('place_changed', function () {
				var place = ac.getPlace();
				if (!place) {
					return;
				}
				if (place.formatted_address) {
					t.address.value = place.formatted_address;
				}
				if (t.name && place.name && '' === (t.name.value || '').trim()) {
					t.name.value = place.name;
				}
				if (place.geometry && place.geometry.location) {
					if (t.lat) { t.lat.value = place.geometry.location.lat().toFixed(7); }
					if (t.lng) { t.lng.value = place.geometry.location.lng().toFixed(7); }
				}
			});

			// Suppress the default 'Enter submits form' behaviour while the
			// user is tabbing through Autocomplete suggestions.
			t.address.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') {
					var pac = document.querySelector('.pac-container');
					if (pac && pac.offsetHeight > 0) {
						e.preventDefault();
					}
				}
			});
		});
	}

	/**
	 * Hide a manual-entry table whenever a reference dropdown picks a saved CPT row.
	 *
	 * Used for the Location and Organiser dropdowns on the event meta
	 * box. Zero means 'enter manually', so the manual table is visible
	 * only for that value.
	 *
	 * @param {string} selectSelector  jQuery selector for the dropdown.
	 * @param {string} manualSelector  jQuery selector for the table of free-text fields.
	 */
	function bindReferenceToggle(selectSelector, manualSelector) {
		var $select = $(selectSelector);
		var $manual = $(manualSelector);
		if (!$select.length || !$manual.length) {
			return;
		}
		function refresh() {
			$manual.toggle(0 === parseInt($select.val(), 10));
		}
		$select.on('change', refresh);
		refresh();
	}

	/**
	 * Attach add / remove / reindex behaviour to a single repeater.
	 *
	 * The repeater's input name prefix is inferred from an existing row
	 * or from the data-kdna-events-attendee-fields-name attribute so
	 * the helper works regardless of whether the repeater belongs to
	 * the event meta box or the Settings, Attendees tab.
	 *
	 * @param {jQuery} $repeater
	 */
	function wireAttendeeRepeater($repeater) {
		var $list = $repeater.find('[data-kdna-events-attendee-fields-list]');
		var $template = $repeater.find('[data-kdna-events-attendee-fields-template]');
		var nameBase = $repeater.data('kdnaEventsAttendeeFieldsName') || 'kdna_event_attendee_fields';

		function prefixRegex() {
			var escaped = String(nameBase).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
			return new RegExp(escaped + '\\[[^\\]]*\\]');
		}

		function reindexRows() {
			var pattern = prefixRegex();
			$list.find('[data-kdna-events-attendee-field]').each(function (index) {
				$(this)
					.find('[name]')
					.each(function () {
						var name = $(this).attr('name') || '';
						var replaced = name.replace(pattern, nameBase + '[' + index + ']');
						$(this).attr('name', replaced);
					});
			});
		}

		$repeater.on('click', '[data-kdna-events-attendee-fields-add]', function (event) {
			event.preventDefault();
			var tpl = $template.html() || '';
			var nextIndex = $list.find('[data-kdna-events-attendee-field]').length;
			var rendered = tpl.replace(/\{\{INDEX\}\}/g, nextIndex);
			$list.append(rendered);
			reindexRows();
		});

		$repeater.on('click', '[data-kdna-events-attendee-field-remove]', function (event) {
			event.preventDefault();
			$(this).closest('[data-kdna-events-attendee-field]').remove();
			reindexRows();
		});
	}

	/**
	 * Attach a wp.media picker to an email image field.
	 *
	 * Shared between the event Email Header Image field and the
	 * Email Design, Brand settings (logo + default header image).
	 * The field contains a hidden input (the attachment ID), a preview
	 * node and select / remove buttons. Emits a 'kdna:email-image-change'
	 * DOM event whenever the value changes so the Email Design live
	 * preview can re-render.
	 *
	 * @param {jQuery} $field
	 */
	function wireEmailImagePicker($field) {
		if (!window.wp || !window.wp.media) {
			return;
		}
		var $input = $field.find('[data-kdna-events-email-image-input]');
		var $preview = $field.find('[data-kdna-events-email-image-preview]');
		var $select = $field.find('[data-kdna-events-email-image-select]');
		var $remove = $field.find('[data-kdna-events-email-image-remove]');

		var frame;

		function notify() {
			if ('function' === typeof window.CustomEvent) {
				$field[0].dispatchEvent(new window.CustomEvent('kdna:email-image-change', { bubbles: true }));
			}
		}

		$select.on('click', function (event) {
			event.preventDefault();

			if (frame) {
				frame.open();
				return;
			}

			frame = window.wp.media({
				title: $select.text() || 'Select image',
				button: { text: 'Use this image' },
				library: { type: ['image/jpeg', 'image/png'] },
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				if (!attachment || !attachment.id) {
					return;
				}
				$input.val(attachment.id);
				var url = attachment.url;
				if (attachment.sizes) {
					if (attachment.sizes.medium && attachment.sizes.medium.url) {
						url = attachment.sizes.medium.url;
					} else if (attachment.sizes.thumbnail && attachment.sizes.thumbnail.url) {
						url = attachment.sizes.thumbnail.url;
					}
				}
				$preview.html('<img src="' + url + '" alt="" />');
				$remove.removeAttr('hidden');
				notify();
			});

			frame.open();
		});

		$remove.on('click', function (event) {
			event.preventDefault();
			$input.val('');
			$preview.empty();
			$remove.attr('hidden', 'hidden');
			notify();
		});
	}
})(jQuery);
