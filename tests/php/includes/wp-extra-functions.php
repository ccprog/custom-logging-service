<?php
if ( ! function_exists( 'plugin_dir_path' ) ) {
    function plugin_dir_path ($file) {
        return dirname($file) . '/';
    }
}

if ( ! function_exists( 'plugin_basename' ) ) {
    function plugin_basename () {
        return 'custom-logging-service';
    }
}

if ( ! function_exists( 'register_activation_hook' ) ) {
    function register_activation_hook ($file, $function) {
        $file = plugin_basename($file);
        add_action('activate_' . $file, $function);
    }
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
    function register_deactivation_hook ($file, $function) {
        $file = plugin_basename($file);
        add_action('deactivate_' . $file, $function);
    }
}

if ( ! function_exists( 'register_uninstall_hook' ) ) {
    function register_uninstall_hook ($file, $function) {
        //empty stub
    }
}

if ( ! function_exists( '__' ) ) {
    function __ ($string) {
        return $string;
    }
}

if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__ ($string) {
        return $string;
    }
}

if ( ! function_exists( '_e' ) ) {
    function _e ($string) {
        echo $string;
    }
}
