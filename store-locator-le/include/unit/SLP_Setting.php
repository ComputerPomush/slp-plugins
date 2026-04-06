<?php
defined( 'ABSPATH' ) || exit;

/**
 * Class SLP_Setting is the admin interface and rendering engine for a single SLP Option.
 *
 * use wp_kses_post to output embedded HTML tags
 * use esc_html to encode HTML (plain text)
 */
class SLP_Setting extends SLPlus_BaseClass_Object {
	/** @var object[] $allowed_tags */
	public $allowed_tags = array();

	protected $attributes = array();
	protected $classes = array();
	protected $data = array();
	protected $content = '';
	public $custom;
	public $data_field;
	public $description;
	public $disabled = false;
	public $display_value;
	public $id;
	public $label;
	public $name;
	public $wrapper = true;
	public $onChange = '';
	public $placeholder = '';
	public $related_to = '';
	public $show_label = true;
	public $type = 'custom';
	public $value;
	public $vue = false;

	public $uses_slplus = false;

	/**
	 * Initialize.
	 */
	protected function initialize() {
		add_filter( 'wp_kses_allowed_html', array( $this, 'filter_wp_kses_allowed_admin_tags' ) );
		add_filter( 'safe_style_css', array( $this, 'filter_safe_style_css_for_admin' ) );
		$this->set_data();
		$this->set_value();
		$this->set_display_value();
		$this->add_base_classes();
		$this->set_attributes();
		$this->at_startup();
	}

	/**
	 * Allow all inline CSS on the admin tabs.
	 *
	 * @param $safe_css_array
	 *
	 * @return array
	 */
	public function filter_safe_style_css_for_admin( $safe_css_array ) {
		return [];
	}

	/**
	 * Tell KSES these tags are allowed on our admin settings pages so wpcs does not freak out.
	 *
	 * It does not look like there is a valid esc* or kses* method that allows echo $var
	 * when $var contains valid admin-page tags like <input> or <select> which means re-tooling
	 * the entire plugin or having this filter extended what is allowed any time SLP_Setting is active.
	 * SLP_Settings is ONLY in play when an authorized admin user is logged in and on an admin page where
	 * they can set the behavior properties for the SLP family of plugins.
	 *
	 * @param $allowed_tags
	 *
	 * @return mixed
	 */
	public function filter_wp_kses_allowed_admin_tags( $allowed_tags ) {
		if ( ! empty ( $this->allowed_tags ) ) {
			return $this->allowed_tags;
		}

		$standard_attributes = array(
			'align'             => true,
			'allowfullscreen'   => true,
			'checked'           => true,
			'class'             => true,
			'color'             => true,
			'cols'              => true,
			'dark'              => true,
			'data-action'       => true,
			'data-cy'           => true,
			'data-field'        => true,
			'data-id'           => true,
			'data-maptype'      => true,
			'disabled'          => true,
			'dismissible'       => true,
			'false-value'       => true,
			'fluid'             => true,
			'frameborder'       => true,
			'height'            => true,
			'href'              => true,
			'id'                => true,
			'justify'           => true,
			'label'             => true,
			'name'              => true,
			'onchange'          => true,
			'onclick'           => true,
			'onkeypress'        => true,
			'placeholder'       => true,
			'ref'               => true,
			'required'          => true,
			'right'             => true,
			'row'               => true,
			'selected'          => true,
			'src'               => true,
			'style'             => true,
			'target'            => true,
			'title'             => true,
			'true-value'        => true,
			'type'              => true,
			'value'             => true,
			'v-bind:disabled'   => true,
			'v-bind:hint'       => true,
			'v-bind:key'        => true,
			'v-bind:label'      => true,
			'v-bind:loading'    => true,
			'v-bind:src'        => true,
			'v-bind:value'      => true,
			'v-if'              => true,
			'v-for'             => true,
			'v-model'           => true,
			'v-on:click.native' => true,
			'v-show'            => true,
			'width'             => true,
			'xs3'               => true,
		);
		// Standard Admin Form Stuff
		$allowed_tags['div']    = $standard_attributes;
		$allowed_tags['img']    = $standard_attributes;
		$allowed_tags['input']  = $standard_attributes;
		$allowed_tags['option'] = $standard_attributes;
		$allowed_tags['select'] = $standard_attributes;

		// Hackish stuff for advance Admin UX
		$allowed_tags['iframe'] = $standard_attributes;
		$allowed_tags['script'] = $standard_attributes;

		// Vue stuff
		$allowed_tags['template']              = $standard_attributes;
		$allowed_tags['v-alert']               = $standard_attributes;
		$allowed_tags['v-app']                 = $standard_attributes;
		$allowed_tags['v-btn']                 = $standard_attributes;
		$allowed_tags['v-card']                = $standard_attributes;
		$allowed_tags['v-card-title']          = $standard_attributes;
		$allowed_tags['v-checkbox']            = $standard_attributes;
		$allowed_tags['v-col']                 = $standard_attributes;
		$allowed_tags['v-container']           = $standard_attributes;
		$allowed_tags['v-content']             = $standard_attributes;
		$allowed_tags['v-data-table']          = $standard_attributes;
		$allowed_tags['v-divider']             = $standard_attributes;
		$allowed_tags['v-flex']                = $standard_attributes;
		$allowed_tags['v-icon']                = $standard_attributes;
		$allowed_tags['v-layout']              = $standard_attributes;
		$allowed_tags['v-list']                = $standard_attributes;
		$allowed_tags['v-list-tile']           = $standard_attributes;
		$allowed_tags['v-list-tile-action']    = $standard_attributes;
		$allowed_tags['v-list-tile-content']   = $standard_attributes;
		$allowed_tags['v-list-tile-sub-title'] = $standard_attributes;
		$allowed_tags['v-list-tile-title']     = $standard_attributes;
		$allowed_tags['v-progress-circular']   = $standard_attributes;
		$allowed_tags['v-row']                 = $standard_attributes;
		$allowed_tags['v-spacer']              = $standard_attributes;
		$allowed_tags['v-switch']              = $standard_attributes;
		$allowed_tags['v-text-field']          = $standard_attributes;
		$allowed_tags['v-textarea']            = $standard_attributes;

		$this->allowed_tags = $allowed_tags;

		return $this->allowed_tags;
	}

	/**
	 * Override this in your class to do things at startup after we are initialized. Optional.
	 */
	protected function at_startup() {
	}

	/**
	 * Render the setting using the content from your override get_content().
	 *
	 * Only override this if you do not want the standard HTML wrapper.
	 */
	public function display() {
		$data          = $this->get_data_string();
		$attributes    = $this->get_attribute_string();
		$this->content = $this->get_content( $data, $attributes );
		$this->wrap_in_default_html();
	}

	/**
	 * Add our base classes
	 */
	private function add_base_classes() {
		$this->classes[] = 'input-group';
		$this->classes[] = 'wpcsl-' . $this->type;
	}

	/**
	 * Create the HTML attribute string by joining the attributes array.
	 * @return string
	 */
	protected function get_attribute_string() {
		if ( empty( $this->attributes ) ) {
			return '';
		}

		return join( ' ', $this->attributes );
	}

	/**
	 * Create the HTML classes string by joining the classes array.
	 *
	 * @return string
	 */
	protected function get_classes_string( $classes_array = null ) {
		if ( is_null( $classes_array ) ) {
			$classes_array = $this->classes;
		}
		if ( empty( $classes_array ) ) {
			return '';
		}

		return join( ' ', $classes_array );
	}

	/**
	 * Get the content to be displayed.  Override to generate the custom content for your setting.
	 *
	 * @param string $data The data-* attributes
	 * @param string $attributes All other attributes.
	 *
	 * @return string
	 */
	protected function get_content( $data, $attributes ) {
		return '';
	}

	/**
	 * Create the HTML data string by joining the data array.
	 * @return string
	 */
	protected function get_data_string( $data_array = null ) {
		if ( is_null( $data_array ) ) {
			$data_array = $this->data;
		}
		if ( empty( $data_array ) ) {
			return '';
		}
		$html_snippet = '';
		foreach ( $data_array as $slug => $value ) {
			$html_snippet .= sprintf( ' data-%s="%s"', $slug, $value );
		}

		return $html_snippet;
	}

	/**
	 * Render the description if needed.
	 */
	public function render_description() {
		if ( empty( $this->description ) ) {
			return;
		}
		?>
        <div class="input-description">
            <span class="input-description-text"><?php echo wp_kses_post( $this->description ); ?></span>
        </div>
		<?php
	}

	/**
	 * Render the label if needed.
	 */
	protected function render_label() {
		if ( ! $this->show_label ) {
			return;
		}
		?>
        <div class="label input-label">
            <label for='<?php echo esc_html( $this->name ); ?>'><?php echo esc_html( $this->label ); ?></label>
        </div>
		<?php
	}

	/**
	 * Set extra attributes.
	 */
	private function set_attributes() {
		$this->set_on_change_attribute();
		$this->set_placeholder_attribute();
		$this->set_disabled_attribute();
	}

	/**
	 * Set the data.
	 */
	private function set_data() {
		$this->set_id();

		if ( ! isset( $this->data_field ) ) {
			$this->data_field = $this->id;
		}
		$this->data['field'] = $this->data_field;

		if ( ! empty( $this->related_to ) ) {
			$this->data['related_to'] = $this->related_to;
		}
	}

	/**
	 * Set disabled attribute
	 */
	private function set_disabled_attribute() {
		if ( ! $this->disabled ) {
			return;
		}
		$this->attributes['disabled'] = "disabled='disabled'";
		$this->classes[]              = 'disabled';
	}

	/**
	 * Set the display value
	 */
	protected function set_display_value() {
		if ( isset( $this->display_value ) ) {
			return;
		}
		$this->display_value = esc_html( $this->value );
	}

	/**
	 * Set the ID
	 */
	private function set_id() {
		if ( isset( $this->id ) ) {
			return;
		}
		$this->id = $this->name;
	}

	/**
	 * Set onChange  attribute
	 */
	private function set_on_change_attribute() {
		if ( empty( $this->onChange ) ) {
			return;
		}
		$this->attributes['onchange'] = sprintf( "onchange='%s'", $this->onChange );
	}

	/**
	 * Set placeholder attribute
	 */
	private function set_placeholder_attribute() {
		if ( empty( $this->placeholder ) ) {
			return;
		}
		$this->attributes['placeholder'] = sprintf( "placeholder='%s'", $this->placeholder );
	}

	/**
	 * Set the value
	 */
	private function set_value() {
		if ( isset( $this->value ) ) {
			return;
		}
		global $slplus_plugin;
		$this->slplus = $slplus_plugin;
		$this->value  = $this->slplus->WPOption_Manager->get_wp_option( $this->name );
		if ( is_array( $this->value ) ) {
			$this->value = print_r( $this->value, true );
		}
		$this->value = htmlspecialchars( $this->value );
	}

	/**
	 * Wrap our HTML in default divs.
	 */
	protected function wrap_in_default_html() {
		// wrap
		$content_postregex_prekses = preg_replace( '/\s+/', ' ', $this->content );


		if ( ! $this->wrapper ) {
			echo wp_kses_post( $content_postregex_prekses );

			return;
		}

		$id         = empty( $this->id ) ? '' : sprintf( 'input-group-%s', $this->id );
		$related_to = empty( $this->data['related_to'] ) ? '' : $this->data['related_to'];
		?>
        <div
                class="<?php echo esc_attr( $this->get_classes_string() ) ?>"
                id="<?php echo esc_attr( $id ) ?>"
                data-related_to="<?php echo esc_attr( $related_to ) ?>"
        >
			<?php $this->render_label(); ?>
            <div class="input input-field">
				<?php echo wp_kses_post( $content_postregex_prekses ) ?>
            </div>
			<?php $this->render_description(); ?>
        </div>
		<?php
	}


}
