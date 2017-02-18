<?php

/**
 * Fill in the path to the WordPress src checkout.
 */
define( 'WP_DIR', '/Users/me/svn/wordpress-dev/trunk/src' );

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/includes/class-unit-tests.php';

spl_autoload_register(function ($class_name) {
    $class_map = array(
        'SQLParserMatch'     => __DIR__ . '/includes/class-sql-parser.php',
        'WP_REST_Controller' => WP_DIR . '/wp-includes/rest-api/endpoints/class-wp-rest-controller.php',
        'WP_Error'           => WP_DIR . '/wp-includes/class-wp-error.php',
        'WP_HTTP_Response'   => WP_DIR . '/wp-includes/class-wp-http-response.php',
    );
    if (isset($class_map[$class_name])) {
        require $class_map[$class_name];
    } elseif (0 === stripos($class_name, 'WP_REST')) {
        $file_name = str_replace('_', '-', strtolower($class_name)) . '.php';
        require WP_DIR . '/wp-includes/rest-api/class-' . $file_name;
    }
});
