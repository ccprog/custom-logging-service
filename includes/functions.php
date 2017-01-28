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
 * @return array settings field array
 */
function clgs_get_settings() {
    $settings_defaults = clgs_settings_defaults();
    
    $settings = get_site_option( CLGS_SETTINGS, array() );

    $args = wp_parse_args( $settings, $settings_defaults ); // needed?
    return $args;
}

/**
 * Get schema for a log entry.
 *
 * @param WP_REST_Request $request Current request.
 * @return array JSON Schema of category data
 */
function clgs_get_item_schema () {
    global $severity_list;

    $properties = array(
        'id' => array(
            'title'  => __( 'Unique ID', 'custom-logging-service' ),
            'type'         => 'integer',
            'readonly'     => true,
            'info'         => array(
                'column'      => true,
            )
        ),
        'message' =>  array(
            'title'        => __( 'Message', 'custom-logging-service' ),
            'type'         => 'string',
            'required'     => true,
            'info'         => array(
                'column'      => true,
                'primary'     => true
            )
        ),
        'severity' => array(
            'title'        => __( 'Severity', 'custom-logging-service' ),
            'type'         => 'string',
            'required'     => true,
            'enum'         => array_values( $severity_list ),
            'info'         => array(
                'column'      => true,
                'target'      => 'severity',
                'desc_first'  => false
            )
        ),
        'category' => array(
            'title'        => __( 'Log category', 'custom-logging-service' ),
            'type'         => 'string',
            'required'     => true,
            'maxLength'    => 190,
            'info'         => array(
                'column'      => true,
                'target'      => 'category',
                'desc_first'  => false
            )
        ),
        'date' => array(
            'title'        => __( 'Time', 'custom-logging-service' ),
            'description'  => __( 'Empty <span> with UNIX timestamp as "data-date" attribute.', 'custom-logging-service' ),
            'type'         => 'string',
            'required'     => true,
            'info'         => array(
                'column'      => true,
                'target'      => 'date',
                'desc_first'  => true
            )
        ),
        'seen' => array (
            'description' => __( 'Old entry', 'custom-logging-service' ),
            'type'       => 'boolean',
            'default'    => false,
            'info'         => array(
                'column'      => false,
            )
        ),
        'user' => array(
            'title'        => __( 'User', 'custom-logging-service' ),
            'description'  => __( 'Login name of the user, preceded by its gravatar.' ),
            'type'         => 'string',
            'required'     => true,
            'info'         => array(
                'column'      => true,
                'target'      => 'user_name',
                'desc_first'  => false
            )
        ),
        'avatar' => array(
            'description'  => __( 'Gravatar <img> tag of the user.' ),
            'type'         => 'string',
            'required'     => true,
            'info'         => array(
                'column'      => false,
            )
        ),
    );

    if ( clgs_is_network_mode() && is_main_site() ) {
        $properties['blog'] = array(
            'title'        => __( 'Blog', 'custom-logging-service' ),
            'description'  => __( 'Link to blog', 'custom-logging-service' ),
            'type'         => 'string',
            'required'     => true,
            'info'         => array(
                'column'      => true,
                'target'      => 'blog_name',
                'desc_first'  => false
            )
        );
    }

    return $properties;
}

function clgs_get_bulk_schema ( $which ) {
    $properties = array (
        'mark-seen' => array (
            'title'       => __( 'Mark as read', 'custom-logging-service' ),
            'description' => __( 'Mark whole category %s as read', 'custom-logging-service' ),
            'context'     => 'edit'
        ),
    );
    if ( 'category' == $which ) {
        $properties['clear'] = array (
            'title'       => __( 'Clear', 'custom-logging-service' ),
            'description' => __( 'Remove all log entries from category %s', 'custom-logging-service' ),
            'context'     => 'edit'
        );
        $properties['unregister'] = array (
            'title'       => __( "Delete", 'custom-logging-service' ),
            'description' => __( 'Delete category %s permanently (with all entries)', 'custom-logging-service' ),
            'context'     => 'delete'
        );
    } else {
        $properties['delete'] = array (
            'title'       => __( 'Delete', 'custom-logging-service' ),
            'context'     => 'delete'
        );
    }

    return $properties;
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
 * @return mixed array of field or null if uninterpretable or no entries
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

    return array_unique( $sane );
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

    return is_a( $value, 'WP_User' ) ? $value->user_login : '';
}

/**
 * transforms DB log entries to an array suitable for output
 *
 * @global array severity_list
 *
 * @param array[string] $names list of expected output field names
 * @param Object $item log entry
 *
 * @return array associative array of field names and text content
 */
function clgs_map_item ( $names, $item ) {
    global $severity_list;

    $user = get_user_by('login', $item->user_name);

    $formatted = array();
    foreach ( $names as $key ) {
        switch ( $key ) {
        case 'date':
            $field = '<span data-date="' . $item->date . '"></span>';
            break;
        case 'seen':
            $field = (boolean) $item->seen;
            break;
        case 'user':
            if ($user) {
                $field = $user->display_name;
            } else {
                $field = $item->user_name ? $item->user_name : '-';
            }
            break;
        case 'avatar':
            $field = $user ?  get_avatar($user, 32) : null;
            break;
        case 'blog':
            $blog_url = get_blogaddress_by_id($item->blog_id);
            $field = "<a href=\"$blog_url\" >$item->blog_name</a>";
            break;
        case 'severity':
            $field = $severity_list[$item->severity];
            break;
        case 'message':
            $field = $item->text;
            break;
        default:
            $field = $item->{$key};
            break;
        }
        $formatted[$key] = $field;
    }

    return $formatted;
}

function clgs_get_unseen () {
    global $clgs_db;

    $where = array(
        'seen' => false,
        'min_severity' => clgs_get_settings()['notification_severity_filter']
    );
    if ( clgs_is_network_mode() && !is_main_site() ) {
        $where['blog_id'] = get_current_blog_id();
    }
    return $clgs_db->get_entries( $where, true )->total;
}
