<?php

require_once dirname(__FILE__) . '/../autorun.php';
require_once dirname(__FILE__) . '/../expectation.php';
require_once dirname(__FILE__) . '/../mock_objects.php';

class TestOfAnythingExpectation extends UnitTestCase
{
    public function testSimpleInteger()
    {
        $expectation = new AnythingExpectation();
        $this->assertTrue($expectation->test(33));
        $this->assertTrue($expectation->test(false));
        $this->assertTrue($expectation->test(null));
    }
}

class TestOfParametersExpectation extends UnitTestCase
{
    public function testEmptyMatch()
    {
        $expectation = new ParametersExpectation(array());
        $this->assertTrue($expectation->test(array()));
        $this->assertFalse($expectation->test(array(33)));
    }

    public function testSingleMatch()
    {
        $expectation = new ParametersExpectation(array(0));
        $this->assertFalse($expectation->test(array(1)));
        $this->assertTrue($expectation->test(array(0)));
    }

    public function testAnyMatch()
    {
        $expectation = new ParametersExpectation(false);
        $this->assertTrue($expectation->test(array()));
        $this->assertTrue($expectation->test(array(1, 2)));
    }

    public function testMissingParameter()
    {
        $expectation = new ParametersExpectation(array(0));
        $this->assertFalse($expectation->test(array()));
    }

    public function testNullParameter()
    {
        $expectation = new ParametersExpectation(array(null));
        $this->assertTrue($expectation->test(array(null)));
        $this->assertFalse($expectation->test(array()));
    }

    public function testAnythingExpectations()
    {
        $expectation = new ParametersExpectation(array(new AnythingExpectation()));
        $this->assertFalse($expectation->test(array()));
        $this->assertIdentical($expectation->test(array(null)), true);
        $this->assertIdentical($expectation->test(array(13)), true);
    }

    public function testOtherExpectations()
    {
        $expectation = new ParametersExpectation(
                array(new PatternExpectation('/hello/i')));
        $this->assertFalse($expectation->test(array('Goodbye')));
        $this->assertTrue($expectation->test(array('hello')));
        $this->assertTrue($expectation->test(array('Hello')));
    }

    public function testIdentityOnly()
    {
        $expectation = new ParametersExpectation(array('0'));
        $this->assertFalse($expectation->test(array(0)));
        $this->assertTrue($expectation->test(array('0')));
    }

    public function testLongList()
    {
        $expectation = new ParametersExpectation(
                array('0', 0, new AnythingExpectation(), false));
        $this->assertTrue($expectation->test(array('0', 0, 37, false)));
        $this->assertFalse($expectation->test(array('0', 0, 37, true)));
        $this->assertFalse($expectation->test(array('0', 0, 37)));
    }
}

class TestOfSimpleSignatureMap extends UnitTestCase
{
    public function testEmpty()
    {
        $map = new SimpleSignatureMap();
        $this->assertFalse($map->isMatch('any', array()));
        $this->assertNull($map->findFirstAction('any', array()));
    }

    public function testDifferentCallSignaturesCanHaveDifferentReferences()
    {
        $map  = new SimpleSignatureMap();
        $fred = 'Fred';
        $jim  = 'jim';
        $map->add(array(0), $fred);
        $map->add(array('0'), $jim);
        $this->assertSame($fred, $map->findFirstAction(array(0)));
        $this->assertSame($jim, $map->findFirstAction(array('0')));
    }

    public function testWildcard()
    {
        $fred = 'Fred';
        $map  = new SimpleSignatureMap();
        $map->add(array(new AnythingExpectation(), 1, 3), $fred);
        $this->assertTrue($map->isMatch(array(2, 1, 3)));
        $this->assertSame($map->findFirstAction(array(2, 1, 3)), $fred);
    }

    public function testAllWildcard()
    {
        $fred = 'Fred';
        $map  = new SimpleSignatureMap();
        $this->assertFalse($map->isMatch(array(2, 1, 3)));
        $map->add('', $fred);
        $this->assertTrue($map->isMatch(array(2, 1, 3)));
        $this->assertSame($map->findFirstAction(array(2, 1, 3)), $fred);
    }

    public function testOrdering()
    {
        $map = new SimpleSignatureMap();
        $map->add(array(1, 2), new SimpleByValue('1, 2'));
        $map->add(array(1, 3), new SimpleByValue('1, 3'));
        $map->add(array(1), new SimpleByValue('1'));
        $map->add(array(1, 4), new SimpleByValue('1, 4'));
        $map->add(array(new AnythingExpectation()), new SimpleByValue('Any'));
        $map->add(array(2), new SimpleByValue('2'));
        $map->add('', new SimpleByValue('Default'));
        $map->add(array(), new SimpleByValue('None'));
        $this->assertEqual($map->findFirstAction(array(1, 2)), new SimpleByValue('1, 2'));
        $this->assertEqual($map->findFirstAction(array(1, 3)), new SimpleByValue('1, 3'));
        $this->assertEqual($map->findFirstAction(array(1, 4)), new SimpleByValue('1, 4'));
        $this->assertEqual($map->findFirstAction(array(1)), new SimpleByValue('1'));
        $this->assertEqual($map->findFirstAction(array(2)), new SimpleByValue('Any'));
        $this->assertEqual($map->findFirstAction(array(3)), new SimpleByValue('Any'));
        $this->assertEqual($map->findFirstAction(array()), new SimpleByValue('Default'));
    }
}

class TestOfCallSchedule extends UnitTestCase
{

    public function testCanBeSetToAlwaysReturnTheSameReference()
    {
        $a        = 5;
        $schedule = new SimpleCallSchedule();
        $schedule->register('aMethod', false, new SimpleByReference($a));
        $this->assertReference($schedule->respond(0, 'aMethod', array()), $a);
        $this->assertReference($schedule->respond(1, 'aMethod', array()), $a);
    }

    public function testSpecificSignaturesOverrideTheAlwaysCase()
    {
        $any      = 'any';
        $one      = 'two';
        $schedule = new SimpleCallSchedule();
        $schedule->register('aMethod', array(1), new SimpleByReference($one));
        $schedule->register('aMethod', false, new SimpleByReference($any));
        $this->assertReference($schedule->respond(0, 'aMethod', array(2)), $any);
        $this->assertReference($schedule->respond(0, 'aMethod', array(1)), $one);
    }

    public function testReturnsCanBeSetOverTime()
    {
        $one      = 'one';
        $two      = 'two';
        $schedule = new SimpleCallSchedule();
        $schedule->registerAt(0, 'aMethod', false, new SimpleByReference($one));
        $schedule->registerAt(1, 'aMethod', false, new SimpleByReference($two));
        $this->assertReference($schedule->respond(0, 'aMethod', array()), $one);
        $this->assertReference($schedule->respond(1, 'aMethod', array()), $two);
    }

    public function testReturnsOverTimecanBeAlteredByTheArguments()
    {
        $one      = '1';
        $two      = '2';
        $two_a    = '2a';
        $schedule = new SimpleCallSchedule();
        $schedule->registerAt(0, 'aMethod', false, new SimpleByReference($one));
        $schedule->registerAt(1, 'aMethod', array('a'), new SimpleByReference($two_a));
        $schedule->registerAt(1, 'aMethod', false, new SimpleByReference($two));
        $this->assertReference($schedule->respond(0, 'aMethod', array()), $one);
        $this->assertReference($schedule->respond(1, 'aMethod', array()), $two);
        $this->assertReference($schedule->respond(1, 'aMethod', array('a')), $two_a);
    }

    public function testCanReturnByValue()
    {
        $a        = 5;
        $schedule = new SimpleCallSchedule();
        $schedule->register('aMethod', false, new SimpleByValue($a));
        $this->assertCopy($schedule->respond(0, 'aMethod', array()), $a);
    }

    public function testCanThrowException()
    {
        $schedule = new SimpleCallSchedule();
        $schedule->register('aMethod', false, new SimpleThrower(new Exception('Ouch')));
        $this->expectException(new Exception('Ouch'));
        $schedule->respond(0, 'aMethod', array());
    }

    public function testCanEmitError()
    {
        $schedule = new SimpleCallSchedule();
        $schedule->register('aMethod', false, new SimpleErrorThrower('Ouch', E_USER_WARNING));
        $this->expectError('Ouch');
        $schedule->respond(0, 'aMethod', array());
    }
}

class Dummy
{
    public $init = false;

    public function __construct()
    {
        $this->init = true;
    }

    public function aMethod()
    {
        return true;
    }

    public function &aReferenceMethod()
    {
        return true;
    }

    public function anotherMethod()
    {
        return true;
    }
}
Mock::generate('Dummy');
Mock::generate('Dummy', 'AnotherMockDummy');
Mock::generate('Dummy', 'MockDummyWithExtraMethods', array('extraMethod'));

class TestOfConstructorCreation extends UnitTestCase
{
    public function testCloning()
    {
        $mock = new MockDummy();
        $this->assertTrue(method_exists($mock, '__constructor'));
        $this->assertFalse($mock->init);
        $this->assertNull($mock->__constructor());
        $this->assertTrue($mock->init);
    }

    public function testCloningWithExtraMethod()
    {
        $mock = new MockDummyWithExtraMethods();
        $this->assertTrue(method_exists($mock, '__constructor'));
    }

    public function testCloningWithChosenClassName()
    {
        $mock = new AnotherMockDummy();
        $this->assertTrue(method_exists($mock, '__constructor'));
    }
}

class TestOfMockGeneration extends UnitTestCase
{
    public function testCloning()
    {
        $mock = new MockDummy();
        $this->assertTrue(method_exists($mock, 'aMethod'));
        $this->assertNull($mock->aMethod());
    }

    public function testCloningWithExtraMethod()
    {
        $mock = new MockDummyWithExtraMethods();
        $this->assertTrue(method_exists($mock, 'extraMethod'));
    }

    public function testCloningWithChosenClassName()
    {
        $mock = new AnotherMockDummy();
        $this->assertTrue(method_exists($mock, 'aMethod'));
    }
}

class TestOfMockReturns extends UnitTestCase
{
    public function testDefaultReturn()
    {
        $mock = new MockDummy();
        $mock->returnsByValue('aMethod', 'aaa');
        $this->assertIdentical($mock->aMethod(), 'aaa');
        $this->assertIdentical($mock->aMethod(), 'aaa');
    }

    public function testParameteredReturn()
    {
        $mock = new MockDummy();
        $mock->returnsByValue('aMethod', 'aaa', array(1, 2, 3));
        $this->assertNull($mock->aMethod());
        $this->assertIdentical($mock->aMethod(1, 2, 3), 'aaa');
    }

    public function testSetReturnGivesObjectReference()
    {
        $mock   = new MockDummy();
        $object = new Dummy();
        $mock->returns('aMethod', $object, array(1, 2, 3));
        $this->assertSame($mock->aMethod(1, 2, 3), $object);
    }

    /*public function testSetReturnReferenceGivesOriginalReference()
    {
        $mock   = new MockDummy();
        $object = 1;
        $mock->returnsByReference('aReferenceMethod', $object, array(1, 2, 3));
        $this->assertReference($mock->aReferenceMethod(1, 2, 3), $object);
    }*/

    public function testReturnValueCanBeChosenJustByPatternMatchingArguments()
    {
        $mock = new MockDummy();
        $mock->returnsByValue(
                'aMethod',
                'aaa',
                array(new PatternExpectation('/hello/i')));
        $this->assertIdentical($mock->aMethod('Hello'), 'aaa');
        $this->assertNull($mock->aMethod('Goodbye'));
    }

    public function testMultipleMethods()
    {
        $mock = new MockDummy();
        $mock->returnsByValue('aMethod', 100, array(1));
        $mock->returnsByValue('aMethod', 200, array(2));
        $mock->returnsByValue('anotherMethod', 10, array(1));
        $mock->returnsByValue('anotherMethod', 20, array(2));
        $this->assertIdentical($mock->aMethod(1), 100);
        $this->assertIdentical($mock->anotherMethod(1), 10);
        $this->assertIdentical($mock->aMethod(2), 200);
        $this->assertIdentical($mock->anotherMethod(2), 20);
    }

    public function testReturnSequence()
    {
        $mock = new MockDummy();
        $mock->returnsByValueAt(0, 'aMethod', 'aaa');
        $mock->returnsByValueAt(1, 'aMethod', 'bbb');
        $mock->returnsByValueAt(3, 'aMethod', 'ddd');
        $this->assertIdentical($mock->aMethod(), 'aaa');
        $this->assertIdentical($mock->aMethod(), 'bbb');
        $this->assertNull($mock->aMethod());
        $this->assertIdentical($mock->aMethod(), 'ddd');
    }

    /*public function testSetReturnReferenceAtGivesOriginal()
    {
        $mock   = new MockDummy();
        $object = 100;
        $mock->returnsByReferenceAt(1, 'aReferenceMethod', $object);
        $this->assertNull($mock->aReferenceMethod());
        $this->assertReference($mock->aReferenceMethod(), $object);
        $this->assertNull($mock->aReferenceMethod());
    }*/

    public function testReturnsAtGivesOriginalObjectHandle()
    {
        $mock   = new MockDummy();
        $object = new Dummy();
        $mock->returnsAt(1, 'aMethod', $object);
        $this->assertNull($mock->aMethod());
        $this->assertSame($mock->aMethod(), $object);
        $this->assertNull($mock->aMethod());
    }

    public function testComplicatedReturnSequence()
    {
        $mock   = new MockDummy();
        $object = new Dummy();
        $mock->returnsAt(1, 'aMethod', 'aaa', array('a'));
        $mock->returnsAt(1, 'aMethod', 'bbb');
        $mock->returnsAt(2, 'aMethod', $object, array('*', 2));
        $mock->returnsAt(2, 'aMethod', 'value', array('*', 3));
        $mock->returns('aMethod', 3, array(3));
        $this->assertNull($mock->aMethod());
        $this->assertEqual($mock->aMethod('a'), 'aaa');
        $this->assertSame($mock->aMethod(1, 2), $object);
        $this->assertEqual($mock->aMethod(3), 3);
        $this->assertNull($mock->aMethod());
    }

    public function testMultipleMethodSequences()
    {
        $mock = new MockDummy();
        $mock->returnsByValueAt(0, 'aMethod', 'aaa');
        $mock->returnsByValueAt(1, 'aMethod', 'bbb');
        $mock->returnsByValueAt(0, 'anotherMethod', 'ccc');
        $mock->returnsByValueAt(1, 'anotherMethod', 'ddd');
        $this->assertIdentical($mock->aMethod(), 'aaa');
        $this->assertIdentical($mock->anotherMethod(), 'ccc');
        $this->assertIdentical($mock->aMethod(), 'bbb');
        $this->assertIdentical($mock->anotherMethod(), 'ddd');
    }

    public function testSequenceFallback()
    {
        $mock = new MockDummy();
        $mock->returnsByValueAt(0, 'aMethod', 'aaa', array('a'));
        $mock->returnsByValueAt(1, 'aMethod', 'bbb', array('a'));
        $mock->returnsByValue('aMethod', 'AAA');
        $this->assertIdentical($mock->aMethod('a'), 'aaa');
        $this->assertIdentical($mock->aMethod('b'), 'AAA');
    }

    public function testMethodInterference()
    {
        $mock = new MockDummy();
        $mock->returnsByValueAt(0, 'anotherMethod', 'aaa');
        $mock->returnsByValue('aMethod', 'AAA');
        $this->assertIdentical($mock->aMethod(), 'AAA');
        $this->assertIdentical($mock->anotherMethod(), 'aaa');
    }
}

class TestOfMockExpectationsThatPass extends UnitTestCase
{
    public function testAnyArgument()
    {
        $mock = new MockDummy();
        $mock->expect('aMethod', array('*'));
        $mock->aMethod(1);
        $mock->aMethod('hello');
    }

    public function testAnyTwoArguments()
    {
        $mock = new MockDummy();
        $mock->expect('aMethod', array('*', '*'));
        $mock->aMethod(1, 2);
    }

    public function testSpecificArgument()
    {
        $mock = new MockDummy();
        $mock->expect('aMethod', array(1));
        $mock->aMethod(1);
    }

    public function testExpectation()
    {
        $mock = new MockDummy();
        $mock->expect('aMethod', array(new IsAExpectation('Dummy')));
        $mock->aMethod(new Dummy());
    }

    public function testArgumentsInSequence()
    {
        $mock = new MockDummy();
        $mock->expectAt(0, 'aMethod', array(1, 2));
        $mock->expectAt(1, 'aMethod', array(3, 4));
        $mock->aMethod(1, 2);
        $mock->aMethod(3, 4);
    }

    public function testAtLeastOnceSatisfiedByOneCall()
    {
        $mock = new MockDummy();
        $mock->expectAtLeastOnce('aMethod');
        $mock->aMethod();
    }

    public function testAtLeastOnceSatisfiedByTwoCalls()
    {
        $mock = new MockDummy();
        $mock->expectAtLeastOnce('aMethod');
        $mock->aMethod();
        $mock->aMethod();
    }

    public function testOnceSatisfiedByOneCall()
    {
        $mock = new MockDummy();
        $mock->expectOnce('aMethod');
        $mock->aMethod();
    }

    public function testMinimumCallsSatisfiedByEnoughCalls()
    {
        $mock = new MockDummy();
        $mock->expectMinimumCallCount('aMethod', 1);
        $mock->aMethod();
    }

    public function testMinimumCallsSatisfiedByTooManyCalls()
    {
        $mock = new MockDummy();
        $mock->expectMinimumCallCount('aMethod', 3);
        $mock->aMethod();
        $mock->aMethod();
        $mock->aMethod();
        $mock->aMethod();
    }

    public function testMaximumCallsSatisfiedByEnoughCalls()
    {
        $mock = new MockDummy();
        $mock->expectMaximumCallCount('aMethod', 1);
        $mock->aMethod();
    }

    public function testMaximumCallsSatisfiedByNoCalls()
    {
        $mock = new MockDummy();
        $mock->expectMaximumCallCount('aMethod', 1);
    }
}

class MockWithInjectedTestCase extends SimpleMock
{
    protected function getCurrentTestCase()
    {
        return SimpleTest::getContext()->getTest()->getMockedTest();
    }
}
SimpleTest::setMockBaseClass('MockWithInjectedTestCase');
Mock::generate('Dummy', 'MockDummyWithInjectedTestCase');
SimpleTest::setMockBaseClass('SimpleMock');
Mock::generate('SimpleTestCase');

class LikeExpectation extends IdenticalExpectation
{
    public function __construct($expectation)
    {
        $expectation->message = '';
        parent::__construct($expectation);
    }

    public function test($compare)
    {
        $compare->message = '';

        return parent::test($compare);
    }

    public function testMessage($compare)
    {
        $compare->message = '';

        return parent::testMessage($compare);
    }
}

class TestOfMockExpectations extends UnitTestCase
{
    private $test;

    public function setUp()
    {
        $this->test = new MockSimpleTestCase();
    }

    public function getMockedTest()
    {
        return $this->test;
    }

    public function testSettingExpectationOnNonMethodThrowsError()
    {
        $mock = new MockDummyWithInjectedTestCase();
        $this->expectError();
        $mock->expectMaximumCallCount('aMissingMethod', 2);
    }

    public function testMaxCallsDetectsOverrun()
    {
        $this->test->expectOnce('assert', array(new MemberExpectation('count', 2), 3));
        $mock = new MockDummyWithInjectedTestCase();
        $mock->expectMaximumCallCount('aMethod', 2);
        $mock->aMethod();
        $mock->aMethod();
        $mock->aMethod();
        $mock->mock->atTestEnd('testSomething', $this->test);
    }

    public function testTallyOnMaxCallsSendsPassOnUnderrun()
    {
        $this->test->expectOnce('assert', array(new MemberExpectation('count', 2), 2));
        $mock = new MockDummyWithInjectedTestCase();
        $mock->expectMaximumCallCount('aMethod', 2);
        $mock->aMethod();
        $mock->aMethod();
        $mock->mock->atTestEnd('testSomething', $this->test);
    }

    public function testExpectNeverDetectsOverrun()
    {
        $this->test->expectOnce('assert', array(new MemberExpectation('count', 0), 1));
        $mock = new MockDummyWithInjectedTestCase();
        $mock->expectNever('aMethod');
        $mock->aMethod();
        $mock->mock->atTestEnd('testSomething', $this->test);
    }

    public function testTallyOnExpectNeverStillSendsPassOnUnderrun()
    {
        $this->test->expectOnce('assert', array(new MemberExpectation('count', 0), 0));
        $mock = new MockDummyWithInjectedTestCase();
        $mock->expectNever('aMethod');
        $mock->mock->atTestEnd('testSomething', $this->test);
    }

    public function testMinCalls()
    {
        $this->test->expectOnce('assert', array(new MemberExpectation('count', 2), 2));
        $mock = new MockDummyWithInjectedTestCase();
        $mock->expectMinimumCallCount('aMethod', 2);
        $mock->aMethod();
        $mock->aMethod();
        $mock->mock->atTestEnd('testSomething', $this->test);
    }

    public function testFailedNever()
    {
        $this->test->expectOnce('assert', array(new MemberExpectation('count', 0), 1));
        $mock = new MockDummyWithInjectedTestCase();
        $mock->expectNever('aMethod');
        $mock->aMethod();
        $mock->mock->atTestEnd('testSomething', $this->test);
    }

    public function testUnderOnce()
    {
        $this->test->expectOnce('assert', array(new MemberExpectation('count', 1), 0));
        $mock = new MockDummyWithInjectedTestCase();
        $mock->expectOnce('aMethod');
        $mock->mock->atTestEnd('testSomething', $this->test);
    }

    public function testOverOnce()
    {
        $this->test->expectOnce('assert', array(new MemberExpectation('count', 1), 2));
        $mock = new MockDummyWithInjectedTestCase();
        $mock->expectOnce('aMethod');
        $mock->aMethod();
        $mock->aMethod();
        $mock->mock->atTestEnd('testSomething', $this->test);
    }

    public function testUnderAtLeastOnce()
    {
        $this->test->expectOnce('assert', array(new MemberExpectation('count', 1), 0));
        $mock = new MockDummyWithInjectedTestCase();
        $mock->expectAtLeastOnce('aMethod');
        $mock->mock->atTestEnd('testSomething', $this->test);
    }

    public function testZeroArguments()
    {
        $this->test->expectOnce('assert',
                                array(new MemberExpectation('expected', array()), array(), '*'));
        $mock = new MockDummyWithInjectedTestCase();
        $mock->expect('aMethod', array());
        $mock->aMethod();
        $mock->mock->atTestEnd('testSomething', $this->test);
    }

    public function testExpectedArguments()
    {
        $this->test->expectOnce('assert',
                                array(new MemberExpectation('expected', array(1, 2, 3)), array(1, 2, 3), '*'));
        $mock = new MockDummyWithInjectedTestCase();
        $mock->expect('aMethod', array(1, 2, 3));
        $mock->aMethod(1, 2, 3);
        $mock->mock->atTestEnd('testSomething', $this->test);
    }

    public function testFailedArguments()
    {
        $this->test->expectOnce('assert',
                                array(new MemberExpectation('expected', array('this')), array('that'), '*'));
        $mock = new MockDummyWithInjectedTestCase();
        $mock->expect('aMethod', array('this'));
        $mock->aMethod('that');
        $mock->mock->atTestEnd('testSomething', $this->test);
    }

    public function testWildcardsAreTranslatedToAnythingExpectations()
    {
        $this->test->expectOnce('assert',
                                array(new MemberExpectation('expected',
                                                            array(new AnythingExpectation(),
                                                                  123,
                                                                  new AnythingExpectation())),
                                      array(100, 123, 101), '*'));
        $mock = new MockDummyWithInjectedTestCase($this);
        $mock->expect('aMethod', array('*', 123, '*'));
        $mock->aMethod(100, 123, 101);
        $mock->mock->atTestEnd('testSomething', $this->test);
    }

    public function testSpecificPassingSequence()
    {
        $this->test->expectAt(0, 'assert',
                              array(new MemberExpectation('expected', array(1, 2, 3)), array(1, 2, 3), '*'));
        $this->test->expectAt(1, 'assert',
                              array(new MemberExpectation('expected', array('Hello')), array('Hello'), '*'));
        $mock = new MockDummyWithInjectedTestCase();
        $mock->expectAt(1, 'aMethod', array(1, 2, 3));
        $mock->expectAt(2, 'aMethod', array('Hello'));
        $mock->aMethod();
        $mock->aMethod(1, 2, 3);
        $mock->aMethod('Hello');
        $mock->aMethod();
        $mock->mock->atTestEnd('testSomething', $this->test);
    }

    public function testNonArrayForExpectedParametersGivesError()
    {
        $mock = new MockDummyWithInjectedTestCase();
        $this->expectError(new PatternExpectation('/\$args.*not an array/i'));
        $mock->expect('aMethod', 'foo');
        $mock->aMethod();
        $mock->mock->atTestEnd('testSomething', $this->test);
    }
}

class TestOfMockComparisons extends UnitTestCase
{
    public function testEqualComparisonOfMocksDoesNotCrash()
    {
        $expectation = new EqualExpectation(new MockDummy());
        $this->assertTrue($expectation->test(new MockDummy(), true));
    }

    public function testIdenticalComparisonOfMocksDoesNotCrash()
    {
        $expectation = new IdenticalExpectation(new MockDummy());
        $this->assertTrue($expectation->test(new MockDummy()));
    }
}

class ClassWithSpecialMethods
{
    public function __get($name)
    {
    }
    public function __set($name, $value)
    {
    }
    public function __isset($name)
    {
    }
    public function __unset($name)
    {
    }
    public function __call($method, $arguments)
    {
    }
    public function __toString()
    {
    }
}
Mock::generate('ClassWithSpecialMethods');


/**
 * __isset and __unset overloading - PHP 5.1+
 */
class TestOfSpecialMethodsAfterPHP51 extends UnitTestCase
{
    public function testCanEmulateIsset()
    {
        $mock = new MockClassWithSpecialMethods();
        $mock->returnsByValue('__isset', true);
        $this->assertIdentical(isset($mock->a), true);
    }

    public function testCanExpectUnset()
    {
        $mock = new MockClassWithSpecialMethods();
        $mock->expectOnce('__unset', array('a'));
        unset($mock->a);
    }
}

class TestOfSpecialMethods extends UnitTestCase
{
    public function testCanMockTheThingAtAll()
    {
        $mock = new MockClassWithSpecialMethods();
    }

    public function testReturnFromSpecialAccessor()
    {
        $mock = new MockClassWithSpecialMethods();
        $mock->returnsByValue('__get', '1st Return', array('first'));
        $mock->returnsByValue('__get', '2nd Return', array('second'));
        $this->assertEqual($mock->first, '1st Return');
        $this->assertEqual($mock->second, '2nd Return');
    }

    public function testcanExpectTheSettingOfValue()
    {
        $mock = new MockClassWithSpecialMethods();
        $mock->expectOnce('__set', array('a', 'A'));
        $mock->a = 'A';
    }

    public function testCanSimulateAnOverloadmethod()
    {
        $mock = new MockClassWithSpecialMethods();
        $mock->expectOnce('__call', array('amOverloaded', array('A')));
        $mock->returnsByValue('__call', 'aaa');
        $this->assertIdentical($mock->amOverloaded('A'), 'aaa');
    }

    public function testToStringMagic()
    {
        $mock = new MockClassWithSpecialMethods();
        $mock->expectOnce('__toString');
        $mock->returnsByValue('__toString', 'AAA');
        ob_start();
        print $mock;
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertEqual($output, 'AAA');
    }
}

class WithStaticMethod
{
    public static function aStaticMethod()
    {
    }
}
Mock::generate('WithStaticMethod');

class TestOfMockingClassesWithStaticMethods extends UnitTestCase
{
    public function testStaticMethodIsMockedAsStatic()
    {
        $mock       = new WithStaticMethod();
        $reflection = new ReflectionClass($mock);
        $method     = $reflection->getMethod('aStaticMethod');
        $this->assertTrue($method->isStatic());
    }
}

class MockTestException extends Exception
{
}

class TestOfThrowingExceptionsFromMocks extends UnitTestCase
{
    public function testCanThrowOnMethodCall()
    {
        $mock = new MockDummy();
        $mock->throwOn('aMethod');
        $this->expectException();
        $mock->aMethod();
    }

    public function testCanThrowSpecificExceptionOnMethodCall()
    {
        $mock = new MockDummy();
        $mock->throwOn('aMethod', new MockTestException());
        $this->expectException();
        $mock->aMethod();
    }

    public function testThrowsOnlyWhenCallSignatureMatches()
    {
        $mock = new MockDummy();
        $mock->throwOn('aMethod', new MockTestException(), array(3));
        $mock->aMethod(1);
        $mock->aMethod(2);
        $this->expectException();
        $mock->aMethod(3);
    }

    public function testCanThrowOnParticularInvocation()
    {
        $mock = new MockDummy();
        $mock->throwAt(2, 'aMethod', new MockTestException());
        $mock->aMethod();
        $mock->aMethod();
        $this->expectException();
        $mock->aMethod();
    }
}

class TestOfThrowingErrorsFromMocks extends UnitTestCase
{
    public function testCanGenerateErrorFromMethodCall()
    {
        $mock = new MockDummy();
        $mock->errorOn('aMethod', 'Ouch!');
        $this->expectError('Ouch!');
        $mock->aMethod();
    }

    public function testGeneratesErrorOnlyWhenCallSignatureMatches()
    {
        $mock = new MockDummy();
        $mock->errorOn('aMethod', 'Ouch!', array(3));
        $mock->aMethod(1);
        $mock->aMethod(2);
        $this->expectError();
        $mock->aMethod(3);
    }

    public function testCanGenerateErrorOnParticularInvocation()
    {
        $mock = new MockDummy();
        $mock->errorAt(2, 'aMethod', 'Ouch!');
        $mock->aMethod();
        $mock->aMethod();
        $this->expectError();
        $mock->aMethod();
    }
}

Mock::generatePartial('Dummy', 'TestDummy', array('anotherMethod', 'aReferenceMethod'));

class TestOfPartialMocks extends UnitTestCase
{
    public function testMethodReplacementWithNoBehaviourReturnsNull()
    {
        $mock = new TestDummy();
        $this->assertEqual($mock->aMethod(99), 99);
        $this->assertNull($mock->anotherMethod());
    }

    public function testSettingReturns()
    {
        $mock = new TestDummy();
        $mock->returnsByValue('anotherMethod', 33, array(3));
        $mock->returnsByValue('anotherMethod', 22);
        $mock->returnsByValueAt(2, 'anotherMethod', 44, array(3));
        $this->assertEqual($mock->anotherMethod(), 22);
        $this->assertEqual($mock->anotherMethod(3), 33);
        $this->assertEqual($mock->anotherMethod(3), 44);
    }

    public function testSetReturnReferenceGivesOriginal()
    {
        $mock   = new TestDummy();
        $object = 99;
        $mock->returnsByReferenceAt(0, 'aReferenceMethod', $object, array(3));
        $this->assertReference($mock->aReferenceMethod(3), $object);
    }

    public function testReturnsAtGivesOriginalObjectHandle()
    {
        $mock   = new TestDummy();
        $object = new Dummy();
        $mock->returnsAt(0, 'anotherMethod', $object, array(3));
        $this->assertSame($mock->anotherMethod(3), $object);
    }

    public function testExpectations()
    {
        $mock = new TestDummy();
        $mock->expectCallCount('anotherMethod', 2);
        $mock->expect('anotherMethod', array(77));
        $mock->expectAt(1, 'anotherMethod', array(66));
        $mock->anotherMethod(77);
        $mock->anotherMethod(66);
    }

    public function testSettingExpectationOnMissingMethodThrowsError()
    {
        $mock = new TestDummy();
        $this->expectError();
        $mock->expectCallCount('aMissingMethod', 2);
    }
}

class ConstructorSuperClass
{
    public function __construct()
    {
    }
}

class ConstructorSubClass extends ConstructorSuperClass
{
}

class TestOfPHP5StaticMethodMocking extends UnitTestCase
{
    public function testCanCreateAMockObjectWithStaticMethodsWithoutError()
    {
        eval('
            class SimpleObjectContainingStaticMethod {
                static function someStatic() { }
            }
        ');
        Mock::generate('SimpleObjectContainingStaticMethod');
    }
}

class TestOfPHP5AbstractMethodMocking extends UnitTestCase
{
    public function testCanCreateAMockObjectFromAnAbstractWithProperFunctionDeclarations()
    {
        eval('
            abstract class SimpleAbstractClassContainingAbstractMethods {
                abstract function anAbstract();
                abstract function anAbstractWithParameter($foo);
                abstract function anAbstractWithMultipleParameters($foo, $bar);
            }
        ');
        Mock::generate('SimpleAbstractClassContainingAbstractMethods');
        $this->assertTrue(
            method_exists(
                // Testing with class name alone does not work in PHP 5.0
                new MockSimpleAbstractClassContainingAbstractMethods,
                'anAbstract'
            )
        );
        $this->assertTrue(
            method_exists(
                new MockSimpleAbstractClassContainingAbstractMethods,
                'anAbstractWithParameter'
            )
        );
        $this->assertTrue(
            method_exists(
                new MockSimpleAbstractClassContainingAbstractMethods,
                'anAbstractWithMultipleParameters'
            )
        );
    }

    public function testMethodsDefinedAsAbstractInParentShouldHaveFullSignature()
    {
        eval('
             abstract class SimpleParentAbstractClassContainingAbstractMethods {
                abstract function anAbstract();
                abstract function anAbstractWithParameter($foo);
                abstract function anAbstractWithMultipleParameters($foo, $bar);
            }

             class SimpleChildAbstractClassContainingAbstractMethods extends SimpleParentAbstractClassContainingAbstractMethods {
                function anAbstract(){}
                function anAbstractWithParameter($foo){}
                function anAbstractWithMultipleParameters($foo, $bar){}
            }

            class EvenDeeperEmptyChildClass extends SimpleChildAbstractClassContainingAbstractMethods {}
        ');
        Mock::generate('SimpleChildAbstractClassContainingAbstractMethods');
        $this->assertTrue(
            method_exists(
                new MockSimpleChildAbstractClassContainingAbstractMethods,
                'anAbstract'
            )
        );
        $this->assertTrue(
            method_exists(
                new MockSimpleChildAbstractClassContainingAbstractMethods,
                'anAbstractWithParameter'
            )
        );
        $this->assertTrue(
            method_exists(
                new MockSimpleChildAbstractClassContainingAbstractMethods,
                'anAbstractWithMultipleParameters'
            )
        );
        Mock::generate('EvenDeeperEmptyChildClass');
        $this->assertTrue(
            method_exists(
                new MockEvenDeeperEmptyChildClass,
                'anAbstract'
            )
        );
        $this->assertTrue(
            method_exists(
                new MockEvenDeeperEmptyChildClass,
                'anAbstractWithParameter'
            )
        );
        $this->assertTrue(
            method_exists(
                new MockEvenDeeperEmptyChildClass,
                'anAbstractWithMultipleParameters'
            )
        );
    }
}

class DummyWithProtected
{
    public function aMethodCallsProtected()
    {
        return $this->aProtectedMethod();
    }
    protected function aProtectedMethod()
    {
        return true;
    }
}

Mock::generatePartial('DummyWithProtected', 'TestDummyWithProtected', array('aProtectedMethod'));

class TestOfProtectedMethodPartialMocks extends UnitTestCase
{
    public function testProtectedMethodExists()
    {
        $this->assertTrue(
            method_exists(
                new TestDummyWithProtected,
                'aProtectedMethod'
            )
        );
    }

    public function testProtectedMethodIsCalled()
    {
        $object = new DummyWithProtected();
        $this->assertTrue($object->aMethodCallsProtected(), 'ensure original was called');
    }

    public function testMockedMethodIsCalled()
    {
        $object = new TestDummyWithProtected();
        $object->returnsByValue('aProtectedMethod', false);
        $this->assertFalse($object->aMethodCallsProtected());
    }
}
