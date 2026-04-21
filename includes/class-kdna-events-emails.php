<?php
/**
 * Email notifications for KDNA Events.
 *
 * Templated HTML emails with merge-tag handling. Replaces the Stage 8
 * wp_mail stubs on KDNA_Events_Orders with a proper rendering pipeline:
 *
 *   1. Apply merge tags ({order_ref}, {event_title}, {event_date},
 *      {attendee_name}, {ticket_code}, {event_location},
 *      {organiser_name}) to the admin-configured body template.
 *   2. Wrap the body in templates/emails/booking-confirmation.php or
 *      admin-notification.php for structured HTML delivery.
 *   3. Send via wp_mail with the From name and address configured in
 *      Settings, falling back to WordPress defaults when unset.
 *
 * Send policies:
 *   - send_booking_confirmation always emails the purchaser. If the
 *     kdna_events_per_attendee_emails option is on, each attendee also
 *     receives a personalised copy with their own code.
 *   - send_admin_notification emails the admin notification address.
 *     When kdna_events_notify_organiser is on and the event carries an
 *     organiser email, the organiser receives a separate mail, not a
 *     cc, so internal addresses never leak across bookings.
 *
 * Also exposes an AJAX test-send endpoint wired to the 'Send test email'
 * button on the Settings page so site owners can verify SMTP without
 * completing a full booking.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email sender + template renderer.
 */
class KDNA_Events_Emails {

	/**
	 * AJAX action for the Send test email button.
	 */
	const TEST_AJAX_ACTION = 'kdna_events_send_test_email';

	/**
	 * Guard so init is idempotent.
	 *
	 * @var bool
	 */
	protected static $booted = false;

	/**
	 * Register hooks. Hooked once only.
	 *
	 * @return void
	 */
	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action( 'wp_ajax_' . self::TEST_AJAX_ACTION, array( __CLASS__, 'ajax_send_test' ) );
	}

	/**
	 * Send the purchaser (and optionally per-attendee) booking confirmation emails.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function send_booking_confirmation( $order_id ) {
		$order = KDNA_Events_Orders::get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$tickets = KDNA_Events_Tickets::get_tickets_for_order( (int) $order->order_id );

		// Purchaser email: one mail with the full ticket list.
		$purchaser_context = self::build_purchaser_context( $order, $tickets );
		$body_html         = self::render_body( $purchaser_context );
		$data              = self::build_template_data( $order, $tickets, $purchaser_context, $body_html, 'purchaser' );

		$html = self::load_template( 'booking-confirmation', $data );
		if ( '' === $html ) {
			return;
		}

		$subject = sprintf(
			/* translators: 1: order reference, 2: event title */
			__( 'Your booking %1$s is confirmed for %2$s', 'kdna-events' ),
			$order->order_reference,
			$purchaser_context['event_title']
		);

		self::send_mail( $order->purchaser_email, $subject, $html );

		// Per-attendee emails (optional).
		if ( ! get_option( 'kdna_events_per_attendee_emails', false ) ) {
			return;
		}

		foreach ( $tickets as $ticket ) {
			$email = (string) $ticket->attendee_email;
			if ( '' === $email || ! is_email( $email ) ) {
				continue;
			}
			// Skip duplicate when the attendee is also the purchaser.
			if ( strcasecmp( $email, (string) $order->purchaser_email ) === 0 && 1 === count( $tickets ) ) {
				continue;
			}

			$attendee_context = self::build_attendee_context( $order, $ticket, $purchaser_context );
			$attendee_body    = self::render_body( $attendee_context );
			$attendee_data    = self::build_template_data( $order, array( $ticket ), $attendee_context, $attendee_body, 'attendee' );

			$attendee_html = self::load_template( 'booking-confirmation', $attendee_data );
			if ( '' === $attendee_html ) {
				continue;
			}

			$attendee_subject = sprintf(
				/* translators: 1: event title, 2: attendee name */
				__( 'Your ticket for %1$s, %2$s', 'kdna-events' ),
				$attendee_context['event_title'],
				$attendee_context['attendee_name']
			);

			self::send_mail( $email, $attendee_subject, $attendee_html );
		}
	}

	/**
	 * Send admin and optional organiser notification.
	 *
	 * Sends the organiser copy as a separate email (not cc) so internal
	 * addresses never appear in the admin's headers and vice versa.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function send_admin_notification( $order_id ) {
		$order = KDNA_Events_Orders::get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$tickets       = KDNA_Events_Tickets::get_tickets_for_order( (int) $order->order_id );
		$admin_email   = (string) get_option( 'kdna_events_admin_notification_email', '' );
		$notify_org    = (bool) get_option( 'kdna_events_notify_organiser', false );
		$organiser_em  = (string) get_post_meta( (int) $order->event_id, '_kdna_event_organiser_email', true );

		$context = self::build_purchaser_context( $order, $tickets );
		$data    = self::build_template_data( $order, $tickets, $context, '', 'admin' );

		$html = self::load_template( 'admin-notification', $data );
		if ( '' === $html ) {
			return;
		}

		$subject = sprintf(
			/* translators: 1: order reference, 2: event title */
			__( 'New KDNA Events booking: %1$s for %2$s', 'kdna-events' ),
			$order->order_reference,
			$context['event_title']
		);

		if ( '' !== $admin_email ) {
			self::send_mail( $admin_email, $subject, $html );
		}

		// Organiser copy goes out in a separate mail so recipients never
		// see each other's addresses.
		if ( $notify_org && '' !== $organiser_em && 0 !== strcasecmp( $organiser_em, $admin_email ) ) {
			self::send_mail( $organiser_em, $subject, $html );
		}
	}

	/**
	 * AJAX handler behind the Settings 'Send test email' button.
	 *
	 * @return void
	 */
	public static function ajax_send_test() {
		check_ajax_referer( 'kdna_events_test_email', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kdna-events' ) ), 403 );
		}

		$admin_email = (string) get_option( 'kdna_events_admin_notification_email', '' );
		if ( '' === $admin_email || ! is_email( $admin_email ) ) {
			wp_send_json_error( array( 'message' => __( 'Set the admin notification email first.', 'kdna-events' ) ), 400 );
		}

		$data = self::build_demo_template_data();
		$html = self::load_template( 'admin-notification', $data );
		if ( '' === $html ) {
			wp_send_json_error( array( 'message' => __( 'Email template missing.', 'kdna-events' ) ), 500 );
		}

		$subject = __( 'KDNA Events test email', 'kdna-events' );
		$sent    = self::send_mail( $admin_email, $subject, $html );

		if ( $sent ) {
			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: %s: admin email */
						__( 'Test email sent to %s.', 'kdna-events' ),
						$admin_email
					),
				)
			);
		}
		wp_send_json_error( array( 'message' => __( 'wp_mail returned false. Check your SMTP config.', 'kdna-events' ) ), 500 );
	}

	/**
	 * Build a merge-tag context for the purchaser email.
	 *
	 * @param object                 $order   Order row.
	 * @param array<int,object>      $tickets Tickets for the order.
	 * @return array<string,string>
	 */
	protected static function build_purchaser_context( $order, $tickets ) {
		$event_title     = (string) get_the_title( (int) $order->event_id );
		$event_start_raw = (string) get_post_meta( (int) $order->event_id, '_kdna_event_start', true );
		$event_date      = '' === $event_start_raw ? '' : kdna_events_format_datetime( $event_start_raw, 'j F Y, g:i a', (int) $order->event_id );
		$organiser_name  = (string) get_post_meta( (int) $order->event_id, '_kdna_event_organiser_name', true );

		$location = kdna_events_get_event_location( (int) $order->event_id );
		$parts    = array_filter( array( $location['name'], $location['address'] ), static function ( $v ) { return '' !== $v; } );
		if ( empty( $parts ) ) {
			$virtual = (string) get_post_meta( (int) $order->event_id, '_kdna_event_virtual_url', true );
			if ( '' !== $virtual ) {
				$parts[] = $virtual;
			}
		}
		$location_str = implode( ', ', $parts );

		$codes = array();
		foreach ( $tickets as $ticket ) {
			$codes[] = $ticket->ticket_code;
		}

		return array(
			'order_ref'      => (string) $order->order_reference,
			'event_title'    => $event_title,
			'event_date'     => $event_date,
			'attendee_name'  => (string) $order->purchaser_name,
			'ticket_code'    => implode( ', ', $codes ),
			'event_location' => $location_str,
			'organiser_name' => $organiser_name,
		);
	}

	/**
	 * Per-attendee merge-tag context, derived from the purchaser one.
	 *
	 * @param object                $order             Order row.
	 * @param object                $ticket            Ticket row.
	 * @param array<string,string>  $purchaser_context Shared context.
	 * @return array<string,string>
	 */
	protected static function build_attendee_context( $order, $ticket, $purchaser_context ) {
		$ctx                   = $purchaser_context;
		$ctx['attendee_name']  = (string) $ticket->attendee_name;
		$ctx['ticket_code']    = (string) $ticket->ticket_code;
		unset( $order );
		return $ctx;
	}

	/**
	 * Apply merge tags to the admin-configured body template.
	 *
	 * Converts the resulting plain text into HTML with line breaks so
	 * it can be dropped into the email template without further work.
	 *
	 * @param array<string,string> $context Merge-tag values.
	 * @return string
	 */
	protected static function render_body( $context ) {
		$template = (string) get_option( 'kdna_events_booking_email_body', '' );
		if ( '' === $template ) {
			$template = KDNA_Events_Settings::default_booking_email_body();
		}

		$map = array();
		foreach ( $context as $key => $value ) {
			$map[ '{' . $key . '}' ] = (string) $value;
		}
		$plain = strtr( $template, $map );
		$escaped = esc_html( $plain );
		return nl2br( $escaped );
	}

	/**
	 * Assemble the template data array for a rendered email.
	 *
	 * @param object               $order     Order row.
	 * @param array<int,object>    $tickets   Tickets.
	 * @param array<string,string> $context   Merge-tag context.
	 * @param string               $body_html Pre-rendered HTML body.
	 * @param string               $role      'purchaser' | 'attendee' | 'admin'.
	 * @return array
	 */
	protected static function build_template_data( $order, $tickets, $context, $body_html, $role ) {
		$support_email = (string) get_option( 'kdna_events_email_from_address', '' );
		if ( '' === $support_email ) {
			$support_email = (string) get_option( 'admin_email', '' );
		}

		$price = isset( $order->total ) ? (float) $order->total : 0.0;
		$currency = isset( $order->currency ) ? (string) $order->currency : (string) get_option( 'kdna_events_default_currency', 'AUD' );

		return array(
			'order'         => $order,
			'tickets'       => $tickets,
			'context'       => $context,
			'body_html'     => $body_html,
			'role'          => $role,
			'site_name'     => (string) get_bloginfo( 'name' ),
			'site_url'      => home_url( '/' ),
			'support_email' => $support_email,
			'total_display' => $price <= 0 ? __( 'Free', 'kdna-events' ) : kdna_events_format_price( $price, $currency ),
			'currency'      => $currency,
		);
	}

	/**
	 * Build demo template data for the Send test email button.
	 *
	 * @return array
	 */
	protected static function build_demo_template_data() {
		$now = current_time( 'Y' );
		$order = (object) array(
			'order_id'        => 0,
			'order_reference' => sprintf( 'KDNA-EV-%s-DEMO', $now ),
			'event_id'        => 0,
			'purchaser_name'  => __( 'Jane Doe', 'kdna-events' ),
			'purchaser_email' => (string) get_option( 'kdna_events_admin_notification_email', '' ),
			'purchaser_phone' => '',
			'quantity'        => 2,
			'subtotal'        => 0,
			'total'           => 0,
			'currency'        => (string) get_option( 'kdna_events_default_currency', 'AUD' ),
			'status'          => 'paid',
		);

		$tickets = array(
			(object) array( 'ticket_code' => 'ABCD1234', 'attendee_name' => __( 'Jane Doe', 'kdna-events' ), 'attendee_email' => '' ),
			(object) array( 'ticket_code' => 'EFGH5678', 'attendee_name' => __( 'John Smith', 'kdna-events' ), 'attendee_email' => '' ),
		);

		$context = array(
			'order_ref'      => $order->order_reference,
			'event_title'    => __( 'Sample Event', 'kdna-events' ),
			'event_date'     => kdna_events_format_datetime( current_time( 'Y-m-d\TH:i' ), 'j F Y, g:i a' ),
			'attendee_name'  => $order->purchaser_name,
			'ticket_code'    => 'ABCD1234, EFGH5678',
			'event_location' => __( 'Sample Venue, 123 Example Street', 'kdna-events' ),
			'organiser_name' => __( 'Event Organiser', 'kdna-events' ),
		);

		$body_html = self::render_body( $context );

		return self::build_template_data( $order, $tickets, $context, $body_html, 'admin' );
	}

	/**
	 * Include an email template and return its HTML string.
	 *
	 * Themes can override either template by copying the shipped file
	 * into their own tree and filtering `kdna_events_email_template_path`
	 * to point at the copy.
	 *
	 * @param string $name Template base name (no extension).
	 * @param array  $data Variables available inside the template.
	 * @return string
	 */
	protected static function load_template( $name, $data ) {
		$default = KDNA_EVENTS_PATH . 'templates/emails/' . $name . '.php';

		/**
		 * Filter the resolved absolute path to an email template.
		 *
		 * @param string $default Absolute default path.
		 * @param string $name    Template name.
		 */
		$path = (string) apply_filters( 'kdna_events_email_template_path', $default, $name );
		if ( ! file_exists( $path ) ) {
			$path = $default;
		}
		if ( ! file_exists( $path ) ) {
			return '';
		}
		ob_start();
		extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		include $path;
		return (string) ob_get_clean();
	}

	/**
	 * Send HTML email via wp_mail using the configured From identity.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param string $html    HTML body.
	 * @return bool
	 */
	protected static function send_mail( $to, $subject, $html ) {
		$from_name    = (string) get_option( 'kdna_events_email_from_name', '' );
		$from_address = (string) get_option( 'kdna_events_email_from_address', '' );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( '' !== $from_name && '' !== $from_address ) {
			$headers[] = sprintf( 'From: %s <%s>', $from_name, $from_address );
		} elseif ( '' !== $from_address ) {
			$headers[] = sprintf( 'From: <%s>', $from_address );
		}

		return (bool) wp_mail( $to, $subject, $html, $headers );
	}
}
