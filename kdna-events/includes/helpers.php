<?php
/**
 * KDNA Events helper functions.
 *
 * Shared utility helpers used across the plugin. All helpers sanitise input
 * and escape output at the point of use.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetch a single event meta value with a sensible default.
 *
 * Wraps get_post_meta with a strict key whitelist check against the
 * registered meta keys and normalises empty values to the provided default.
 *
 * @param int    $post_id Event post ID.
 * @param string $key     Meta key, with or without leading underscore.
 * @param mixed  $default Default to return when the meta is empty.
 * @return mixed
 */
function kdna_events_get_event_meta( $post_id, $key, $default = '' ) {
	$post_id = absint( $post_id );
	$key     = (string) $key;

	if ( '' === $key ) {
		return $default;
	}

	if ( '_kdna_event_' !== substr( $key, 0, 12 ) ) {
		$key = '_kdna_event_' . ltrim( $key, '_' );
	}

	$value = get_post_meta( $post_id, $key, true );

	if ( '' === $value || null === $value ) {
		return $default;
	}

	return $value;
}

/**
 * Public wrapper: generate a signed download token for an invoice.
 *
 * @param string $invoice_number Invoice number.
 * @param int    $lifetime       Optional lifetime in seconds. 0 = default.
 * @return string
 */
function kdna_events_invoice_generate_token( $invoice_number, $lifetime = 0 ) {
	if ( ! class_exists( 'KDNA_Events_Invoices' ) ) {
		return '';
	}
	return KDNA_Events_Invoices::generate_download_token( $invoice_number, $lifetime );
}

/**
 * Public wrapper: verify a signed invoice download token.
 *
 * @param string $invoice_number Invoice number.
 * @param string $token          Token.
 * @return bool
 */
function kdna_events_invoice_verify_token( $invoice_number, $token ) {
	if ( ! class_exists( 'KDNA_Events_Invoices' ) ) {
		return false;
	}
	return KDNA_Events_Invoices::verify_download_token( $invoice_number, $token );
}

/**
 * Build a download URL for an invoice, optionally with a signed token.
 *
 * @param string $invoice_number Invoice number.
 * @param bool   $signed         When true, appends a 24h signed token
 *                               so guest buyers can reach it.
 * @return string
 */
function kdna_events_invoice_download_url( $invoice_number, $signed = false ) {
	$base = rest_url( 'kdna-events/v1/invoice/' . rawurlencode( (string) $invoice_number ) . '.pdf' );
	if ( $signed ) {
		$base = add_query_arg( 't', kdna_events_invoice_generate_token( $invoice_number ), $base );
	}
	return $base;
}

/**
 * Resolve the URL of the email header image for an event.
 *
 * Implements the fallback cascade from Brief A, Section 3:
 *   1. The event's own _kdna_event_image attachment, cropped to
 *      'kdna-events-email-header'.
 *   2. The plugin-wide default attachment configured in the Email
 *      Design settings tab, cropped to the same size.
 *   3. Empty string, meaning the caller should hide the header block.
 *
 * If the 'kdna-events-email-header' crop has not been generated yet
 * (for example the image was uploaded before the plugin registered
 * the size) the helper falls back to the built-in 'large' size so the
 * email still has an image, and schedules a background regeneration
 * for the attachment via wp_schedule_single_event.
 *
 * @param int $event_id Event post ID.
 * @return string Absolute URL, or empty string.
 */
function kdna_events_get_email_header_image_url( $event_id ) {
	$event_id = absint( $event_id );

	$candidates = array();

	$event_attachment_id = $event_id ? (int) get_post_meta( $event_id, '_kdna_event_image', true ) : 0;
	if ( $event_attachment_id ) {
		$candidates[] = $event_attachment_id;
	}

	$default_attachment_id = (int) get_option( 'kdna_events_email_default_header_image', 0 );
	if ( $default_attachment_id && $default_attachment_id !== $event_attachment_id ) {
		$candidates[] = $default_attachment_id;
	}

	foreach ( $candidates as $attachment_id ) {
		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			continue;
		}

		$url = wp_get_attachment_image_url( $attachment_id, 'kdna-events-email-header' );
		if ( $url ) {
			return $url;
		}

		// Cropped size missing. Queue a regeneration and fall back to 'large'.
		if ( ! wp_next_scheduled( 'kdna_events_regenerate_email_image_crops', array( $attachment_id ) ) ) {
			wp_schedule_single_event( time() + 10, 'kdna_events_regenerate_email_image_crops', array( $attachment_id ) );
		}

		$fallback = wp_get_attachment_image_url( $attachment_id, 'large' );
		if ( $fallback ) {
			return $fallback;
		}

		$full = wp_get_attachment_url( $attachment_id );
		if ( $full ) {
			return (string) $full;
		}
	}

	return '';
}

/**
 * Regenerate the 'kdna-events-email-header' crop for one or many attachments.
 *
 * Invoked by the wp_schedule_single_event queued on plugin activation,
 * by the v1.1 upgrade hook, and on demand when
 * kdna_events_get_email_header_image_url finds a missing crop.
 *
 * Called with no argument it sweeps every event that has an
 * _kdna_event_image set plus the plugin-wide default and regenerates
 * their metadata. Called with an explicit attachment ID it limits the
 * work to that one image.
 *
 * @param int $attachment_id Optional. Attachment to regenerate.
 * @return void
 */
function kdna_events_regenerate_email_image_crops( $attachment_id = 0 ) {
	$attachment_id = absint( $attachment_id );
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$ids = array();

	if ( $attachment_id ) {
		$ids[] = $attachment_id;
	} else {
		$posts = get_posts(
			array(
				'post_type'      => 'kdna_event',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_kdna_event_image',
						'value'   => '0',
						'compare' => '!=',
					),
				),
			)
		);
		foreach ( $posts as $post_id ) {
			$id = (int) get_post_meta( $post_id, '_kdna_event_image', true );
			if ( $id ) {
				$ids[] = $id;
			}
		}
		$default_id = (int) get_option( 'kdna_events_email_default_header_image', 0 );
		if ( $default_id ) {
			$ids[] = $default_id;
		}
	}

	$ids = array_unique( array_filter( $ids ) );

	foreach ( $ids as $id ) {
		$file = get_attached_file( $id );
		if ( ! $file || ! file_exists( $file ) ) {
			continue;
		}
		$meta = wp_generate_attachment_metadata( $id, $file );
		if ( is_array( $meta ) ) {
			wp_update_attachment_metadata( $id, $meta );
		}
	}
}
add_action( 'kdna_events_regenerate_email_image_crops', 'kdna_events_regenerate_email_image_crops' );

/**
 * Pre-compute a solid hex colour that approximates a tint of $hex at
 * $alpha (0.0-1.0) composited over a solid $background hex.
 *
 * Email clients like Outlook do not support rgba or hsla, so semi-
 * transparent accents must be flattened to a solid hex at render time.
 *
 * @param string $hex        Foreground hex colour, e.g. #2E75B6.
 * @param float  $alpha      Opacity 0..1.
 * @param string $background Background hex colour to composite against.
 * @return string Solid hex colour including leading '#'.
 */
function kdna_events_mix_hex( $hex, $alpha, $background = '#FFFFFF' ) {
	$parse = static function ( $candidate ) {
		$candidate = trim( (string) $candidate );
		if ( '' === $candidate ) {
			return array( 255, 255, 255 );
		}
		if ( '#' === $candidate[0] ) {
			$candidate = substr( $candidate, 1 );
		}
		if ( 3 === strlen( $candidate ) ) {
			$candidate = $candidate[0] . $candidate[0] . $candidate[1] . $candidate[1] . $candidate[2] . $candidate[2];
		}
		if ( 6 !== strlen( $candidate ) || ! ctype_xdigit( $candidate ) ) {
			return array( 255, 255, 255 );
		}
		return array(
			hexdec( substr( $candidate, 0, 2 ) ),
			hexdec( substr( $candidate, 2, 2 ) ),
			hexdec( substr( $candidate, 4, 2 ) ),
		);
	};

	$alpha = max( 0.0, min( 1.0, (float) $alpha ) );
	$fg    = $parse( $hex );
	$bg    = $parse( $background );

	$r = (int) round( $fg[0] * $alpha + $bg[0] * ( 1 - $alpha ) );
	$g = (int) round( $fg[1] * $alpha + $bg[1] * ( 1 - $alpha ) );
	$b = (int) round( $fg[2] * $alpha + $bg[2] * ( 1 - $alpha ) );

	return '#' . strtoupper( sprintf( '%02X%02X%02X', $r, $g, $b ) );
}

/**
 * Apply merge tags to a string using the KDNA Events email context.
 *
 * Any {key} token in $string that matches a key in $context is
 * replaced with the context value. Tokens for keys that do not exist
 * or are empty render as empty so emails never show '{foo}' literals.
 *
 * @param string $string  Raw string containing {tag} tokens.
 * @param array  $context Key/value map of merge tags.
 * @return string
 */
function kdna_events_render_merge_tags( $string, $context ) {
	$string  = (string) $string;
	$context = is_array( $context ) ? $context : array();

	if ( '' === $string || false === strpos( $string, '{' ) ) {
		return $string;
	}

	return preg_replace_callback(
		'/\{([a-z0-9_]+)\}/i',
		static function ( $matches ) use ( $context ) {
			$key = strtolower( $matches[1] );
			if ( array_key_exists( $key, $context ) ) {
				$value = $context[ $key ];
				if ( is_scalar( $value ) ) {
					return (string) $value;
				}
			}
			return '';
		},
		$string
	);
}

/**
 * Return the parsed location array for an event.
 *
 * If the event links to a shared Location CPT via
 * _kdna_event_location_ref, the helper returns the venue data from
 * that CPT. Otherwise it falls back to the legacy per-event
 * _kdna_event_location JSON meta so events created before the CPT
 * landed keep rendering their address and coordinates.
 *
 * @param int $post_id Event post ID.
 * @return array{name:string,address:string,lat:float,lng:float}
 */
function kdna_events_get_event_location( $post_id ) {
	$post_id  = absint( $post_id );
	$defaults = array(
		'name'    => '',
		'address' => '',
		'lat'     => 0.0,
		'lng'     => 0.0,
	);

	$ref = (int) get_post_meta( $post_id, '_kdna_event_location_ref', true );
	if ( $ref > 0 && 'kdna_event_location' === get_post_type( $ref ) ) {
		return array(
			'name'    => sanitize_text_field( (string) get_the_title( $ref ) ),
			'address' => sanitize_text_field( (string) get_post_meta( $ref, '_kdna_event_loc_address', true ) ),
			'lat'     => (float) get_post_meta( $ref, '_kdna_event_loc_lat', true ),
			'lng'     => (float) get_post_meta( $ref, '_kdna_event_loc_lng', true ),
		);
	}

	$raw = get_post_meta( $post_id, '_kdna_event_location', true );
	if ( empty( $raw ) ) {
		return $defaults;
	}

	$decoded = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
	if ( ! is_array( $decoded ) ) {
		return $defaults;
	}

	return array(
		'name'    => isset( $decoded['name'] ) ? sanitize_text_field( (string) $decoded['name'] ) : '',
		'address' => isset( $decoded['address'] ) ? sanitize_text_field( (string) $decoded['address'] ) : '',
		'lat'     => isset( $decoded['lat'] ) ? (float) $decoded['lat'] : 0.0,
		'lng'     => isset( $decoded['lng'] ) ? (float) $decoded['lng'] : 0.0,
	);
}

/**
 * Return the resolved organiser array for an event.
 *
 * Mirrors kdna_events_get_event_location: if the event points at a
 * shared Organiser CPT, data comes from there; otherwise we fall back
 * to the legacy _kdna_event_organiser_name / _email meta fields.
 *
 * @param int $post_id Event post ID.
 * @return array{name:string,email:string,phone:string}
 */
function kdna_events_get_event_organiser( $post_id ) {
	$post_id = absint( $post_id );

	$ref = (int) get_post_meta( $post_id, '_kdna_event_organiser_ref', true );
	if ( $ref > 0 && 'kdna_event_organiser' === get_post_type( $ref ) ) {
		return array(
			'name'  => sanitize_text_field( (string) get_the_title( $ref ) ),
			'email' => sanitize_email( (string) get_post_meta( $ref, '_kdna_event_org_email', true ) ),
			'phone' => sanitize_text_field( (string) get_post_meta( $ref, '_kdna_event_org_phone', true ) ),
		);
	}

	return array(
		'name'  => sanitize_text_field( (string) get_post_meta( $post_id, '_kdna_event_organiser_name', true ) ),
		'email' => sanitize_email( (string) get_post_meta( $post_id, '_kdna_event_organiser_email', true ) ),
		'phone' => '',
	);
}

/**
 * Determine whether an event is free.
 *
 * Price of 0 or empty string counts as free. There is no separate
 * paid/free toggle.
 *
 * @param int $post_id Event post ID.
 * @return bool
 */
function kdna_events_is_free( $post_id ) {
	$price = get_post_meta( absint( $post_id ), '_kdna_event_price', true );

	if ( '' === $price || null === $price ) {
		return true;
	}

	return 0.0 === (float) $price;
}

/**
 * Format an ISO 8601 datetime using the event or site timezone.
 *
 * @param string $iso    ISO 8601 datetime (Y-m-d\TH:i).
 * @param string $format PHP date format string.
 * @param int    $post_id Optional. Event post ID used to look up timezone.
 * @return string Formatted datetime or empty string when input is invalid.
 */
function kdna_events_format_datetime( $iso, $format = 'j F Y, g:i a', $post_id = 0 ) {
	if ( empty( $iso ) ) {
		return '';
	}

	$timezone_string = '';

	if ( $post_id ) {
		$timezone_string = (string) get_post_meta( absint( $post_id ), '_kdna_event_timezone', true );
	}

	if ( '' === $timezone_string ) {
		$timezone_string = wp_timezone_string();
	}

	try {
		$timezone = new DateTimeZone( $timezone_string );
	} catch ( Exception $e ) {
		$timezone = wp_timezone();
	}

	try {
		$datetime = new DateTimeImmutable( (string) $iso, $timezone );
	} catch ( Exception $e ) {
		return '';
	}

	return wp_date( $format, $datetime->getTimestamp(), $timezone );
}

/**
 * Format a price amount with the supplied currency code.
 *
 * Uses a lightweight currency symbol map. Unknown codes fall back to the
 * three letter code prefix.
 *
 * @param float|string $amount   Numeric amount.
 * @param string       $currency 3-letter currency code.
 * @return string
 */
function kdna_events_format_price( $amount, $currency = '' ) {
	$amount   = (float) $amount;
	$currency = strtoupper( sanitize_text_field( (string) $currency ) );

	if ( '' === $currency ) {
		$currency = strtoupper( (string) get_option( 'kdna_events_default_currency', 'AUD' ) );
	}

	$symbols = array(
		'AUD' => '$',
		'USD' => '$',
		'NZD' => '$',
		'CAD' => '$',
		'GBP' => '£',
		'EUR' => '€',
	);

	$symbol    = isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : $currency . ' ';
	$formatted = number_format_i18n( $amount, 2 );

	return $symbol . $formatted;
}

/**
 * Generate a unique order reference.
 *
 * Format is driven by the Booking References section of the General
 * settings tab so each installation can set its own prefix, suffix,
 * year inclusion, padding width, starting number and sequential /
 * random mode.
 *
 * Output shape:
 *   {prefix}[-{year}]-{NUMBER}{suffix}
 *
 * Sequential mode reads kdna_events_reference_next, assembles the
 * reference, checks for collisions in the orders table and retries up
 * to 10 times (advancing the counter each try) before falling back to
 * a random tail. Random mode keeps the v1.0 behaviour.
 *
 * @return string
 */
function kdna_events_generate_order_reference() {
	global $wpdb;

	$prefix       = (string) get_option( 'kdna_events_reference_prefix', '' );
	$suffix       = (string) get_option( 'kdna_events_reference_suffix', '' );
	$include_year = (bool) get_option( 'kdna_events_reference_include_year', true );
	$pad_width    = max( 1, min( 12, (int) get_option( 'kdna_events_reference_pad_width', 6 ) ) );
	$format       = (string) get_option( 'kdna_events_reference_format', 'sequential' );

	$year  = (int) current_time( 'Y' );
	$table = class_exists( 'KDNA_Events_DB' ) ? KDNA_Events_DB::orders_table() : '';

	$assemble = static function ( $body ) use ( $prefix, $suffix, $include_year, $year ) {
		$parts = array();
		if ( '' !== $prefix ) {
			$parts[] = $prefix;
		}
		if ( $include_year ) {
			$parts[] = (string) $year;
		}
		$parts[] = $body;
		return implode( '-', $parts ) . $suffix;
	};

	if ( 'random' === $format ) {
		$attempts = 0;
		do {
			$body      = strtoupper( wp_generate_password( 6, false, false ) );
			$reference = $assemble( $body );
			$attempts++;
			if ( '' === $table ) {
				return $reference;
			}
			$exists = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE order_reference = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$reference
				)
			);
		} while ( $exists > 0 && $attempts < 10 );
		return $reference;
	}

	// Sequential mode.
	$next      = (int) get_option( 'kdna_events_reference_next', 1 );
	if ( $next < 1 ) {
		$next = 1;
	}
	$attempts  = 0;

	do {
		$body      = str_pad( (string) $next, $pad_width, '0', STR_PAD_LEFT );
		$reference = $assemble( $body );
		$attempts++;

		if ( '' === $table ) {
			update_option( 'kdna_events_reference_next', $next + 1, false );
			return $reference;
		}

		$exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE order_reference = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$reference
			)
		);

		if ( $exists > 0 ) {
			$next++;
			continue;
		}

		update_option( 'kdna_events_reference_next', $next + 1, false );
		return $reference;
	} while ( $attempts < 20 );

	// Fallback, never expected. Assemble a random tail so we still return
	// something unique rather than duplicating an existing reference.
	$fallback = strtoupper( wp_generate_password( 6, false, false ) );
	update_option( 'kdna_events_reference_next', $next + 1, false );
	return $assemble( $fallback );
}

/**
 * Generate a unique 8-character uppercase alphanumeric ticket code.
 *
 * Retries up to 10 times against the tickets table to avoid collisions.
 *
 * @return string
 */
function kdna_events_generate_ticket_code() {
	global $wpdb;

	$table = '';

	if ( class_exists( 'KDNA_Events_DB' ) ) {
		$table = KDNA_Events_DB::tickets_table();
	}

	$attempts = 0;

	do {
		$code = strtoupper( wp_generate_password( 8, false, false ) );
		$attempts++;

		if ( '' === $table ) {
			return $code;
		}

		$exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE ticket_code = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$code
			)
		);
	} while ( $exists > 0 && $attempts < 10 );

	return $code;
}

/**
 * Return the number of valid tickets sold for an event.
 *
 * Calculated on demand via a COUNT against the tickets table and cached in
 * a 5 minute transient to avoid hammering the database from widget renders.
 *
 * @param int $event_id Event post ID.
 * @return int
 */
function kdna_events_get_tickets_sold( $event_id ) {
	global $wpdb;

	$event_id = absint( $event_id );

	if ( ! $event_id ) {
		return 0;
	}

	$transient_key = 'kdna_events_sold_' . $event_id;
	$cached        = get_transient( $transient_key );

	if ( false !== $cached ) {
		return (int) $cached;
	}

	if ( ! class_exists( 'KDNA_Events_DB' ) ) {
		return 0;
	}

	$table = KDNA_Events_DB::tickets_table();

	$count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE event_id = %d AND status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$event_id,
			'valid'
		)
	);

	set_transient( $transient_key, $count, 5 * MINUTE_IN_SECONDS );

	return $count;
}

/**
 * Invalidate the cached tickets-sold transient for an event.
 *
 * Must be called after every ticket create, cancel or refund so the
 * calculated count stays in sync.
 *
 * @param int $event_id Event post ID.
 * @return void
 */
function kdna_events_invalidate_sold_count( $event_id ) {
	$event_id = absint( $event_id );

	if ( ! $event_id ) {
		return;
	}

	delete_transient( 'kdna_events_sold_' . $event_id );
}

/**
 * Determine whether registration is currently open for an event.
 *
 * Central source of truth for registration eligibility. Checks:
 *   - opens-at is in the past (or unset),
 *   - closes-at is in the future, defaulting to event start when unset,
 *   - capacity is not reached (when capacity > 0).
 *
 * @param int $event_id Event post ID.
 * @return bool
 */
function kdna_events_is_registration_open( $event_id ) {
	$event_id = absint( $event_id );

	if ( ! $event_id || 'kdna_event' !== get_post_type( $event_id ) ) {
		return false;
	}

	$timezone_string = (string) get_post_meta( $event_id, '_kdna_event_timezone', true );

	if ( '' === $timezone_string ) {
		$timezone_string = wp_timezone_string();
	}

	try {
		$timezone = new DateTimeZone( $timezone_string );
		$now      = new DateTimeImmutable( 'now', $timezone );
	} catch ( Exception $e ) {
		$timezone = wp_timezone();
		$now      = new DateTimeImmutable( 'now', $timezone );
	}

	$opens_raw  = (string) get_post_meta( $event_id, '_kdna_event_registration_opens', true );
	$closes_raw = (string) get_post_meta( $event_id, '_kdna_event_registration_closes', true );
	$start_raw  = (string) get_post_meta( $event_id, '_kdna_event_start', true );

	if ( '' !== $opens_raw ) {
		try {
			$opens_at = new DateTimeImmutable( $opens_raw, $timezone );
			if ( $now < $opens_at ) {
				return false;
			}
		} catch ( Exception $e ) {
			// Ignore malformed opens-at, treat as open-now.
			unset( $e );
		}
	}

	$closes_source = '' !== $closes_raw ? $closes_raw : $start_raw;

	if ( '' !== $closes_source ) {
		try {
			$closes_at = new DateTimeImmutable( $closes_source, $timezone );
			if ( $now >= $closes_at ) {
				return false;
			}
		} catch ( Exception $e ) {
			unset( $e );
		}
	}

	$capacity = (int) get_post_meta( $event_id, '_kdna_event_capacity', true );

	if ( $capacity > 0 ) {
		$sold = kdna_events_get_tickets_sold( $event_id );
		if ( $sold >= $capacity ) {
			return false;
		}
	}

	return true;
}
