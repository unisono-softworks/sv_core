<?php
namespace sv_core;

class scripts extends sv_abstract {
	private static $scripts						= array();
	private static $scripts_by_handle			= array();
	private static $scripts_enqueued			= array();
	private static $scripts_active				= array();
	
	// properties
	protected $section_icon 					= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M21.312 12.644c-.972-1.189-3.646-4.212-4.597-5.284l-1.784 1.018 4.657 5.35c.623.692.459 1.704-.376 2.239-.773.497-5.341 3.376-6.386 4.035-.074-.721-.358-1.391-.826-1.948-.469-.557-6.115-7.376-7.523-9.178-.469-.601-.575-1.246-.295-1.817.268-.549.842-.918 1.43-.918.919 0 1.408.655 1.549 1.215.16.641-.035 1.231-.623 1.685l1.329 1.624 7.796-4.446c1.422-1.051 1.822-2.991.93-4.513-.618-1.053-1.759-1.706-2.978-1.706-1.188 0-.793-.016-9.565 4.475-1.234.591-2.05 1.787-2.05 3.202 0 .87.308 1.756.889 2.487 1.427 1.794 7.561 9.185 7.616 9.257.371.493.427 1.119.15 1.673-.277.555-.812.886-1.429.886-.919 0-1.408-.655-1.549-1.216-.156-.629.012-1.208.604-1.654l-1.277-1.545c-.822.665-1.277 1.496-1.377 2.442-.232 2.205 1.525 3.993 3.613 3.993.596 0 1.311-.177 1.841-.51l9.427-5.946c.957-.664 1.492-1.781 1.492-2.897 0-.744-.24-1.454-.688-2.003zm-8.292-10.492c.188-.087.398-.134.609-.134.532 0 .997.281 1.243.752.312.596.226 1.469-.548 1.912l-5.097 2.888c-.051-1.089-.579-2.081-1.455-2.732l5.248-2.686zm-2.374 12.265l.991-2.691.813 1.017-.445 1.433 1.782.238.812 1.015-3.399-.321-.554-.691zm5.481-3.076l.552.691-.99 2.691-.812-1.015.44-1.438-1.778-.232-.812-1.016 3.4.319z"/></svg>';
	private $is_enqueued						= false;
	private $ID									= false;
	private $type								= 'css';
	private $script_url							= '';
	private $script_path						= '';
	private $deps								= array();
	private $no_prefix							= false;
	private static $is_loaded					= array(
		'css'									=> array(),
		'js'									=> array()
	);
	private $is_backend						    = false;
	private $is_gutenberg						= false;
	private $is_external						= false;
	private $is_required						= false;
	private $is_consent_required				= false;
	private $custom_attributes					= '';
	private $block_style                        = false;
	
	// CSS specific
	private $media								= 'all';
	private $inline								= false;
	
	// JS specific
	private $localized							= array();

	private static $list						= array();
	private $load_in_header						= false;

	//protected $option_updated					= false;

	protected $module_css_cache_invalidated		= NULL;

	public function __construct() {

	}

	public function init(){
		// Section Info
		$this->set_section_title( __('Scripts', 'sv_core') )
			->set_section_desc( __( 'Override Scripts Loading.', 'sv_core' ) )
			->set_section_type( 'settings' )
			->load_settings()
			->set_section_template_path($this->get_path_core('lib/tpl/settings/scripts.php'));

		add_action( 'enqueue_block_editor_assets', array($this, 'gutenberg_scripts'));

		// Loads Settings
		if(is_admin()) {
			add_action( 'admin_init', array( $this, 'start' ), 1);
			add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ), 10 );
			add_action( 'admin_enqueue_scripts', array($this, 'admin_scripts'), 99999);
		}else{
			add_action( 'wp_enqueue_script', array( $this, 'register_scripts' ), 10 );
			add_action( 'wp_enqueue_scripts', array($this, 'wp_head_start'), 1);
			add_action( 'wp_enqueue_scripts', array($this, 'wp_head'), 10);

			add_action( 'template_redirect', array( $this, 'start' ), 1 );
			add_action( 'template_redirect', array( $this, 'wp_footer' ), 10 );
			add_action( 'wp_footer', array( $this, 'wp_footer' ) ); // enqueue late registered scripts
			add_action( 'wp_footer', array( $this, 'enqueue_inline_style' ), 10 ); // enqueue late registered scripts

			add_filter('script_loader_tag', function($tag, $handle){
				if(isset($this->get_scripts_by_handle()[$handle])){
					$script			= $this->get_scripts_by_handle()[$handle];

					$tag = str_replace(
						array(
							"type='text/javascript'",
							'type="text/javascript"'
						),
						'',
						$tag);

					if(strlen($script->get_custom_attributes()) > 0 && strpos($tag, $script->get_custom_attributes()) === false) {
						$tag = str_replace(
							'<script ',
							'<script ' . $script->get_custom_attributes().' ',
							$tag);
					}

					if($script->get_consent_required() && strpos($tag, 'type="text/plain"') === false) {
						$tag = str_replace(
							'<script ',
							'<script type="text/plain"',
							$tag);
					}
				}

				return $tag;
			}, 10, 2);
		}

		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu' ), 999 );
		add_action( 'admin_post_' . $this->get_prefix( 'clear_cache' ), array( $this, 'clear_cache_link' ) );
	}

	public function add_admin_bar_menu( $admin_bar ) {
		if ( ! $this->is_theme_instance() ){
			return;
		}
		if ( ! current_user_can( apply_filters('sv_admin_menu_capability', 'manage_options') ) ){
			return;
		}
	
		$admin_bar->add_menu(
			array(
				'id' 	=> $this->get_root()->get_prefix(),
				'title' => __( 'SV100', 'sv_core' ),
				'href'	=> admin_url('/admin.php?page=sv100')
			)
		);
	
		$admin_bar->add_menu(
			array(
				'parent' 	=> $this->get_root()->get_prefix(),
				'id'		=> $this->get_root()->get_prefix( 'settings' ),
				'title'		=> __( 'Settings', 'sv_core' ),
				'href'		=> admin_url('/admin.php?page=sv100')
			)
		);
	
		$clear_cache_nonce 	= wp_create_nonce( 'admin_post_' . $this->get_prefix( 'clear_cache' ) );
		$clear_cache_url	= admin_url('/admin-post.php?action=' . $this->get_prefix( 'clear_cache' ) . '&_wpnonce=' . $clear_cache_nonce);
	
		$admin_bar->add_menu(
			array(
				'parent' 	=> $this->get_root()->get_prefix(),
				'id'		=> $this->get_root()->get_prefix( 'clear_cache' ),
				'title'		=> __( 'Clear Cache', 'sv_core' ),
				'href'		=> $clear_cache_url
			)
		);
	}

	public function clear_cache_link() {
		if (
			isset( $_REQUEST['_wpnonce'] )
			&& ! empty( $_REQUEST['_wpnonce'] )
			&& current_user_can( apply_filters('sv_admin_menu_capability', 'manage_options') )
			&& isset( $this->s[ 'flush_css_cache' ] )
			&& wp_verify_nonce( $_REQUEST['_wpnonce'], 'admin_post_' . $this->get_prefix( 'clear_cache' ) )
		) {
			$this->clear_cache();
		}

		wp_redirect( $_SERVER['HTTP_REFERER'] );
	}

	public function clear_cache() {
		foreach($this->get_instances() as $instance){
			update_option($instance->get_prefix('scripts_settings_flush_css_cache'), '1');
		}
	}

	public function update_setting_flush_css_cache($option_name, $old_value, $value){
		if(
			isset($this->s[ 'flush_css_cache' ])
			&& strpos($option_name, '_scripts_settings') === false // do not trigger when script settings are saved
			&& strpos($option_name, 'sv') === 0 // but trigger when SV settings are saved
			//&& $old_value != $value // only when something has changed
			//&& isset($this->get_parent()->option_updated)
			//&& $this->get_parent()->option_updated === false
		){
			//$this->module_css_cache_invalidated = true;
			//$this->get_parent()->option_updated = true;
			remove_action('updated_option', array($this,'update_setting_flush_css_cache'), 10);
			update_option($this->s[ 'flush_css_cache' ]->get_field_id(), 1);
		}
	}
	
	public function load_settings() {
			if ($this->get_is_expert_mode()) {
				$this->get_root()->add_section($this);
			}

			$this->s['flush_css_cache'] = $this->get_parent()::$settings->create($this)
				->set_ID('flush_css_cache')
				->set_title(__('Flush cache for all CSS files', 'sv_core'))
				->set_description(__('All cached CSS Files will be regenerated', 'sv_core'))
				->load_type('checkbox');

			$this->s['disable_css_cache'] = $this->get_parent()::$settings->create($this)
                ->set_ID('disable_css_cache')
                ->set_title(__('Disable CSS caching', 'sv_core'))
                ->set_description(__('Useful if you are working on css in a child theme. This will increase load times!', 'sv_core'))
                ->load_type('checkbox');

			$this->s['disable_all_css'] = $this->get_parent()::$settings->create($this)
				->set_ID('disable_all_css')
				->set_title(__('Disable all CSS per Default', 'sv_core'))
				->set_description(__('CSS enqueued will be disabled by default - you may override this later down below.', 'sv_core'))
				->load_type('checkbox');

			$this->s['disable_all_js'] = $this->get_parent()::$settings->create($this)
				->set_ID('disable_all_js')
				->set_title(__('Disable all JS per Default', 'sv_core'))
				->set_description(__('JS enqueued will be disabled by default - you may override this later down below.', 'sv_core'))
				->load_type('checkbox');

			add_action('updated_option', array($this,'update_setting_flush_css_cache'), 10, 3);

		return $this;
	}
	
	public function start() {
		if(count($this->get_scripts()) > 0) {
			foreach ( $this->get_scripts() as $script ) {
				// Setting attached
				static::$list[$script->get_UID()][ 'attached' ] = $this->get_parent()::$settings->create( $this )
				   ->set_ID( $script->get_UID().'_attached' )
				   ->set_default_value( 'default' )
				   ->set_title( '<div class="sv_core_scripts sv_core_scripts_'.$script->get_type().'"></div><div style="display:inline-block;"><a href="'.$script->get_url().'" target="_blank">' . $script->get_handle().'</a></div>' )
				   ->load_type( 'select' )
				   ->set_disabled( $script->get_is_required() ? true : false );

				if(
					($this->s[ 'disable_all_css' ]->get_data() == 1 && $script->get_type() == 'css') ||
					($this->s[ 'disable_all_js' ]->get_data() == 1 && $script->get_type() == 'js')
				){
					$default_label											=  __( 'Disabled', 'sv_core' );
				}else{
					$default_label											= $script->get_inline() ? __( 'Inline', 'sv_core' ) : __( 'Attached', 'sv_core' );
				}

				$options = array(
					'default'  => __( 'Default', 'sv_core' ) . ': ' . $default_label,
					'inline'   => __( 'Inline', 'sv_core' ),
					'attached' => __( 'Attached', 'sv_core' ),
					'disable'  => __( 'Disabled', 'sv_core' )
				);

				static::$list[$script->get_UID()][ 'attached' ]->set_options( $options );

				// Setting cache invalidated
				if(strpos($script->get_ID(),'config') !== false){
					static::$list[$script->get_UID()]['cache']['active'] = true;
				}else{
					static::$list[$script->get_UID()]['cache']['active'] = false;
				}

				static::$list[$script->get_UID()]['cache'][ 'invalidated' ]['gutenberg'] = $this->get_parent()::$settings->create( $this )
					->set_ID( $script->get_UID().'_cache_invalidated_gutenberg' )
					->set_default_value( 1 )
					->set_title( 'Cache Gutenberg' )
					->set_options( array(
						'1'		=> __( 'Flush Cache', 'sv_core' ),
						'0'		=> __( 'Cache Active', 'sv_core' )
					))
					->load_type( 'select' );

				static::$list[$script->get_UID()]['cache'][ 'invalidated' ]['frontend'] = $this->get_parent()::$settings->create( $this )
					->set_ID( $script->get_UID().'_cache_invalidated_frontend' )
					->set_default_value( 1 )
					->set_title( 'Cache Frontend' )
					->set_options( array(
						'1'		=> __( 'Flush Cache', 'sv_core' ),
						'0'		=> __( 'Cache Active', 'sv_core' )
					))
					->load_type( 'select' );

				// invalidate cache globally if requested
				if(intval($this->s[ 'flush_css_cache' ]->get_data()) === 1 || intval($this->s[ 'disable_css_cache' ]->get_data()) === 1){
					$script->set_css_cache_invalidated(true,true);
				}

				$script->cache_css();
			}
			$this->s[ 'flush_css_cache' ]->set_data('0')->save_option();
		}
	}
	
	public function get_list(){
		return static::$list;
	}
	
	public function get_scripts(): array {
		return isset( self::$scripts[ $this->get_root()->get_name() ] ) ? self::$scripts[ $this->get_root()->get_name() ] : array();
	}
	
	public function get_scripts_by_handle(): array {
		return isset( self::$scripts_by_handle ) ? self::$scripts_by_handle : array();
	}
	
	public function get_enqueued_scripts(): array{
		return self::$scripts_enqueued;
	}
	
	public function get_active_scripts(): array {
		return self::$scripts_active;
	}
	
	public function wp_head_start(){
		ob_start();
		// now remove the attached style
		add_action('wp_footer', function(){
			$this->replace_type_attributes();
		}, 99999999);
	}
	
	public function wp_head() {
		foreach ( $this->get_scripts() as $script ) {
			if(!$script->get_is_backend() && $script->get_load_in_header()) {
				$this->add_script($script);
			}
		}
	}
	
	public function enqueue_inline_style() {
		wp_enqueue_style('sv_core_init_style');
	}

	public function wp_footer() {
		// Register the style to allow inline styles with WP functions
		wp_register_style('sv_core_init_style', $this->get_url_core('frontend/css/style.css'));

		foreach ($this->get_scripts() as $script) {
			if (!$script->get_is_backend() && !$script->get_load_in_header()) {
				$this->add_script($script);
			}
		}

		// Check if Imagify is installed and its buffer method is active
		if (class_exists('Imagify') && function_exists('get_imagify_option') && get_imagify_option( 'display_nextgen' )) {
			// Use Imagify's buffer for content manipulation
			add_filter('imagify_buffer', function ($buffer) {
				// Remove the <link> tag associated with `sv_core_init_style-css`
				$buffer = preg_replace('/<link[^>]*id="sv_core_init_style-css"[^>]*>/', '', $buffer);

				// Additional modifications
				$buffer = $this->replace_type_attr($buffer);

				return $buffer;
			}, PHP_INT_MAX);
		} else {
			// Fallback: Use your original output buffering logic
			ob_start();

			add_action('wp_print_footer_scripts', function () {
				$this->replace_type_attributes();
			}, PHP_INT_MAX);
		}
	}

	private function replace_type_attributes() {
		// Get all buffered content
		$html = ob_get_clean();

		// Remove the <link> tag associated with the registered style
		$html = preg_replace('/<link[^>]*id="sv_core_init_style-css"[^>]*>/', '', $html);

		// Further modify attributes if needed
		$html = $this->replace_type_attr($html);

		// Output the modified HTML
		echo $html;
	}
	
	public function replace_type_attr($input){
		foreach ( $this->get_scripts() as $script ) {
			if($script->get_consent_required()) {
				$input = str_replace(
					array(
						"type='text/javascript' src='".$script->get_url(),
						'type="text/javascript" src=\''.$script->get_url()
					),
					'type="text/plain"'.$script->get_custom_attributes().' src=\''.$script->get_url(),
					$input);
			}else{
				$input = str_replace(
					array(
						"type='text/javascript' src='".$script->get_url(),
						'type="text/javascript" src=\''.$script->get_url()
					),
					'type="text/javascript"'.$script->get_custom_attributes().' src=\''.$script->get_url(),
					$input);
			}
		}
		return $input;
	}
	
	public function admin_scripts($hook){
		if (
			is_admin()
			&& (
				strpos( $hook,'straightvisions' ) !== false
				|| strpos( $hook,'appearance_page_sv100' ) !== false
				|| (
					get_current_screen() && get_current_screen()->base == "post"
					|| get_current_screen() && get_current_screen()->base == "widgets"
					|| (get_current_screen() && get_current_screen()->id === 'edit-category')
				)
			)
		) {
			foreach ( $this->get_scripts() as $script ) {
				if ( $script->get_is_backend() && !$script->get_is_gutenberg() ) {
					if($script->get_type() == 'css') {
						wp_enqueue_style(
							$script->get_handle(),						  // script handle
							$script->get_url(),							// script url
							$script->get_deps(),							// script dependencies
							( $this->is_external() ? md5( $script->get_url() ) : filemtime( $script->get_path() ) ),	 // script version, generated by last filechange time
							$script->get_media()							// The media for which this stylesheet has been defined. Accepts media types like 'all', 'print' and 'screen', or media queries like '(orientation: portrait)' and '(max-width: 640px)'.
						);
					}else{
						$this->add_script($script);
					}
				}
			}
		}
	}
	
	public function gutenberg_scripts(){
		foreach ( $this->get_scripts() as $script ) {
			if ( $script->get_is_gutenberg() ) {
				$this->add_script($script);
			}
		}
	}
	
	public function register_scripts(){
		foreach ( $this->get_scripts() as $script ) {
			$filetime = file_exists($script->get_path()) ? filemtime($script->get_path()) : time();
			
			if($script->get_type() == 'js'){
				wp_register_script(
					$script->get_handle(),
					$script->get_url(),
					$script->get_deps(),
					($this->is_external() ? md5($script->get_url()) : $filetime)
				);
			}else{
				wp_register_style(
					$script->get_handle(),
					$script->get_url(),
					$script->get_deps(),
					($this->is_external() ? md5($script->get_url()) : $filetime)
				);
			}
		}
	}

	private function check_for_enqueue(scripts $script): bool{
		if($script->get_is_loaded()){ // always load scripts once only
			return false;
		}

		if($script->get_is_required()){ // always load required scripts
			return true;
		}

		if(isset(static::$list[$script->get_UID()][ 'attached' ]) && static::$list[$script->get_UID()][ 'attached' ]->get_data() == 'disable'){ // don't load disabled scripts
			return false;
		}

		if( // if script has no user load settings
			isset(static::$list[$script->get_UID()][ 'attached' ]) &&
			(
				static::$list[$script->get_UID()][ 'attached' ]->get_data() == '' ||
				static::$list[$script->get_UID()][ 'attached' ]->get_data() == 'default'
			)
		){
			if( // make sure they are not globally disabled
				($this->s[ 'disable_all_css' ]->get_data() == 1 && $script->get_type() == 'css') ||
				($this->s[ 'disable_all_js' ]->get_data() == 1 && $script->get_type() == 'js')
			){
				return false;
			}
		}

		return true;
	}
	
	// MAIN SCRIPT LOADING PIPE ----------------------------------------------------------------------------------------
	// MAIN SCRIPT LOADING PIPE ----------------------------------------------------------------------------------------
	// MAIN SCRIPT LOADING PIPE ----------------------------------------------------------------------------------------
	// @todo check why we have to set_is_enqueued for admin env, looks like a conceptional issue to me - Dennis
	private function add_script( scripts $script ) {
		// run all registered scripts
		if(is_admin() && $script->get_is_gutenberg()){
			$script->set_is_enqueued();
		}

		// check if script is enqueued
		if($script->get_is_enqueued()) {
			// check is script isn't loaded already and not disabled
			if ($this->check_for_enqueue($script)) {
				// set as loaded
				$script->set_is_loaded();
				self::$scripts_by_handle[$script->get_handle()]	= $script;
				
				if ($script->get_type() === 'css') {
					$this->handle_add_script_css($script);
				} // else removed to prevent handling of non css && non js files as js -> use custom handler
				
				if ($script->get_type() === 'js') {
					$this->handle_add_script_js( $script );
				}
				
				// add more file type handler here
				// ...
			}
		}
		
		return $this;
	}
	
	/*
	 * @todo refactor this function: move independent parts to dedicated functions to reduce complexity, length and depth
	 * check for domain earlier (editor, frontend, backend etc.) and run dedicated handler functions instead of
	 * "breaking" the function with return statements in f() end
	 */
	private function handle_add_script_css($script) {
		//@todo could be a dedicated function
		$module = $script->get_parent();

		// get settings object for build css later
		if ( $script->get_ID() === 'config' || $script->get_ID() === $module->get_block_handle() ) {
			if ( $script->get_parent()->get_css_cache_active() ) {
				$script->cache_css();
				foreach($script->get_parent()->get_scripts() as $combined_script){
					if($combined_script->get_type() === 'js'){
						continue;
					}
					if($combined_script->get_ID() === $script->get_ID()){
						continue;
					}
					if($combined_script->get_inline() === true){
						continue;
					}
					$combined_script->set_is_enqueued(false);
				}
			} else {
				// legacy
				$_s = $script->get_parent()->get_settings();
				$_s = reset( $_s );
			}
		}
		// -----------------------------------------------------
		
		// check if inline per settings (higher prio) or per parameter (lower prio)
		if ( isset(static::$list[ $script->get_UID() ]) && static::$list[ $script->get_UID() ]['attached'] && // checks if null - Dennis
		     (
			     static::$list[ $script->get_UID() ]['attached']->get_data() === 'inline'
			     || (
				     static::$list[ $script->get_UID() ]['attached']->get_data() === 'default'
				     && $script->get_inline()
			     )
		     )
		     && ! $script->get_is_backend()
		     && !is_admin()
		) {
			if ( is_file( $script->get_path() ) ) {
				ob_start();
				require_once( $script->get_path() );
				$css = ob_get_clean();
				
				wp_add_inline_style( 'sv_core_init_style', $css );
				wp_add_inline_style( 'sv_core_gutenberg_style', $css );
			} else {
				error_log( __( 'Script "' . $script->get_handle() . '" in path "' . $script->get_path() . '" not found.' ) );
			}
		} else {
			//@todo f() should have only one return statement
			// make clear what happens here
			if ( $script->get_path() && filesize( $script->get_path() ) === 0 ) {
				return $this->set_script_active( $script );
			}

			// remove default styles from Gutenberg
			wp_dequeue_style( $script->get_handle() );
			wp_deregister_style( $script->get_handle() );
			
			// register style
			wp_register_style(
				$script->get_handle(),                          // script handle
				$script->get_url(),                            // script url
				$script->get_deps(),                            // script dependencies
				( $this->is_external() ? md5( $script->get_url() ) : filemtime( $script->get_path() ) ),     // script version, generated by last filechange time
				$script->get_media()                            // The media for which this stylesheet has been defined. Accepts media types like 'all', 'print' and 'screen', or media queries like '(orientation: portrait)' and '(max-width: 640px)'.
			);
			
			if ( is_admin() && $script->get_is_gutenberg() ) {
				if ( $module->get_css_cache_active() ) {
					if ( in_array( $script->get_ID(), ['common', 'default'] ) ) {
						return $this->set_script_active( $script );
					}
				}
				
				// enqueue block styles (default,common,config=gutenberg.css)
				if ( strlen( $module->get_block_handle() ) > 0 ) {
					wp_enqueue_style( $script->get_handle() );
				}
				
				// Add editor styles.
				// add_editor_styles doesn't allow absolute paths at the moment
				// add_editor_style( "styles/blocks/$block_name.min.css" );
				if ( is_file( $script->get_path() ) ) {
					/*ob_start();
					require_once( $script->get_path() );
					$css = ob_get_clean();*/
					
					// site editor
					// we use a standard block to enqueue global styles until add_editor_style works for site editor
					// @see https://github.com/WordPress/gutenberg/issues/41821
					//wp_add_inline_style( 'wp-block-paragraph', $css );
					
					// post editor
					wp_enqueue_style( $script->get_handle() );
				}
			} else {
				// frontend: enqueue extra styles if block is loaded
				add_action( 'wp_footer', function () use ( $module, $script ) {
					if (
						strlen( $module->get_block_handle() ) > 0
						&& wp_style_is( $module->get_block_handle(), 'enqueued' )
						&& ! wp_style_is( $script->get_handle(), 'enqueued' )
						&& $script->get_handle() != $module->get_block_handle()
						||
						( // load block styles when used in archive descriptions on demand
							is_archive()
							&& $this->has_block_term_desc($script->get_parent()->get_block_name())
						)
					) {
						wp_enqueue_style( $script->get_handle() );
					}
				} );
			}
			
			// non block styles
			if ( $script->get_is_enqueued() && strlen( $module->get_block_handle() ) === 0 ) {
				wp_enqueue_style( $script->get_handle() );
			}
		}
		
		return $this->set_script_active( $script );
	}
	
	private function handle_add_script_js($script){
		if(strlen($script->get_inline()) > 0){
			ob_start();
			require_once($script->get_path());
			$js = ob_get_clean();
			
			wp_add_inline_script($script->get_inline(), $js, 'before');
			
		}else{
			wp_enqueue_script(
				$script->get_handle(),							  // script handle
				$script->get_url(),							  // script url
				$script->get_deps(),								// script dependencies
				($this->is_external() ? md5($script->get_url()) : filemtime($script->get_path())),		 // script version, generated by last filechange time
				$script->get_load_in_header() ? false : true									   // print in footer
			);
			
		}
		
		if ($script->is_localized()) {
			wp_localize_script($script->get_handle(), $script->get_uid(), $script->get_localized());
		}
		
		$this->set_script_active( $script );
	}
	// MAIN SCRIPT LOADING PIPE ----------------------------------------------------------------------------------------
	// MAIN SCRIPT LOADING PIPE ----------------------------------------------------------------------------------------
	// MAIN SCRIPT LOADING PIPE ----------------------------------------------------------------------------------------
	
	public function set_script_active($script){
		self::$scripts_active[]								= $script;

		return $this;
	}
	
	public function is_script_active(): bool{
		$output = false;
		
		foreach(self::$scripts_active as $key => $_s){
			if($this->get_handle() === $_s->get_handle()){
				$output = true;
				break;
			}
		}
		
		return $output;
	}
	
	// OBJECT METHODS
	public static function create( $parent ) {
		$new									= new static();

		// @todo: deprecated in PHP 8
		@$new->prefix							= $parent->get_prefix() . '_';
		$new->set_root( $parent->get_root() );
		$new->set_parent( $parent );

		self::$scripts[ $parent->get_root()->get_name() ][]						= $new;

		return $new;
	}
	
	public function set_consent_required( bool $is_consent_required = true ): scripts {
		$this->is_consent_required						= $is_consent_required;

		return $this;
	}
	
	public function get_consent_required(): bool {
		return $this->is_consent_required;
	}
	
	public function set_custom_attributes( string $string = '' ): scripts {
		$this->custom_attributes						= $string;

		return $this;
	}
	
	public function get_custom_attributes(): string {
		return $this->custom_attributes;
	}
	
	public function set_is_required( bool $is_required = true ): scripts {
		$this->is_required						= $is_required;

		return $this;
	}
	
	public function get_is_required(): bool {
		return $this->is_required;
	}
	
	public function set_is_no_prefix( bool $no_prefix = true ): scripts {
		$this->no_prefix						= $no_prefix;

		return $this;
	}
	
	public function get_is_no_prefix(): bool {
		return $this->no_prefix;
	}

	public function set_is_enqueued( bool $is_enqueued = true ): scripts {
		$this->is_enqueued						= $is_enqueued;

		return $this;
	}
	
	public function get_is_enqueued(): bool {
		return $this->is_enqueued;
	}

	public function get_handle(): string {
		if ( $this->get_is_no_prefix() ) {
			return $this->get_ID();
		} else {
			return $this->get_prefix( $this->get_ID() );
		}
	}
	
	public function get_UID(): string {
		if ( $this->get_is_no_prefix() ) {
			return $this->get_ID();
		} else {
			return $this->get_type() . '_' . $this->get_prefix( $this->get_ID() );
		}
	}

	public function set_ID( string $ID ): scripts {
		$this->ID								= $ID;

		return $this;
	}
	
	public function get_ID(): string {
		return $this->ID;
	}

	public function set_localized(array $settings): scripts{

		if( $this->is_localized() ){
			$settings = array_merge($this->get_localized(), $settings);
		}

		$this->localized						= $settings;

		return $this;
	}
	
	public function get_localized(): array{
		return $this->localized;
	}
	
	public function is_localized(): bool{
		return boolval(count($this->get_localized()));
	}

	public function set_is_loaded(): scripts {
		static::$is_loaded[$this->get_type()][$this->get_handle()]	= true;

		return $this;
	}

	public function get_is_loaded(): bool {
		return isset(static::$is_loaded[$this->get_type()][$this->get_handle()]);
	}

	public function set_type( string $type ): scripts {
		$this->type								= $type;

		return $this;
	}

	public function get_type(): string {
		return $this->type;
	}

	public function set_is_backend(): scripts {
		$this->is_backend						= true;

		return $this;
	}
	
	public function get_is_backend(): bool {
		return $this->is_backend;
	}

	public function set_is_gutenberg(): scripts {
		$this->is_gutenberg						= true;

		return $this;
	}
	
	public function get_is_gutenberg(): bool {
		return $this->is_gutenberg;
	}

	public function set_path(string $path, bool $full = false, string $url = ''): scripts {
		if($this->is_valid_url($path)){
			$this->script_url					= $path;
			$this->is_external					= true;

			return $this;
		}

		if(!$full){
			$this->script_url					= $this->get_parent()->get_url($path);
			if(is_file($this->get_parent()->get_parent()->get_path($path))){
				$this->script_path				= $this->get_parent()->get_parent()->get_path($path);
			}elseif(is_file($this->get_parent()->get_path($path))){
				$this->script_path				= $this->get_parent()->get_path($path);
			}
		}else{
			$this->script_path					= $path;
			$this->script_url					= $url;
		}

		return $this;
	}
	
	public function get_css_cache_invalidated(): bool{
		// true = cache invalidated
		// false = cache valid

		if($this->module_css_cache_invalidated !== NULL){
			return $this->module_css_cache_invalidated; // status already retrieved
		}

		if(!isset(static::$list[$this->get_UID()]['cache'][ 'invalidated' ])){
			$this->module_css_cache_invalidated = true;
			return true; // setting not saved yet
		}

		if(is_admin() && intval(static::$list[$this->get_UID()]['cache'][ 'invalidated' ]['gutenberg']->get_data()) === 1){
			$this->module_css_cache_invalidated = true;
			return true; // cache is invalidated
		}
		if(!is_admin() && intval(static::$list[$this->get_UID()]['cache'][ 'invalidated' ]['frontend']->get_data()) === 1){
			$this->module_css_cache_invalidated = true;
			return true; // cache is invalidated
		}

		$this->module_css_cache_invalidated = false;
		return false; // cache is valid
	}
	
	public function set_css_cache_invalidated(bool $invalidated = true, bool $all = false): scripts {
		$this->module_css_cache_invalidated = $invalidated;

		if($all){
			static::$list[$this->get_UID()]['cache']['invalidated']['gutenberg']->set_data(intval($invalidated))->save_option();
			static::$list[$this->get_UID()]['cache']['invalidated']['frontend']->set_data(intval($invalidated))->save_option();
		}else{
			if(is_admin()) {
				static::$list[$this->get_UID()]['cache']['invalidated']['gutenberg']->set_data(intval($invalidated))->save_option();
			}else{
				static::$list[$this->get_UID()]['cache']['invalidated']['frontend']->set_data(intval($invalidated))->save_option();
			}
		}

		return $this;
	}
	
	public function get_path_cached(string $file): string{
		$path		= wp_upload_dir()['basedir'].'/straightvisions/cache/'.$this->get_root()->get_prefix().'/'.$this->get_parent()->get_prefix().'/';

		// create directories of not exist
		if (!is_dir($path.dirname($file))) {
			// dir doesn't exist, make it
			mkdir($path.dirname($file), 0755, true);
		}

		return $path.$file;
	}
	
	public function get_url_cached(string $file): string{
		return wp_upload_dir()['baseurl'].'/straightvisions/cache/'.$this->get_root()->get_prefix().'/'.$this->get_parent()->get_prefix().'/'.$file;
	}
	
	public function cache_css(): scripts {
		$module = $this->get_parent();

		if (
			$module->get_css_cache_active()
			&& $this->get_type() == 'css'
			&&
			(
				$this->get_ID() == 'config'
				||
				(
					(strlen($module->get_block_handle()) > 0)
					&& $this->get_ID() == $module->get_block_handle()
				)
			)
		) {
			$this->cache_css_file();
			$this->set_path($this->get_path_cached($module->get_prefix().'.css'), true, $this->get_url_cached($module->get_prefix().'.css'));
			add_action('admin_footer', array($this,'cache_css_file'), 1000);
		}

		return $this;
	}
	
	public function cache_css_file(){
		$module = $this->get_parent();
		if ($module->get_css_cache_active()) {
			if ($this->get_css_cache_invalidated()) {
				$_s = $module->get_settings();
				$_s = reset($_s);

				ob_start();
				foreach($module->get_scripts() as $script){
					if($script->get_is_enqueued() && file_exists($script->get_path())) {
						require_once($script->get_path());
					}
				}
				$css = ob_get_clean();

				file_put_contents($this->get_path_cached($module->get_prefix().'.css'), $css);
				$this->set_css_cache_invalidated(false);

				// update theme.json
				if($module->get_root()->get_prefix() === 'sv100'){
					$module->theme_json_update();
				}
			}

			return $this;
		}

		return $this;
	}
	
	public function get_path(string $suffix = ''): string {
		return $this->script_path;
	}
	
	public function get_url(string $suffix = ''): string {
		return $this->script_url;
	}
	
	public function is_external(): bool{
		return $this->is_external;
	}
	
	public function set_deps( array $deps ): scripts {
		$this->deps								= $deps;
		
		return $this;
	}
	
	public function get_deps(): array {
		return $this->deps;
	}
	
	// CSS specific
	public function set_media( string $media ): scripts{
		$this->media							= $media;
		
		return $this;
	}
	
	public function get_media(): string {
		return $this->media;
	}
	
	public function set_inline( bool $inline = true ): scripts {
		$this->inline							= $inline;
		
		return $this;
	}
	
	public function get_inline(): bool {
		return $this->inline;
	}

	// supports multiple block names now, using "," string instead of array|string for PHP 7.x
	public function set_block_style( string $label, string $block_name = '' ): scripts {
		// fallback
		$block_name = empty($block_name) ? $this->get_parent()->get_block_name() : $block_name;
		if(empty($block_name)) return $this; // exit

		// single block name
		$block_names = [$block_name];

		// list of block names
		if(strpos($block_name, ',') !== false){
			$block_name = str_replace(' ', '', $block_name);
			$block_names = explode(',', $block_name);
		}

		foreach($block_names as $key => $block_name){
			register_block_style(
				$block_name,
				array(
					'name'         => $this->get_ID(),
					'label'        => $label,
				)
			);
		}

		// @todo: deprecated in PHP 8
		@$this->is_block_style							= $label;

		return $this;
	}

	public function get_block_style(): string {
		return $this->block_style;
	}

	public function set_load_in_header( bool $load_in_header ): scripts {
		$this->load_in_header							= $load_in_header;

		return $this;
	}

	public function get_load_in_header(): bool {
		return $this->load_in_header;
	}
}