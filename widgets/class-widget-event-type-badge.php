<?php
/**
 * Event Type Badge widget.
 *
 * Renders a pill-style badge showing whether the event is in-person,
 * virtual, or hybrid. Each type can have its own label, icon, text
 * colour and background colour via condition-visible style sections.
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
 * Elementor widget rendering the event type badge.
 */
class KDNA_Events_Widget_Event_Type_Badge extends KDNA_Events_Widget_Base {

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'kdna-events-event-type-badge';
	}

	/**
	 * Visible title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Event Type Badge', 'kdna-events' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-tags';
	}

	/**
	 * Event types handled by the widget.
	 *
	 * @return array<string,array{label:string,icon:string,bg:string,fg:string}>
	 */
	protected function types() {
		return array(
			'in-person' => array(
				'label' => __( 'In-person', 'kdna-events' ),
				'icon'  => 'eicon-pin',
				'bg'    => '#ecfdf5',
				'fg'    => '#065f46',
			),
			'virtual'   => array(
				'label' => __( 'Virtual', 'kdna-events' ),
				'icon'  => 'eicon-video-camera',
				'bg'    => '#eff6ff',
				'fg'    => '#1e3a8a',
			),
			'hybrid'    => array(
				'label' => __( 'Hybrid', 'kdna-events' ),
				'icon'  => 'eicon-globe',
				'bg'    => '#fef3c7',
				'fg'    => '#92400e',
			),
		);
	}

	/**
	 * Register controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$types = $this->types();

		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		foreach ( $types as $key => $info ) {
			$this->add_control(
				'label_' . str_replace( '-', '_', $key ),
				array(
					'label'   => sprintf( /* translators: %s: type label */ __( '%s label', 'kdna-events' ), $info['label'] ),
					'type'    => \Elementor\Controls_Manager::TEXT,
					'default' => $info['label'],
				)
			);
		}

		$this->add_control(
			'show_icon',
			array(
				'label'        => __( 'Show icon', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		foreach ( $types as $key => $info ) {
			$this->add_control(
				'icon_' . str_replace( '-', '_', $key ),
				array(
					'label'     => sprintf( /* translators: %s: type label */ __( '%s icon', 'kdna-events' ), $info['label'] ),
					'type'      => \Elementor\Controls_Manager::ICONS,
					'default'   => array(
						'value'   => $info['icon'],
						'library' => 'eicons',
					),
					'condition' => array( 'show_icon' => 'yes' ),
				)
			);
		}

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style',
			array(
				'label' => __( 'Badge', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->register_typography_control( 'typography', __( 'Typography', 'kdna-events' ), '.kdna-events-event-type-badge' );

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'badge_border',
				'selector' => '{{WRAPPER}} .kdna-events-event-type-badge',
			)
		);

		$this->add_control(
			'border_radius',
			array(
				'label'      => __( 'Border radius', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'default'    => array(
					'top'      => 999,
					'right'    => 999,
					'bottom'   => 999,
					'left'     => 999,
					'unit'     => 'px',
					'isLinked' => true,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-type-badge' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'badge_padding',
			array(
				'label'      => __( 'Padding', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'default'    => array(
					'top'      => 0.3,
					'right'    => 0.8,
					'bottom'   => 0.3,
					'left'     => 0.8,
					'unit'     => 'em',
					'isLinked' => false,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-type-badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'icon_size',
			array(
				'label'      => __( 'Icon size', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 8,
						'max' => 48,
					),
					'em' => array(
						'min'  => 0.5,
						'max'  => 3,
						'step' => 0.1,
					),
				),
				'default'    => array(
					'unit' => 'em',
					'size' => 1,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-type-badge__icon i, {{WRAPPER}} .kdna-events-event-type-badge__icon svg' => 'font-size: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array( 'show_icon' => 'yes' ),
			)
		);

		$this->add_control(
			'icon_spacing',
			array(
				'label'      => __( 'Icon spacing', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 24,
					),
					'em' => array(
						'min'  => 0,
						'max'  => 2,
						'step' => 0.1,
					),
				),
				'default'    => array(
					'unit' => 'em',
					'size' => 0.4,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-type-badge' => 'gap: {{SIZE}}{{UNIT}};',
				),
				'condition'  => array( 'show_icon' => 'yes' ),
			)
		);

		$this->register_spacing_controls( 'wrapper', '.kdna-events-event-type-badge' );

		$this->end_controls_section();

		foreach ( $types as $key => $info ) {
			$slug = str_replace( '-', '_', $key );
			$this->start_controls_section(
				'section_style_' . $slug,
				array(
					'label' => sprintf( /* translators: %s: type label */ __( '%s colours', 'kdna-events' ), $info['label'] ),
					'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
				)
			);

			$this->add_control(
				'bg_' . $slug,
				array(
					'label'     => __( 'Background', 'kdna-events' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'default'   => $info['bg'],
					'selectors' => array(
						'{{WRAPPER}} .kdna-events-event-type-badge--' . $key => 'background-color: {{VALUE}};',
					),
				)
			);

			$this->add_control(
				'fg_' . $slug,
				array(
					'label'     => __( 'Text colour', 'kdna-events' ),
					'type'      => \Elementor\Controls_Manager::COLOR,
					'default'   => $info['fg'],
					'selectors' => array(
						'{{WRAPPER}} .kdna-events-event-type-badge--' . $key => 'color: {{VALUE}};',
						'{{WRAPPER}} .kdna-events-event-type-badge--' . $key . ' .kdna-events-event-type-badge__icon i' => 'color: {{VALUE}};',
						'{{WRAPPER}} .kdna-events-event-type-badge--' . $key . ' .kdna-events-event-type-badge__icon svg' => 'fill: {{VALUE}};',
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
		$event_id = $this->get_event_id();
		if ( ! $event_id ) {
			$this->render_editor_placeholder( __( 'Create an event to preview the type badge.', 'kdna-events' ) );
			return;
		}

		$type  = (string) get_post_meta( $event_id, '_kdna_event_type', true );
		$types = $this->types();
		if ( '' === $type || ! isset( $types[ $type ] ) ) {
			$type = 'in-person';
		}

		$settings  = $this->get_settings_for_display();
		$slug      = str_replace( '-', '_', $type );
		$label_key = 'label_' . $slug;
		$icon_key  = 'icon_' . $slug;

		$label     = isset( $settings[ $label_key ] ) && '' !== $settings[ $label_key ] ? (string) $settings[ $label_key ] : $types[ $type ]['label'];
		$show_icon = 'yes' === ( $settings['show_icon'] ?? 'yes' );
		$icon      = $settings[ $icon_key ] ?? null;
		?>
		<span class="kdna-events-event-type-badge kdna-events-event-type-badge--<?php echo esc_attr( $type ); ?>">
			<?php if ( $show_icon && is_array( $icon ) && ! empty( $icon['value'] ) ) : ?>
				<span class="kdna-events-event-type-badge__icon" aria-hidden="true">
					<?php \Elementor\Icons_Manager::render_icon( $icon ); ?>
				</span>
			<?php endif; ?>
			<span class="kdna-events-event-type-badge__label"><?php echo esc_html( $label ); ?></span>
		</span>
		<?php
	}
}
