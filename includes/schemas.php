<?php
/**
 * Get schema for a log entry.
 * 
 * @param string $purpose 'api' (input), 'rest' (output), or 'column' (list table)
 *
 * @return array appropriate attributes for each column needed for a purpose
 */
function clgs_get_item_schema ( $purpose ) {
    global $severity_list;

    $properties = array(
        'id' => array(
            'title'  => __( 'Unique ID', 'custom-logging-service' ),
            'api'         => false,
            'rest'        => array(
                'type'        => 'integer',
            ),
            'column'      => array(
                'primary'    => false
            )
        ),
        'message' =>  array(
            'title'       => __( 'Message', 'custom-logging-service' ),
            'api'         => array(
                'db_key'      => 'text',
                'sanitize'    => 'kses_string',
                'validate'    => 'length',
            ),
            'rest'        => array(
                'type'        => 'string',
            ),
            'column'      => array(
                'primary'     => true
            )
        ),
        'severity' => array(
            'title'       => __( 'Severity', 'custom-logging-service' ),
            'api'         => array(
                'db_key'      => 'severity',
                'sanitize'    => 'int',
                'validate'    => 'severity',
                'default'     => CLGS_NOSEVERITY,
            ),
            'rest'        => array(
                'type'         => 'string',
                'enum'         => array_values( $severity_list ),
            ),
            'column'      => array(
                'target'      => 'severity',
                'desc_first'  => false
            )
        ),
        'category' => array(
            'title'       => __( 'Log category', 'custom-logging-service' ),
            'api'         => array(
                'db_key'      => 'category',
                'sanitize'    => 'string',
                'validate'    => 'registered',
            ),
            'rest'        => array(
                'type'         => 'string',
                'maxLength'    => 190,
            ),
            'column'      => array(
                'target'      => 'category',
                'desc_first'  => false
            )
        ),
        'date' => array(
            'title'       => __( 'Time', 'custom-logging-service' ),
            'api'         => array(
                'db_key'      => 'date',
                'sanitize'    => 'time',
                'default'     => time(),
            ),
            'rest'        => array(
                'description'  => __( 'Empty <span> with UNIX timestamp as "data-date" attribute.', 'custom-logging-service' ),
                'type'         => 'string',
            ),
            'column'      => array(
                'target'      => 'date',
                'desc_first'  => true
            )
        ),
        'seen' => array (
            'api'         => false,
            'rest'        => array(
                'description' => __( 'Old entry', 'custom-logging-service' ),
                'type'       => 'boolean',
            ),
            'column'      => false
        ),
        'user' => array(
            'title'       => __( 'User', 'custom-logging-service' ),
            'api'         => array(
                'db_key'      => 'user_name',
                'validate'    => 'exists',
                'default'     => is_user_logged_in() ? wp_get_current_user()->display_name : ' &mdash; ',
            ),
            'rest'        => array(
                'description'  => __( 'Login name of the user, preceded by its gravatar.' ),
                'type'         => 'string',
            ),
            'column'      => array(
                'target'      => 'user_name',
                'desc_first'  => false
            )
        ),
        'avatar' => array(
            'api'         => false,
            'rest'        => array(
                'description'  => __( 'Gravatar <img> tag of the user.' ),
                'type'         => 'string',
            ),
            'column'      => false,
        ),
        'blog_id' => array(
            'api'         => array(
                'db_key'      => 'blog_id',
                'sanitize'    => 'int',
                'validate'    => 'positive',
                'default'     => get_current_blog_id(),
            ),
            'rest'        => false,
            'column'      => false
        )
    );

    if ( clgs_is_network_mode() && is_main_site() ) {
        $properties['blog'] = array(
            'title'        => __( 'Blog', 'custom-logging-service' ),
            'input'        => false,
            'rest'         => array(
                'description'  => __( 'Link to blog', 'custom-logging-service' ),
                'type'         => 'string',
            ),
            'column'      => array(
                'target'      => 'blog_name',
                'desc_first'  => false
            )
        );
    }

    $schema = array();
    foreach  ($properties as $key => $prop ) {
        if ( $prop[$purpose] ) {
            $attrs = $prop[$purpose];
            if ( 'api' != $purpose && isset( $prop['title'] ) ) {
                $attrs['title'] = $prop['title'];
            }
            $schema[$key] = $attrs;
        }
    }

    return $schema;
}

/**
 * Get list of bulk action parameter values.
 * 
 * @param string $which 'category' or 'logs'
 *
 * @return array list of valid actions and their associated l10n strings
 */
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
