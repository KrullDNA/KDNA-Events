<?php
/**
 * ATO-compliant tax invoice generator for KDNA Events.
 *
 * Brief C, v1.2.0. Every paid booking automatically generates a
 * sequentially numbered, immutable tax invoice PDF and attaches it
 * to the branded booking confirmation email from Brief A. Free
 * events generate no invoice.
 *
 * Core responsibilities:
 *   - Allocate unique sequential invoice numbers under concurrent
 *     load via a SELECT ... FOR UPDATE row lock on the options row.
 *   - Snapshot business details + tax rate + tax label on issue so
 *     historical invoices never shift when settings change later.
 *   - Idempotent generation: calling generate() for the same order
 *     always returns the same invoice number and PDF content.
 *   - Plug into the Brief A kdna_events_email_attachments filter at
 *     priority 20 so the PDF ticket add-on (Brief B, priority 10)
 *     keeps its spot first.
 *   - Serve the PDF through a signed-token REST endpoint so guest
 *     buyers can download their own invoice without an account.
 *
 * @package KDNA_Events
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Invoice generator + REST endpoint controller.
 */
class KDNA_Events_Invoices {

	const REST_NAMESPACE   = 'kdna-events/v1';
	const PREVIEW_ACTION   = 'kdna_events_preview_invoice';
	const REGEN_ACTION     = 'kdna_events_regenerate_invoice';
	const TMP_SUBDIR       = 'kdna-events-tmp';
	const TOKEN_LIFETIME   = DAY_IN_SECONDS;

	/**
	 * @var bool
	 */
	protected static $booted = false;

	/**
	 * Register hooks. Idempotent.
	 *
	 * @return void
	 */
	public static function init() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_filter( 'kdna_events_email_attachments', array( __CLASS__, 'attach_invoice_pdf' ), 20, 3 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'wp_ajax_' . self::PREVIEW_ACTION, array( __CLASS__, 'ajax_preview_invoice' ) );
		add_action( 'wp_ajax_' . self::REGEN_ACTION, array( __CLASS__, 'ajax_regenerate_invoice' ) );
		add_action( 'kdna_events_cleanup_tmp', array( __CLASS__, 'cleanup_tmp' ) );
		add_action( 'admin_notices', array( __CLASS__, 'incomplete_settings_notice' ) );

		// Daily cleanup of stale temp PDFs.
		if ( ! wp_next_scheduled( 'kdna_events_cleanup_tmp' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'kdna_events_cleanup_tmp' );
		}
	}

	/**
	 * Default values + types for every Tax Invoices setting.
	 *
	 * @return array<string,array{type:string,default:mixed}>
	 */
	public static function options_schema() {
		return array(
			// Master switch.
			'kdna_events_invoices_enabled'                  => array( 'type' => 'boolean', 'default' => true ),

			// Business details.
			'kdna_events_invoice_business_legal_name'       => array( 'type' => 'string',  'default' => '' ),
			'kdna_events_invoice_business_trading_name'     => array( 'type' => 'string',  'default' => '' ),
			'kdna_events_invoice_tax_id'                    => array( 'type' => 'string',  'default' => '' ),
			'kdna_events_invoice_business_address'          => array( 'type' => 'string',  'default' => '' ),
			'kdna_events_invoice_business_email'            => array( 'type' => 'string',  'default' => '' ),
			'kdna_events_invoice_business_phone'            => array( 'type' => 'string',  'default' => '' ),
			'kdna_events_invoice_business_website'          => array( 'type' => 'string',  'default' => '' ),

			// Tax config.
			'kdna_events_invoice_tax_registered'            => array( 'type' => 'boolean', 'default' => true ),
			'kdna_events_invoice_jurisdiction'              => array( 'type' => 'string',  'default' => 'au' ),
			'kdna_events_invoice_tax_label'                 => array( 'type' => 'string',  'default' => 'GST' ),
			'kdna_events_invoice_tax_rate'                  => array( 'type' => 'string',  'default' => '10.00' ),
			'kdna_events_invoice_document_heading'          => array( 'type' => 'string',  'default' => 'Tax Invoice' ),
			'kdna_events_invoice_tax_inclusive_statement'   => array( 'type' => 'string',  'default' => 'All prices include GST where applicable.' ),

			// Numbering.
			'kdna_events_invoice_number_prefix'             => array( 'type' => 'string',  'default' => 'INV-' ),
			'kdna_events_invoice_number_suffix'             => array( 'type' => 'string',  'default' => '' ),
			'kdna_events_invoice_number_start'              => array( 'type' => 'integer', 'default' => 1 ),
			'kdna_events_invoice_number_padding'            => array( 'type' => 'integer', 'default' => 5 ),
			'kdna_events_invoice_current_sequence'          => array( 'type' => 'integer', 'default' => 0 ),

			// Content strings.
			'kdna_events_invoice_line_item_template'        => array( 'type' => 'string',  'default' => '{quantity} x Ticket to {event_title}' ),
			'kdna_events_invoice_paid_label'                => array( 'type' => 'string',  'default' => 'PAID' ),
			'kdna_events_invoice_pending_label'             => array( 'type' => 'string',  'default' => 'PENDING PAYMENT' ),
			'kdna_events_invoice_payment_method_label'      => array( 'type' => 'string',  'default' => 'Paid via Stripe' ),
			'kdna_events_invoice_payment_terms'             => array( 'type' => 'string',  'default' => 'Payment received in full.' ),
			'kdna_events_invoice_notes'                     => array( 'type' => 'string',  'default' => 'Thank you for your business.' ),
			'kdna_events_invoice_date_source'               => array( 'type' => 'string',  'default' => 'booking' ),

			// Design.
			'kdna_events_invoice_design_inherit_logo'       => array( 'type' => 'boolean', 'default' => true ),
			'kdna_events_invoice_design_logo_id'            => array( 'type' => 'integer', 'default' => 0 ),
			'kdna_events_invoice_design_logo_width'         => array( 'type' => 'integer', 'default' => 140 ),
			'kdna_events_invoice_design_inherit_colours'    => array( 'type' => 'boolean', 'default' => true ),
			'kdna_events_invoice_design_color_primary'      => array( 'type' => 'string',  'default' => '#1A1A1A' ),
			'kdna_events_invoice_design_color_accent'       => array( 'type' => 'string',  'default' => '#F07759' ),
			'kdna_events_invoice_design_inherit_fonts'      => array( 'type' => 'boolean', 'default' => true ),
			'kdna_events_invoice_design_heading_font'       => array( 'type' => 'string',  'default' => 'helvetica' ),
			'kdna_events_invoice_design_body_font'          => array( 'type' => 'string',  'default' => 'helvetica' ),
			'kdna_events_invoice_design_heading_size'       => array( 'type' => 'integer', 'default' => 18 ),
			'kdna_events_invoice_design_body_size'          => array( 'type' => 'integer', 'default' => 10 ),
			'kdna_events_invoice_design_page_size'          => array( 'type' => 'string',  'default' => 'A4' ),
			'kdna_events_invoice_design_page_margin'        => array( 'type' => 'integer', 'default' => 15 ),
			'kdna_events_invoice_design_show_paid_stamp'    => array( 'type' => 'boolean', 'default' => true ),
		);
	}

	/**
	 * Read every invoice option with defaults applied.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_options() {
		$out = array();
		foreach ( self::options_schema() as $name => $def ) {
			$value = get_option( $name, $def['default'] );
			if ( '' === $value || null === $value ) {
				$value = $def['default'];
			}
			$out[ $name ] = $value;
		}
		return $out;
	}

	/**
	 * Jurisdiction presets. Jurisdiction selector populates these
	 * defaults on the Tax Invoices tab; admins can still override any
	 * field after selecting.
	 *
	 * @return array<string,array{label:string,tax_label:string,rate:string,heading:string,tax_id_label:string}>
	 */
	public static function jurisdictions() {
		return array(
			'au'    => array( 'label' => __( 'Australia', 'kdna-events' ),      'tax_label' => 'GST',       'rate' => '10.00', 'heading' => __( 'Tax Invoice', 'kdna-events' ), 'tax_id_label' => __( 'ABN', 'kdna-events' ) ),
			'uk'    => array( 'label' => __( 'United Kingdom', 'kdna-events' ), 'tax_label' => 'VAT',       'rate' => '20.00', 'heading' => __( 'VAT Invoice', 'kdna-events' ), 'tax_id_label' => __( 'VAT Number', 'kdna-events' ) ),
			'eu'    => array( 'label' => __( 'European Union', 'kdna-events' ), 'tax_label' => 'VAT',       'rate' => '20.00', 'heading' => __( 'VAT Invoice', 'kdna-events' ), 'tax_id_label' => __( 'VAT Number', 'kdna-events' ) ),
			'nz'    => array( 'label' => __( 'New Zealand', 'kdna-events' ),    'tax_label' => 'GST',       'rate' => '15.00', 'heading' => __( 'Tax Invoice', 'kdna-events' ), 'tax_id_label' => __( 'GST Number', 'kdna-events' ) ),
			'us'    => array( 'label' => __( 'United States', 'kdna-events' ),  'tax_label' => 'Sales Tax', 'rate' => '0.00',  'heading' => __( 'Sales Invoice', 'kdna-events' ), 'tax_id_label' => __( 'EIN', 'kdna-events' ) ),
			'other' => array( 'label' => __( 'Other', 'kdna-events' ),          'tax_label' => 'Tax',       'rate' => '0.00',  'heading' => __( 'Invoice', 'kdna-events' ), 'tax_id_label' => __( 'Tax ID', 'kdna-events' ) ),
		);
	}

	/**
	 * Return the jurisdiction-aware tax ID label.
	 *
	 * @return string
	 */
	public static function tax_id_label() {
		$jur = (string) get_option( 'kdna_events_invoice_jurisdiction', 'au' );
		$map = self::jurisdictions();
		return isset( $map[ $jur ] ) ? $map[ $jur ]['tax_id_label'] : __( 'Tax ID', 'kdna-events' );
	}

	/**
	 * Format an invoice number from its components.
	 *
	 * @param int $sequence The raw sequential integer.
	 * @return string
	 */
	public static function format_invoice_number( $sequence ) {
		$prefix  = (string) get_option( 'kdna_events_invoice_number_prefix', 'INV-' );
		$suffix  = (string) get_option( 'kdna_events_invoice_number_suffix', '' );
		$padding = max( 0, (int) get_option( 'kdna_events_invoice_number_padding', 5 ) );
		$body    = $padding > 0 ? str_pad( (string) max( 0, (int) $sequence ), $padding, '0', STR_PAD_LEFT ) : (string) $sequence;
		return $prefix . $body . $suffix;
	}

	/**
	 * Atomically allocate the next sequence number.
	 *
	 * Uses START TRANSACTION + SELECT ... FOR UPDATE on the autoload-
	 * false options row so two simultaneous paid bookings can never
	 * read the same current value. Falls back to a plain update when
	 * the wp_options engine does not support FOR UPDATE (it does on
	 * InnoDB, which is the WordPress default).
	 *
	 * @return int
	 */
	public static function next_sequence_number() {
		global $wpdb;

		$wpdb->query( 'START TRANSACTION' );
		$wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s FOR UPDATE",
				'kdna_events_invoice_current_sequence'
			)
		);

		$current = (int) get_option( 'kdna_events_invoice_current_sequence', 0 );
		$start   = max( 1, (int) get_option( 'kdna_events_invoice_number_start', 1 ) );
		$next    = max( $current + 1, $start );

		update_option( 'kdna_events_invoice_current_sequence', $next, false );
		$wpdb->query( 'COMMIT' );

		return $next;
	}

	/**
	 * Fetch the invoice row for a given order, or null.
	 *
	 * @param int $order_id Order ID.
	 * @return object|null
	 */
	public static function get_by_order( $order_id ) {
		global $wpdb;
		$table   = KDNA_Events_Invoices_DB::invoices_table();
		$row     = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $order_id
			)
		);
		return $row ? $row : null;
	}

	/**
	 * Fetch an invoice by its human invoice_number.
	 *
	 * @param string $number Formatted invoice number.
	 * @return object|null
	 */
	public static function get_by_number( $number ) {
		global $wpdb;
		$table = KDNA_Events_Invoices_DB::invoices_table();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE invoice_number = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(string) $number
			)
		);
		return $row ? $row : null;
	}

	/**
	 * Return the 10 most recent invoices (any status).
	 *
	 * @param int $limit Maximum rows to return.
	 * @return array<int,object>
	 */
	public static function get_latest( $limit = 10 ) {
		global $wpdb;
		$table = KDNA_Events_Invoices_DB::invoices_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY invoice_id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				max( 1, (int) $limit )
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Decide whether an order qualifies for an invoice.
	 *
	 * Paid events only (total > 0) and master switch on.
	 *
	 * @param object $order Order row.
	 * @return bool
	 */
	public static function order_needs_invoice( $order ) {
		if ( ! (bool) get_option( 'kdna_events_invoices_enabled', true ) ) {
			return false;
		}
		if ( ! $order || ! isset( $order->total ) ) {
			return false;
		}
		return (float) $order->total > 0.0;
	}

	/**
	 * Create the invoice DB row for a paid order.
	 *
	 * Snapshots the business details, tax rate and tax label so that
	 * regenerated PDFs keep their historical values even if the admin
	 * edits settings later. Short-circuits when the row already
	 * exists so generate() is safe to call repeatedly.
	 *
	 * @param int $order_id Order ID.
	 * @return object|WP_Error
	 */
	public static function create_invoice_record( $order_id ) {
		$existing = self::get_by_order( $order_id );
		if ( $existing ) {
			return $existing;
		}

		$order = KDNA_Events_Orders::get_order( (int) $order_id );
		if ( ! $order ) {
			return new WP_Error( 'kdna_events_invoice_missing_order', __( 'Order not found.', 'kdna-events' ) );
		}
		if ( ! self::order_needs_invoice( $order ) ) {
			return new WP_Error( 'kdna_events_invoice_not_applicable', __( 'Invoices are disabled or this order is free.', 'kdna-events' ) );
		}

		$options = self::get_options();
		$sequence = self::next_sequence_number();
		$number   = self::format_invoice_number( $sequence );

		$tax_registered = ! empty( $options['kdna_events_invoice_tax_registered'] );
		$tax_rate       = $tax_registered ? (float) $options['kdna_events_invoice_tax_rate'] : 0.0;
		$tax_label      = (string) $options['kdna_events_invoice_tax_label'];

		$total = round( (float) $order->total, 2 );
		$subtotal = $tax_rate > 0 ? round( $total / ( 1 + ( $tax_rate / 100 ) ), 2, PHP_ROUND_HALF_EVEN ) : $total;
		$tax_amount = round( $total - $subtotal, 2, PHP_ROUND_HALF_EVEN );

		$snapshot = array(
			'legal_name'    => (string) $options['kdna_events_invoice_business_legal_name'],
			'trading_name'  => (string) $options['kdna_events_invoice_business_trading_name'],
			'tax_id'        => (string) $options['kdna_events_invoice_tax_id'],
			'tax_id_label'  => self::tax_id_label(),
			'address'       => (string) $options['kdna_events_invoice_business_address'],
			'email'         => (string) $options['kdna_events_invoice_business_email'],
			'phone'         => (string) $options['kdna_events_invoice_business_phone'],
			'website'       => (string) $options['kdna_events_invoice_business_website'],
			'heading'       => (string) $options['kdna_events_invoice_document_heading'],
			'jurisdiction'  => (string) $options['kdna_events_invoice_jurisdiction'],
		);

		$issued_at  = self::resolve_issue_date( $order, (string) $options['kdna_events_invoice_date_source'] );
		$now_mysql  = current_time( 'mysql' );

		global $wpdb;
		$table = KDNA_Events_Invoices_DB::invoices_table();
		$wpdb->insert(
			$table,
			array(
				'invoice_number'    => $number,
				'sequence_number'   => $sequence,
				'order_id'          => (int) $order->order_id,
				'issued_at'         => $issued_at,
				'tax_rate_applied'  => $tax_registered ? $tax_rate : 0,
				'tax_label_applied' => $tax_label,
				'subtotal_ex_tax'   => $subtotal,
				'tax_amount'        => $tax_amount,
				'total_inc_tax'     => $total,
				'currency'          => isset( $order->currency ) ? (string) $order->currency : (string) get_option( 'kdna_events_default_currency', 'AUD' ),
				'business_snapshot' => wp_json_encode( $snapshot ),
				'status'            => 'issued',
				'notes'             => '',
				'created_at'        => $now_mysql,
				'updated_at'        => $now_mysql,
			),
			array( '%s', '%d', '%d', '%s', '%f', '%s', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return self::get_by_order( $order_id );
	}

	/**
	 * Generate the PDF for an order. Creates the invoice record on
	 * first call, returns cached PDF binary on subsequent calls.
	 *
	 * @param int $order_id Order ID.
	 * @return string|WP_Error PDF binary or error.
	 */
	public static function generate( $order_id ) {
		$invoice = self::get_by_order( $order_id );
		if ( ! $invoice ) {
			$invoice = self::create_invoice_record( $order_id );
			if ( is_wp_error( $invoice ) ) {
				return $invoice;
			}
		}
		$html = self::render_html( $invoice );
		if ( is_wp_error( $html ) ) {
			return $html;
		}
		return self::render_pdf( $html );
	}

	/**
	 * Resolve the invoice issue date per the admin setting.
	 *
	 * @param object $order Order row.
	 * @param string $source 'booking' or 'payment'.
	 * @return string MySQL datetime.
	 */
	protected static function resolve_issue_date( $order, $source ) {
		if ( 'payment' === $source && ! empty( $order->paid_at ) ) {
			return (string) $order->paid_at;
		}
		if ( ! empty( $order->created_at ) ) {
			return (string) $order->created_at;
		}
		return current_time( 'mysql' );
	}

	/**
	 * Build the template context + render the HTML string that will
	 * be handed to Dompdf.
	 *
	 * @param object $invoice Invoice row.
	 * @return string|WP_Error
	 */
	public static function render_html( $invoice ) {
		if ( ! $invoice ) {
			return new WP_Error( 'kdna_events_invoice_missing', __( 'Invoice record missing.', 'kdna-events' ) );
		}
		$order = KDNA_Events_Orders::get_order( (int) $invoice->order_id );
		$context = self::build_context( $invoice, $order );

		$design = self::get_design_tokens();

		ob_start();
		include KDNA_EVENTS_PATH . 'templates/invoices/invoice.php';
		$html = (string) ob_get_clean();

		/**
		 * Filter the raw HTML before Dompdf ingests it.
		 *
		 * @param string $html    Rendered template output.
		 * @param array  $context Merge-tag context.
		 * @param object $invoice Invoice record.
		 */
		return (string) apply_filters( 'kdna_events_invoice_html', $html, $context, $invoice );
	}

	/**
	 * Run the HTML through Dompdf and return the binary.
	 *
	 * @param string $html Raw HTML.
	 * @return string|WP_Error PDF binary or error.
	 */
	public static function render_pdf( $html ) {
		if ( ! class_exists( '\\Dompdf\\Dompdf' ) ) {
			return new WP_Error( 'kdna_events_dompdf_missing', __( 'Dompdf is not available. Run composer install in the plugin folder.', 'kdna-events' ) );
		}

		$page_size = (string) get_option( 'kdna_events_invoice_design_page_size', 'A4' );
		if ( ! in_array( $page_size, array( 'A4', 'Letter' ), true ) ) {
			$page_size = 'A4';
		}

		$options = new \Dompdf\Options();
		$options->set( 'isRemoteEnabled', true );
		$options->set( 'isHtml5ParserEnabled', true );
		$options->set( 'defaultFont', 'helvetica' );

		/**
		 * Filter the Dompdf options object before rendering.
		 *
		 * @param \Dompdf\Options $options
		 */
		$options = apply_filters( 'kdna_events_invoice_pdf_options', $options );

		$dompdf = new \Dompdf\Dompdf( $options );
		$dompdf->setPaper( $page_size, 'portrait' );
		$dompdf->loadHtml( $html, 'UTF-8' );
		$dompdf->render();

		return (string) $dompdf->output();
	}

	/**
	 * Produce a PDF rendered from dummy data so the admin can preview
	 * + download a sample from the Tax Invoices settings tab.
	 *
	 * @return string PDF binary.
	 */
	public static function generate_sample() {
		$invoice = self::sample_invoice();
		$order   = self::sample_order();
		$context = self::build_context( $invoice, $order );
		$design  = self::get_design_tokens();

		ob_start();
		include KDNA_EVENTS_PATH . 'templates/invoices/invoice.php';
		$html = (string) ob_get_clean();

		$html = (string) apply_filters( 'kdna_events_invoice_html', $html, $context, $invoice );

		return self::render_pdf( $html );
	}

	/**
	 * Sample invoice row used by the preview + sample-download flow.
	 *
	 * @return object
	 */
	public static function sample_invoice() {
		$options   = self::get_options();
		$tax_rate  = ! empty( $options['kdna_events_invoice_tax_registered'] ) ? (float) $options['kdna_events_invoice_tax_rate'] : 0.0;
		$total     = 14212.00;
		$subtotal  = $tax_rate > 0 ? round( $total / ( 1 + ( $tax_rate / 100 ) ), 2, PHP_ROUND_HALF_EVEN ) : $total;
		$tax_amt   = round( $total - $subtotal, 2, PHP_ROUND_HALF_EVEN );
		$currency  = (string) get_option( 'kdna_events_default_currency', 'AUD' );

		$snapshot = array(
			'legal_name'   => (string) $options['kdna_events_invoice_business_legal_name'] ?: __( 'Event company name', 'kdna-events' ),
			'trading_name' => (string) $options['kdna_events_invoice_business_trading_name'],
			'tax_id'       => (string) $options['kdna_events_invoice_tax_id'] ?: '26 356 458 985',
			'tax_id_label' => self::tax_id_label(),
			'address'      => (string) $options['kdna_events_invoice_business_address'] ?: __( 'Event company address', 'kdna-events' ),
			'email'        => (string) $options['kdna_events_invoice_business_email'] ?: 'info@example.com',
			'phone'        => (string) $options['kdna_events_invoice_business_phone'] ?: '(02) 6587 6958',
			'website'      => (string) $options['kdna_events_invoice_business_website'] ?: 'example.com',
			'heading'      => (string) $options['kdna_events_invoice_document_heading'],
			'jurisdiction' => (string) $options['kdna_events_invoice_jurisdiction'],
		);

		return (object) array(
			'invoice_id'        => 0,
			'invoice_number'    => self::format_invoice_number( max( 1, (int) get_option( 'kdna_events_invoice_number_start', 1 ) ) ),
			'sequence_number'   => max( 1, (int) get_option( 'kdna_events_invoice_number_start', 1 ) ),
			'order_id'          => 0,
			'issued_at'         => current_time( 'mysql' ),
			'tax_rate_applied'  => $tax_rate,
			'tax_label_applied' => (string) $options['kdna_events_invoice_tax_label'],
			'subtotal_ex_tax'   => $subtotal,
			'tax_amount'        => $tax_amt,
			'total_inc_tax'     => $total,
			'currency'          => $currency,
			'business_snapshot' => wp_json_encode( $snapshot ),
			'status'            => 'issued',
			'notes'             => '',
			'created_at'        => current_time( 'mysql' ),
			'updated_at'        => current_time( 'mysql' ),
		);
	}

	/**
	 * Sample order row matching sample_invoice() values.
	 *
	 * @return object
	 */
	public static function sample_order() {
		return (object) array(
			'order_id'              => 0,
			'order_reference'       => 'PREVIEW-0001',
			'event_id'              => 0,
			'purchaser_name'        => 'John Doe',
			'purchaser_email'       => 'test@test.com',
			'purchaser_phone'       => '0412 345 678',
			'quantity'              => 3,
			'subtotal'              => 14212.00,
			'total'                 => 14212.00,
			'currency'              => (string) get_option( 'kdna_events_default_currency', 'AUD' ),
			'status'                => 'paid',
			'created_at'            => current_time( 'mysql' ),
			'paid_at'               => current_time( 'mysql' ),
			'stripe_payment_intent' => 'pi_preview_sample',
		);
	}

	/**
	 * Build the merge-tag + template context for an invoice render.
	 *
	 * @param object      $invoice Invoice row.
	 * @param object|null $order   Order row (may be null for sample).
	 * @return array
	 */
	public static function build_context( $invoice, $order ) {
		$snapshot = array();
		if ( ! empty( $invoice->business_snapshot ) ) {
			$decoded = json_decode( (string) $invoice->business_snapshot, true );
			if ( is_array( $decoded ) ) {
				$snapshot = $decoded;
			}
		}

		$event_id = $order && isset( $order->event_id ) ? (int) $order->event_id : 0;
		$event_title = $event_id ? (string) get_the_title( $event_id ) : __( 'Sample event', 'kdna-events' );
		$event_start = $event_id ? (string) get_post_meta( $event_id, '_kdna_event_start', true ) : '';
		$event_date  = '' !== $event_start ? kdna_events_format_datetime( $event_start, 'j F Y', $event_id ) : '';
		$event_subtitle = $event_id ? (string) get_post_meta( $event_id, '_kdna_event_subtitle', true ) : '';

		$currency = (string) $invoice->currency;

		$line_item_tpl = (string) get_option( 'kdna_events_invoice_line_item_template', '{quantity} x Ticket to {event_title}' );
		$quantity      = $order && isset( $order->quantity ) ? (int) $order->quantity : 1;
		$unit_total    = $quantity > 0 ? round( (float) $invoice->total_inc_tax / $quantity, 2, PHP_ROUND_HALF_EVEN ) : (float) $invoice->total_inc_tax;
		$unit_ex_tax   = (float) $invoice->tax_rate_applied > 0
			? round( $unit_total / ( 1 + ( (float) $invoice->tax_rate_applied / 100 ) ), 2, PHP_ROUND_HALF_EVEN )
			: $unit_total;

		$tags = array(
			'quantity'        => (string) $quantity,
			'event_title'     => $event_title,
			'event_subtitle'  => $event_subtitle,
			'event_date'      => $event_date,
			'order_ref'       => $order && isset( $order->order_reference ) ? (string) $order->order_reference : '',
			'invoice_number'  => (string) $invoice->invoice_number,
			'purchaser_name'  => $order && isset( $order->purchaser_name ) ? (string) $order->purchaser_name : '',
			'purchaser_email' => $order && isset( $order->purchaser_email ) ? (string) $order->purchaser_email : '',
		);

		$line_description = kdna_events_render_merge_tags( $line_item_tpl, $tags );

		$payload = array(
			'invoice'   => $invoice,
			'order'     => $order,
			'snapshot'  => $snapshot,
			'currency'  => $currency,
			'line_items' => array(
				array(
					'description'     => $line_description,
					'quantity'        => $quantity,
					'unit_total'      => $unit_total,
					'unit_ex_tax'     => $unit_ex_tax,
					'line_total'      => (float) $invoice->total_inc_tax,
					'line_ex_tax'     => (float) $invoice->subtotal_ex_tax,
				),
			),
			'tags'      => $tags,
		);

		/**
		 * Filter the invoice render payload before templates consume it.
		 *
		 * @param array  $payload
		 * @param object $invoice
		 * @param object|null $order
		 */
		return (array) apply_filters( 'kdna_events_invoice_payload', $payload, $invoice, $order );
	}

	/**
	 * Resolve the design tokens, honouring Inherit from Email Design
	 * toggles so brand edits flow through without duplication.
	 *
	 * @return array
	 */
	public static function get_design_tokens() {
		$inv     = self::get_options();
		$email   = class_exists( 'KDNA_Events_Settings' ) ? KDNA_Events_Settings::get_email_design() : array();

		$logo_id = ! empty( $inv['kdna_events_invoice_design_inherit_logo'] )
			? (int) ( $email['kdna_events_email_logo_id'] ?? 0 )
			: (int) $inv['kdna_events_invoice_design_logo_id'];
		$logo_url = $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'medium' ) : '';

		$primary = ! empty( $inv['kdna_events_invoice_design_inherit_colours'] )
			? (string) ( $email['kdna_events_email_color_primary'] ?? '#1A1A1A' )
			: (string) $inv['kdna_events_invoice_design_color_primary'];
		$accent = ! empty( $inv['kdna_events_invoice_design_inherit_colours'] )
			? (string) ( $email['kdna_events_email_color_accent'] ?? '#F07759' )
			: (string) $inv['kdna_events_invoice_design_color_accent'];

		return array(
			'logo_id'        => $logo_id,
			'logo_url'       => $logo_url,
			'logo_width'     => (int) $inv['kdna_events_invoice_design_logo_width'],
			'primary'        => $primary,
			'accent'         => $accent,
			'heading_font'   => (string) $inv['kdna_events_invoice_design_heading_font'],
			'body_font'      => (string) $inv['kdna_events_invoice_design_body_font'],
			'heading_size'   => (int) $inv['kdna_events_invoice_design_heading_size'],
			'body_size'      => (int) $inv['kdna_events_invoice_design_body_size'],
			'page_size'      => (string) $inv['kdna_events_invoice_design_page_size'],
			'page_margin'    => (int) $inv['kdna_events_invoice_design_page_margin'],
			'show_paid_stamp' => ! empty( $inv['kdna_events_invoice_design_show_paid_stamp'] ),
		);
	}

	/**
	 * Hook into kdna_events_email_attachments to inject the invoice
	 * PDF onto booking confirmation emails for paid orders only.
	 *
	 * @param array  $attachments Current attachments.
	 * @param int    $order_id    Order ID.
	 * @param string $email_type  Email type slug.
	 * @return array
	 */
	public static function attach_invoice_pdf( $attachments, $order_id, $email_type ) {
		if ( 'booking_confirmation' !== $email_type ) {
			return $attachments;
		}
		$order = KDNA_Events_Orders::get_order( (int) $order_id );
		if ( ! $order || ! self::order_needs_invoice( $order ) ) {
			return $attachments;
		}

		$binary = self::generate( (int) $order_id );
		if ( is_wp_error( $binary ) || '' === $binary ) {
			return $attachments;
		}

		$invoice = self::get_by_order( (int) $order_id );
		if ( ! $invoice ) {
			return $attachments;
		}

		$path = self::save_to_temp( $binary, self::safe_filename( $invoice->invoice_number ) );
		if ( $path ) {
			$attachments[] = $path;
		}
		return $attachments;
	}

	/**
	 * Guarantee the temp upload folder exists + write a PDF to it.
	 *
	 * @param string $binary  PDF binary.
	 * @param string $basename Filename (no directory, no extension).
	 * @return string|false Absolute path on success, false on error.
	 */
	public static function save_to_temp( $binary, $basename ) {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return false;
		}
		$dir = trailingslashit( $upload['basedir'] ) . self::TMP_SUBDIR;
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
			// Drop a silence file so the folder is not browsable.
			@file_put_contents( $dir . '/index.html', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,Generic.PHP.NoSilencedErrors.Discouraged
		}
		$token = wp_generate_password( 12, false, false );
		$path  = $dir . '/' . $basename . '-' . $token . '.pdf';
		$ok    = @file_put_contents( $path, $binary ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,Generic.PHP.NoSilencedErrors.Discouraged
		return false === $ok ? false : $path;
	}

	/**
	 * Safe filename fragment derived from an invoice number.
	 *
	 * @param string $number Invoice number.
	 * @return string
	 */
	public static function safe_filename( $number ) {
		$slug = sanitize_title( (string) $number );
		if ( '' === $slug ) {
			$slug = 'invoice';
		}
		return 'invoice-' . $slug;
	}

	/**
	 * Sweep the temp folder of files older than 24h. Runs daily.
	 *
	 * @return void
	 */
	public static function cleanup_tmp() {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return;
		}
		$dir = trailingslashit( $upload['basedir'] ) . self::TMP_SUBDIR;
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$cutoff = time() - DAY_IN_SECONDS;
		$iter   = @opendir( $dir ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		if ( ! $iter ) {
			return;
		}
		while ( false !== ( $entry = readdir( $iter ) ) ) {
			if ( '.' === $entry || '..' === $entry || 'index.html' === $entry ) {
				continue;
			}
			$path = $dir . '/' . $entry;
			if ( is_file( $path ) && filemtime( $path ) < $cutoff ) {
				@unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink,Generic.PHP.NoSilencedErrors.Discouraged
			}
		}
		closedir( $iter );
	}

	/**
	 * Generate a signed download token for an invoice number.
	 *
	 * Hash-based, stateless, 24h validity. Embeds the expiry so
	 * verification is a hash comparison with no DB round trip.
	 *
	 * @param string $invoice_number Invoice number.
	 * @param int    $lifetime       Lifetime in seconds.
	 * @return string token 'expires.hash'
	 */
	public static function generate_download_token( $invoice_number, $lifetime = 0 ) {
		$lifetime = $lifetime > 0 ? (int) $lifetime : self::TOKEN_LIFETIME;
		$expires  = time() + $lifetime;
		$payload  = (string) $invoice_number . '|' . $expires;
		$hash     = hash_hmac( 'sha256', $payload, self::token_secret() );
		return $expires . '.' . $hash;
	}

	/**
	 * Verify a token for an invoice number.
	 *
	 * @param string $invoice_number Invoice number.
	 * @param string $token          Token from the query string.
	 * @return bool
	 */
	public static function verify_download_token( $invoice_number, $token ) {
		if ( ! is_string( $token ) || false === strpos( $token, '.' ) ) {
			return false;
		}
		list( $expires, $hash ) = array_pad( explode( '.', $token, 2 ), 2, '' );
		$expires = (int) $expires;
		if ( $expires <= 0 || $expires < time() ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', (string) $invoice_number . '|' . $expires, self::token_secret() );
		return hash_equals( $expected, (string) $hash );
	}

	/**
	 * Secret used to sign invoice download tokens.
	 *
	 * @return string
	 */
	protected static function token_secret() {
		$salt = wp_salt( 'auth' );
		return 'kdna-events-invoice|' . $salt;
	}

	/**
	 * Register the REST route for invoice downloads.
	 *
	 * @return void
	 */
	public static function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/invoice/(?P<number>[A-Za-z0-9_\-\.]+)\.pdf',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => array( __CLASS__, 'rest_download_invoice' ),
				'args'                => array(
					'number' => array( 'type' => 'string' ),
					't'      => array( 'type' => 'string' ),
				),
			)
		);
	}

	/**
	 * REST callback: stream an invoice PDF to an authorised caller.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_download_invoice( $request ) {
		$number  = sanitize_text_field( (string) $request->get_param( 'number' ) );
		$token   = sanitize_text_field( (string) $request->get_param( 't' ) );
		$invoice = self::get_by_number( $number );
		if ( ! $invoice ) {
			return new WP_Error( 'kdna_events_invoice_not_found', __( 'Invoice not found.', 'kdna-events' ), array( 'status' => 404 ) );
		}

		if ( ! self::request_can_download( $invoice, $token ) ) {
			return new WP_Error( 'kdna_events_invoice_forbidden', __( 'You do not have permission to download this invoice.', 'kdna-events' ), array( 'status' => 403 ) );
		}

		$binary = self::generate( (int) $invoice->order_id );
		if ( is_wp_error( $binary ) ) {
			return $binary;
		}

		if ( ! headers_sent() ) {
			header( 'Content-Type: application/pdf' );
			header( 'Content-Disposition: attachment; filename="' . self::safe_filename( $invoice->invoice_number ) . '.pdf"' );
			header( 'Content-Length: ' . strlen( $binary ) );
			header( 'Cache-Control: private, max-age=0, must-revalidate' );
		}
		echo $binary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Authorisation check for an invoice download.
	 *
	 * Allowed when: the current user can manage_options, the current
	 * user's ID or email matches the underlying order, or the signed
	 * token validates.
	 *
	 * @param object $invoice Invoice record.
	 * @param string $token   Optional token.
	 * @return bool
	 */
	public static function request_can_download( $invoice, $token = '' ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		if ( '' !== $token && self::verify_download_token( (string) $invoice->invoice_number, $token ) ) {
			return true;
		}
		$order = KDNA_Events_Orders::get_order( (int) $invoice->order_id );
		if ( ! $order ) {
			return false;
		}
		$user = wp_get_current_user();
		if ( $user && $user->ID ) {
			if ( (int) $user->ID === (int) ( $order->user_id ?? 0 ) ) {
				return true;
			}
			if ( strtolower( (string) $user->user_email ) === strtolower( (string) $order->purchaser_email ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * AJAX: render a live preview of the invoice PDF as inline HTML.
	 * Returns the rendered HTML (not a PDF) so the admin iframe can
	 * embed it cheaply without a second request per keystroke.
	 *
	 * @return void
	 */
	public static function ajax_preview_invoice() {
		check_ajax_referer( self::PREVIEW_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kdna-events' ) ), 403 );
		}

		// Overlay unsaved form values.
		$schema = self::options_schema();
		foreach ( $schema as $name => $def ) {
			if ( ! isset( $_POST[ $name ] ) ) {
				continue;
			}
			$raw = wp_unslash( $_POST[ $name ] );
			if ( 'boolean' === $def['type'] ) {
				update_option( $name, ! empty( $raw ), false );
				continue;
			}
			if ( 'integer' === $def['type'] ) {
				update_option( $name, absint( $raw ), false );
				continue;
			}
			update_option( $name, sanitize_textarea_field( (string) $raw ), false );
		}

		$invoice = self::sample_invoice();
		$order   = self::sample_order();
		$context = self::build_context( $invoice, $order );
		$design  = self::get_design_tokens();

		ob_start();
		include KDNA_EVENTS_PATH . 'templates/invoices/invoice.php';
		$html = (string) ob_get_clean();

		// Inject screen-only padding so the iframe preview visually
		// matches the page margin that Dompdf will apply in the real
		// PDF. @media screen is ignored by Dompdf so this is a no-op
		// at render time.
		$margin = max( 5, min( 40, (int) get_option( 'kdna_events_invoice_design_page_margin', 20 ) ) );
		$padding_css = sprintf(
			'<style>@media screen { html, body { margin: 0; padding: 0; background: #eee; } body { padding: %1$dmm !important; box-sizing: border-box; background: #ffffff !important; min-height: 297mm; position: relative; } .inv-footer { position: absolute !important; left: 0; right: 0; bottom: %1$dmm !important; padding: 0 %1$dmm !important; } .inv-paid-stamp { position: absolute !important; } }</style>',
			$margin
		);
		$html = str_replace( '</head>', $padding_css . '</head>', $html );

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * AJAX: regenerate an existing invoice's PDF (snapshot preserved).
	 * Does not change the invoice number.
	 *
	 * @return void
	 */
	/**
	 * Show a dashboard notice when invoicing is on but the business
	 * identity fields have not been filled in. Runs on admin_notices.
	 *
	 * @return void
	 */
	public static function incomplete_settings_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! (bool) get_option( 'kdna_events_invoices_enabled', true ) ) {
			return;
		}
		$legal  = trim( (string) get_option( 'kdna_events_invoice_business_legal_name', '' ) );
		$tax_id = trim( (string) get_option( 'kdna_events_invoice_tax_id', '' ) );
		$addr   = trim( (string) get_option( 'kdna_events_invoice_business_address', '' ) );
		if ( '' !== $legal && '' !== $tax_id && '' !== $addr ) {
			return;
		}
		$settings_url = add_query_arg(
			array(
				'page' => 'kdna-events-settings',
				'tab'  => 'tax_invoices',
			),
			admin_url( 'admin.php' )
		);
		printf(
			'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
			esc_html__( 'KDNA Events:', 'kdna-events' ),
			esc_html__( 'Invoicing is enabled but Business Legal Name, Tax ID or Registered Address is missing. Invoices will render incomplete until those fields are set.', 'kdna-events' ),
			esc_url( $settings_url ),
			esc_html__( 'Open Tax Invoices settings', 'kdna-events' )
		);
	}

	public static function ajax_regenerate_invoice() {
		check_ajax_referer( self::REGEN_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kdna-events' ) ), 403 );
		}
		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing order ID.', 'kdna-events' ) ), 400 );
		}
		$binary = self::generate( $order_id );
		if ( is_wp_error( $binary ) ) {
			wp_send_json_error( array( 'message' => $binary->get_error_message() ), 400 );
		}
		$invoice = self::get_by_order( $order_id );
		wp_send_json_success(
			array(
				'message'        => __( 'Invoice PDF regenerated from its stored snapshot. Number unchanged.', 'kdna-events' ),
				'invoice_number' => $invoice ? (string) $invoice->invoice_number : '',
			)
		);
	}
}
