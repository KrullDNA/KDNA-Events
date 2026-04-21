<?php
/**
 * Checkout Order Summary widget.
 *
 * Renders live line items and a running total. For free events it
 * replaces the $0.00 total with a prominent 'Free' label. Reacts to
 * quantity change events dispatched by the Quantity widget.
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
 * Elementor widget rendering the order summary block.
 */
class KDNA_Events_Widget_Checkout_Order_Summary extends KDNA_Events_Widget_Base {

	/**
	 * @return string
	 */
	public function get_name() {
		return 'kdna-events-checkout-order-summary';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'Checkout Order Summary', 'kdna-events' );
	}

	/**
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-bullet-list';
	}

	/**
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( 'kdna-events-frontend', 'kdna-events-checkout' );
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
			'subtotal_label',
			array(
				'label'   => __( 'Subtotal label', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Subtotal', 'kdna-events' ),
			)
		);
		$this->add_control(
			'total_label',
			array(
				'label'   => __( 'Total label', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Total', 'kdna-events' ),
			)
		);
		$this->add_control(
			'free_label',
			array(
				'label'   => __( 'Free label', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Free', 'kdna-events' ),
			)
		);
		$this->add_control(
			'show_currency_code',
			array(
				'label'        => __( 'Show currency code', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			)
		);
		$this->add_control(
			'show_tax_line',
			array(
				'label'        => __( 'Show tax line', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'description'  => __( 'Placeholder for a future tax breakdown.', 'kdna-events' ),
			)
		);
		$this->add_control(
			'tax_label',
			array(
				'label'     => __( 'Tax label', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'Tax', 'kdna-events' ),
				'condition' => array( 'show_tax_line' => 'yes' ),
			)
		);
		$this->add_control(
			'line_item_template',
			array(
				'label'   => __( 'Line item template', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( '{qty} x {event_title}', 'kdna-events' ),
			)
		);
		$this->end_controls_section();

		// Style.
		$this->start_controls_section(
			'section_style_rows',
			array(
				'label' => __( 'Rows', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'row_color',
			array(
				'label'     => __( 'Row colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-checkout-summary-row' => 'color: {{VALUE}};',
				),
			)
		);
		$this->register_typography_control( 'row_typography', __( 'Row typography', 'kdna-events' ), '.kdna-events-checkout-summary-row' );
		$this->add_responsive_control(
			'row_spacing',
			array(
				'label'      => __( 'Row spacing', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 32,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 8,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-checkout-summary-row' => 'padding: {{SIZE}}{{UNIT}} 0;',
				),
			)
		);
		$this->add_control(
			'divider_color',
			array(
				'label'     => __( 'Divider colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#e5e7eb',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-checkout-summary-row' => 'border-bottom: 1px solid {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'alignment',
			array(
				'label'     => __( 'Alignment', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'default'   => 'space-between',
				'options'   => array(
					'flex-start'    => array(
						'title' => __( 'Left', 'kdna-events' ),
						'icon'  => 'eicon-text-align-left',
					),
					'space-between' => array(
						'title' => __( 'Justified', 'kdna-events' ),
						'icon'  => 'eicon-text-align-justify',
					),
					'flex-end'      => array(
						'title' => __( 'Right', 'kdna-events' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-checkout-summary-row' => 'justify-content: {{VALUE}};',
				),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_total',
			array(
				'label' => __( 'Total row', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'total_color',
			array(
				'label'     => __( 'Total colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-checkout-summary-row--total' => 'color: {{VALUE}};',
				),
			)
		);
		$this->register_typography_control( 'total_typography', __( 'Typography', 'kdna-events' ), '.kdna-events-checkout-summary-row--total' );
		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_card',
			array(
				'label' => __( 'Card', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'card_background',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-checkout-order-summary' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'card_border',
				'selector' => '{{WRAPPER}} .kdna-events-checkout-order-summary',
			)
		);
		$this->add_responsive_control(
			'card_padding',
			array(
				'label'      => __( 'Padding', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-checkout-order-summary' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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
		$event_id = $this->get_checkout_event_id();
		if ( ! $event_id ) {
			$this->render_editor_placeholder( __( 'Add ?event_id=X to the checkout URL to preview.', 'kdna-events' ) );
			return;
		}

		$settings = $this->get_settings_for_display();

		$price    = (float) get_post_meta( $event_id, '_kdna_event_price', true );
		$currency = strtoupper( (string) get_post_meta( $event_id, '_kdna_event_currency', true ) );
		if ( '' === $currency ) {
			$currency = strtoupper( (string) get_option( 'kdna_events_default_currency', 'AUD' ) );
		}
		$is_free = kdna_events_is_free( $event_id );

		$subtotal_label = (string) ( $settings['subtotal_label'] ?? __( 'Subtotal', 'kdna-events' ) );
		$total_label    = (string) ( $settings['total_label'] ?? __( 'Total', 'kdna-events' ) );
		$free_label     = (string) ( $settings['free_label'] ?? __( 'Free', 'kdna-events' ) );
		$tax_label      = (string) ( $settings['tax_label'] ?? __( 'Tax', 'kdna-events' ) );
		$show_currency  = 'yes' === ( $settings['show_currency_code'] ?? '' );
		$show_tax       = 'yes' === ( $settings['show_tax_line'] ?? '' );
		$line_tpl       = (string) ( $settings['line_item_template'] ?? __( '{qty} x {event_title}', 'kdna-events' ) );

		$min = KDNA_Events_Checkout::get_effective_min( $event_id );
		$start_qty = max( 1, $min );
		$subtotal  = $is_free ? 0.0 : (float) ( $price * $start_qty );
		?>
		<div
			class="kdna-events-checkout-order-summary"
			data-kdna-events-checkout-order-summary="1"
			data-event-id="<?php echo esc_attr( (string) $event_id ); ?>"
			data-event-title="<?php echo esc_attr( get_the_title( $event_id ) ); ?>"
			data-price="<?php echo esc_attr( (string) $price ); ?>"
			data-currency="<?php echo esc_attr( $currency ); ?>"
			data-is-free="<?php echo $is_free ? '1' : '0'; ?>"
			data-line-template="<?php echo esc_attr( $line_tpl ); ?>"
			data-subtotal-label="<?php echo esc_attr( $subtotal_label ); ?>"
			data-total-label="<?php echo esc_attr( $total_label ); ?>"
			data-free-label="<?php echo esc_attr( $free_label ); ?>"
			data-tax-label="<?php echo esc_attr( $tax_label ); ?>"
			data-show-currency="<?php echo $show_currency ? '1' : '0'; ?>"
			data-show-tax="<?php echo $show_tax ? '1' : '0'; ?>"
		>
			<div class="kdna-events-checkout-summary-rows" data-lines>
				<div class="kdna-events-checkout-summary-row">
					<span class="kdna-events-checkout-summary-row__label">
						<?php echo esc_html( strtr( $line_tpl, array( '{qty}' => (string) $start_qty, '{event_title}' => get_the_title( $event_id ) ) ) ); ?>
					</span>
					<span class="kdna-events-checkout-summary-row__value">
						<?php echo $is_free ? esc_html( $free_label ) : esc_html( kdna_events_format_price( $subtotal, $currency ) ); ?>
					</span>
				</div>

				<div class="kdna-events-checkout-summary-row kdna-events-checkout-summary-row--subtotal">
					<span class="kdna-events-checkout-summary-row__label"><?php echo esc_html( $subtotal_label ); ?></span>
					<span class="kdna-events-checkout-summary-row__value" data-subtotal>
						<?php echo $is_free ? esc_html( $free_label ) : esc_html( kdna_events_format_price( $subtotal, $currency ) ); ?>
					</span>
				</div>

				<?php if ( $show_tax ) : ?>
					<div class="kdna-events-checkout-summary-row kdna-events-checkout-summary-row--tax">
						<span class="kdna-events-checkout-summary-row__label"><?php echo esc_html( $tax_label ); ?></span>
						<span class="kdna-events-checkout-summary-row__value" data-tax><?php echo esc_html( $is_free ? $free_label : kdna_events_format_price( 0, $currency ) ); ?></span>
					</div>
				<?php endif; ?>

				<div class="kdna-events-checkout-summary-row kdna-events-checkout-summary-row--total">
					<span class="kdna-events-checkout-summary-row__label"><?php echo esc_html( $total_label ); ?></span>
					<span class="kdna-events-checkout-summary-row__value" data-total>
						<?php
						if ( $is_free ) {
							echo esc_html( $free_label );
						} else {
							echo esc_html( kdna_events_format_price( $subtotal, $currency ) );
							if ( $show_currency ) {
								echo ' ' . esc_html( $currency );
							}
						}
						?>
					</span>
				</div>
			</div>
		</div>
		<?php
	}
}
