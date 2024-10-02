<?php if ( current_user_can( apply_filters('sv_admin_menu_capability', 'manage_options') ) ) { ?>
	<div class="sv_setting_subpage">
		<h2><?php _e('General', 'sv100'); ?></h2>
		<div class="sv_setting_flex">
			<?php
				echo $module->get_setting( 'disable_css_cache' )->form();
				echo $module->get_setting( 'disable_all_css' )->form();
				echo $module->get_setting( 'disable_all_js' )->form();
			?>
		</div>
	</div>
<?php } ?>