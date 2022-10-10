<?php

namespace Chipslays\Event;

class Event
{
    use WithEvent;

    /**
     * @param array|string|stdClass|Collection $payload
     *
     * @throws EventException
     */
    public function __construct($payload = [])
    {
        $this->setPayload($payload);
    }
}