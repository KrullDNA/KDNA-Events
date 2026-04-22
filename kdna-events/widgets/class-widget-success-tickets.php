<?php
/**
 * Success Tickets widget.
 *
 * Lists each ticket generated for the buyer's order, one card per
 * attendee, with the ticket code shown prominently. Optionally renders
 * a QR code image per ticket.
 *
 * QR code choice: uses api.qrserver.com, a free, no-signup QR generator
 * that returns a PNG for a given text payload. Chosen over a bundled
 * JS library to avoid adding a JS dependency for a feature many sites
 * will not even use. Sites that need the QR content to stay private
 * or that want to avoid an external dependency can swap the URL in
 * render_qr_url() for a local server-side generator or a JS library
 * such as qrcode.js without changing the widget surface.
 *
 * PDF download is documented as a Stage 11 follow-up.
 *
 * @package KDNA_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KDNA_Events_Widget_Base' ) ) {
	return;
}

/**
 * Elementor widget rendering per-ticket cards on the Success page.
 */
class KDNA_Events_Widget_Success_Tickets extends KDNA_Events_Widget_Base {

	/**
	 * @return string
	 */
	public function get_name() {
		return 'kdna-events-success-tickets';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'Success Tickets', 'kdna-events' );
	}

	/**
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-ticket';
	}

	/**
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( 'kdna-events-frontend', 'kdna-events-success' );
	}

	/**
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'card_heading_template',
			array(
				'label'       => __( 'Card heading', 'kdna-events' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Ticket {n}', 'kdna-events' ),
				'description' => __( 'Merge tags: {n}, {attendee_name}', 'kdna-events' ),
			)
		);

		$this->add_control(
			'show_email',
			array(
				'label'        => __( 'Show attendee email', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->add_control(
			'show_qr',
			array(
				'label'        => __( 'Show QR code', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => __( 'Uses an external QR service. Disable if you prefer to keep ticket codes off third-party endpoints.', 'kdna-events' ),
			)
		);

		$this->add_control(
			'show_download',
			array(
				'label'        => __( 'Show download PDF button', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'description'  => __( 'Placeholder. PDF ticket generation is scheduled for a post-launch release.', 'kdna-events' ),
			)
		);

		$this->add_control(
			'monospace_code',
			array(
				'label'        => __( 'Monospace ticket code', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();

		// Card style.
		$this->start_controls_section(
			'section_style_card',
			array(
				'label' => __( 'Card', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'card_bg',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-success-ticket' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'card_border',
				'selector' => '{{WRAPPER}} .kdna-events-success-ticket',
			)
		);
		$this->add_control(
			'card_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'default'    => array(
					'top'      => 12,
					'right'    => 12,
					'bottom'   => 12,
					'left'     => 12,
					'unit'     => 'px',
					'isLinked' => true,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-success-ticket' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
				),
			)
		);
		$this->add_responsive_control(
			'card_padding',
			array(
				'label'      => __( 'Padding', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'default'    => array(
					'top'      => 16,
					'right'    => 16,
					'bottom'   => 16,
					'left'     => 16,
					'unit'     => 'px',
					'isLinked' => true,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-success-ticket' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'card_shadow',
				'selector' => '{{WRAPPER}} .kdna-events-success-ticket',
			)
		);
		$this->add_responsive_control(
			'card_gap',
			array(
				'label'      => __( 'Gap between tickets', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 48,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 16,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-success-tickets__list' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);
		$this->end_controls_section();

		// Name + email.
		$this->start_controls_section(
			'section_style_name',
			array(
				'label' => __( 'Attendee name', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'name_color',
			array(
				'label'     => __( 'Colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-success-ticket__name' => 'color: {{VALUE}};',
				),
			)
		);
		$this->register_typography_control( 'name_typography', __( 'Typography', 'kdna-events' ), '.kdna-events-success-ticket__name' );
		$this->end_controls_section();

		// Ticket code.
		$this->start_controls_section(
			'section_style_code',
			array(
				'label' => __( 'Ticket code', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'code_color',
			array(
				'label'     => __( 'Text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-success-ticket__code' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'code_bg',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#f3f4f6',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-success-ticket__code' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->register_typography_control( 'code_typography', __( 'Typography', 'kdna-events' ), '.kdna-events-success-ticket__code' );
		$this->add_control(
			'code_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'default'    => array(
					'top'      => 6,
					'right'    => 6,
					'bottom'   => 6,
					'left'     => 6,
					'unit'     => 'px',
					'isLinked' => true,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-success-ticket__code' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'code_border',
				'selector' => '{{WRAPPER}} .kdna-events-success-ticket__code',
			)
		);
		$this->add_responsive_control(
			'code_padding',
			array(
				'label'      => __( 'Padding', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'default'    => array(
					'top'      => 0.5,
					'right'    => 0.75,
					'bottom'   => 0.5,
					'left'     => 0.75,
					'unit'     => 'em',
					'isLinked' => false,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-success-ticket__code' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$ref = isset( $_GET['order_ref'] ) ? sanitize_text_field( wp_unslash( $_GET['order_ref'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order = '' !== $ref ? KDNA_Events_Orders::get_order_by_reference( $ref ) : null;

		$tickets = array();
		if ( $order ) {
			$tickets = KDNA_Events_Tickets::get_tickets_for_order( (int) $order->order_id );
		}

		// Editor preview: fabricate a couple of tickets so authors can style the card.
		if ( empty( $tickets ) && $this->is_editor_mode() ) {
			$tickets = array(
				(object) array(
					'ticket_code'    => 'ABCD1234',
					'attendee_name'  => __( 'Jane Doe', 'kdna-events' ),
					'attendee_email' => 'jane@example.com',
				),
				(object) array(
					'ticket_code'    => 'EFGH5678',
					'attendee_name'  => __( 'John Smith', 'kdna-events' ),
					'attendee_email' => 'john@example.com',
				),
			);
		}

		if ( empty( $tickets ) ) {
			if ( $this->is_editor_mode() ) {
				$this->render_editor_placeholder( __( 'Tickets will appear here once the buyer lands on the success page.', 'kdna-events' ) );
			}
			return;
		}

		$heading_tpl  = (string) ( $settings['card_heading_template'] ?? __( 'Ticket {n}', 'kdna-events' ) );
		$show_email   = 'yes' === ( $settings['show_email'] ?? '' );
		$show_qr      = 'yes' === ( $settings['show_qr'] ?? 'yes' );
		$show_pdf     = 'yes' === ( $settings['show_download'] ?? '' );
		$monospace    = 'yes' === ( $settings['monospace_code'] ?? 'yes' );
		$code_classes = 'kdna-events-success-ticket__code' . ( $monospace ? ' kdna-events-success-ticket__code--mono' : '' );
		?>
		<div class="kdna-events-success-tickets" data-kdna-events-success-tickets="1">
			<div class="kdna-events-success-tickets__list">
				<?php foreach ( $tickets as $index => $ticket ) :
					$n = $index + 1;
					$heading = strtr(
						$heading_tpl,
						array(
							'{n}'             => (string) $n,
							'{attendee_name}' => (string) $ticket->attendee_name,
						)
					);
					?>
					<article class="kdna-events-success-ticket">
						<header class="kdna-events-success-ticket__header">
							<h3 class="kdna-events-success-ticket__heading"><?php echo esc_html( $heading ); ?></h3>
						</header>
						<div class="kdna-events-success-ticket__body">
							<div class="kdna-events-success-ticket__details">
								<div class="kdna-events-success-ticket__name"><?php echo esc_html( (string) $ticket->attendee_name ); ?></div>
								<?php if ( $show_email && ! empty( $ticket->attendee_email ) ) : ?>
									<div class="kdna-events-success-ticket__email"><?php echo esc_html( (string) $ticket->attendee_email ); ?></div>
								<?php endif; ?>
								<div class="<?php echo esc_attr( $code_classes ); ?>" aria-label="<?php esc_attr_e( 'Ticket code', 'kdna-events' ); ?>">
									<?php echo esc_html( (string) $ticket->ticket_code ); ?>
								</div>
								<?php if ( $show_pdf ) : ?>
									<button type="button" class="kdna-events-success-ticket__download" disabled>
										<?php esc_html_e( 'Download PDF (coming soon)', 'kdna-events' ); ?>
									</button>
								<?php endif; ?>
								<?php
								/**
								 * Fires after each ticket's body has rendered on the Success page.
								 *
								 * Add-ons (v1.1 Brief B's kdna-events-pdf-tickets, wallet passes,
								 * calendar buttons) hook here to inject per-ticket UI without
								 * touching core.
								 *
								 * @param object $ticket   The ticket object.
								 * @param object $order    The parent order object.
								 * @param array  $settings Elementor widget settings.
								 */
								do_action( 'kdna_events_after_success_ticket', $ticket, $order, $settings );
								?>
							</div>
							<?php if ( $show_qr ) : ?>
								<div class="kdna-events-success-ticket__qr">
									<img
										src="<?php echo esc_url( $this->render_qr_url( (string) $ticket->ticket_code ) ); ?>"
										alt="<?php echo esc_attr( sprintf( /* translators: %s: ticket code */ __( 'QR code for ticket %s', 'kdna-events' ), $ticket->ticket_code ) ); ?>"
										width="140"
										height="140"
										loading="lazy"
									/>
								</div>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Return the QR image URL for a ticket code.
	 *
	 * Site owners wanting to avoid the external service can replace
	 * this method with a local generator that returns a data URI or
	 * a URL served from wp-content.
	 *
	 * @param string $ticket_code Ticket code.
	 * @return string
	 */
	protected function render_qr_url( $ticket_code ) {
		$base = 'https://api.qrserver.com/v1/create-qr-code/';
		return $base . '?' . http_build_query(
			array(
				'size'   => '280x280',
				'margin' => 4,
				'data'   => $ticket_code,
			)
		);
	}
}
