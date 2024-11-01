<?php
/*
Plugin Name: Yank Widget
Plugin URI: http://funroe.net/projects/yank-widget
Description: Yank Widget allows you to place page content into sidebar widgets for different pages and posts.
Version: 1.2.1
Author: Jess Planck
Author URI: http://funroe.net

Copyright (c) Jess Planck (http://funroe.net/)
Yank Widget is released under the GNU General Public
License: http://www.gnu.org/licenses/gpl.txt

This is a WordPress plugin (http://wordpress.org). WordPress is
free software; you can redistribute it and/or modify it under the
terms of the GNU General Public License as published by the Free
Software Foundation; either version 2 of the License, or (at your
option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
General Public License for more details.

For a copy of the GNU General Public License, write to:

Free Software Foundation, Inc.
59 Temple Place, Suite 330
Boston, MA  02111-1307
USA

You can also view a copy of the HTML version of the GNU General
Public License at http://www.gnu.org/copyleft/gpl.html
*/

// TODO: load_plugin_textdomain('yank_widget', 'wp-content/plugins/yank-widget');

/**
* Include Yank Widget core class since it is always needed.
*/
require_once( ABSPATH .  PLUGINDIR . '/yank-widget/yank-widget.core.class.php' );

/**
* Conditional includes for Yank Widget functions and classes in WordPress admin panels.
* Also intialize the global $yank_widget object based on is_admin().
*
* @global object $yank_widget is the main control object for Yank Widget.
* @name $yank_widget
*/
if ( is_admin() ) {
	require_once( ABSPATH . PLUGINDIR . '/yank-widget/yank-widget.admin.class.php' );
	require_once( ABSPATH . PLUGINDIR . '/yank-widget/yank-widget-admin.php' );
	if ( class_exists( 'yank_widget_core' ) ) $yank_widget = new yank_widget_admin;
} else {
	if ( class_exists( 'yank_widget_core' ) ) $yank_widget = new yank_widget_core;
}

/**
 * wp_link_pages() - {@internal Missing Short Description}}
 *
 * {@internal Missing Long Description}}
 *
 * @since 1.2.0
 *
 * @param unknown_type $args
 * @return unknown
 */
function yank_widget_link_pages( $yankedID ) {
	
	$link = get_permalink( $yankedID );
	
	$output = '<p class="page-link"><a href="' . $link . '">' . __( 'Read More' ) . '</a></p>';

	return $output;
}

/**
* Yank Widget
*
* Function used to display Yank Widgets in sidebars on public site.
* 
* @global object $yank_widget
* @param $args array Common WordPress widget arguments
* @param $widget_args array Common WordPress widget arguments
*/
function yank_widget( $args, $widget_args = 1 ) {
	global $post, $more, $yank_widget;

	extract( $args, EXTR_SKIP );
	if ( is_numeric($widget_args) )
		$widget_args = array( 'number' => $widget_args );
	$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
	extract( $widget_args, EXTR_SKIP );
		
	if ( !isset( $yank_widget->current_yank_widgets[$number] ) && $yank_widget->widgets[$number]['show_container'] != 'true' ) return;
	
	$widget = $yank_widget->current_yank_widgets[$number];
	
	if( !empty( $widget ) ) {
		
		$yanked_widget_content = false;
		
		if ( $yank_widget->widgets[$number][title] != '' ) {
			$yanked_widget_title = stripslashes( $yank_widget->widgets[$number]['title'] );
		} else {
			$yanked_widget_title = false;
		}
		
		// Set the temporary variables so we can restore them later
		$post_temp = $post;		
		$more_temp = $more;

		$yanked = new WP_Query( "page_id=$widget" );
		$more = 1;
		
		if ( $yanked->have_posts() ) {
			while ($yanked->have_posts()) {
				$yanked->the_post();
				if ( !$yanked_widget_title ) $yanked_widget_title = get_the_title();
				$yanked_widget_content = get_the_content();
				// Shortcode support
				$yanked_widget_content = do_shortcode( $yanked_widget_content );
				$yanked_widget_linked_pages = wp_link_pages('echo=0&before=<p class="page-link">&after=</p>&next_or_number=next&nextpagelink=Read More');
				if ( !current_user_can( 'edit_page', $yanked->ID ) ) continue;
				$yanked_widget_edit_link = '<p class="edit-link"><a href="' . get_edit_post_link( $yanked->ID ) . '" title="' . __( 'Edit' ) . '">' . __( 'Edit' ) . '</a></p>';				
			}
		}
		
		// Restore temporary variables!
		$post = $post_temp;
		$more = $more_temp;
	}
	
	if( !empty( $widget ) || $yank_widget->widgets[$number]['show_container'] == 'true' ) {		
		echo $before_widget;
		
		if ( $yank_widget->widgets[$number]['title_hide'] != 'true' ) {
			if ( $yanked_widget_title != false ) {
				echo $before_title;
				echo $yanked_widget_title;
				echo $after_title;
			}
		}

		if ( $yanked_widget_content != false ) {
			echo '<div>';
			echo $yanked_widget_content;
			echo '</div>';
			echo $yanked_widget_linked_pages;
			echo $yanked_widget_edit_link;
		}
		
		echo $after_widget;
	}
}

/**
* Yank Widget Controls
*
* Function used to display and process Yank Widget controls in Widget admininstration panel
* 
* @global object $wp_registered_widgets
* @global object $yank_widget
* @global object $yank_widget
* @param $widget_args array Common WordPress widget arguments
*/
function yank_widget_control( $widget_args = 1 ) {
	global $wp_registered_widgets, $yank_widget;
	static $updated = false; // Whether or not we have already updated the data after a POST submit

	if ( is_numeric($widget_args) )
		$widget_args = array( 'number' => $widget_args );
	$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
	extract( $widget_args, EXTR_SKIP );

	// Data should be stored as array:  array( number => data for that instance of the widget, ... )
	$options = $yank_widget->widgets;
	if ( !is_array($options) )
		$options = array();

	// We need to update the data
	if ( !$updated && !empty($_POST['sidebar']) ) {
		// Tells us what sidebar to put the data in
		$sidebar = (string) $_POST['sidebar'];

		$sidebars_widgets = wp_get_sidebars_widgets();
		if ( isset($sidebars_widgets[$sidebar]) )
			$this_sidebar =& $sidebars_widgets[$sidebar];
		else
			$this_sidebar = array();

		foreach ( $this_sidebar as $_widget_id ) {
			// Remove all widgets of this type from the sidebar.  We'll add the new data in a second.  This makes sure we don't get any duplicate data
			// since widget ids aren't necessarily persistent across multiple updates
			if ( 'yank_widget' == $wp_registered_widgets[$_widget_id]['callback'] && isset($wp_registered_widgets[$_widget_id]['params'][0]['number']) ) {
				$widget_number = $wp_registered_widgets[$_widget_id]['params'][0]['number'];
				if ( !in_array( "yank-widget-$widget_number", $_POST['widget-id'] ) ) { // the widget has been removed. "yank-widget-$widget_number" is "{id_base}-{widget_number}
					unset($options[$widget_number]);
					$yank_widget->remove_yank_widget( $widget_number );
				}
			}
		}
		
		$yank_widget_number = 1;
		foreach ( (array) $_POST['yank-widget'] as $widget_number => $yank_widget_instance ) {
			// compile data from $yank_widget_instance
			if ( !isset( $yank_widget_instance['title'] ) && isset( $options[$widget_number] ) ) // user clicked cancel
				continue;
			$title = wp_specialchars( $yank_widget_instance['title'] );
			$title_hide = ( $yank_widget_instance['title_hide'] == 'true' ? 'true' : 'false' ); 
			$show_container = ( $yank_widget_instance['show_container'] == 'true' ? 'true' : 'false' ); 
			$options[$widget_number] = array( 
				'title' => $title,
				'title_hide' => $title_hide,
				'show_container' => $show_container,
				'number' => $yank_widget_number
			);  // Even simple widgets should store stuff in array, rather than in scalar
			$yank_widget_number++;
		}

		update_option('yank_widget_widgets', $options);

		$updated = true; // So that we don't go through this more than once
	}


	// Here we echo out the form
	if ( -1 == $number ) { // We echo out a template for a form which can be converted to a specific form later via JS
		$title = '';
		$title_hide = 'false';
		$show_container = 'false';
		$number = '%i%';
	} else {
		$title = attribute_escape($options[$number]['title']);
		$title_hide = $options[$number]['title_hide'];
		$show_container = $options[$number]['show_container'];
		$yank_widget_current = $options[$number]['number'];
	}

	$check_hide = ( $title_hide == 'true' ? ' checked="checked"' : '' );
	$check_container = ( $show_container == 'true' ? ' checked="checked"' : '' )

	// The form has inputs with names like [$number][title] so that all data for that instance of
	// the widget are stored in one $_POST variable: $_POST[''][$number]
?>
		<p>
			<strong>Yank Widget #<?php echo $options[$number]['number']; ?></strong><br /> 
			<strong>CSS class:</strong> yank-widget-<?php echo $options[$number]['number']; ?><br /> 
			<strong>CSS id:</strong> yank-widget-<?php echo $number; ?>
		</p>
		<p>			
			<strong>Title</strong><br />
			<input class="widefat" id="yank-widget-title-<?php echo $number; ?>" name="yank-widget[<?php echo $number; ?>][title]" type="text" value="<?php echo stripslashes( $title ); ?>" /><br />
			Hide Title? <input id="yank-widget-hide-title-<?php echo $number; ?>" name="yank-widget[<?php echo $number; ?>][title_hide]"  type="checkbox"<?php echo $check_hide; ?> value="true"><br />
			Always show container widget HTML? <input id="yank-widget-show-container-<?php echo $number; ?>" name="yank-widget[<?php echo $number; ?>][show_container]"  type="checkbox"<?php echo $check_container; ?> value="true">

			<input type="hidden" id="yank-widget-number-<?php echo $number; ?>" name="yank-widget[<?php echo $number; ?>][number]" value="<?php echo $options[$number]['number']; ?>" />
			<input type="hidden" id="yank-widget-submit-<?php echo $number; ?>" name="yank-widget[<?php echo $number; ?>][submit]" value="1" />
		</p>
<?php
}

/**
* Yank Widget Register
*
* Function used to register each instance of Yank Widget on startup
*
*/
function yank_widget_register() {
	global $yank_widget;
	
	// Data should be stored as array:  array( number => data for that instance of the widget, ... )
	$options = $yank_widget->widgets;
	if ( !is_array($options) )
		$options = array();
		
	$widget_ops = array(
		'classname' => 'yank_widget',
		'description' => __('Yank Widget displays static page content in sidebars.')
	);
	$control_ops = array('width' => 400, 'height' => 350, 'id_base' => 'yank-widget');
	$name = __('Yank Widget');
	
	$id = false;
	
	foreach ( array_keys( $options ) as $o ) {
		
		// Old widgets can have null values for some reason
		if ( !isset($options[$o]['title']) ) // we used 'title' above in our exampple.  Replace with with whatever your real data are.
			continue;

		// $id should look like {$id_base}-{$o}
		$id = "yank-widget-$o"; // Never never never translate an id
		$widget_ops['classname'] = 'yank-widget yank-widget-' . $options[$o]['number'];
		wp_register_sidebar_widget( $id, $name, 'yank_widget', $widget_ops, array( 'number' => $o ) );
		wp_register_widget_control( $id, $name, 'yank_widget_control', $control_ops, array( 'number' => $o ) );
		
	}

	// If there are none, we register the widget's existance with a generic template
	if ( !$id ) {
		wp_register_sidebar_widget( 'yank-widget-1', $name, 'yank_widget', $widget_ops, array( 'number' => -1 ) );
		wp_register_widget_control( 'yank-widget-1', $name, 'yank_widget_control', $control_ops, array( 'number' => -1 ) );
	}
}

function yank_widget_init() {
	global $yank_widget, $post;
	$yank_widget->load_yank_widgets( $post->ID );
}

/**
* Wordpress actions and filters for Yank Widget
*/
add_action('wp', 'yank_widget_init');
add_action('widgets_init', 'yank_widget_register');
add_filter( 'wp_list_pages_excludes', array( &$yank_widget, 'exclude_pages' ) );

/**
* Conditional Wordpress hooks, actions, and filters for Yank Widget interfaces in WordPress admin panels
*/
if ( is_admin() ) {
	register_activation_hook( __FILE__, array( &$yank_widget, 'install' ) );
	
	add_action('admin_menu', 'yank_widget_ui');
		
	/* actions for save_post */
	add_action('save_post', 'yank_widget_manage_content');
	add_action('admin_init', 'yank_widget_admin_setup');	
} 

?>