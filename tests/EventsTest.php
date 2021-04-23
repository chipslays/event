<?php

use PHPUnit\Framework\TestCase;

use Chipslays\Event\Event;
use Chipslays\Collection\Collection;

final class BotTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
    }

    protected  function setUp(): void
    {
    }

    protected function tearDown(): void
    {
    }

    public function testCreateFromJson()
    {
        $this->assertEquals(['test' => 'data'], (new Event('{"test": "data"}'))->getEventData()->toArray());
    }

    public function testCreateFromArray()
    {
        $this->assertEquals(['test' => 'data'], (new Event(['test' => 'data']))->getEventData()->toArray());
    }

    public function testCreateFromStdClass()
    {
        $stdClass = new stdClass;
        $stdClass->test = 'data';

        $this->assertEquals(['test' => 'data'], (new Event($stdClass))->getEventData()->toArray());
    }

    public function testCreateFromCollection()
    {
        $collection = new Collection(['test' => 'data']);

        $this->assertEquals(['test' => 'data'], (new Event($collection))->getEventData()->toArray());
    }

    public function testSingleOn()
    {
        $event = new Event([
            'message' => [
                'text' => 'test',
            ],
        ]);

        $event->on('message.text', function () {
            echo 'catch';
        });

        ob_start();
        $event->run();
        $output = ob_get_contents();
        ob_end_clean();

        $this->assertEquals('catch', $output);
    }

    public function testMultipleOn()
    {
        $event = new Event([
            'message' => [
                'text' => 'test',
            ],
            'user' => [
                'name' => 'chipslays',
            ],
        ]);

        $event->on(['*.name', '*.text'], function () {
            echo 'catch';
        });

        ob_start();
        $event->run();
        $output = ob_get_contents();
        ob_end_clean();

        $this->assertEquals('catch', $output);
    }

    public function testPreventChainOn()
    {
        $event = new Event([
            'message' => [
                'text' => 'test',
            ],
        ]);

        $event->on('*.text', function () {
            echo 'catch';
            return false;
        });

        $event->on(['message.text'], function () {
            echo 'this text will not show';
        });

        ob_start();
        $event->run();
        $output = ob_get_contents();
        ob_end_clean();

        $this->assertEquals('catch', $output);
    }

    public function testSimilarKeyOn()
    {
        $event = new Event([
            'message' => [
                'text' => 'test',
            ],
        ]);

        $event->on([['*.text' => 'test'], '*.text'], function () {
            echo 'catch';
        });

        ob_start();
        $event->run();
        $output = ob_get_contents();
        ob_end_clean();

        $this->assertEquals('catch', $output);
    }

    public function testNoCaughtOn()
    {
        $event = new Event([
            'message' => [
                'text' => 'test',
            ],
        ]);

        $event->on([['*.date' => '123']], function () {
            echo 'catch';
        });

        ob_start();
        $event->run();
        $output = ob_get_contents();
        ob_end_clean();

        $this->assertEquals('', $output);
    }

    public function testRegexOn()
    {
        $event = new Event([
            'message' => [
                'text' => 'teSt',
            ],
        ]);

        $event->on([['message.text' => '/test/i']], function () {
            echo 'catch';
        });

        ob_start();
        $event->run();
        $output = ob_get_contents();
        ob_end_clean();

        $this->assertEquals('catch', $output);
    }

    public function testMatchOn()
    {
        $event = new Event([
            'message' => [
                'text' => 'My nickname is Chipslays',
            ],
        ]);

        $event->on([['message.text' => 'My nickname is {name}']], function ($name) {
            echo $name;
        });

        ob_start();
        $event->run();
        $output = ob_get_contents();
        ob_end_clean();

        $this->assertEquals('Chipslays', $output);
    }

    public function testLikeCommandOn()
    {
        $event = new Event([
            'message' => [
                'text' => '/ban user time',
            ],
        ]);

        $event->on([['message.text' => '/ban {:user?} {:time?}']], function ($user = null, $time = null) {
            echo "{$user} {$time}";
        });

        ob_start();
        $event->run();
        $output = ob_get_contents();
        ob_end_clean();

        $this->assertEquals('user time', $output);
    }

    public function testLikeCommandWithMissedParamOn()
    {
        $event = new Event([
            'message' => [
                'text' => '/ban user',
            ],
        ]);

        $event->on([['message.text' => '/ban {user?} {:time?}']], function ($user = null, $time = 'missed!') {
            echo "{$user} {$time}";
        });

        ob_start();
        $event->run();
        $output = ob_get_contents();
        ob_end_clean();

        $this->assertEquals('user missed!', $output);
    }

    public function testLikeCommandWithRequiredAndOptionalParamOn()
    {
        $event = new Event([
            'message' => [
                'text' => '/ban user time',
            ],
        ]);

        $event->on([['message.text' => '/ban {user} {:time?}']], function ($user = null, $time = 'missed!') {
            echo "{$user}: {$time}";
        });

        ob_start();
        $event->run();
        $output = ob_get_contents();
        ob_end_clean();

        $this->assertEquals('user: time', $output);
    }

    public function testMatchWithUnderlineOn()
    {
        $event = new Event([
            'message' => [
                'text' => '/ban_user',
            ],
        ]);

        $event->on([['message.text' => '/ban_{user}']], function ($user = null) {
            echo $user;
        });

        ob_start();
        $event->run();
        $output = ob_get_contents();
        ob_end_clean();

        $this->assertEquals('user', $output);
    }

    public function testCallbackParams()
    {
        $event = new Event([
            'ok',
        ]);

        $event->on('ok', [function (...$args) {
            echo array_sum($args);
        }, 2, 2, 1, 5]);

        ob_start();
        $event->run();
        $output = ob_get_contents();
        ob_end_clean();

        $this->assertEquals(10, $output);
    }

    public function testForceEvent()
    {
        $event = new Event([]);

        $event->on(true, function () {
            echo 'force';
        });

        ob_start();
        $event->run();
        $output = ob_get_contents();
        ob_end_clean();

        $this->assertEquals('force', $output);
    }
}
