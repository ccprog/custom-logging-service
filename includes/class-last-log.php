<?php
class Clgs_last_log
{
    const OPTION = 'clgs_last_log';

    public $data;
    public $entry_id;
    public $count;

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

    function write() {
	    update_site_option( self::OPTION, $this->data ? array(
            'data' => $this->data,
            'entry_id' => $this->entry_id,
            'count' => $this->count,
        ) : null );
    }

    function set( $data, $entry_id ) {
        $this->data = $data;
        $this->entry_id = $entry_id;
        $this->count = 1;

        $this->write();
    }

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

    function flush() {
        update_site_option( self::OPTION, null );
    }
}

