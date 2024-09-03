<?php

namespace sv_core;

if ( !class_exists( '\sv_core\core' ) ) {
	require_once('abstract.php');

	class core extends sv_abstract {
		public static $notices				= false;
		public static $settings				= false;
		public static $remote_get			= false;
		public static $widgets				= false;
		public static $info					= false;
		public static $metabox				= false;
		public static $scripts				= false;
		public $ajax_fragmented_requests	= false;
		public static $initialized			= false;

		// beta
		public static $scripts_localized_collection = array();

		public function setup_core( $path, $name ): bool{
			$output = false;

			if( $this->core_validation() === true ){
				parent::$instances_active[ $name ]	= $this;

				// these modules are available in all instances and should be initialized once only.
				if ( static::$initialized === false ) {

					self::$path_core			= trailingslashit( str_replace('\\','/', dirname( __FILE__ )) );
					self::$url_core				= trailingslashit( str_replace('\\','/',get_site_url()) )
												. str_replace( str_replace('\\','/',ABSPATH),'', str_replace('\\','/', self::$path_core) );
					parent::$active_core		= $this;

					// run setup functions
					$this->setup_settings();

					$this->setup_remote_get();

					$this->setup_widgets();

					$this->setup_info();

					$this->setup_metabox();

					$this->setup_ajax_fragmented_requests();

					// run setup actions
					$this->setup_wp_actions();

					// run setup filters
					$this->setup_wp_filters($path);

					// run setup credits
					$this->setup_credits();
	
					add_action( 'wp_ajax_sv_core_gutenberg_save_post_update_metaboxes' , function(){
							echo json_encode(apply_filters('sv_core_gutenberg_save_post_update_metaboxes', array()));
							die();
					}, 100 );
				}

				// run setup scripts
				$this->setup_scripts();

				// initialize sub core
				$this->init_subcore();

				// run setup modules
				$this->setup_modules($path);

				// run setup localized per plugin
				$this->setup_wp_plugin_localized();

				static::$initialized = true;
				$output			  = true;

			}else{

				$this->set_core_update_warning();

			}

			return $output;

		}
		private function core_validation(){
			$output = false;

			if(
				$this->get_root()->get_version_core_match() == $this->get_version_core()
				|| ( defined('WP_DEBUG') && WP_DEBUG === true )
			){

				$output = true;

			}

			return $output;

		}

		private function set_core_update_warning(){
			/*
			* @todo remove html from php class - use included tpl file instead
			*/

			add_action('admin_init', function(){
				?>
				<div class="update-nag">
					<?php _e( 'Please check that all SV products are up to date. You may need to update ', 'sv_core' ); ?><strong><?php echo $this->get_name(); ?>.</strong><br/>
					<?php _e( 'SV Core was loaded by', 'sv_core' ); ?>
					<em><?php echo $this->get_path_core(); ?></em>
					<?php _e( 'with version:', 'sv_core' ); ?>
					<strong><?php echo $this->get_version_core(); ?></strong>, <?php echo sprintf(__( 'but %s v%s requires core-version', 'sv_core' ), $this->get_name(), $this->get_version(true)); ?>
					<strong><?php echo $this->get_root()->get_version_core_match(); ?></strong>
				</div>
				<?php
			});

		}

		public function ajax_get_section(){
			$output = null;

			if( $_REQUEST['nonce'] &&
				$_REQUEST['section'] &&
				$_REQUEST['page'] &&
				wp_verify_nonce( $_REQUEST['nonce'], 'sv_admin_ajax_'.$_REQUEST['page'] ) !== false
				&& $this->get_instances()[ $_REQUEST['page'] ]
			) {

				$section = str_replace('#', '', sanitize_key($_REQUEST['section']));
				$section = $this->get_instances()[ $_REQUEST['page'] ]->get_root()->get_section_single($section);

				if( $section ){
					$section_name = $section['object']->get_prefix(); // var will be used in included file
					$path = $this->get_root()->get_path_core( 'backend/tpl/section_' . $section[ 'object' ]->get_section_type() . '.php' );
					ob_start();
					include( $path );
					$output = ob_get_contents();
					ob_end_clean();
				}

			}

			if($output){
				$this->send_response('success', '', base64_encode(mb_convert_encoding($output, 'ISO-8859-1', 'UTF-8'))); // magic
			}else{
				$this->send_response('error', 'Section not found!');
			}


		}
		private function is_valid_array($array): bool{
			// no array
			if(!is_array($array)){
				$this->ajaxStatus(var_export($array,true), __('Setting saved', 'sv_core'));
				return false;
			}

			// array empty
			if(count($array) === 0){
				$this->ajaxStatus(var_export($array,true), __('Setting saved', 'sv_core'));
				return false;
			}

			return true;
		}
		public function ajax_settings_save_form(){
			if( $_POST['nonce'] &&
				$_POST['fields'] &&
				$_POST['page'] &&
				wp_verify_nonce( $_POST['nonce'], 'sv_admin_ajax_'.$_POST['page'] ) !== false
			) {
				// @todo: strip unnecessary array level
				//var_dump($_POST['fields']); return;
				$fields = reset($_POST['fields']);

				// no array
				if(!$this->is_valid_array($fields)){
					return;
				}

				foreach($fields as $arr){
					if(!$this->is_valid_array($arr)){
						continue;
					}

					if(!isset($arr['name'])){
						error_log('Setting has no name: '.var_export($arr,true));
						continue;
					}

					if(!isset($arr['value'])){
						error_log('Setting has no value: '.var_export($arr,true));
						continue;
					}

					$key = $arr['name'];
					$val = $arr['value'];

					// primitive check prefixed key names, if not ignore it
					if( strpos($key, 'sv100_') === false ){ // true >= 0
						continue;
					}

					// uncomment this if you want to test a single setting
					/*if(strpos($key,'sv100_sv_archive_settings_home_margin') === false) {
						continue;
					}*/
					/*if($key != 'sv100_sv_archive_settings_home_margin[desktop][left]') {
						continue;
					}*/

					/*if($key != 'sv100_sv_archive_settings_home_title_font_size[mobile]') {
						continue;
					}*/
					/*
					if($key != 'sv100_sv_archive_settings_home_show_header') {
						continue;
					}*/

					// primitive check if this is a single option (no array)
					if( strpos($key, '[') === false ){ // true >= 0
						// @todo: implement input validation
						update_option( $key, $val );
						continue;
					}

					// setting may be an array now
					$setting_update_array		= array();
					parse_str ( $key , $setting_update_array );

					if(!is_array($setting_update_array)){
						continue; // setting is not a string, not an array, so not supported yet
					}

					$setting_update_key			= array_key_first($setting_update_array); // option key
					$setting_update_structure	= reset($setting_update_array); // option structure

					/*error_log(var_export($setting_update_key,true));
					error_log(var_export($setting_update_structure,true));*/

					/*
					 * Set value to deepest array key, e.g. set array
					 *

					 array (
					  'mobile' =>
					  array (
						'right' => '',
					  ),
					)

					to

					array (
					  'mobile' =>
					  array (
						'right' => '22px',
					  ),
					)

					 *
					 */
					array_walk_recursive($setting_update_structure, function (&$item, $key, $val)
					{
						$item = $val;
					}, $val);

					$setting_update_val			= $setting_update_structure;

					// @todo: implement input validation

					//delete_option( $setting_update_key); continue;

					// get existing option
					$option_db				= get_option( $setting_update_key, true );

					// setting is empty or not valid array, so replace with value
					if(!$option_db || !$this->is_valid_array($option_db)){
						update_option( $setting_update_key, $setting_update_val );
						continue;
					}

					// merge updated setting into existing option
					//$setting_merged			= array_merge_recursive($option_db, $setting_update_val);
					//$setting_merged			= $setting_update_val+$option_db;
					$setting_merged			= $this->array_merge_recursive_distinct($option_db, $setting_update_val);

					/*error_log(var_export($setting_update_key,true));
					error_log(var_export($option_db,true));
					error_log(var_export($setting_update_val,true));
					error_log(var_export($setting_merged,true));*/
					//die('end');

					update_option( $setting_update_key, $setting_merged );
				}

				$this->ajaxStatus('success', __('Setting saved', 'sv_core'));
			}else{
				$this->ajaxStatus( 'error', __('Nonce check failed / Empty data.', 'sv_core') );
			}
	
		}
		
		public function ajax_expert_mode(){
			if( empty($_POST) || isset($_POST) === false ){

				$this->ajaxStatus('error', __('Nothing to update.', 'sv_core'));

			}else{

				if( wp_verify_nonce( $_POST['nonce'], 'sv_expert_mode' ) !== false ) {

					if( get_current_user_id() ){

						update_user_meta(get_current_user_id(), 'sv_core_expert_mode', intval($_POST['state']));
						$this->ajaxStatus('success', __('Setting saved', 'sv_core'));

					}else{

						$this->ajaxStatus('error', __('You are unauthorized to perform this action.', 'sv_core'));

					}

				}else{

					$this->ajaxStatus( 'error', __('Nonce check cannot fail.', 'sv_core') );

				}

			}

		}

		public function ajaxStatus($status, $message, $data = NULL) {
			$response = array (
				'status'		=> $status,
				'message'	   => $message,
				'data'		  => $data
			);

			$output = json_encode($response);

			exit($output);

		}

		private function send_response(string $status, string $message, $data = ''){
			$response = array (
				'status'		=> $status,
				'message'	   => $message,
				'data'		  => $data
			);

			wp_send_json($response);
		}

		private function setup_credits() {
			add_action('wp_footer', function(){

				echo "\n\n".'<!--' . __( 'Website enhanced by straightvisions.com', 'sv_core' ). '-->'."\n\n";

				},
				999999
			);
		}
		
		protected function setup_settings(){
			require_once( 'settings/settings.php' );

			static::$settings = new settings;
			static::$settings->set_root( $this->get_root() );
			static::$settings->set_parent( $this );

		}

		protected function setup_remote_get(){
			require_once( 'remote_get/remote_get.php' );

			static::$remote_get = new remote_get;
			static::$remote_get->set_root( $this->get_root() );
			static::$remote_get->set_parent( $this );

		}
		
		protected function setup_widgets(){
			require_once( 'widgets/widgets.php' );

			static::$widgets = new widgets;
			static::$widgets->set_root( $this->get_root() );
			static::$widgets->set_parent( $this );

		}
		
		protected function setup_info(){
			require_once( 'info/info.php' );

			static::$info = new info;
			static::$info->set_root( $this->get_root() );
			static::$info->set_parent( $this );
			static::$info->init();

		}
		
		protected function setup_metabox(){
			require_once( 'metabox/metabox.php' );

			static::$metabox = new metabox;
			static::$metabox->set_root( $this->get_root() );
			static::$metabox->set_parent( $this );
			static::$metabox->init();

		}
		
		protected function setup_ajax_fragmented_requests(){
			require_once( 'ajax_fragmented_requests/ajax_fragmented_requests.php' );

			$this->ajax_fragmented_requests = new ajax_fragmented_requests;
			$this->ajax_fragmented_requests->set_root( $this->get_root() );
			$this->ajax_fragmented_requests->set_parent( $this );
			$this->ajax_fragmented_requests->init();

		}
		
		protected function setup_wp_actions(){
			// setup action for expert mode
			add_action('plugins_loaded', function(){
				//@todo Add description to describe what the expert mode does
				$this->get_root()->set_is_expert_mode(
						$this->get_setting()
							->set_ID('sv_expert_mode')
							->set_title( __('Expert Mode', 'sv_core') )
							->set_is_no_prefix()
							->load_type('checkbox')
							->run_type()
							->set_data( intval( get_user_meta( get_current_user_id(), 'sv_core_expert_mode', true ) ) )
							->get_data()
				);

		   });

			add_action( 'wp_ajax_sv_core_expert_mode', array($this, 'ajax_expert_mode'));

			add_action( 'wp_ajax_sv_ajax_get_section', array($this, 'ajax_get_section'));
			
			add_action( 'wp_ajax_sv_ajax_settings_save_form', array($this, 'ajax_settings_save_form'));

			// setup init action
			add_action( 'init', array( $this, 'load_core_scripts' ), 100 );
		}

		// Loads all required core scripts
		public function load_core_scripts() {
			if($this->is_theme_instance() === false){
				$this->get_root()->get_script( 'sv_core_admin' )
					->set_path( $this->get_path_core( '../assets/admin.js' ), true, $this->get_url_core( '../assets/admin.js' ) )
					->set_is_backend()
					->set_is_enqueued()
					->set_is_no_prefix()
					->set_type( 'js' )
					->set_deps( array( 'jquery' ) )
					->set_is_required()
					->set_localized( array(
						'ajaxurl'		   => admin_url( 'admin-ajax.php' ),
						'nonce_expert_mode' => \wp_create_nonce( 'sv_expert_mode' ),
						'settings_saved'	=> __('Setting saved', 'sv_core')
					) )
					->set_localized(static::$scripts_localized_collection['sv_core_admin'])
					;

				$this->get_root()->get_script( 'sv_core_admin_sections' )
					->set_path( $this->get_path_core( '../assets/admin_sections.js' ), true, $this->get_url_core( '../assets/admin_sections.js' ) )
					->set_is_backend()
					->set_is_enqueued()
					->set_is_no_prefix()
					->set_type( 'js' )
					->set_deps( array( 'sv_core_admin', 'jquery' ) )
					->set_is_required();

				$this->get_root()->get_script( 'sv_core_color_picker' )
					->set_is_no_prefix()
					->set_path( $this->get_path_core( 'settings/js/sv_color_picker_min/sv_color_picker.min.js' ), true, $this->get_url_core( 'settings/js/sv_color_picker_min/sv_color_picker.min.js' ) )
					->set_type( 'js' )
					->set_deps( array( 'jquery', 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor' ) )
					->set_is_backend()
					->set_is_enqueued();
			}

			// Creates an action when all required core scripts are loaded
			do_action( 'sv_core_module_scripts_loaded' );
		}

		public function setup_wp_plugin_localized(){
			if(!did_action('init') || static::$initialized === false){
				// wait for init
				add_action( 'init', array( $this, 'setup_wp_plugin_localized' ), 50 );
			}else{
				// merge locals
				// if not set, set it
				if(!isset(static::$scripts_localized_collection['sv_core_admin'])){
					static::$scripts_localized_collection['sv_core_admin'] = array();
				}

				static::$scripts_localized_collection['sv_core_admin'] = array_merge(array(
						'nonce_sv_admin_ajax_'.$this->get_name() =>  \wp_create_nonce( 'sv_admin_ajax_'.$this->get_name() )
						), static::$scripts_localized_collection['sv_core_admin']);

			}

		}
		
		protected function setup_wp_filters(string $path){
			add_filter( 'plugin_action_links_' . plugin_basename( $path ) . '/' . plugin_basename( $path ) . '.php', array( $this, 'plugin_action_links' ), 10, 5 );
		}
		
		protected function setup_scripts(){
			require_once( 'scripts/scripts.php' );

			static::$scripts = new scripts;
			static::$scripts->set_root( $this->get_root() );
			static::$scripts->set_parent( $this );
			static::$scripts->init();
		}
		
		protected function setup_modules(string $path){
			if( is_file( $path . 'lib/modules/modules.php' ) ) {
				$this->modules->init();
			}

		}

	}
}