<?php

use Brain\Monkey\Functions;

class Clgs_Test_Settings extends CLgs_UnitTestCase {

    function test_settings_structure () {
        $columns = [
            'notification_severity_filter',
            'def_severity_filter',
            'manager_role',
            'log_entries_per_page',
        ];
        $enum =  ['sanitize', 'validate', 'desc'];
        
        $struct = clgs_get_settings_structure();

        foreach ( $columns as $key ) {
            $this->assertArrayHasKey( $key, $struct, "structure misses $key" );
            foreach ( $struct[$key] as $attr => $value ) {
                $this->assertContains( $attr, $enum, "$key contains $attr" );
                $this->assertInternalType( 'string', $value, $value, "$attr is not a string" );
            }
        }
    }

    function test_add_settings () {
        global $wp_roles;

        $settings = [
            'manager_role' => ['role1', 'role2'],
            'log_entries_per_page' => 100
        ];
        Functions::when('clgs_get_settings')->justReturn($settings);

        $wp_roles = Mockery::mock('WP_Roles');
        $wp_roles->shouldReceive('add_cap')->with('role1', CLGS_CAP)->once();
        $wp_roles->shouldReceive('add_cap')->with('role2', CLGS_CAP)->once();

        Functions::expect('add_site_option')->with(CLGS_SETTINGS, $settings)->once();

        clgs_add_settings( false );
    }

    function page_output_inspector ($out) {
        $list = $this->get_DOM_Nodes($out);
        $this->assertEquals(1, $list->length);
        $wrap =$list[0];
        $this->assertEquals('div', $wrap->nodeName);
        $this->compare_attributes([
            'class' => 'wrap'
        ], $wrap->attributes);
        $this->assertEquals(2, $wrap->childNodes->length);

        $heading = $wrap->childNodes[0];
        $this->assertEquals('h1', $heading->nodeName);
        $this->assertRegExp('/\w+/', $heading->textContent);

        $form = $wrap->childNodes[1];
        $this->assertEquals('form', $form->nodeName);
        $this->compare_attributes([
            'method' => 'post',
            'action' => 'options.php'
        ], $form->attributes);
    }

    function test_settings_page () {
        Functions::expect('settings_fields')->with(CLGS_SETTINGS)->ordered()->once();
        Functions::expect('do_settings_sections')->with(CLGS_OPTION_PAGE)->ordered()->once();
        Functions::expect('submit_button')->with()->ordered()->once();

        $this->setOutputCallback (array($this, 'page_output_inspector'));

        clgs_settings_page();
    }

    function test_settings_init () {
        global $wp_version;

        Functions::expect('clgs_settings_defaults')->once()->andReturn('defaults');

        Functions::expect('register_setting')
            ->with(CLGS_SETTINGS, CLGS_SETTINGS, 'clgs_sanitize_settings')->once();

        Functions::expect('add_settings_section')
            ->with(CLGS_GROUP, null, null, CLGS_OPTION_PAGE)->twice();

        $struct = [
            'first' => [ 'desc' => 'one setting'],
            'two'   => [ 'desc' => 'two settings', 'extra' => 'fine'],
            'three' => [ 'desc' => 'three settings'],
        ];
        Functions::when('clgs_get_settings_structure')->justReturn($struct);
        foreach ($struct as $key => $value) {
            Functions::expect('add_settings_field')->with(
                $key, $value['desc'], 'clgs_field_render',
                CLGS_OPTION_PAGE, CLGS_GROUP, [$key]
            )->twice();
        }

        $wp_version = '4.6';
        clgs_settings_init();

        Functions::expect('register_setting')
            ->with(CLGS_SETTINGS, CLGS_SETTINGS, [
                'sanitize_callback' => 'clgs_sanitize_settings',
                'default' => 'defaults'
            ])->once();

        $wp_version = '4.7';
        clgs_settings_init();
    }

    function settings_data_provider () {
        return [
            [['something' => 'given', 'manager_role' => 'role1'], false],
            [['something' => 'taken', 'manager_role' => 'role2'], false],
            [['something' => 'taken', 'manager_role' => 'role1'], 'manager_role'],
        ];
    }

    /**
     * @dataProvider settings_data_provider
     */
    function test_sanitize_settings ( $input, $invalid) {
        $struct = [
            'something' => ['sanitize' => 'sanitize_some', 'validate' => 'validate_some', 'desc' => 'a something'],
            'manager_role' => ['validate' => 'validate_role', 'desc' => 'a role']
        ];
        Functions::when('clgs_get_settings_structure')->justReturn($struct);

        $original =[
            'something' => 'given',
            'manager_role' => 'role2'
        ] ;
        Functions::when('clgs_get_settings')->justReturn($original);

        Functions::expect('clgs_to_array')
            ->with($input['manager_role'])->once()
            ->andReturn($input['manager_role']);
        Functions::expect('clgs_sanitize')
            ->with($input['something'], 'sanitize_some')->once()
            ->andReturn($input['something']);

        Functions::expect('clgs_validate')
            ->with($input['something'], 'validate_some')->once()
            ->andReturn($invalid != 'something');
        Functions::expect('clgs_validate')
            ->with($input['manager_role'], 'validate_role')->once()
            ->andReturn($invalid != 'manager_role');

        if ($invalid) {
            Functions::expect('add_settings_error')
                ->with(CLGS_SETTINGS, 'clgs_error', containsString($struct[$invalid]['desc']))->once();
            
            $this->assertEquals($original, clgs_sanitize_settings($input));
        } else {
            $this->assertEquals($input, clgs_sanitize_settings($input));
        }
    }

    function field_output_inspector_select ($out) {
        global $severity_list;

        $list = $this->get_DOM_Nodes($out);
        $this->assertEquals(1, $list->length);
        $select =$list[0];
        $this->assertEquals('select', $select->nodeName);
        $this->compare_attributes([
            'name' => CLGS_SETTINGS . "[$this->field_id]"
        ], $select->attributes);
        $this->assertEquals(3, $select->childNodes->length);

        foreach ($severity_list as $key => $value) {
            $option = $select->childNodes[$key];
            $this->assertEquals('option', $option->nodeName);
            $expected = [
                'value' => $key
            ];
            $selected = 'def_severity_filter' == $this->field_id ? 2 : 0;
            if ($selected == $key) $expected['selected'] = 'selected';
            $this->compare_attributes($expected, $option->attributes);
            $this->assertEquals($value, $option->textContent);
        }
    }

    function field_output_inspector_checkboxes ($out) {
        $list = $this->get_DOM_Nodes($out);
        $this->assertEquals(6, $list->length);

        $i = 0;
        foreach ($this->roles as $key => $value) {
            $label = $list[$i++];
            $this->assertEquals('label', $label->nodeName);
            $this->assertEquals(2, $label->childNodes->length);
            $this->assertEquals('br', $list[$i++]->nodeName);

            $input = $label->childNodes[0];
            $this->assertEquals('input', $input->nodeName);
            $expected = [
                'type' => 'checkbox',
                'name' => CLGS_SETTINGS . '[manager_role][]',
                'value' => $key
            ];
            if ('administrator' == $key) $expected['checked'] = '';
            $this->compare_attributes($expected, $input->attributes);

            $text = $label->childNodes[1];
            $this->assertEquals(XML_TEXT_NODE, $text->nodeType);
            $this->assertEquals($value, $text->textContent);
        }
    }

    function field_output_inspector_input ($out) {
        $list = $this->get_DOM_Nodes($out);
        $this->assertEquals(1, $list->length);
        $input =$list[0];
        $this->assertEquals('input', $input->nodeName);
        $this->compare_attributes([
            'type' => 'text',
            'name' => CLGS_SETTINGS . '[log_entries_per_page]',
            'value' => 100
        ], $input->attributes);
    }

    function field_data_provider () {
        return [
            ['notification_severity_filter', 'field_output_inspector_select'],
            ['def_severity_filter', 'field_output_inspector_select'],
            ['manager_role', 'field_output_inspector_checkboxes'],
            ['log_entries_per_page', 'field_output_inspector_input'],
        ];
    }

    /**
     * @dataProvider field_data_provider
     */
    function test_field_render ($id, $inspector) {
        global $severity_list, $wp_roles;

        $severity_list = ['none', 'debug', 'notice'];
        Functions::when('clgs_get_settings')->alias('clgs_settings_defaults');
        Functions::when('translate_user_role')->returnArg();

        $this->roles = [
            'administrator' => 'Adminstrator',
            'role1' => 'First Role',
            'role2' => 'Second Role'
        ];
        $wp_roles = Mockery::mock('WP_Roles');
        $wp_roles->shouldReceive('get_names')->andReturn($this->roles);


        $this->field_id = $id;
        $this->setOutputCallback (array($this, $inspector));
        clgs_field_render([$id]);
    }

    function role_data_provider () {
        return [
            [['role1', 'role2'], ['role1'], ['role2'], []],
            [['role2'], ['role1', 'role2'], [], ['role1']],
            [['role1', 'role2'], ['role2', 'role3'], ['role1'], ['role3']],
        ];
    }

    /**
     * @dataProvider role_data_provider
     */
    function test_update_capabilities ($old, $new, $removed, $added) {
        global $wp_roles;

        $wp_roles = Mockery::mock('WP_Roles');

        foreach ($removed as $role) {
            $wp_roles->shouldReceive('remove_cap')->with($role, CLGS_CAP)->once();
        }
        foreach ($added as $role) {
            $wp_roles->shouldReceive('add_cap')->with($role, CLGS_CAP)->once();
        }

        clgs_update_capabilities(['manager_role' => $old], ['manager_role' => $new]);
    }
}