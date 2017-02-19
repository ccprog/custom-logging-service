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
 * sanitizes log entry and settings arguments
 *
 * @global array $allowedtags
 *
 * @param mixed $value argument to transform
 * @param string $rule indentifier for the sanitation method
 *
 * @return mixed sane value
 */
function clgs_sanitize( $value, $rule ) {
    global $allowedtags;

    switch ( $rule ) {
    case 'string':
        return esc_attr( $value );
    case 'kses_string':
        $tags = $allowedtags;
        $tags['br'] = array();
        return wp_kses( (string)$value, $tags );
    case 'int':
        return (int)esc_attr( $value );
    case 'bool':
        return (bool)esc_attr( $value );
    case 'time':
        if ( in_array( gettype( $value ), ['integer', 'double'] ) ) {
            return (int)$value;
        } else {
            return strtotime( (string)$value );
        }
    }
}

/**
 * validates log entry and settings arguments
 *
 * @global Clgs_CB $clgs_db
 * @global array $severity_list
 *
 * @param mixed $value argument to test
 * @param string $rule indentifier for the validation method
 *
 * @return boolean for passing the test
 */
function clgs_validate ( $value, $rule ) {
    global $clgs_db, $severity_list;

    switch ( $rule ) {
    case 'exists':
        return !is_null( $value );
    case 'length':
        return strlen( $value ) > 0;
    case 'severity':
        return array_key_exists( $value, $severity_list );
    case 'role':
        return !is_null( $value ) && array_reduce( $value, function( $carry, $entry ) {
            return $carry && wp_roles()->is_role( $entry );
        }, true );
    case 'registered':
        return $clgs_db->is_registered( $value );
    case 'positive':
        return ( $value > 0 );
    }
}

/**
 * helper santation function: normalize to array
 *
 * @param mixed $value input argument
 * @param boolean $cast cast entries to integer
 *
 * @return mixed array of field or null if uninterpretable or no entries
 */
function clgs_to_array ( $value, $cast = false ) {
    if ( 'array' == gettype( $value ) ) {
        $sane = $value;
    } elseif ( 'string' == gettype( $value ) ) {
        $sane = '' === $value ? array() : explode( ',', esc_attr( $value ) );
    } else {
        return null;
    }

    foreach ( $sane as &$entry ) {
        $entry = esc_attr( $entry );
        if ( $cast ){
            $entry = (int)$entry;
        }
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
function clgs_to_user ( $value ) {
    switch ( gettype( $value ) ) {
    case 'integer':
        $user = get_user_by( 'id', $value );
        break;
    case 'string':
        $user = get_user_by( 'login', $value );
        if ( !$user ) $user = get_user_by( 'slug', $value );
        break;
    }

    return is_a( $user, 'WP_User' ) ? $user->user_login : '';
}

function clgs_prepare_data ($args) {
    $data = array();

    foreach ( clgs_get_item_schema('api') as $key => $rule ) {
        if ( !isset ( $args[$key] ) ) {
            if ( isset( $rule['default'] ) ) {
                $data[$rule['db_key']] = $rule['default'];
            } else {
                return false;
            }
        } elseif ( 'user' == $key ) {
            $data[$rule['db_key']] = clgs_to_user( $args['user'] );
        } else {
            $data[$rule['db_key']] = clgs_sanitize( $args[$key], $rule['sanitize'] );
        }

        if ( 'date' != $key && !clgs_validate( $data[$rule['db_key']], $rule['validate'] ) ) {
            return false;
        }
    }

	// get blog name
	if( clgs_is_network_mode() ) {
		switch_to_blog( $args['blog_id'] );
        $data['blog_name'] = get_bloginfo( 'name' );
		restore_current_blog();
	} else {
		$data['blog_name'] = get_bloginfo( 'name' );
	}

    return $data;
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

/**
 * retrieves the total count of unseen log entries
 *
 * @global Clgs_DB $clgs_db
 *
 * @return int number of unseen entries
 */
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
