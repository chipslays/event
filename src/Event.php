<?php

namespace Chipslays\Event;

use Chipslays\Event\EventTrait;

class Event
{
    use EventTrait;
    
    /**
     * @return Chipslays\Collection\Collection
     */
    public function getEventData()
    {
        return $this->data;
    }
}
