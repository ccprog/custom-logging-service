<?php

use Brain\Monkey\Functions;

class Clgs_Test_Schemas extends CLgs_UnitTestCase {

    function setUp () {
        parent::setUp();

        global $severity_list;
        $severity_list = [];

        Functions::when('is_user_logged_in')->justReturn(false);
        Functions::when('get_current_blog_id')->justReturn(1);
    }

    function pupose_provider () {
        return [
            'api' => [
                'api',
                ['message', 'severity', 'category', 'date', 'user', 'blog_id'],
                ['db_key' => 'string', 'sanitize' => 'string', 'validate' => 'string', 'default' => 'mixed']
            ],
            'rest' => [
                'rest',
                ['id', 'message', 'severity', 'category', 'date', 'seen', 'user', 'avatar'],
                ['title' => 'string', 'description' => 'string', 'type' => 'string', 'enum' => 'array', 'maxLength' => 'integer']
            ],
            'column' => [
                'column',
                ['id', 'message', 'severity', 'category', 'date', 'user'],
                ['title' => 'string', 'primary' => 'boolean', 'target' => 'string', 'desc_first' => 'boolean']
            ],
        ];
    }

    /**
     * @dataProvider pupose_provider
     */
    function test_item_schema ( $purpose, $columns, $enum ) {
        $schema = clgs_get_item_schema( $purpose );

        foreach ( $columns as $key ) {
            $this->assertArrayHasKey( $key, $schema );
            foreach ( $schema[$key] as $attr => $value ) {
                $this->assertContains( $attr, array_keys($enum), "$key contains $attr for $purpose" );
                if ( 'mixed' != $enum[$attr] ) {
                    $this->assertInternalType( $enum[$attr], $value, "$attr is not a $enum[$attr]" );
                }
                
            }
        }
    }

    function test_api_sanitation_methods ( ) {
        $sanitize_methods = ['string', 'kses_string', 'int', 'bool', 'time'];

        $schema = clgs_get_item_schema( 'api' );

        foreach ( $schema as $key => $attrs ) {
            if ( isset( $attrs['sanitize'] ) ) {
                $method = $attrs['sanitize'];
                $this->assertContains( $method, $sanitize_methods, "$key calls sanitize with $method");
            }
        }
    }

    function test_api_validation_methods ( ) {
        $validate_methods = ['exists', 'length', 'severity', 'role', 'registered', 'positive'];

        $schema = clgs_get_item_schema( 'api' );

        foreach ( $schema as $key => $attrs ) {
            if ( isset( $attrs['validate'] ) ) {
                $method = $attrs['validate'];
                $this->assertContains( $method, $validate_methods, "$key calls validate with $method");
            }
        }
    }

    function bulk_provider () {
        return [
            'category' => [
                'category',
                ['mark-seen', 'clear', 'unregister']
            ],
            'logs' => [
                'logs',
                ['mark-seen', 'delete']
            ],
        ];
    }

    /**
     * @dataProvider bulk_provider
     */
    function test_bulk_schema ( $which, $columns ) {
        $enum =  ['title', 'description', 'context'];
        
        $schema = clgs_get_bulk_schema( $which );

        foreach ( $columns as $key ) {
            $this->assertArrayHasKey( $key, $schema, "$which schema misses $key" );
            foreach ( $schema[$key] as $attr => $value ) {
                $this->assertContains( $attr, $enum, "$key contains $attr for $which" );
                $this->assertInternalType( 'string', $value, $value, "$attr is not a string" );
            }
        }
    }
}

