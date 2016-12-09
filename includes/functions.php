<?php
/**
 * detects plugin network install
 *
 * @global string $plugin_basename
 *
 * @return boolean false if not multisite or single-blog install
 */
function clgs_is_network_mode() {
    global $plugin_basename;

    if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
        require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
    }

    return ( is_multisite() && is_plugin_active_for_network( $plugin_basename ) );
}

/**
 * retrieves settings option from DB or defaults if they do not exist
 *
 * @return array settings data array
 */
function clgs_get_settings() {
    $settings_defaults = clgs_settings_defaults();
    
    $settings = get_site_option( CLGS_SETTINGS, array() );

    $args = wp_parse_args( $settings, $settings_defaults ); // needed?
    return $args;
}

/**
 * sanitizes and validates plugin-external arguments
 *
 * @global Clgs_DB $clgs_db
 * @global array $severity_list
 *
 * @param array $values arguments to test in the format array(
 *      'name' => $value
 * )
 * @param array $config test rules in the format array(
 *      'name' => array(
 *          'sanitize' => string named santitation action or
 *          'sanitize_function' => function a custom value transformation function,
 *          'validate' => string named validation rule or
 *          'validate_array' => array list of valid values,
 *          'default' => mixed optional default value
 *     )
 * )
 * @param string $action form of return in case of validation errrors:
 *     'block':   return false on first error
 *     'hold':    return argument name for first error
 *     'default': return null as argument value or default if one was supplied
 *
 * @return mixed false, name of first erroneous argument or array of sane values
 */
function clgs_evaluate( $values, $config, $action = 'default' ) {
    global $clgs_db, $severity_list, $allowedtags;

    $sanitized = array();
    foreach ( $config as $key => $rule ) {
        if ( !isset(  $values[$key] ) ) {
            $sane = null;
        } else if ( isset( $rule['sanitize_function'] ) ) {
            $sane = call_user_func( $rule['sanitize_function'], $values[$key], $key );
        } else switch ( $rule['sanitize'] ) {
        case 'string':
            $sane = esc_attr( $values[$key] );
            break;
        case 'kses_string':
			$tags = $allowedtags;
			$tags['br'] = array();
            $sane = wp_kses( (string)$values[$key], $tags );
            break;
        case 'toupper_string':
            $sane = strtoupper( esc_attr( $values[$key] ) );
            break;
        case 'int':
            $sane = (int)esc_attr( $values[$key] );
            break;
        case 'bool':
            $sane = (bool)esc_attr( $values[$key] );
            break;
        case 'time':
            if ( in_array( gettype( $values[$key] ), ['integer', 'double'] ) ) {
            $sane = (int)$values[$key];
            } else {
                $sane = strtotime( (string)$values[$key] );
            }
            break;
        }

        if ( isset( $rule['validate_array'] ) ) {
            $passed = in_array( $sane, $rule['validate_array'] );
        } else switch ( $rule['validate'] ) {
        case 'exists':
            $passed = !is_null( $sane );
            break;
        case 'sanitation':
            $passed = ( $sane !== false );
            break;
        case 'severity':
            $passed = array_key_exists( $sane, $severity_list );
            break;
        case 'role':
            $passed = !is_null( $sane ) && array_reduce( $sane, function( $carry, $entry ) {
                return $carry && wp_roles()->is_role( $entry );
            }, true );
            break;
        case 'registered':
            $passed = $clgs_db->is_registered( $sane );
            break;
        case 'positive':
            $passed = ( $sane > 0 );
            break;
        }

        if ( $passed ) {
            $sanitized[$key] = $sane;
        } elseif ( 'block' == $action ) {
            return false;
        } else if ( 'hold' == $action ) {
            return $key;
        } else if ( isset( $rule['default'] ) ) {
            $sanitized[$key] = $rule['default'];
        }
    }

    return $sanitized;
}

/**
 * helper santation function: normalize to array
 *
 * @param mixed $value input argument
 * @param mixed $key argument name
 *
 * @return mixed array of data or null if uninterpretable or no entries
 */
function clgs_to_array ( $value, $key ) {
    if ( 'array' == gettype( $value ) ) {
        $sane = $value;
    } elseif ( 'string' == gettype( $value ) ) {
            $sane = explode( ',', esc_attr( $value ) );
    } else {
        return null;
    }

    if ( count( $sane ) ) {
        foreach ( $sane as &$entry ) {
            $entry = esc_attr( $entry );
            if ( 'entries' == $key ){
                $entry = (int)$entry;
            }
        }
    } else {
        return null;
    }

    return $sane;
}

/**
 * helper santation function: normalize to user display name
 *
 * @param mixed $value input argument
 * @param mixed $key argument name
 *
 * @return mixed user display name or null if uninterpretable
 */
function clgs_to_user ( $value, $key ) {
    switch ( gettype( $value ) ) {
    case 'integer':
        $value = get_user_by( 'id', $value );
        break;
    case 'string':
        $value = get_user_by( 'login', $value ) || get_user_by( 'slug', $value );
        break;
    }

    return is_a( $value, 'WP_User' ) ? $value->display_name : '';
}
