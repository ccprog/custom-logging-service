<?php

abstract class Clgs_REST_Controller extends WP_REST_Controller {
    const NSPACE = 'clgs';

    /**
     * Global permission check.
     *
     * @param  WP_REST_Request $request The current request object.
     * @return WP_Error|boolean
     */
    public function permissions_check ( $request ) {
        if ( ! current_user_can( CLGS_CAP ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view the Custom logging resources.', 'custom-logging-service' ), array( 'status' => rest_authorization_required_code() ) );
        }
        return true;
    }

    /**
     * Prepares the argument schema for bulk actions.
     *
     * @param array args Specific properties, i. e. description and action enum.
     * @param boolen list Flags whether an entries list is needed.
     * @return array Argument schema for endpoint.
     */
    protected function get_bulk_arguments ( $which, $context, $description ) {
        $actions = array_filter( clgs_get_bulk_schema( $which ), function ( $attrs ) use ( $context ) {
            return $attrs['context'] == $context;
        } );
        
        $schema =  array(
            'action' => array(
                'description' => $description,
                'type'        => 'string',
                'enum'        => array_keys( $actions ),
                'required'    => true,
                'sanitize_callback'  => 'sanitize_key',
                'validate_callback'  => array( $this, 'validate_action'),
            )
        );
        if ( 'log' == $which ) {
            $schema['entries'] = array(
                'description' => __( 'Comma-separated list of log entry ids to act on.', 'custom-logging-service' ),
                'type'        => 'string',
                'required'    => true,
                'sanitize_callback'  => array( $this, 'sanitize_entries_list'),
            );
        }

        return $schema;
    }

    /**
     * Validates the action argument.
     *
     * @param  mixed $value Value of the 'action' argument.
     * @param  WP_REST_Request $request The current request object.
     * @param  string $param Key of the parameter.
     * @return WP_Error|boolean
     */
    public function validate_action ( $value, $request, $param ) {
        if ( ! is_string( $value ) ) {
            return new WP_Error( 'rest_invalid_param', __( 'The action argument must be a string.', 'custom-logging-service' ), array( 'status' => 400 ) );
        }
    
        $attributes = $request->get_attributes();
        $args = $attributes['args'][ $param ];
    
        if ( ! in_array( $value, $args['enum'], true ) ) {
            $route = $request->get_route();
            $route_callbacks = rest_get_server()->get_routes( )[$route];
            $route_data = rest_get_server()->get_data_for_route( $route, $route_callbacks );

            $alternatives = array();
            foreach ( $route_data['endpoints'] as $endpoint ) {
                if( key_exists($param, $endpoint['args'] ) ) {
                    $enum = $endpoint['args'][ $param ]['enum'];
                    if ( in_array( $value, $enum ) ) {
                        array_push( $alternatives, $endpoint);
                    }
                }
            }

            if ( count( $alternatives ) ) {
                $allowed = array();
                foreach ( $alternatives as $endpoint ) {
                    $allowed = array_merge( $allowed, $endpoint['methods'] );
                }
                $allowed = implode( ', ', $allowed );

                return new WP_Error( 'rest_method_not_allowed', sprintf( __( '%s is only allowed with one of methods %s' ), $value, $allowed ) );
            }

            return new WP_Error( 'rest_invalid_param', sprintf( __( '%s is not one of %s' ), $param, implode( ', ', $args['enum'] ) ) );
        }

		return true;
    }

    /**
     * Sanitizes and validates the entries argument.
     *
     * @param  mixed $value Value of the 'entries' argument.
     * @param  WP_REST_Request $request The current request object.
     * @param  string $param Key of the parameter.
     * @return WP_Error|array[int]
     */
    public function sanitize_entries_list ( $value, $request, $param ) {
        $list = clgs_to_array( $value, true );

        if ( ! isset( $list ) ) {
            return new WP_Error( 'rest_invalid_param', __( 'The entries argument must be a list of id numbers.', 'custom-logging-service' ) );
        }
        return $list;
    }

    /**
     * Prepares an item collection before it is turned into a response collection.
     *
     * @param array[Object] $data The query result whose response is being prepared.
	 * @param WP_REST_Request $request Request object.
	 * @return array[WP_REST_Response] List of item responses.
     */
    public function prepare_collection_for_response ( $data, $request ) {
        $collection = array();

        if ( ! empty( $data ) ) {
            foreach ( $data as $item ) {
                $response = $this->prepare_item_for_response( $item, $request );
                $collection[] = $this->prepare_response_for_collection( $response );
            }
        }

        return $collection;
    }

    /**
     * Prepares the item for the response.
     *
     * @param Object $item The object whose response is being prepared.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function prepare_item_for_response( $item, $request ) {
        $schema = $this->get_item_schema( $request );
        $names = array_keys( $schema['properties'] );
        $response_data = $this->map_response_item( $names, $item );

        $response = rest_ensure_response( $response_data );
        $response->add_links( $this->prepare_links( $response_data, true ) );

        return $response;
    }

    /**
     * Concatenates path components to a URL.
     *
     * @param array[string] $parts Array of path components.
     * @return string Full URL to the endpoint.
     */
    public function prepare_link_url ( $parts = array() ) {
        array_unshift( $parts, self::NSPACE );
        return rest_url( implode( '/', $parts ) );
    }

    /**
     * sets the X-WP-Total headers for a collection response.
     *
     * @param WP_REST_Response $response The response object.
     * @param int $total Total entries.
     * @param int $page4s Total pages.
     * @return void
     */
    protected function set_count_headers ( $response, $total, $pages ) {
        $response->header( 'X-WP-Total', (int) $total );
        $response->header( 'X-WP-TotalPages', (int) $pages );
    }
}