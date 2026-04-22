<?php
/**
 * Utility helpers for the PDF Tickets add-on.
 *
 * Every add-on file stays within the kdna_events_pdf_ prefix to avoid
 * collisions with core's kdna_events_ namespace.
 *
 * @package KDNA_Events_PDF_Tickets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Absolute path to the temp directory for generated PDFs.
 *
 * @return string
 */
function kdna_events_pdf_tmp_dir() {
	$upload = wp_upload_dir();
	if ( ! empty( $upload['error'] ) ) {
		return '';
	}
	$dir = trailingslashit( $upload['basedir'] ) . 'kdna-events-pdf-tickets-tmp';
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	return $dir;
}

/**
 * HMAC secret shared by generate + verify token. Derived from WP
 * salt so it stays unique per install.
 *
 * @return string
 */
function kdna_events_pdf_token_secret() {
	return 'kdna-events-pdf-tickets|' . wp_salt( 'auth' );
}

/**
 * Generate a signed download token for a ticket code.
 *
 * Stateless, 24h lifetime. Embeds the expiry so verification is a
 * hash comparison with no DB round trip.
 *
 * @param string $ticket_code Ticket code.
 * @param int    $lifetime    Lifetime in seconds (default 24h).
 * @return string token as 'expires.hash'
 */
function kdna_events_pdf_generate_token( $ticket_code, $lifetime = 0 ) {
	$lifetime = $lifetime > 0 ? (int) $lifetime : DAY_IN_SECONDS;
	$expires  = time() + $lifetime;
	$payload  = (string) $ticket_code . '|' . $expires;
	$hash     = hash_hmac( 'sha256', $payload, kdna_events_pdf_token_secret() );
	return $expires . '.' . $hash;
}

/**
 * Verify a signed download token for a ticket code.
 *
 * @param string $ticket_code Ticket code.
 * @param string $token       Token string.
 * @return bool
 */
function kdna_events_pdf_verify_token( $ticket_code, $token ) {
	if ( ! is_string( $token ) || false === strpos( $token, '.' ) ) {
		return false;
	}
	list( $expires, $hash ) = array_pad( explode( '.', $token, 2 ), 2, '' );
	$expires = (int) $expires;
	if ( $expires <= 0 || $expires < time() ) {
		return false;
	}
	$expected = hash_hmac(
		'sha256',
		(string) $ticket_code . '|' . $expires,
		kdna_events_pdf_token_secret()
	);
	return hash_equals( $expected, (string) $hash );
}

/**
 * Get a setting with optional inheritance fallback to a core option.
 *
 * When the 'inherit_<key>' flag is on (the default), the helper
 * returns the matching core Email Design value. When the flag is
 * off, it returns the add-on's own override.
 *
 * @param string $option_name    Add-on option key (without prefix).
 * @param string $core_option    Core option key to fall back to.
 * @param mixed  $hard_default   Value to return when neither is set.
 * @return mixed
 */
function kdna_events_pdf_setting( $option_name, $core_option = '', $hard_default = '' ) {
	$own = get_option( 'kdna_events_pdf_' . $option_name, null );
	if ( '' !== $core_option ) {
		$inherit = (bool) get_option( 'kdna_events_pdf_inherit_' . $option_name, true );
		if ( $inherit ) {
			$core_value = get_option( $core_option, null );
			if ( null !== $core_value && '' !== $core_value ) {
				return $core_value;
			}
		}
	}
	if ( null === $own || '' === $own ) {
		return $hard_default;
	}
	return $own;
}

/**
 * Convenience: build the REST download URL for a ticket code,
 * optionally with a signed token suitable for guest buyers.
 *
 * @param string $ticket_code Ticket code.
 * @param bool   $signed      Append a signed token.
 * @return string
 */
function kdna_events_pdf_download_url( $ticket_code, $signed = true ) {
	$url = rest_url( 'kdna-events-pdf/v1/ticket/' . rawurlencode( (string) $ticket_code ) . '.pdf' );
	if ( $signed ) {
		$url = add_query_arg( 't', kdna_events_pdf_generate_token( $ticket_code ), $url );
	}
	return $url;
}
