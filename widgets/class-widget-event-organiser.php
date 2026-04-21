<?php
/**
 * Event Organiser widget.
 *
 * Renders the event organiser name, optionally with an email link and
 * a prefix line such as 'Organised by'. Layout switches between
 * stacked and inline. Typography is split across the prefix, name and
 * email so each can be styled independently.
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
 * Elementor widget rendering the event organiser.
 */
class KDNA_Events_Widget_Event_Organiser extends KDNA_Events_Widget_Base {

	/**
	 * Machine name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'kdna-events-event-organiser';
	}

	/**
	 * Visible title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Event Organiser', 'kdna-events' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-person';
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
			'show_name',
			array(
				'label'        => __( 'Show name', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_email',
			array(
				'label'        => __( 'Show email link', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->add_control(
			'email_label',
			array(
				'label'     => __( 'Email label', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'Contact organiser', 'kdna-events' ),
				'condition' => array( 'show_email' => 'yes' ),
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Layout', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'inline',
				'options' => array(
					'inline'  => __( 'Inline', 'kdna-events' ),
					'stacked' => __( 'Stacked', 'kdna-events' ),
				),
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
					'value'   => 'eicon-user-circle-o',
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

		$this->add_control(
			'show_prefix',
			array(
				'label'        => __( 'Show prefix', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'prefix_text',
			array(
				'label'     => __( 'Prefix text', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'Organised by', 'kdna-events' ),
				'condition' => array( 'show_prefix' => 'yes' ),
			)
		);

		$this->add_responsive_control(
			'align',
			array(
				'label'     => __( 'Alignment', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array(
						'title' => __( 'Left', 'kdna-events' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'     => array(
						'title' => __( 'Centre', 'kdna-events' ),
						'icon'  => 'eicon-text-align-center',
					),
					'flex-end'   => array(
						'title' => __( 'Right', 'kdna-events' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-organiser' => 'justify-content: {{VALUE}}; align-items: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_layout_style',
			array(
				'label' => __( 'Layout', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'gap',
			array(
				'label'      => __( 'Gap', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 48,
					),
					'em' => array(
						'min'  => 0,
						'max'  => 3,
						'step' => 0.1,
					),
				),
				'default'    => array(
					'unit' => 'em',
					'size' => 0.5,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-organiser' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->register_spacing_controls( 'wrapper', '.kdna-events-event-organiser' );

		$this->end_controls_section();

		$this->start_controls_section(
			'section_prefix_style',
			array(
				'label'     => __( 'Prefix', 'kdna-events' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_prefix' => 'yes' ),
			)
		);

		$this->add_control(
			'prefix_color',
			array(
				'label'     => __( 'Colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-organiser__prefix' => 'color: {{VALUE}};',
				),
			)
		);

		$this->register_typography_control( 'prefix_typography', __( 'Typography', 'kdna-events' ), '.kdna-events-event-organiser__prefix' );

		$this->end_controls_section();

		$this->start_controls_section(
			'section_name_style',
			array(
				'label' => __( 'Name', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'name_color',
			array(
				'label'     => __( 'Colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-organiser__name' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'name_hover_color',
			array(
				'label'     => __( 'Hover colour (when linked)', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} a.kdna-events-event-organiser__name:hover' => 'color: {{VALUE}};',
				),
			)
		);

		$this->register_typography_control( 'name_typography', __( 'Typography', 'kdna-events' ), '.kdna-events-event-organiser__name' );

		$this->end_controls_section();

		$this->start_controls_section(
			'section_email_style',
			array(
				'label'     => __( 'Email link', 'kdna-events' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_email' => 'yes' ),
			)
		);

		$this->add_control(
			'email_color',
			array(
				'label'     => __( 'Colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-organiser__email' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'email_hover_color',
			array(
				'label'     => __( 'Hover colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-organiser__email:hover' => 'color: {{VALUE}};',
				),
			)
		);

		$this->register_typography_control( 'email_typography', __( 'Typography', 'kdna-events' ), '.kdna-events-event-organiser__email' );

		$this->end_controls_section();

		$this->start_controls_section(
			'section_icon_style',
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
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-event-organiser__icon i, {{WRAPPER}} .kdna-events-event-organiser__icon svg' => 'color: {{VALUE}}; fill: {{VALUE}};',
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
						'min' => 8,
						'max' => 96,
					),
					'em' => array(
						'min'  => 0.5,
						'max'  => 5,
						'step' => 0.1,
					),
				),
				'default'    => array(
					'unit' => 'em',
					'size' => 1.25,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-event-organiser__icon i, {{WRAPPER}} .kdna-events-event-organiser__icon svg' => 'font-size: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
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
		$event_id = $this->get_event_id();
		if ( ! $event_id ) {
			$this->render_editor_placeholder( __( 'Create an event to preview the organiser.', 'kdna-events' ) );
			return;
		}

		$name  = (string) get_post_meta( $event_id, '_kdna_event_organiser_name', true );
		$email = (string) get_post_meta( $event_id, '_kdna_event_organiser_email', true );

		if ( '' === $name && '' === $email ) {
			$this->render_editor_placeholder( __( 'No organiser set for this event.', 'kdna-events' ) );
			return;
		}

		$settings = $this->get_settings_for_display();

		$show_name   = 'yes' === ( $settings['show_name'] ?? 'yes' );
		$show_email  = 'yes' === ( $settings['show_email'] ?? '' ) && '' !== $email;
		$show_prefix = 'yes' === ( $settings['show_prefix'] ?? 'yes' );
		$show_icon   = 'yes' === ( $settings['show_icon'] ?? 'yes' );
		$layout      = $settings['layout'] ?? 'inline';
		$icon_pos    = $settings['icon_position'] ?? 'left';
		$prefix_text = (string) ( $settings['prefix_text'] ?? __( 'Organised by', 'kdna-events' ) );
		$email_label = (string) ( $settings['email_label'] ?? __( 'Contact organiser', 'kdna-events' ) );

		$classes = array(
			'kdna-events-event-organiser',
			'kdna-events-event-organiser--' . ( 'stacked' === $layout ? 'stacked' : 'inline' ),
			'kdna-events-event-organiser--icon-' . ( 'right' === $icon_pos ? 'right' : 'left' ),
		);
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<?php if ( $show_icon && ! empty( $settings['icon']['value'] ) ) : ?>
				<span class="kdna-events-event-organiser__icon" aria-hidden="true">
					<?php \Elementor\Icons_Manager::render_icon( $settings['icon'] ); ?>
				</span>
			<?php endif; ?>
			<div class="kdna-events-event-organiser__text">
				<?php if ( $show_prefix && '' !== $prefix_text ) : ?>
					<span class="kdna-events-event-organiser__prefix"><?php echo esc_html( $prefix_text ); ?></span>
				<?php endif; ?>
				<?php if ( $show_name && '' !== $name ) : ?>
					<?php if ( $show_email ) : ?>
						<a class="kdna-events-event-organiser__name" href="<?php echo esc_url( 'mailto:' . $email ); ?>"><?php echo esc_html( $name ); ?></a>
					<?php else : ?>
						<span class="kdna-events-event-organiser__name"><?php echo esc_html( $name ); ?></span>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ( $show_email ) : ?>
					<a class="kdna-events-event-organiser__email" href="<?php echo esc_url( 'mailto:' . $email ); ?>">
						<?php echo esc_html( '' !== $email_label ? $email_label : $email ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
