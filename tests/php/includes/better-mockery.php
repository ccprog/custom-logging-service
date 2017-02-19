<?php

class BetterMockery extends Mockery {
    //warum notwendig?
    public static function formatArgs($method, array $arguments = null) {
        if (is_null($arguments)) {
            return $method . '()';
        }
        $formattedArguments = array();
        foreach ($arguments as $argument) {
            $formattedArguments[] = self::formatArgument($argument);
        }
        return $method . '(' . implode(', ', $formattedArguments) . ')';
    }

    private static function formatArgument($argument, $depth = 0) {
        if (is_object($argument) && method_exists($argument, '__toString')) {
            return $argument;
        }
        return parent::formatArgument($argument, $depth);
    }

}

class BetterExpectationDirector extends Mockery\ExpectationDirector {
    public function addNewExpectation() {
         $expectation = new BetterExpectation($this->_mock, $this->_name);
         $this->_expectations[] = $expectation;
         return $expectation;
    }

    public function call(array $args) {
        $expectation = $this->findExpectation($args);
        if (is_null($expectation)) {
            $all_expectations = '';
            if ( count( $this->_expectations ) ) {
                $all_expectations .= PHP_EOL
                    .'Expected calls for this method were:'
                    . PHP_EOL;
                foreach( $this->_expectations as $i =>$exp) {
                    $all_expectations .= "$i. $exp". PHP_EOL;
                }
            }

            $exception = new \Mockery\Exception\NoMatchingExpectationException(
                'No matching handler found for '
                . $this->_mock->mockery_getName() . '::'
                . \Mockery::formatArgs($this->_name, $args)
                . '. Either the method was unexpected or its arguments matched'
                . ' no expected argument list for this method'
                . PHP_EOL . PHP_EOL
                . \Mockery::formatObjects($args)
                . $all_expectations
            );
            $exception->setMock($this->_mock)
                ->setMethodName($this->_name)
                ->setActualArguments($args);
            throw $exception;
        }
        return $expectation->verifyCall($args);
    }
}

class BetterExpectation extends Mockery\Expectation {
    public function __toString() {
        return BetterMockery::formatArgs($this->_name, $this->_expectedArgs);
    }
}
/*
class SQLCallPrediction implements PredictionInterface {
    private $util;

    public function __construct() {
        $this->util = new StringUtil;
    }

    public function check(array $calls, ObjectProphecy $object, MethodProphecy $method) {
        if (count($calls)) {
            return;
        }

        $methodCalls = $object->findProphecyMethodCalls(
            $method->getMethodName(),
            new ArgumentsWildcard(array(Argument::any()))
        );

        $argumentTokens = $method->getArgumentsWildcard()->getTokens();
        $sqlTokens = array_filter($argumentTokens, function ($token) {
            return is_a($token, 'SQLParserToken');
        });

        if (count($sqlTokens)) {
            $receivedCalls = array();
            foreach ($methodCalls as $i => $call) {
                $args = $call->getArguments();
                foreach ($sqlTokens as $key => $token) {
                    $args[$key] = $token->get_diff($i);
                }
                array_push($receivedCalls, new Call(
                    $call->getMethodName(),
                    $args,
                    $call->getReturnValue(),
                    $call->getException(),
                    $call->getFile(),
                    $call->getLine()
                ));
            }
        } else {
            $receivedCalls = $methodCalls;
        }

        if (count($receivedCalls)) {
            throw new NoCallsException(sprintf(
                "No calls have been made that match:\n".
                "  %s->%s(%s)\n".
                "but expected at least one.\n".
                "In recorded `%s(...)` calls the SQL differed:\n%s",
                get_class($object->reveal()),
                $method->getMethodName(),
                $method->getArgumentsWildcard(),
                $method->getMethodName(),
                $this->util->stringifyCalls($receivedCalls)
            ), $method);
        }
        throw new NoCallsException(sprintf(
            "No calls have been made that match:\n".
            "  %s->%s(%s)\n".
            "but expected at least one.",
            get_class($object->reveal()),
            $method->getMethodName(),
            $method->getArgumentsWildcard()
        ), $method);
    }
}
*/