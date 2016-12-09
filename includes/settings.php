<?php
/**
 * sanitation/validation rules and description localization for settings
 *
 * @var array
 */
$clgs_settings_structure = array(
    'notification_severity_filter' => array(
        'sanitize' => 'int',
        'validate' => 'severity',
        'desc' => __( 'Minimum severity for notification in administration menu', 'custom-logging-service' )
    ),
    'def_severity_filter' => array(
        'sanitize' => 'int',
        'validate' => 'severity',
        'desc' => __( 'Default minimum severity filter on the log page', 'custom-logging-service' )
    ),
    'manager_role' => array(
        'sanitize_function' => 'clgs_to_array',
        'validate' => 'role',
        'desc' => __( 'Roles that can use the logs page', 'custom-logging-service' )
    ),
    'log_entries_per_page' => array(
        'sanitize' => 'int',
        'validate' => 'positive',
        'desc' => __( 'Log entries per page', 'custom-logging-service' )
    )
);

/**
 * returns default settings
 *
 * @global array $clgs_settings_structure
 *
 * @return array
 */
function clgs_settings_defaults () {
     $settings_defaults =  array(
        'notification_severity_filter' => 0,
        'def_severity_filter' => 2,
        'manager_role' => ['administrator'],
        'log_entries_per_page' => 100
    );
    if ( clgs_is_network_mode() ) {
        unset( $settings_defaults['manager_role'] );
    }
    return $settings_defaults;
}

/**
 * writes default settings to DB option
 *
 * @global array $clgs_settings_structure
 *
 * @param bool $network_wide indicates network install
 *
 * @return void
 */
function clgs_add_settings ( $network_wide ) {
    global $clgs_settings_structure;

    $settings_defaults = clgs_settings_defaults();
    if ( $network_wide ) {
        unset( $clgs_settings_structure['manager_role'] );
    } else {
        foreach ( clgs_get_settings()['manager_role'] as $key => $name ) {
            wp_roles()->add_cap( $name, CLGS_CAP );
        }
    }
    add_site_option( CLGS_SETTINGS, $settings_defaults );
}

/**
 * echos settings page
 *
 * @return void
 */
function clgs_settings_page () { 

?>
    <div class="wrap">
        <h1><?php _e( 'Custom Logging Service', 'custom-logging-service' ); ?></h1>
<?php

        if ( clgs_is_network_mode() ) {
            settings_errors( CLGS_SETTINGS );
        }

?>
        <form method="post" action="<?php echo clgs_is_network_mode() ? 'edit.php?action=clgs_update' : 'options.php' ?>">
<?php

        settings_fields( CLGS_SETTINGS );
        do_settings_sections( CLGS_OPTION_PAGE );
        submit_button();

?>
        </form>
    </div>
<?php

}

/**
 * inits setting fields for settings page
 *
 * @global array $clgs_settings_structure
 *
 * @return void
 */
function clgs_settings_init () { 
    global $clgs_settings_structure, $wp_version;

    $setting_attributes = 'clgs_sanitize';
    if ( version_compare( $wp_version, '4.6', '>' ) ) {
        $setting_attributes = array(
            'sanitize_callback' => 'clgs_sanitize',
            'default' => clgs_settings_defaults()
        );
    }

    if ( clgs_is_network_mode() ) {
        unset( $clgs_settings_structure['manager_role'] );
    } else {
        register_setting( CLGS_SETTINGS, CLGS_SETTINGS, $setting_attributes );
    }

    add_settings_section(CLGS_GROUP, null, null, CLGS_OPTION_PAGE);

    foreach ( $clgs_settings_structure as $key => $rule ) {
        add_settings_field( 
            $key, 
            __( $rule['desc'], 'custom-logging-service' ), 
            'clgs_field_render', 
            CLGS_OPTION_PAGE,
            CLGS_GROUP,
            [ $key ]
        );
    }
}
add_action( 'admin_init', 'clgs_settings_init' );

/**
 * sanitation function for settings page
 *
 * @global array $clgs_settings_structure
 *
 * @return array sane settings, unaltered in case of an error
 */
function clgs_sanitize ( $input ) {
    global $clgs_settings_structure;

    $original = clgs_get_settings();
    $result = clgs_evaluate( $input, $clgs_settings_structure, 'hold' );
    if ( 'string' == gettype( $result ) ) {
        $offending = __( $clgs_settings_structure[$result]['desc'], 'custom-logging-service' );
        $message = sprintf( __( 'The setting %s was invalid, nothing saved.', 'custom-logging-service' ),
            '<em>"' . $offending . '"</em>' );
        add_settings_error( CLGS_SETTINGS, 'clgs_error', $message );
        return $original;
    }
    return array_merge($original, $result );
}

/**
 * echos setting input field
 *
 * @global array $severity_list
 *
 * @param array $args(mixed) field info
 *
 * @return void
 */
function clgs_field_render( $args ) { 
    global $severity_list;

    $id = $args[0];
    $options = clgs_get_settings();

    if ( 'log_entries_per_page' == $id ) {

?>
    <input type="text" name="<?php echo CLGS_SETTINGS . "[$id]"; ?>" value="<?php echo $options[$id]; ?>">
<?php

    } elseif ( 'manager_role' == $id ) {
        $name_attr = CLGS_SETTINGS . "[$id][]";
        foreach ( wp_roles()->get_names() as $key => $name ) {
            $checked = in_array( $key, $options[$id] ) ? 'checked ' : '';
            echo "<label><input type=\"checkbox\" name=\"$name_attr\" value=\"$key\" $checked/>";
            echo translate_user_role( $name ) . "</label><br/>";
        }
    } else {

?>
        <select name="<?php echo CLGS_SETTINGS . "[$id]"; ?>">
<?php

        foreach ( $severity_list as $key => $value ) {

?>
            <option value="<?php echo $key; ?>"<?php
                if ($key == $options[$id]) echo ' selected="selected"'; ?>><?php
                echo $value; ?></option>
<?php

        }
?>
        </select>
<?php

    }
}

/**
 * synch role capabilities on settings update
 *
 * @param array $old_value old settings values
 * @param array $value altered settings values
 *
 * @return void
 */
function clgs_update_capabilities ( $old_value, $value ) {
    $removed = array_diff( $old_value['manager_role'], $value['manager_role'] );
    foreach ($removed as $name ) {
        get_role( $name )->remove_cap( CLGS_CAP );
    }
    $added = array_diff( $value['manager_role'], $old_value['manager_role'] );
    foreach ($added as $name ) {
        get_role( $name )->add_cap( CLGS_CAP );
    }
}
add_action ( "update_option_" . CLGS_SETTINGS, 'clgs_update_capabilities', 10, 2 );

/**
 * save settings in multisite environment
 *
 * @return void
 */
function clgs_save_network_settings () {
    check_admin_referer( CLGS_SETTINGS . '-options' );
    if ( !current_user_can( 'manage_network_options' ) ) wp_die();

    $settings = clgs_sanitize( $_POST[ CLGS_SETTINGS ] );
    update_site_option( CLGS_SETTINGS, $settings );

	if ( !count( get_settings_errors() ) )
		add_settings_error( CLGS_SETTINGS, 'settings_updated', __('Settings saved.'), 'updated');
	set_transient('settings_errors', get_settings_errors(), 30);
    add_filter('wp_redirect', function ($url) {
        $url = add_query_arg( array(
            'page' => CLGS_OPTION_PAGE,
            'settings-updated' => 'true'
        ),  admin_url( 'network/settings.php' ) );
        return $url;
    });
}
add_action('network_admin_edit_clgs_update', 'clgs_save_network_settings');
