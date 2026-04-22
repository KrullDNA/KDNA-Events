<?php
/**
 * Widget integration: renders a 'Download Ticket PDF' button
 * underneath each ticket rendered by the core Success Tickets and
 * My Tickets widgets.
 *
 * Extends the widgets exclusively through the core actions
 * kdna_events_after_success_ticket and kdna_events_after_my_ticket
 * added in core Brief A. Core widgets are not modified.
 *
 * @package KDNA_Events_PDF_Tickets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widget action hook.
 */
class KDNA_Events_PDF_Widget_Integration {

	/**
	 * @var self|null
	 */
	protected static $instance = null;

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
	 * Wire up the action hooks and the frontend stylesheet.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'kdna_events_after_success_ticket', array( $this, 'render_download_button' ), 10, 3 );
		add_action( 'kdna_events_after_my_ticket', array( $this, 'render_download_button' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Render a download button for a ticket.
	 *
	 * @param object $ticket   Ticket record.
	 * @param mixed  $order    Order record (may be null for My Tickets).
	 * @param array  $settings Widget settings.
	 * @return void
	 */
	public function render_download_button( $ticket, $order, $settings ) {
		unset( $order, $settings );
		if ( ! $ticket || empty( $ticket->ticket_code ) ) {
			return;
		}
		$url   = kdna_events_pdf_download_url( (string) $ticket->ticket_code, true );
		$label = (string) get_option( 'kdna_events_pdf_button_label', __( 'Download Ticket PDF', 'kdna-events-pdf-tickets' ) );
		?>
		<a class="kdna-events-pdf-download-btn" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">
			<span class="kdna-events-pdf-download-btn__icon" aria-hidden="true">
				<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
			</span>
			<span class="kdna-events-pdf-download-btn__label"><?php echo esc_html( $label ); ?></span>
		</a>
		<?php
	}

	/**
	 * Enqueue a small stylesheet for the download button.
	 *
	 * @return void
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			'kdna-events-pdf-download-btn',
			KDNA_EVENTS_PDF_PLUGIN_URL . 'assets/css/download-button.css',
			array(),
			KDNA_EVENTS_PDF_VERSION
		);
	}
}
