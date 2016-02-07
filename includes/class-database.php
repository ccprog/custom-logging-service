<?php
/**
 * DB interface
 */
class Clgs_DB
{
    /**
     * SQL log table name
     *
     * @access private
     * @var string
     */
    private $logs_table_name;
    /**
     * SQL entries table name
     *
     * @access private
     * @var string
     */
    private $entries_table_name;

    /**
     * Constructor gets stored data from DB
     *
     * @global WPDB $wpdb
     *
     * @access public
     */
    function __construct () {
        global $wpdb;

        $prefix = $wpdb->base_prefix;
        $this->logs_table_name = $prefix . 'clgs_logs';
        $this->entries_table_name = $prefix . 'clgs_entries';
    }

    /**
     * tests length of a category string
     *
     * @param string $str
     *
     * @access public
     *
     * @return boolean true if no longer than 190 (unicode) characters
     */
    function category_fits ( $str ) {
        $length = iconv_strlen( $str, 'UTF-8' );
        return $length > 0 && $length <=190;
    }

    /**
     * creates plugin DB tables
     *
     * @access public
     *
     * @global WPDB $wpdb
     * @global string $charset_collate
     *
     * @return void
     */
    function create() {
        global $wpdb, $charset_collate;
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        dbDelta( "
CREATE TABLE IF NOT EXISTS $this->logs_table_name (
    category VARCHAR(190) NOT NULL,
    description LONGTEXT,
    PRIMARY KEY  ( category )
    ) $charset_collate;\n"
        );

        dbDelta( "
CREATE TABLE IF NOT EXISTS $this->entries_table_name (
    id INT NOT NULL AUTO_INCREMENT,
    category VARCHAR(190) NOT NULL,
    blog_id INT NOT NULL,
    blog_name LONGTEXT NOT NULL,
    date BIGINT NOT NULL,
    user_name VARCHAR(255),
    severity INT NOT NULL,
    text LONGTEXT,
    seen BOOL NOT NULL DEFAULT FALSE,
    PRIMARY KEY  ( id ),
    KEY  ( category ),
    KEY  ( date )
    ) $charset_collate;\n"
        );
    }

    /**
     * deletes plugin DB tables
     *
     * @access public
     *
     * @global WPDB $wpdb
     *
     * @return void
     */
    function destroy () {
        global $wpdb;

        $wpdb->query( "DROP TABLE IF EXISTS $this->logs_table_name" );        
        $wpdb->query( "DROP TABLE IF EXISTS $this->entries_table_name" );        
    }

    /**
     * retrieve all log category entries
     *
     * @access public
     *
     * @global WPDB $wpdb
     *
     * @return array
     */
    function get_logs( ) {
        global $wpdb;

        $query = "SELECT *
            FROM $this->logs_table_name
            ORDER BY category ASC";
        return $wpdb->get_results( $query );
    }

    /**
     * retrieve one log category entry
     *
     * @access public
     *
     * @global WPDB $wpdb
     *
     * @param string $category
     *
     * @return array
     */
    function get_log( $category ) {
        global $wpdb;

        $query = $wpdb->prepare( "SELECT *
            FROM $this->logs_table_name
            WHERE category = %s", $category );
        return $wpdb->get_row( $query );
    }

    /**
     * test if a log category name exists
     *
     * @access public
     *
     * @global WPDB $wpdb
     *
     * @param string $category
     *
     * @return boolean
     */
    function is_registered( $category ) {
        global $wpdb;
        $query = $wpdb->prepare( "SELECT COUNT(*)
            FROM $this->logs_table_name
            WHERE category = %s", $category );
        return ( $wpdb->get_var( $query ) > 0 );
    }

    /**
     * insert a log category entry
     *
     * @access public
     *
     * @global WPDB $wpdb
     *
     * @param string $category name
     * @param string $description freetext
     *
     * @return mixed
     */
    function register( $category, $description ) {
        global $wpdb;

        return $wpdb->insert( $this->logs_table_name, compact( 'category', 'description' ) );
    }

    /**
     * delete log entries in bulk
     *
     * @access public
     *
     * @global WPDB $wpdb
     * @global Clgs_Last_Log $clgs_last_log
     *
     * @param string $which
     *     'clear' - delete all antries of $category
     *     'unregister' - additionally delete category entry 
     * @param string $category name
     *
     * @return mixed
     */
    function bulk_category( $which, $category ) {
        global $wpdb, $clgs_last_log;

        $clgs_last_log->flush();
        $where = compact ( 'category' );
        switch ( $which ) {
        case 'mark-category':
            $data = array( 'seen' => true );
            $result = $wpdb->update( $this->entries_table_name, $data, $where, '%d' );
            break;
        default:
            $result = $wpdb->delete( $this->entries_table_name, $where );
            break;
        }
        if ( ( false !== $result ) && 'unregister' == $which ) {
            $result = $wpdb->delete( $this->logs_table_name, $where );
        }
        return $result;
    }

    /**
     * insert a log entry
     *
     * @access public
     *
     * @global WPDB $wpdb
     *
     * @param array(mixed) $data in this order:
     *     category, blog_id, blog_name, date, user_name, text, severity
     *
     * @return mixed
     */
    function insert_entry( $data ) {
        global $wpdb;

        $result = $wpdb->insert( $this->entries_table_name, $data,
            array( '%s', '%d', '%s', '%d', '%s', '%s', '%d' ) );
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * update a log entry
     *
     * @access public
     *
     * @global WPDB $wpdb
     *
     * @param int $entry_id
     * @param array $data in this order:
     *     category, blog_id, blog_name, date, user_name, text, severity, seen
     *
     * @return mixed
     */
    function update_entry( $entry_id, $data ) {
        global $wpdb;

        $data['seen'] = false;
        return $wpdb->update( $this->entries_table_name, $data, array( 'id' => $entry_id ),
            array( '%s', '%d', '%s', '%d', '%s', '%s', '%d', '%d' ), '%d' );
    }

    /**
     * retrieve log entries
     *
     * @access public
     *
     * @global WPDB $wpdb
     *
     * @param array $where can contain data for
     *     'blog_id' => int
     *     'severity' => int
     *     'id' => int
     *     'min_severity' => int
     *     'category' => string
     *     'seen' => boolean
     * @param boolean $count return only row count (ignores $limit and $order)
     * @param array $limit
     *     'from' => int
     *     'offset' => int
     * @param array $order
     *     'by' => string column name
     *     'dir' => string 'ASC' or 'DESC'
     *
     * @return mixed
     */
    function get_entries( $where, $count = false, $limit = null, $order = null ) {
        global $wpdb;

        // SELECT claus
        $what = $count ? 'COUNT(*)' : "*";

        $cond = array();
        $args = array();

        // WHERE clause
        foreach ( $where as $key => $value ) {
            switch ($key) {
            case 'blog_id':
            case 'severity':
            case 'id':
                $cond[] = $key . ' = %d';
                $args[] = $value;
                break;
            case 'min_severity':
                if ( $value > 0 ) {
                    $cond[] = 'severity >= %d';
                    $args[] = $value;
                }
                break;
            case 'category':
                $cond[] = $key . ' = %s';
                $args[] = $value;
                break;
            case 'seen':
                if ( !$value ) $cond[] = 'seen = 0';
                break;
            }
        }
        if( count( $cond ) > 0 ) {
            $where = ' WHERE ' . implode( ' AND ', $cond );
        } else {
            $where = '';
        }

        // LIMIT clause
        if( $limit ) {
            $args[] = $limit['from'];
            $args[] = $limit['offset'];
            $limit = "LIMIT %d, %d";
        } else {
            $limit = "";
        }

        // ORDER BY clause
        if( $count || !$order ) {
            $order_by = "";
        } else {
            extract( $order );
            $order_by = "ORDER BY $by $dir, id $dir";
        }

        /* Build a query string */
        $query = "SELECT $what
        FROM $this->entries_table_name
        $where
        $order_by
        $limit";
        if ( count( $args ) > 0 ) {
            $query = $wpdb->prepare( $query, $args );
        }

        if( $count ) {
            return $wpdb->get_var( $query );
        } else {
            return $wpdb->get_results( $query );
        }
    }

    /**
     * manipulate log entries in bulk
     *
     * @access public
     *
     * @global WPDB $wpdb
     * @global Clgs_Last_Log $clgs_last_log
     *
     * @param string $what
     *     'delete' - delete all listed entries
     *     'mark-seen' - set seen=true for all listed entries
     * @param array(int) $entry_ids
     *
     * @return mixed
     */
    function bulk_entries( $what, $entry_ids ) {
        global $wpdb, $clgs_last_log;

        if ( !count( $entry_ids ) ) {
            return false;
        }
        $where = "WHERE id IN (" . implode( ", ", $entry_ids ) . ")";
        switch ( $what ) {
        case 'delete':
           $clgs_last_log->flush();
            return $wpdb->query(
                "DELETE FROM $this->entries_table_name $where"
            );
        case 'mark-seen':
            return $wpdb->query(
                "UPDATE $this->entries_table_name SET seen = 1 $where"
            );
        }
    }
}

