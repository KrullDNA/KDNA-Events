<?php
/**
 * Success Confirmation widget.
 *
 * Friendly confirmation message on the Success page. Reads ?order_ref=
 * and optional session_id from the URL. When session_id is present and
 * the order is still pending, it renders a loading state and the
 * success polling JS calls /kdna-events/v1/confirm-order at 500ms
 * intervals up to five times before reloading the page. If the order
 * cannot be found, a configurable fallback message shows instead.
 *
 * Merge tags in the sub-heading: {order_ref}, {event_title},
 * {attendee_count}.
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
 * Elementor widget rendering the success confirmation.
 */
class KDNA_Events_Widget_Success_Confirmation extends KDNA_Events_Widget_Base {

	/**
	 * @return string
	 */
	public function get_name() {
		return 'kdna-events-success-confirmation';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'Success Confirmation', 'kdna-events' );
	}

	/**
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-check-circle-o';
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
			'heading_text',
			array(
				'label'   => __( 'Heading', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Thank you, your booking is confirmed', 'kdna-events' ),
			)
		);

		$this->add_control(
			'subheading_text',
			array(
				'label'       => __( 'Sub-heading', 'kdna-events' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'default'     => __( 'Booking {order_ref} for {event_title}. {attendee_count} ticket(s) confirmed.', 'kdna-events' ),
				'description' => __( 'Merge tags: {order_ref}, {event_title}, {attendee_count}', 'kdna-events' ),
			)
		);

		$this->add_control(
			'not_found_message',
			array(
				'label'   => __( 'No order found message', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'We could not find that booking. If you have just paid, please refresh.', 'kdna-events' ),
			)
		);

		$this->add_control(
			'loading_message',
			array(
				'label'   => __( 'Loading message', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Confirming your booking...', 'kdna-events' ),
			)
		);

		$this->add_control(
			'show_icon',
			array(
				'label'        => __( 'Show icon', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'icon',
			array(
				'label'     => __( 'Icon', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::ICONS,
				'default'   => array(
					'value'   => 'eicon-check-circle-o',
					'library' => 'eicons',
				),
				'condition' => array( 'show_icon' => 'yes' ),
			)
		);

		$this->add_responsive_control(
			'align',
			array(
				'label'     => __( 'Alignment', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'default'   => 'center',
				'options'   => array(
					'left'   => array(
						'title' => __( 'Left', 'kdna-events' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => __( 'Centre', 'kdna-events' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => __( 'Right', 'kdna-events' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-success-confirmation' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		// Style: wrapper.
		$this->start_controls_section(
			'section_style_wrapper',
			array(
				'label' => __( 'Wrapper', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'wrapper_bg',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-success-confirmation' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'wrapper_border',
				'selector' => '{{WRAPPER}} .kdna-events-success-confirmation',
			)
		);
		$this->add_control(
			'wrapper_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-success-confirmation' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->add_responsive_control(
			'wrapper_padding',
			array(
				'label'      => __( 'Padding', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'default'    => array(
					'top'      => 2,
					'right'    => 2,
					'bottom'   => 2,
					'left'     => 2,
					'unit'     => 'em',
					'isLinked' => true,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-success-confirmation' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->end_controls_section();

		// Icon.
		$this->start_controls_section(
			'section_style_icon',
			array(
				'label'     => __( 'Icon', 'kdna-events' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_icon' => 'yes' ),
			)
		);
		$this->add_control(
			'icon_color',
			array(
				'label'     => __( 'Colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#059669',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-success-confirmation__icon i' => 'color: {{VALUE}};',
					'{{WRAPPER}} .kdna-events-success-confirmation__icon svg' => 'fill: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'icon_size',
			array(
				'label'      => __( 'Size', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 16,
						'max' => 160,
					),
					'em' => array(
						'min'  => 1,
						'max'  => 8,
						'step' => 0.1,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 64,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-success-confirmation__icon i, {{WRAPPER}} .kdna-events-success-confirmation__icon svg' => 'font-size: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				),
			)
		);
		$this->end_controls_section();

		// Heading + sub-heading.
		$text_sections = array(
			'heading'    => array( __( 'Heading', 'kdna-events' ), '.kdna-events-success-confirmation__heading' ),
			'subheading' => array( __( 'Sub-heading', 'kdna-events' ), '.kdna-events-success-confirmation__subheading' ),
		);
		foreach ( $text_sections as $key => $info ) {
			$this->start_controls_section(
				'section_style_' . $key,
				array(
					'label' => $info[0],
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);
			$this->add_control(
				$key . '_color',
				array(
					'label'     => __( 'Colour', 'kdna-events' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}} ' . $info[1] => 'color: {{VALUE}};',
					),
				)
			);
			$this->register_typography_control( $key . '_typography', __( 'Typography', 'kdna-events' ), $info[1] );
			$this->add_responsive_control(
				$key . '_margin',
				array(
					'label'      => __( 'Margin', 'kdna-events' ),
					'type'       => \Elementor\Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em' ),
					'selectors'  => array(
						'{{WRAPPER}} ' . $info[1] => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
				)
			);
			$this->end_controls_section();
		}
	}

	/**
	 * Render.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$ref = isset( $_GET['order_ref'] ) ? sanitize_text_field( wp_unslash( $_GET['order_ref'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$heading      = (string) ( $settings['heading_text'] ?? __( 'Thank you, your booking is confirmed', 'kdna-events' ) );
		$subheading   = (string) ( $settings['subheading_text'] ?? '' );
		$not_found    = (string) ( $settings['not_found_message'] ?? __( 'We could not find that booking.', 'kdna-events' ) );
		$loading_text = (string) ( $settings['loading_message'] ?? __( 'Confirming your booking...', 'kdna-events' ) );
		$show_icon    = 'yes' === ( $settings['show_icon'] ?? 'yes' );
		$icon         = $settings['icon'] ?? null;

		$order = '' !== $ref ? KDNA_Events_Orders::get_order_by_reference( $ref ) : null;

		// In the editor preview, always show the full success state.
		if ( $this->is_editor_mode() && ! $order ) {
			$order = (object) array(
				'order_reference' => 'KDNA-EV-' . current_time( 'Y' ) . '-PREVIEW',
				'event_id'        => $this->get_event_id(),
				'quantity'        => 2,
				'status'          => 'paid',
			);
		}

		if ( ! $order ) {
			?>
			<div class="kdna-events-success-confirmation kdna-events-success-confirmation--not-found" role="status">
				<p class="kdna-events-success-confirmation__not-found"><?php echo esc_html( $not_found ); ?></p>
			</div>
			<?php
			return;
		}

		$is_pending = ! in_array( (string) $order->status, array( 'paid', 'free' ), true );
		$should_poll = $is_pending && '' !== $session_id && '' !== $ref;

		$event_title    = (string) get_the_title( (int) $order->event_id );
		$attendee_count = (int) $order->quantity;

		$subheading = strtr(
			$subheading,
			array(
				'{order_ref}'      => $order->order_reference,
				'{event_title}'    => $event_title,
				'{attendee_count}' => (string) $attendee_count,
			)
		);
		?>
		<div
			class="kdna-events-success-confirmation <?php echo $should_poll ? 'is-loading' : ''; ?>"
			data-kdna-events-success-confirmation="1"
			data-order-ref="<?php echo esc_attr( (string) $order->order_reference ); ?>"
			data-should-poll="<?php echo $should_poll ? '1' : '0'; ?>"
			role="status"
		>
			<?php if ( $show_icon && is_array( $icon ) && ! empty( $icon['value'] ) ) : ?>
				<div class="kdna-events-success-confirmation__icon" aria-hidden="true">
					<?php \Elementor\Icons_Manager::render_icon( $icon ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $should_poll ) : ?>
				<h2 class="kdna-events-success-confirmation__heading kdna-events-success-confirmation__heading--loading">
					<?php echo esc_html( $loading_text ); ?>
				</h2>
				<div class="kdna-events-success-confirmation__spinner" aria-hidden="true"></div>
			<?php else : ?>
				<h2 class="kdna-events-success-confirmation__heading">
					<?php echo esc_html( $heading ); ?>
				</h2>
				<?php if ( '' !== trim( $subheading ) ) : ?>
					<p class="kdna-events-success-confirmation__subheading">
						<?php echo esc_html( $subheading ); ?>
					</p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}
