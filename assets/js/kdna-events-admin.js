/**
 * KDNA Events admin UI behaviours.
 *
 * Drives the Event Details meta box:
 *   - toggles the venue and virtual-URL fieldsets based on event type,
 *   - powers the custom attendee fields repeater.
 */
(function ($) {
	'use strict';

	$(function () {
		var $metabox = $('.kdna-events-metabox');
		if (!$metabox.length) {
			return;
		}

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

		// Attendee fields repeater.
		var $repeater = $metabox.find('[data-kdna-events-attendee-fields]');
		if (!$repeater.length) {
			return;
		}

		var $list = $repeater.find('[data-kdna-events-attendee-fields-list]');
		var $template = $repeater.find('[data-kdna-events-attendee-fields-template]');

		/**
		 * Reindex every row so POST keys stay contiguous after insert/remove.
		 */
		function reindexRows() {
			$list.find('[data-kdna-events-attendee-field]').each(function (index) {
				$(this)
					.find('[name]')
					.each(function () {
						var name = $(this).attr('name') || '';
						var replaced = name.replace(/kdna_event_attendee_fields\[[^\]]*\]/, 'kdna_event_attendee_fields[' + index + ']');
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
	});
})(jQuery);
