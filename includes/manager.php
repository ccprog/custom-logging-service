<?php
/**
 * returns notification bubble for log page menu entry
 *
 * @global Clgs_DB $clgs_db
 *
 * @return string bubble markup
 */
function clgs_unseen_field () {
    $unseen = clgs_get_unseen();

    if( $unseen > 0 ) {
        $title = sprintf( __( '%d unseen Log entries', 'custom-logging-service' ), $unseen );
        return " <span class=\"awaiting-mod count-$unseen\" aria-label=\"$title\"><span>$unseen</span></span>";
    } else {
        return '';
    }
}

/**
 * render category actions (links)
 *
 * @return void
 */
function clgs_print_category_actions () {
    $actions = clgs_get_bulk_schema ( 'category' );
    $links = array ();

    foreach ( $actions as $action => $attrs ) {
        $links[] = '<a data-action="' . $action . '" href="" aria-label="' .
            sprintf( $attrs['description'], '<%= name %>') . '" >' . $attrs['title'] . '</a>';
    }

    echo implode( ' | ', $links );
}

/**
 * Print column headers, accounting for sortable columns.
 */
function clgs_print_column_headers() {
    foreach ( clgs_get_item_schema() as $key => $attrs ) {
        if ( $attrs['info']['column'] ) {
            $class = array( 'manage-column' );

            if ('id' == $key) {
                $column_key = 'cb';
                $class[] = 'check-column';
                $column_content = '<label class="screen-reader-text" for="cb-select-all">' .
                    __( 'Select All' ) . '</label>' . '<input id="cb-select-all" type="checkbox" />';
            } else {
                $column_key = $key;
                $column_content = $attrs['title'];
            }

            $class[] = "column-$column_key";

            if ( isset( $attrs['info']['primary'] ) ) {
                $class[] = 'column-primary';
            }

            if ( isset( $attrs['info']['desc_first'] ) ) {
                $orderby = $attrs['info']['target'];

                $class[] = 'sortable';
                $class[] = $attrs['info']['desc_first'] ? 'asc' : 'desc';

                $column_content = '<a href=""><span>' . $column_content .
                    '</span><span class="sorting-indicator"></span></a>';
            }

            $tag = ( 'cb' === $column_key ) ? 'td' : 'th';
            $scope = ( 'th' === $tag ) ? 'scope="col"' : '';
            $id = "data-id='$column_key'";

            $class = "class='" . join( ' ', $class ) . "'";

            echo "<$tag $scope $id $class>$column_content</$tag>";
        }
    }
}

/**
 * Display the bulk actions dropdown.
 *
 * @param string $which The location of the bulk actions: 'top' or 'bottom'.
 */
function clgs_print_bulk_actions( $which ) {

?>
    <div class="alignleft actions bulkactions">
        <label for="bulk-action-selector-<?php echo esc_attr( $which ) ?>" class="screen-reader-text"><?php _e( 'Select bulk action' ) ?></label>
        <select name="action" id="bulk-action-selector-<?php echo esc_attr( $which ) ?>">
            <option value="-1"><?php _e( 'Bulk Actions' ) ?></option>
<?php

    $actions = clgs_get_bulk_schema( 'log' );

    foreach ( $actions as $name => $property ) {
        echo '<option value="' . $name . '">' . $property['title'] . "</option>";
    }

?>
        </select>
        <button class="button doaction" value="action"><?php _e( 'Apply' ) ?></button>
    </div>
<?php

}

/**
 * Extra filter controls to be displayed between bulk actions and pagination
 *
 * @param string $which The location of the filter controls: 'top' or 'bottom'.
 */
function clgs_print_filters ( $which, $screen_reader_text = '' ) {
    global $severity_list;

?>
    <div class="clgs-tablefilter alignleft actions">
        <label for="clgs-seen-<?php echo $which ?>"><?php _e( 'Show old entries:', 'custom-logging-service' ); ?></label>
        <input id="clgs-seen-<?php echo $which ?>" class="clgs-seen" type="checkbox" />
        <label for="clgs-severity-<?php echo $which ?>"><?php _e( 'Minimum Severity:', 'custom-logging-service' ); ?></label>
        <select id="clgs-severity-<?php echo $which ?>" class="clgs-severity">
<?php

    extract( clgs_get_settings() );

    for( $i = 0; $i < count( $severity_list ); $i++ ) {
        $selected = ( $i == $def_severity_filter) ? 'selected="selected"' : '';
        echo "<option value=\"$severity_list[$i]\" $selected>$severity_list[$i]</option>";
    }

?>
        </select>
        <button class="button dofilter" value="view"><?php _e('Filter', 'custom-logging-service' ); ?></button>
    </div>
<?php

}

/**
 * Display the pagination.
 *
 * @param string $which The location of the pagination: 'top' or 'bottom'.
 */
function clgs_print_pagination( $which, $screen_reader_text = '' ) {

?>
    <div class='tablenav-pages no-page'>
        <span class='displaying-num'></span>
        <span class='pagination-links'>
            <a class='first-page' href=''><span class='screen-reader-text'><?php echo __( 'First page' ); ?></span><span aria-hidden='true'>&laquo;</span></a>
            <a class='prev-page' href=''><span class='screen-reader-text'><?php echo __( 'Previous page' ); ?></span><span aria-hidden='true'>&lsaquo;</span></a>
            <span class="paging-input"><label for="current-page-selector-<?php echo $which ?>" class="screen-reader-text"><?php _e( 'Current Page' ); ?></label>
<?php 

    $html_current_page = "<input class='current-page' id='current-page-selector-" . $which . "' type='text' size='2' /><span class='tablenav-paging-text'>";
    $html_total_pages = "<span class='total-pages'></span></span>";

    printf( _x( '%1$s of %2$s', 'paging' ), $html_current_page, $html_total_pages );

?>
            </span>
            <a class='next-page' href=''><span class='screen-reader-text'><?php echo __( 'Next page' ); ?></span><span aria-hidden='true'>&rsaquo;</span></a>
            <a class='last-page' href=''><span class='screen-reader-text'><?php echo __( 'Last page' ); ?></span><span aria-hidden='true'>&raquo;</span></a>
        </span>
    </div>
<?php
}

/**
 * render log page
 *
 * @return void
 */
function clgs_manager_page() {
    $screen = convert_to_screen( null );
    $screen->set_screen_reader_content();
    $screen_reader_content = $screen->get_screen_reader_content();

?>
<div class="wrap" id="clgs-manager">
    <div class="closed" id="clgs-curtain"></div>
    <h1><?php _e( 'Application logs', 'custom-logging-service' ); ?></h1>

    <h2 class="clgs-all-categories"><?php _e( "Log entries from all categories", 'custom-logging-service' ); ?></h2>
    <div id="clgs-category-details" class="hidden">
        <h2 id="clgs-category-header"></h2>
        <p class="category-description"></p>
        <p><?php clgs_print_category_actions(); ?>
            | <a data-action="show-all" href=""><?php
                _e( 'Show all categories', 'custom-logging-service' ); ?></a>
        </p>
    </div>

    <div class="tablenav top">
        <?php clgs_print_bulk_actions( 'top' ); ?>
        <h2 class='screen-reader-text'><?php echo $screen_reader_content['heading_views'] ?></h2>
        <?php clgs_print_filters( 'top' ); ?>
        <h2 class='screen-reader-text'><?php echo $screen_reader_content['heading_pagination'] ?></h2>
        <?php clgs_print_pagination( 'top' ); ?>
        <br class="clear" />
    </div>

    <h2 class='screen-reader-text'><?php echo $screen_reader_content['heading_list'] ?></h2>
    <table class="wp-list-table widefat fixed striped clgs-logs-table">
        <thead>
            <tr><?php clgs_print_column_headers(); ?></tr>
        </thead>
        <tbody>
        </tbody>
        <tfoot>
            <tr><?php clgs_print_column_headers( false ); ?></tr>
        </tfoot>
    </table>

    <div class="tablenav bottom">
        <?php clgs_print_bulk_actions( 'bottom' ); ?>
        <?php clgs_print_filters( 'bottom' ); ?>
        <?php clgs_print_pagination( 'bottom' ); ?>
        <br class="clear" />
    </div>

    <h2 class="clgs-all-categories"><?php _e( 'Log categories', 'custom-logging-service' ); ?></h2>
    <div id="clgs-category-list" class="clgs-all-categories">
        <table class="wp-list-table widefat fixed striped"><tbody><tr class="hidden">
            <td class="column-primary">
                <span><a data-action="single-category" href="" aria-label="<?php
                    printf( __( 'Show only category %s', 'custom-logging-service' ), '<%= name %>'); ?>"></a></span>
                <div class="row-actions visible"><?php clgs_print_category_actions(); ?></div>
            </td>
            <td class="column-description"></td>
        </tr></tbody></table>
    </div>
</div>
<?php

}
