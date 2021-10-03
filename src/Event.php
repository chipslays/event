<?php

namespace Chipslays\Event;

use Chipslays\Event\EventTrait;
use Chipslays\Collection\Collection;

class Event
{
    use EventTrait;

    /**
     * @return Collection
     */
    public function getPayload()
    {
        return $this->payload;
    }
}
