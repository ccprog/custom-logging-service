<?php

class SQLParserMatch extends Mockery\Matcher\MatcherAbstract {
    private $statement;

    public function __construct($statement) {
        $this->statement = $statement;
    }

    private function compare_recursive($part1, $part2) {
        if (is_object($part1)) {
            foreach (get_object_vars($part1) as $key => $arr) {
//                print(PHP_EOL . get_class($part1) . '->' . $key);
                if (is_array($arr)) {
                    foreach ($arr as $i => $content) {
                        if (! $this->compare_recursive($content, $part2->{$key}[$i])) {
                            return false;
                        }
                    }
                } elseif (isset($arr)) {
                    return $this->compare_recursive($arr, $part2->{$key});
                }
            }
            return true;
        } elseif (isset($part1) && '' !== $part1) {
            return $part1 === $part2;
        }
        return true;
    } 

    public function match(&$actual) {
        $parsed = new PhpMyAdmin\SqlParser\Parser(strtolower($actual));
        return $this->compare_recursive($this->statement, $parsed->statements[0]);
    }

    public function __toString()
    {
        return $this->statement->build();
    }
}
