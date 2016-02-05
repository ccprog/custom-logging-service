<?php
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

$clgs_settings_defaults = array(
    'notification_severity_filter' => 0,
    'def_severity_filter' => 2,
    'manager_role' => ['administrator'],
    'log_entries_per_page' => 100
);

function clgs_add_settings () {
    global $clgs_settings_defaults;

    if ( clgs_is_network_mode() ) {
        $clgs_settings_defaults['manager_role'] = ['super-admin'];
    }
    add_site_option( CLGS_SETTINGS, $clgs_settings_defaults );

    foreach ( clgs_get_settings()['manager_role'] as $name ) {
        get_role( $name )->add_cap( CLGS_CAP );
    }
}

function clgs_settings_page () { 

?>
	<div class="wrap">
		<h1><?php _e( 'Custom Logging Service', 'custom-logging-service' ); ?></h1>
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

function clgs_settings_init () { 
    global $clgs_settings_structure;

    clgs_add_settings(); // on activation instead?

    if ( !clgs_is_network_mode() ) {
	    register_setting( CLGS_SETTINGS, CLGS_SETTINGS, 'clgs_sanitize' );
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
        if ( clgs_is_network_mode() ) {
            echo "<label><input type=\"checkbox\" name=\"$name_attr\" value=\"super-admin\" disabled checked/>";
            echo "Super Admin</label><br/>";
        }
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

function clgs_save_network_settings () {
    check_admin_referer( CLGS_SETTINGS . '-options' );
    if ( !current_user_can( 'manage_network_options' ) ) wp_die();

    $settings = clgs_sanitize( $_POST[ CLGS_SETTINGS ] );
    update_site_option( CLGS_SETTINGS, $settings );

    add_filter('wp_redirect', 'clgs_export_url');
}
add_action('network_admin_edit_clgs_update', 'clgs_save_network_settings');

function clgs_export_url ($url) {
    return admin_url( 'network/settings.php?page=' . CLGS_OPTION_PAGE );;
}