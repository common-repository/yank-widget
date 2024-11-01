<?php 



function yank_widget_admin_setup() {
	global $yank_widget;
		
	$yank_widget->init_ui();
		
	$yank_widget_option_page = add_options_page( __('Yank Widget', 'yank_widget'), __('Yank Widget', 'yank_widget'), 5, 'yank-widget-admin.php', 'yank_widget_options_form');
		
	if ( strstr( $_GET['page'], 'yank-widget-admin' ) != false ) {
	
		if (  $_REQUEST['yank_widget_action'] == 'uninstall' ) {
			check_admin_referer( 'yank_widget_nonce-' . $yank_widget->nonce );
			$yank_widget->uninstall();
			$yank_widget->is_installed = false;
		}
		
		if (  $_REQUEST['yank_widget_action'] == 'options' ) {
			check_admin_referer( 'yank_widget_nonce-' . $yank_widget->nonce );
			$yank_widget->do_options();
		}
		
		add_action( 'admin_head', array( &$yank_widget, 'js_uninstall_confirm' ) );
		add_action( 'admin_head', array( &$yank_widget, 'css' ) );

	}
}

/**
* Manage Yank Content
*
* Function used to add, update, and delete yanked options in database.
*
*/
function yank_widget_manage_content( $post_ID ) {
	global $wpdb, $yank_widget;
		
	 if ( !isset( $_REQUEST['yank_widgets'] ) ) return;
	 $yank_widget->manage_yank_widgets( $_REQUEST['yank_widgets'], $post_ID );
	
	return $post_ID;
}


function yank_widget_options_form() {
	global $yank_widget;
	$yank_widget->options_ui();
}

function yank_widget_ui() {
	global $yank_widget;
	add_meta_box( 'yank_widget_control', __( 'Yank Widget', 'yank_widget_textdomain' ), array( &$yank_widget, 'yank_management' ), 'page', 'advanced' );
	add_meta_box( 'yank_widget_control', __( 'Yank Widget', 'yank_widget_textdomain' ), array( &$yank_widget, 'yank_management' ), 'post', 'advanced' );
}

?>