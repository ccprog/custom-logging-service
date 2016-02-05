<?php

if ( !class_exists( 'WP_List_Table' ) ) require( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

/** @see http://codex.wordpress.org/Class_Reference/WP_List_Table
 *  @see http://wordpress.org/extend/plugins/custom-list-table-example/
 */
class Clgs_Manager_Table extends WP_List_Table
{

	private $_seen_entries;

	private $settings;
    private $where;
    private $order;

	function __construct( ) {
				
		$this->_seen_entries = array();

        $this->settings = clgs_get_settings();

		parent::__construct( array(
	    	'singular'  => 'entry_id',	//singular name of the listed records
	        'plural'    => 'entries',   //plural name of the listed records
	        'ajax'      => false        //does this table support ajax?
	    ) );
	}

	function get_columns() {
		$columns = array(
			'cb' => '<input type="checkbox" />',
			'category' => __( 'Log category', 'custom-logging-service' ),
			'time' => __( 'Time', 'custom-logging-service' ),
			'user' => __( 'User', 'custom-logging-service' )
		);

		if ( clgs_is_network_mode() && is_main_site() ) {
            $columns['blog'] = __( 'Blog', 'custom-logging-service' );
		}
        $columns['severity'] = __( 'Severity', 'custom-logging-service' );
        $columns['message'] = __( 'Message', 'custom-logging-service' );

		return $columns;
	}

	function get_sortable_columns() {
		$sortable_columns = array(
            'time' => array( 'date', true ),
            'category' => array( 'category', false ),
            'user' => array( 'user_name', false ),
            'blog' => array( 'blog_name', false ),
            'severity' => array( 'severity', false )
		);

	    return $sortable_columns;
	}

	function get_bulk_actions() {
		$actions = array(
			'delete' => __( 'Delete', 'custom-logging-service' ),
			'mark-seen' => __( 'Mark as read', 'custom-logging-service' )
		);
		return $actions;
	}

    function set_attributes ( $attrs ) {
		extract( $attrs );
        $this->where = compact( 'seen', 'min_severity', 'category' );
        if ( isset( $entry_id ) ) { // rename for database
            $this->where['id'] = $entry_id;
        }
        if ( clgs_is_network_mode() && !is_main_site() ) {
            $this->where['blog_id'] = get_current_blog_id();
        }
        $this->order = array(
            'dir' => $order,
            'by' => $orderby
        );
    }

	function prepare_items() {
	    global $clgs_db;

		extract( $this->settings );

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);

		$per_page = $log_entries_per_page;
		$current_page = $this->get_pagenum();

		/* Get entries for a page and their total count. */
        $limit = array(
            'from' => ( $current_page - 1 ) * $per_page,
            'offset' => $per_page
        );

		$this->items = $clgs_db->get_entries( $this->where, false, $limit, $this->order );
		$total_items = $clgs_db->get_entries( $this->where, true );

		$this->set_pagination_args( array(
	        'total_items' => $total_items,
	        'per_page'    => $per_page,
	        'total_pages' => ceil($total_items/$per_page)
    	) );
	}

	function extra_tablenav ( $which ) {
        global $clgs_db, $severity_list;

	    if ( isset( $this->where['entry_id'] ) ) {
            return;
        }

        extract( $this->settings );

?>
<div class="alignleft actions">
    <label for="clgs-seen"><?php _e( 'Show old entries:', 'custom-logging-service' ); ?></label>
    <input name="seen" class="clgs-seen" type="checkbox" <?php if ( $this->where['seen'] )  echo 'checked'; ?> />
    <label for="clgs-severity"><?php _e( 'Minimum Severity:', 'custom-logging-service' ); ?></label>
    <select name="min_severity" class="clgs-severity severity-<?php echo $this->where['min_severity']; ?>">
<?php

        $where = array(
            'seen' => false
        );
        if ( isset( $this->where['category'] ) ) {
            $where['category'] = $this->where['category'];
        }
        for( $i = 0; $i <= 5; $i++ ) {
		    $severity = $severity_list[$i];
            $where['severity'] = $i;
		    $unseen = $clgs_db->get_entries( $where, true );
		    if( $unseen > 0 ) {
			    $severity .= ' (' . $unseen . ')';
		    }

		    $selected = ( $i == $this->where['min_severity'] ) ? 'selected="selected"' : '';

		    echo "<option value=\"$i\" $selected>$severity</option>";
	    }

?>
    </select><button class="button" type="submit" value="view"><?php _e('Filter', 'custom-logging-service' ); ?></button>
</div>
<?php

	}

	function single_row( $item ) {
		/* Prepare style */
		static $alt = '';
		$alt = ( $alt == '' ? "alternate" : '' );
        if ( $item->seen ) {
            $unseen ='';
            $title = '';
        } else {
            $unseen = ' unseen';
            $title = 'title="' . __( 'New entry', 'custom-logging-service' ) . '"';
        }
        $unseen = $item->seen ? '' : ' unseen';
		$row_class = "class=\" $alt $unseen severity-{$item->severity} \"";

		/* Show the row */
		echo "<tr $row_class $title>";
		echo $this->single_row_columns( $item );
		echo '</tr>';

		/* Add the entry to list of seen. */
		$this->_seen_entries[] = $item->id;
	}

	function column_cb($item) {
			return sprintf(
			'<input type="checkbox" name="entries[]" value="%s" />', $item->id
		);
	}

	function column_category( $item ) {
		return '<span>' . $item->category . '</span>';
	}

	function column_time( $item ) {
		return '<span data-date="' . $item->date . '"></span>';
	}

	function column_blog( $item ) {
        $blog_url = get_blogaddress_by_id($item->blog_id);
		return "<a href=\"$blog_url\" >$item->blog_name</a>";
	}

	function column_user( $item ) {
		return $item->user_name ? $item->user_name : '-';
	}

	function column_severity( $item ) {
        global $severity_list;

		return $severity_list[$item->severity];
	}

	function column_message( $item ) {
		return $item->text;
	}

	function print_mark_form() {

?>
		<form method="GET">
			<input type="hidden" name="action" value="mark-seen" />
			<input type="hidden" name="entries" value="<?php echo implode( ',', $this->_seen_entries ); ?>" />
			<input type="hidden" name="page" value="<?php echo CLGS_LOG_PAGE ?>" />
			
<?php

            foreach( [ 'seen', 'min_severity' ] as $name ) {
                if ( isset( $this->where[$name] ) ) {
                    $value = $this->where[$name];
                    echo "<input type=\"hidden\" name=\"$name\" value=\"$value\" />";
                }
            }
            wp_nonce_field( 'bulk-' . $this->_args['plural'], '_wpnonce', false );

?>
			<p class="submit">
				<input type="submit" class="button-primary" value="<?php _e( 'Mark all as read', 'custom-logging-service' ); ?>" />
			</p>
		</form>
<?php

	}
}

