<?php

class Clgs_REST_Categories extends Clgs_REST_Controller {
    const BASE = 'categories';

    public function __construct() {
        $this->rest_base = self::BASE;
    }

    public function register_routes() {
        register_rest_route( self::NSPACE, '/' . $this->rest_base, array(
            array(
                'methods'   => WP_REST_Server::READABLE,
                'callback'  => array( $this, 'get_items' ),
                'permission_callback' => array( $this, 'permissions_check' ),
            ),
            'schema' => array( $this, 'get_public_item_schema' ),
        ) );
        register_rest_route( self::NSPACE, '/' . $this->rest_base . '/(?P<name>.+)', array(
            array(
                'methods'   => WP_REST_Server::READABLE,
                'callback'  => array( $this, 'get_item' ),
                'permission_callback' => array( $this, 'permissions_check' ),
            ),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'bulk_item' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => $this->get_bulk_arguments( 'category', 'delete',
                                         __( 'Remove all log entries from this category, for unregister additonally delete the category', 'custom-logging-service' ) ),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'bulk_item' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => $this->get_bulk_arguments( 'category', 'edit',
                                         __( 'Mark whole category as read', 'custom-logging-service' ) ),
			),
            'schema' => array( $this, 'get_public_item_schema' ),
        ) );
    }

    /**
     * Grabs the categories and outputs them as a rest response.
     *
     * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function get_items( $request ) {
        global $clgs_db;

        $categories = $clgs_db->get_logs();

        $collection = $this->prepare_collection_for_response( $categories, $request );
        $response = rest_ensure_response( $collection );

        $this->set_count_headers( $response, count($collection), 1 );

        return $response;
    }

    /**
     * Grabs a category and outputs as a rest response.
     *
     * @param WP_REST_Request $request Current request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function get_item( $request ) {
        global $clgs_db;

        $name = urldecode( $request['name'] );

        if ( empty( $name ) || !$clgs_db->is_registered( $name ) ) {
            return new WP_Error( 'clgs_rest_items_invalid', __( 'Invalid category name.', 'custom-logging-service' ), array( 'status' => 404 ) );
        }

        $category = $clgs_db->get_log( $name );
        $response = $this->prepare_item_for_response( $category, $request );

        return $response;
    }

    protected function map_response_item( $names, $item ) {
        $formatted = array();

        foreach ( $names as $key ) {
            switch ( $key ) {
            case 'name':
                $field = $item->category;
                break;
            default:
                $field = $item->{$key};
                break;
            }
            $formatted[$key] = $field;
        }

        return $formatted;
    }

	/**
	 * Empties or deletes a single category and all its entries.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function bulk_item( $request ) {
        global $clgs_db;

        $name = urldecode( $request['name'] );
        $action = $request['action'];

        $previous = $this->get_item( $request );
        $previous_data = $previous->get_data();
        $result = $clgs_db->bulk_category( $action, $name );

        if ( $result ) {
            $response_data = new WP_REST_Response( array(
                'affected' => $result,
                'category' => $previous_data
            ) );

            $has_self = 'unregister' != $action;
            $response = rest_ensure_response( $response_data );
            $response->add_links( $this->prepare_links( $previous_data, $has_self ) );
        } else {
            $response = new WP_Error( 'clgs_rest_items_invalid', __( 'Invalid category name.', 'custom-logging-service' ), array( 'status' => 404 ) );
        }

        return $response;
    }

	/**
	 * Prepares links for the category request.
	 *
     * @param Object $category The category object whose response is being prepared.
	 * @param boolean $has_self If the category still exists.
	 * @return array Links for the given category.
	 */
	protected function prepare_links( $category, $has_self ) {
		$clean = urlencode( $category['name'] );

        $links = array(
			'collection' => array(
                'href' => $this->prepare_link_url( array( $this->rest_base ) ),
			),
		);
        if ( $has_self ) {
            $links['self'] = array(
                'href' => $this->prepare_link_url( array( $this->rest_base, $clean ) ),
			);
            $links['contents'] = array(
                'href' => $this->prepare_link_url( array( Clgs_REST_Logs::BASE, '?category=' . $clean ) ),
			);
        }

		return $links;
	}

    /**
     * Get schema for a category.
     *
     * @param WP_REST_Request $request Current request.
     * @return array JSON Schema of category data
     */
    public function get_item_schema( ) {
        $schema = array(
            '$schema'              => 'http://json-schema.org/draft-04/schema#',
            'title'                => 'category',
            'type'                 => 'object',
            'properties'           => array(
                'name' => array(
                    'description'  => esc_html__( 'Registered category name', 'custom-logging-service' ),
                    'type'         => 'string',
                    'required'     => true,
                    'maxLength'    => 190,
                ),
                'description' => array(
                    'description'  => esc_html__( 'A description for the category.', 'custom-logging-service' ),
                    'type'         => 'string',
                ),
            ),
        );

        return $schema;
    }
}