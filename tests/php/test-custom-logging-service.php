<?php

use Brain\Monkey\Functions;

class Clgs_Test_Clgs extends CLgs_UnitTestCase {
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    function test_activation () {
        global $clgs_db;

        $db = Mockery::mock('overload:Clgs_DB');
        $db->shouldReceive('create')->once();

        Functions::expect('clgs_add_settings')->once();
        Functions::expect('update_option')->once()
            ->with('clgs_version', CLGS_VERSION);

        clgs_activation();

        $this->assertInstanceOf('Clgs_DB', $clgs_db);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    function test_update () {
        global $clgs_db, $clgs_last_log;

        Mockery::mock('overload:Clgs_DB');
        Mockery::mock('overload:Clgs_last_log');

        Functions::expect('get_option')->once()->with('clgs_version');

        clgs_update();

        $this->assertInstanceOf('Clgs_DB', $clgs_db);
        $this->assertInstanceOf('Clgs_last_log', $clgs_last_log);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    function test_deactivation () {
        global $clgs_last_log, $wp_roles;

        $wp_roles = Mockery::mock('WP_Roles');
        Mockery::mock('overload:WP_Role')
            ->shouldReceive('remove_cap')->once()->with(CLGS_CAP);
        $wp_roles->role_objects = [
            'role1' => new WP_Role,
            'role2' => new WP_Role
        ];

        $clgs_last_log = Mockery::mock('Clgs_last_log');
        $clgs_last_log->shouldReceive('flush')->once();

        clgs_deactivation();
    }

    function test_uninstall () {
        global $clgs_db;

        Functions::expect('delete_option')->once()->with('clgs_version');
        Functions::expect('delete_option')->once()->with(CLGS_SETTINGS);
        Functions::expect('delete_site_option')->once()->with(CLGS_SETTINGS);
        Functions::expect('delete_site_option')->once()->with('clgs_last_log');

        $clgs_db = Mockery::mock('Clgs_DB');
        $clgs_db->shouldReceive('destroy')->once();

        clgs_uninstall();
    }

    function test_load_textdomain () {
        Functions::expect('load_plugin_textdomain')->once()->with(
            'custom-logging-service',
            false,
            '/custom-logging-service\/languages$/'
        );

        clgs_load_textdomain();
    }

    function test_admin_enqueue_styles () {
        Functions::when('clgs_get_item_schema')->justReturn([
            'one' => ['title' => 'One', 'type' => 1],
            'two' => ['title' => 'Two', 'desc' => 2],
        ]);
        Functions::when('get_rest_url')->justReturn('api/');
        
        Functions::expect('plugins_url')->once()
            ->with('style.css', '/custom-logging-service\.php$/')
            ->andReturn('style.css');
        Functions::expect('plugins_url')->once()
            ->with('includes/manager.js', '/custom-logging-service\.php$/')
            ->andReturn('includes/manager.js');
        Functions::expect('wp_create_nonce')->once()
            ->with('wp_rest')
            ->andReturn('nonce');

        Functions::expect('wp_enqueue_style')->once()->with( 'clgs-admin-style', 'style.css' );

        Functions::expect('wp_enqueue_script')->once()->with(
            'clgs-manager-script',
            'includes/manager.js',
            Mockery::contains('jquery', 'backbone'),
            false,
            true
        );

        Functions::expect('wp_localize_script')->once()->with(
            'clgs-manager-script',
            'clgs_base',
            Mockery::on(function ($props) {
                return is_array($props) &&
                       isset($props['l10n']) &&
                       isset($props['rest_base']) && 'api/clgs' == $props['rest_base'] &&
                       isset($props['nonce']) && 'nonce' == $props['nonce'] &&
                       isset($props['used_columns']) && [
                           'one' => 'One',
                           'two' => 'Two'
                       ] == $props['used_columns'];
            })
        );

        clgs_admin_enqueue_styles(); // should do nothing yet

        $_REQUEST['page'] = CLGS_LOG_PAGE;

        clgs_admin_enqueue_styles();
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    function test_admin_menu () {
        $manager = Mockery::mock('overload:Clgs_Manager');

        Functions::when('clgs_unseen_field')->justReturn('-unseen');
        Functions::when('clgs_is_network_mode')->justReturn(false);

        Functions::expect('add_dashboard_page')->once()->with(
            'Application logs',
            'Application logs-unseen',
            CLGS_CAP,
            CLGS_LOG_PAGE,
            Mockery::on(function ($callable) {
                return is_callable($callable) && 
                       is_a($callable[0], 'Clgs_Manager') &&
                       'render_page' === $callable[1];
            })
        );

        Functions::expect('add_submenu_page')->once()->with(
            'options-general.php',
            'Custom Logging Service',
            'Custom Logging Service',
            'manage_options',
            CLGS_OPTION_PAGE,
            'clgs_settings_page'
        );

        clgs_admin_menu();
    }

    function test_unseen_field () {
        Functions::when('clgs_get_unseen')->justReturn(0);
        $this->assertEquals('', clgs_unseen_field());

        Functions::when('clgs_get_unseen')->justReturn(25);
        $list = $this->get_DOM_Nodes(clgs_unseen_field());
        $this->assertEquals(1, $list->length);
        $span =$list[0];
        $this->assertEquals('span', $span->nodeName);
        $this->compare_attributes([
            'class' => 'awaiting-mod count-25',
            'aria-label' => '25 unseen Log entries'
        ], $span->attributes);
        $this->assertEquals(1, $span->childNodes->length);
        $this->assertEquals('span', $span->childNodes[0]->nodeName);
        $this->assertEquals('25', $span->childNodes[0]->textContent);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    function test_register_rest_routes () {
        require WP_DIR . '/wp-includes/rest-api/endpoints/class-wp-rest-controller.php';

        $rest_categories = Mockery::mock('overload:Clgs_REST_Categories');
        $rest_categories->shouldReceive('register_routes')->once();

        $rest_logs = Mockery::mock('overload:Clgs_REST_Logs');
        $rest_logs->shouldReceive('register_routes')->once();

        clgs_register_rest_routes(null);
    }

    function test_is_registered () {
        global $clgs_db;

        $clgs_db = Mockery::mock('Clgs_DB');
        $clgs_db->shouldReceive('is_registered')->twice()
            ->with('category')->andReturn(true, false);

        $this->assertTrue(clgs_is_registered('category'));
        $this->assertFalse(clgs_is_registered('category'));
    }

    function test_register () {
        global $clgs_db;

        Functions::expect('clgs_sanitize')->times(3)
            ->with('description', 'kses_string')->andReturn('sane_description');

        $clgs_db = Mockery::mock('Clgs_DB');
        $clgs_db->shouldReceive('is_registered')->times(3)
            ->with('category')->andReturn(true, false, false);
        $clgs_db->shouldReceive('category_fits')->times(2)
            ->with('category')->andReturn(false, true);
        $clgs_db->shouldReceive('register')->times(1)
            ->with('category', 'sane_description')->andReturn(true);

        $this->assertFalse(clgs_register('category', 'description'));
        $this->assertFalse(clgs_register('category', 'description'));
        $this->assertTrue(clgs_register('category', 'description'));
    }

    function test_unregister () {
        global $clgs_db;

        $clgs_db = Mockery::mock('Clgs_DB');
        $clgs_db->shouldReceive('bulk_category')->once()
            ->with('unregister', 'category')->andReturn(true);

        $this->assertTrue(clgs_unregister('category'));
    }

    function test_clear () {
        global $clgs_db;

        $clgs_db = Mockery::mock('Clgs_DB');
        $clgs_db->shouldReceive('bulk_category')->once()
            ->with('clear', 'category')->andReturn(true);

        $this->assertTrue(clgs_clear('category'));
    }

    function log_provider () {
        $data = [
            'category' => 'cat', 
            'message' => 'text', 
            'severity' => 2, 
            'user' => 'user1', 
            'blog_id' => 1, 
            'date' => 1234
        ];
        $args = array_values($data);
        return [[$args, $data]];
    }

    /**
     * @dataProvider log_provider
     */
    function test_log_new ($args, $data) {
        global $clgs_db, $clgs_last_log;
        
        Functions::expect('clgs_prepare_data')
            ->with($data)->times(3)
            ->andReturn(false, $data, $data);

        $this->assertFalse(call_user_func_array('clgs_log',$args));


        $clgs_last_log = Mockery::mock('Clgs_last_log');
        $clgs_last_log->shouldReceive('compare')
            ->with($data)->twice()
            ->andReturn(false);

        $clgs_db = Mockery::mock('Clgs_DB');
        $clgs_db->shouldReceive('insert_entry')
            ->with($data)->twice()
            ->andReturn(false, 32);

        $this->assertFalse(call_user_func_array('clgs_log', $args));

        $clgs_last_log->shouldReceive('set')
            ->with($data, 32)->once();

        $this->assertTrue(call_user_func_array('clgs_log', $args));
    }

    /**
     * @dataProvider log_provider
     */
    function test_log_repeat_log ($args, $data) {
        global $clgs_db, $clgs_last_log;
        
        Functions::when('clgs_prepare_data')->justReturn($data);

        $clgs_last_log = Mockery::mock('Clgs_last_log');
        $clgs_last_log->entry_id = 25;
        $clgs_last_log->data = $data;
        $clgs_last_log->data['date'] = 666;
        $clgs_last_log->count = 2;

        $clgs_last_log->shouldReceive('compare')
            ->with($data)->once()
            ->andReturn(true);
        $clgs_last_log->shouldReceive('write')->once();

        $clgs_db = Mockery::mock('Clgs_DB');
        $clgs_db->shouldReceive('update_entry')->with(25, Mockery::on(function ($update_data) use ($data) {
            foreach ($data as $key => $value) {
                if ('message' == $key) {
                    $message = $this->get_DOM_Nodes($update_data[$key]);
                    $this->assertGreaterThanOrEqual(3, $message->length);
                    $text = $message[0];
                    $this->assertEquals(XML_TEXT_NODE, $text->nodeType);
                    $this->assertContains('2×', $text->textContent);
                    $span = $message[1];
                    $this->assertEquals('span', $span->nodeName);
                    $this->compare_attributes([ 'data-date' => '666'], $span->attributes);
                    $this->assertEquals('', $span->textContent);
                    $this->assertEquals($value, $message[$message->length-1]->textContent);
                } else {
                    $this->assertEquals($value, $update_data[$key]);
                }
            }
            return true;
        }))->once()->andReturn(true);

        $this->assertTrue(call_user_func_array('clgs_log', $args));
    }
}
