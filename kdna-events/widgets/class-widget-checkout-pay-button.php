<?php
/**
 * Checkout Pay Button widget.
 *
 * Final CTA. Swaps its label to 'Pay {total}' or a free-event label,
 * flips to a loading state on submit, and triggers the checkout JS
 * which runs the AJAX create-order flow.
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
 * Elementor widget rendering the pay button.
 */
class KDNA_Events_Widget_Checkout_Pay_Button extends KDNA_Events_Widget_Base {

	/**
	 * @return string
	 */
	public function get_name() {
		return 'kdna-events-checkout-pay-button';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'Checkout Pay Button', 'kdna-events' );
	}

	/**
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-cart-medium';
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
			'paid_label_template',
			array(
				'label'       => __( 'Paid label template', 'kdna-events' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Pay {total}', 'kdna-events' ),
				'description' => __( 'Merge tags: {total}, {currency}', 'kdna-events' ),
			)
		);
		$this->add_control(
			'free_label',
			array(
				'label'   => __( 'Free event label', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Reserve Free Spot', 'kdna-events' ),
			)
		);
		$this->add_control(
			'loading_label',
			array(
				'label'   => __( 'Loading label', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Processing...', 'kdna-events' ),
			)
		);
		$this->add_control(
			'show_icon',
			array(
				'label'        => __( 'Show icon', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			)
		);
		$this->add_control(
			'icon',
			array(
				'label'     => __( 'Icon', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::ICONS,
				'default'   => array(
					'value'   => 'eicon-lock',
					'library' => 'eicons',
				),
				'condition' => array( 'show_icon' => 'yes' ),
			)
		);
		$this->add_control(
			'icon_position',
			array(
				'label'     => __( 'Icon position', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'left',
				'options'   => array(
					'left'  => __( 'Left', 'kdna-events' ),
					'right' => __( 'Right', 'kdna-events' ),
				),
				'condition' => array( 'show_icon' => 'yes' ),
			)
		);
		$this->end_controls_section();

		// Style.
		$this->start_controls_section(
			'section_style_button',
			array(
				'label' => __( 'Button', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		$this->register_typography_control( 'button_typography', __( 'Typography', 'kdna-events' ), '.kdna-events-checkout-pay-button' );
		$this->add_responsive_control(
			'button_padding',
			array(
				'label'      => __( 'Padding', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'default'    => array(
					'top'      => 0.85,
					'right'    => 1.5,
					'bottom'   => 0.85,
					'left'     => 1.5,
					'unit'     => 'em',
					'isLinked' => false,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-checkout-pay-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_control(
			'button_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-checkout-pay-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->register_hover_transition_control( 'button_transition', '.kdna-events-checkout-pay-button', 200 );

		$this->start_controls_tabs( 'button_state' );

		$this->start_controls_tab( 'button_normal', array( 'label' => __( 'Normal', 'kdna-events' ) ) );
		$this->add_control(
			'button_color',
			array(
				'label'     => __( 'Text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-checkout-pay-button' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'button_bg',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#1d4ed8',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-checkout-pay-button' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'button_border',
				'selector' => '{{WRAPPER}} .kdna-events-checkout-pay-button',
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab( 'button_hover', array( 'label' => __( 'Hover', 'kdna-events' ) ) );
		$this->add_control(
			'button_hover_color',
			array(
				'label'     => __( 'Text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-checkout-pay-button:hover' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'button_hover_bg',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#1e40af',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-checkout-pay-button:hover' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab( 'button_loading', array( 'label' => __( 'Loading', 'kdna-events' ) ) );
		$this->add_control(
			'loading_opacity',
			array(
				'label'   => __( 'Opacity', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::SLIDER,
				'range'   => array(
					'px' => array(
						'min' => 40,
						'max' => 100,
					),
				),
				'default' => array(
					'size' => 80,
				),
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-checkout-pay-button.is-loading' => 'opacity: calc({{SIZE}} / 100);',
				),
			)
		);
		$this->add_control(
			'spinner_color',
			array(
				'label'     => __( 'Spinner colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-checkout-pay-button__spinner' => 'border-top-color: {{VALUE}};',
				),
			)
		);
		$this->end_controls_tab();

		$this->end_controls_tabs();

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

		$paid_tpl      = (string) ( $settings['paid_label_template'] ?? __( 'Pay {total}', 'kdna-events' ) );
		$free_label    = (string) ( $settings['free_label'] ?? __( 'Reserve Free Spot', 'kdna-events' ) );
		$loading_label = (string) ( $settings['loading_label'] ?? __( 'Processing...', 'kdna-events' ) );

		$min = KDNA_Events_Checkout::get_effective_min( $event_id );
		$start_qty = max( 1, $min );
		$initial_total = $is_free ? 0.0 : (float) ( $price * $start_qty );
		$initial_label = $is_free ? $free_label : strtr(
			$paid_tpl,
			array(
				'{total}'    => kdna_events_format_price( $initial_total, $currency ),
				'{currency}' => $currency,
			)
		);

		$icon_position = 'right' === ( $settings['icon_position'] ?? 'left' ) ? 'right' : 'left';
		$show_icon     = 'yes' === ( $settings['show_icon'] ?? '' );
		$icon          = $settings['icon'] ?? null;
		?>
		<button
			type="button"
			class="kdna-events-checkout-pay-button <?php echo $show_icon ? 'kdna-events-checkout-pay-button--icon-' . esc_attr( $icon_position ) : ''; ?>"
			data-kdna-events-checkout-pay="1"
			data-event-id="<?php echo esc_attr( (string) $event_id ); ?>"
			data-price="<?php echo esc_attr( (string) $price ); ?>"
			data-currency="<?php echo esc_attr( $currency ); ?>"
			data-is-free="<?php echo $is_free ? '1' : '0'; ?>"
			data-paid-template="<?php echo esc_attr( $paid_tpl ); ?>"
			data-free-label="<?php echo esc_attr( $free_label ); ?>"
			data-loading-label="<?php echo esc_attr( $loading_label ); ?>"
		>
			<?php if ( $show_icon && 'left' === $icon_position && is_array( $icon ) && ! empty( $icon['value'] ) ) : ?>
				<span class="kdna-events-checkout-pay-button__icon" aria-hidden="true">
					<?php \Elementor\Icons_Manager::render_icon( $icon ); ?>
				</span>
			<?php endif; ?>
			<span class="kdna-events-checkout-pay-button__spinner" aria-hidden="true"></span>
			<span class="kdna-events-checkout-pay-button__label" data-label>
				<?php echo esc_html( $initial_label ); ?>
			</span>
			<?php if ( $show_icon && 'right' === $icon_position && is_array( $icon ) && ! empty( $icon['value'] ) ) : ?>
				<span class="kdna-events-checkout-pay-button__icon" aria-hidden="true">
					<?php \Elementor\Icons_Manager::render_icon( $icon ); ?>
				</span>
			<?php endif; ?>
		</button>
		<div class="kdna-events-checkout-pay-button__error" data-kdna-events-checkout-error role="alert" aria-live="assertive" hidden></div>
		<?php
	}
}
