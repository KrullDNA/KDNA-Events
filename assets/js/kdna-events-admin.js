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

		// Every attendee-fields repeater on the page.
		$('[data-kdna-events-attendee-fields]').each(function () {
			wireAttendeeRepeater($(this));
		});
	});

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
})(jQuery);
