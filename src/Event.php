<?php

namespace Chipslays\Event;

use Chipslays\Event\EventTrait;

class Event
{
    use EventTrait;

    public function getEventData()
    {
        return $this->data;
    }
}
