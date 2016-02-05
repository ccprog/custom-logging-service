<?php
/*
Plugin Name: Custom Logging Service
Plugin URI: https://github.com/ccprog/custom-logging-service
Version: 1.0.0
Author: Claus Colloseus
Author URI: http://browser-unplugged.net
Text Domain: custom-logging-service
Domain Path: /languages
Description: Provides a simple API for storing miscellaneous log entries and displays them in a Dashboard subpage.
License: GPL2

Copyright Claus Colloseus 2016
Based on  https://wordpress.org/plugins/wordpress-logging-service/
Copyright 2011-2013 Zaantar (email: zaantar@zaantar.eu)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define( 'CLGS_VERSION', '1.0.0' );

define( 'CLGS_SETTINGS', 'clgs_settings' );
define( 'CLGS_LOG_PAGE', 'clgs_manager' );
define( 'CLGS_OPTION_PAGE', 'clgs_options' );
define( 'CLGS_GROUP', 'clgs_group' );
define( 'CLGS_CAP', 'clgs_manage' );

define( 'CLGS_NOSEVERITY', 0 );
define( 'CLGS_INFO', 1 );
define( 'CLGS_NOTICE', 2 );
define( 'CLGS_WARNING', 3 );
define( 'CLGS_ERROR', 4 );
define( 'CLGS_FATALERROR', 5 );

$severity_list = [
    CLGS_NOSEVERITY => 'none',
    CLGS_INFO => 'debug',
    CLGS_NOTICE => 'notice',
    CLGS_WARNING => 'warning',
    CLGS_ERROR => 'error',
    CLGS_FATALERROR => 'fatal'
];

global $clgs_db;

if ( ! function_exists( 'wp_roles' ) ) {
    function wp_roles() {
        global $wp_roles;
    
        if ( ! isset( $wp_roles ) ) {
            $wp_roles = new WP_Roles();
        }
        return $wp_roles;
    }
}

require_once plugin_dir_path( __FILE__ ).'includes/functions.php';

require_once plugin_dir_path( __FILE__ ).'includes/class-database.php';
$clgs_db = new Clgs_DB();

require_once plugin_dir_path( __FILE__ ).'includes/class-last-log.php';
$clgs_last_log = new Clgs_last_log();

require_once plugin_dir_path( __FILE__ ).'includes/settings.php';
require_once plugin_dir_path( __FILE__ ).'includes/manager.php';
require_once plugin_dir_path( __FILE__ ).'includes/class-manager-table.php';

$plugin_basename = plugin_basename( __FILE__ );

/*
 * (De)Installation and (De)Activation
 */

function clgs_activation () {
    global $clgs_db;

    if ( version_compare( PHP_VERSION, '5.3', '<' ) ) {
        add_action( 'admin_notices', create_function( '', "echo '<div class=\"error\"><p>This plugin requires at least version 5.3 of PHP. Please contact your server administrator before you activate this plugin.</p></div>';" ) );
        trigger_error( 'Plugin could not be activated.', E_USER_ERROR );
    }

    $clgs_db->create();
    update_option( 'clgs_version', CLGS_VERSION );
}
register_activation_hook( __FILE__, 'clgs_activation' );

function clgs_update () {
    $old_version = get_option( 'clgs_version' );
    if ( version_compare( CLGS_VERSION, $old_version , '<' ) ) {
        // placeholder
        update_option( 'clgs_version', CLGS_VERSION );
    }
}
add_action( 'plugins_loaded', 'clgs_update' );

function clgs_deactivation () {
    global $clgs_last_log, $wp_roles;

    foreach ( $wp_roles->role_objects as $name => $role ) {
        $role->remove_cap( CLGS_CAP );
    }
    $clgs_last_log->flush();
}
register_deactivation_hook( __FILE__, 'clgs_deactivation' );

function clgs_uninstall () {
    global $clgs_db;
    
    delete_option( 'clgs_version' );
    delete_option( CLGS_SETTINGS );
    delete_site_option( CLGS_SETTINGS );
    delete_site_option( Clgs_last_log::OPTION );
    $clgs_db->destroy();
}
register_uninstall_hook( __FILE__, 'clgs_uninstall' );

/*
 * Loading
 */

function clgs_load_textdomain () {
	$plugin_dir = basename( dirname(__FILE__) );
	load_plugin_textdomain( 'custom-logging-service', false, $plugin_dir.'/languages' );
}
add_action( 'plugins_loaded', 'clgs_load_textdomain' );

function clgs_admin_enqueue_styles() {
	if( isset( $_REQUEST["page"] ) && CLGS_LOG_PAGE == $_REQUEST["page"] ) {
		wp_enqueue_style( "clgs-admin-style", plugins_url( "style.css", __FILE__ ) );
        wp_enqueue_script( "clgs-manager-script",
            plugins_url( "includes/manager.js", __FILE__ ), ['jquery'], false, true );
	}
}
add_action( "admin_enqueue_scripts", "clgs_admin_enqueue_styles" );

function clgs_admin_menu () {
    $option_heading = __( 'Custom Logging Service', 'custom-logging-service' );
    $log_heading = __( 'Application logs', 'custom-logging-service' );
    $option_page = 'options-general.php';
    $option_cap =  'manage_options';

	if ( clgs_is_network_mode() ) {
        $option_page = 'settings.php';
        $option_cap =  'manage_network_options';
    }

	add_dashboard_page( $log_heading, $log_heading . clgs_unseen_field(),
		CLGS_CAP, CLGS_LOG_PAGE, 'clgs_manager_page' );
    add_submenu_page( $option_page, $option_heading, $option_heading,
        $option_cap, CLGS_OPTION_PAGE, 'clgs_settings_page' );
}
add_action( 'network_admin_menu', 'clgs_admin_menu' );
add_action( 'admin_menu', 'clgs_admin_menu' );

/*
 * Public API
 */

define( 'CLGS', true );

function clgs_is_registered ( $category ) {
    global $clgs_db;

	return $clgs_db->is_registered( $category );
}

function clgs_register ( $category, $description ) {
    global $clgs_db;

    $description = wp_kses_data( $description );
    return !$clgs_db->is_registered( $category ) &&
        $clgs_db->category_fits( $category ) &&
        $clgs_db->register( $category, $description );
}

function clgs_unregister ( $category ) {
    global $clgs_db;

	return $clgs_db->bulk_category( 'unregister', $category );
}

function clgs_clear ( $category ) {
    global $clgs_db;

	return $clgs_db->bulk_category( 'clear', $category );
}

function clgs_log ( $category, $text, $severity = null, $user = null, $blog_id = null, $date = null ) {
    global $clgs_db, $clgs_last_log;

    // category must pass validation
    if ( is_null ( $date) ) {
        $date = time();
    }
    $rules = array (
        'category' => array( 'sanitize' => 'string', 'validate' => 'registered' ),
        'text' => array( 'sanitize' => 'kses_string', 'validate' => 'length' ),
        'date' => array( 'sanitize' => 'time', 'validate' => 'sanitation' )
    );
    $must = clgs_evaluate( compact( 'category', 'text', 'date' ), $rules, 'block' );
    if ( !$must ) {
        return false;
    }

    // all others have a default
    $rules = array (
        'user' => array(
            'sanitize_function' => 'clgs_to_user',
            'validate' => 'exists',
            'default' => is_user_logged_in() ? wp_get_current_user()->display_name : ' &mdash; '
        ),
        'blog_id' => array(
            'sanitize' => 'int',
            'validate' => 'positive',
            'default' => get_current_blog_id()
        ),
        'severity' => array(
            'sanitize' => 'int',
            'validate' => 'severity',
            'default' => CLGS_NOSEVERITY
        )
    );
    $sane = clgs_evaluate( compact( 'date', 'user', 'blog_id', 'severity' ), $rules );

	// get blog name
	if( clgs_is_network_mode() ) {
		switch_to_blog( $sane['blog_id'] );
		$blog_name = get_bloginfo( 'name' );
		restore_current_blog();
	} else {
		$blog_name = get_bloginfo( 'name' );
	}

    $data = array(
	    'category' =>$must['category'],
	    'blog_id' => $sane['blog_id'],
	    'blog_name' => $blog_name,
	    'date' => $must['date'],
	    'user_name' => $sane['user'],
	    'text' => $must['text'],
	    'severity' => $sane['severity']
    );

    if( $clgs_last_log->compare( $data ) ) {
        $clgs_last_log->write();

        $first =  $clgs_last_log->data['date'];
        $data['text'] = '(' . $clgs_last_log->count . '× ' .
            __('since ', 'custom-logging-service' ) . 
            '<span data-date="'. $first .'"></span>):<br/>' . $data['text'];

        $ok = (bool)$clgs_db->update_entry( $clgs_last_log->entry_id, $data );
    } else {
	    $entry_id = $clgs_db->insert_entry( $data );
        $ok = (bool)$entry_id;
        if ( $ok ) {
            $clgs_last_log->set( $data, $entry_id );
        }
    }
    return $ok;
}
