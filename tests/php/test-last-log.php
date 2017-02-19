<?php

use Brain\Monkey\Functions;

class Clgs_Test_last_log extends CLgs_UnitTestCase {
    private $last_log;
    private $log;

    function setUp() {
        parent::setUp();

        $this->last_log = Mockery::mock('Clgs_last_log')->makePartial();

        $this->log = [
            'data' => ['category' => 'cat', 'date' => 123, 'text' => 'entry'],
            'entry_id' => 2,
            'count' => 3
        ];
    }

    function test_constructor () {
        Functions::expect('get_site_option')
            ->with('clgs_last_log', null)->twice()
            ->andReturn($this->log, null);

        $last_log = new Clgs_last_log();

        $this->assertEquals($this->log['data'], $last_log->data);
        $this->assertEquals($this->log['entry_id'], $last_log->entry_id);
        $this->assertEquals($this->log['count'], $last_log->count);

        $last_log = new Clgs_last_log();

        $this->assertNull($last_log->data);
        $this->assertNull($last_log->entry_id);
        $this->assertEquals(1, $last_log->count);
    }

    function test_write () {
        Functions::expect('update_site_option')
            ->with('clgs_last_log', null)->once();
        $this->last_log->data = null;

        $this->last_log->write();

        Functions::expect('update_site_option')
            ->with('clgs_last_log', $this->log)->once();
        $this->last_log->data = $this->log['data'];
        $this->last_log->entry_id = $this->log['entry_id'];
        $this->last_log->count = $this->log['count'];

        $this->last_log->write();
    }

    function test_set () {
        $this->last_log->shouldReceive('write')->once();

        $this->last_log->set('something', 6);

        $this->assertEquals('something', $this->last_log->data);
        $this->assertEquals(6, $this->last_log->entry_id);
        $this->assertEquals(1, $this->last_log->count);
    }

    function log_data_provider () {
        return [
            [false, ['category' => 'cat', 'date' => 456,  'text' => 'entry'], false],
            [true, ['category' => 'cat', 'date' => 456,  'text' => 'changed'], false],
            [true, ['category' => 'cat', 'date' => 123,  'text' => 'changed'], false],
            [true, ['category' => 'cat', 'date' => 456,  'text' => 'entry'], true],
        ];
    }

    /**
     * @dataProvider log_data_provider
     */
    function test_compare ($old, $data, $expected) {
        if ($old) {
            $this->last_log->data = $this->log['data'];
            $this->last_log->entry_id = $this->log['entry_id'];
            $this->last_log->count = $this->log['count'];
        }

        $this->assertEquals($expected, $this->last_log->compare($data));
    }

    function test_flush () {
        Functions::expect('update_site_option')
            ->with('clgs_last_log', null)->once();

        $this->last_log->flush();
    }
}