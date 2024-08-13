<?php
	
	namespace sv_core;
	
	class setting_upload extends settings{
		private $parent						= false;
		protected static $updated			= array();
		private $allowed_filetypes			= array();
		
		/**
		 * @desc			initialize
		 * @author			Matthias Bathke
		 * @since			1.0
		 * @ignore
		 */
		public function __construct($parent=false){
			$this->parent			= $parent;
		}
		public function set_allowed_filetypes(array $filetypes): setting_upload{
			$this->allowed_filetypes			= $filetypes;
			
			return $this;
		}
		public function get_allowed_filetypes(): array{
			return $this->allowed_filetypes;
		}
		private function delete_attachment(int $attachment_id){
			wp_delete_attachment( $attachment_id, true );
		}
		private function reformat_file_data(array $data): array{
			$data_new = array();
			foreach ( $data as $type => $values ) {
				foreach ( $values as $i => $value ) {
					foreach ( $value as $name => $field ) {
						$data_new[ $i ][ $name ][ $type ] = $field['file'];
					}
				}
			}

			return $data_new;
		}
		private function delete_group_files(array $input): array{
			$key = $this->get_parent()->get_parent()->get_prefix( $this->get_parent()->get_parent()->get_ID() );
			$data = isset($_POST[$key]) ? $_POST[$key] : array();
			if(is_array($data)) {
				foreach ($data as $group => $fields) {
					$group = sanitize_key($group);

					if(is_array($fields)) {
						foreach ($fields as $name => $value) {
							$name = sanitize_key($name);

							if (is_array($value) && isset($value['delete']) && intval($value['delete']) === 1) {
								if(
									isset($input[$group][$name]['file']) &&
									intval($input[$group][$name]['file']) &&
									strlen(get_attached_file($input[$group][$name]['file'])) > 0
								) {
									$this->delete_attachment(intval($input[$group][$name]['file']));
									unset($input[$group][$name]);
								}
							}
						}
					}
				}
			}
			return $input;
		}
		private function field_single($input){
			if(isset($_POST[$this->get_parent()->get_prefix($this->get_parent()->get_ID())]['delete'])){
				// delete checked
				$this->delete_attachment($this->get_data());
				delete_option($this->get_parent()->get_prefix($this->get_parent()->get_ID()));
			}elseif(intval($_FILES[$this->get_parent()->get_prefix($this->get_parent()->get_ID())]['size']['file']) > 0 ) {
				// single setting field

				if($_FILES[ $this->get_parent()->get_prefix( $this->get_parent()->get_ID() ) ]['name']['file'] != '' &&
					$_FILES[ $this->get_parent()->get_prefix( $this->get_parent()->get_ID() ) ]['type']['file'] != '' &&
					$_FILES[ $this->get_parent()->get_prefix( $this->get_parent()->get_ID() ) ]['tmp_name']['file'] != '' &&
					is_file($_FILES[ $this->get_parent()->get_prefix( $this->get_parent()->get_ID() ) ]['tmp_name']['file'])) {
						$input = $this->handle_file_upload(
							wp_handle_upload(
								$this->unfiltered_files_resorted(), array( 'test_form' => false ) )
						);
					return $input;
				}
			}

			return $input ? $input : $this->get_parent()->get_data();
		}
		/*
		 * IMPORTANT: This method just resorts the FILES-Upload array to allow handling and sanitation through wp_handle_upload. Do not work with return values of this method without proper sanitation.
		 */
		private function &unfiltered_files_resorted(): array{
			$unfiltered_FILES = array(
				'name'			=> $_FILES[ $this->get_parent()->get_prefix( $this->get_parent()->get_ID() ) ]['name']['file'],
				'file'			=> $_FILES[ $this->get_parent()->get_prefix( $this->get_parent()->get_ID() ) ]['type']['file'],
				'tmp_name'		=> $_FILES[ $this->get_parent()->get_prefix( $this->get_parent()->get_ID() ) ]['tmp_name']['file'],
				'error'			=> $_FILES[ $this->get_parent()->get_prefix( $this->get_parent()->get_ID() ) ]['error']['file'],
				'size'			=> $_FILES[ $this->get_parent()->get_prefix( $this->get_parent()->get_ID() ) ]['size']['file'],
				'type'			=> $_FILES[ $this->get_parent()->get_prefix( $this->get_parent()->get_ID() ) ]['type']['file']
			);

			return $unfiltered_FILES;
		}
		private function field_group($input){
			if ( ! empty( $this->get_parent()->get_data() ) ) {
				foreach( $input as $i => $field ) {
					if ( ! empty( $this->get_parent()->get_data()[ $i ] ) ) {
						$input[ $i ] = array_merge($this->get_parent()->get_data()[ $i ], $field);
					}
				}
			}
			
			// make sure it's a group field
			if(method_exists($this->get_parent()->get_parent()->get_parent(), 'get_ID') && isset($_FILES[$this->get_parent()->get_parent()->get_parent()->get_prefix($this->get_parent()->get_parent()->get_parent()->get_ID())])) {
				// reformat data to convert group uploads to single uploads
				$data_new					= $this->reformat_file_data($_FILES[ $this->get_parent()->get_parent()->get_parent()->get_prefix( $this->get_parent()->get_parent()->get_parent()->get_ID() ) ]);

				// new uploads
				foreach ( $data_new as $i => $data ) {
					foreach ( $data as $name => $fields ) {
						// do not attempt empty upload fields
						if ( $fields['name'] != '' &&
							$fields['type'] != '' &&
							$fields['tmp_name'] != '' &&
							is_file( $fields['tmp_name'] ) ) {
							$input[ $i ][ $name ]['file'] = $this->handle_file_upload(
								wp_handle_upload( $fields, array( 'test_form' => false ) )
							);
							// make sure existing uploads are carried
						} elseif ( !isset($input[ $i ][ $name ]['file']) && isset( $this->get_parent()->get_parent()->get_parent()->get_data()[ $i ][ $name ] ) && intval( $this->get_parent()->get_parent()->get_parent()->get_data()[ $i ][ $name ] ) > 0 ) {
							$input[ $i ][ $name ] = $this->get_parent()->get_parent()->get_parent()->get_data()[ $i ][ $name ];
						}
					}
				}

				// delete files if requested
				$input						= $this->delete_group_files($input);
			}

			return $input ? $input : $this->get_data();
		}
		private function is_single_field(){
			if(isset($_FILES[$this->get_parent()->get_prefix($this->get_parent()->get_ID())]['delete'])){
				return true;
			}
			if(isset($_FILES[$this->get_parent()->get_prefix($this->get_parent()->get_ID())]['name']['file'])){
				return true;
			}
			return false;
		}
		public function field_callback($input){
			if(isset($_POST['option_page'])) {
				//@todo This method get's triggered twice, that's why a second upload is attempted
				if ( isset( static::$updated[ $this->get_parent()->get_prefix( $this->get_parent()->get_ID() ) ] ) ) {
					return $input;
				}
				static::$updated[ $this->get_parent()->get_prefix( $this->get_parent()->get_ID() ) ] = true;
				
				// check if single or group field
				if ( $this->is_single_field() ) {
					return $this->field_single( $input );
				} else {
					return $this->field_group( $input );
				}
			}else{
				return $input;
			}
		}
		private function handle_file_upload(array $file){
			// remove old attachment
			wp_delete_attachment( $this->get_data(), true );

			$input				= wp_insert_attachment(array(
				'guid'		   => wp_upload_dir()['url'] . '/' . basename( $file['file'] ),
				'post_mime_type' => $file['type'],
				'post_title'	 => preg_replace( '/\.[^.]+$/', '', basename( $file['file'] ) ),
				'post_content'   => '',
				'post_status'	=> 'inherit',
			),
				$file['file']);
			
			// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
			require_once( ABSPATH . 'wp-admin/includes/image.php' );
			
			// Generate the metadata for the attachment, and update the database record.
			$attach_data = wp_generate_attachment_metadata( $input, $file['file'] );
			wp_update_attachment_metadata( $input, $attach_data );
			
			return $input;
		}
	}