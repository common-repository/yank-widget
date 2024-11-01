<?php

if ( !class_exists( 'yank_widget_core' ) ) {

    class yank_widget_core { 
		
        var $nice_name;
        var $nice_system_name;
        var $options;
        
        var $core_path;
        var $core_uri;
        
        var $db_table;
        var $page_excludes;
        var $widgets;
        
        var $is_installed;
        
        var $current_yank_widgets;

        function yank_widget_core() { // Maintain php4 compatiblity  
        	global $wpdb;

			$this->nice_name = 'yank_widget';
			$this->nice_system_name = 'yank-widget';
			
			$this->options = array(
        		'db_version' => '1.0',
        		'db_table' => $wpdb->prefix . $this->nice_name,
        		'widget_limit' => 9,
        		'user_level' => 10
        	);
        	
			$this->core_path = WP_PLUGIN_DIR . "/$this->nice_system_name/";
        	$this->core_uri = WP_PLUGIN_URL . "/$this->nice_system_name/";
   	
        	$this->db_table = $this->options['db_table'];
        	
        	$this->page_excludes = array();
        	$this->widgets = array();
  
        	$this->is_installed = $this->is_db_installed();
        	
        	if ( !$this->is_installed ) return;
        	
        	$this->page_excludes = get_option( $this->nice_name . '_excludes' );
        	$this->widgets = get_option( $this->nice_name . '_widgets' );
        	$this->options = get_option( $this->nice_name . '_options' );
        	
        	
        }

        function is_db_installed() {
        	global $wpdb;
        	if( $wpdb->get_var( "SHOW TABLES LIKE '$this->db_table'") == $this->db_table ) return true;
			return false;
        }

		/**
		* Load Yanked pages for current view
		*
		* Function used as action to load ids for posts in yank widget areas and
		* remove any yank widget with no content
		*/
		function load_yank_widgets( $postID ) {
			global $wpdb, $wp_registered_widgets;
						
			$get_yank_widgets = $wpdb->get_results( $wpdb->prepare("
				SELECT widget_id, yanked_id FROM $this->db_table
				WHERE post_id=%d
			", $postID ));
			
			if( empty( $get_yank_widgets ) ) return;
			
			foreach ( $get_yank_widgets as $yank_widget ) {
				$this->current_yank_widgets[$yank_widget->widget_id] = $yank_widget->yanked_id;
			}
			
		}  


		/**
		* Filter excluded pages from wp_list_pages
		*
		* Function used as filter to add noted pages to be excluded from page listings.
		*
		*/
		function exclude_pages( $excludes ) {
							
			if ( !is_array( $this->page_excludes ) ) return $excludes;
			if ( !is_array( $excludes ) ) $excludes = array();
		
			foreach ( $this->page_excludes as $yank_exclude ) {
				if ( !in_array( $yank_exclude, $excludes ) ) array_push( $excludes, $yank_exclude );
			}
		
			return $excludes;
		}        
        
	}
	
}

?>