<?php
/**
 * Admin UI for the KDNA Events custom post type.
 *
 * Provides the Event Details meta box with every field from Section 4
 * of the project brief, save routing with sanitisation and clamping,
 * custom list columns and admin asset enqueuing scoped to the edit screen.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires up the admin UI for kdna_event.
 */
class KDNA_Events_Admin {

	/**
	 * Nonce action for meta box saves.
	 */
	const NONCE_ACTION = 'kdna_events_save_event';

	/**
	 * Nonce field name for meta box saves.
	 */
	const NONCE_NAME = 'kdna_events_event_nonce';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_box' ) );
		add_action( 'save_post_' . KDNA_Events_CPT::POST_TYPE, array( __CLASS__, 'save_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		add_filter( 'manage_' . KDNA_Events_CPT::POST_TYPE . '_posts_columns', array( __CLASS__, 'register_columns' ) );
		add_action( 'manage_' . KDNA_Events_CPT::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
		add_filter( 'manage_edit-' . KDNA_Events_CPT::POST_TYPE . '_sortable_columns', array( __CLASS__, 'register_sortable_columns' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'apply_sortable_orderby' ) );

		// Location + Organiser CPT meta boxes.
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_location_meta_box' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_organiser_meta_box' ) );
		add_action( 'save_post_' . KDNA_Events_CPT::LOCATION_POST_TYPE, array( __CLASS__, 'save_location_meta' ), 10, 2 );
		add_action( 'save_post_' . KDNA_Events_CPT::ORGANISER_POST_TYPE, array( __CLASS__, 'save_organiser_meta' ), 10, 2 );
	}

	/**
	 * Return the list of supported currency codes.
	 *
	 * @return array<string,string>
	 */
	public static function supported_currencies() {
		return array(
			'AUD' => __( 'Australian Dollar (AUD)', 'kdna-events' ),
			'USD' => __( 'US Dollar (USD)', 'kdna-events' ),
			'EUR' => __( 'Euro (EUR)', 'kdna-events' ),
			'GBP' => __( 'Pound Sterling (GBP)', 'kdna-events' ),
			'NZD' => __( 'New Zealand Dollar (NZD)', 'kdna-events' ),
		);
	}

	/**
	 * Return the list of supported event types.
	 *
	 * @return array<string,string>
	 */
	public static function event_types() {
		return array(
			'in-person' => __( 'In-person', 'kdna-events' ),
			'virtual'   => __( 'Virtual', 'kdna-events' ),
			'hybrid'    => __( 'Hybrid', 'kdna-events' ),
		);
	}

	/**
	 * Register the Event Details meta box.
	 *
	 * @return void
	 */
	public static function register_meta_box() {
		add_meta_box(
			'kdna_events_event_details',
			__( 'Event Details', 'kdna-events' ),
			array( __CLASS__, 'render_meta_box' ),
			KDNA_Events_CPT::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the Event Details meta box.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public static function render_meta_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$subtitle             = (string) get_post_meta( $post->ID, '_kdna_event_subtitle', true );
		$start                = (string) get_post_meta( $post->ID, '_kdna_event_start', true );
		$end                  = (string) get_post_meta( $post->ID, '_kdna_event_end', true );
		$timezone             = (string) get_post_meta( $post->ID, '_kdna_event_timezone', true );
		$registration_opens   = (string) get_post_meta( $post->ID, '_kdna_event_registration_opens', true );
		$registration_closes  = (string) get_post_meta( $post->ID, '_kdna_event_registration_closes', true );
		$price                = get_post_meta( $post->ID, '_kdna_event_price', true );
		$currency             = (string) get_post_meta( $post->ID, '_kdna_event_currency', true );
		$type                 = (string) get_post_meta( $post->ID, '_kdna_event_type', true );
		$virtual_url          = (string) get_post_meta( $post->ID, '_kdna_event_virtual_url', true );
		$capacity             = (int) get_post_meta( $post->ID, '_kdna_event_capacity', true );
		$min_per_order        = (int) get_post_meta( $post->ID, '_kdna_event_min_tickets_per_order', true );
		$max_per_order        = (int) get_post_meta( $post->ID, '_kdna_event_max_tickets_per_order', true );
		$organiser_name       = (string) get_post_meta( $post->ID, '_kdna_event_organiser_name', true );
		$organiser_email      = (string) get_post_meta( $post->ID, '_kdna_event_organiser_email', true );

		$location = kdna_events_get_event_location( $post->ID );

		$attendee_fields_raw = get_post_meta( $post->ID, '_kdna_event_attendee_fields', true );
		$attendee_fields     = array();
		if ( ! empty( $attendee_fields_raw ) ) {
			$decoded = is_array( $attendee_fields_raw ) ? $attendee_fields_raw : json_decode( (string) $attendee_fields_raw, true );
			if ( is_array( $decoded ) ) {
				$attendee_fields = $decoded;
			}
		}

		$ignore_global_fields = (bool) get_post_meta( $post->ID, '_kdna_event_ignore_global_attendee_fields', true );
		$has_globals          = '' !== trim( (string) get_option( 'kdna_events_global_attendee_fields', '' ) );

		$location_ref  = (int) get_post_meta( $post->ID, '_kdna_event_location_ref', true );
		$organiser_ref = (int) get_post_meta( $post->ID, '_kdna_event_organiser_ref', true );
		$locations     = self::fetch_reference_posts( KDNA_Events_CPT::LOCATION_POST_TYPE );
		$organisers    = self::fetch_reference_posts( KDNA_Events_CPT::ORGANISER_POST_TYPE );

		if ( '' === $type ) {
			$type = 'in-person';
		}
		if ( '' === $currency ) {
			$currency = (string) get_option( 'kdna_events_default_currency', 'AUD' );
		}
		if ( '' === $timezone ) {
			$timezone = wp_timezone_string();
		}
		if ( ! $min_per_order ) {
			$min_per_order = 1;
		}
		if ( ! $max_per_order ) {
			$max_per_order = (int) get_option( 'kdna_events_default_max_per_order', 10 );
			if ( ! $max_per_order ) {
				$max_per_order = 10;
			}
		}
		$price_display = ( '' === $price || null === $price ) ? '0.00' : number_format( (float) $price, 2, '.', '' );
		?>
		<div class="kdna-events-metabox" data-event-type="<?php echo esc_attr( $type ); ?>">

			<h3 class="kdna-events-section-heading"><?php esc_html_e( 'Basics', 'kdna-events' ); ?></h3>
			<table class="form-table" role="presentation">
				<tbody>
				<tr>
					<th scope="row"><label for="kdna_event_subtitle"><?php esc_html_e( 'Subtitle', 'kdna-events' ); ?></label></th>
					<td><input type="text" class="regular-text" id="kdna_event_subtitle" name="kdna_event_subtitle" value="<?php echo esc_attr( $subtitle ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_event_start"><?php esc_html_e( 'Start', 'kdna-events' ); ?> <span class="description">*</span></label></th>
					<td><input type="datetime-local" id="kdna_event_start" name="kdna_event_start" value="<?php echo esc_attr( $start ); ?>" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_event_end"><?php esc_html_e( 'End', 'kdna-events' ); ?> <span class="description">*</span></label></th>
					<td><input type="datetime-local" id="kdna_event_end" name="kdna_event_end" value="<?php echo esc_attr( $end ); ?>" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_event_timezone"><?php esc_html_e( 'Timezone', 'kdna-events' ); ?></label></th>
					<td>
						<select id="kdna_event_timezone" name="kdna_event_timezone">
							<?php echo wp_timezone_choice( $timezone, get_user_locale() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</select>
						<p class="description"><?php esc_html_e( 'Defaults to the site timezone.', 'kdna-events' ); ?></p>
					</td>
				</tr>
				</tbody>
			</table>

			<h3 class="kdna-events-section-heading"><?php esc_html_e( 'Registration Window', 'kdna-events' ); ?></h3>
			<table class="form-table" role="presentation">
				<tbody>
				<tr>
					<th scope="row"><label for="kdna_event_registration_opens"><?php esc_html_e( 'Opens at', 'kdna-events' ); ?></label></th>
					<td>
						<input type="datetime-local" id="kdna_event_registration_opens" name="kdna_event_registration_opens" value="<?php echo esc_attr( $registration_opens ); ?>" />
						<p class="description"><?php esc_html_e( 'Optional. Leave blank to open registration immediately.', 'kdna-events' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_event_registration_closes"><?php esc_html_e( 'Closes at', 'kdna-events' ); ?></label></th>
					<td>
						<input type="datetime-local" id="kdna_event_registration_closes" name="kdna_event_registration_closes" value="<?php echo esc_attr( $registration_closes ); ?>" />
						<p class="description"><?php esc_html_e( 'Defaults to event start if blank.', 'kdna-events' ); ?></p>
					</td>
				</tr>
				</tbody>
			</table>

			<h3 class="kdna-events-section-heading"><?php esc_html_e( 'Pricing', 'kdna-events' ); ?></h3>
			<table class="form-table" role="presentation">
				<tbody>
				<tr>
					<th scope="row"><label for="kdna_event_price"><?php esc_html_e( 'Price', 'kdna-events' ); ?></label></th>
					<td>
						<input type="number" step="0.01" min="0" id="kdna_event_price" name="kdna_event_price" value="<?php echo esc_attr( $price_display ); ?>" />
						<p class="description"><?php esc_html_e( 'Leave at 0 for a free event.', 'kdna-events' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_event_currency"><?php esc_html_e( 'Currency', 'kdna-events' ); ?></label></th>
					<td>
						<select id="kdna_event_currency" name="kdna_event_currency">
							<?php foreach ( self::supported_currencies() as $code => $label ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $currency, $code ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				</tbody>
			</table>

			<h3 class="kdna-events-section-heading"><?php esc_html_e( 'Type and Location', 'kdna-events' ); ?></h3>
			<table class="form-table" role="presentation">
				<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Event type', 'kdna-events' ); ?></th>
					<td>
						<fieldset class="kdna-events-type-fieldset">
							<?php foreach ( self::event_types() as $value => $label ) : ?>
								<label class="kdna-events-type-option">
									<input type="radio" name="kdna_event_type" value="<?php echo esc_attr( $value ); ?>" <?php checked( $type, $value ); ?> />
									<span><?php echo esc_html( $label ); ?></span>
								</label>
							<?php endforeach; ?>
						</fieldset>
					</td>
				</tr>
				</tbody>
			</table>

			<fieldset class="kdna-events-location-fieldset" data-kdna-events-location>
				<legend><?php esc_html_e( 'Venue', 'kdna-events' ); ?></legend>
				<table class="form-table" role="presentation">
					<tbody>
					<tr>
						<th scope="row"><label for="kdna_event_location_ref"><?php esc_html_e( 'Use saved location', 'kdna-events' ); ?></label></th>
						<td>
							<select id="kdna_event_location_ref" name="kdna_event_location_ref">
								<option value="0"><?php esc_html_e( 'Enter manually below', 'kdna-events' ); ?></option>
								<?php foreach ( $locations as $loc ) : ?>
									<option value="<?php echo esc_attr( (string) $loc->ID ); ?>" <?php selected( $location_ref, $loc->ID ); ?>><?php echo esc_html( $loc->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
							<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . KDNA_Events_CPT::LOCATION_POST_TYPE ) ); ?>" target="_blank" rel="noopener">
								<?php esc_html_e( 'Add new location', 'kdna-events' ); ?>
							</a>
							<p class="description"><?php esc_html_e( 'Pick a saved venue or leave this on "Enter manually below" to capture a one-off location.', 'kdna-events' ); ?></p>
						</td>
					</tr>
					</tbody>
				</table>
				<table class="form-table" role="presentation" data-kdna-events-location-manual>
					<tbody>
					<tr>
						<th scope="row"><label for="kdna_event_location_name"><?php esc_html_e( 'Venue name', 'kdna-events' ); ?></label></th>
						<td><input type="text" class="regular-text" id="kdna_event_location_name" name="kdna_event_location[name]" value="<?php echo esc_attr( $location['name'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="kdna_event_location_address"><?php esc_html_e( 'Address', 'kdna-events' ); ?></label></th>
						<td><input type="text" class="regular-text" id="kdna_event_location_address" name="kdna_event_location[address]" value="<?php echo esc_attr( $location['address'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="kdna_event_location_lat"><?php esc_html_e( 'Latitude', 'kdna-events' ); ?></label></th>
						<td><input type="text" class="regular-text" id="kdna_event_location_lat" name="kdna_event_location[lat]" value="<?php echo esc_attr( (string) $location['lat'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="kdna_event_location_lng"><?php esc_html_e( 'Longitude', 'kdna-events' ); ?></label></th>
						<td><input type="text" class="regular-text" id="kdna_event_location_lng" name="kdna_event_location[lng]" value="<?php echo esc_attr( (string) $location['lng'] ); ?>" /></td>
					</tr>
					</tbody>
				</table>
			</fieldset>

			<fieldset class="kdna-events-virtual-fieldset" data-kdna-events-virtual>
				<legend><?php esc_html_e( 'Virtual link', 'kdna-events' ); ?></legend>
				<table class="form-table" role="presentation">
					<tbody>
					<tr>
						<th scope="row"><label for="kdna_event_virtual_url"><?php esc_html_e( 'Virtual URL', 'kdna-events' ); ?></label></th>
						<td><input type="url" class="regular-text" id="kdna_event_virtual_url" name="kdna_event_virtual_url" value="<?php echo esc_attr( $virtual_url ); ?>" /></td>
					</tr>
					</tbody>
				</table>
			</fieldset>

			<h3 class="kdna-events-section-heading"><?php esc_html_e( 'Capacity and Limits', 'kdna-events' ); ?></h3>
			<table class="form-table" role="presentation">
				<tbody>
				<tr>
					<th scope="row"><label for="kdna_event_capacity"><?php esc_html_e( 'Capacity', 'kdna-events' ); ?></label></th>
					<td>
						<input type="number" min="0" step="1" id="kdna_event_capacity" name="kdna_event_capacity" value="<?php echo esc_attr( (string) $capacity ); ?>" />
						<p class="description"><?php esc_html_e( '0 = unlimited. Maximum total tickets this event can sell.', 'kdna-events' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_event_min_tickets_per_order"><?php esc_html_e( 'Min tickets per order', 'kdna-events' ); ?></label></th>
					<td>
						<input type="number" min="1" step="1" id="kdna_event_min_tickets_per_order" name="kdna_event_min_tickets_per_order" value="<?php echo esc_attr( (string) $min_per_order ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_event_max_tickets_per_order"><?php esc_html_e( 'Max tickets per order', 'kdna-events' ); ?></label></th>
					<td>
						<input type="number" min="1" step="1" id="kdna_event_max_tickets_per_order" name="kdna_event_max_tickets_per_order" value="<?php echo esc_attr( (string) $max_per_order ); ?>" />
						<p class="description"><?php esc_html_e( 'Per single buyer, not total.', 'kdna-events' ); ?></p>
					</td>
				</tr>
				</tbody>
			</table>

			<h3 class="kdna-events-section-heading"><?php esc_html_e( 'Organiser', 'kdna-events' ); ?></h3>
			<table class="form-table" role="presentation">
				<tbody>
				<tr>
					<th scope="row"><label for="kdna_event_organiser_ref"><?php esc_html_e( 'Use saved organiser', 'kdna-events' ); ?></label></th>
					<td>
						<select id="kdna_event_organiser_ref" name="kdna_event_organiser_ref">
							<option value="0"><?php esc_html_e( 'Enter manually below', 'kdna-events' ); ?></option>
							<?php foreach ( $organisers as $org ) : ?>
								<option value="<?php echo esc_attr( (string) $org->ID ); ?>" <?php selected( $organiser_ref, $org->ID ); ?>><?php echo esc_html( $org->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
						<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . KDNA_Events_CPT::ORGANISER_POST_TYPE ) ); ?>" target="_blank" rel="noopener">
							<?php esc_html_e( 'Add new organiser', 'kdna-events' ); ?>
						</a>
					</td>
				</tr>
				</tbody>
			</table>
			<table class="form-table" role="presentation" data-kdna-events-organiser-manual>
				<tbody>
				<tr>
					<th scope="row"><label for="kdna_event_organiser_name"><?php esc_html_e( 'Organiser name', 'kdna-events' ); ?></label></th>
					<td><input type="text" class="regular-text" id="kdna_event_organiser_name" name="kdna_event_organiser_name" value="<?php echo esc_attr( $organiser_name ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_event_organiser_email"><?php esc_html_e( 'Organiser email', 'kdna-events' ); ?></label></th>
					<td>
						<input type="email" class="regular-text" id="kdna_event_organiser_email" name="kdna_event_organiser_email" value="<?php echo esc_attr( $organiser_email ); ?>" />
						<p class="description"><?php esc_html_e( 'Optional. If set and organiser notifications are enabled in Settings, this address receives booking alerts for this event.', 'kdna-events' ); ?></p>
					</td>
				</tr>
				</tbody>
			</table>

			<h3 class="kdna-events-section-heading"><?php esc_html_e( 'Custom Attendee Fields', 'kdna-events' ); ?></h3>
			<p class="description">
				<?php
				if ( $has_globals ) {
					esc_html_e( 'Extra fields captured for every ticket at checkout, on top of name, email and phone. Global fields from Settings, Attendees apply first; fields below extend the global list or override a global when they share the same key.', 'kdna-events' );
				} else {
					esc_html_e( 'Extra fields captured for every ticket at checkout, on top of name, email and phone.', 'kdna-events' );
				}
				?>
			</p>

			<?php if ( $has_globals ) : ?>
				<p>
					<label>
						<input type="checkbox" name="kdna_event_ignore_global_attendee_fields" value="1" <?php checked( $ignore_global_fields ); ?> />
						<?php esc_html_e( 'Ignore global attendee fields for this event', 'kdna-events' ); ?>
					</label>
				</p>
			<?php endif; ?>

			<div class="kdna-events-attendee-fields" data-kdna-events-attendee-fields data-kdna-events-attendee-fields-name="kdna_event_attendee_fields">
				<div class="kdna-events-attendee-fields__list" data-kdna-events-attendee-fields-list>
					<?php if ( ! empty( $attendee_fields ) ) : ?>
						<?php foreach ( $attendee_fields as $index => $row ) : ?>
							<?php self::render_attendee_field_row( (int) $index, is_array( $row ) ? $row : array() ); ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
				<p>
					<button type="button" class="button" data-kdna-events-attendee-fields-add>
						<?php esc_html_e( 'Add field', 'kdna-events' ); ?>
					</button>
				</p>
				<script type="text/html" data-kdna-events-attendee-fields-template>
					<?php self::render_attendee_field_row( 0, array(), true ); ?>
				</script>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a single attendee-field repeater row.
	 *
	 * @param int   $index       Row index. Used only when $is_template is false.
	 * @param array $row         Saved row data.
	 * @param bool  $is_template Whether this row is the JS template (uses {{INDEX}}).
	 * @return void
	 */
	protected static function render_attendee_field_row( $index, $row, $is_template = false ) {
		$placeholder = $is_template ? '{{INDEX}}' : (string) $index;

		$label    = isset( $row['label'] ) ? (string) $row['label'] : '';
		$key      = isset( $row['key'] ) ? (string) $row['key'] : '';
		$type     = isset( $row['type'] ) ? (string) $row['type'] : 'text';
		$required = ! empty( $row['required'] );

		$types = array(
			'text'   => __( 'Text', 'kdna-events' ),
			'email'  => __( 'Email', 'kdna-events' ),
			'tel'    => __( 'Phone', 'kdna-events' ),
			'select' => __( 'Select', 'kdna-events' ),
		);
		?>
		<div class="kdna-events-attendee-field" data-kdna-events-attendee-field>
			<div class="kdna-events-attendee-field__cell">
				<label><?php esc_html_e( 'Label', 'kdna-events' ); ?></label>
				<input type="text" name="kdna_event_attendee_fields[<?php echo esc_attr( $placeholder ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" />
			</div>
			<div class="kdna-events-attendee-field__cell">
				<label><?php esc_html_e( 'Key', 'kdna-events' ); ?></label>
				<input type="text" name="kdna_event_attendee_fields[<?php echo esc_attr( $placeholder ); ?>][key]" value="<?php echo esc_attr( $key ); ?>" />
			</div>
			<div class="kdna-events-attendee-field__cell">
				<label><?php esc_html_e( 'Type', 'kdna-events' ); ?></label>
				<select name="kdna_event_attendee_fields[<?php echo esc_attr( $placeholder ); ?>][type]">
					<?php foreach ( $types as $value => $type_label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $type_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="kdna-events-attendee-field__cell kdna-events-attendee-field__cell--checkbox">
				<label>
					<input type="checkbox" name="kdna_event_attendee_fields[<?php echo esc_attr( $placeholder ); ?>][required]" value="1" <?php checked( $required ); ?> />
					<?php esc_html_e( 'Required', 'kdna-events' ); ?>
				</label>
			</div>
			<div class="kdna-events-attendee-field__cell kdna-events-attendee-field__cell--actions">
				<button type="button" class="button-link-delete" data-kdna-events-attendee-field-remove>
					<?php esc_html_e( 'Remove', 'kdna-events' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Save meta box values.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function save_meta( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Subtitle.
		$subtitle = isset( $_POST['kdna_event_subtitle'] ) ? sanitize_text_field( wp_unslash( $_POST['kdna_event_subtitle'] ) ) : '';
		update_post_meta( $post_id, '_kdna_event_subtitle', $subtitle );

		// Start / End / Registration window, stored as Y-m-d\TH:i.
		foreach ( array(
			'kdna_event_start'                => '_kdna_event_start',
			'kdna_event_end'                  => '_kdna_event_end',
			'kdna_event_registration_opens'   => '_kdna_event_registration_opens',
			'kdna_event_registration_closes'  => '_kdna_event_registration_closes',
		) as $post_key => $meta_key ) {
			$raw = isset( $_POST[ $post_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) ) : '';
			update_post_meta( $post_id, $meta_key, self::normalise_datetime( $raw ) );
		}

		// Timezone.
		$timezone = isset( $_POST['kdna_event_timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['kdna_event_timezone'] ) ) : '';
		update_post_meta( $post_id, '_kdna_event_timezone', $timezone );

		// Price.
		$price = isset( $_POST['kdna_event_price'] ) ? (float) wp_unslash( $_POST['kdna_event_price'] ) : 0.0;
		if ( $price < 0 ) {
			$price = 0.0;
		}
		update_post_meta( $post_id, '_kdna_event_price', $price );

		// Currency.
		$currency_raw      = isset( $_POST['kdna_event_currency'] ) ? sanitize_text_field( wp_unslash( $_POST['kdna_event_currency'] ) ) : 'AUD';
		$allowed_currencies = array_keys( self::supported_currencies() );
		if ( ! in_array( $currency_raw, $allowed_currencies, true ) ) {
			$currency_raw = 'AUD';
		}
		update_post_meta( $post_id, '_kdna_event_currency', $currency_raw );

		// Event type.
		$type = isset( $_POST['kdna_event_type'] ) ? sanitize_text_field( wp_unslash( $_POST['kdna_event_type'] ) ) : 'in-person';
		if ( ! array_key_exists( $type, self::event_types() ) ) {
			$type = 'in-person';
		}
		update_post_meta( $post_id, '_kdna_event_type', $type );

		// Virtual URL.
		$virtual_url = isset( $_POST['kdna_event_virtual_url'] ) ? esc_url_raw( wp_unslash( $_POST['kdna_event_virtual_url'] ) ) : '';
		update_post_meta( $post_id, '_kdna_event_virtual_url', $virtual_url );

		// Location JSON.
		$location_input = isset( $_POST['kdna_event_location'] ) && is_array( $_POST['kdna_event_location'] ) ? wp_unslash( $_POST['kdna_event_location'] ) : array();
		$location       = array(
			'name'    => isset( $location_input['name'] ) ? sanitize_text_field( (string) $location_input['name'] ) : '',
			'address' => isset( $location_input['address'] ) ? sanitize_text_field( (string) $location_input['address'] ) : '',
			'lat'     => isset( $location_input['lat'] ) ? (float) $location_input['lat'] : 0.0,
			'lng'     => isset( $location_input['lng'] ) ? (float) $location_input['lng'] : 0.0,
		);
		update_post_meta( $post_id, '_kdna_event_location', wp_json_encode( $location ) );

		// Capacity.
		$capacity = isset( $_POST['kdna_event_capacity'] ) ? absint( wp_unslash( $_POST['kdna_event_capacity'] ) ) : 0;
		update_post_meta( $post_id, '_kdna_event_capacity', $capacity );

		// Min / Max tickets per order with clamping.
		$min_per_order = isset( $_POST['kdna_event_min_tickets_per_order'] ) ? absint( wp_unslash( $_POST['kdna_event_min_tickets_per_order'] ) ) : 1;
		if ( $min_per_order < 1 ) {
			$min_per_order = 1;
		}

		$max_per_order = isset( $_POST['kdna_event_max_tickets_per_order'] ) ? absint( wp_unslash( $_POST['kdna_event_max_tickets_per_order'] ) ) : 10;
		if ( $max_per_order < $min_per_order ) {
			$max_per_order = $min_per_order;
		}

		update_post_meta( $post_id, '_kdna_event_min_tickets_per_order', $min_per_order );
		update_post_meta( $post_id, '_kdna_event_max_tickets_per_order', $max_per_order );

		// Organiser.
		$organiser_name  = isset( $_POST['kdna_event_organiser_name'] ) ? sanitize_text_field( wp_unslash( $_POST['kdna_event_organiser_name'] ) ) : '';
		$organiser_email = isset( $_POST['kdna_event_organiser_email'] ) ? sanitize_email( wp_unslash( $_POST['kdna_event_organiser_email'] ) ) : '';
		update_post_meta( $post_id, '_kdna_event_organiser_name', $organiser_name );
		update_post_meta( $post_id, '_kdna_event_organiser_email', $organiser_email );

		// Custom attendee fields.
		$attendee_input  = isset( $_POST['kdna_event_attendee_fields'] ) && is_array( $_POST['kdna_event_attendee_fields'] ) ? wp_unslash( $_POST['kdna_event_attendee_fields'] ) : array();
		$attendee_clean  = array();
		$allowed_types   = array( 'text', 'email', 'tel', 'select' );

		foreach ( $attendee_input as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}
			$key  = isset( $row['key'] ) && '' !== $row['key'] ? sanitize_key( (string) $row['key'] ) : sanitize_key( $label );
			$type = isset( $row['type'] ) ? sanitize_key( (string) $row['type'] ) : 'text';
			if ( ! in_array( $type, $allowed_types, true ) ) {
				$type = 'text';
			}
			$attendee_clean[] = array(
				'label'    => $label,
				'key'      => $key,
				'type'     => $type,
				'required' => ! empty( $row['required'] ),
			);
		}
		update_post_meta( $post_id, '_kdna_event_attendee_fields', wp_json_encode( $attendee_clean ) );

		$ignore_global = isset( $_POST['kdna_event_ignore_global_attendee_fields'] ) ? 1 : 0;
		update_post_meta( $post_id, '_kdna_event_ignore_global_attendee_fields', $ignore_global );

		$location_ref = isset( $_POST['kdna_event_location_ref'] ) ? absint( wp_unslash( $_POST['kdna_event_location_ref'] ) ) : 0;
		if ( $location_ref && 'kdna_event_location' !== get_post_type( $location_ref ) ) {
			$location_ref = 0;
		}
		update_post_meta( $post_id, '_kdna_event_location_ref', $location_ref );

		$organiser_ref = isset( $_POST['kdna_event_organiser_ref'] ) ? absint( wp_unslash( $_POST['kdna_event_organiser_ref'] ) ) : 0;
		if ( $organiser_ref && 'kdna_event_organiser' !== get_post_type( $organiser_ref ) ) {
			$organiser_ref = 0;
		}
		update_post_meta( $post_id, '_kdna_event_organiser_ref', $organiser_ref );

		unset( $post );
	}

	/**
	 * Fetch published posts of a CPT for reference dropdowns, id ASC by title.
	 *
	 * @param string $post_type Post type slug.
	 * @return WP_Post[]
	 */
	protected static function fetch_reference_posts( $post_type ) {
		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'private' ),
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		return is_array( $posts ) ? $posts : array();
	}

	/**
	 * Register the meta box on the Location CPT edit screen.
	 *
	 * @return void
	 */
	public static function register_location_meta_box() {
		add_meta_box(
			'kdna_events_location_details',
			__( 'Location Details', 'kdna-events' ),
			array( __CLASS__, 'render_location_meta_box' ),
			KDNA_Events_CPT::LOCATION_POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the Location CPT meta box.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public static function render_location_meta_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION . '_location', self::NONCE_NAME );

		$address = (string) get_post_meta( $post->ID, '_kdna_event_loc_address', true );
		$lat     = (string) get_post_meta( $post->ID, '_kdna_event_loc_lat', true );
		$lng     = (string) get_post_meta( $post->ID, '_kdna_event_loc_lng', true );
		?>
		<div class="kdna-events-metabox">
			<table class="form-table" role="presentation">
				<tbody>
				<tr>
					<th scope="row"><label for="kdna_event_loc_address"><?php esc_html_e( 'Address', 'kdna-events' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="kdna_event_loc_address" name="kdna_event_loc_address" value="<?php echo esc_attr( $address ); ?>" autocomplete="off" />
						<p class="description"><?php esc_html_e( 'Street address. Start typing to auto-fill from Google Places when a Maps API key is configured.', 'kdna-events' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_event_loc_lat"><?php esc_html_e( 'Latitude', 'kdna-events' ); ?></label></th>
					<td><input type="text" class="regular-text" id="kdna_event_loc_lat" name="kdna_event_loc_lat" value="<?php echo esc_attr( $lat ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_event_loc_lng"><?php esc_html_e( 'Longitude', 'kdna-events' ); ?></label></th>
					<td><input type="text" class="regular-text" id="kdna_event_loc_lng" name="kdna_event_loc_lng" value="<?php echo esc_attr( $lng ); ?>" /></td>
				</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Save Location CPT meta.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function save_location_meta( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION . '_location' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$address = isset( $_POST['kdna_event_loc_address'] ) ? sanitize_text_field( wp_unslash( $_POST['kdna_event_loc_address'] ) ) : '';
		$lat     = isset( $_POST['kdna_event_loc_lat'] ) ? (float) wp_unslash( $_POST['kdna_event_loc_lat'] ) : 0.0;
		$lng     = isset( $_POST['kdna_event_loc_lng'] ) ? (float) wp_unslash( $_POST['kdna_event_loc_lng'] ) : 0.0;

		update_post_meta( $post_id, '_kdna_event_loc_address', $address );
		update_post_meta( $post_id, '_kdna_event_loc_lat', $lat );
		update_post_meta( $post_id, '_kdna_event_loc_lng', $lng );

		unset( $post );
	}

	/**
	 * Register the meta box on the Organiser CPT edit screen.
	 *
	 * @return void
	 */
	public static function register_organiser_meta_box() {
		add_meta_box(
			'kdna_events_organiser_details',
			__( 'Organiser Details', 'kdna-events' ),
			array( __CLASS__, 'render_organiser_meta_box' ),
			KDNA_Events_CPT::ORGANISER_POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the Organiser CPT meta box.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public static function render_organiser_meta_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION . '_organiser', self::NONCE_NAME );

		$email = (string) get_post_meta( $post->ID, '_kdna_event_org_email', true );
		$phone = (string) get_post_meta( $post->ID, '_kdna_event_org_phone', true );
		?>
		<div class="kdna-events-metabox">
			<table class="form-table" role="presentation">
				<tbody>
				<tr>
					<th scope="row"><label for="kdna_event_org_email"><?php esc_html_e( 'Email', 'kdna-events' ); ?></label></th>
					<td>
						<input type="email" class="regular-text" id="kdna_event_org_email" name="kdna_event_org_email" value="<?php echo esc_attr( $email ); ?>" />
						<p class="description"><?php esc_html_e( 'Optional. Receives organiser notifications when enabled in Settings.', 'kdna-events' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="kdna_event_org_phone"><?php esc_html_e( 'Phone', 'kdna-events' ); ?></label></th>
					<td><input type="text" class="regular-text" id="kdna_event_org_phone" name="kdna_event_org_phone" value="<?php echo esc_attr( $phone ); ?>" /></td>
				</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Save Organiser CPT meta.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function save_organiser_meta( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION . '_organiser' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$email = isset( $_POST['kdna_event_org_email'] ) ? sanitize_email( wp_unslash( $_POST['kdna_event_org_email'] ) ) : '';
		$phone = isset( $_POST['kdna_event_org_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['kdna_event_org_phone'] ) ) : '';

		update_post_meta( $post_id, '_kdna_event_org_email', $email );
		update_post_meta( $post_id, '_kdna_event_org_phone', $phone );

		unset( $post );
	}

	/**
	 * Coerce a datetime-local string to Y-m-d\TH:i format.
	 *
	 * Browsers submit datetime-local as Y-m-d\TH:i but older clients may
	 * add seconds. This strips them down to the canonical shape used
	 * across the plugin.
	 *
	 * @param string $raw Raw datetime value.
	 * @return string
	 */
	protected static function normalise_datetime( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}

		$raw = str_replace( ' ', 'T', $raw );

		if ( preg_match( '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})/', $raw, $m ) ) {
			return $m[1];
		}

		return '';
	}

	/**
	 * Enqueue admin scripts and styles on the event edit screen only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		$ours   = array(
			KDNA_Events_CPT::POST_TYPE,
			KDNA_Events_CPT::LOCATION_POST_TYPE,
			KDNA_Events_CPT::ORGANISER_POST_TYPE,
		);
		if ( ! $screen || ! in_array( $screen->post_type, $ours, true ) ) {
			return;
		}

		wp_enqueue_style(
			'kdna-events-admin',
			KDNA_EVENTS_URL . 'assets/css/kdna-events-admin.css',
			array(),
			KDNA_EVENTS_VERSION
		);

		wp_enqueue_script(
			'kdna-events-admin',
			KDNA_EVENTS_URL . 'assets/js/kdna-events-admin.js',
			array( 'jquery' ),
			KDNA_EVENTS_VERSION,
			true
		);
	}

	/**
	 * Register custom list columns on the events admin table.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function register_columns( $columns ) {
		$new = array();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['kdna_event_start']    = __( 'Start Date', 'kdna-events' );
				$new['kdna_event_type']     = __( 'Type', 'kdna-events' );
				$new['kdna_event_price']    = __( 'Price', 'kdna-events' );
				$new['kdna_event_sold']     = __( 'Tickets Sold', 'kdna-events' );
			}
		}

		return $new;
	}

	/**
	 * Render a custom column cell.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function render_column( $column, $post_id ) {
		switch ( $column ) {
			case 'kdna_event_start':
				$start = (string) get_post_meta( $post_id, '_kdna_event_start', true );
				if ( '' === $start ) {
					echo '<span aria-hidden="true">-</span>';
				} else {
					echo esc_html( kdna_events_format_datetime( $start, 'j M Y, H:i', $post_id ) );
				}
				break;

			case 'kdna_event_type':
				$type  = (string) get_post_meta( $post_id, '_kdna_event_type', true );
				$types = self::event_types();
				echo esc_html( isset( $types[ $type ] ) ? $types[ $type ] : '' );
				break;

			case 'kdna_event_price':
				if ( kdna_events_is_free( $post_id ) ) {
					echo esc_html__( 'Free', 'kdna-events' );
				} else {
					$price    = get_post_meta( $post_id, '_kdna_event_price', true );
					$currency = (string) get_post_meta( $post_id, '_kdna_event_currency', true );
					echo esc_html( kdna_events_format_price( $price, $currency ) );
				}
				break;

			case 'kdna_event_sold':
				$sold     = kdna_events_get_tickets_sold( $post_id );
				$capacity = (int) get_post_meta( $post_id, '_kdna_event_capacity', true );
				if ( $capacity > 0 ) {
					/* translators: 1: tickets sold, 2: total capacity */
					echo esc_html( sprintf( __( '%1$d / %2$d', 'kdna-events' ), $sold, $capacity ) );
				} else {
					echo esc_html( (string) $sold );
				}
				break;
		}
	}

	/**
	 * Mark the Start Date column as sortable.
	 *
	 * @param array $columns Sortable columns map.
	 * @return array
	 */
	public static function register_sortable_columns( $columns ) {
		$columns['kdna_event_start'] = 'kdna_event_start';
		return $columns;
	}

	/**
	 * Apply Start Date sorting when the admin list orders by it.
	 *
	 * @param WP_Query $query Current query.
	 * @return void
	 */
	public static function apply_sortable_orderby( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( KDNA_Events_CPT::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		if ( 'kdna_event_start' === $query->get( 'orderby' ) ) {
			$query->set( 'meta_key', '_kdna_event_start' );
			$query->set( 'orderby', 'meta_value' );
		}
	}
}
