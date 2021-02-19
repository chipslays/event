# Event

☎ A simple event dispatching mechanism (like routing) for chat bots.

## Installation

```bash
$ composer require chipslays/event
```

## Methods

#### `__construct($data)`

Paramater `$data` must be a `array`, `string`, `stdClass` or instance of `Chipslays\Collection\Collection`.

#### `on(string $event, callable|string $fn [, int $sort = 500]): Event`

Paramater `$fn` must be a function or class.

```php
$event->on(..., function () {...});
$event->on(..., '\App\Controller@method');
```

Parameter `$sort` responsible for the execution priority.

#### `run(): void`

Dispatch and execute all caught events.

## Own Event class

You can use events in your class by trait:

```php
use Chipslays\Event\EventTrait;

class MyClass
{
    use EventTrait;

    // ...
}

```

## Usage

```php
use Chipslays\Event\Event;

require __DIR__ . 'vendor/autoload.php';

$event = new Event([
    'message' => [
        'text' => 'Hello 👋',
    ],
    'user' => [
        'id' => 1234567890,
        'firstname' => 'John',
        'lastname' => 'Doe'
    ],
]);

$event->on('message.text', function () {
    echo 'This event has `text`';
});

$event->run();
```


```php
$event->on(['message.text' => 'Hello 👋'], function () {
    echo 'Hello!';
});
```

```php
// At least one "OR Bye OR Goodbye" logic
$event->on([['message.text' => 'Bye'], ['message.text' => 'Goodbye']], function () {
    echo 'Bye!';
});
```

```php
$event->on(['*.text' => 'My name is {name}'], function ($name) {
    echo "Nice to meet you, {$name}!";
});
```

```php
$event->on(['*.text' => '/^hello$/i'], function () {
    echo "Hello!";
});
```

```php
// For prevent chain function must return False
$event->on(['*.text' => 'Hi!'], function () {
    echo "Hello!";
    return false;
});

$event->on(['*.firstname' => 'John'], function () {
    echo "This text will never be displayed";
});
```

```php
// Ypu can use sort for events
$event->on(['*.firstname' => 'John'], function () {
    echo "This text will never be displayed";
}, 500);

$event->on(['*.text' => 'Hi!'], function () {
    echo "Hello!";
    return false;
}, 400);
```