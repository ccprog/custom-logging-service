<?php
use Brain\Monkey;

class CLgs_UnitTestCase extends PHPUnit_Framework_TestCase {

    protected function setUp() {
        parent::setUp();
        Monkey::setUpWP();

        $this->doc = new DOMDocument();

        require_once __DIR__ . '/wp-extra-functions.php';
        require_once __DIR__ . '/../../../custom-logging-service.php';

        Monkey\Functions::when('clgs_is_network_mode')->justReturn(false);
    }

    protected function tearDown() {
        Monkey::tearDownWP();
        parent::tearDown();
    }

    private function normalize_recursive($node) {
        $empty = [];
        foreach($node->childNodes as $sub_node) {
            if (XML_ELEMENT_NODE == $sub_node->nodeType) {
                $this->normalize_recursive($sub_node);
            } elseif ($sub_node->isWhitespaceInElementContent() ) {
                $empty[] = $sub_node;
            }
        }
        foreach( $empty as $e ){
            $node->removeChild($e);
        }
    }

    protected function get_DOM_Nodes ($string) {
        $this->doc->loadXML('<entity>'. $string . '</entity>');
        $entity = $this->doc->firstChild;
        $this->normalize_recursive($entity);
        return $entity->childNodes;
    }

    protected function compare_attributes ($expected, $attributes) {
        $this->assertEquals(count($expected), $attributes->length);
        foreach($attributes as $key => $value) {
            $this->assertArrayHasKey($key, $expected);
            $this->assertEquals($value->value, $expected[$key]);
        }
    }
}