<?php
/**
 * Checkout Attendees widget.
 *
 * Renders one fieldset per ticket on quantity change. Each fieldset
 * collects name (required), email (required), phone (optional unless
 * forced required), and every custom attendee field defined on the
 * event. A 'Copy details from ticket 1' checkbox on attendees 2+
 * auto-fills and disables their matching fields.
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
 * Elementor widget rendering the attendee forms.
 */
class KDNA_Events_Widget_Checkout_Attendees extends KDNA_Events_Widget_Base {

	/**
	 * @return string
	 */
	public function get_name() {
		return 'kdna-events-checkout-attendees';
	}

	/**
	 * @return string
	 */
	public function get_title() {
		return __( 'Checkout Attendees', 'kdna-events' );
	}

	/**
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-form-horizontal';
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
			'heading_template',
			array(
				'label'       => __( 'Section heading format', 'kdna-events' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Attendee {n}', 'kdna-events' ),
				'description' => __( 'Use {n} for the ticket number.', 'kdna-events' ),
			)
		);

		$this->add_control(
			'allow_copy',
			array(
				'label'        => __( 'Offer copy-from-first for 2+ attendees', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'copy_label',
			array(
				'label'     => __( 'Copy-from-first label', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'Copy details from ticket 1', 'kdna-events' ),
				'condition' => array( 'allow_copy' => 'yes' ),
			)
		);

		$this->add_control(
			'required_marker',
			array(
				'label'   => __( 'Required marker', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => '*',
			)
		);

		$this->add_control(
			'show_helper',
			array(
				'label'        => __( 'Show field helper text', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'phone_required',
			array(
				'label'        => __( 'Force phone required', 'kdna-events' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'description'  => __( 'When on, phone becomes mandatory on every ticket.', 'kdna-events' ),
			)
		);

		$this->add_control(
			'divider_style',
			array(
				'label'   => __( 'Divider between attendees', 'kdna-events' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'line',
				'options' => array(
					'none'   => __( 'None', 'kdna-events' ),
					'line'   => __( 'Line', 'kdna-events' ),
					'spacer' => __( 'Spacer', 'kdna-events' ),
				),
			)
		);

		$this->end_controls_section();

		// Section heading style.
		$this->start_controls_section(
			'section_style_heading',
			array(
				'label' => __( 'Section heading', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'heading_color',
			array(
				'label'     => __( 'Colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-checkout-attendees__heading' => 'color: {{VALUE}};',
				),
			)
		);
		$this->register_typography_control( 'heading_typography', __( 'Typography', 'kdna-events' ), '.kdna-events-checkout-attendees__heading' );
		$this->add_responsive_control(
			'heading_margin',
			array(
				'label'      => __( 'Margin', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-checkout-attendees__heading' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);
		$this->end_controls_section();

		// Labels style.
		$this->start_controls_section(
			'section_style_labels',
			array(
				'label' => __( 'Field labels', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'field_label_color',
			array(
				'label'     => __( 'Colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-checkout-attendees__label' => 'color: {{VALUE}};',
				),
			)
		);
		$this->register_typography_control( 'field_label_typography', __( 'Typography', 'kdna-events' ), '.kdna-events-checkout-attendees__label' );
		$this->add_control(
			'required_color',
			array(
				'label'     => __( 'Required marker colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#dc2626',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-checkout-attendees__required' => 'color: {{VALUE}};',
				),
			)
		);
		$this->end_controls_section();

		// Inputs.
		$input_selector = '.kdna-events-checkout-attendees__field input, {{WRAPPER}} .kdna-events-checkout-attendees__field select';
		$this->start_controls_section(
			'section_style_inputs',
			array(
				'label' => __( 'Inputs', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'input_color',
			array(
				'label'     => __( 'Text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} ' . $input_selector => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'input_background',
			array(
				'label'     => __( 'Background', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} ' . $input_selector => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'placeholder_color',
			array(
				'label'     => __( 'Placeholder colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-checkout-attendees__field input::placeholder' => 'color: {{VALUE}};',
				),
			)
		);
		$this->register_typography_control( 'input_typography', __( 'Typography', 'kdna-events' ), $input_selector );
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			array(
				'name'     => 'input_border',
				'selector' => '{{WRAPPER}} ' . $input_selector,
			)
		);
		$this->add_control(
			'input_focus_border',
			array(
				'label'     => __( 'Focus border colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#1d4ed8',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-checkout-attendees__field input:focus, {{WRAPPER}} .kdna-events-checkout-attendees__field select:focus' => 'border-color: {{VALUE}}; outline: none; box-shadow: 0 0 0 3px {{VALUE}}33;',
				),
			)
		);
		$this->add_control(
			'error_color',
			array(
				'label'     => __( 'Error text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#dc2626',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-checkout-attendees__error' => 'color: {{VALUE}};',
					'{{WRAPPER}} .kdna-events-checkout-attendees__field.has-error input, {{WRAPPER}} .kdna-events-checkout-attendees__field.has-error select' => 'border-color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'helper_color',
			array(
				'label'     => __( 'Helper text colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-checkout-attendees__helper' => 'color: {{VALUE}};',
				),
			)
		);
		$this->end_controls_section();

		// Fieldset layout.
		$this->start_controls_section(
			'section_style_fieldset',
			array(
				'label' => __( 'Fieldsets', 'kdna-events' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_responsive_control(
			'fieldset_spacing',
			array(
				'label'      => __( 'Spacing between attendees', 'kdna-events' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 64,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 20,
				),
				'selectors'  => array(
					'{{WRAPPER}} .kdna-events-checkout-attendees__list' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);
		$this->add_control(
			'checkbox_accent',
			array(
				'label'     => __( 'Checkbox accent colour', 'kdna-events' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#1d4ed8',
				'selectors' => array(
					'{{WRAPPER}} .kdna-events-checkout-attendees__copy input' => 'accent-color: {{VALUE}};',
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

		$heading_tpl    = (string) ( $settings['heading_template'] ?? __( 'Attendee {n}', 'kdna-events' ) );
		$copy_label     = (string) ( $settings['copy_label'] ?? __( 'Copy details from ticket 1', 'kdna-events' ) );
		$required_mark  = (string) ( $settings['required_marker'] ?? '*' );
		$allow_copy     = 'yes' === ( $settings['allow_copy'] ?? 'yes' );
		$show_helper    = 'yes' === ( $settings['show_helper'] ?? 'yes' );
		$phone_required = 'yes' === ( $settings['phone_required'] ?? '' );
		$divider_style  = (string) ( $settings['divider_style'] ?? 'line' );

		$fields = KDNA_Events_Checkout::get_attendee_fields( $event_id );

		$config = array(
			'eventId'        => $event_id,
			'headingTpl'     => $heading_tpl,
			'copyLabel'      => $copy_label,
			'required'       => $required_mark,
			'showHelper'     => $show_helper,
			'allowCopy'      => $allow_copy,
			'phoneRequired'  => $phone_required,
			'customFields'   => $fields,
			'i18n'           => array(
				'name'         => __( 'Full name', 'kdna-events' ),
				'email'        => __( 'Email address', 'kdna-events' ),
				'phone'        => __( 'Phone', 'kdna-events' ),
				'nameMissing'  => __( 'Please enter a name.', 'kdna-events' ),
				'emailMissing' => __( 'Please enter a valid email.', 'kdna-events' ),
				'phoneMissing' => __( 'Please enter a phone number.', 'kdna-events' ),
				'fieldMissing' => __( 'This field is required.', 'kdna-events' ),
				'namePlace'    => __( 'e.g. Jane Doe', 'kdna-events' ),
				'emailPlace'   => __( 'name@example.com', 'kdna-events' ),
				'phonePlace'   => __( '+61 400 000 000', 'kdna-events' ),
			),
		);

		$json = wp_json_encode( $config );
		?>
		<div
			class="kdna-events-checkout-attendees kdna-events-checkout-attendees--divider-<?php echo esc_attr( $divider_style ); ?>"
			data-kdna-events-checkout-attendees="1"
			data-event-id="<?php echo esc_attr( (string) $event_id ); ?>"
			data-phone-required="<?php echo $phone_required ? '1' : '0'; ?>"
			data-config="<?php echo esc_attr( (string) $json ); ?>"
		>
			<div class="kdna-events-checkout-attendees__list" aria-live="polite"></div>
		</div>
		<?php
	}
}
