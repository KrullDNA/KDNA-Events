<?php
/**
 * Settings page for the PDF Tickets add-on.
 *
 * Registers a submenu under the core Events menu, renders the
 * control sections per Section 5 of the brief, exposes the live
 * preview AJAX endpoint + the Download sample serving endpoint.
 *
 * @package KDNA_Events_PDF_Tickets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page controller.
 */
class KDNA_Events_PDF_Settings {

	const OPTION_GROUP  = 'kdna_events_pdf';
	const PAGE_SLUG     = 'kdna-events-pdf-settings';
	const PARENT_SLUG   = 'kdna-events';
	const PREVIEW_AJAX  = 'kdna_events_pdf_preview';
	const SAMPLE_AJAX   = 'kdna_events_pdf_sample_download';
	const DEBUG_AJAX    = 'kdna_events_pdf_font_debug';

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
	 * Register menu, settings and assets.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_' . self::PREVIEW_AJAX, array( $this, 'ajax_preview' ) );
		add_action( 'wp_ajax_' . self::SAMPLE_AJAX, array( $this, 'ajax_sample' ) );
		add_action( 'wp_ajax_' . self::DEBUG_AJAX, array( $this, 'ajax_font_debug' ) );
		add_filter( 'upload_mimes', array( $this, 'allow_font_uploads' ) );
	}

	/**
	 * Allow TTF / OTF font uploads in the WordPress Media Library so
	 * admins can drop a Google Fonts family in and paste the URL
	 * into the PDF settings page.
	 *
	 * @param array $mimes
	 * @return array
	 */
	public function allow_font_uploads( $mimes ) {
		$mimes['ttf'] = 'font/ttf';
		$mimes['otf'] = 'font/otf';
		return $mimes;
	}

	/**
	 * Add the submenu under the core Events menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'PDF Tickets', 'kdna-events-pdf-tickets' ),
			__( 'PDF Tickets', 'kdna-events-pdf-tickets' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Curated font dropdown, mirrors the core Email Design options.
	 *
	 * The labels are identical to the core email font picker so the
	 * two settings tabs feel like one connected experience.
	 *
	 * @return array<string,string>
	 */
	public static function font_options() {
		if ( class_exists( 'KDNA_Events_Settings' ) && method_exists( 'KDNA_Events_Settings', 'email_font_options' ) ) {
			$core = KDNA_Events_Settings::email_font_options();
			if ( is_array( $core ) && ! empty( $core ) ) {
				return $core;
			}
		}
		// Fallback list if core Brief A is not on this install.
		return array(
			'google:Inter'            => __( 'Inter (Google, recommended)', 'kdna-events-pdf-tickets' ),
			'google:Roboto'           => __( 'Roboto (Google)', 'kdna-events-pdf-tickets' ),
			'google:Poppins'          => __( 'Poppins (Google)', 'kdna-events-pdf-tickets' ),
			'google:Montserrat'       => __( 'Montserrat (Google)', 'kdna-events-pdf-tickets' ),
			'google:Open Sans'        => __( 'Open Sans (Google)', 'kdna-events-pdf-tickets' ),
			'google:Lato'             => __( 'Lato (Google)', 'kdna-events-pdf-tickets' ),
			'google:Nunito'           => __( 'Nunito (Google)', 'kdna-events-pdf-tickets' ),
			'google:Work Sans'        => __( 'Work Sans (Google)', 'kdna-events-pdf-tickets' ),
			'google:DM Sans'          => __( 'DM Sans (Google)', 'kdna-events-pdf-tickets' ),
			'google:Manrope'          => __( 'Manrope (Google)', 'kdna-events-pdf-tickets' ),
			'google:Playfair Display' => __( 'Playfair Display (Google, serif)', 'kdna-events-pdf-tickets' ),
			'google:Merriweather'     => __( 'Merriweather (Google, serif)', 'kdna-events-pdf-tickets' ),
			'system:arial'            => __( 'Arial (system)', 'kdna-events-pdf-tickets' ),
			'system:helvetica'        => __( 'Helvetica (system)', 'kdna-events-pdf-tickets' ),
			'system:georgia'          => __( 'Georgia (serif, system)', 'kdna-events-pdf-tickets' ),
			'system:verdana'          => __( 'Verdana (system)', 'kdna-events-pdf-tickets' ),
			'system:tahoma'           => __( 'Tahoma (system)', 'kdna-events-pdf-tickets' ),
			'system:trebuchet'        => __( 'Trebuchet MS (system)', 'kdna-events-pdf-tickets' ),
			'system:times'            => __( 'Times New Roman (serif, system)', 'kdna-events-pdf-tickets' ),
		);
	}

	/**
	 * Resolve a font dropdown value to a Dompdf-safe font name.
	 *
	 * Dompdf only renders fonts registered in its font cache. The
	 * defaults that ship are Helvetica, Times and Courier. Google
	 * fonts and most system fonts are not available unless the TTF
	 * is pre-registered. We therefore collapse every choice to one
	 * of those three built-ins so the PDF always renders, and call
	 * out the fallback behaviour in the field help text.
	 *
	 * @param string $value Dropdown value (e.g. 'google:Inter').
	 * @return string
	 */
	public static function resolve_pdf_font( $value ) {
		$value = (string) $value;
		// Serif Google / system fonts collapse to Times.
		$serif_tokens = array( 'google:Playfair Display', 'google:Merriweather', 'system:georgia', 'system:times', 'times' );
		if ( in_array( $value, $serif_tokens, true ) ) {
			return 'times';
		}
		// Monospace.
		if ( 'courier' === $value ) {
			return 'courier';
		}
		// Everything else is sans-serif and falls back to Helvetica.
		return 'helvetica';
	}

	/**
	 * Option schema used for sanitiser dispatch + defaults.
	 *
	 * @return array<string,array{type:string,default:mixed}>
	 */
	public static function schema() {
		return array(
			// Inheritance toggles.
			'kdna_events_pdf_inherit_logo_id'              => array( 'type' => 'boolean', 'default' => true ),
			'kdna_events_pdf_inherit_header_bg'            => array( 'type' => 'boolean', 'default' => true ),
			'kdna_events_pdf_inherit_default_event_image' => array( 'type' => 'boolean', 'default' => true ),
			'kdna_events_pdf_inherit_color_primary'        => array( 'type' => 'boolean', 'default' => true ),
			'kdna_events_pdf_inherit_color_accent'         => array( 'type' => 'boolean', 'default' => true ),
			'kdna_events_pdf_inherit_business_name'        => array( 'type' => 'boolean', 'default' => true ),
			'kdna_events_pdf_inherit_support_email'        => array( 'type' => 'boolean', 'default' => true ),
			'kdna_events_pdf_inherit_support_phone'        => array( 'type' => 'boolean', 'default' => true ),

			// Brand overrides.
			'kdna_events_pdf_logo_id'              => array( 'type' => 'integer', 'default' => 0 ),
			'kdna_events_pdf_logo_width'           => array( 'type' => 'integer', 'default' => 140 ),
			'kdna_events_pdf_logo_align'           => array( 'type' => 'string',  'default' => 'center' ),
			'kdna_events_pdf_header_bg'            => array( 'type' => 'string',  'default' => '#2E75B6' ),
			'kdna_events_pdf_header_height'        => array( 'type' => 'integer', 'default' => 100 ),
			'kdna_events_pdf_default_event_image' => array( 'type' => 'integer', 'default' => 0 ),

			// Colours.
			'kdna_events_pdf_color_primary'        => array( 'type' => 'string',  'default' => '#2E75B6' ),
			'kdna_events_pdf_color_accent'         => array( 'type' => 'string',  'default' => '#F07759' ),
			'kdna_events_pdf_page_bg'              => array( 'type' => 'string',  'default' => '#FFFFFF' ),
			'kdna_events_pdf_heading_color'        => array( 'type' => 'string',  'default' => '#1A1A1A' ),
			'kdna_events_pdf_body_color'           => array( 'type' => 'string',  'default' => '#333333' ),
			'kdna_events_pdf_muted_color'          => array( 'type' => 'string',  'default' => '#888888' ),
			'kdna_events_pdf_divider_color'        => array( 'type' => 'string',  'default' => '#E5E5E5' ),

			// Typography.
			'kdna_events_pdf_heading_font'         => array( 'type' => 'string',  'default' => 'google:Inter' ),
			'kdna_events_pdf_heading_font_url'     => array( 'type' => 'string',  'default' => '' ),
			'kdna_events_pdf_body_font_url'        => array( 'type' => 'string',  'default' => '' ),
			'kdna_events_pdf_heading_size'         => array( 'type' => 'integer', 'default' => 20 ),
			'kdna_events_pdf_body_font'            => array( 'type' => 'string',  'default' => 'google:Inter' ),
			'kdna_events_pdf_body_size'            => array( 'type' => 'integer', 'default' => 11 ),
			'kdna_events_pdf_code_font'            => array( 'type' => 'string',  'default' => 'courier' ),
			'kdna_events_pdf_code_size'            => array( 'type' => 'integer', 'default' => 36 ),

			// Barcode.
			'kdna_events_pdf_barcode_type'         => array( 'type' => 'string',  'default' => 'code128' ),
			'kdna_events_pdf_barcode_width'        => array( 'type' => 'integer', 'default' => 80 ),
			'kdna_events_pdf_barcode_height'       => array( 'type' => 'integer', 'default' => 20 ),
			'kdna_events_pdf_barcode_show_text'    => array( 'type' => 'boolean', 'default' => true ),

			// Layout.
			'kdna_events_pdf_page_size'            => array( 'type' => 'string',  'default' => 'A4' ),
			'kdna_events_pdf_page_orientation'     => array( 'type' => 'string',  'default' => 'portrait' ),
			'kdna_events_pdf_page_margin'          => array( 'type' => 'integer', 'default' => 15 ),
			'kdna_events_pdf_tickets_per_order'    => array( 'type' => 'string',  'default' => 'combined' ),

			// Footer.
			'kdna_events_pdf_show_footer'          => array( 'type' => 'boolean', 'default' => true ),
			'kdna_events_pdf_business_name'        => array( 'type' => 'string',  'default' => '' ),
			'kdna_events_pdf_website_url'          => array( 'type' => 'string',  'default' => '' ),
			'kdna_events_pdf_support_email'        => array( 'type' => 'string',  'default' => '' ),
			'kdna_events_pdf_support_phone'        => array( 'type' => 'string',  'default' => '' ),
			'kdna_events_pdf_show_timestamp'       => array( 'type' => 'boolean', 'default' => false ),

			// Terms + button label.
			'kdna_events_pdf_terms_text'           => array( 'type' => 'string',  'default' => '' ),
			'kdna_events_pdf_button_label'         => array( 'type' => 'string',  'default' => 'Download Ticket PDF' ),
		);
	}

	/**
	 * Register every option with the Settings API.
	 *
	 * @return void
	 */
	public function register_settings() {
		foreach ( self::schema() as $name => $def ) {
			register_setting(
				self::OPTION_GROUP,
				$name,
				array(
					'type'              => 'string' === $def['type'] ? 'string' : $def['type'],
					'sanitize_callback' => array( $this, 'sanitize_value' ),
					'default'           => $def['default'],
				)
			);
		}
	}

	/**
	 * Sanitiser dispatcher keyed off the schema.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public function sanitize_value( $value ) {
		$option = '';
		if ( isset( $GLOBALS['wp_current_filter'] ) && is_array( $GLOBALS['wp_current_filter'] ) ) {
			foreach ( $GLOBALS['wp_current_filter'] as $filter ) {
				if ( 0 === strpos( (string) $filter, 'sanitize_option_' ) ) {
					$option = substr( (string) $filter, strlen( 'sanitize_option_' ) );
					break;
				}
			}
		}
		$schema = self::schema();
		if ( '' === $option || ! isset( $schema[ $option ] ) ) {
			return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		}
		$type = $schema[ $option ]['type'];
		switch ( $type ) {
			case 'boolean':
				return ! empty( $value );
			case 'integer':
				return max( 0, absint( $value ) );
			case 'string':
			default:
				$value = (string) $value;
				if ( 0 === strpos( $option, 'kdna_events_pdf_color_' )
					|| in_array( $option, array( 'kdna_events_pdf_header_bg', 'kdna_events_pdf_page_bg', 'kdna_events_pdf_heading_color', 'kdna_events_pdf_body_color', 'kdna_events_pdf_muted_color', 'kdna_events_pdf_divider_color' ), true ) ) {
					$value = trim( $value );
					if ( preg_match( '/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value ) ) {
						return strtoupper( $value );
					}
					return $schema[ $option ]['default'];
				}
				if ( 'kdna_events_pdf_support_email' === $option ) {
					return sanitize_email( $value );
				}
				if ( 'kdna_events_pdf_website_url' === $option ) {
					return esc_url_raw( $value );
				}
				if ( 'kdna_events_pdf_terms_text' === $option ) {
					return sanitize_textarea_field( $value );
				}
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Enqueue admin assets on the settings page only.
	 *
	 * @param string $hook
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE_SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_media();
		wp_enqueue_script( 'wp-color-picker' );
		wp_add_inline_script(
			'wp-color-picker',
			'window.kdnaEventsPdf = ' . wp_json_encode(
				array(
					'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
					'previewNonce' => wp_create_nonce( self::PREVIEW_AJAX ),
					'sampleNonce'  => wp_create_nonce( self::SAMPLE_AJAX ),
					'previewAction' => self::PREVIEW_AJAX,
					'sampleAction' => self::SAMPLE_AJAX,
				)
			) . ';'
		);
	}

	/**
	 * AJAX preview: render sample PDF, return as base64 data URI.
	 *
	 * @return void
	 */
	public function ajax_preview() {
		check_ajax_referer( self::PREVIEW_AJAX, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kdna-events-pdf-tickets' ) ), 403 );
		}
		// Overlay unsaved form values onto live options for the preview render.
		$schema = self::schema();
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

		$generator = new KDNA_Events_PDF_Generator();
		$pdf       = $generator->generate_sample();
		if ( '' === $pdf ) {
			wp_send_json_error( array( 'message' => __( 'Preview render failed.', 'kdna-events-pdf-tickets' ) ), 500 );
		}
		wp_send_json_success( array( 'data_uri' => 'data:application/pdf;base64,' . base64_encode( $pdf ) ) );
	}

	/**
	 * AJAX sample download: stream a sample PDF as a direct file.
	 *
	 * @return void
	 */
	public function ajax_sample() {
		check_ajax_referer( self::SAMPLE_AJAX, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'kdna-events-pdf-tickets' ), 403 );
		}
		$generator = new KDNA_Events_PDF_Generator();
		$pdf       = $generator->generate_sample();
		if ( '' === $pdf ) {
			wp_die( esc_html__( 'Sample render failed.', 'kdna-events-pdf-tickets' ), 500 );
		}
		if ( ! headers_sent() ) {
			header( 'Content-Type: application/pdf' );
			header( 'Content-Disposition: attachment; filename="kdna-events-ticket-sample.pdf"' );
			header( 'Content-Length: ' . strlen( $pdf ) );
		}
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * AJAX: run a full diagnostic on the font rendering path and
	 * return a human-readable HTML report. Used by the 'Debug fonts'
	 * button on the settings page.
	 *
	 * @return void
	 */
	public function ajax_font_debug() {
		check_ajax_referer( self::DEBUG_AJAX, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'kdna-events-pdf-tickets' ) ), 403 );
		}

		$heading_url = (string) get_option( 'kdna_events_pdf_heading_font_url', '' );
		$body_url    = (string) get_option( 'kdna_events_pdf_body_font_url', '' );
		$upload      = wp_upload_dir();
		$font_dir    = empty( $upload['error'] ) ? trailingslashit( $upload['basedir'] ) . 'kdna-events-pdf-fonts' : '';

		$generator = new KDNA_Events_PDF_Generator();
		$order     = (object) array(
			'order_id' => 0, 'order_reference' => 'DBG-0001', 'event_id' => 0,
			'purchaser_name' => 'Debug', 'purchaser_email' => 'debug@example.com',
			'quantity' => 1, 'total' => 1, 'currency' => 'AUD', 'status' => 'paid', 'created_at' => current_time( 'mysql' ),
		);
		$tickets   = array( (object) array( 'ticket_id' => 0, 'ticket_code' => 'DBG12345', 'attendee_name' => 'Debug', 'attendee_email' => '', 'event_id' => 0 ) );
		$context   = $generator->build_context_for_order( $order, $tickets );
		$html      = $generator->render_html( $context );

		// Render, capturing any error logged by Dompdf into an output buffer.
		$err_before = error_get_last();
		$pdf        = $generator->render_pdf( $html );
		$err_after  = error_get_last();
		$changed    = $err_after !== $err_before ? $err_after : null;

		// Check HEAD on each TTF URL.
		$probe = static function ( $url ) {
			if ( '' === $url ) {
				return null;
			}
			$r = wp_remote_head( $url, array( 'timeout' => 10, 'redirection' => 5, 'sslverify' => false ) );
			if ( is_wp_error( $r ) ) {
				return array( 'ok' => false, 'error' => $r->get_error_message() );
			}
			return array(
				'ok'           => true,
				'status'       => (int) wp_remote_retrieve_response_code( $r ),
				'content_type' => (string) wp_remote_retrieve_header( $r, 'content-type' ),
				'length'       => (string) wp_remote_retrieve_header( $r, 'content-length' ),
			);
		};
		$body_probe    = $probe( $body_url );
		$heading_probe = $probe( $heading_url );

		// List font cache dir contents.
		$cache_files = array();
		if ( '' !== $font_dir && is_dir( $font_dir ) ) {
			foreach ( scandir( $font_dir ) as $f ) {
				if ( '.' === $f || '..' === $f ) {
					continue;
				}
				$p = $font_dir . '/' . $f;
				if ( is_file( $p ) ) {
					$cache_files[] = array( 'name' => $f, 'size' => filesize( $p ) );
				}
			}
		}

		// Extract the @font-face block from the rendered HTML so we
		// can confirm the CSS is actually being emitted.
		$face_block = '';
		if ( preg_match_all( '/@font-face\s*\{[^}]*\}/i', $html, $m ) ) {
			$face_block = implode( "\n", $m[0] );
		}

		ob_start();
		?>
		<div style="font-family:ui-monospace,Menlo,monospace;font-size:12px;line-height:1.5;">
			<p><strong>Dompdf class loaded:</strong> <?php echo class_exists( '\\Dompdf\\Dompdf' ) ? 'YES' : 'NO'; ?></p>
			<p><strong>Font cache dir:</strong> <?php echo esc_html( $font_dir ); ?> &nbsp; <strong>writable:</strong> <?php echo ( $font_dir && is_writable( $font_dir ) ) ? 'YES' : 'NO'; ?></p>
			<p><strong>Body font URL:</strong> <?php echo esc_html( '' === $body_url ? '(not set)' : $body_url ); ?></p>
			<p><strong>Heading font URL:</strong> <?php echo esc_html( '' === $heading_url ? '(not set)' : $heading_url ); ?></p>
			<p><strong>PDF generated:</strong> <?php echo ( '' !== $pdf && substr( $pdf, 0, 4 ) === '%PDF' ) ? 'YES, ' . strlen( $pdf ) . ' bytes' : 'FAIL'; ?></p>
			<?php if ( $changed ) : ?>
				<p style="color:#b91c1c;"><strong>PHP error during render:</strong> <?php echo esc_html( (string) $changed['message'] ); ?> @ <?php echo esc_html( (string) $changed['file'] ); ?>:<?php echo esc_html( (string) $changed['line'] ); ?></p>
			<?php endif; ?>

			<p><strong>Body URL HEAD probe:</strong></p>
			<pre style="white-space:pre-wrap;background:#fff;padding:6px;border:1px solid #dcdcde;"><?php echo esc_html( wp_json_encode( $body_probe, JSON_PRETTY_PRINT ) ); ?></pre>

			<p><strong>Heading URL HEAD probe:</strong></p>
			<pre style="white-space:pre-wrap;background:#fff;padding:6px;border:1px solid #dcdcde;"><?php echo esc_html( wp_json_encode( $heading_probe, JSON_PRETTY_PRINT ) ); ?></pre>

			<p><strong>@font-face rules emitted in the rendered HTML:</strong></p>
			<pre style="white-space:pre-wrap;background:#fff;padding:6px;border:1px solid #dcdcde;"><?php echo '' === $face_block ? '(none, the template did not emit an @font-face rule, URL fields may not have saved)' : esc_html( $face_block ); ?></pre>

			<p><strong>Font cache contents (<?php echo count( $cache_files ); ?> files):</strong></p>
			<pre style="white-space:pre-wrap;background:#fff;padding:6px;border:1px solid #dcdcde;"><?php
			if ( empty( $cache_files ) ) {
				echo '(empty, Dompdf has not cached any fonts yet)';
			} else {
				foreach ( $cache_files as $f ) {
					echo esc_html( $f['name'] ) . '  ' . esc_html( (string) $f['size'] ) . " bytes\n";
				}
			}
			?></pre>
		</div>
		<?php
		$report = (string) ob_get_clean();
		wp_send_json_success( array( 'html' => $report ) );
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'kdna-events-pdf-tickets' ) );
		}
		$o = array();
		foreach ( self::schema() as $name => $def ) {
			$value = get_option( $name, $def['default'] );
			if ( '' === $value || null === $value ) {
				$value = $def['default'];
			}
			$o[ $name ] = $value;
		}
		?>
		<div class="wrap kdna-events-pdf-settings">
			<h1><?php esc_html_e( 'PDF Tickets', 'kdna-events-pdf-tickets' ); ?></h1>
			<?php settings_errors(); ?>
			<div class="kdna-events-pdf-grid">
				<form method="post" action="options.php" class="kdna-events-pdf-form">
					<?php settings_fields( self::OPTION_GROUP ); ?>
					<?php $this->render_sections( $o ); ?>
					<?php submit_button(); ?>
				</form>
				<?php $this->render_preview_panel(); ?>
			</div>
			<?php $this->render_preview_script(); ?>
		</div>
		<?php
	}

	/**
	 * Render every control section. Kept compact for brevity.
	 *
	 * @param array $o Resolved option values.
	 * @return void
	 */
	protected function render_sections( $o ) {
		$this->render_inheritable_pair( 'logo_id', __( 'Logo image', 'kdna-events-pdf-tickets' ), $o, 'media' );
		$this->render_inheritable_pair( 'header_bg', __( 'Brand header background', 'kdna-events-pdf-tickets' ), $o, 'color' );
		$this->render_inheritable_pair( 'default_event_image', __( 'Default event image', 'kdna-events-pdf-tickets' ), $o, 'media' );

		$this->render_section(
			__( 'Brand + Layout', 'kdna-events-pdf-tickets' ),
			array(
				array( 'label' => __( 'Logo width (px)', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_logo_width', 'type' => 'number' ),
				array( 'label' => __( 'Logo alignment', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_logo_align', 'type' => 'select', 'options' => array( 'left' => __( 'Left', 'kdna-events-pdf-tickets' ), 'center' => __( 'Centre', 'kdna-events-pdf-tickets' ) ) ),
				array( 'label' => __( 'Brand header height (px)', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_header_height', 'type' => 'number' ),
				array( 'label' => __( 'Page size', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_page_size', 'type' => 'select', 'options' => array( 'A4' => 'A4', 'Letter' => 'Letter' ) ),
				array( 'label' => __( 'Orientation', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_page_orientation', 'type' => 'select', 'options' => array( 'portrait' => __( 'Portrait', 'kdna-events-pdf-tickets' ), 'landscape' => __( 'Landscape', 'kdna-events-pdf-tickets' ) ) ),
				array( 'label' => __( 'Page margin (mm)', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_page_margin', 'type' => 'number' ),
				array( 'label' => __( 'Tickets per order', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_tickets_per_order', 'type' => 'select', 'options' => array( 'combined' => __( 'Combined (one PDF, page per ticket)', 'kdna-events-pdf-tickets' ), 'separate' => __( 'Separate (one PDF per ticket)', 'kdna-events-pdf-tickets' ) ) ),
			),
			$o
		);

		$this->render_inheritable_pair( 'color_primary', __( 'Primary brand colour', 'kdna-events-pdf-tickets' ), $o, 'color' );
		$this->render_inheritable_pair( 'color_accent', __( 'Accent colour', 'kdna-events-pdf-tickets' ), $o, 'color' );

		$this->render_section(
			__( 'Colours', 'kdna-events-pdf-tickets' ),
			array(
				array( 'label' => __( 'Page background', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_page_bg', 'type' => 'color' ),
				array( 'label' => __( 'Heading text', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_heading_color', 'type' => 'color' ),
				array( 'label' => __( 'Body text', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_body_color', 'type' => 'color' ),
				array( 'label' => __( 'Muted text', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_muted_color', 'type' => 'color' ),
				array( 'label' => __( 'Divider', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_divider_color', 'type' => 'color' ),
			),
			$o
		);

		$font_options = self::font_options();
		$this->render_section(
			__( 'Typography', 'kdna-events-pdf-tickets' ),
			array(
				array( 'label' => __( 'Heading font', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_heading_font', 'type' => 'select', 'options' => $font_options ),
				array( 'label' => __( 'Heading size (pt)', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_heading_size', 'type' => 'number' ),
				array( 'label' => __( 'Body font', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_body_font', 'type' => 'select', 'options' => $font_options ),
				array( 'label' => __( 'Body size (pt)', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_body_size', 'type' => 'number' ),
				array( 'label' => __( 'Ticket code font', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_code_font', 'type' => 'select', 'options' => array( 'courier' => 'Courier', 'helvetica' => 'Helvetica' ) ),
				array( 'label' => __( 'Ticket code size (pt)', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_code_size', 'type' => 'number' ),
			),
			$o
		);
		echo '<p class="description" style="max-width:64em;">' . esc_html__( 'Font selections share the same list as the core Email Design tab for consistency. Dompdf cannot load Google Fonts directly, but you can paste a TTF URL below per font slot and the PDF will download the file and render with it. TTF / OTF uploads are enabled in your Media Library.', 'kdna-events-pdf-tickets' ) . '</p>';
		$this->render_section(
			__( 'Custom PDF fonts', 'kdna-events-pdf-tickets' ),
			array(
				array( 'label' => __( 'Heading font TTF URL', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_heading_font_url', 'type' => 'url' ),
				array( 'label' => __( 'Body font TTF URL', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_body_font_url', 'type' => 'url' ),
			),
			$o
		);
		echo '<p class="description" style="max-width:64em;">' . esc_html__( 'Upload your TTF to Media Library (or paste any public HTTPS URL) and paste the file URL here. Leave blank to use the Dompdf-safe fallback from the dropdown above. First PDF render after a change will cache the font in Dompdf, subsequent renders are instant.', 'kdna-events-pdf-tickets' ) . '</p>';

		$this->render_section(
			__( 'Barcode', 'kdna-events-pdf-tickets' ),
			array(
				array( 'label' => __( 'Barcode type', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_barcode_type', 'type' => 'select', 'options' => array( 'code128' => 'Code 128' ) ),
				array( 'label' => __( 'Barcode width (mm)', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_barcode_width', 'type' => 'number' ),
				array( 'label' => __( 'Barcode height (mm)', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_barcode_height', 'type' => 'number' ),
				array( 'label' => __( 'Show ticket code below barcode', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_barcode_show_text', 'type' => 'checkbox' ),
			),
			$o
		);

		$this->render_inheritable_pair( 'business_name', __( 'Business name', 'kdna-events-pdf-tickets' ), $o, 'text' );
		$this->render_inheritable_pair( 'support_email', __( 'Support email', 'kdna-events-pdf-tickets' ), $o, 'email' );
		$this->render_inheritable_pair( 'support_phone', __( 'Support phone', 'kdna-events-pdf-tickets' ), $o, 'text' );

		$this->render_section(
			__( 'Footer + Terms', 'kdna-events-pdf-tickets' ),
			array(
				array( 'label' => __( 'Show footer', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_show_footer', 'type' => 'checkbox' ),
				array( 'label' => __( 'Website URL', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_website_url', 'type' => 'url' ),
				array( 'label' => __( 'Show generation timestamp', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_show_timestamp', 'type' => 'checkbox' ),
				array( 'label' => __( 'Terms / fine print', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_terms_text', 'type' => 'textarea' ),
				array( 'label' => __( 'Download button label', 'kdna-events-pdf-tickets' ), 'name' => 'kdna_events_pdf_button_label', 'type' => 'text' ),
			),
			$o
		);
	}

	/**
	 * Render a single labelled section with a list of fields.
	 *
	 * @param string $title
	 * @param array  $fields
	 * @param array  $o
	 * @return void
	 */
	protected function render_section( $title, $fields, $o ) {
		echo '<h2 style="margin-top:1.5em;">' . esc_html( $title ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( $fields as $f ) {
			$this->render_field_row( $f, $o );
		}
		echo '</tbody></table>';
	}

	/**
	 * Render a field row (label + control).
	 *
	 * @param array $f Field definition.
	 * @param array $o Resolved options.
	 * @return void
	 */
	protected function render_field_row( $f, $o ) {
		$name  = (string) $f['name'];
		$label = (string) $f['label'];
		$type  = (string) ( $f['type'] ?? 'text' );
		$value = array_key_exists( $name, $o ) ? $o[ $name ] : '';
		echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td>';
		switch ( $type ) {
			case 'checkbox':
				echo '<label><input type="checkbox" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="1" ' . checked( (bool) $value, true, false ) . ' data-pdf-preview-key="' . esc_attr( $name ) . '" /> ' . esc_html__( 'Enable', 'kdna-events-pdf-tickets' ) . '</label>';
				break;
			case 'color':
				echo '<input type="text" class="kdna-events-pdf-color" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" data-default-color="' . esc_attr( (string) $value ) . '" data-pdf-preview-key="' . esc_attr( $name ) . '" />';
				break;
			case 'number':
				echo '<input type="number" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" data-pdf-preview-key="' . esc_attr( $name ) . '" />';
				break;
			case 'select':
				echo '<select id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" data-pdf-preview-key="' . esc_attr( $name ) . '">';
				foreach ( (array) ( $f['options'] ?? array() ) as $k => $v ) {
					echo '<option value="' . esc_attr( $k ) . '" ' . selected( $value, $k, false ) . '>' . esc_html( $v ) . '</option>';
				}
				echo '</select>';
				break;
			case 'textarea':
				echo '<textarea class="large-text" rows="3" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" data-pdf-preview-key="' . esc_attr( $name ) . '">' . esc_textarea( (string) $value ) . '</textarea>';
				break;
			case 'email':
				echo '<input type="email" class="regular-text" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" data-pdf-preview-key="' . esc_attr( $name ) . '" />';
				break;
			case 'url':
				echo '<input type="url" class="regular-text" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" data-pdf-preview-key="' . esc_attr( $name ) . '" />';
				break;
			default:
				echo '<input type="text" class="regular-text" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" data-pdf-preview-key="' . esc_attr( $name ) . '" />';
		}
		echo '</td></tr>';
	}

	/**
	 * Render an inheritable field pair: the toggle + override field.
	 *
	 * @param string $key   Option key suffix (without the prefix).
	 * @param string $label Field label.
	 * @param array  $o     Options.
	 * @param string $type  'media' | 'color' | 'text' | 'email'.
	 * @return void
	 */
	protected function render_inheritable_pair( $key, $label, $o, $type ) {
		$inherit_name = 'kdna_events_pdf_inherit_' . $key;
		$override_name = 'kdna_events_pdf_' . $key;
		$inherit_on    = ! empty( $o[ $inherit_name ] );
		echo '<h2 style="margin-top:1.5em;">' . esc_html( $label ) . '</h2>';
		echo '<p class="description"><label><input type="checkbox" name="' . esc_attr( $inherit_name ) . '" value="1" ' . checked( $inherit_on, true, false ) . ' /> ' . esc_html__( 'Inherit from core Email Design', 'kdna-events-pdf-tickets' ) . '</label></p>';
		echo '<table class="form-table" role="presentation"><tbody><tr><th scope="row"><label for="' . esc_attr( $override_name ) . '">' . esc_html__( 'Override', 'kdna-events-pdf-tickets' ) . '</label></th><td>';
		switch ( $type ) {
			case 'media':
				$id  = (int) ( $o[ $override_name ] ?? 0 );
				$url = $id ? (string) wp_get_attachment_image_url( $id, 'medium' ) : '';
				echo '<div class="kdna-events-pdf-media" data-pdf-media>';
				echo '<input type="hidden" name="' . esc_attr( $override_name ) . '" value="' . esc_attr( (string) $id ) . '" data-pdf-media-input data-pdf-preview-key="' . esc_attr( $override_name ) . '" />';
				echo '<div class="kdna-events-pdf-media__preview" data-pdf-media-preview>' . ( $url ? '<img src="' . esc_url( $url ) . '" alt="" style="max-width:180px;" />' : '' ) . '</div>';
				echo '<button type="button" class="button" data-pdf-media-select>' . ( $url ? esc_html__( 'Change image', 'kdna-events-pdf-tickets' ) : esc_html__( 'Select image', 'kdna-events-pdf-tickets' ) ) . '</button> ';
				echo '<button type="button" class="button-link-delete" data-pdf-media-remove ' . ( $url ? '' : 'hidden' ) . '>' . esc_html__( 'Remove', 'kdna-events-pdf-tickets' ) . '</button>';
				echo '</div>';
				break;
			case 'color':
				$value = (string) ( $o[ $override_name ] ?? '' );
				echo '<input type="text" class="kdna-events-pdf-color" name="' . esc_attr( $override_name ) . '" value="' . esc_attr( $value ) . '" data-default-color="' . esc_attr( $value ) . '" data-pdf-preview-key="' . esc_attr( $override_name ) . '" />';
				break;
			case 'email':
				echo '<input type="email" class="regular-text" name="' . esc_attr( $override_name ) . '" value="' . esc_attr( (string) ( $o[ $override_name ] ?? '' ) ) . '" data-pdf-preview-key="' . esc_attr( $override_name ) . '" />';
				break;
			default:
				echo '<input type="text" class="regular-text" name="' . esc_attr( $override_name ) . '" value="' . esc_attr( (string) ( $o[ $override_name ] ?? '' ) ) . '" data-pdf-preview-key="' . esc_attr( $override_name ) . '" />';
		}
		echo '<p class="description">' . esc_html__( 'Only used when Inherit is off.', 'kdna-events-pdf-tickets' ) . '</p>';
		echo '</td></tr></tbody></table>';
	}

	/**
	 * Render the preview iframe panel.
	 *
	 * @return void
	 */
	protected function render_preview_panel() {
		?>
		<div class="kdna-events-pdf-preview-panel" data-pdf-preview>
			<div class="kdna-events-pdf-preview-panel__bar">
				<strong><?php esc_html_e( 'Live preview', 'kdna-events-pdf-tickets' ); ?></strong>
				<button type="button" class="button button-secondary" data-pdf-preview-refresh><?php esc_html_e( 'Refresh', 'kdna-events-pdf-tickets' ); ?></button>
				<button type="button" class="button button-primary" data-pdf-sample-download><?php esc_html_e( 'Download sample', 'kdna-events-pdf-tickets' ); ?></button>
				<button type="button" class="button button-link" data-pdf-font-debug><?php esc_html_e( 'Debug fonts', 'kdna-events-pdf-tickets' ); ?></button>
			</div>
			<div class="kdna-events-pdf-debug" data-pdf-debug-output hidden style="margin:10px 0;border:1px solid #dcdcde;background:#f6f7f7;padding:10px;max-height:360px;overflow:auto;"></div>
			<iframe class="kdna-events-pdf-preview-panel__frame" data-pdf-preview-frame title="<?php esc_attr_e( 'Ticket PDF preview', 'kdna-events-pdf-tickets' ); ?>" src="about:blank"></iframe>
			<p class="description"><?php esc_html_e( 'Preview uses dummy sample data. Save settings before going live.', 'kdna-events-pdf-tickets' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Inline JS for the preview panel + media picker + colour picker.
	 *
	 * @return void
	 */
	protected function render_preview_script() {
		$cfg = array(
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'previewNonce'  => wp_create_nonce( self::PREVIEW_AJAX ),
			'sampleNonce'   => wp_create_nonce( self::SAMPLE_AJAX ),
			'debugNonce'    => wp_create_nonce( self::DEBUG_AJAX ),
			'previewAction' => self::PREVIEW_AJAX,
			'sampleAction'  => self::SAMPLE_AJAX,
			'debugAction'   => self::DEBUG_AJAX,
		);
		?>
		<style>
			.kdna-events-pdf-grid { display: grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr); gap: 20px; align-items: flex-start; }
			@media (max-width: 1100px) { .kdna-events-pdf-grid { grid-template-columns: 1fr; } }
			.kdna-events-pdf-preview-panel { position: sticky; top: 40px; border: 1px solid #dcdcde; border-radius: 6px; padding: 12px; background: #fff; }
			.kdna-events-pdf-preview-panel__bar { display: flex; gap: 8px; align-items: center; margin-bottom: 10px; }
			.kdna-events-pdf-preview-panel__bar strong { flex: 1; }
			.kdna-events-pdf-preview-panel__frame { width: 100%; height: 760px; border: 1px solid #dcdcde; border-radius: 4px; background: #f4f4f4; }
		</style>
		<script>
		window.kdnaEventsPdf = <?php echo wp_json_encode( $cfg ); ?>;
		function kdnaEventsPdfBoot() {
			var cfg = window.kdnaEventsPdf;
			if (!cfg || !cfg.ajaxUrl) { return; }
			var $ = window.jQuery;
			if ($ && $.fn && $.fn.wpColorPicker) {
				$('.kdna-events-pdf-color').wpColorPicker({ change: schedule });
			}
			var panel = document.querySelector('[data-pdf-preview]');
			if (!panel) { return; }
			var frame = panel.querySelector('[data-pdf-preview-frame]');
			var refresh = panel.querySelector('[data-pdf-preview-refresh]');
			var sample = panel.querySelector('[data-pdf-sample-download]');
			var form = document.querySelector('.kdna-events-pdf-form');
			var timer = null;

			function collect() {
				var fd = new FormData();
				fd.append('action', cfg.previewAction);
				fd.append('nonce', cfg.previewNonce);
				if (form) {
					form.querySelectorAll('[name^="kdna_events_pdf_"]').forEach(function (el) {
						if (el.type === 'checkbox') {
							if (el.checked) { fd.append(el.name, el.value || '1'); }
						} else {
							fd.append(el.name, el.value);
						}
					});
				}
				return fd;
			}
			function render() {
				fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: collect() })
					.then(function (r) { return r.json(); })
					.then(function (res) { if (res && res.success && res.data && res.data.data_uri) { frame.src = res.data.data_uri; } });
			}
			function schedule() {
				if (timer) { clearTimeout(timer); }
				timer = setTimeout(render, 350);
			}
			if (form) {
				form.addEventListener('input', schedule);
				form.addEventListener('change', schedule);
				form.addEventListener('kdna:pdf-media-change', schedule);
			}
			if (refresh) { refresh.addEventListener('click', render); }
			if (sample) {
				sample.addEventListener('click', function () {
					var url = cfg.ajaxUrl + '?action=' + encodeURIComponent(cfg.sampleAction) + '&nonce=' + encodeURIComponent(cfg.sampleNonce);
					window.open(url, '_blank');
				});
			}

			var debugBtn = panel.querySelector('[data-pdf-font-debug]');
			var debugOut = panel.querySelector('[data-pdf-debug-output]');
			if (debugBtn && debugOut) {
				debugBtn.addEventListener('click', function () {
					debugOut.removeAttribute('hidden');
					debugOut.innerHTML = '<em>Running diagnostic...</em>';
					var fd = new FormData();
					fd.append('action', cfg.debugAction);
					fd.append('nonce', cfg.debugNonce);
					fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
						.then(function (r) { return r.json(); })
						.then(function (res) {
							if (res && res.success && res.data && res.data.html) {
								debugOut.innerHTML = res.data.html;
							} else {
								debugOut.textContent = 'Debug failed: ' + JSON.stringify(res);
							}
						})
						.catch(function (err) { debugOut.textContent = 'Debug request failed: ' + err; });
				});
			}

			// Media picker wiring.
			document.querySelectorAll('[data-pdf-media]').forEach(function (root) {
				var input = root.querySelector('[data-pdf-media-input]');
				var preview = root.querySelector('[data-pdf-media-preview]');
				var pick = root.querySelector('[data-pdf-media-select]');
				var remove = root.querySelector('[data-pdf-media-remove]');
				var frame;
				pick.addEventListener('click', function (e) {
					e.preventDefault();
					if (!window.wp || !window.wp.media) { return; }
					if (!frame) {
						frame = window.wp.media({ title: pick.textContent, button: { text: 'Use this' }, library: { type: ['image/jpeg', 'image/png'] }, multiple: false });
						frame.on('select', function () {
							var a = frame.state().get('selection').first().toJSON();
							input.value = a.id;
							preview.innerHTML = '<img src="' + (a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url) + '" alt="" style="max-width:180px;" />';
							remove.removeAttribute('hidden');
							form.dispatchEvent(new CustomEvent('kdna:pdf-media-change', { bubbles: true }));
						});
					}
					frame.open();
				});
				remove.addEventListener('click', function (e) {
					e.preventDefault();
					input.value = '';
					preview.innerHTML = '';
					remove.setAttribute('hidden', 'hidden');
					form.dispatchEvent(new CustomEvent('kdna:pdf-media-change', { bubbles: true }));
				});
			});

			render();
		}
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', kdnaEventsPdfBoot);
		} else {
			kdnaEventsPdfBoot();
		}
		</script>
		<?php
	}
}
