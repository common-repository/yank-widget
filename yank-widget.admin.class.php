<?php

if ( class_exists( 'yank_widget_core' ) ) {
    
    class yank_widget_admin extends yank_widget_core {

        var $ui_url;
        var $ui_form_url;
        var $nonce;
        
		function init_ui() {
			if ( !$this->is_installed ) $this->ui = 'options';
			$this->ui_url = $_SERVER['PHP_SELF'] . '?page=' . $_REQUEST['page'];
			$this->ui_form_url = $_SERVER['PHP_SELF'] . '?page=' . $_REQUEST['page'];
			$this->nonce = $this->nice_system_name . '-update-key';
		}

		function js_uninstall_confirm() {

			$js = "\n/* <![CDATA[ */\n";
			$js .= "function yank_widget_uninstall_confirm() { \n";
			$js .= "return confirm( 'Delete Yank Widget options and database tables?' );\n";
			$js .= "}\n";
			$js .= "/* ]]> */\n";
			
			$this->html_tag( array(
				'tag' => 'script',
				'type' => 'text/javascript',
				'content' => $js
			) );		
		}
		
		function css() {

			$js = "\n/* <![CDATA[ */\n";
			$js .= "div#yank_widget_deactivate { \n";
			$js .= " margin: .5em 1em;\n";
			$js .= " padding: .5em;\n";
			$js .= " background: #FFFF66;\n";
			$js .= "}\n";
			$js .= "/* ]]> */\n";
			
			$this->html_tag( array(
				'tag' => 'style',
				'type' => 'text/css',
				'content' => $js
			) );		
		}		
		
		function install() {  
			global $wpdb;
						
			if( !$this->is_installed ) {
			
				if ( $wpdb->supports_collation() ) {
					if ( ! empty($wpdb->charset) )
						$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
					if ( ! empty($wpdb->collate) )
						$charset_collate .= " COLLATE $wpdb->collate";
				}
				
				//Creating the table
				$sql = "CREATE TABLE $this->db_table (
					id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					post_id bigint(20) unsigned NOT NULL,
					yanked_id bigint(20) unsigned NOT NULL,
					widget_id bigint(20) unsigned NOT NULL, 
					PRIMARY KEY  (id)
				) $charset_collate;";
							
				$results = $wpdb->query( $sql );

				add_option( $this->nice_name . '_excludes', $this->excludes );
				add_option( $this->nice_name . '_widgets', $this->widgets );
				add_option( $this->nice_name . '_options', $this->options );

			}			
			
		}

		/**
		* Uninstall plugin
		*
		* Function used when to clear settings.
		*
		*/
		function uninstall() {
			global $wpdb;
			
			$wpdb->query('DROP TABLE IF EXISTS ' . $this->db_table );
			delete_option( $this->nice_name . '_excludes' );
			delete_option( $this->nice_name . '_widgets' );
			delete_option( $this->nice_name . '_options' );
			$this->is_installed = false;
		}

		/**
		* Options 
		*
		* Function used to set options from form.
		*
		*/
		function do_options() {
			global $wpdb;
			
			$excludes = explode( ',', $wpdb->escape( $_REQUEST['yank_widget_excludes'] ) );
			update_option( $this->nice_name . '_excludes', $excludes );
        	$this->page_excludes = get_option( $this->nice_name . '_excludes' );
		}
		
		/**
		* Remove Yanked Content by Yank Widget ID
		*
		* Function used to clear database of yanked content by widget id when widget is removed.
		*
		*/
		function remove_yank_widget( $id = 0 ) {
			global $wpdb;
			
			$result = $wpdb->get_row("
				SELECT * FROM $this->db_table 
				WHERE widget_id=$id
			",ARRAY_N);
			if( count( $result ) != 0 ) $result = $wpdb->query("
				DELETE FROM $this->db_table 
				WHERE widget_id=$id
			");			

		}
		
		function manage_yank_widgets( $widgets = array(), $post_ID = 0 ) {
			global $wpdb;
			
			if ( function_exists( 'wp_is_post_revision' ) ) {
				if ( wp_is_post_revision( $post_ID ) != false ) return $post_ID;
			}
			
			foreach( $widgets as $widget ) {
					
				$widget_number = $widget['yank_widget'];

				if ( $widget['widget_yanked_id_exclude'] != 'default' ) {
					
					if ( !is_array( $this->page_excludes ) ) $this->page_excludes = array();
				
					if ( $widget['widget_yanked_id_exclude'] == 'yes' ) {
						if ( !in_array( $widget['widget_yanked_id'], $this->page_excludes ) ) array_push( $this->page_excludes, $widget['widget_yanked_id'] );
						update_option( 'yank_widget_excludes', $this->page_excludes );
					} elseif ( $widget['widget_yanked_id_exclude'] == 'no' ) {
						$find_exclude = array_search( $widget['widget_yanked_id'], $this->page_excludes );
						unset( $this->page_excludes[ $find_exclude ] );
						update_option( 'yank_widget_excludes', $this->page_excludes );
					}
				}
					
				if( $widget['widget_yanked_id'] == 'delete' ) {
					$result = $wpdb->get_row("
						SELECT * FROM $this->db_table 
						WHERE post_id=$post_ID  AND widget_id=$widget_number
					",ARRAY_N);
					if( count( $result ) != 0 ) $result = $wpdb->query("
						DELETE FROM $this->db_table 
						WHERE post_id=$post_ID AND widget_id=$widget_number
						LIMIT 1
					");
			
					continue;
				}
				
				if( $widget['widget_yanked_id'] == 'current' || $widget['widget_yanked_id'] == '' ) {
					continue;
				}
				
				$result = $wpdb->get_row("
					SELECT * FROM $this->db_table
					WHERE post_id=$post_ID AND widget_id=$widget_number
				",ARRAY_N);
				
				if( count( $result ) == 0 ) {
					$result = $wpdb->query("
						INSERT INTO $this->db_table
						(post_id, yanked_id, widget_id ) 
						VALUES ($post_ID, {$widget['widget_yanked_id']}, $widget_number)
					");
								
				} elseif( count( $result ) > 0 ) {
					$result = $wpdb->query("
						UPDATE $this->db_table 
						SET yanked_id={$widget['widget_yanked_id']}
						WHERE post_id=$post_ID AND widget_id=$widget_number
						");
				}
			
			}
		}

		/**
		* Display html tag with attributes
		* @param array $html_options options and content to display
		*/
		
		/*
			$format = 'The %2$s contains %1$d monkeys.
					   That\'s a nice %2$s full of %1$d monkeys.';
			printf($format, $num, $location);
			
			echo "var is ".($var < 0 ? "negative" : "positive"); 
		*/
		function html_tag( $html_options = array() ) {

			$attributes = '';
			$composite = '';
			
			foreach ( $html_options as $name => $option ) {
				if ( $name == 'tag' ) continue;
				if ( $name == 'content' ) continue;
				if ( $name == 'return' ) continue;
				if ( $name == 'tag_type' ) continue;
				$html_attributes .= sprintf( ' %s="%s"', $name, $option );
			}
			
			switch ( $html_options['tag_type'] ) {
				case 'single':
					$format = '%3$s <%1$s%2$s />' ;
					break;
				case 'open':
					$format = '<%1$s%2$s>%3$s';
					break;
				case 'close':
					$format = '%3$s</%1$s>';
					break;
				default:
					$format = '<%1$s%2$s>%3$s</%1$s>';
					break;
			}
				
			$composite = sprintf( $format, $html_options['tag'], $html_attributes, $html_options['content'] );
			
			if ( $html_options['return'] == true ) return $composite ;
			
			echo $composite;
		}

		/**
		* WP Super Edit admin nonce field generator for form security
		* @param string $action nonce action to make keys
		*/		
		function nonce_field($action = -1) { 
			return wp_nonce_field( $action, "_wpnonce", true , false );
		}
		
		/**
		* Admin display header and information
		* @param string $text text to display
		*/
		function ui_header() {
		
			$this->html_tag( array(
				'tag' => 'div',
				'tag_type' => 'open',
				'class' => 'wrap',
			) );
			
			$this->html_tag( array(
				'tag' => 'h2',
				'content' => 'Yank Widget Options',
			) );
						
		}

		/**
		* Admin display footer
		* @param string $text text to display
		*/
		function ui_footer() {
			$this->html_tag( array(
				'tag' => 'div',
				'tag_type' => 'close',
			) );
			$this->html_tag( array(
				'tag' => 'div',
				'id' => 'wp_super_edit_null',
			) );
		}
		
		/**
		* Start admin form
		* @param string $text text to display
		*/
		function form( $action = '', $content = '', $return = false ) {
			global $wp_super_edit_nonce;
			
			$form_contents = $this->nonce_field('yank_widget_nonce-' . $this->nonce);
			
			$form_contents .= $this->html_tag( array(
				'tag' => 'input',
				'tag_type' => 'single',
				'type' => 'hidden',
				'name' => 'yank_widget_action',
				'value' => $action,
				'return' => true
			) );
			
			$form_contents .= $content;
			
			$form_array =  array(
				'tag' => 'form',
				'id' => 'yank_widget_controller',
				'enctype' => 'application/x-www-form-urlencoded',
				'action' => htmlentities( $this->ui_form_url ),
				'method' => 'post',
				'content' => $form_contents,
				'return' => $return
			);
			
			if ( $return == true ) return $this->html_tag( $form_array );
			
			$this->html_tag( $form_array );
			
		}

		/**
		* Form Table
		* @param string $text text to display
		*/
		function form_table( $content = '', $return = false ) {
			
			$content_array = array(
				'tag' => 'table',
				'class' => 'form-table',
				'content' => $content,
				'return' => $return
			);
			
			if ( $return == true ) return $this->html_tag( $content_array );
			
			$this->html_tag( $content_array );			
		}

		/**
		* Form Table Row
		* @param string $text text to display
		*/
		function form_table_row( $header = '', $content = '', $return = false ) {
			
			$row_content = $this->html_tag( array(
				'tag' => 'th',
				'scope' => 'row',
				'content' => $header,
				'return' => true
			) );
			
			$row_content .= $this->html_tag( array(
				'tag' => 'td',
				'content' => $content,
				'return' => true
			) );
			
			$content_array = array(
				'tag' => 'tr',
				'valign' => 'top',
				'content' => $row_content,
				'return' => $return
			);
			
			if ( $return == true ) return $this->html_tag( $content_array );
			
			$this->html_tag( $content_array );
		}

		/**
		* Yank Dropdown list
		*
		* Helper function used to display dropdown select list in edit form
		*
		*/
		function yank_dropdown( $default = 0, $parent = 0, $level = 0 ) {
			global $wpdb, $post_ID;
						
			$items = $wpdb->get_results( "
				SELECT ID, post_parent, post_title FROM $wpdb->posts 
				WHERE post_parent = $parent AND post_type = 'page' 
				ORDER BY menu_order
			" );
		
			if ( $items ) {
				foreach ( $items as $item ) {
		
					$exclude_mark = '';
					$pad = str_repeat( '&nbsp;', $level * 3 );
		
					$select_html = array(
						'tag' => 'option'
					);
					
					if( is_array( $this->page_excludes ) ) {
						if ( in_array( $item->ID, $this->page_excludes ) ) $exclude_mark = ' [hidden]';
					}
		
					if ( $item->ID == $default) {
						$select_html['selected'] = 'selected';
						$current_title = ' [selected]';
					} else {
						$current = '';
						$current_title = '';
					}
						
					if ( $item->ID == $post_ID ) {
						$yank_id = 'current';
						$yank_title = "$pad $item->post_title [current page]";
					} else {
						$yank_id = $item->ID;
						$yank_title = "$pad $item->post_title";
					}
					
					$select_html['value'] =  $yank_id;
					$select_html['content'] =  $yank_title . $current_title . $exclude_mark;
					
					$this->html_tag( $select_html );

					$this->yank_dropdown( $default, $item->ID, $level +1 );
				}
			} else {
				return false;
			}
		}

		/**
		* Form Select
		* @param string $text text to display
		*/
		function form_select( $option_name = '', $options = array(), $return = false ) {
			
			foreach( $options as $option_value => $option_text ) {
				$option_array = array(
					'tag' => 'option',
					'value' => $option_value,
					'content' => $option_text,
					'return' => true
				);			
				
				if ( $option_value == $this->management_mode ) $option_array['selected'] = 'selected';
				
				$option_content .= $this->html_tag( $option_array );
			}
			
			$content_array = array(
				'tag' => 'select',
				'name' => $option_name,
				'id' => $option_name,
				'content' => $option_content,
				'return' => $return
			);
			
			if ( $return == true ) return $this->html_tag( $content_array );
			
			$this->html_tag( $content_array );
		}
		/**
		* Display submit button
		* @param string $button_text button value
		* @param string $message description text
		*/
		function submit_button( $button_text = 'Update Options &raquo;', $message = '', $return = false, $onclick = '' ) {
			$content_array = array(
				'tag' => 'input',
				'tag_type' => 'single',
				'type' => 'submit',
				'name' => $this->nice_name. '_submit',
				'id' => $this->nice_name. '_submit_id',
				'class' => 'button',
				'value' => $button_text,
				'content' => $message,
				'return' => $return,
			);
			
			if ( $onclick != '' ) $content_array['onClick'] = $onclick;

			if ( $return == true ) return $this->html_tag( $content_array );
			
			$this->html_tag( $content_array );
		}

		/**
		* Yank Managment Form
		*
		* Function used to display management form on content editing areas in dashboard.
		*
		*/
		function yank_management() {	
			global $post, $wpdb;
		   
			$post_id = $post;
			
			$yank_widgets = get_option("yank_widget_widgets" );
		
			if (is_object($post_id)) {
				$post_id = $post_id->ID;
			} 
				
			$class_number = 1;
			foreach( $this->widgets as $widget_number => $widget_option ) {
		
				$widget = $wpdb->get_row("
					SELECT * FROM $this->db_table
					WHERE post_id=$post_id and widget_id=$widget_number
				");
						
				if( is_object($widget) ) {
					$widget_number = $widget->widget_id;
					$widget_post = $widget->post_id;
					$widget_yanked_id = $widget->yanked_id;
				} else {
					$widget_number = $widget_number;
					$widget_post="";
					$widget_yanked_id="";
					$widget_yanked_id_exclude="";
				}
				
		?>
		
							<div class="yank_widgets">
							 
								<p>
								<strong>Yank Widget #<?php echo $class_number; ?></strong><br />
								</p>
								<input name="yank_widgets[<?php echo $widget_number; ?>][yank_widget]" type="hidden" value="<?php echo $widget_number; ?>">
								<label for="widget_yanked_id">Yank Content From:</label>
								<select name="yank_widgets[<?php echo $widget_number; ?>][widget_yanked_id]">
									<option value=''>- Select Page to Yank into Widget -</option>
									<?php $this->yank_dropdown( $widget_yanked_id );?>
									<option value='delete'>- Remove this page content from widget -</option>
								</select>
								<br />
								<label for="widget_yanked_id_exclude">Hide yanked page from page lists and menus?</label>
								<select name="yank_widgets[<?php echo $widget_number; ?>][widget_yanked_id_exclude]">
									<option value='default'>Default</option>
									<option value='no'>No</option>
									<option value='yes'>Yes</option>
								</select>
							</div>
				
		<?php
				$class_number++;
			}
		}
		/**
		* Create deactivation user interface
		* 
		*/
		function uninstall_ui() {
			$this->html_tag( array(
				'tag' => 'div',
				'tag_type' => 'open',
				'id' => $this->nice_name. '_deactivate'
			) );
						
			$button = $this->submit_button( 'Uninstall Yank Widget', '<strong>Click here to remove ALL Yank Widget database tables and settings. </strong>', true, 'return yank_widget_uninstall_confirm();' );

			$this->form( 'uninstall', $button );

			$this->html_tag( array(
				'tag' => 'div',
				'tag_type' => 'close'
			) );
			
		}
		
		/**
		* Yank Widget Options Interface
		* 
		*/
		function options_ui() {

        	if ( !$this->is_installed ) {
				$this->html_tag( array(
					'tag' => 'div',
					'id' => 'yank_widget_deactivate',
					'content' => '<strong>Yank Widget is not installed! Please deactivate and reactivate the Yank Widget plugin!</strong>'
				) );
				return;
        	}

			$this->html_tag( array(
				'tag' => 'div',
				'tag_type' => 'open',
				'id' => $this->nice_name . '_options'
			) );
			
			$submit_button = $this->submit_button( 'Update Options', '', true );
			$submit_button_group = $this->html_tag( array(
				'tag' => 'p',
				'class' => 'submit',
				'content' => $submit_button,
				'return' => true
			) );
			
			if( is_array( $this->page_excludes ) ) $options_excludes_value = implode( ',', $this->page_excludes );							
			
			$options_excludes = $this->html_tag( array(
				'tag' => 'input',
				'tag_type' => 'single-after',
				'type' => 'text',
				'name' => 'yank_widget_excludes',
				'value' => $options_excludes_value,
				'id' => 'yank_widget_excludes_id',
				'content' => '<br />Use Page ID numbers separated by commas!',
				'return' => true
			) );

			$table_row .= $this->form_table_row( 'Current pages excluded from page lists' , $options_excludes, true );

			$form_content .= $this->form_table( $table_row, true );
			$form_content .= $submit_button_group;
			
			$this->form( 'options', $form_content );

			$this->html_tag( array(
				'tag' => 'div',
				'tag_type' => 'close'
			) );
			
			$this->uninstall_ui();

		}
		
	}

}

?>