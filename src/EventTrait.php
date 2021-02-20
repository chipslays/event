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
     * @param callable|string $fn
     * @param integer $sort
     * @return static
     */
    public function on($event, $fn, int $sort = 500)
    {
        if ($this->data === []) {
            return $this;
        }
        
        foreach ((array) $event as $key => $value) {

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
                $this->events[$sort][] = [
                    'fn' => $fn,
                ];
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
                $this->events[$sort][] = [
                    'fn' => $fn,
                ];
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
                $this->events[$sort][] = [
                    'fn' => $fn,
                    'params' => $this->buildParamsFromMatches($matches),
                ];
                break;
            }

            /**
             * ['key' => '/regex/i]
             */
            if (@preg_match_all($value, $received, $matches)) {
                $this->events[$sort][] = [
                    'fn' => $fn,
                    'params' => $this->buildParamsFromMatches($matches),
                ];
                break;
            }
        }

        return $this;
    }

    /**
     * Execute events.
     *
     * @return void
     */
    public function run()
    {
        $this->runCaughtEvents();
    }

    /**
     * @return bool - True: has any event caught, False: no caught events
     */
    private function runCaughtEvents()
    {
        if ($this->events === []) {
            return false;
        } else {
            foreach ($this->getEvents() as $event) {
                if ($this->executeController($event['fn'], $event['params'] ?? []) === false) {
                    break;
                }
            }
            return true;
        }
    }

    /**
     * @param callable|string $callback
     * @param array $params
     * @return mixed
     */
    private function executeController($fn, $params = [])
    {
        if (is_callable($fn) || $fn instanceof \Closure) {
            return call_user_func_array($fn, $params);
        }

        [$controller, $method] = explode('@', $fn);

        try {
            $reflectedMethod = new \ReflectionMethod($controller, $method);

            if ($reflectedMethod->isPublic() && (!$reflectedMethod->isAbstract())) {
                if ($reflectedMethod->isStatic()) {
                    return forward_static_call_array([$controller, $method], $params);
                } else {
                    if (\is_string($controller)) {
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
        }, array_slice($matches, 1)));
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
