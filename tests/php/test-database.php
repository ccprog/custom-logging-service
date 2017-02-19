<?php

use Brain\Monkey\Functions;
use PhpMyAdmin\SqlParser\Statements;
use PhpMyAdmin\SqlParser\Components;

class Clgs_Test_DB extends CLgs_UnitTestCase {
    private $wpdb;
    private $clgs_db;

    function setUp() {
        parent::setUp();

        global $wpdb;

        require_once(__DIR__ . '/includes/better-mockery.php');
        $this->wpdb = $wpdb = BetterMockery::mock('WPDB');
        $wpdb->base_prefix = 'wp_';
        $this->clgs_db = new Clgs_DB;
    }

    function prepare_for_real($query, $args) {
        if ( is_null( $query ) )
            return;
    
        $args = func_get_args();
        array_shift( $args );
        if ( isset( $args[0] ) && is_array($args[0]) )
            $args = $args[0];
        $query = str_replace( "'%s'", '%s', $query );
        $query = str_replace( '"%s"', '%s', $query );
        $query = preg_replace( '|(?<!%)%f|' , '%F', $query );
        $query = preg_replace( '|(?<!%)%s|', "'%s'", $query );
        return @vsprintf( $query, $args );
    }

    private function init_director ($method) {
        $director = new BetterExpectationDirector($method, $this->wpdb);
        $this->wpdb->mockery_setExpectationsFor($method, $director);
        return $director;
    }

    function dump_parsed ($string) {
        $parsed = new PhpMyAdmin\SqlParser\Parser($string);
        print_r($parsed->statements[0]);
        return true;
    }

    function string_data_provider () {
        return [
            ['', false],
            [str_repeat('x', 190), true], // UTF-8: 0x78
            [str_repeat('x', 191), false],
            [str_repeat('■', 190), true], // UTF-8: 0xE2 0x96 0xA0
            [str_repeat('■', 191), false],
        ];
    }

    /**
     * @dataProvider string_data_provider
     */
    function test_category_fits ($str, $fits) {
//         $this->markTestSkipped();
       
        $this->assertEquals($fits, $this->clgs_db->category_fits($str));
    }

    function test_create () {
        global $charset_collate;

        $charset_collate = 'COLLATE utf8_general_ci';

        $statement_logs = new Statements\CreateStatement();
        $statement_logs->options = new Components\OptionsArray(['TABLE']);
        $statement_logs->name = new Components\Expression('wp_clgs_logs');
        $statement_logs->fields = [
            new Components\CreateDefinition(
                'category',
                new Components\OptionsArray(['NOT NULL']),
                new Components\DataType('varchar', ['190'])
            ),
            new Components\CreateDefinition(
                'description',
                null,
                new Components\DataType('longtext')
            ),
            new Components\CreateDefinition(
                null,
                null,
                new Components\Key(null, [['name'=>'category']], 'PRIMARY KEY')
            ),
        ];
        $statement_logs->entityOptions = new Components\OptionsArray([
            'name' => 'COLLATE',
            'expr' => 'utf8_general_ci'
        ]);

        $statement_entries = new Statements\CreateStatement();
        $statement_entries->options = new Components\OptionsArray(['TABLE']);
        $statement_entries->name = new Components\Expression('wp_clgs_entries');
        $statement_entries->fields = [
            new Components\CreateDefinition(
                'id',
                new Components\OptionsArray(['NOT NULL', 'auto_increment']),
                new Components\DataType('INT')
            ),
            new Components\CreateDefinition(
                'category',
                new Components\OptionsArray(['NOT NULL']),
                new Components\DataType('varchar', ['190'])
            ),
            new Components\CreateDefinition(
                'blog_id',
                new Components\OptionsArray(['NOT NULL']),
                new Components\DataType('int')
            ),
            new Components\CreateDefinition(
                'blog_name',
                new Components\OptionsArray(['NOT NULL']),
                new Components\DataType('longtext')
            ),
            new Components\CreateDefinition(
                'date',
                new Components\OptionsArray(['NOT NULL']),
                new Components\DataType('bigint')
            ),
            new Components\CreateDefinition(
                'user_name',
                new Components\OptionsArray(['NOT NULL']),
                new Components\DataType('varchar', ['255'])
            ),
            new Components\CreateDefinition(
                'severity',
                new Components\OptionsArray(['NOT NULL']),
                new Components\DataType('int')
            ),
            new Components\CreateDefinition(
                'text',
                null,
                new Components\DataType('longtext')
            ),
            new Components\CreateDefinition(
                'seen',
                new Components\OptionsArray([
                    'NOT NULL',
                    ['name' => 'DEFAULT', 'expr' => new Components\Expression('false')]
                ]),
                new Components\DataType('bool')
            ),
            new Components\CreateDefinition(
                null,
                null,
                new Components\Key(null, [['name'=>'id']], 'PRIMARY KEY')
            ),
            new Components\CreateDefinition(
                null,
                null,
                new Components\Key(null, [['name'=>'category']], 'KEY')
            ),
            new Components\CreateDefinition(
                null,
                null,
                new Components\Key(null, [['name'=>'date']], 'KEY')
            ),
        ];
        $statement_entries->entityOptions = new Components\OptionsArray([
            'name' => 'COLLATE',
            'expr' => 'utf8_general_ci'
        ]);

        Functions::expect('dbDelta')->with(new SQLParserMatch($statement_logs))->once();
        Functions::expect('dbDelta')->with(new SQLParserMatch($statement_entries))->once();

        $this->clgs_db->create();
    }

    function test_destroy () {
        $statement_logs = new Statements\DropStatement();
        $statement_logs->fields = [ new Components\Expression('wp_clgs_logs') ];

        $statement_entries = new Statements\DropStatement();
        $statement_entries->fields = [ new Components\Expression('wp_clgs_entries') ];

        $director_query = $this->init_director('query');
        $director_query->addNewExpectation()
            ->with(new SQLParserMatch($statement_logs))->once();
        $director_query->addNewExpectation()
            ->with(new SQLParserMatch($statement_entries))->once();

        $this->clgs_db->destroy();
    }

    function test_get_logs () {
        $statement = new Statements\SelectStatement();
        $statement->expr = [ new Components\Expression('*') ];
        $statement->from = [ new Components\Expression('wp_clgs_logs') ];

        $this->init_director('get_results')
            ->addNewExpectation()
            ->with(new SQLParserMatch($statement))->once()
            ->andReturn('answer');
        
        $this->assertEquals('answer', $this->clgs_db->get_logs());
    }

    function test_get_log () {
        $statement = new Statements\SelectStatement();
        $statement->expr = [ new Components\Expression('*') ];
        $statement->from = [ new Components\Expression('wp_clgs_logs') ];
        $statement->where = [ new Components\Condition('category = \'category\'') ];

        $this->wpdb->shouldReceive('prepare')
            ->with(typeOf('string'), 'category')->once()
            ->andReturnUsing(array($this, 'prepare_for_real'));

        $this->init_director('get_row')
            ->addNewExpectation()
            ->with(new SQLParserMatch($statement))->once()
            ->andReturn('answer');
        
        $this->assertEquals('answer', $this->clgs_db->get_log('category'));
    }

    function test_is_registered () {
        $statement = new Statements\SelectStatement();
        $statement->expr = [ new Components\Expression('count(*)') ];
        $statement->from = [ new Components\Expression('wp_clgs_logs') ];
        $statement->where = [ new Components\Condition('category = \'category\'') ];

        $this->wpdb->shouldReceive('prepare')
            ->with(typeOf('string'), 'category')->twice()
            ->andReturnUsing(array($this, 'prepare_for_real'));

        $this->init_director('get_var')
            ->addNewExpectation()
            ->with(new SQLParserMatch($statement))->twice()
            ->andReturn(1, 0);

        $this->assertTrue($this->clgs_db->is_registered('category'));
        $this->assertFalse($this->clgs_db->is_registered('category'));
    }

    function test_register () {
        $data = [
            'category' => 'category',
            'description' => 'desc'
        ];
        $this->wpdb->shouldReceive('insert')
            ->with('wp_clgs_logs', $data)->once()
            ->andReturn('answer');
        
        $this->assertEquals('answer', $this->clgs_db->register('category', 'desc'));
    }

    function test_bulk_category_mark_seen () {
        global $clgs_last_log;

        $clgs_last_log = Mockery::mock('Clgs_last_log');
        $clgs_last_log->shouldReceive('flush')->once();

        $data = [ 'seen' => true ];
        $where = [ 'category' => 'cat' ];
        $this->wpdb->shouldReceive('update')
            ->with('wp_clgs_entries', $data, $where, '%d')->once()
            ->andReturn('answer');

        $this->assertEquals('answer', $this->clgs_db->bulk_category('mark-seen', 'cat'));
    }

    function test_bulk_category_delete () {
        global $clgs_last_log;

        $clgs_last_log = Mockery::mock('Clgs_last_log');
        $clgs_last_log->shouldReceive('flush')->once();

        $where = [ 'category' => 'cat' ];
        $this->wpdb->shouldReceive('delete')
            ->with('wp_clgs_entries', $where)->once()
            ->andReturn('answer');

        $this->assertEquals('answer', $this->clgs_db->bulk_category('delete', 'cat'));
    }

    function test_bulk_category_unregister () {
        global $clgs_last_log;

        $clgs_last_log = Mockery::mock('Clgs_last_log');
        $clgs_last_log->shouldReceive('flush')->twice();

        $where = [ 'category' => 'cat' ];
        $this->wpdb->shouldReceive('delete')
            ->with('wp_clgs_entries', $where)->twice()
            ->andReturn('answer', false);
        $this->wpdb->shouldReceive('delete')
            ->with('wp_clgs_logs', $where)->once()
            ->andReturn('answer');

        $this->assertEquals('answer', $this->clgs_db->bulk_category('unregister', 'cat'));
        $this->assertEquals(false, $this->clgs_db->bulk_category('unregister', 'cat'));
    }

    function entry_data_provider () {
        $default = [
            'category' => 'cat',
            'blog_id' => 2,
            'blog_name' => 'blog',
            'date' => 123345,
            'user_name' => 'user',
            'severity' =>  0,
            'text' => 'message'
        ];
        return [
            [$default, $default, 5],
            [[
                'category' => 'cat',
                'blog_name' => 'blog',
                'user_name' => 'user',
            ], [
                'category' => 'cat',
                'blog_name' => 'blog',
                'user_name' => 'user',
            ], 5],
            [[
                'user_name' => 'user',
                'blog_name' => 'blog',
                'category' => 'cat',
            ], [
                'category' => 'cat',
                'blog_name' => 'blog',
                'user_name' => 'user',
            ], 5],
            [$default, $default, false],
        ];
    }

    /**
     * @dataProvider entry_data_provider
     */
    function test_insert_entry ($data, $sorted, $id) {
        $formats = ['%s', '%d', '%s', '%d', '%s', '%d', '%s'];
        if ($id) {
            $this->wpdb->insert_id = $id;
            $this->wpdb->shouldReceive('insert')
                ->with('wp_clgs_entries', $sorted, $formats)->once()
                ->andReturn(1);
        } else {
            $this->wpdb->shouldReceive('insert')
                ->with('wp_clgs_entries', $sorted, $formats)->once()
                ->andReturn(false);
        }

        $this->assertEquals($id, $this->clgs_db->insert_entry($data));
    }

    /**
     * @dataProvider entry_data_provider
     */
    function test_update_entry ($data, $sorted, $id) {
        $formats = ['%s', '%d', '%s', '%d', '%s', '%d', '%s', '%d'];
        if ($id) {
            $position = ['id' => $id];
            $sorted['seen'] = false;
            $this->wpdb->shouldReceive('update')
                ->with('wp_clgs_entries', $sorted, $position, $formats, '%d')->once()
                ->andReturn('answer');

            $this->assertEquals('answer', $this->clgs_db->update_entry($id, $data));
        }
    }

    function query_where_data_provider () {
        return [
            [
                [],
                []
            ],
            [
                ['foo'=>'bar', 'quux'=>null],
                []
            ],
            [
                ['foo'=>'bar', 'category'=>'cat'],
                [['category = \'cat\'', 'cat']]
            ],
            [
                ['id'=>20],
                [['id = 20', 20]]
            ],
            [
                ['blog_id'=>1, 'severity'=>3, 'seen'=>1, 'category'=>'cat'],
                [['blog_id = 1', 1], ['severity = 3', 3], ['category = \'cat\'', 'cat']]
            ],
            [
                ['min_severity'=>1],
                [['severity >= 1', 1]]
            ],
            [
                ['min_severity'=>0],
                []
            ],
            [
                ['seen'=>0],
                [['seen = 0', 0]]
            ],
        ];
    }

    /**
     * @dataProvider query_where_data_provider
     */
    function test_get_entries_where ($where, $clauses) {
        $statement_select = new Statements\SelectStatement();
        $statement_select->expr = [ new Components\Expression('*') ];
        $statement_select->from = [ new Components\Expression('wp_clgs_entries') ];

        $director_get_results = $this->init_director('get_results');

        $method_select = $director_get_results->addNewExpectation()
            ->with(new SQLParserMatch($statement_select));

        $statement_where = new Statements\SelectStatement();
        $statement_where->expr = [ new Components\Expression('*') ];
        $statement_where->from = [ new Components\Expression('wp_clgs_entries') ];
        $statement_where->where = [];

        $valid = array();
        foreach ($clauses as $i => $clause) {
            if ($i > 0) {
                $condition = new Components\Condition('AND');
                $condition->isOperator = true;
                $statement_where->where[] = $condition;
            }
            $statement_where->where[] = new Components\Condition($clause[0]);
            array_push($valid, $clause[1]);
        }

        $method_where = $director_get_results->addNewExpectation()
            ->with( new SQLParserMatch($statement_where));

        if (count($statement_where->where)) {
            $this->wpdb->shouldReceive('prepare')
                ->with(typeOf('string'), $valid)->once()
                ->andReturnUsing(array($this, 'prepare_for_real'));

            $method_where->once()->andReturn('answer');
            $method_select->never();
        } else {
            $method_where->never();
            $method_select->once()->andReturn('answer');
        }

        $this->assertEquals('answer', $this->clgs_db->get_entries($where, false));
    }

    /**
     * @dataProvider query_where_data_provider
     */
    function test_get_entries_count ($where, $clauses) {
        global $severity_list;
        
        $statement = new Statements\SelectStatement();
        $statement->expr = [];
        $statement->from = [ new Components\Expression('wp_clgs_entries') ];

        $cols = array_values($severity_list);
        array_push($cols, 'total');
        foreach($cols as $name) {
            $statement->expr[] = new Components\Expression(null, null, null, $name);
        }

        $this->wpdb->shouldReceive('prepare')
            ->with(typeOf('string'), typeOf('array'))->atMost(1)
            ->andReturnUsing(array($this, 'prepare_for_real'));

        $director_get_row = $this->init_director('get_row');

        $director_get_row->addNewExpectation()
            ->with(new SQLParserMatch($statement))->once()
            ->andReturn('answer');

        $st_l = new Statements\SelectStatement();
        $st_l->limit = new Components\Limit();
        $director_get_row->addNewExpectation()
            ->with(new SQLParserMatch($st_l))->never();

        $st_o = new Statements\SelectStatement();
        $st_o->order = [ new Components\OrderKeyword() ];
        $director_get_row->addNewExpectation()
            ->with(new SQLParserMatch($st_o))->never();

        $this->assertEquals('answer', $this->clgs_db->get_entries($where, true,
                ['offset'=>10, 'rowcount'=>20], ['by'=>'col', 'dir'=>'ASC']));
    }

    function query_limit_data_provider () {
        return [
            [null],
            [['offset'=>0, 'rowcount'=>0]],
            [['offset'=>20, 'rowcount'=>10]]
        ];
    }

    /**
     * @dataProvider query_limit_data_provider
     */
    function test_get_entries_limit ($limit) {
        $statement_select = new Statements\SelectStatement();
        $statement_select->expr = [ new Components\Expression('*') ];
        $statement_select->from = [ new Components\Expression('wp_clgs_entries') ];

        $director_get_results = $this->init_director('get_results');

        $method_select = $director_get_results->addNewExpectation()
            ->with(new SQLParserMatch($statement_select));

        $statement_limit = new Statements\SelectStatement();
        $statement_limit->expr = [ new Components\Expression('*') ];
        $statement_limit->from = [ new Components\Expression('wp_clgs_entries') ];
        $statement_limit->limit = new Components\Limit();
        if ($limit) {
            $this->wpdb->shouldReceive('prepare')
                ->with(typeOf('string'), [$limit['offset'], $limit['rowcount']])->once()
                ->andReturnUsing(array($this, 'prepare_for_real'));
            
            $statement_limit->limit = new Components\Limit($limit['rowcount'], $limit['offset']);
            $statement_limit->limit->rowCount = 15;

            $director_get_results->addNewExpectation()
                ->with(new SQLParserMatch($statement_limit))->once()
                ->andReturn('answer');
            $method_select->never();
        } else {
            $director_get_results->addNewExpectation()
                ->with(new SQLParserMatch($statement_limit))->never();
            $method_select->once()->andReturn('answer');
        }

        $this->assertEquals('answer', $this->clgs_db->get_entries([], false, $limit));
    }

    function query_order_data_provider () {
        return [
            [null],
            [['by'=>'col', 'dir'=>'ASC']]
        ];
    }

    /**
     * @dataProvider query_order_data_provider
     */
    function test_get_entries_order ($order) {
        $statement_select = new Statements\SelectStatement();
        $statement_select->expr = [ new Components\Expression('*') ];
        $statement_select->from = [ new Components\Expression('wp_clgs_entries') ];

        $this->wpdb->shouldReceive('prepare')->never();

        $director_get_results = $this->init_director('get_results');

        $method_select = $director_get_results->addNewExpectation()
            ->with(new SQLParserMatch($statement_select));

        $statement_order = new Statements\SelectStatement();
        $statement_order->order = [ new Components\OrderKeyword() ];
        if ($order) {
            $statement_order->order = [
                new Components\OrderKeyword(
                    new Components\Expression($order['by']),
                    $order['dir']
                ),
                new Components\OrderKeyword(
                    new Components\Expression('id'),
                    $order['dir']
                )
            ];

            $director_get_results->addNewExpectation()
                ->with(new SQLParserMatch($statement_order))->once()
                ->andReturn('answer');
            $method_select->never();
        } else {
            $director_get_results->addNewExpectation()
                ->with(new SQLParserMatch($statement_order))->never();
            $method_select->once()->andReturn('answer');
        }

        $this->assertEquals('answer', $this->clgs_db->get_entries([], false, null, $order));
    }

    function test_bulk_entries_delete () {
        global $clgs_last_log;

        $clgs_last_log = Mockery::mock('Clgs_last_log');
        $clgs_last_log->shouldReceive('flush')->once();

        $statement = new Statements\DeleteStatement();
        $statement->from = [ new Components\Expression('wp_clgs_entries') ];
        $statement->where = [ new Components\Condition('id IN (1,2,3)')];

        $this->init_director('query')
            ->addNewExpectation()
            ->with(new SQLParserMatch($statement))->once()
            ->andReturn('answer');

        $this->assertEquals('answer', $this->clgs_db->bulk_entries('delete', [1,2,3]));

        $this->assertEquals(false, $this->clgs_db->bulk_entries('delete', []));
    }

    function test_bulk_entries_mark_seen () {
        global $clgs_last_log;

        $clgs_last_log = Mockery::mock('Clgs_last_log');
        $clgs_last_log->shouldReceive('flush')->never();

        $statement = new Statements\UpdateStatement();
        $statement->tables = [ new Components\Expression('wp_clgs_entries') ];
        $set = new Components\SetOperation;
        $set->column = 'seen';
        $set->value = '1';
        $statement->set = [ $set ];
        $statement->where = [ new Components\Condition('id IN (1,2,3)')];

        $this->init_director('query')
            ->addNewExpectation()
            ->with(new SQLParserMatch($statement))->once()
            ->andReturn('answer');

        $this->assertEquals('answer', $this->clgs_db->bulk_entries('mark-seen', [1,2,3]));

        $this->assertEquals(false, $this->clgs_db->bulk_entries('mark-seen', []));
    }

    function test_extra () {
        $statement = new Statements\SelectStatement;
        $statement->expr = [ new Components\Expression('*')];
        $statement->from = [ new Components\Expression('tbl')];
        $statement->limit = new Components\Limit(10, 20);

        $this->wpdb->shouldReceive('query')
            ->with(new SQLParserMatch($statement))->once()
            ->andReturn('answer');

        $this->assertEquals('answer', $this->wpdb->query('SELECT * FROM tbl LIMIT 20, 1'));
    }

}