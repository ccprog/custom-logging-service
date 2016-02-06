<?php
/**
 * Log entry cache
 */
class Clgs_last_log
{
    /**
     * DB option id
     *
     * @access public
     * @const string
     */
    const OPTION = 'clgs_last_log';

    /**
     * content of cached entry
     *
     * @access public
     * @var array
     */
    public $data;
    /**
     * id of cached entry
     *
     * @access public
     * @var int
     */
    public $entry_id;
    /**
     * times the cached entry was submitted
     *
     * @access public
     * @var int
     */
    public $count;

    /**
     * Constructor gets stored data from DB
     *
     * @access public
     */
    function __construct () {
        $log = get_site_option( self::OPTION, NULL );
        if ( $log ) {
            $this->data = $log['data'];
            $this->entry_id = $log['entry_id'];
            $this->count = $log['count'];
        } else {
            $this->data = null;
            $this->entry_id = null;
            $this->count = 1;
        }
    }

    /**
     * write cache to DB
     *
     * @access public
     *
     * @return void
     */
    function write() {
        update_site_option( self::OPTION, $this->data ? array(
            'data' => $this->data,
            'entry_id' => $this->entry_id,
            'count' => $this->count,
        ) : null );
    }

    /**
     * set a new cache entry and write to DB
     *
     * @access public
     *
     * @param array $data entry content
     * @param int $entry_id id of log ietm
     *
     * @return void
     */
    function set( $data, $entry_id ) {
        $this->data = $data;
        $this->entry_id = $entry_id;
        $this->count = 1;

        $this->write();
    }

    /**
     * compare a new log entry with cache and adjust counter if a double is detected
     *
     * @access public
     *
     * @param array $data entry content
     *
     * @return boolean true if new entry and cache match
     */
    function compare( $data ) {
        if ( !is_array( $this->data ) ) {
            return false;
        }
        $compared = array_diff_assoc( $data, $this->data );
        if ( 1 == count( $compared ) && array_key_exists( 'date', $compared ) ) {
            $this->count++;
            return true;
        }
        return false;
    }

    /**
     * empty DB cache entry
     *
     * @access public
     *
     * @return void
     */
    function flush() {
        update_site_option( self::OPTION, null );
    }
}

