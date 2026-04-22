<?php
/**
 * Email integration: attaches generated PDF tickets to the core
 * booking confirmation email via the kdna_events_email_attachments
 * filter added in core Brief A.
 *
 * Registers at the default priority 10 so it runs BEFORE the core
 * Tax Invoices attachment at priority 20; the email ends up with
 * attachments in the order [ticket.pdf, invoice.pdf] which is the
 * ordering admins expect.
 *
 * @package KDNA_Events_PDF_Tickets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email attachment hook.
 */
class KDNA_Events_PDF_Email_Integration {

	/**
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * @var KDNA_Events_PDF_Generator
	 */
	protected $generator;

	/**
	 * Singleton accessor.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire up the filter. Idempotent.
	 *
	 * @return void
	 */
	public function init() {
		$this->generator = new KDNA_Events_PDF_Generator();
		add_filter( 'kdna_events_email_attachments', array( $this, 'attach_pdf_tickets' ), 10, 3 );
		add_action( 'kdna_events_pdf_unlink_tmp', array( $this, 'unlink_tmp' ), 10, 1 );
	}

	/**
	 * Attach ticket PDFs to booking confirmation emails only.
	 *
	 * @param array  $attachments Current attachments.
	 * @param int    $order_id    Order ID.
	 * @param string $email_type  'booking_confirmation' | 'admin_notification'.
	 * @return array
	 */
	public function attach_pdf_tickets( $attachments, $order_id, $email_type ) {
		if ( 'booking_confirmation' !== $email_type ) {
			return $attachments;
		}

		$mode = (string) get_option( 'kdna_events_pdf_tickets_per_order', 'combined' );

		if ( 'separate' === $mode ) {
			if ( ! class_exists( 'KDNA_Events_Tickets' ) ) {
				return $attachments;
			}
			$tickets = KDNA_Events_Tickets::get_tickets_for_order( (int) $order_id );
			foreach ( (array) $tickets as $ticket ) {
				$pdf = $this->generator->generate_ticket( (int) $ticket->ticket_id );
				if ( '' === $pdf ) {
					continue;
				}
				$path = $this->generator->save_to_temp( $pdf, 'ticket-' . $ticket->ticket_code );
				if ( '' !== $path ) {
					$attachments[] = $path;
					$this->schedule_cleanup( $path );
				}
			}
			return $attachments;
		}

		// Combined mode: one PDF with page-break-before on each ticket.
		$pdf = $this->generator->generate_order_tickets( (int) $order_id );
		if ( '' === $pdf ) {
			return $attachments;
		}
		$path = $this->generator->save_to_temp( $pdf, 'tickets-order-' . (int) $order_id );
		if ( '' !== $path ) {
			$attachments[] = $path;
			$this->schedule_cleanup( $path );
		}
		return $attachments;
	}

	/**
	 * Schedule a single-event cleanup 5 minutes after the mail send,
	 * as a safety net beyond the hourly cron.
	 *
	 * @param string $path
	 * @return void
	 */
	protected function schedule_cleanup( $path ) {
		wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, 'kdna_events_pdf_unlink_tmp', array( $path ) );
	}

	/**
	 * Cron callback: delete a temp file if it still exists.
	 *
	 * @param string $path
	 * @return void
	 */
	public function unlink_tmp( $path ) {
		$path = (string) $path;
		if ( '' === $path || ! is_string( $path ) ) {
			return;
		}
		$dir = kdna_events_pdf_tmp_dir();
		if ( '' === $dir ) {
			return;
		}
		// Guard: only unlink inside our temp dir.
		$real_dir  = realpath( $dir );
		$real_path = realpath( $path );
		if ( false === $real_dir || false === $real_path || 0 !== strpos( $real_path, $real_dir ) ) {
			return;
		}
		if ( is_file( $real_path ) ) {
			@unlink( $real_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink,Generic.PHP.NoSilencedErrors.Discouraged
		}
	}
}
