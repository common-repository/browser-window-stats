<?php
/*
Plugin Name: Browser Window Stats
Plugin URI: 
Description: Runs javascript at each page load to measure browser window size and send it back to server.
Version: 1.1
Author: Tom Fletcher
Text Domain: browser-window-stats

Copyright 2011  Tom Fletcher  (email : tf.hartle@gmail.com)

Released under the GPLv3 license
http://www.gnu.org/licenses/gpl.html

**********************************************************************
This program is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
**********************************************************************
*/

// Disallow direct access to the plugin file
if (basename($_SERVER['PHP_SELF']) == basename (__FILE__)) {
	die( __('Sorry, but you cannot access this page directly.', 'browser-window-stats') );
}

// Make plugin WP symlink compatible - for development
$my_plugin_file = __FILE__;

if (isset($plugin)) {
	$my_plugin_file = $plugin;
}
else if (isset($mu_plugin)) {
	$my_plugin_file = $mu_plugin;
}
else if (isset($network_plugin)) {
	$my_plugin_file = $network_plugin;
}

define('BWS_PLUGIN_FILE', $my_plugin_file);
define('BWS_PLUGIN_PATH', WP_PLUGIN_DIR.'/'.basename(dirname($my_plugin_file)));

// Make it translatable
function bws_i18n() {
	load_plugin_textdomain( 'browser-window-stats', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'init', 'bws_i18n' );

// SET UP DATABASE FOR PLUGIN
global $wpdb, $bws_db_version, $bws_table_name;
$bws_db_version = 1.0; // saved in wordpress options, to be used for database upgrades.
$bws_table_name = $wpdb->prefix. 'browser_window_stats';

// include dashboard page
include_once( 'dashboard.php' );

/* Create new table for storing data when plugin activated
* 
* stores timestamp, browser window width and height, screen width and height
* and if user logged in or not
*/
function bws_install() {
	global $wpdb, $bws_db_version, $bws_table_name;
	
	if($wpdb->get_var("SHOW TABLES LIKE '$bws_table_name'") != $bws_table_name) {
		$sql = "CREATE TABLE $bws_table_name (".
			"id INT NOT NULL AUTO_INCREMENT, ".
			"timestamp DATETIME NOT NULL, ".
			"browser_width LONGTEXT NOT NULL, ".
			"browser_height LONGTEXT NOT NULL, ".
			"screen_width INT NOT NULL, ".
			"screen_height INT NOT NULL, ".
			"registered_user BOOLEAN NOT NULL DEFAULT 0, ".
			"UNIQUE KEY id (id) ".
			")";
		require_once( ABSPATH.'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		
		add_option('SAD-db-version', $tsc_SAD_db_version);
	}
}
register_activation_hook(__FILE__, 'bws_install');


/* Register and enqueue javascripts and variables
* 
* loads jQuery, custom js with variables attached.
*/
function bws_scripts() {
	
	if( $_SERVER['HTTP_X_PURPOSE'] != 'preview' ) { // check for safari top sites
		$js_vars = array( 
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'browser_window_stats_ajax_security' )
			);
		wp_enqueue_script( 'bws', plugins_url( 'bws.js', BWS_PLUGIN_FILE ), array( 'jquery' ) );
		wp_localize_script( 'bws', 'bws', $js_vars );
	}
}
add_action( 'wp_print_scripts', 'bws_scripts' );


/* start a session if one has already been started
*/
function bws_session() {
	if( ! session_id() ) {
		session_name( 'bws_session' );
		session_start();
		
		if( $_SESSION['last_activity']+15*60 < time() ) {
			// Unset all of the session variables after 15 mins inactivity
			$_SESSION = array();
		}
	}
}
add_action( 'init', 'bws_session' );


/* Receive, parse and save data from javascript
*/
function bws_save() {
	check_ajax_referer( 'browser_window_stats_ajax_security', 'security' );
	
	// update timeout to current time
	$_SESSION['last_activity'] = time();

	$form_data = filter_input_array( INPUT_POST, FILTER_VALIDATE_INT );
	
	// Shortcode atts gives 2nd layer of validation
	// removes all other keys from array, provides false as default value
	// action and security keys no longer needed
	$form_data_defaults = array(
		'browser_width' => false,
		'browser_height' => false,
		'screen_width' => false,
		'screen_height' => false
		);
	$form_data = shortcode_atts( $form_data_defaults, $form_data );
	
	// stop scipt if not getting expected data
	if( !$form_data || in_array( false, $form_data ) ) {
		exit;
	}
	
	$_SESSION['browser_w_arr'][] = $form_data['browser_width'];
	$_SESSION['browser_h_arr'][] = $form_data['browser_height'];
	if( !isset($_SESSION['browser_save']) ) {
		// it's a new session - start counter for updating browser stats
		$_SESSION['browser_save'] = time();
	}
	
	
	// determine if user has logged in during session
	$new_login = false;
	if( isset( $_SESSION['logged_in'] ) // session has been started ...
	&& !$_SESSION['logged_in'] // user was not logged in ...
	&& is_user_logged_in() ) { // but is now
		$new_login = true;
	}
	
			
	$_SESSION['logged_in'] = is_user_logged_in();
	$timestamp = date('Y-m-d H:i:s');
	
	$save_data['timestamp'] = $timestamp;
	$save_data['browser_width'] = serialize( $_SESSION['browser_w_arr'] );
	$save_data['browser_height'] = serialize( $_SESSION['browser_h_arr'] );
	$save_data['screen_width'] = $form_data['screen_width'];
	$save_data['screen_height'] = $form_data['screen_height'];
	$save_data['registered_user'] = $_SESSION['logged_in'];
	
	global $wpdb, $bws_table_name;
		
	if( !isset($_SESSION['bws_id']) ) { // it's a shiny new session, make a new row
		$wpdb->insert($bws_table_name, $save_data);
		$_SESSION['bws_id'] = $wpdb->insert_id;
	} else if( $new_login ) { // someones logged in, update registered user as well as browser resolution array
		$wpdb->update( $bws_table_name, array('registered_user' => $save_data['logged_in'], 'browser_width' => $save_data['browser_width'], 'browser_height' => $save_data['browser_height'] ), array('id' => $_SESSION['bws_id']) );
	} else { // just save the browser resolution array
		$wpdb->update( $bws_table_name, array('browser_width' => $save_data['browser_width'], 'browser_height' => $save_data['browser_height'] ), array('id' => $_SESSION['bws_id']) );	
	}
	exit;
}
add_action( 'wp_ajax_nopriv_bws-save', 'bws_save');
add_action( 'wp_ajax_bws-save', 'bws_save');

function bws_update_browser_res() {
	global $wpdb, $bws_table_name;
	
	return $wpdb->update( $bws_table_name, array('registered_user' => $_SESSION['logged_in'], 'browser_width' => $_SESSION['browser_w_arr'], 'browser_height' => $_SESSION['browser_h_arr'] ), array('id' => $_SESSION['bws_id']) );
}
?>