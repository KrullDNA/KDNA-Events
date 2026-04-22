<?php
/**
 * Email notifications for KDNA Events.
 *
 * v1.1 Brief A rebuilds the send pipeline on top of properly designed
 * HTML templates + inlined CSS via pelago/emogrifier. Templates live
 * under templates/emails/ and the stylesheet under
 * templates/emails/css/email.css. The class builds a merge-tag context,
 * renders the template, inlines CSS, derives a plain-text fallback,
 * and sends via wp_mail as multipart/alternative.
 *
 * AJAX endpoints:
 *   - kdna_events_send_test_email (v1.0, now uses new template)
 *   - kdna_events_preview_email (v1.1, renders with dummy data)
 *   - kdna_events_preview_test_send (v1.1, sends current settings to an inbox)
 *
 * Extension hooks added in v1.1 Brief A:
 *   - filter kdna_events_email_attachments applied just before wp_mail
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

	const TEST_AJAX_ACTION        = 'kdna_events_send_test_email';
	const PREVIEW_AJAX_ACTION     = 'kdna_events_preview_email';
	const PREVIEW_SEND_AJAX_ACTION = 'kdna_events_preview_test_send';

	/**
	 * @var bool
	 */
	protected static $booted = false;

	/**
	 * Register AJAX endpoints.
	 *
	 * @return void
	 */
	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action( 'wp_ajax_' . self::TEST_AJAX_ACTION, array( __CLASS__, 'ajax_send_test' ) );
		add_action( 'wp_ajax_' . self::PREVIEW_AJAX_ACTION, array( __CLASS__, 'ajax_preview_email' ) );
		add_action( 'wp_ajax_' . self::PREVIEW_SEND_AJAX_ACTION, array( __CLASS__, 'ajax_preview_test_send' ) );
	}

	/**
	 * Send the purchaser (and optionally per-attendee) booking emails.
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

		$context = self::build_context( $order, $tickets, null );
		$design  = KDNA_Events_Settings::get_email_design();
		$html    = self::render_booking_confirmation_html( $context, $design );
		$subject = self::resolve_subject( $context, $design, (int) $order->event_id );

		$attachments = apply_filters( 'kdna_events_email_attachments', array(), (int) $order->order_id, 'booking_confirmation' );
		self::send_mail( $order->purchaser_email, $subject, $html, $attachments );

		if ( ! get_option( 'kdna_events_per_attendee_emails', false ) ) {
			return;
		}

		foreach ( $tickets as $ticket ) {
			$email = (string) $ticket->attendee_email;
			if ( '' === $email || ! is_email( $email ) ) {
				continue;
			}
			if ( strcasecmp( $email, (string) $order->purchaser_email ) === 0 && 1 === count( $tickets ) ) {
				continue;
			}
			$ticket_context = self::build_context( $order, array( $ticket ), $ticket );
			$ticket_html    = self::render_booking_confirmation_html( $ticket_context, $design );
			$ticket_subject = self::resolve_subject( $ticket_context, $design, (int) $order->event_id );
			$ticket_atts    = apply_filters( 'kdna_events_email_attachments', array(), (int) $order->order_id, 'booking_confirmation' );
			self::send_mail( $email, $ticket_subject, $ticket_html, $ticket_atts );
		}
	}

	/**
	 * Send admin and optional organiser notification emails.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function send_admin_notification( $order_id ) {
		$order = KDNA_Events_Orders::get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$tickets      = KDNA_Events_Tickets::get_tickets_for_order( (int) $order->order_id );
		$admin_email  = (string) get_option( 'kdna_events_admin_notification_email', '' );
		$notify_org   = (bool) get_option( 'kdna_events_notify_organiser', false );
		$organiser    = kdna_events_get_event_organiser( (int) $order->event_id );
		$organiser_em = (string) $organiser['email'];

		$context = self::build_context( $order, $tickets, null );
		$design  = KDNA_Events_Settings::get_email_design();
		$html    = self::render_admin_notification_html( $context, $design, $order, $tickets );

		$subject_tpl = (string) ( $design['kdna_events_email_admin_subject'] ?? 'New booking: {event_title} ({quantity} tickets)' );
		$subject     = kdna_events_render_merge_tags( $subject_tpl, $context );
		if ( '' === trim( $subject ) ) {
			$subject = sprintf(
				/* translators: 1: order reference, 2: event title */
				__( 'New KDNA Events booking: %1$s for %2$s', 'kdna-events' ),
				$order->order_reference,
				$context['event_title']
			);
		}

		$attachments = apply_filters( 'kdna_events_email_attachments', array(), (int) $order->order_id, 'admin_notification' );

		if ( '' !== $admin_email ) {
			self::send_mail( $admin_email, $subject, $html, $attachments );
		}
		if ( $notify_org && '' !== $organiser_em && 0 !== strcasecmp( $organiser_em, $admin_email ) ) {
			self::send_mail( $organiser_em, $subject, $html, $attachments );
		}
	}

	/**
	 * Build the merge-tag context for a single order.
	 *
	 * When $target_ticket is given, per-ticket tags ({attendee_name},
	 * {ticket_code}) take that ticket's values; otherwise {attendee_name}
	 * falls back to the purchaser name and {ticket_code} concatenates
	 * every ticket code in the order.
	 *
	 * @param object      $order         Order row.
	 * @param array       $tickets       Tickets array.
	 * @param object|null $target_ticket Optional per-attendee ticket.
	 * @return array<string,string>
	 */
	protected static function build_context( $order, $tickets, $target_ticket = null ) {
		$event_id   = (int) $order->event_id;
		$start_raw  = (string) get_post_meta( $event_id, '_kdna_event_start', true );
		$end_raw    = (string) get_post_meta( $event_id, '_kdna_event_end', true );
		$virtual    = (string) get_post_meta( $event_id, '_kdna_event_virtual_url', true );
		$type_slug  = (string) get_post_meta( $event_id, '_kdna_event_type', true );
		$subtitle   = (string) get_post_meta( $event_id, '_kdna_event_subtitle', true );
		$organiser  = kdna_events_get_event_organiser( $event_id );
		$location   = kdna_events_get_event_location( $event_id );

		$types = class_exists( 'KDNA_Events_Admin' ) ? KDNA_Events_Admin::event_types() : array();
		$type_label = isset( $types[ $type_slug ] ) ? (string) $types[ $type_slug ] : ucfirst( $type_slug );

		$event_date  = '' === $start_raw ? '' : kdna_events_format_datetime( $start_raw, 'j F Y', $event_id );
		$event_time  = '' === $start_raw ? '' : kdna_events_format_datetime( $start_raw, 'g:i a', $event_id );
		$event_edate = '' === $end_raw ? '' : kdna_events_format_datetime( $end_raw, 'j F Y', $event_id );
		$event_etime = '' === $end_raw ? '' : kdna_events_format_datetime( $end_raw, 'g:i a', $event_id );

		$location_parts = array_filter( array( $location['name'], $location['address'] ), static function ( $v ) { return '' !== $v; } );
		$location_str   = implode( ', ', $location_parts );
		if ( '' === $location_str && in_array( $type_slug, array( 'virtual', 'hybrid' ), true ) && '' !== $virtual ) {
			$location_str = __( 'Online', 'kdna-events' );
		}

		$purchaser_first = '';
		if ( ! empty( $order->purchaser_name ) ) {
			$bits = preg_split( '/\s+/', trim( (string) $order->purchaser_name ) );
			$purchaser_first = is_array( $bits ) && isset( $bits[0] ) ? $bits[0] : (string) $order->purchaser_name;
		}

		$codes = array();
		foreach ( (array) $tickets as $t ) {
			if ( isset( $t->ticket_code ) ) {
				$codes[] = (string) $t->ticket_code;
			}
		}

		$attendee_name = (string) $order->purchaser_name;
		$ticket_code   = implode( ', ', $codes );
		if ( $target_ticket ) {
			$attendee_name = (string) ( $target_ticket->attendee_name ?? $attendee_name );
			$ticket_code   = (string) ( $target_ticket->ticket_code ?? $ticket_code );
		}

		$currency = isset( $order->currency ) ? (string) $order->currency : (string) get_option( 'kdna_events_default_currency', 'AUD' );
		$total    = isset( $order->total ) ? (float) $order->total : 0.0;
		$total_fmt = $total <= 0 ? __( 'Free', 'kdna-events' ) : kdna_events_format_price( $total, $currency );

		$my_tickets_url = '';
		if ( function_exists( 'kdna_events_get_page_url' ) ) {
			$my_tickets_url = (string) kdna_events_get_page_url( 'my_tickets' );
		}

		return array(
			'event_id'            => (string) $event_id,
			'event_title'         => (string) get_the_title( $event_id ),
			'event_subtitle'      => $subtitle,
			'event_date'          => $event_date,
			'event_time'          => $event_time,
			'event_end_date'      => $event_edate,
			'event_end_time'      => $event_etime,
			'event_type'          => $type_label,
			'event_location'      => $location_str,
			'event_location_name' => (string) $location['name'],
			'event_address'       => (string) $location['address'],
			'event_url'           => (string) get_permalink( $event_id ),
			'virtual_url'         => $virtual,
			'organiser_name'      => (string) $organiser['name'],
			'organiser_email'     => (string) $organiser['email'],
			'purchaser_name'      => (string) $order->purchaser_name,
			'purchaser_first_name' => $purchaser_first,
			'purchaser_email'     => (string) $order->purchaser_email,
			'attendee_name'       => $attendee_name,
			'ticket_code'         => $ticket_code,
			'order_ref'           => (string) $order->order_reference,
			'quantity'            => isset( $order->quantity ) ? (string) $order->quantity : (string) count( (array) $tickets ),
			'total'               => $total_fmt,
			'my_tickets_url'      => $my_tickets_url,
			'business_name'       => (string) get_option( 'kdna_events_email_footer_business_name', '' ),
			'support_email'       => (string) get_option( 'kdna_events_email_from_address', '' ),
			'_event_id_int'       => $event_id,
			'_type_slug'          => $type_slug,
		);
	}

	/**
	 * Render the booking confirmation template to a final HTML string
	 * with CSS inlined via Emogrifier.
	 *
	 * @param array $context Merge-tag context.
	 * @param array $design  Resolved Email Design options.
	 * @return string
	 */
	public static function render_booking_confirmation_html( $context, $design ) {
		$event_id = (int) ( $context['_event_id_int'] ?? 0 );

		$heading = self::resolve_per_event_string( $event_id, '_kdna_event_email_heading', (string) ( $design['kdna_events_email_heading_default'] ?? '' ) );
		$content_1 = self::resolve_per_event_string( $event_id, '_kdna_event_email_content_1', (string) ( $design['kdna_events_email_content_1_default'] ?? '' ) );
		$content_2 = self::resolve_per_event_string( $event_id, '_kdna_event_email_content_2', (string) ( $design['kdna_events_email_content_2_default'] ?? '' ) );
		$footer_text = self::resolve_per_event_string( $event_id, '_kdna_event_email_footer_text', (string) ( $design['kdna_events_email_footer_text'] ?? '' ) );

		$email_heading = kdna_events_render_merge_tags( $heading, $context );
		$content_1     = kdna_events_render_merge_tags( $content_1, $context );
		$content_2     = kdna_events_render_merge_tags( $content_2, $context );
		$footer_text   = kdna_events_render_merge_tags( $footer_text, $context );

		$subject          = self::resolve_subject( $context, $design, $event_id );
		$preheader_text   = self::build_preheader( $context );
		$header_image_url = $event_id ? kdna_events_get_email_header_image_url( $event_id ) : self::preview_default_header_image( $design );
		$header_image_max_h = (int) ( $design['kdna_events_email_header_image_max_h'] ?? 300 );

		$heading_stack   = KDNA_Events_Settings::email_resolve_font_stack( (string) ( $design['kdna_events_email_heading_font'] ?? 'google:Inter' ), (string) ( $design['kdna_events_email_heading_font_custom'] ?? '' ) );
		$body_stack      = KDNA_Events_Settings::email_resolve_font_stack( (string) ( $design['kdna_events_email_body_font'] ?? 'google:Inter' ), (string) ( $design['kdna_events_email_body_font_custom'] ?? '' ) );
		$heading_google  = KDNA_Events_Settings::email_resolve_google_font_url( (string) ( $design['kdna_events_email_heading_font'] ?? '' ) );
		$body_google     = KDNA_Events_Settings::email_resolve_google_font_url( (string) ( $design['kdna_events_email_body_font'] ?? '' ) );
		$inline_style    = self::load_email_css( (string) ( $design['_preview_mode'] ?? '' ) );

		$event_title = (string) ( $context['event_title'] ?? '' );
		$card_radius = (int) ( $design['kdna_events_email_card_border_radius'] ?? 8 );

		// Virtual button context.
		$type_slug   = (string) ( $context['_type_slug'] ?? '' );
		$virtual_url = '';
		if ( in_array( $type_slug, array( 'virtual', 'hybrid' ), true ) ) {
			$virtual_url = (string) ( $context['virtual_url'] ?? '' );
		}
		$button_label   = (string) ( $design['kdna_events_email_virtual_button_label'] ?? 'Virtual Event link' );
		$button_bg      = (string) ( $design['kdna_events_email_virtual_button_bg'] ?? '#F07759' );
		$button_text    = (string) ( $design['kdna_events_email_virtual_button_text'] ?? '#FFFFFF' );
		$button_radius  = (int) ( $design['kdna_events_email_virtual_button_radius'] ?? 28 );

		// Logo.
		$logo_id    = (int) ( $design['kdna_events_email_logo_id'] ?? 0 );
		$logo_url   = $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
		$logo_width = (int) ( $design['kdna_events_email_logo_width'] ?? 160 );
		$logo_align = (string) ( $design['kdna_events_email_logo_align'] ?? 'center' );

		$site_name       = (string) get_bloginfo( 'name' );
		$footer_business = (string) ( $design['kdna_events_email_footer_business_name'] ?? '' );
		if ( '' === $footer_business ) {
			$footer_business = $site_name;
		}

		$muted_color = (string) ( $design['kdna_events_email_color_muted'] ?? '#888888' );
		$body_color  = (string) ( $design['kdna_events_email_color_body'] ?? '#555555' );
		$heading_color = (string) ( $design['kdna_events_email_color_heading'] ?? '#1A1A1A' );

		ob_start();
		include KDNA_EVENTS_PATH . 'templates/emails/booking-confirmation.php';
		$raw_html = (string) ob_get_clean();

		return self::inline_css( $raw_html );
	}

	/**
	 * Render the admin notification template to a final HTML string.
	 *
	 * @param array            $context Merge-tag context.
	 * @param array            $design  Email Design options.
	 * @param object|null      $order   Order row (null for preview).
	 * @param array|null       $tickets Tickets array (null for preview).
	 * @return string
	 */
	public static function render_admin_notification_html( $context, $design, $order = null, $tickets = null ) {
		$heading_stack  = KDNA_Events_Settings::email_resolve_font_stack( (string) ( $design['kdna_events_email_heading_font'] ?? 'google:Inter' ), (string) ( $design['kdna_events_email_heading_font_custom'] ?? '' ) );
		$body_stack     = KDNA_Events_Settings::email_resolve_font_stack( (string) ( $design['kdna_events_email_body_font'] ?? 'google:Inter' ), (string) ( $design['kdna_events_email_body_font_custom'] ?? '' ) );
		$heading_google = KDNA_Events_Settings::email_resolve_google_font_url( (string) ( $design['kdna_events_email_heading_font'] ?? '' ) );
		$body_google    = KDNA_Events_Settings::email_resolve_google_font_url( (string) ( $design['kdna_events_email_body_font'] ?? '' ) );
		$inline_style   = self::load_email_css();

		$admin_heading  = kdna_events_render_merge_tags( (string) ( $design['kdna_events_email_admin_heading'] ?? '' ), $context );
		$admin_intro    = kdna_events_render_merge_tags( (string) ( $design['kdna_events_email_admin_intro'] ?? '' ), $context );
		$summary_heading   = kdna_events_render_merge_tags( (string) ( $design['kdna_events_email_admin_summary_heading'] ?? '' ), $context );
		$event_heading     = kdna_events_render_merge_tags( (string) ( $design['kdna_events_email_admin_event_heading'] ?? '' ), $context );
		$attendees_heading = kdna_events_render_merge_tags( (string) ( $design['kdna_events_email_admin_attendees_heading'] ?? '' ), $context );
		$admin_footer_note = kdna_events_render_merge_tags( (string) ( $design['kdna_events_email_admin_footer_note'] ?? '' ), $context );
		$compact           = ! empty( $design['kdna_events_email_admin_header_compact'] );

		$subject_tpl = (string) ( $design['kdna_events_email_admin_subject'] ?? 'New booking: {event_title} ({quantity} tickets)' );
		$subject     = kdna_events_render_merge_tags( $subject_tpl, $context );

		$event_id_int = (int) ( $context['_event_id_int'] ?? 0 );
		$event_edit_url = $event_id_int ? admin_url( 'post.php?post=' . $event_id_int . '&action=edit' ) : '';

		$preheader_text = sprintf(
			/* translators: 1: event title, 2: quantity, 3: total */
			__( 'New booking received for %1$s, %2$s tickets, %3$s', 'kdna-events' ),
			(string) ( $context['event_title'] ?? '' ),
			(string) ( $context['quantity'] ?? '' ),
			(string) ( $context['total'] ?? '' )
		);

		$booked_at = '';
		if ( $order && isset( $order->created_at ) ) {
			$booked_at = (string) $order->created_at;
		}
		$purchaser_phone = $order && isset( $order->purchaser_phone ) ? (string) $order->purchaser_phone : '';
		$stripe_ref      = $order && isset( $order->stripe_payment_intent ) ? (string) $order->stripe_payment_intent : '';
		$status_label    = $order && isset( $order->status ) ? (string) $order->status : '';
		$total_display   = (string) ( $context['total'] ?? '' );

		$summary_rows = array(
			__( 'Order reference', 'kdna-events' ) => (string) ( $context['order_ref'] ?? '' ),
			__( 'Booked at', 'kdna-events' )       => $booked_at,
			__( 'Purchaser', 'kdna-events' )       => (string) ( $context['purchaser_name'] ?? '' ),
			__( 'Purchaser email', 'kdna-events' ) => (string) ( $context['purchaser_email'] ?? '' ),
			__( 'Purchaser phone', 'kdna-events' ) => $purchaser_phone,
			__( 'Tickets purchased', 'kdna-events' ) => (string) ( $context['quantity'] ?? '' ),
			__( 'Total amount', 'kdna-events' )    => $total_display,
			__( 'Payment status', 'kdna-events' )  => $status_label,
			__( 'Payment reference', 'kdna-events' ) => '' !== $stripe_ref ? $stripe_ref : ( __( 'Free', 'kdna-events' ) === $total_display ? __( 'Free', 'kdna-events' ) : '' ),
		);

		$attendee_rows = array();
		if ( is_array( $tickets ) ) {
			foreach ( $tickets as $t ) {
				$custom = array();
				if ( isset( $t->custom_fields ) ) {
					if ( is_array( $t->custom_fields ) ) {
						$custom = $t->custom_fields;
					} elseif ( is_string( $t->custom_fields ) ) {
						$decoded = json_decode( $t->custom_fields, true );
						if ( is_array( $decoded ) ) {
							$custom = $decoded;
						}
					}
				}
				$attendee_rows[] = array(
					'name'          => (string) ( $t->attendee_name ?? '' ),
					'email'         => (string) ( $t->attendee_email ?? '' ),
					'phone'         => (string) ( $t->attendee_phone ?? '' ),
					'ticket_code'   => (string) ( $t->ticket_code ?? '' ),
					'custom_fields' => $custom,
				);
			}
		}

		$site_name       = (string) get_bloginfo( 'name' );
		$footer_business = (string) ( $design['kdna_events_email_footer_business_name'] ?? '' );
		if ( '' === $footer_business ) {
			$footer_business = $site_name;
		}
		$footer_text = kdna_events_render_merge_tags( (string) ( $design['kdna_events_email_footer_text'] ?? '' ), $context );
		$muted_color = (string) ( $design['kdna_events_email_color_muted'] ?? '#888888' );

		// Logo.
		$logo_id    = (int) ( $design['kdna_events_email_logo_id'] ?? 0 );
		$logo_url   = $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
		$logo_width = (int) ( $design['kdna_events_email_logo_width'] ?? 160 );
		$logo_align = (string) ( $design['kdna_events_email_logo_align'] ?? 'center' );

		ob_start();
		include KDNA_EVENTS_PATH . 'templates/emails/admin-notification.php';
		$raw_html = (string) ob_get_clean();

		return self::inline_css( $raw_html );
	}

	/**
	 * Return the per-event override when set, otherwise the global default.
	 *
	 * @param int    $event_id Event post ID.
	 * @param string $meta_key Meta key to read.
	 * @param string $default  Global default value.
	 * @return string
	 */
	protected static function resolve_per_event_string( $event_id, $meta_key, $default ) {
		if ( $event_id > 0 ) {
			$value = (string) get_post_meta( $event_id, $meta_key, true );
			if ( '' !== trim( $value ) ) {
				return $value;
			}
		}
		return (string) $default;
	}

	/**
	 * Resolve the subject line, per-event override then global default.
	 *
	 * @param array $context  Merge-tag context.
	 * @param array $design   Email Design options.
	 * @param int   $event_id Event post ID.
	 * @return string
	 */
	protected static function resolve_subject( $context, $design, $event_id ) {
		$subject = self::resolve_per_event_string( $event_id, '_kdna_event_email_subject', (string) ( $design['kdna_events_email_subject_default'] ?? '' ) );
		return kdna_events_render_merge_tags( $subject, $context );
	}

	/**
	 * Build preheader text from the context.
	 *
	 * @param array $context Merge-tag context.
	 * @return string
	 */
	protected static function build_preheader( $context ) {
		$bits = array();
		if ( ! empty( $context['event_title'] ) ) {
			$bits[] = (string) $context['event_title'];
		}
		if ( ! empty( $context['event_date'] ) ) {
			$bits[] = (string) $context['event_date'];
		}
		if ( empty( $bits ) ) {
			return '';
		}
		return sprintf(
			/* translators: %s: event detail summary */
			__( 'Booking confirmed: %s', 'kdna-events' ),
			implode( ', ', $bits )
		);
	}

	/**
	 * Load the email CSS file from disk.
	 *
	 * @return string
	 */
	protected static function load_email_css( $preview_mode = '' ) {
		$path = KDNA_EVENTS_PATH . 'templates/emails/css/email.css';
		if ( ! file_exists( $path ) ) {
			return '';
		}
		$css = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( 'light' === $preview_mode ) {
			// Strip the prefers-color-scheme:dark media query so the preview
			// always renders light regardless of the admin's OS setting.
			$css = preg_replace( '/@media\s*\(prefers-color-scheme:\s*dark\)\s*\{(?:[^{}]|\{[^{}]*\})*\}/i', '', $css );
		} elseif ( 'dark' === $preview_mode ) {
			// Drop the media-query gate so the dark rules apply unconditionally.
			$css = preg_replace( '/@media\s*\(prefers-color-scheme:\s*dark\)\s*\{\s*((?:[^{}]|\{[^{}]*\})*)\s*\}/i', '$1', $css );
		}

		return (string) $css;
	}

	/**
	 * Inline CSS into the HTML via Emogrifier when available,
	 * returning the HTML unchanged when the vendor dep is missing.
	 *
	 * @param string $html Raw rendered template output.
	 * @return string
	 */
	protected static function inline_css( $html ) {
		$vendor = KDNA_EVENTS_PATH . 'vendor/autoload.php';
		if ( file_exists( $vendor ) ) {
			require_once $vendor;
		}
		if ( ! class_exists( '\\Pelago\\Emogrifier\\CssInliner' ) ) {
			return $html;
		}
		try {
			$inliner = \Pelago\Emogrifier\CssInliner::fromHtml( $html )->inlineCss();
			if ( class_exists( '\\Pelago\\Emogrifier\\HtmlProcessor\\HtmlPruner' ) ) {
				\Pelago\Emogrifier\HtmlProcessor\HtmlPruner::fromDomDocument( $inliner->getDomDocument() )
					->removeElementsWithDisplayNone()
					->removeRedundantClassesAfterCssInlined( $inliner );
			}
			return (string) $inliner->render();
		} catch ( \Throwable $e ) {
			return $html;
		}
	}

	/**
	 * Strip the HTML body to a plain text fallback.
	 *
	 * @param string $html Full email HTML.
	 * @return string
	 */
	protected static function strip_html_to_text( $html ) {
		$text = (string) $html;

		// Drop head and style blocks entirely.
		$text = preg_replace( '#<style[^>]*>.*?</style>#si', '', $text );
		$text = preg_replace( '#<head[^>]*>.*?</head>#si', '', $text );

		// Turn links into 'label (URL)'.
		$text = preg_replace_callback(
			'#<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#si',
			static function ( $m ) {
				$label = trim( wp_strip_all_tags( $m[2] ) );
				return $label ? $label . ' (' . $m[1] . ')' : $m[1];
			},
			(string) $text
		);

		// Block-level elements to newlines.
		$text = preg_replace( '#</(p|div|tr|h[1-6]|li|br)[^>]*>#i', "\n", (string) $text );
		$text = preg_replace( '#<br\s*/?>#i', "\n", (string) $text );

		// Remove remaining tags + decode entities + collapse whitespace.
		$text = wp_strip_all_tags( (string) $text );
		$text = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( "/\n{3,}/", "\n\n", (string) $text );
		$text = preg_replace( '/[ \t]+/', ' ', (string) $text );

		return trim( (string) $text );
	}

	/**
	 * Resolve the default header image URL for the preview / fallback.
	 *
	 * @param array $design Email Design options.
	 * @return string
	 */
	protected static function preview_default_header_image( $design ) {
		$id = (int) ( $design['kdna_events_email_default_header_image'] ?? 0 );
		if ( ! $id ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $id, 'kdna-events-email-header' );
		if ( $url ) {
			return (string) $url;
		}
		return (string) wp_get_attachment_image_url( $id, 'large' );
	}

	/**
	 * Send a multipart HTML email via wp_mail, with a plain-text fallback.
	 *
	 * @param string $to          Recipient.
	 * @param string $subject     Subject.
	 * @param string $html        HTML body.
	 * @param array  $attachments Attachments to pass to wp_mail.
	 * @return bool
	 */
	protected static function send_mail( $to, $subject, $html, $attachments = array() ) {
		$from_name    = (string) get_option( 'kdna_events_email_from_name', '' );
		$from_address = (string) get_option( 'kdna_events_email_from_address', '' );
		$reply_to     = (string) get_option( 'kdna_events_email_reply_to', '' );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( '' !== $from_name && '' !== $from_address ) {
			$headers[] = sprintf( 'From: %s <%s>', $from_name, $from_address );
		} elseif ( '' !== $from_address ) {
			$headers[] = sprintf( 'From: <%s>', $from_address );
		}
		if ( '' !== $reply_to && is_email( $reply_to ) ) {
			$headers[] = sprintf( 'Reply-To: <%s>', $reply_to );
		}

		$plain = self::strip_html_to_text( $html );

		// Swap the HTML Content-Type for a multipart/alternative boundary
		// on this single mail via phpmailer_init.
		$add_plain = static function ( $phpmailer ) use ( $plain ) {
			if ( is_object( $phpmailer ) ) {
				$phpmailer->AltBody = $plain; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			}
		};
		add_action( 'phpmailer_init', $add_plain );

		$ok = (bool) wp_mail( $to, $subject, $html, $headers, (array) $attachments );

		remove_action( 'phpmailer_init', $add_plain );

		return $ok;
	}

	/**
	 * Return a complete fake context for preview rendering and tests.
	 *
	 * @return array<string,string>
	 */
	public static function get_sample_context() {
		$start_iso = date_i18n( 'Y-m-d', strtotime( '+21 days' ) ) . 'T19:00';
		return array(
			'event_id'             => '0',
			'event_title'          => __( 'Done Differently Brand Launch', 'kdna-events' ),
			'event_subtitle'       => __( 'An evening with our friends', 'kdna-events' ),
			'event_date'           => kdna_events_format_datetime( $start_iso, 'j F Y' ),
			'event_time'           => kdna_events_format_datetime( $start_iso, 'g:i a' ),
			'event_end_date'       => '',
			'event_end_time'       => '',
			'event_type'           => __( 'In-person', 'kdna-events' ),
			'event_location'       => __( 'The Apothecary Lab, 42 Flinders Lane, Melbourne', 'kdna-events' ),
			'event_location_name'  => __( 'The Apothecary Lab', 'kdna-events' ),
			'event_address'        => __( '42 Flinders Lane, Melbourne', 'kdna-events' ),
			'event_url'            => home_url( '/' ),
			'virtual_url'          => 'https://example.com/join',
			'organiser_name'       => __( 'Alex Taylor', 'kdna-events' ),
			'organiser_email'      => 'alex@example.com',
			'purchaser_name'       => __( 'Jane Doe', 'kdna-events' ),
			'purchaser_first_name' => __( 'Jane', 'kdna-events' ),
			'purchaser_email'      => 'jane@example.com',
			'attendee_name'        => __( 'Jane Doe', 'kdna-events' ),
			'ticket_code'          => 'ABCD1234',
			'order_ref'            => '2026-000123',
			'quantity'             => '2',
			'total'                => kdna_events_format_price( 199, (string) get_option( 'kdna_events_default_currency', 'AUD' ) ),
			'my_tickets_url'       => home_url( '/my-tickets/' ),
			'business_name'        => (string) get_bloginfo( 'name' ),
			'support_email'        => (string) get_option( 'admin_email', '' ),
			'_event_id_int'        => 0,
			'_type_slug'           => 'hybrid',
		);
	}

	/**
	 * Produce sample summary + attendee rows for the admin preview.
	 *
	 * @return array{summary:array<string,string>,attendees:array<int,array<string,string>>}
	 */
	public static function get_sample_admin_rows() {
		$context = self::get_sample_context();
		return array(
			'summary' => array(
				__( 'Order reference', 'kdna-events' ) => $context['order_ref'],
				__( 'Event', 'kdna-events' )           => $context['event_title'],
				__( 'Event date', 'kdna-events' )      => trim( $context['event_date'] . ' ' . $context['event_time'] ),
				__( 'Event type', 'kdna-events' )      => $context['event_type'],
				__( 'Location', 'kdna-events' )        => $context['event_location'],
				__( 'Purchaser', 'kdna-events' )       => $context['purchaser_name'],
				__( 'Purchaser email', 'kdna-events' ) => $context['purchaser_email'],
				__( 'Quantity', 'kdna-events' )        => $context['quantity'],
				__( 'Total', 'kdna-events' )           => $context['total'],
				__( 'Status', 'kdna-events' )          => __( 'paid', 'kdna-events' ),
			),
			'attendees' => array(
				array( 'name' => __( 'Jane Doe', 'kdna-events' ),   'email' => 'jane@example.com', 'ticket_code' => 'ABCD1234' ),
				array( 'name' => __( 'John Smith', 'kdna-events' ), 'email' => 'john@example.com', 'ticket_code' => 'EFGH5678' ),
			),
		);
	}

	/**
	 * Render a template with sample data for the live preview panel.
	 *
	 * Accepts an optional overlay of Email Design options so the
	 * preview reflects unsaved control changes without touching the DB.
	 *
	 * @param string $template Template slug: 'booking_confirmation' | 'admin_notification'.
	 * @param array  $overlay  Option overlay.
	 * @return string Rendered HTML.
	 */
	public static function render_preview( $template, $overlay = array(), $mode = 'light' ) {
		$design = KDNA_Events_Settings::get_email_design();
		foreach ( (array) $overlay as $key => $value ) {
			if ( array_key_exists( $key, $design ) ) {
				$design[ $key ] = $value;
			}
		}

		$design['_preview_mode'] = in_array( $mode, array( 'light', 'dark' ), true ) ? $mode : 'light';

		$context = self::get_sample_context();

		if ( 'admin_notification' === $template ) {
			$sample_order   = (object) array(
				'order_id'              => 0,
				'order_reference'       => $context['order_ref'],
				'event_id'              => 0,
				'purchaser_name'        => $context['purchaser_name'],
				'purchaser_email'       => $context['purchaser_email'],
				'purchaser_phone'       => '+61 400 123 456',
				'quantity'              => 2,
				'total'                 => 199,
				'currency'              => (string) get_option( 'kdna_events_default_currency', 'AUD' ),
				'status'                => 'paid',
				'created_at'            => current_time( 'mysql' ),
				'stripe_payment_intent' => 'pi_3PreviewExample',
			);
			$sample_tickets = array(
				(object) array(
					'attendee_name'  => __( 'Jane Doe', 'kdna-events' ),
					'attendee_email' => 'jane@example.com',
					'attendee_phone' => '+61 400 555 001',
					'ticket_code'    => 'ABCD1234',
					'custom_fields'  => array( 'Dietary' => 'Vegetarian', 'T-shirt size' => 'M' ),
				),
				(object) array(
					'attendee_name'  => __( 'John Smith', 'kdna-events' ),
					'attendee_email' => 'john@example.com',
					'attendee_phone' => '+61 400 555 002',
					'ticket_code'    => 'EFGH5678',
					'custom_fields'  => array( 'Dietary' => 'None', 'T-shirt size' => 'L' ),
				),
			);
			return self::render_admin_notification_html( $context, $design, $sample_order, $sample_tickets );
		}

		return self::render_booking_confirmation_html( $context, $design );
	}

	/**
	 * AJAX: send a branded test email using the new templates.
	 *
	 * Preserves the v1.0 action name so existing admin wiring keeps
	 * working. Now routes through the v1.1 render flow.
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
		$html = self::render_preview( 'booking_confirmation' );
		if ( '' === $html ) {
			wp_send_json_error( array( 'message' => __( 'Email template missing.', 'kdna-events' ) ), 500 );
		}
		$subject = __( 'KDNA Events test email', 'kdna-events' );
		$sent    = self::send_mail( $admin_email, $subject, $html );
		if ( $sent ) {
			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: %s: email address */
						__( 'Test email sent to %s.', 'kdna-events' ),
						$admin_email
					),
				)
			);
		}
		wp_send_json_error( array( 'message' => __( 'wp_mail returned false. Check your SMTP config.', 'kdna-events' ) ), 500 );
	}

	/**
	 * AJAX: render a preview of the selected template with current
	 * form values overlaid on top of the saved Email Design options.
	 *
	 * @return void
	 */
	public static function ajax_preview_email() {
		check_ajax_referer( 'kdna_events_preview_email', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kdna-events' ) ), 403 );
		}
		$template = isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : 'booking_confirmation';
		if ( ! in_array( $template, array( 'booking_confirmation', 'admin_notification' ), true ) ) {
			$template = 'booking_confirmation';
		}

		$mode = isset( $_POST['preview_mode'] ) ? sanitize_key( wp_unslash( $_POST['preview_mode'] ) ) : 'light';
		if ( ! in_array( $mode, array( 'light', 'dark' ), true ) ) {
			$mode = 'light';
		}

		$overlay = array();
		$schema  = KDNA_Events_Settings::email_design_schema();
		foreach ( $schema as $name => $def ) {
			if ( ! isset( $_POST[ $name ] ) ) {
				continue;
			}
			$raw = wp_unslash( $_POST[ $name ] );
			if ( 'integer' === $def['type'] ) {
				$overlay[ $name ] = absint( $raw );
				continue;
			}
			if ( 0 === strpos( $name, 'kdna_events_email_color_' ) || in_array( $name, array( 'kdna_events_email_virtual_button_bg', 'kdna_events_email_virtual_button_text' ), true ) ) {
				$raw = trim( (string) $raw );
				if ( preg_match( '/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $raw ) ) {
					$overlay[ $name ] = strtoupper( $raw );
				}
				continue;
			}
			$overlay[ $name ] = sanitize_textarea_field( (string) $raw );
		}

		$html = self::render_preview( $template, $overlay, $mode );
		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * AJAX: send the currently previewed template to a given inbox
	 * using saved settings.
	 *
	 * @return void
	 */
	public static function ajax_preview_test_send() {
		check_ajax_referer( 'kdna_events_preview_test_send', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kdna-events' ) ), 403 );
		}
		$to = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
		if ( '' === $to || ! is_email( $to ) ) {
			wp_send_json_error( array( 'message' => __( 'Enter a valid email address.', 'kdna-events' ) ), 400 );
		}
		$template = isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : 'booking_confirmation';
		if ( ! in_array( $template, array( 'booking_confirmation', 'admin_notification' ), true ) ) {
			$template = 'booking_confirmation';
		}
		$mode = isset( $_POST['preview_mode'] ) ? sanitize_key( wp_unslash( $_POST['preview_mode'] ) ) : 'light';
		if ( ! in_array( $mode, array( 'light', 'dark' ), true ) ) {
			$mode = 'light';
		}

		$html    = self::render_preview( $template, array(), $mode );
		$subject = 'admin_notification' === $template
			? __( '[Test] KDNA Events admin notification', 'kdna-events' )
			: __( '[Test] KDNA Events booking confirmation', 'kdna-events' );

		$sent = self::send_mail( $to, $subject, $html );
		if ( $sent ) {
			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: %s: email */
						__( 'Test sent to %s.', 'kdna-events' ),
						$to
					),
				)
			);
		}
		wp_send_json_error( array( 'message' => __( 'wp_mail returned false. Check your SMTP config.', 'kdna-events' ) ), 500 );
	}
}
