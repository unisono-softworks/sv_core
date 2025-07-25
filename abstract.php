<?php
	namespace sv_core;

	abstract class sv_abstract {
		const version_core					= 10001;

		protected $name						= false;
		protected $module_name				= false;
		protected $basename					= false;
		protected $path						= false;
		protected $prefix                   = false;
		protected $url						= false;
		protected $version					= false;
		private $parent						= false;
		private $root						= false;
		protected $s						= array(); // settings object array
		protected $s_clustered				= array(); // clustered settings object array
		protected $m						= array(); // metabox object array
		protected static $wpdb				= false;
		private static $instances			= array();
		protected static $instances_active	= array();
		protected $loaded					= array();
		protected static $active_core		= false;
		protected static $path_core			= false;
		protected static $url_core			= false;
		protected $sections					= array();
		protected $section_types			= array();
		protected $section_template_path	= '';
		protected $section_title			= false;
		protected $section_order			= false;
		protected $section_desc				= false;
		protected $section_privacy			= false;
		protected $section_type				= 'settings';
		protected $section_icon				= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M24 14.187v-4.374c-2.148-.766-2.726-.802-3.027-1.529-.303-.729.083-1.169 1.059-3.223l-3.093-3.093c-2.026.963-2.488 1.364-3.224 1.059-.727-.302-.768-.889-1.527-3.027h-4.375c-.764 2.144-.8 2.725-1.529 3.027-.752.313-1.203-.1-3.223-1.059l-3.093 3.093c.977 2.055 1.362 2.493 1.059 3.224-.302.727-.881.764-3.027 1.528v4.375c2.139.76 2.725.8 3.027 1.528.304.734-.081 1.167-1.059 3.223l3.093 3.093c1.999-.95 2.47-1.373 3.223-1.059.728.302.764.88 1.529 3.027h4.374c.758-2.131.799-2.723 1.537-3.031.745-.308 1.186.099 3.215 1.062l3.093-3.093c-.975-2.05-1.362-2.492-1.059-3.223.3-.726.88-.763 3.027-1.528zm-4.875.764c-.577 1.394-.068 2.458.488 3.578l-1.084 1.084c-1.093-.543-2.161-1.076-3.573-.49-1.396.581-1.79 1.693-2.188 2.877h-1.534c-.398-1.185-.791-2.297-2.183-2.875-1.419-.588-2.507-.045-3.579.488l-1.083-1.084c.557-1.118 1.066-2.18.487-3.58-.579-1.391-1.691-1.784-2.876-2.182v-1.533c1.185-.398 2.297-.791 2.875-2.184.578-1.394.068-2.459-.488-3.579l1.084-1.084c1.082.538 2.162 1.077 3.58.488 1.392-.577 1.785-1.69 2.183-2.875h1.534c.398 1.185.792 2.297 2.184 2.875 1.419.588 2.506.045 3.579-.488l1.084 1.084c-.556 1.121-1.065 2.187-.488 3.58.577 1.391 1.689 1.784 2.875 2.183v1.534c-1.188.398-2.302.791-2.877 2.183zm-7.125-5.951c1.654 0 3 1.346 3 3s-1.346 3-3 3-3-1.346-3-3 1.346-3 3-3zm0-2c-2.762 0-5 2.238-5 5s2.238 5 5 5 5-2.238 5-5-2.238-5-5-5z"/></svg>';
		protected $scripts_queue			= array();
		protected static $expert_mode		= false;
		public static $breakpoints			= false;

		protected $block_handle             = '';
		protected $block_name               = '';

		protected $modules_loaded 			= array();

		protected $module_css_cache			= true; // default false, set true in a module to opt in for CSS Caching

		/**
		 * @desc			initialize plugin
		 * @author			Matthias Bathke
		 * @since			1.0
		 * @ignore
		 */
		public function load_translation() {
			$locale = apply_filters( 'plugin_locale', determine_locale(), 'sv_core' );
			load_textdomain( 'sv_core', dirname( __FILE__ ) . '/languages/sv_core-'.$locale.'.mo' );
		}
		/**
		 * @desc			Load's requested libraries dynamicly
		 * @param	string	$name library-name
		 * @return			class object of the requested library
		 * @author			Matthias Bathke
		 * @since			1.0
		 * @ignore
		 */
		public function __get( string $name ) {
			// look for class file in modules directory
			// @deprecated: move module files into own subdirectory
			// @todo: remove
			if ( is_file($this->get_path( 'lib/modules/'.$name . '.php' )) ) {
				require_once( $this->get_path( 'lib/modules/'.$name . '.php' ) );

				$class_name		= $this->get_root()->get_name() . '\\' . $name;
				// @todo: deprecated in PHP 8
				@$this->$name	= new $class_name();
				$this->$name->set_root( $this->get_root() );
				$this->$name->set_parent( $this );

				return $this->$name;
			}

			if ( is_file($this->get_root()->get_path( 'lib/modules/'.$name . '/'.$name . '.php' )) ) {
				require_once( $this->get_root()->get_path( 'lib/modules/'.$name . '/'.$name . '.php' ) );

				$class_name		= $this->get_root()->get_name() . '\\' . $name;
				// @todo: deprecated in PHP 8
				@$this->$name	= new $class_name();
				$this->$name->set_root( $this->get_root() );
				$this->$name->set_parent( $this );
				$this->$name->set_path( $this->get_root()->get_path( 'lib/modules/'.$name . '/' ) );
				$this->$name->set_url( $this->get_root()->get_url( 'lib/modules/'.$name . '/' ) );

				return $this->$name;
			}

			throw new \Exception( __( 'Class', 'sv_core' ) . ' '
				. $name
				. ' ' . __( 'could not be loaded (tried to load class-file', 'sv_core' ) . ' '
				. $this->get_path()
				. 'lib/modules/'.$name . '.php)' );
		}

		public function wordpress_version_check(string $min_version = '6.0'){
			$wp_version = '1.0.0'; // declare default even it's re-declared by an included file
			// Get unmodified $wp_version.
			include ABSPATH . WPINC . '/version.php';
			// Strip '-src' from the version string. Messes up version_compare().
			$wp_version = str_replace( '-src', '', $wp_version );
			if ( version_compare( $wp_version, $min_version, '<' ) ) {
				add_action( 'admin_notices', array($this, 'wordpress_version_notice') );
			}
		}

		/*
		 * @todo include a return in the parent function when the child function should have a return
		 */
		public function wordpress_version_notice(){
			// extend in children when needed.

		}

		public function set_is_expert_mode(bool $yes = true){
			static::$expert_mode = $yes;
		}

		public function get_is_expert_mode(): bool {
			return static::$expert_mode;
		}

		public function set_parent( $parent ) {
			$this->parent = $parent;

			return $this;
		}

		public function get_parent() {
			return $this->parent ? $this->parent : $this;
		}

		public function get_root() {
			return $this->root ? $this->root : $this;
		}

		public function set_root( $root ) {
			$this->root = $root;

			return $this;
		}

		// Returns the current core object, that is in use
		public function get_active_core(): core {
			return $this::$active_core;
		}

		public function get_previous_version(): int{
			return intval( get_option( $this->get_root()->get_prefix( 'version') ) );
		}

		public function update_version(int $version): int{
			return update_option( $this->get_root()->get_prefix( 'version'), $version );
		}

		public function get_version( bool $formatted = false ): string{
			$output = '0';

			if ( defined( get_called_class() . '::version' ) ) {
				if ( $formatted === true ) {
					$output =  number_format( get_called_class()::version, 0, ',', '.' );
				} else {
					$output =  get_called_class()::version;
				}
			} else {
				if ( $formatted ) {
					$output = __( 'not defined', 'sv_core' );
				}
			}

			return $output;
		}

		public function get_version_core_match( bool $formatted = false ): string {
			$output = '0';

			if ( defined( get_called_class() . '::version_core_match' ) ) {
				if ( $formatted === true ) {
					$output = number_format( get_called_class()::version_core_match, 0, ',', '.' );
				} else {
					$output = get_called_class()::version_core_match;
				}
			} else {
				if ( $formatted ) {
					$output = __( 'not defined', 'sv_core' );
				}
			}

			return $output;
		}

		public function get_version_core( bool $formatted = false ): string {
			$output = '0';

			if ( defined( get_called_class() . '::version_core' ) ) {
				if ( $formatted === true) {
					$output = number_format( get_called_class()::version_core, 0, ',', '.' );
				} else {
					$output = get_called_class()::version_core;
				}
			} else {
				if ( $formatted ) {
					$output = __( 'not defined', 'sv_core' );
				}
			}

			return $output;
		}

		public function find_parent( $class_name, bool $qualified = false ){
			/*
			 * @todo shouldn't we return an empty object or better NULL as default here?
			 * same for find_parent_by_name or other object returning functions
			 * a change to null or empty object could be a breaking change
			 */
			$output = false;

			if ( $this->get_parent() != $this->get_root() ) {
				if ( $qualified === false ) {
					if ( $this->get_parent()->get_module_name() == $class_name ) {
						$output = $this->get_parent();
					} else {
						$output = $this->get_parent()->find_parent( $class_name, $qualified );
					}
				} else {
					if ( get_class( $this->get_parent() ) == $class_name ) {
						$output = $this->get_parent();
					} else {
						$output = $this->get_parent()->find_parent( $class_name, $qualified );
					}
				}
			}

			return $output;
		}

		public function find_parent_by_name( string $name ) {
			$output = false;

			if ( $this->get_parent() != $this->get_root() ) {
				if ( $this->get_parent()->get_name() == $name ) {
					$output = $this->get_parent();
				} else {
					$output = $this->get_parent()->find_parent_by_name( $name );
				}
			}

			return $output;
		}

		public function is_theme_instance(): bool{
			return get_class( $this->get_root() ) == 'sv100\init' ? true : false;
		}

		protected function setup( string $name, $file ): bool {
			$output = false;
			// make sure to init only once
			//$namespace = strstr(get_class($this->get_root()), '\\', true);
			//if(isset($this->get_instances()[$namespace])){
			if( isset($this->get_instances()[$name]) === false) {
				$this->set_section_types();

				global $wpdb;

				self::$wpdb	 = $wpdb;
				$this->name	 = $name;

				if ( $this->is_theme_instance() === true ) {
					$this->path = trailingslashit(get_template_directory());
					$this->url  = trailingslashit(get_template_directory_uri());
				} else {
					$this->path = plugin_dir_path($file);
					$this->url  = trailingslashit(plugins_url('', $this->get_path() . $this->get_name()));
					$this->plugins_loaded();
				}

				if (!isset(self::$instances[$name])) {
					$output = $this->setup_core($this->path, $name);
				}else{
					$output = true;
				}

				self::$instances[$name] = $this;
			}

			return $output;
		}

		public function activate_plugin(): void{}

		protected function set_section_types(): sv_abstract {
			$this->section_types = array(
				'settings'	=> __( 'Configuration &amp; Settings', 'sv_core' ),
				'tools'		=> __( 'Helpful tools &amp; helper', 'sv_core' )
			);

			return $this;
		}

		public static function get_instances(): array {
			return self::$instances;
		}

		public static function get_instance(string $name) {
			$output = false;

			if( isset(self::$instances[$name]) === true ){
				$output = self::$instances[$name];
			}

			return $output;
		}

		public static function get_instances_active(): array {
			return self::$instances_active;
		}

		public static function is_instance_active( string $name ): bool{
			return isset( self::$instances_active[ $name ] );
		}

		/*
		 * @todo function name is not explicit
		 */
		public function plugins_loaded() {
			if( $this->is_theme_instance() === false ) {
				load_plugin_textdomain( $this->get_root()->get_prefix(), false, basename( $this->get_path() ) . '/languages' );
			}
		}

		public function init() {
			if(did_action('plugins_loaded')){
				$this->load_translation();
			}else{
				add_action('plugins_loaded', array($this, 'load_translation'));
			}

			if(did_action($this->get_root()->get_name() . '_activate_plugin')){
				$this->activate_plugin();
			}else{
				add_action($this->get_root()->get_name() . '_activate_plugin', array($this, 'activate_plugin'));
			}
		}

		public function set_name(string $name) {
			$this->name		= $name;

			return $this;
		}

		public function get_name(): string {
			$output = 'sv';

			if ( $this->name ) { // if name is set, use it
				$output = $this->name;
			} else if ( $this != $this->get_parent() ) { // if there's a parent, go a step higher
				$output =  $this->get_parent()->get_name() . '_' . $this->get_module_name();
			}

			return $output;
		}

		public function get_module_name() {
			if(!$this->module_name){
				$this->module_name = ( new \ReflectionClass( get_called_class() ) )->getShortName();
			}
			return $this->module_name;
		}

		public function get_prefix( string $append = '' ) {
			if( strlen( $append ) > 0 ) {
				$append = '_' . $append;
			}

			return $this->get_name() . $append;
		}
		public function get_prefix_gutenberg( $append = '' ) {
			if( strlen( $append ) > 0 ) {
				$append = '/' . $append;
			}

			return str_replace('_', '-', $this->get_name() . $append);
		}

		public function get_relative_prefix( string $append = '' ): string {
			if( strlen( $append ) > 0 ) {
				$append = '_' . $append;
			}

			$prefix = str_replace( $this->get_root()->get_name(), 'sv_common', $this->get_name() );

			return  $prefix . $append;
		}

		public function load_settings() {
			return $this;
		}

		public function get_settings(): array {
			if(count($this->s) === 0){
				$this->load_settings();
			}

			return $this->s;
		}

		public function get_settings_clustered(): array {
			if(count($this->s_clustered) === 0){
				return array(__('Common', 'sv_core') => $this->get_settings());
			}

			return $this->s_clustered;
		}

		public function get_setting(string $setting = '', string $cluster = ''): settings {
			if( strlen($setting) === 0 || isset($this->s[$setting]) === false ) {
				// create empty setting if not set
				$this->s[$setting] = static::$settings->create($this)->set_ID($setting);

				if (strlen($cluster) > 0){
					$this->s_clustered[$cluster][$setting]	=  $this->s[$setting]->set_cluster($cluster);
				}
			}

			return $this->s[$setting];
		}

		public function get_breakpoints(): array {
			//if(!self::$breakpoints){ // disabled this check, as filter may be triggered later
				self::$breakpoints	= apply_filters($this->get_root()->get_prefix('breakpoints'), array( // number = min width
					'mobile'						=> 0,
					'mobile_landscape'				=> 0,
					'tablet'						=> 768,
					'tablet_landscape'				=> 992,
					'tablet_pro'					=> 1024,
					'tablet_pro_landscape'			=> 1366,
					'desktop'						=> 1600,
				));
			//}

			foreach(self::$breakpoints as &$val){
				if(empty($val) === true){
					$val = 0;
				}
			}

			return self::$breakpoints;
		}

		public function get_metabox(): metabox {
			if( isset($this->m[$this->get_prefix()]) === false ){
				// create empty setting if not set
				$this->m[$this->get_prefix()] = static::$metabox->create( $this );
			}

			return $this->m[$this->get_prefix()];
		}

		public function get_script(string $script = ''): scripts {
			if( strlen($script) === 0 || isset($this->scripts_queue[$script]) === false ){
				// create empty setting if not set
				$this->scripts_queue[$script] = static::$scripts->create( $this )->set_ID($script);
			}

			return $this->scripts_queue[$script];
		}
		public function get_scripts(): array {
			return $this->scripts_queue;
		}

		public function set_path(string $path) {
			$this->path	= $path;

			return $this;
		}

		public function get_path( string $suffix = ''): string {
			if ( $this->path ) {
				$path	= $this->path;
			} else if ( $this != $this->get_parent() ) { // if there's a parent, go a step higher
				$path	= $this->get_parent()->get_path();
			} else { // fallback
				$path	= trailingslashit( dirname( __FILE__ ) );
			}

			$this->set_path($path);

			return $this->path . $suffix;
			//return apply_filters('get_path', $this->path . $suffix, $path, $suffix, $this->get_prefix(), $this);
		}

		public function set_url(string $url) {
			$this->url	= $url;

			return $this;
		}

		public function get_url( string $suffix = ''): string {
			$url = '';
			if ( $this->url ) { // if url is set, use it
				$url	= $this->url;
			} else if ( $this != $this->get_parent() ) { // if there's a parent, go a step higher
				$url	= $this->get_parent()->get_url();
			}

			$this->set_url($url);

			return $this->url . $suffix;
			//return apply_filters('get_url', $this->url . $suffix, $this->url, $suffix, $this->get_prefix(), $this);
		}

		public function get_path_core(string $suffix = ''): string{
			return self::$path_core . $suffix;
		}

		public function get_url_core(string $suffix = ''): string{
			return self::$url_core . $suffix;
		}

		public function get_current_url(): string {
			$protocol = 'http';

			// check if set and not 'off' for support ISAPI under IIS
			if( isset($_SERVER['HTTPS']) === true && $_SERVER['HTTPS'] !== 'off' ){
				$protocol = 'https';
			}

			return $protocol . '://' . $_SERVER[ 'HTTP_HOST' ] . $_SERVER[ 'REQUEST_URI' ];
		}

		public function get_current_path(): string {
			return $_SERVER[ 'REQUEST_URI' ];
		}

		public function is_valid_url(string $url): bool{
			$output = true;
			if( filter_var($url, FILTER_VALIDATE_URL) === false ){
				$output = false;
			}
			return $output;
		}

		public function acp_style( bool $hook = false ) {
			if ( !$hook || $hook == 'sv-100_page_' . $this->get_module_name() ) {
				if(file_exists($this->get_active_core()->get_path_core('../assets'))) { // dir exists only when core_plugin is loaded, so if only theme is loaded, don't load these assets
					// Common
					wp_enqueue_style($this->get_prefix('common'), $this->get_active_core()->get_url_core('../assets/common.css'), array('wp-editor'), filemtime($this->get_active_core()->get_path_core('../assets/common.css')));

					// Dashboard
					wp_enqueue_style($this->get_prefix('dashboard'), $this->get_active_core()->get_url_core('../assets/dashboard.css'), array($this->get_prefix('common')), filemtime($this->get_active_core()->get_path_core('../assets/dashboard.css')));

					// Form
					wp_enqueue_style($this->get_prefix('settings'), $this->get_active_core()->get_url_core('../assets/settings.css'), array($this->get_prefix('dashboard')), filemtime($this->get_active_core()->get_path_core('../assets/settings.css')));

					// Check if page is settings page
					$this->setting_scripts();
				}
			}
		}

		protected function setting_scripts() {
			$settings = glob( $this->get_active_core()->get_path_core('settings/modules/*'), GLOB_ONLYDIR );

			foreach( $settings as $setting ) {
				$path = str_replace('\\', '/', $this->get_active_core()->get_path());
				$css = $setting . '/lib/css/';
				$js = $setting . '/lib/js/';

				// Styles
				if(!function_exists('list_files')){
					require(ABSPATH.'/wp-admin/includes/file.php');
				}

				if ( file_exists($css) && $files = list_files( $css ) ) {
					foreach( $files as $file ) {
						$relative_path = str_replace( $path, '', $file );
						$url = $this->get_active_core()->get_url( $relative_path );
						$setting_name = wp_basename( $setting );
						$filename = wp_basename( $file, '.css' );
						$handle = $setting_name . '_' . $filename;

						if ( filesize($file) && filesize($file) > 0 ) {
							wp_enqueue_style( $handle, $url, array($this->get_prefix('settings')), filemtime( $file ) );
						}
					}
				}

				// Scripts
				if ( file_exists($js) && $files = list_files( $js ) ) {
					foreach( $files as $file ) {
						$relative_path = str_replace( $path, '', $file );
						$url = $this->get_active_core()->get_url( $relative_path );
						$setting_name = wp_basename( $setting );
						$filename = wp_basename( $file, '.js' );
						$handle = $setting_name . '_' . $filename;

						if ( filesize($file) && filesize($file) > 0 ) {
							wp_enqueue_script( $handle, $url, array(), filemtime( $file ) );
						}
					}
				}
			}
		}

		public function add_section( $object ) {
			$output = null;
			// @todo: remove the following line once sv_bb_dashboard is upgraded
			if ( is_object( $object ) && !empty( $object->get_section_type() ) ) {
				$this->sections[ $object->get_prefix() ] = array(
					'object'	=> $object,
					'type'		=> $this->section_types[ $object->get_section_type() ],
				);

				$output = $object;
			} else {
				$output = $this; // @todo Notification forS SV Notices that the section_type is missing.
			}

			return $output;
		}

		public function get_sections(): array {
			return $this->sections;
		}

		public function get_section_single(string $section_title = '') {
			$output  	= false; // should be null
			$sections 	= $this->get_sections_sorted_by_prefix();

			foreach($sections as $key => $section){
				if($key == $section_title){
					$output = $section;
					break;
				}
			}

			return $output;
		}

		public function get_sections_sorted_by_prefix(): array {
			$sections = array();

			if ( empty( $this->sections ) === false ) {
				foreach($this->sections as $section){
					$sections[$section['object']->get_prefix()] = $section;
				}

				ksort($sections);
			}

			return $sections;
		}

		public function get_sections_sorted_by_title(): array {
			$sections = array();

			if ( empty( $this->sections ) === false ) {
				foreach($this->sections as $section){
					$sections[$section['object']->get_section_title()] = $section;
				}

				ksort($sections);
			}
			return $sections;
		}

		public function get_sections_sorted_by_order(): array {
			$sections	= array();

			if ( empty( $this->sections ) === false ) {
				foreach($this->sections as $section){
					$order_num = $section['object']->get_section_order();

					if($order_num === 0){
						$order_num = 777; // move them to the bottom
					}

					while( isset($sections[$order_num]) ){
						$order_num++;
					}

					$sections[$order_num] = $section;
				}

			}

			ksort($sections);

			return $sections;
		}

		public function get_section_template_path(): string {
			return $this->section_template_path;
		}

		public function set_section_template_path( string $path = 'lib/tpl/settings/init.php' ) {
			$this->section_template_path = is_file($path) ? $path : $this->get_path($path);

			return $this;
		}

		public function has_section_template_path(): bool{
			$output = false;

			if( strlen($this->get_section_template_path()) > 0 && is_file($this->get_section_template_path()) ){
				$output = true;
			}

			return $output;
		}

		public function set_section_title( string $title ) {
			$this->section_title = $title;

			return $this;
		}

		public function get_section_title(): string {
			return $this->section_title ? $this->section_title : __( 'No Title defined.', 'sv_core' );
		}

		public function set_section_order( int $num ) {
			$this->section_order = $num;

			return $this;
		}

		public function get_section_order(): int {
			return $this->section_order ? $this->section_order : 0;
		}

		public function set_section_desc( string $desc ) {
			$this->section_desc = $desc;

			return $this;
		}

		public function get_section_desc(): string {
			return $this->section_desc ? $this->section_desc : __( 'No description defined.', 'sv_core' );
		}

		public function set_section_privacy( string $section_privacy ) {
			$this->section_privacy = $section_privacy;

			return $this;
		}

		public function get_section_privacy(): string {
			return $this->section_privacy ? $this->section_privacy : __( 'No privacy statement defined.', 'sv_core' );
		}

		public function set_section_type( string $type ) {
			$this->section_type = $type;

			return $this;
		}

		public function get_section_type(): string {
			return $this->section_type;
		}

		public function load_page( string $custom_about_path = '' ) {
			$path = $this->get_path_core( 'backend/tpl/about.php' );

			$this->get_root()->acp_style();

			require_once( $this->get_path_core( 'backend/tpl/header.php' ) );

			if( strlen( $custom_about_path ) > 0 ){
				$path = $custom_about_path;
			}

			require_once( $path );

			// $this->load_section_html();

			require_once( $this->get_path_core( 'backend/tpl/legal.php' ) );
			require_once( $this->get_path_core( 'backend/tpl/footer.php' ) );
		}

		public function load_section_menu() {
			foreach ( $this->get_sections_sorted_by_order() as $section ) {
				$section_name = $section['object']->get_prefix();
				$section_icon = $this->get_section_icon( $section['object'] );

				$output = '<div data-sv_admin_menu_target="#section_';
				$output .= $section_name . '" class="sv_admin_menu_item section_';
				$output .= $section[ 'object' ]->get_section_type() . '">';
				$output .= '<i class="section_icon">' . $section_icon . '</i>';
				$output .= '<div class="section_title">';
				$output .= '<h4>' . $section[ 'object' ]->get_section_title() . '</h4>';
				$output .= '<span>' . $section[ 'object' ]->get_section_desc() . '</span>';
				$output .= '</div>';
				$output .= '</div>';

				echo $output;

			}
		}

		public function get_section_icon( $module ) {
			if ( isset( $module->section_icon ) && ! empty( $module->section_icon ) ) {
				return $module->section_icon;
			} else {
				return $this->section_icon;
			}
		}
		public function set_section_icon(string $icon) {
			$this->section_icon = $icon;

			return $this;
		}

		public function load_section_html() {
			foreach( $this->get_sections_sorted_by_title() as $section ) {
				$section_name = $section['object']->get_prefix(); // var will be used in included file
				$path = $this->get_path_core( 'backend/tpl/section_' . $section[ 'object' ]->get_section_type() . '.php' );

				require( $path );
			}
		}

		public function plugin_action_links( array $actions ): array {
			$links						= array(
				'settings'				=> '<a href="admin.php?page=' . $this->get_root()->get_prefix() . '">'
											.__('Settings', 'sv_core')
											.'</a>',
				'straightvisions'		=> '<a href="https://straightvisions.com" target="_blank" rel="noopener">straightvisions GmbH</a>',
			);

			$actions					= array_merge( $links, $actions );

			return $actions;
		}
		public function has_block_sidebar( string $block_name ): bool{
			$widget_blocks = get_option( 'widget_block' );
			foreach( (array) $widget_blocks as $widget_block ) {
				if ( ! empty( $widget_block['content'] ) && $this->has_block( $block_name, do_shortcode($widget_block['content'] ))) {
					return true;
				}
			}

			return false;
		}
		public function has_block_term_desc(string $block_name): bool{
			$object = apply_filters('sv_core_has_block_frontend_queried_object', get_queried_object());

			if(!$object){
				return false;
			}

			if( get_class($object) !== 'WP_Term'){
				return false;
			}

			$content	= (string) term_description($object);
			if($this->has_block( $block_name, $content)){
				return true;
			}

			return false;
		}
		public function has_block_frontend(string $block_name): bool{
			// always deliver all assets in Gutenberg, as we don't know when a block is added
			if( is_admin() ) {
				return true;
			}

			// get object
			$object = apply_filters('sv_core_has_block_frontend_queried_object', get_queried_object());

			// check if it's a post
			if($object && get_class($object) == 'WP_Post'){
				// post contains block?
				if ($this->has_block( $block_name, get_post_field( 'post_content', $object->ID ) )) {
					return true;
				}
			}

			// check if it's a term
			if($this->has_block_term_desc($block_name)){
				return true;
			}

			// check if any sidebar contains block
			if ($this->has_block_sidebar($block_name)) {
				return true;
			}

			if(is_404()){ // load all styles on 404 page as it won't detect sidebar styles correctly
				return true;
			}

			// nothing found
			return false;
		}
		public function has_block( string $block_name, string $content = '' ): bool{
			if(has_block($block_name, $content)){
				return true;
			}
			if($this->has_reusable_block($block_name, $content)){
				return true;
			}
			return false;
		}
		public function has_reusable_block( string $block_name, string $content = '' ): bool{
			if( strlen($content) === 0 ) {
				return false;
			}

			if (has_block( 'block', $content ) ) {
				return true;
			}

			// Check reusable blocks
			$blocks = parse_blocks( $content );

			if ( ! is_array( $blocks ) || empty( $blocks ) ) {
				return false;
			}

			foreach ( $blocks as $block ) {
				if($this->check_inner_blocks($block_name, $block)){
					return true;
				}
			}

			return false;
		}
		protected function check_inner_blocks(string $block_name, array $block): bool{

			if ( $block['blockName'] === 'core/block' && ! empty( $block['attrs']['ref'] ) ) {
				if( has_block( $block_name, $block['attrs']['ref'] ) ){
					return true;
				}
			}

			if ( $block['blockName'] === 'core/block' && ! empty( $block['attrs']['ref'] ) ) {
				if( has_block( $block_name, $block['attrs']['ref'] ) ){
					return true;
				}
			}

			if(isset($block['innerBlocks'])){
				$inner_blocks = $block['innerBlocks'];
				unset($block['innerBlocks']);
				foreach($inner_blocks as $inner_block) {
					if($this->check_inner_blocks($block_name, $inner_block)){
						return true;
					}
				}

			}

			return false;
		}

		public function get_css_cache_active(){
			return $this->module_css_cache;
		}
		public function set_css_cache_active(bool $active = true){
			$this->module_css_cache = $active;

			return $this;
		}
		public function get_path_cached(string $file): string{
			return static::$scripts->create( $this )->get_path_cached($file);
		}
		public function get_url_cached(string $file): string{
			return static::$scripts->create( $this )->get_url_cached($file);
		}
		public function get_templates_archive(): array{
			return apply_filters('sv_core_templates_archive', array());
		}


		public function get_scripts_settings(): array {
			$settings = array();

			foreach ( static::$scripts->get_scripts() as $script ) {
				$name				= static::$scripts->get_prefix( 'settings_' . $script->get_UID() );

				if ( isset( static::$scripts->s[ $script->get_UID() ] ) ) {
					$settings[ $name ] 	= static::$scripts->s[ $script->get_UID() ]->get_data();
				}
			}

			return $settings;
		}


		public function get_modules_settings(): array {
			$settings = array();

			foreach ( $this->get_modules_loaded() as $prefix => $module ) {
				$module_settings = array();

				foreach ( $module->s as $setting_name => $setting ) {
					if ( $setting ) {
						$module_settings[ $setting->get_field_id() ] = $setting->get_data();
					}
				}

				$settings[ $prefix ] = $module_settings;
			}

			return $settings;
		}
		protected function set_modules_loaded(string $name, $object){
			$this->get_root()->modules_loaded[$name] = $object;
		}
		protected function get_modules_loaded(): array {
			return $this->get_root()->modules_loaded;
		}
		protected function is_module_loaded(string $name): bool{
			return isset($this->get_modules_loaded()[$this->get_root()->get_prefix($name)]) ? true : false;
		}
		public function get_module( string $name, bool $required = false ) {

			return false;
		}
		public function load_module( string $name, string $path, string $url, bool $required = false ): bool {
			return false;
		}
		public function array_merge_recursive_distinct ( array $array1, array $array2 ): array{
			$merged = $array1;

			foreach ( $array2 as $key => &$value )
			{
				if ( is_array ( $value ) && isset ( $merged [$key] ) && is_array ( $merged [$key] ) )
				{
					$merged [$key] = $this->array_merge_recursive_distinct ( $merged [$key], $value );
				}
				else
				{
					$merged [$key] = $value;
				}
			}

			return $merged;
		}
		public function get_category_name($post = false): string{
			$post		= get_post($post);

			if($this->get_category($post)){
				return esc_html( $this->get_category($post)->name );
			}

			return '';
		}
		public function get_category_link($post = false, $string = false): string{
			$post		= get_post($post);

			if($string){
				$string	= str_replace('%name', esc_html( $this->get_category($post)->name ), $string);
			}else{
				$string	= esc_html( $this->get_category($post)->name );
			}

			if($this->get_category()){
				return '<a href="' . esc_url( get_category_link( $this->get_category($post)->term_id ) ) . '">' . $string . '</a>';
			}

			return '';
		}
		public function get_category($post = false){
			$post		= get_post($post);

			// get first category if yoast is not available
			if(!function_exists('yoast_get_primary_term_id')){
				return $this->get_first_category($post);
			}

			return $this->get_primary_category($post);
		}
		public function get_first_category($post){
			$categories = get_the_category($post);

			if ( empty( $categories ) ) {
				return '';
			}

			return $categories[0];
		}
		public function get_primary_category($post = false, string $taxonomy_slug = 'category'){
			$post		= get_post($post);

			if(function_exists('yoast_get_primary_term_id')) {
				$primary_term_id = yoast_get_primary_term_id( $taxonomy_slug, $post );
			}

			if ( !isset($primary_term_id) ) {
				return $this->get_first_category($post);
			}

			$primary_term = get_term( $primary_term_id );
			if ( !$primary_term ) {
				return $this->get_first_category($post);
			}

			return $primary_term;
		}
		public function get_reading_time($post=false): string{
			if(!function_exists('YoastSEO')){
				return '';
			}

			if($post){
				$post		= get_post($post);
				$meta		= \YoastSEO()->meta->for_post( $post->ID );
			}else{
				$meta		= \YoastSEO()->meta->for_current_page();
			}

			$minutes		= $meta->estimated_reading_time_minutes;

			return sprintf( _n('%s Minute', '%s Minutes', $minutes, 'sv_core'), $minutes);
		}
		public function set_block_handle( string $string = '' ) {
			$this->block_handle				= $string;

			return $this;
		}
		public function get_block_handle(): string {
			return $this->block_handle;
		}
		public function set_block_name( string $string = '' ) {
			$this->block_name				= $string;

			return $this;
		}
		public function get_block_name(): string {
			return $this->block_name;
		}
	}
