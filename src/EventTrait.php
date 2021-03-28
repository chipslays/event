<?php

namespace Chipslays\Event;

use Chipslays\Collection\Collection;

trait EventTrait
{
    /**
     * @var Collection
     */
    private $data = [];

    /**
     * @var array
     */
    private $events = [];

    /**
     * @param array|string|\stdClass|\Chipslays\Collection\Collection $data
     */
    public function __construct($data)
    {
        $this->setEventData($data);
    }

    /**
     * @param array|string|\stdClass|\Chipslays\Collection\Collection $data
     */
    public function setEventData($data)
    {
        if (is_string($data)) {
            if (($data = json_decode($data, true)) !== null) {
                return ($this->data = new Collection($data));
            } else {
                throw new \Exception("String must be a valid JSON.");
            }
        }

        $this->data = $data instanceof Collection ? $data : new Collection($data);
    }

    /**
     * Handle events.
     * 
     * @param array|string $event
     * @param callable|string|array $callback
     * @param integer $sort
     * @return mixed
     */
    public function on($event, $callback, int $sort = 500)
    {
        $this->addEvent($event, $callback, $sort);
    }

    /**
     * Execute events.
     *
     * @return void
     */
    public function run()
    {
        $this->runAllEvents();
    }

    /**
     * @return bool True any event has been caught, False no one event not be caught.
     */
    protected function runAllEvents()
    {
        $hasAnyEventCaught = false;

        foreach ($this->getEvents() as $item) {
            foreach ((array) $item['event'] as $key => $value) {

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
                if (is_numeric($key) && $this->data->has($value)) {
                    $hasAnyEventCaught = true;
                    if ($this->executeCallback($item['callback']) === false) {
                        return $hasAnyEventCaught;
                    }
                    break;
                }

                /**
                 * Get value by key, if not exists then skip iteration.
                 * ['key' => 'value']
                 */
                if (!$received = $this->data->get($key)) {
                    continue;
                }

                /**
                 * ['key' => 'value']
                 */
                if ($received == $value) {
                    $hasAnyEventCaught = true;
                    if ($this->executeCallback($item['callback']) === false) {
                        return $hasAnyEventCaught;
                    }
                    break;
                }

                /**
                 * ['key' => 'my name is {name}']
                 * 
                 * command(?: (.*?))?(?: (.*?))?$
                 */
                $value = preg_replace('~.?{(.*?)\?}~', '(?: (.*?))?', $value);
                $pattern = '~^' . preg_replace('/{(.*?)}/', '(.*?)', $value) . '$~';

                if (@preg_match_all($pattern, $received, $matches)) {
                    $hasAnyEventCaught = true;
                    if ($this->executeCallback($item['callback'], $this->buildParamsFromMatches($matches))  === false) {
                        return $hasAnyEventCaught;
                    }
                    break;
                }

                /**
                 * ['key' => '/regex/i]
                 */
                if (@preg_match_all($value, $received, $matches)) {
                    $hasAnyEventCaught = true;
                    if ($this->executeCallback($item['callback'], $this->buildParamsFromMatches($matches)) === false) {
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
    private function executeCallback($callback, $params = [])
    {
        /**
         * $this->addEvent($event, [$callback, [$param1, $param2, ...], $sort])
         */
        if (is_array($callback)) {
            $tmp = $callback;
            $callback = array_shift($tmp);
            $params = array_merge($params, $tmp);
        }

        if (is_callable($callback) || $callback instanceof \Closure) {
            return call_user_func_array($callback, $params);
        }

        [$controller, $method] = explode('@', $callback);

        try {
            $reflectedMethod = new \ReflectionMethod($controller, $method);

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
        } catch (\ReflectionException $reflectionException) {
            // Do something...
        }
    }

    /**
     * @param array $matches
     * @return array
     */
    private function buildParamsFromMatches($matches)
    {
        return array_filter(array_map(function ($item) {
            return array_shift($item);
        }, array_slice($matches, 1)), 'strlen');
    }

    /**
     * @param string|array $event
     * @param callable|string|array $callback
     * @param int $sort
     * @return void
     */
    protected function addEvent($event, $callback, $sort)
    {
        $this->events[$sort][] = [
            'event' => $event,
            'callback' => $callback,
        ];
    }

    /**
     * @return array
     */
    private function getEvents()
    {
        ksort($this->events);
        return call_user_func_array('array_merge', $this->events);
    }
}
