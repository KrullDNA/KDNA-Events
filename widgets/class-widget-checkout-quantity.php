<?php
/**
 * Checkout Quantity Selector widget.
 *
 * Minus / input / plus. Enforces the effective min (event min) and the
 * effective max (min of event max and capacity - sold). Emits
 * 'kdna-events-quantity-changed' CustomEvents on every change so the
 * attendee form, order summary and pay button can react live.
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
 * Elementor widget rendering the quantity selector.
 */
class KDNA_Events_Widget_Checkout_Quantity extends KDNA_Events_Widget_Base {

	/**
	 * @return string
	 */
	public function get_name() {
		return 'kdna-events-checkout-quantity';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'Checkout Quantity', 'kdna-events' );
	}

	/**
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-plus-circle';
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
			'label_text',
			array(
				'label'   => __( 'Label', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Tickets', 'kdna-events' ),
			)
		);

		$this->add_control(
			'show_available',
			array(
				'label'        => __( 'Show tickets available', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => __( 'Displays remaining capacity when the event sets one.', 'kdna-events' ),
			)
		);

		$this->add_control(
			'available_template',
			array(
				'label'     => __( 'Available text template', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( '{n} tickets available', 'kdna-events' ),
				'condition' => array( 'show_available' => 'yes' ),
			)
		);

		$this->add_control(
			'show_price_per_ticket',
			array(
				'label'        => __( 'Show price per ticket', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_min_max',
			array(
				'label'        => __( 'Show min/max helper text', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'min_max_template',
			array(
				'label'     => __( 'Min/max helper text', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'Minimum {min}, maximum {max} per order.', 'kdna-events' ),
				'condition' => array( 'show_min_max' => 'yes' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_label_style',
			array(
				'label' => __( 'Label', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'label_color',
			array(
				'label'     => __( 'Colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-checkout-quantity__label' => 'color: {{VALUE}};',
				),
			)
		);
		$this->register_typography_control( 'label_typography', __( 'Typography', 'kdna-events' ), '.kdna-events-checkout-quantity__label' );
		$this->add_responsive_control(
			'label_spacing',
			array(
				'label'      => __( 'Bottom spacing', 'kdna-events' ),
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
					'size' => 8,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-checkout-quantity__label' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'section_controls_style',
			array(
				'label' => __( 'Controls', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'controls_gap',
			array(
				'label'      => __( 'Spacing between controls', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 24,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 8,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-checkout-quantity__controls' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$input_selector = '.kdna-events-checkout-quantity__input';

		$this->add_control(
			'input_color',
			array(
				'label'     => __( 'Input text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} ' . $input_selector => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'input_background',
			array(
				'label'     => __( 'Input background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} ' . $input_selector => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->register_typography_control( 'input_typography', __( 'Input typography', 'kdna-events' ), $input_selector );
		$this->add_responsive_control(
			'input_width',
			array(
				'label'      => __( 'Input width', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 32,
						'max' => 200,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 72,
				),
				'selectors'  => array(
					'{{WRAPPER}} ' . $input_selector => 'width: {{SIZE}}{{UNIT}};',
				),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'input_border',
				'selector' => '{{WRAPPER}} ' . $input_selector,
			)
		);
		$this->add_control(
			'input_radius',
			array(
				'label'      => __( 'Input border radius', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} ' . $input_selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_control(
			'input_focus_border',
			array(
				'label'     => __( 'Input focus border colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} ' . $input_selector . ':focus' => 'border-color: {{VALUE}}; outline: none; box-shadow: 0 0 0 3px {{VALUE}}33;',
				),
			)
		);

		$this->start_controls_tabs( 'button_state' );

		$this->start_controls_tab( 'button_normal', array( 'label' => __( 'Normal', 'kdna-events' ) ) );
		$this->add_control(
			'button_color',
			array(
				'label'     => __( 'Button text', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-checkout-quantity__button' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'button_background',
			array(
				'label'     => __( 'Button background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#f3f4f6',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-checkout-quantity__button' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'button_border',
				'selector' => '{{WRAPPER}} .kdna-events-checkout-quantity__button',
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab( 'button_hover', array( 'label' => __( 'Hover', 'kdna-events' ) ) );
		$this->add_control(
			'button_hover_color',
			array(
				'label'     => __( 'Button text', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-checkout-quantity__button:hover' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'button_hover_background',
			array(
				'label'     => __( 'Button background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-checkout-quantity__button:hover' => 'background-color: {{VALUE}};',
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

		$min      = KDNA_Events_Checkout::get_effective_min( $event_id );
		$max      = KDNA_Events_Checkout::get_effective_max( $event_id );
		$price    = (float) get_post_meta( $event_id, '_kdna_event_price', true );
		$currency = strtoupper( (string) get_post_meta( $event_id, '_kdna_event_currency', true ) );
		if ( '' === $currency ) {
			$currency = strtoupper( (string) get_option( 'kdna_events_default_currency', 'AUD' ) );
		}
		$is_free  = kdna_events_is_free( $event_id );
		$capacity = (int) get_post_meta( $event_id, '_kdna_event_capacity', true );
		$sold     = kdna_events_get_tickets_sold( $event_id );
		$remaining = $capacity > 0 ? max( 0, $capacity - $sold ) : -1;

		$start_qty = max( $min, 1 );
		if ( $max < $start_qty ) {
			$start_qty = max( 0, $max );
		}

		$price_formatted = $is_free ? '' : kdna_events_format_price( $price, $currency );
		?>
		<div
			class="kdna-events-checkout-quantity"
			data-kdna-events-checkout-quantity="1"
			data-event-id="<?php echo esc_attr( (string) $event_id ); ?>"
			data-min="<?php echo esc_attr( (string) $min ); ?>"
			data-max="<?php echo esc_attr( (string) $max ); ?>"
			data-price="<?php echo esc_attr( (string) $price ); ?>"
			data-currency="<?php echo esc_attr( $currency ); ?>"
			data-is-free="<?php echo $is_free ? '1' : '0'; ?>"
		>
			<div class="kdna-events-checkout-quantity__label">
				<?php echo esc_html( (string) ( $settings['label_text'] ?? __( 'Tickets', 'kdna-events' ) ) ); ?>
			</div>

			<div class="kdna-events-checkout-quantity__controls">
				<button type="button" class="kdna-events-checkout-quantity__button kdna-events-checkout-quantity__button--minus" aria-label="<?php esc_attr_e( 'Decrease quantity', 'kdna-events' ); ?>">
					<span aria-hidden="true">-</span>
				</button>
				<input
					type="number"
					class="kdna-events-checkout-quantity__input"
					min="<?php echo esc_attr( (string) $min ); ?>"
					max="<?php echo esc_attr( (string) $max ); ?>"
					step="1"
					value="<?php echo esc_attr( (string) $start_qty ); ?>"
					inputmode="numeric"
					aria-label="<?php esc_attr_e( 'Ticket quantity', 'kdna-events' ); ?>"
				/>
				<button type="button" class="kdna-events-checkout-quantity__button kdna-events-checkout-quantity__button--plus" aria-label="<?php esc_attr_e( 'Increase quantity', 'kdna-events' ); ?>">
					<span aria-hidden="true">+</span>
				</button>
			</div>

			<?php if ( 'yes' === ( $settings['show_price_per_ticket'] ?? 'yes' ) && ! $is_free ) : ?>
				<div class="kdna-events-checkout-quantity__price-per-ticket">
					<?php
					printf(
						/* translators: %s: formatted price per ticket */
						esc_html__( '%s per ticket', 'kdna-events' ),
						esc_html( $price_formatted )
					);
					?>
				</div>
			<?php endif; ?>

			<?php if ( 'yes' === ( $settings['show_available'] ?? 'yes' ) && $remaining > -1 ) : ?>
				<div class="kdna-events-checkout-quantity__available">
					<?php
					$tpl = (string) ( $settings['available_template'] ?? __( '{n} tickets available', 'kdna-events' ) );
					echo esc_html( str_replace( '{n}', (string) $remaining, $tpl ) );
					?>
				</div>
			<?php endif; ?>

			<?php if ( 'yes' === ( $settings['show_min_max'] ?? 'yes' ) ) : ?>
				<div class="kdna-events-checkout-quantity__min-max">
					<?php
					$tpl = (string) ( $settings['min_max_template'] ?? __( 'Minimum {min}, maximum {max} per order.', 'kdna-events' ) );
					echo esc_html( strtr( $tpl, array( '{min}' => (string) $min, '{max}' => (string) $max ) ) );
					?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
