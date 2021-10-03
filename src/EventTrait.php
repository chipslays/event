<?php

namespace Chipslays\Event;

use Chipslays\Collection\Collection;
use Closure;
use stdClass;
use ReflectionMethod;
use ReflectionException;

trait EventTrait
{
    /**
     * @var Collection
     */
    protected $payload = [];

    /**
     * @var array
     */
    protected $events = [];

    /**
     * @param array|string|stdClass|Collection $payload
     */
    public function __construct($payload)
    {
        $this->setPayload($payload);
    }

    /**
     * @param array|string|stdClass|Collection $payload
     * @return void
     */
    public function setPayload($payload)
    {
        if (is_string($payload)) {
            if (($payload = @json_decode($payload, true)) !== null) {
                $this->payload = new Collection($payload);
                return;
            } else {
                throw new EventException("String must be a valid JSON.");
            }
        }

        $this->payload = $payload instanceof Collection ? $payload : new Collection($payload);
    }

    /**
     * Add event.
     *
     * @param array|string $event
     * @param callable|string|array $callback
     * @param integer $sort
     * @return void
     */
    public function on($event, $callback, int $sort = 500)
    {
        $this->addEvent($event, $callback, $sort);
    }

    /**
     * Handle events.
     *
     * @return void
     */
    public function run()
    {
        $this->handleEvents();
    }

    /**
     * @return bool True any event has been caught, False no one event not be caught.
     */
    protected function handleEvents()
    {
        $hasAnyEventCaught = false;

        foreach ($this->getEvents() as $event) {
            foreach ((array) $event['pattern'] as $key => $value) {

                /**
                 * Force execute event
                 * on(true, ..., ...)
                 */
                if ($value === true) {
                    $hasAnyEventCaught = true;
                    if ($this->executeEventCallback($event['callback']) === false) {
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
                if (is_numeric($key) && $this->payload->has($value)) {
                    $hasAnyEventCaught = true;
                    if ($this->executeEventCallback($event['callback']) === false) {
                        return $hasAnyEventCaught;
                    }
                    break;
                }

                /**
                 * Get value by key, if not exists then skip iteration.
                 * ['key' => 'value']
                 */
                if (!$received = $this->payload->get($key)) {
                    continue;
                }

                /**
                 * ['key' => 'value']
                 */
                if ($received == $value) {
                    $hasAnyEventCaught = true;
                    if ($this->executeEventCallback($event['callback']) === false) {
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
                    if ($this->executeEventCallback($event['callback'], $this->buildParamsFromMatches($matches))  === false) {
                        return $hasAnyEventCaught;
                    }
                    break;
                }

                /**
                 * ['key' => '/regex/i]
                 */
                if (@preg_match_all($value, $received, $matches)) {
                    $hasAnyEventCaught = true;
                    if ($this->executeEventCallback($event['callback'], $this->buildParamsFromMatches($matches)) === false) {
                        return $hasAnyEventCaught;
                    }
                    break;
                }
            }
        }

        return $hasAnyEventCaught;
    }

    /**
     * @param callable|string|array $callback
     * @param array $params
     * @return mixed
     */
    protected function executeEventCallback($callback, $params = [])
    {
        /**
         * With force params.
         * $this->addEvent($event, [$callback, [$param1, $param2, ...], $sort])
         */
        if (is_array($callback)) {
            $tmp = $callback;
            $callback = array_shift($tmp);
            $params = array_merge($params, $tmp);
        }

        if (is_callable($callback) || $callback instanceof Closure) {
            return call_user_func_array($callback, $params);
        }

        [$controller, $method] = explode('@', $callback);

        try {
            $reflectedMethod = new ReflectionMethod($controller, $method);

            if ($reflectedMethod->isPublic() && (!$reflectedMethod->isAbstract())) {
                if ($reflectedMethod->isStatic()) {
                    return forward_static_call_array([$controller, $method], $params);
                } else {
                    if (is_string($controller)) {
                        $controller = new $controller();
                    }
                    return call_user_func_array([$controller, $method], $params);
                }
            }
        } catch (ReflectionException $reflectionException) {
            //
        }
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

    /**
     * @param string|array $pattern
     * @param callable|string|array $callback
     * @param int $sort
     * @return void
     */
    protected function addEvent($pattern, $callback, $sort)
    {
        $this->events[$sort][] = compact('pattern', 'callback');
    }

    /**
     * @return array
     */
    public function getEvents()
    {
        ksort($this->events);
        return call_user_func_array('array_merge', $this->events);
    }

    /**
     * @return boolean
     */
    public function hasPayload()
    {
        return $this->payload !== [];
    }
}
