<?php

class Clgs_REST_Logs extends Clgs_REST_Controller {
    const BASE = 'logs';

    public function __construct() {
        $this->rest_base = self::BASE;
    }

    public function register_routes() {

        register_rest_route( self::NSPACE, '/' . $this->rest_base, array(
            array(
                'methods'   => WP_REST_Server::READABLE,
                'callback'  => array( $this, 'get_items' ),
                'permission_callback' => array( $this, 'permissions_check' ),
                'args'      => $this->get_param_schema ( false ),
            ),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'bulk_items' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => $this->get_bulk_arguments( 'log', 'delete',
                                         __( 'Remove all listed log entries', 'custom-logging-service' ) ),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'bulk_items' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => $this->get_bulk_arguments( 'log', 'edit',
                                         __( 'Mark all listed log entries as read', 'custom-logging-service' ) ),
            ),
            'schema' => array( $this, 'get_items_schema' ),
        ) );
        register_rest_route( self::NSPACE,  '/count', array(
            array(
                'methods'   => WP_REST_Server::READABLE,
                'callback'  => array( $this, 'get_count' ),
                'permission_callback' => array( $this, 'permissions_check' ),
                'args'      => $this->get_param_schema ( true ),
            ),
            'schema' => array( $this, 'get_count_schema' ),
        ) );
        register_rest_route( self::NSPACE, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods'   => WP_REST_Server::READABLE,
                'callback'  => array( $this, 'get_item' ),
                'permission_callback' => array( $this, 'permissions_check' ),
            ),
            'schema' => array( $this, 'get_public_item_schema' ),
        ) );
    }

    /**
     * Grabs a log entry and outputs as a rest response.
     *
     * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function get_item( $request ) {
        global $clgs_db;

        $entries = $clgs_db->get_entries( array(
            'id' => $request['id']
        ) );

        if ( !$entries || 0 == count( $entries ) ) {
            return new WP_Error( 'clgs_rest_items_invalid', __( 'Invalid entry id.', 'custom-logging-service' ), array( 'status' => 404 ) );
        }

        $response = $this->prepare_item_for_response( current( $entries ), $request );

        return $response;
    }

    /**
     * Grabs the log entries and outputs them as a rest response.
     *
     * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function get_items( $request ) {
        global $clgs_db;

        $params = $request->get_params();
        extract( $this->prepare_query_params( $params ) );

        $limit = array(
            'from' => $params['per_page'] * ( $params['page'] - 1),
            'offset' => $params['per_page']
        );

        $total_items = $clgs_db->get_entries( $where, true )->total;
        $log_entries = $clgs_db->get_entries( $where, false, $limit, $order );

        unset( $where['min_severity'] );
        $item_count = $clgs_db->get_entries( $where, true );

        $base_link = $this->prepare_link_url( array( $this->rest_base ) );
        $base = $this->get_link_base( $request, $base_link );

        $collection = $this->prepare_collection_for_response( $log_entries, $request );
        $response = rest_ensure_response( array(
            'collection' => $collection,
            'count' => (array) $item_count
         ) );

        $max_pages = max( 1, ceil( $total_items / (int) $params['per_page'] ) );

        $this->set_count_headers( $response, (int) $total_items, (int) $max_pages );
        $response->header( 'X-CLGS-Unseen', (int) clgs_get_unseen() );

        if ( $params['page'] > 1 ) {
            $prev_page = $params['page'] - 1;

            if ( $prev_page > $max_pages ) {
                $prev_page = $max_pages;
            }

            $prev_link = add_query_arg( 'page', $prev_page, $base );
            $response->link_header( 'prev', $prev_link );
        }
        if ( $max_pages > $params['page'] ) {
            $next_page = $params['page'] + 1;
            $next_link = add_query_arg( 'page', $next_page, $base );

            $response->link_header( 'next', $next_link );
        }
/*
        $count_link =  $this->prepare_link_url( array( 'count' ) );
        $response->add_links( array (
            'count'  => array(
                'href' => $this->get_link_base( $request, $count_link ),
                'embeddable' => true
            ),
        ) );
*/
        return $response;
    }

    /**
     * Grabs the log entries and outputs them as a rest response.
     *
     * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function get_count( $request ) {
        global $clgs_db;

        $params = $request->get_params();
        extract( $this->prepare_query_params( $params ) );

        $item_count = array_map(function ( $count ) {
            return (int) $count;
        }, (array) $clgs_db->get_entries( $where, true ));

        $response = rest_ensure_response( $item_count );
        $base_link = $this->prepare_link_url( array( $this->rest_base ) );
        $response->add_links( array (
            'contents'  => array(
                'href' => $this->get_link_base( $request, $base_link ),
            ),
        ) );

        return $response;
    }

    /**
     * transforms request attributes to structured DB query parameters
     *
     * @param array $params list of sanitized and validated attributes with
     * all defaults set.
     *
     * @return array[array] 'where' and 'order' parameters
     */
    protected function prepare_query_params ( $params ) {
        global $severity_list;

        if ( key_exists( 'min_severity', $params ) ) {
            $params['min_severity'] = array_search( $params['min_severity'], $severity_list );
        }
        extract( $params );

        $where_args = compact( 'seen', 'min_severity', 'category' );
        if ( isset( $entry_id ) ) { // rename for fieldbase
            $where_args['id'] = $entry_id;
        }
        if ( clgs_is_network_mode() && !is_main_site() ) {
            $where_args['blog_id'] = get_current_blog_id();
        }

        if ( isset( $orderby ) ) {
            $order_args = array(
                'by' => 'user' == $orderby ? 'user_name' : $orderby,
                'dir' => $order 
            );
        } else {
            $order_args = null;
        }

        return array ( 'where' => $where_args, 'order' => $order_args);
    }

    protected function get_link_base ( $request, $base_link ) {
        $request_params = array_intersect_key( $request->get_query_params(), $request->get_Attributes()['args'] );
        return add_query_arg( $request_params, $base_link );
    }

    protected function map_response_item( $names, $item ) {
        return clgs_map_item( $names, $item );
    }

	/**
	 * Marks or deletes a list of entries.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function bulk_items( $request ) {
        global $clgs_db;

        $action = $request['action'];
        $entry_ids = $request['entries'];

        $result = $clgs_db->bulk_entries( $action, $entry_ids );

        if ( $result ) {
            $response_data = new WP_REST_Response( array(
                'affected' => $result
            ) );

            $response = rest_ensure_response( $response_data );
            $response->add_links( $this->prepare_links( false ) );
        } else {
            $response = new WP_Error( 'clgs_rest_items_invalid', __( 'No valid entry ids.', 'custom-logging-service' ), array( 'status' => 404 ) );
        }

        return $response;
    }

	/**
	 * Prepares links for the log entry request.
	 *
     * @param Object $log The category object whose response is being prepared.
	 * @param boolean $has_self If the entry still exists.
	 * @return array Links for the given category.
	 */
	protected function prepare_links( $log ) {
        $links = array(
			'collection' => array(
                'href' =>  $this->prepare_link_url( array( $this->rest_base ) ),
			),
		);
        if ( $log ) {
            $links['self'] = array(
                'href' => $this->prepare_link_url( array( $this->rest_base, $log['id'] ) ),
			);
        }

		return $links;
	}

    public function get_param_schema ( $count ) {
        global $severity_list;

        extract( clgs_get_settings() );

        $orderable = [ 'date', 'category', 'user', 'severity' ];
        if ( clgs_is_network_mode() && is_main_site() ) {
             $orderable[] = 'blog_name';
        }

        $args = array (
            'category'      => array (
                'description' => __( 'Filter log category.', 'custom-logging-service' ),
                'type'       => 'string',
            ),
            'seen'          => array (
                'description' => __( 'Show old entries.', 'custom-logging-service' ),
                'type'       => 'boolean',
                'default'    => false,
            ),
            'min_severity'  => array (
                'description' => __( 'Minimum Severity.', 'custom-logging-service' ),
                'type'       => 'string',
                'enum'       => array_values( $severity_list ),
                'default'    => $severity_list[$def_severity_filter],
            ),
        );

        if ( ! $count ) {
			$args['page'] = array(
				'description'        => __( 'Current page of the collection.' ),
				'type'               => 'integer',
				'default'            => 1,
				'sanitize_callback'  => 'absint',
				'minimum'            => 1,
			);
			$args['per_page'] = array(
				'description'        => __( 'Maximum number of items to be returned in result set.' ),
				'type'               => 'integer',
				'default'            => $log_entries_per_page,
				'minimum'            => 1,
				'sanitize_callback'  => 'absint',
			);
            $args['orderby'] = array(
				'description' => __( 'Order by property.', 'custom-logging-service' ),
                'type'       => 'string',
                'enum'       => $orderable,
                'default'    => 'date'
            );
            $args['order'] = array(
				'description' => __( 'Order direction.', 'custom-logging-service' ),
                'type'       => 'string',
                'enum'       => [ 'asc', 'desc' ],
                'default'    => 'asc'
            );
        }

        foreach( $args as $arg) {
            $arg = array_merge( array (
                'sanitize_callback' => 'sanitize_key',
                'validate_callback' => 'rest_validate_request_arg',
            ), $arg );
        };

        return $args;
    }

    public function get_items_schema () {
        $item_schema = $this->get_item_schema();
        unset( $item_schema['$schema'] );
        $count_schema = $this->get_count_schema();
        unset( $count_schema['$schema'] );
        return array (
            '$schema'     => 'http://json-schema.org/draft-04/schema#',
            'title'       => 'logs',
            'type'        => 'object',
            'properties'  => array(
                'collection'   => array(
                    'description' => esc_html__( 'Log items', 'custom-logging-service' ),
                    'type'        => 'array',
                    'items'       => $item_schema
                ),
                'count'     => $count_schema
            ),
        );
    }

    /**
     * Get schema for a log count.
     *
     * @return array JSON Schema of category data
     */
    public function get_count_schema ( ) {
        global $severity_list;
        
        $properties = array();
        foreach ($severity_list as $severity) {
            $properties[$severity] = array(
                'description' => sprintf(esc_html__( 'Count of entries with severity %s', 'custom-logging-service' ), $severity),
                'type'       => 'integer',
                'readonly'     => true,
            );
        }
        $properties['total'] = array(
            'description' => esc_html__( 'Entry count', 'custom-logging-service' ),
            'type'       => 'integer',
            'readonly'     => true,
        );
        return array(
            '$schema'     => 'http://json-schema.org/draft-04/schema#',
            'title'       => 'count',
            'type'        => 'object',
            'properties'  => $properties
        );
    }

    /**
     * Get schema for a log entry.
     *
     * @param WP_REST_Request $request Current request.
     * @return array JSON Schema of category data
     */
    public function get_item_schema ( ) {
        return array(
        '$schema'     => 'http://json-schema.org/draft-04/schema#',
        'title'       => 'log',
        'type'        => 'object',
        'properties'  => clgs_get_item_schema( 'rest' )
    );
    }
}