<?php

use Brain\Monkey\Functions;

class Clgs_Test_Functions extends CLgs_UnitTestCase {

    function test_get_settings() {
        Functions::expect('clgs_settings_defaults')->once()->andReturn('defaults');
        Functions::expect('get_site_option')->with(CLGS_SETTINGS, [])->once()->andReturn('actual');
        Functions::expect('wp_parse_args')->with('actual', 'defaults')->once()->andReturn('merged');

        $this->assertEquals('merged', clgs_get_settings());
    }

    function sanitation_data_provider () {
        return [
            ['string', 'esc_attr', ['content'], 'content'],
            ['kses_string', 'wp_kses', ['content', ['br' => []]], 'content'],
            ['int', 'esc_attr', [123], 123],
            ['bool', 'esc_attr', [0], false],
            ['time', null, [123.4], 123]
        ];
    }

    /**
     * @dataProvider sanitation_data_provider
     */
    function test_sanitation ( $rule, $callback, $input, $expected ) {
        global $allowedtags;
        $allowedtags = [];
        
        if ($callback) {
            Functions::expect($callback)->withArgs($input)->andReturn($input[0]);
        }
         
        $this->assertSame( $expected, clgs_sanitize( $input[0], $rule ) );
    }

    function validation_data_provider () {
        global $severity_list;

        $severity_list = ['severity', 'list'];
        
        return [
            ['exists', null, ['foo']],
            ['length', null, ['foo']],
            ['severity', null, [1, $severity_list]],
            ['registered', 'is_registered', ['cat']],
            ['positive', null, [1]],
        ];
    }

    /**
     * @dataProvider validation_data_provider
     */
    function test_validation ( $rule, $callback, $input ) {
        global $clgs_db, $severity_list;

        $clgs_db = Mockery::mock('Clgs_DB');
        if ($callback) {
            $clgs_db->shouldReceive($callback)->withArgs($input)->once()->andReturn(true);
        }

        $this->assertTrue(clgs_validate( $input[0], $rule ) );
    }

    function test_validation_role () {
        global $wp_roles;

        $wp_roles = Mockery::mock('WP_Roles');

        $role_list = ['role1', 'role2', 'role3'];
        foreach($role_list as $name) {
            $wp_roles->shouldReceive('is_role')->with($name)->once()->andReturn(true);
        }

        $this->assertTrue( clgs_validate( $role_list, 'role' ) );

        $this->assertFalse( clgs_validate( null, 'role' ) );
    }

    function array_data_provider () {
        return [
            [['foo', 'bar', 'baz'], false, ['foo', 'bar', 'baz']],
            ['foo,bar,baz', false, ['foo', 'bar', 'baz']],
            [23, false, null],
            [['1', '2', '3'], true, [1, 2, 3]],
            ['1,2,3', false, ['1', '2', '3']],
            ['1,2,3', true, [1, 2, 3]],
            ['1,2,3', true, [1, 2, 3]],
            [[0], false, [0]],
            ['', false, []],
            [[], false, []],
        ];
    }

    /**
     * @dataProvider array_data_provider
     */
    function test_to_array ( $input, $cast, $expected ) {
         Functions::when('esc_attr')->returnArg();
         
         $this->assertEquals( $expected, clgs_to_array( $input, $cast ) );
    }

    function user_data_provider () {
         $user = Mockery::mock('WP_User');
         $user->user_login = 'login';

        return [
            [1, ['id' => $user], 'login'],
            [2, ['id' =>false], ''],
            ['name', ['login' => $user], 'login'],
            ['name', ['login' => false, 'slug' => $user], 'login'],
            ['name', ['login' => false, 'slug' => false], ''],
        ];
    }

    /**
     * @dataProvider user_data_provider
     */
    function test_to_user ($value, $bys, $expected) {
         foreach($bys as $type => $answer) {
             Functions::expect('get_user_by')->with($type, $value)->once()->andReturn($answer);
         }

         $this->assertEquals($expected, clgs_to_user($value));
    }

    function get_api_schema() {
        return [
            'message' => [ 'db_key' => 'text','sanitize' => 'kses_string','validate' => 'length' ],
            'severity' => [ 'db_key' => 'severity', 'sanitize' => 'int','validate' => 'severity','default'  => 0 ],
            'category' => [ 'db_key' => 'category', 'sanitize' => 'string', 'validate' => 'registered' ],
            'date' => [ 'db_key' => 'date', 'sanitize' => 'time', 'default' => 4567 ],
            'user' => [ 'db_key' => 'user_name', 'validate' => 'exists', 'default' => 'user2'],
            'blog_id' => [ 'db_key' => 'blog_id', 'sanitize' => 'int', 'validate' => 'positive', 'default' => 1 ]
        ];
    }

    function log_data_provider () {
        return [
            [
                ['category' => 'cat', 'message' => 'text', 'severity' => 2, 'user' => 'user1', 'blog_id' => 3, 'date' => 1234],
                [], false,
                ['category' => 'cat', 'text' => 'text', 'severity' => 2, 'user_name' => 'user1', 'blog_id' => 3, 'blog_name' => 'blog', 'date' => 1234]
            ],
            [
                ['category' => 'cat', 'message' => 'text'],
                ['severity', 'user', 'blog_id', 'date'], false,
                ['category' => 'cat', 'text' => 'text', 'severity' => 0, 'user_name' => 'user2', 'blog_id' => 1, 'blog_name' => 'blog', 'date' => 4567]
            ],
            [
                ['category' => 'cat', 'message' => 'text', 'severity' => 2],
                ['user', 'blog_id', 'date'], false,
                ['category' => 'cat', 'text' => 'text', 'severity' => 2, 'user_name' => 'user2', 'blog_id' => 1, 'blog_name' => 'blog', 'date' => 4567]
            ],
            [
                ['category' => 'cat', 'message' => 'text', 'user' => 'user1'],
                ['severity', 'blog_id', 'date'], false,
                ['category' => 'cat', 'text' => 'text', 'severity' => 0, 'user_name' => 'user1', 'blog_id' => 1, 'blog_name' => 'blog', 'date' => 4567]
            ],
            [
                ['category' => 'cat', 'message' => 'text', 'blog_id' => 3],
                ['severity', 'user', 'date'], false,
                ['category' => 'cat', 'text' => 'text', 'severity' => 0, 'user_name' => 'user2', 'blog_id' => 3, 'blog_name' => 'blog', 'date' => 4567]
            ],
            [
                ['category' => 'cat', 'message' => 'text', 'date' => 1234],
                ['severity', 'user', 'blog_id'], false,
                ['category' => 'cat', 'text' => 'text', 'severity' => 0, 'user_name' => 'user2', 'blog_id' => 1, 'blog_name' => 'blog', 'date' => 1234]
            ],
            [
                ['category' => 'cat', 'message' => 'text'],
                ['severity', 'user', 'blog_id', 'date'], 'category', false
            ],
        ];
    }

    /**
     * @dataProvider log_data_provider
     */
    function test_prepare_data ($args, $defaults, $invalid, $expected) {
        $schema = $this->get_api_schema();

        Functions::expect('clgs_get_item_schema')->with('api')->andReturn($schema);

        foreach ($schema as $key => $rule) {
            if (in_array($key, $defaults)) {
                $value = $rule['default'];
            } elseif ('user' == $key) {
                $value = $args[$key];
                $to_user = Functions::expect('clgs_to_user')
                    ->with($value)->once()
                    ->andReturn($value);
            } else {
                $value = $args[$key];
                $sanitize = Functions::expect('clgs_sanitize')
                    ->with($value, $rule['sanitize'])->once()
                    ->andReturn($value);
            }

            $validate = Functions::expect('clgs_validate');
            if ( 'date' == $key ) {
                $validate->never();
            } elseif (!$invalid) {
                $validate->with($value, $rule['validate'])
                    ->once()->andReturn(true);
            } elseif ($key == $invalid) {
                $validate->with($value, $rule['validate'])
                    ->once()->andReturn(false);
            } else {
                $validate->with($value, $rule['validate'])
                    ->atMost(1)->andReturn(true);
            }
        }

        Functions::when('get_bloginfo')->justReturn('blog');

        $this->assertEquals($expected, clgs_prepare_data($args));
    }

    function test_prepare_data_invalid () {
        $schema = $this->get_api_schema();
        $args = ['category' => 'cat', 'message' => 'text'];

        Functions::expect('clgs_get_item_schema')->with('api')->andReturn($schema);

        foreach ($schema as $key => $rule) {
            if (in_array($key, ['severity', 'user', 'blog_id', 'date'])) {
                $value = $rule['default'];
            } else {
                $value = $args[$key];
                $sanitize = Functions::expect('clgs_sanitize')
                    ->with($value, $rule['sanitize'])->atMost(1)
                    ->andReturn($value);
            }
        }

        Functions::expect('clgs_validate')->andReturn(true, false);

        Functions::when('get_bloginfo')->justReturn('blog');

        $this->assertFalse(clgs_prepare_data($args));
    }

    function log_data_incomplete_provider () {
        return [
            [ ['category' => 'cat', 'severity' => 2] ],
            [ ['message' => 'text'] ],
        ];
    }


    /**
     * @dataProvider log_data_incomplete_provider
     */
    function test_prepare_data_incomplete ($args) {
        $schema = $this->get_api_schema();

        Functions::expect('clgs_get_item_schema')->with('api')->andReturn($schema);

        foreach ($schema as $key => $rule) {
            if (in_array($key, ['severity', 'user', 'blog_id', 'date'])) {
                $value = $rule['default'];
            } elseif (isset($args[$key])) {
                $value = $args[$key];
                $sanitize = Functions::expect('clgs_sanitize')
                    ->with($value, $rule['sanitize'])->atMost(1)
                    ->andReturn($value);
            }
        }

        Functions::when('clgs_validate')->justReturn(true);
        Functions::when('get_bloginfo')->justReturn('blog');

        $this->assertFalse(clgs_prepare_data($args));
    }

    function map_data_provider () {
        $time = (int)time();

        $item = (object)[
            'id' => 2,
            'category' => 'Category',
            'blog_id' => 1,
            'blog_name' => 'a blog',
            'date' => $time,
            'user_name' => 'logger',
            'severity' => 0,
            'text' => 'message',
            'seen' => 1,
        ];
        
        return [
            ['id', 2, $item],
            ['category', 'Category', $item],
            ['seen', true, $item],
            ['date', '<span data-date="' . $time . '"></span>', $item],
            ['user', 'the_user', $item],
            ['avatar', 'the_avatar', $item],
            ['blog', '<a href="url" >a blog</a>', $item],
            ['severity', 'none', $item],
            ['message', 'message', $item],
        ];
    }

    /**
     * @dataProvider map_data_provider
     */
    function test_map_item ( $key, $value, $item ) {
        global $severity_list;

        $severity_list = ['none'];

        $user = (object)['display_name' => 'the_user'];
        Functions::expect('get_user_by')->with('login', $item->user_name)->once()->andReturn($user);

        switch ($key) {
            case 'avatar':
            Functions::expect('get_avatar')
                ->with($user, both(intValue())->andAlso(greaterThan(1)))->once()
                ->andReturn('the_avatar');
            break;
            case 'blog':
            Functions::expect('get_blogaddress_by_id')
                ->with(1)->once()->andReturn('url');
            break;
        }
        
        $this->assertEquals([$key => $value], clgs_map_item([$key], $item));
    }

    function test_get_unseen () {
        global $clgs_db;
        
        Functions::when('clgs_get_settings')->justReturn(['notification_severity_filter'=>1]);

        $clgs_db = Mockery::mock('Clgs_DB');
        $clgs_db->shouldReceive('get_entries')
            ->with(['seen'=>false, 'min_severity'=>1], true)
            ->andReturn((object)['total'=>5]);

        $this->assertEquals(5, clgs_get_unseen());
    }
}