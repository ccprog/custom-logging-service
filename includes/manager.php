<?php
/**
 * returns notification bubble for log page menu entry
 *
 * @global Clgs_DB $clgs_db
 *
 * @return string bubble markup
 */
function clgs_unseen_field () {
    global $clgs_db;

    $where = array(
        'seen' => false,
        'min_severity' => clgs_get_settings()['notification_severity_filter']
    );
    if ( clgs_is_network_mode() && !is_main_site() ) {
        $where['blog_id'] = get_current_blog_id();
    }
    $unseen = $clgs_db->get_entries( $where, true );

    if( $unseen > 0 ) {
        $title = sprintf( __( '%d unseen Log entries', 'custom-logging-service' ), $unseen );
        return " <span class=\"awaiting-mod count-$unseen\" title=\"$title\"><span>$unseen</span></span>";
    } else {
        return '';
    }
}

/**
 * process log page action and redirect for rendering
 *
 * @global string $pagenow
 * @global Clgs_DB $clgs_db
 *
 * @return void
 */
function clgs_manager_action() {
    global $pagenow, $clgs_db;

    if ( !isset( $_REQUEST["page"] ) || CLGS_LOG_PAGE != $_REQUEST["page"] ) {
        return;
    }

    //trigger_error(var_export($_REQUEST,true));
    if ( isset( $_REQUEST['action'] ) && -1 != $_REQUEST['action'] )
        $action = $_REQUEST['action'];

    if ( isset( $_REQUEST['action2'] ) && -1 != $_REQUEST['action2'] )
        $action = $_REQUEST['action2'];

    if ( isset( $_REQUEST['_wpnonce'] ) ) {
        $args = array_intersect_key( $_REQUEST, array_flip(
            [ 'page', 'min_severity', 'seen', 'entry_id', 'orderby', 'order' ] 
        ) );
        if ( isset( $_REQUEST['category'] ) ) {
            $args['category'] = urlencode( $_REQUEST['category'] );
        }

        if ( isset( $action ) ) {
            // Input sanitation
            $attrs = clgs_evaluate( $_REQUEST, array(
                'category' => array(
                    'sanitize' => 'string',
                    'validate' => 'registered'
                ),
                'entries' => array(
                    'sanitize_function' => 'clgs_to_array',
                    'validate' => 'exists',
                    'default' => array()
                )
            ) );
            extract( $attrs );

            // Actions
            if ( in_array( $action, [ 'delete', 'mark-seen' ] ) && isset( $entries ) ) {
                check_admin_referer( 'bulk-entries' );
                $clgs_db->bulk_entries( $action, $entries );
            } elseif ( in_array( $action, [ 'clear', 'unregister', 'mark-category' ] ) && isset( $category ) ) {
                check_admin_referer( 'bulk-category' );
                $clgs_db->bulk_category( $action, $category );
                unset( $args['category'] );
            }
        }

        wp_redirect( add_query_arg( $args, $pagenow ) );
        die();
    }
}
add_action( 'init', 'clgs_manager_action' );

/**
 * render log page
 *
 * @global string $pagenow
 * @global Clgs_DB $clgs_db
 *
 * @return void
 */
function clgs_manager_page() {
    global $pagenow, $clgs_db;

    extract( clgs_get_settings() );

    /*** Input sanitation ***/
    $attrs = clgs_evaluate( $_REQUEST, array(
        'min_severity' => array(
            'sanitize' => 'int',
            'validate' => 'severity',
            'default' => $def_severity_filter
        ),
        'seen' => array(
            'sanitize' => 'bool',
            'validate' => 'exists',
            'default' => false
        ),
        'category' => array(
            'sanitize' => 'string',
            'validate' => 'registered'
        ),
        'entry_id' => array(
            'sanitize' => 'int',
            'validate' => 'positive'
        ),
        'orderby' => array(
            'sanitize' => 'string',
            'validate_array' => [ 'date', 'category', 'user_name', 'blog_name', 'severity' ],
            'default' => 'date'
        ),
        'order' => array(
            'sanitize' => 'toupper_string',
            'validate_array' => [ 'ASC', 'DESC' ],
            'default' => 'ASC'
        )
    ) );
    extract( $attrs );
    //var_dump( $attrs );

    /*** Render ***/
    $table = new Clgs_Manager_Table();

    $pageurl = add_query_arg( 'page', CLGS_LOG_PAGE, $pagenow );
    $pageurl = add_query_arg( compact( 'seen', 'min_severity' ), $pageurl );

    // Show a single entry or a list?
    if( isset( $entry_id ) ) {
        unset( $attrs['category'] );
    }

?>
    <div class="wrap">
        <h1><?php _e( 'Application logs', 'custom-logging-service' ); ?></h1>
<?php

    if ( isset( $attrs['category'] ) ) { // single log category
        $log = $clgs_db->get_log( $attrs['category'] );
        $actionurl = wp_nonce_url( add_query_arg( 'category',  urlencode( $attrs['category'] ), $pageurl ), 'bulk-category' );

?>
        <h2><?php echo __( 'Log category', 'custom-logging-service' ) . ': ' . $log->category; ?></h2>
        <p><?php echo $log->description; ?></p>
        <p>
            <a href="<?php echo $actionurl . '&action=mark-category'; ?>" title="<?php
                _e( "Mark whole category as read", 'custom-logging-service' ); ?>" ><?php
                _e( 'Mark whole category as read', 'custom-logging-service' ); ?></a> |
            <a href="<?php echo $actionurl . '&action=clear'; ?>" title="<?php
                _e( "Remove all log entries from this category", 'custom-logging-service' ); ?>"><?php
                _e( 'Clear', 'custom-logging-service' ); ?></a> |
            <a href="<?php echo $actionurl . '&action=unregister'; ?>" title="<?php
                _e( "Delete this log category permanently (with all entries)", 'custom-logging-service' ); ?>" ><?php
                _e( 'Delete', 'custom-logging-service' ); ?></a> |
            <a href="<?php echo $pageurl; ?>"><?php
                _e( 'Show all categories', 'custom-logging-service' ); ?></a>
        </p>
        <form method="get">
            <input type="hidden" name="page" value="<?php echo CLGS_LOG_PAGE; ?>" />
            <input type="hidden" name="action" value="view" />
            <input type="hidden" name="category" value="<?php echo $log->category; ?>" />
<?php

    } else {

?>
        <h2><?php _e( "New log entries from all categories", 'custom-logging-service' ); ?></h2>
        <form method="get">
            <input type="hidden" name="page" value="<?php echo CLGS_LOG_PAGE; ?>" />
            <input type="hidden" name="action" value="view" />
<?php

    }

    $table->set_attributes( $attrs );
    $table->prepare_items();
    $table->display();

?>
        </form>
<?php

    if ( !isset( $attrs['category'] ) ) { // include a log category overview

?>
        <h2><?php _e( 'Log categories', 'custom-logging-service' ); ?></h2>
        <div id="clgs-log-list"><table class="wp-list-table widefat fixed striped">
<?php

        foreach( $clgs_db->get_logs() as $id => $log ) {
            $caturl = add_query_arg( 'category', urlencode( $log->category ), $pageurl );
            $actionurl = wp_nonce_url( $caturl, 'bulk-category' );

?>
            <tr class="<?php echo $id % 2 === 0 ? 'alternate' : ''; ?>">
            <td class="column-primary">
                <span><a href="<?php echo $caturl; ?>"><?php echo $log->category; ?></a></span>
                <div class="row-actions visible">
                <a href="<?php echo $actionurl . '&action=mark-category'; ?>" title="<?php
                    _e( "Mark whole category as read", 'custom-logging-service' ); ?>" ><?php
                    _e( 'Mark as read', 'custom-logging-service' ); ?></a> |
                <a href="<?php echo $actionurl . '&action=clear'; ?>" title="<?php
                    _e( "Remove all log entries from this category", 'custom-logging-service' ); ?>"><?php
                    _e( 'Clear', 'custom-logging-service' ); ?></a> |
                <a href="<?php echo $actionurl . '&action=unregister'; ?>" title="<?php
                    _e( "Delete this log category permanently (with all entries)", 'custom-logging-service' ); ?>" ><?php
                    _e( 'Delete', 'custom-logging-service' ); ?></a>
                </div>
                <button class="toggle-row" type="button"><span class="screen-reader-text"><?php
                    _e( 'Show more details' ); ?></span></button>
            </td>
            <td><?php echo esc_attr( $log->description ) ?></td>
<?php

        }

?>
        </table></div>
<?php

    }

?>
    </div>
<?php

}
