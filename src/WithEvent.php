<?php

namespace Chipslays\Event;

use Chipslays\Collection\Collection;
use Sauce\Traits\Call;

define('EVENT_DEFAULT_SORT_VALUE', 500);

trait WithEvent
{
    use Call {
        __call_function as protected callEventCallback;
    }

    protected ?Collection $__payload = null;

    protected array $__events = [];

    protected array $__beforeRun = [];

    protected array $__afterRun = [];

    protected array $__defaultEvents = [];

    /**
     * Reset all current events.
     *
     * @return void
     */
    public function resetEvents()
    {
        $this->__events = [];
    }

    /**
     * Reset current payload.
     *
     * @return void
     */
    public function resetPayload()
    {
        $this->__events = [];
    }

    /**
     * Reset payload and events.
     *
     * @return void
     */
    public function resetAll()
    {
        $this->resetEvents();
        $this->resetPayload();
    }

    /**
     * @return Collection|null
     */
    public function getPayload()
    {
        return $this->__payload;
    }

    /**
     * Set payload.
     *
     * @param array|string|stdClass|Collection $payload
     * @return void
     *
     * @throws EventException
     */
    public function setPayload($payload)
    {
        if (is_string($payload)) {
            if (($payload = @json_decode($payload, true)) !== null) {
                $this->__payload = new Collection($payload);
                return;
            } else {
                throw new EventException("String must be a valid JSON.");
            }
        }

        $this->__payload = $payload instanceof Collection ? $payload : new Collection($payload);
    }

    /**
     * Add event.
     *
     * @param array|string $pattern
     * @param callable $callback
     * @param integer $sort
     * @param array $callbackExtraParameters
     * @return this
     */
    public function on($pattern, $callback, int $sort = EVENT_DEFAULT_SORT_VALUE, array $callbackExtraParameters = [])
    {
        $this->__events[$sort][] = compact('pattern', 'callback', 'sort', 'callbackExtraParameters');
        return $this;
    }

    public function default($pattern, $callback)
    {
        $this->__defaultEvents[] = compact('pattern', 'callback');
    }

    /**
     * Fire event callbacks.
     *
     * @return void
     */
    public function run()
    {
        $this->executeBeforeRun();

        if (!$this->processEvents()) {
            $this->processDefaultEvents();
        }

        $this->executeAfterRun();
    }

    /**
     * @param callable $fn($payload,...$callbackExtraParameters)
     * @param integer $sort
     * @param array $callbackExtraParameters
     * @return void
     */
    public function beforeRun($fn, int $sort = EVENT_DEFAULT_SORT_VALUE, array $callbackExtraParameters = [])
    {
        $this->__beforeRun[$sort][] = compact('fn', 'callbackExtraParameters');
    }

    /**
     * @param callable $fn($payload,...$callbackExtraParameters)
     * @param integer $sort
     * @param array $callbackExtraParameters
     * @return void
     */
    public function afterRun($fn, int $sort = EVENT_DEFAULT_SORT_VALUE, array $callbackExtraParameters = [])
    {
        $this->__afterRun[$sort][] = compact('fn', 'callbackExtraParameters');
    }

    /**
     * @param array $array
     * @return void
     */
    protected function executeFunctionsFromArray(array $array)
    {
        ksort($array);
        $functions = call_user_func_array('array_merge', $array);

        foreach ($functions as $function) {
            $this->callEventCallback($function['fn'], [$this->__payload, ...$function['callbackExtraParameters']]);
        }
    }

    /**
     * @return void
     */
    protected function executeBeforeRun()
    {
        if ($this->__beforeRun !== []) {
            $this->executeFunctionsFromArray($this->__beforeRun);
        }
    }

    /**
     * @return void
     */
    protected function executeAfterRun()
    {
        if ($this->__afterRun !== []) {
            $this->executeFunctionsFromArray($this->__afterRun);
        }
    }

    /**
     * @return void
     */
    protected function processDefaultEvents()
    {
        foreach ($this->__defaultEvents as $event) {
            foreach ((array) $event['pattern'] as $key) {
                if ($this->__payload->has($key)) {
                    $this->callEventCallback($event['callback']);
                    return;
                }
            }
        }
    }

    /**
     * @return bool
     */
    protected function processEvents()
    {
        $hasAnyEventCaught = false;

        ksort($this->__events);
        $events = call_user_func_array('array_merge', $this->__events);

        foreach ($events as $event) {
            foreach ((array) $event['pattern'] as $key => $value) {

                /**
                 * Force execute event
                 * on(true, ..., ...)
                 */
                if ($value === true) {
                    $hasAnyEventCaught = true;
                    if ($this->callEventCallback($event['callback'], $event['callbackExtraParameters']) === false) {
                        return $hasAnyEventCaught;
                    }
                    break;
                }

                /**
                 * [['key' => 'value'], ...]
                 */
                if (is_array($value)) {
                    $key = key($value);
                    $value = $value[$key];
                }

                /**
                 * ['key'] or 'key'
                 */
                if (is_numeric($key) && $this->__payload->has($value)) {
                    $hasAnyEventCaught = true;
                    if ($this->callEventCallback($event['callback'], $event['callbackExtraParameters']) === false) {
                        return $hasAnyEventCaught;
                    }
                    break;
                }

                /**
                 * Get value by key, if not exists then skip iteration.
                 * ['key' => 'value']
                 */
                if (!$received = $this->__payload->get($key)) {
                    continue;
                }

                 /**
                 * ['key' => 'value']
                 */
                if ($received == $value) {
                    $hasAnyEventCaught = true;
                    if ($this->callEventCallback($event['callback'], $event['callbackExtraParameters']) === false) {
                        return $hasAnyEventCaught;
                    }
                    break;
                }

                /**
                 * ['key' => 'my name is {name}']
                 *
                 * command(?: (.*?))?(?: (.*?))?$
                 *
                 * {text} - required text
                 * {:text?} - optional text
                 */
                $value = preg_replace('~.?{:(.*?)\?}~', '(?: (.*?))?', $value);
                $pattern = '~^' . preg_replace('/{(.*?)}/', '(.*?)', $value) . '$~';

                if (@preg_match_all($pattern, $received, $matches)) {
                    $hasAnyEventCaught = true;
                    if ($this->callEventCallback($event['callback'], array_merge($this->buildParamsFromMatches($matches), $event['callbackExtraParameters'])) === false) {
                        return $hasAnyEventCaught;
                    }
                    break;
                }

                /**
                 * ['key' => '/regex/i]
                 */
                if (@preg_match_all($value, $received, $matches)) {
                    $hasAnyEventCaught = true;
                    if ($this->callEventCallback($event['callback'], array_merge($this->buildParamsFromMatches($matches), $event['callbackExtraParameters'])) === false) {
                        return $hasAnyEventCaught;
                    }
                    break;
                }
            }
        }

        return $hasAnyEventCaught;
    }

    /**
     * @param array $matches
     * @return array
     */
    protected function buildParamsFromMatches($matches)
    {
        return array_filter(array_map(function ($item) {
            return array_shift($item);
        }, array_slice($matches, 1)), 'strlen');
    }
}