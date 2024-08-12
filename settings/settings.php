<?php

namespace sv_core;
use AllowDynamicProperties;
#[AllowDynamicProperties]
class settings extends sv_abstract{
	// properties
	private $parent					= false;
	private $no_prefix				= false;
	private $ID						= false;
	private $section				= false;
	private $section_group			= '';
	private $section_name			= '';
	private $section_description	= '';
	private $type					= false;
	private $title					= '';
	private $cluster				= '';
	private $description			= '';
	private $options				= array();
	private $placeholder			= '';
	private $maxlength				= false;
	private $minlength				= false;
	private $max					= false;
	private $min					= false;
	private $is_units 				= false;
	private $units					= array( 'px', 'em', 'rem', '%' );
	private $required  				= false;
	private $disabled  				= false;
	private $callback				= array();
	private $filter					= array();
	protected $prefix				= 'sv_';
	private $data					= false;
	private $default_value			= false;
	private $responsive			 = false;
	private $radio_style			= 'radio';
	private $is_label				= false;
	protected static $new			= array();
	private $properties				= [];
	private $types				= [];

	/**
	 * @desc			initialize
	 * @author			Matthias Bathke
	 * @since			1.0
	 * @ignore
	 */
	public function __construct(){

	}
	/**
	 * @desc			Load's requested libraries dynamicly
	 * @param	string	$name library-name
	 * @return			class object of the requested library
	 * @author			Matthias Bathke
	 * @since			1.0
	 * @ignore
	 */
	public function __get(string $name){
		$class_name							= __NAMESPACE__.'\\'.$name;

		if(!class_exists($class_name)) {
			require_once($this->get_path_core('settings/modules/' . $name . '/' . $name  . '.php'));
		}

		if(!$this->getProperty($name)) {
			$this->setProperty($name, new $class_name($this));
			$this->getProperty($name)->set_root($this->get_root());
			$this->getProperty($name)->set_parent($this);
		}

		return $this->getProperty($name);
	}
    public function setProperty($name, $value) {
		$this->properties[$name] = $value;
	}

    public function getProperty($name) {
		return $this->properties[$name] ?? null;
	}
	// OBJECT METHODS
	public static function create($parent){
		$new									= new static();

		$new->prefix							= $parent->get_prefix().'_';
		$new->set_root($parent->get_root());
		$new->set_parent($parent);

		return $new;
	}
	public function set_ID(string $ID): settings{
		$this->ID								= sanitize_key($ID);

		return $this;
	}
	public function get_ID(): string{
		return sanitize_key($this->ID);
	}
	public function set_is_no_prefix(bool $no = true): settings{
		$this->no_prefix								= $no;

		return $this;
	}
	public function get_is_no_prefix(): bool{
		return $this->no_prefix;
	}
	public function set_section(string $section): settings{
		$this->section							= $section;

		return $this;
	}
	public function get_section(): string{
		if(!$this->section){
			return $this->get_parent()->get_prefix();
		}else{
			return $this->section;
		}
	}
	public function set_section_group(string $section_group): settings{
		$this->section_group							= $section_group;

		return $this;
	}
	public function get_section_group(): string{
		if(!$this->section_group){
			return $this->get_section();
		}else{
			return $this->section_group;
		}
	}
	public function set_section_name(string $section_name): settings{
		$this->section_name						= $section_name;

		return $this;
	}
	public function get_section_name(): string{
		if(!$this->section_name){
			return $this->get_parent()->get_section_title();
		}else{
			return $this->section_name;
		}
	}
	public function set_section_description(string $description): settings{
		$this->section_description						= $description;

		return $this;
	}
	public function get_section_description(): string{
		if(!$this->section_description){
			return $this->get_parent()->get_section_desc();
		}else{
			return $this->section_description;
		}
	}
	public function setTypes($name, $value) {
		$this->types[$name] = $value;
	}

	public function getTypes($name) {
		return $this->types[$name] ?? null;
	}
	/*
	 * 	@param: $source		set a type for form field
	 */
	public function load_type(string $type){
		$type		= $this->type				= 'setting_'.$type;

		if(is_object($this->$type)){
			$this->$type = $this->$type->create($this->get_parent());
			$this->$type->set_root($this->get_root());
			$this->$type->set_parent($this);

			$this->init_wp_setting($this->$type);
		}
		// @todo: add proper error notice
		return $this;
	}
	public function get_type(){
		return $this->type;
	}
	public function run_type(){
		$type				= $this->get_type();

		if(isset($this->$type)){
			return $this->$type;
		}else{
			return $this;
		}
	}
	public function get_form_field(): string{
		return $this->default();
	}
	public function print_form_field(){
		echo $this->get_form_field();
	}
	public function set_title(string $title): settings{
		$this->title							= $title;

		return $this;
	}
	public function get_title(): string{
		return $this->title;
	}
	public function set_cluster(string $cluster): settings{
		$this->cluster							= $cluster;

		return $this;
	}
	public function get_cluster(): string{
		return $this->cluster;
	}
	public function set_is_label(bool $check): settings{
		$this->is_label							= $check;

		return $this;
	}
	public function get_is_label(): bool{
		return $this->is_label;
	}
	public function set_default_value($default_value): settings{
		$this->default_value							= $default_value;

		return $this;
	}
	public function get_default_value(){
		$value = $this->default_value;

		if($this->get_is_responsive() && !is_array($value)){
			$breakpoints = $this->get_breakpoints();

			foreach($breakpoints as &$bp){
				$bp = $this->default_value;
			}

			$value = $breakpoints;
		}

		// workaround for array defaults
		if($this->get_is_responsive() && is_array($value)){

			if(array_key_first($value) != 'mobile'){
				$breakpoints = $this->get_breakpoints();

				foreach($breakpoints as &$bp){
					$bp = $this->default_value;
				}

				$value = $breakpoints;
			}
		}

		return $value;
	}
	public function set_is_responsive(bool $check): settings{
		$this->responsive = $check;

		return $this;
	}
	public function get_is_responsive(): bool {
		return $this->responsive;
	}
	public function set_description(string $description){
		$this->description						= $description;

		return $this;
	}
	public function get_description(): string{
		return $this->description;
	}
	public function set_options(array $options): settings{
		$this->options						= $options;

		return $this;
	}
	public function get_options(): array{
		if ( count( $this->options ) === 0 ) {
			$this->options = array( __( 'No Options defined!', 'sv_core' ) );
		}

		return $this->options;
	}
	public function has_options(): bool{
		if ( count( $this->options ) === 0 ) {
			return false;
		}else{
			return true;
		}
	}
	public function set_placeholder(string $placeholder){
		$this->placeholder						= $placeholder;

		return $this;
	}
	public function get_placeholder(): string{
		return ( $this->placeholder ? $this->placeholder : $this->title );
	}
	public function set_maxlength( int $maxlength ) {
		$this->maxlength						= $maxlength;

		return $this;
	}
	public function get_maxlength(): int {
		return $this->maxlength;
	}
	public function set_minlength( int $minlength ) {
		$this->minlength						= 'pattern=".{' . $minlength .',}" title="' . __( 'You need at least', 'sv_core' ) . ' ' . $minlength . ' ' . __( 'characters.', 'sv_core' ) .  '"';

		return $this;
	}
	public function get_minlength(): string {
		return $this->minlength;
	}
	public function set_max( string $max ) {
		$this->max						= $max;

		return $this;
	}
	public function get_max(): string {
		return $this->max;
	}
	public function set_min( string $min ) {
		$this->min						= $min;

		return $this;
	}
	public function get_min(): string {
		return $this->min;
	}
	public function set_is_units() {
		$this->is_units = true;

		return $this;
	}
	public function get_is_units(): bool {
		return $this->is_units;
	}
	public function set_units( array $units ) {
		$this->units = $units;

		return $this;
	}
	public function get_units(): array {
		return $this->units;
	}
	public function set_required( bool $required ) {
		if( $required == true ) {
			$this->required						= 'required';
		} else {
			$this->required						= '';
		}

		return $this;
	}
	public function get_required(): string {
		return $this->required;
	}
	public function set_disabled( bool $disabled ) {
		if( $disabled == true ) {
			$this->disabled					= 'disabled';
		} else {
			$this->disabled						= '';
		}

		return $this;
	}
	public function get_disabled(): string {
		return $this->disabled;
	}

	public function get_radio_style(): string {
		return $this->radio_style;
	}

	public function set_radio_style( string $style ) {
		$this->radio_style			= $style;

		return $this;
	}

	public function get_data(){
		// Allow Setting Data override via constant
		if(defined($this->get_field_id())){
			return constant($this->get_field_id());
		}

		// Upgrade non-responsive data for responsive settings
		if($this->data !== false && !is_array($this->data) && $this->get_is_responsive()){
			$breakpoints = $this->get_breakpoints();

			foreach($breakpoints as &$bp){
				$bp = $this->data;
			}

			return $breakpoints;
		}

		// get data from db
		if($this->data === false){
			$db_data	= get_option($this->get_field_id());
			$this->data		= ($db_data === false || $db_data === '') ? $this->get_default_value() : $db_data;

			// Upgrade non-responsive data for responsive settings
			if($this->get_is_responsive() && !is_array($this->data)){
				$breakpoints = $this->get_breakpoints();

				foreach($breakpoints as &$bp){
					$bp = $this->data;
				}

				$this->data = $breakpoints;
			}

			// workaround for array defaults
			if($this->get_is_responsive() && is_array($this->data)){
				if(array_key_first($this->data) != 'mobile'){
					$breakpoints = $this->get_breakpoints();

					foreach($breakpoints as &$bp){
						$bp = $this->data;
					}

					$this->data = $breakpoints;
				}
			}

			if($this->data !== false && $db_data !== $this->data){ // fill options not created yet in DB with default values to allow cached requests
				update_option($this->get_field_id(),$this->data,'yes');
			}

			return $this->data;
		}

		return $this->data;
	}

	public function get_css_data(string $custom_property = '', string $prefix = '', string $suffix = ''): array{
		/*if($this->get_type() === false){
			error_log($this->get_field_id().__(' cannot be loaded correctly', 'sv_core'));
			return array();
		}*/

		if(strlen($custom_property) === 0){
			return $this->run_type()->get_css_data();
		}
		if(strlen($suffix) > 0){
			return $this->run_type()->get_css_data($custom_property, $prefix, $suffix);
		}
		if(strlen($prefix) > 0){
			return $this->run_type()->get_css_data($custom_property, $prefix);
		}

		return $this->run_type()->get_css_data($custom_property);
	}

	// set data value from external source
	public function set_data($data){
		$this->data		= $data;

		return $this;
	}
	public function prepare_css_property(string $val, string $prefix = '', string $suffix = ''): string{
		if($val === 'transparent,undefined,undefined,undefined'){ // if setting color is transparent, no output
			return '';
		}

		return $prefix.$val.$suffix;
	}
	public function prepare_css_property_responsive(array $val, string $prefix = '', string $suffix = ''): array{
		return array_map(function ($val) use($prefix, $suffix) {
			if($val === 'transparent,undefined,undefined,undefined'){ // if setting color is transparent, no output
				return '';
			}

			return (strlen($val) > 0) ? $prefix.$val.$suffix : $val; // do not add prefix or suffix when val is empty
		}, $val);
	}
	public function build_css(string $selector, $vars): string{
		if(!is_array($vars)){
			return '';
		}

		$output				= array();
		$merged_css			= array();
		$responsive_css		= array();

		foreach($this->get_breakpoints() as $breakpoint => $min_width){
			foreach($vars as $property => $value) {
				// non responsive setting
				if(!is_array($value) && strlen($value) > 0){
					$merged_css[$selector][$property]						= $property . ':' . $value . ';'. "\n";
				}
				elseif(is_array($value) && isset($value[$breakpoint]) && strlen($value[$breakpoint]) > 0) {
					// all values are equal, so no media query necessary
					$value_unique = array_unique($value);

					if (
						count($value_unique) === 1 || // all the same
						(
							// all empty but mobile
							count($value_unique) === 2 && // only two different values
							strlen($value_unique['mobile']) > 0 && // mobile is set
							strlen(end($value_unique)) === 0 // other element is empty
						)
					) {
						$merged_css[$selector][$property] = $property . ':' . $value[$breakpoint] . ';'. "\n";
					} else {
						// there are different values for specific media queries
						$responsive_css[$breakpoint][$selector][$property] = $property . ':' . $value[$breakpoint] . ';' . "\n";
					}
				}
			}
		}

		// css without need for media queries
		if(count($merged_css) > 0) {
			foreach ($merged_css as $s) {
				$output[] = $selector . '{ '. "\n";
				foreach ($s as $css) {
					$output[] = $css;
				}
				$output[] = '}' . "\n";
			}
		}

		// css with media queries required
		if(count($responsive_css) > 0) {
			$output[] = $this->wrap_media_queries($responsive_css);
		}

		return implode('', $output);
	}
	public function wrap_media_queries(array $css = array()): string{
		if(!is_array($css) || count($css) == 0){
			$css		= $this->get_data();
		}
		$output = array();

		if(is_array($css) && count($css) > 0) {
			foreach($css as $breakpoint => $s) {
				// orientation support
				if($breakpoint == 'mobile'){
					$orientation		= ' and (orientation: portrait)';
				}elseif($breakpoint == 'mobile_landscape'){
					$orientation		= ' and (orientation: landscape)';
				}elseif($breakpoint == 'tablet'){
					if($css[$breakpoint] === $css['mobile']){
						continue;
					}

					$orientation		= ' and (orientation: portrait)';
				}elseif($breakpoint == 'tablet_landscape'){
					if($css[$breakpoint] === $css['mobile_landscape']){
						continue;
					}

					$orientation		= ' and (orientation: landscape)';
				}elseif($breakpoint == 'tablet_pro'){
					if($css[$breakpoint] === $css['tablet']){
						continue;
					}

					$orientation		= ' and (orientation: portrait)';
				}elseif($breakpoint == 'tablet_pro_landscape'){
					if($css[$breakpoint] === $css['tablet_landscape']){
						continue;
					}

					$orientation		= ' and (orientation: landscape)';
				}else{
					$orientation		= '';
				}

				// generate media query
				$output[] = '@media ( min-width: ' . $this->get_breakpoints()[$breakpoint] . 'px )'.$orientation.' {'."\n";

				if(is_array($s)) {
					foreach ($s as $selector => $properties) {
						$output[] = $selector . '{' . "\n";
						if(is_array($properties)) {
							$output[] = implode('', $properties);
						}
						$output[] = "}\n";
					}
				}else{
					$output[]	 = $s;
				}

				$output[] = "}\n\n";
			}
		}

		return implode('', $output);
	}
	public function save_option(): bool{
		return update_option($this->get_field_id(), $this->get_data());
	}
	public function set_callback(array $callback): settings{
		$this->callback							= $callback;

		return $this;
	}
	public function get_callback(): array{
		return $this->callback;
	}
	public function run_callback($setting){
		if(count($this->get_callback()) == 2) {
			if (method_exists($this->get_callback()[0], $this->get_callback()[1])) {
				$class = $this->get_callback()[0];
				$method = $this->get_callback()[1];

				return $class->$method($setting);
			} else {
				// @todo: proper error notice
				return false;
			}
		}
	}
	public function set_filter(array $filter): settings{
		$this->filter							= $filter;

		return $this;
	}
	public function get_filter(): array{
		return $this->filter;
	}
	public static function get_module_settings_form($module): string{
		ob_start();
		echo '<form id="' . $module->get_name() . '" class="'.$module->get_parent()->get_prefix('settings_ajax_save_form').'" method="post" action="options.php" enctype="multipart/form-data">';
		\settings_fields($module->get_name()); // $option_group from register_settings()
		if($module->get_section_template_path()){
			require_once($module->get_section_template_path());
		}else {
			\do_settings_sections($module->get_name()); // $page from add_settings_section()
		}
		//\submit_button();
		echo '<p class="submit"><input type="submit" name="button-classic" id="submit-classic" class="button button-primary save-classic" value="'.__('Save', 'sv_core').'"></p>';
		echo '</form>';
		$form			= ob_get_contents();
		ob_end_clean();
		return $form;
	}
	public function section_callback(){
		echo '<div class="sv_section_description">'.$this->get_section_description().'</div>';
	}

	// Helper Methods

	// Returns a value in the rgb format, with alpha value
	// Example Output: 255,0,255,1
	function get_rgb( string $val, string $opacity = '1' ): string {
		// Value is a hex color
		if ( preg_match( '/^#[0-9A-F]{6}$/i', $val ) ) {
			list( $r, $g, $b ) = sscanf( $val, "#%02x%02x%02x" );

			return $r . ',' . $g . ',' . $b . ','.$opacity;
		}

		// Value is a rgb color
		elseif ( preg_match( '/(\d{1,3}),(\d{1,3}),(\d{1,3})/ix', str_replace( ' ', '', $val ) ) ) {
			return str_replace( ' ', '', $val );
		}

		// Couldn't detect format
		return $val;
	}

	// Returns a value in the hex format
	// Example Output: #ff00ff
	function get_hex( string $val ): string {
		// Value is a hex color
		if ( preg_match( '/^#[0-9A-F]{6}$/i', $val ) ) {
			return $val;
		}

		// Value is a rgb color
		elseif ( preg_match( '/(\d{1,3}),(\d{1,3}),(\d{1,3})/ix', str_replace( ' ', '', $val ) ) ) {
			$rgb = explode( ',', str_replace( ' ', '', $val ) );

			return sprintf( "#%02x%02x%02x", $rgb[0], $rgb[1], $rgb[2] );
		}

		// Couldn't detect format
		return $val;
	}

	/* methods for inheritance */
	public function default(bool $title = false): string{
		if($this->get_callback()){
			return $this->run_callback($this);
		}else{
			return $this->form(array('title' => $title));
		}
	}
	private function init_wp_setting($setting){
		if(is_admin()) {
			require_once(ABSPATH . '/wp-admin/includes/template.php');

			$section = $this->get_section();
			$section_group = $setting->get_parent()->get_section_group();
			$section_name = '';

			\add_settings_section(
				$section_group,											// $id, String for use in the 'id' attribute of tags.
				$section_name,											// $title, Title of the section.
				array($setting->get_parent(), 'section_callback'),	// $callback, Function that fills the section with the desired content. The function should echo its output.
				$section											// $page, the menu page on which to display this section
			);

			\add_settings_field(
				$setting->get_parent()->get_field_id(),											// $id, Slug-name to identify the field. Used in the 'id' attribute of tags.
				$setting->get_parent()->get_title(),								// $title, Formatted title of the field. Shown as the label for the field during output.
				array($setting->get_parent(), 'print_form_field'),					// $callback, Function that fills the field with the desired form inputs. The function should echo its output.
				$section,											// $page, The slug-name of the settings page on which to show the section (general, reading, writing, ...).
				$section_group,											// $section, The slug-name of the section of the settings page in which to show the box.
				array(														// $args, Extra arguments used when outputting the field.
					'description' => $setting->get_parent()->get_description(),
					'setting_id' => $setting->get_parent()->get_field_id()
				)
			);

			\register_setting(
				$section,											// $option_group, A settings group name.
				$setting->get_parent()->get_field_id(),			// $option_name, The name of an option to sanitize and save.
				array('sanitize_callback'	=> array($setting,'field_callback'))
			);
		}
	}
	public function field_callback($input){
		return $input;
		return $this->sanitize($input);
	}
	public function get_field_id(): string{
		return $this->get_is_no_prefix() ? $this->get_ID() : $this->get_prefix($this->get_ID());
	}
	public function widget(string $value, $object): string{
		$props                      = array();
		$props['ID']				= $object->get_field_id($this->get_ID());
		$props['title']				= $this->get_title();
		$props['cluster']			= $this->get_cluster();
		$props['description']		= $this->get_description();
		$props['name']				= $object->get_field_name($this->get_ID());
		$props['required']			= $this->get_required();
		$props['disabled']			= $this->get_disabled();
		$props['placeholder']		= $this->get_placeholder();
		$props['maxlength']			= $this->get_maxlength();
		$props['minlength']			= $this->get_minlength();
		$props['max']				= $this->get_max();
		$props['min']				= $this->get_min();
		$props['radio_style']		= $this->get_radio_style();
		$props['default_value']		= $this->get_default_value();
		$props['value']				= $value;

		return $this->form($props);
	}
	public function form(array $props = array()): string{
		$props		= $this->map_props($props);

		// non responsive setting
		if($this->get_is_responsive() === false) {
			return $this->load_form_field_html_wrapper($this->load_form_field_html($props), $props);
		}

		// responsive setting
		$output		= '';
		$i		  = 0;

		foreach($this->get_breakpoints() as $breakpoint => $min_width){
			$i++;
			$active							 = ($i === 1) ? 'active' : ''; // flag for responsive copy force script
			$props_child						= $props;
			$props_child['name']				= $props['name'].'['.$breakpoint.']';
			$props_child['ID']					= $props['ID'].'['.$breakpoint.']';
			$props_child['value']				= isset($props['value'][$breakpoint]) ? $props['value'][$breakpoint] : '';
			$props_child['default_value']		= isset($props['default_value'][$breakpoint]) ? $props['default_value'][$breakpoint] : '';

			$output .= '<div class="sv_setting_responsive sv_setting_responsive_'.$breakpoint.' '.$active.'">'.$this->load_form_field_html($this->map_props($props_child)).'</div>';
		}

		return $this->load_form_field_html_wrapper($output, $props);
	}
	private function load_form_field_html_wrapper(string $settings_html, array $props): string{
		ob_start();
		require($this->get_path_core('settings/tpl/_wrapper.php'));
		$output = ob_get_contents();
		ob_end_clean();

		return $output;
	}
	private function load_form_field_html(array $props): string{
		// hotfix
		$props = is_array($props) ? $props : [];
		ob_start();
		if(is_file($this->get_path_core('settings/tpl/'.$this->run_type()->get_module_name().'.php'))) {
			require($this->get_path_core('settings/tpl/' . $this->run_type()->get_module_name() . '.php'));
		}else{
			echo __('Settings Template not found: ', 'sv_core').$this->get_path_core('settings/tpl/'.$this->run_type()->get_module_name().'.php');
		}
		$output = ob_get_contents();
		ob_end_clean();

		return $output;
	}
	private function map_props(array $props): array{
		$props['ID']				= isset($props['ID']) ? $props['ID'] : $this->get_field_id();
		$props['title']				= isset($props['title']) ? $props['title'] : $this->get_title();
		$props['cluster']			= isset($props['cluster']) ? $props['cluster'] : $this->get_cluster();
		$props['description']		= isset($props['description']) ?  $props['description'] : $this->get_description();
		$props['name']				= isset($props['name']) ? $props['name'] : $this->get_field_id();
		$props['value']				= isset($props['value']) ? $props['value'] : $this->get_data();
		$props['required']			= isset($props['required']) ? $props['required'] : $this->get_required();
		$props['disabled']			= isset($props['disabled']) ? $props['disabled'] : $this->get_disabled();
		$props['placeholder']		= isset($props['placeholder']) ? $props['placeholder'] : $this->get_placeholder();
		$props['maxlength']			= isset($props['maxlength']) ? $props['maxlength'] : $this->get_maxlength();
		$props['minlength']			= isset($props['minlength']) ? $props['minlength'] : $this->get_minlength();
		$props['max']				= isset($props['max']) ? $props['max'] : $this->get_max();
		$props['min']				= isset($props['min']) ? $props['min'] : $this->get_min();
		$props['radio_style']		= isset($props['radio_style']) ? $props['radio_style'] : $this->get_radio_style();
		$props['default_value']		= isset($props['default_value']) ? $props['default_value'] : $this->get_default_value();

		return $props;
	}

	public function delete() :settings {
		if ( $this->get_type() == 'setting_upload' ) {
			wp_delete_attachment( $this->run_type()->get_data(), true );
		}

		delete_option( $this->run_type()->get_field_id() );

		return $this;
	}
	public function sanitize($meta_value){
		return sanitize_text_field($meta_value);
	}
	protected function print_sub_field(array $props, string $sub, array $options = []){
		if(is_file($this->get_path_core('settings/tpl/'.$this->run_type()->get_module_name().'_field.php'))) {
			require($this->get_path_core('settings/tpl/' . $this->run_type()->get_module_name() . '_field.php'));
		}else{
			echo __('Settings Template not found: ', 'sv_core').$this->get_path_core('settings/tpl/'.$this->run_type()->get_module_name().'_field.php');
		}
	}

	protected function get_responsive_select(): string {
		$output = '<div class="sv_setting_responsive_select">';
		$icon_mobile = '<svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="mobile" class="svg-inline--fa fa-mobile fa-w-10" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path d="M272 0H48C21.5 0 0 21.5 0 48v416c0 26.5 21.5 48 48 48h224c26.5 0 48-21.5 48-48V48c0-26.5-21.5-48-48-48zM160 480c-17.7 0-32-14.3-32-32s14.3-32 32-32 32 14.3 32 32-14.3 32-32 32z"></path></svg>';
		$icon_tablet = '<svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="tablet" class="svg-inline--fa fa-tablet fa-w-14" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M400 0H48C21.5 0 0 21.5 0 48v416c0 26.5 21.5 48 48 48h352c26.5 0 48-21.5 48-48V48c0-26.5-21.5-48-48-48zM224 480c-17.7 0-32-14.3-32-32s14.3-32 32-32 32 14.3 32 32-14.3 32-32 32z"></path></svg>';
		$icon_tablet_pro = '<svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="tablet-alt" class="svg-inline--fa fa-tablet-alt fa-w-14" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M400 0H48C21.5 0 0 21.5 0 48v416c0 26.5 21.5 48 48 48h352c26.5 0 48-21.5 48-48V48c0-26.5-21.5-48-48-48zM224 480c-17.7 0-32-14.3-32-32s14.3-32 32-32 32 14.3 32 32-14.3 32-32 32zm176-108c0 6.6-5.4 12-12 12H60c-6.6 0-12-5.4-12-12V60c0-6.6 5.4-12 12-12h328c6.6 0 12 5.4 12 12v312z"></path></svg>';
		$icon_desktop = '<svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="desktop" class="svg-inline--fa fa-desktop fa-w-18" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M528 0H48C21.5 0 0 21.5 0 48v320c0 26.5 21.5 48 48 48h192l-16 48h-72c-13.3 0-24 10.7-24 24s10.7 24 24 24h272c13.3 0 24-10.7 24-24s-10.7-24-24-24h-72l-16-48h192c26.5 0 48-21.5 48-48V48c0-26.5-21.5-48-48-48zm-16 352H64V64h448v288z"></path></svg>';
		$sizes = array(
			array( 'name' => 'mobile', 'landscape' => true ,'icon' => $icon_mobile ),
			array( 'name' => 'tablet', 'landscape' => true ,'icon' => $icon_tablet ),
			array( 'name' => 'tablet_pro', 'landscape' => true ,'icon' => $icon_tablet_pro ),
			array( 'name' => 'desktop', 'landscape' => false ,'icon' => $icon_desktop ),
		);

		for( $i = 0; $i < count($sizes); $i++ ) {
			$title = ucwords( str_replace( '_', ' ', $sizes[$i]['name'] ) );
			$output .= '<i class="sv_setting_responsive_icon';
			$output .= ($i === 0) ? ' active" ' : '" ';
			$output .= 'data-sv_setting_responsive_select="' . $sizes[$i]['name'] . '" ';
			$output .= 'title="' . $title . '">';
			$output .= $sizes[$i]['icon'] . '</i>';

			if ( $sizes[$i]['landscape'] ) {
				$output .= '<i class="sv_setting_responsive_icon landscape" ';
				$output .= 'data-sv_setting_responsive_select="' . $sizes[$i]['name'] . '_landscape" ';
				$output .= 'title="' . $title . ' Landscape">';
				$output .= $sizes[$i]['icon'] . '</i>';
			}
		}

		$output .= '</div>';

		return $output;
	}
}
