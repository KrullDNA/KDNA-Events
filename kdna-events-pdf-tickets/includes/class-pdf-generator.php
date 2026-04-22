<?php
/**
 * PDF ticket generator for the KDNA Events PDF Tickets add-on.
 *
 * Reads data from core's ticket + order tables via read-only $wpdb
 * queries and renders one ticket per A4 page via Dompdf.
 *
 * @package KDNA_Events_PDF_Tickets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dompdf-backed ticket PDF generator.
 */
class KDNA_Events_PDF_Generator {

	/**
	 * Generate a combined PDF for every ticket in an order.
	 *
	 * @param int $order_id Order ID.
	 * @return string PDF binary or empty string on error.
	 */
	public function generate_order_tickets( $order_id ) {
		$order = $this->fetch_order( (int) $order_id );
		if ( ! $order ) {
			return '';
		}
		$tickets = $this->fetch_tickets_for_order( (int) $order_id );
		if ( empty( $tickets ) ) {
			return '';
		}
		$context = $this->build_context_for_order( $order, $tickets );
		$html    = $this->render_html( $context );
		return $this->render_pdf( $html );
	}

	/**
	 * Generate a PDF for a single ticket.
	 *
	 * @param int $ticket_id Ticket ID.
	 * @return string PDF binary or empty string on error.
	 */
	public function generate_ticket( $ticket_id ) {
		$ticket = $this->fetch_ticket( (int) $ticket_id );
		if ( ! $ticket ) {
			return '';
		}
		$order = $this->fetch_order( (int) $ticket->order_id );
		if ( ! $order ) {
			return '';
		}
		$context = $this->build_context_for_order( $order, array( $ticket ) );
		$html    = $this->render_html( $context );
		return $this->render_pdf( $html );
	}

	/**
	 * Generate a sample PDF using dummy data, for the settings preview.
	 *
	 * @return string
	 */
	public function generate_sample() {
		$order = (object) array(
			'order_id'        => 0,
			'order_reference' => 'PREVIEW-0001',
			'event_id'        => 0,
			'purchaser_name'  => __( 'Jane Doe', 'kdna-events-pdf-tickets' ),
			'purchaser_email' => 'jane@example.com',
			'purchaser_phone' => '+61 400 123 456',
			'total'           => 199.00,
			'currency'        => 'AUD',
			'status'          => 'paid',
			'created_at'      => current_time( 'mysql' ),
		);
		$tickets = array(
			(object) array(
				'ticket_id'      => 0,
				'ticket_code'    => 'ABCD1234',
				'attendee_name'  => __( 'Jane Doe', 'kdna-events-pdf-tickets' ),
				'attendee_email' => 'jane@example.com',
				'event_id'       => 0,
			),
			(object) array(
				'ticket_id'      => 0,
				'ticket_code'    => 'EFGH5678',
				'attendee_name'  => __( 'John Smith', 'kdna-events-pdf-tickets' ),
				'attendee_email' => 'john@example.com',
				'event_id'       => 0,
			),
		);
		$context = $this->build_context_for_order( $order, $tickets );
		$context['is_sample'] = true;
		$html    = $this->render_html( $context );
		return $this->render_pdf( $html );
	}

	/**
	 * Save a PDF binary to the temp directory. Filename includes a
	 * short random suffix so the path can be safely shared over email
	 * without revealing a guessable URL.
	 *
	 * @param string $pdf_content PDF binary.
	 * @param string $basename    Base filename (no extension).
	 * @return string Absolute path, or empty string on error.
	 */
	public function save_to_temp( $pdf_content, $basename ) {
		$dir = kdna_events_pdf_tmp_dir();
		if ( '' === $dir ) {
			return '';
		}
		$slug  = sanitize_title( (string) $basename );
		$token = wp_generate_password( 10, false, false );
		$path  = $dir . '/' . 'kdna-pdf-' . ( '' === $slug ? 'ticket' : $slug ) . '-' . $token . '.pdf';
		$ok    = @file_put_contents( $path, $pdf_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,Generic.PHP.NoSilencedErrors.Discouraged
		return false === $ok ? '' : $path;
	}

	/**
	 * Fetch an order row via core's DB helper.
	 *
	 * @param int $order_id
	 * @return object|null
	 */
	protected function fetch_order( $order_id ) {
		if ( class_exists( 'KDNA_Events_Orders' ) ) {
			return KDNA_Events_Orders::get_order( (int) $order_id );
		}
		global $wpdb;
		if ( ! class_exists( 'KDNA_Events_DB' ) ) {
			return null;
		}
		$table = KDNA_Events_DB::orders_table();
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $order_id
			)
		);
	}

	/**
	 * Fetch tickets for an order.
	 *
	 * @param int $order_id
	 * @return array<int,object>
	 */
	protected function fetch_tickets_for_order( $order_id ) {
		if ( class_exists( 'KDNA_Events_Tickets' ) ) {
			$rows = KDNA_Events_Tickets::get_tickets_for_order( (int) $order_id );
			return is_array( $rows ) ? $rows : array();
		}
		global $wpdb;
		if ( ! class_exists( 'KDNA_Events_DB' ) ) {
			return array();
		}
		$table = KDNA_Events_DB::tickets_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d ORDER BY ticket_id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $order_id
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Fetch a single ticket row.
	 *
	 * @param int $ticket_id
	 * @return object|null
	 */
	protected function fetch_ticket( $ticket_id ) {
		global $wpdb;
		if ( ! class_exists( 'KDNA_Events_DB' ) ) {
			return null;
		}
		$table = KDNA_Events_DB::tickets_table();
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE ticket_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $ticket_id
			)
		);
	}

	/**
	 * Build the render context for one order + its tickets.
	 *
	 * @param object $order
	 * @param array  $tickets
	 * @return array
	 */
	public function build_context_for_order( $order, $tickets ) {
		$event_id       = (int) ( $order->event_id ?? 0 );
		$event_title    = $event_id ? (string) get_the_title( $event_id ) : __( 'Sample event', 'kdna-events-pdf-tickets' );
		$event_subtitle = $event_id ? (string) get_post_meta( $event_id, '_kdna_event_subtitle', true ) : __( 'A brand new evening with us', 'kdna-events-pdf-tickets' );
		$event_start    = $event_id ? (string) get_post_meta( $event_id, '_kdna_event_start', true ) : '';
		$event_type     = $event_id ? (string) get_post_meta( $event_id, '_kdna_event_type', true ) : 'in-person';
		$virtual_url    = $event_id ? (string) get_post_meta( $event_id, '_kdna_event_virtual_url', true ) : '';

		$event_date = '' !== $event_start && function_exists( 'kdna_events_format_datetime' )
			? kdna_events_format_datetime( $event_start, 'j F Y', $event_id )
			: __( 'Sample date', 'kdna-events-pdf-tickets' );
		$event_time = '' !== $event_start && function_exists( 'kdna_events_format_datetime' )
			? kdna_events_format_datetime( $event_start, 'g:i a', $event_id )
			: '';

		$location = function_exists( 'kdna_events_get_event_location' ) && $event_id
			? kdna_events_get_event_location( $event_id )
			: array( 'name' => __( 'Sample venue', 'kdna-events-pdf-tickets' ), 'address' => __( '123 Sample St', 'kdna-events-pdf-tickets' ), 'lat' => 0.0, 'lng' => 0.0 );
		$location_parts = array_filter( array( $location['name'], $location['address'] ), static function ( $v ) { return '' !== $v; } );
		$location_str   = implode( ', ', $location_parts );

		$organiser = function_exists( 'kdna_events_get_event_organiser' ) && $event_id
			? kdna_events_get_event_organiser( $event_id )
			: array( 'name' => '', 'email' => '', 'phone' => '' );

		$header_image_url = function_exists( 'kdna_events_get_email_header_image_url' ) && $event_id
			? kdna_events_get_email_header_image_url( $event_id )
			: '';

		return array(
			'order'            => $order,
			'tickets'          => $tickets,
			'event_id'         => $event_id,
			'event_title'      => $event_title,
			'event_subtitle'   => $event_subtitle,
			'event_date'       => $event_date,
			'event_time'       => $event_time,
			'event_type'       => $event_type,
			'event_location'   => $location_str,
			'event_location_arr' => $location,
			'virtual_url'      => $virtual_url,
			'organiser'        => $organiser,
			'header_image_url' => $header_image_url,
			'design'           => $this->get_design_tokens(),
		);
	}

	/**
	 * Resolve the design tokens, honouring Inherit toggles.
	 *
	 * @return array
	 */
	public function get_design_tokens() {
		$accent  = kdna_events_pdf_setting( 'color_accent', 'kdna_events_email_color_accent', '#F07759' );
		$primary = kdna_events_pdf_setting( 'color_primary', 'kdna_events_email_color_primary', '#2E75B6' );

		return array(
			'logo_id'         => (int) kdna_events_pdf_setting( 'logo_id', 'kdna_events_email_logo_id', 0 ),
			'logo_width'      => (int) get_option( 'kdna_events_pdf_logo_width', 140 ),
			'logo_align'      => (string) get_option( 'kdna_events_pdf_logo_align', 'center' ),
			'header_bg'       => (string) kdna_events_pdf_setting( 'header_bg', 'kdna_events_email_color_primary', $primary ),
			'header_height'   => (int) get_option( 'kdna_events_pdf_header_height', 100 ),
			'default_image'   => (int) kdna_events_pdf_setting( 'default_event_image', 'kdna_events_email_default_header_image', 0 ),
			'primary'         => $primary,
			'accent'          => $accent,
			'accent_soft'     => $this->mix_hex( $accent, 0.15, '#FFFFFF' ),
			'page_bg'         => (string) get_option( 'kdna_events_pdf_page_bg', '#FFFFFF' ),
			'heading_color'   => (string) get_option( 'kdna_events_pdf_heading_color', '#1A1A1A' ),
			'body_color'      => (string) get_option( 'kdna_events_pdf_body_color', '#333333' ),
			'muted_color'     => (string) get_option( 'kdna_events_pdf_muted_color', '#888888' ),
			'divider_color'   => (string) get_option( 'kdna_events_pdf_divider_color', '#E5E5E5' ),
			'heading_font'    => (string) get_option( 'kdna_events_pdf_heading_font', 'helvetica' ),
			'heading_size'    => (int) get_option( 'kdna_events_pdf_heading_size', 20 ),
			'body_font'       => (string) get_option( 'kdna_events_pdf_body_font', 'helvetica' ),
			'body_size'       => (int) get_option( 'kdna_events_pdf_body_size', 11 ),
			'code_font'       => (string) get_option( 'kdna_events_pdf_code_font', 'courier' ),
			'code_size'       => (int) get_option( 'kdna_events_pdf_code_size', 36 ),
			'barcode_width'   => (int) get_option( 'kdna_events_pdf_barcode_width', 80 ),
			'barcode_height'  => (int) get_option( 'kdna_events_pdf_barcode_height', 20 ),
			'barcode_show_text' => (bool) get_option( 'kdna_events_pdf_barcode_show_text', true ),
			'page_size'       => (string) get_option( 'kdna_events_pdf_page_size', 'A4' ),
			'page_orientation' => (string) get_option( 'kdna_events_pdf_page_orientation', 'portrait' ),
			'page_margin'     => (int) get_option( 'kdna_events_pdf_page_margin', 15 ),
			'combined_mode'   => 'separate' !== (string) get_option( 'kdna_events_pdf_tickets_per_order', 'combined' ),
			'show_footer'     => (bool) get_option( 'kdna_events_pdf_show_footer', true ),
			'business_name'   => (string) kdna_events_pdf_setting( 'business_name', 'kdna_events_email_footer_business_name', (string) get_bloginfo( 'name' ) ),
			'website_url'     => (string) get_option( 'kdna_events_pdf_website_url', home_url( '/' ) ),
			'support_email'   => (string) kdna_events_pdf_setting( 'support_email', 'kdna_events_email_from_address', (string) get_option( 'admin_email' ) ),
			'support_phone'   => (string) get_option( 'kdna_events_pdf_support_phone', '' ),
			'show_timestamp'  => (bool) get_option( 'kdna_events_pdf_show_timestamp', false ),
			'terms_text'      => (string) get_option( 'kdna_events_pdf_terms_text', '' ),
		);
	}

	/**
	 * Composite a hex colour at an alpha over a solid background, so
	 * we have an rgba-like tint without rgba (Dompdf handles rgba 2.x
	 * but PDF itself is best expressed with solid colours for ticket
	 * code background chips).
	 *
	 * @param string $hex
	 * @param float  $alpha
	 * @param string $bg
	 * @return string
	 */
	protected function mix_hex( $hex, $alpha, $bg = '#FFFFFF' ) {
		$parse = static function ( $candidate ) {
			$c = ltrim( trim( (string) $candidate ), '#' );
			if ( 3 === strlen( $c ) ) {
				$c = $c[0] . $c[0] . $c[1] . $c[1] . $c[2] . $c[2];
			}
			if ( 6 !== strlen( $c ) || ! ctype_xdigit( $c ) ) {
				return array( 255, 255, 255 );
			}
			return array( hexdec( substr( $c, 0, 2 ) ), hexdec( substr( $c, 2, 2 ) ), hexdec( substr( $c, 4, 2 ) ) );
		};
		$alpha = max( 0.0, min( 1.0, (float) $alpha ) );
		$fg    = $parse( $hex );
		$bgRgb = $parse( $bg );
		$r = (int) round( $fg[0] * $alpha + $bgRgb[0] * ( 1 - $alpha ) );
		$g = (int) round( $fg[1] * $alpha + $bgRgb[1] * ( 1 - $alpha ) );
		$b = (int) round( $fg[2] * $alpha + $bgRgb[2] * ( 1 - $alpha ) );
		return '#' . strtoupper( sprintf( '%02X%02X%02X', $r, $g, $b ) );
	}

	/**
	 * Render the ticket template into HTML.
	 *
	 * @param array $context
	 * @return string
	 */
	public function render_html( $context ) {
		ob_start();
		$context_var = $context; // phpcs:ignore
		$data        = $context_var;
		include KDNA_EVENTS_PDF_PLUGIN_DIR . 'templates/ticket.php';
		return (string) ob_get_clean();
	}

	/**
	 * Render HTML to PDF binary via Dompdf.
	 *
	 * @param string $html
	 * @return string PDF binary, or empty string on error.
	 */
	public function render_pdf( $html ) {
		if ( ! class_exists( '\\Dompdf\\Dompdf' ) ) {
			return '';
		}
		$page_size = (string) get_option( 'kdna_events_pdf_page_size', 'A4' );
		if ( ! in_array( $page_size, array( 'A4', 'Letter' ), true ) ) {
			$page_size = 'A4';
		}
		$orientation = (string) get_option( 'kdna_events_pdf_page_orientation', 'portrait' );
		if ( ! in_array( $orientation, array( 'portrait', 'landscape' ), true ) ) {
			$orientation = 'portrait';
		}

		$options = new \Dompdf\Options();
		$options->set( 'isRemoteEnabled', true );
		$options->set( 'isHtml5ParserEnabled', true );
		$options->set( 'defaultFont', 'helvetica' );

		$dompdf = new \Dompdf\Dompdf( $options );
		$dompdf->setPaper( $page_size, $orientation );
		$dompdf->loadHtml( $html, 'UTF-8' );
		$dompdf->render();
		return (string) $dompdf->output();
	}
}
