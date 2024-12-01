<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Hooks;

trait HasWriteHooks
{
    private array $writeEvents = [];

    public function onPreInsert(?callable $callback = null)
    {
        if (!$callback) {
            return array_key_exists(EventType::PRE_INSERT->value, $this->writeEvents) ? $this->writeEvents[EventType::PRE_INSERT->value] : null;
        }

        $this->writeEvents[EventType::PRE_INSERT->value] = $callback;

        return $this;
    }

    public function onPostInsert(?callable $callback = null)
    {
        if (!$callback) {
            return array_key_exists(EventType::POST_INSERT->value, $this->writeEvents) ? $this->writeEvents[EventType::POST_INSERT->value] : null;
        }

        $this->writeEvents[EventType::POST_INSERT->value] = $callback;

        return $this;
    }

    public function onPreUpdate(?callable $callback = null)
    {
        if (!$callback) {
            return array_key_exists(EventType::PRE_UPDATE->value, $this->writeEvents) ? $this->writeEvents[EventType::PRE_UPDATE->value] : null;
        }

        $this->writeEvents[EventType::PRE_UPDATE->value] = $callback;

        return $this;
    }

    public function onPostUpdate(?callable $callback = null)
    {
        if (!$callback) {
            return array_key_exists(EventType::POST_UPDATE->value, $this->writeEvents) ? $this->writeEvents[EventType::POST_UPDATE->value] : null;
        }

        $this->writeEvents[EventType::POST_UPDATE->value] = $callback;

        return $this;
    }

    public function onPreDelete(?callable $callback = null)
    {
        if (!$callback) {
            return array_key_exists(EventType::PRE_DELETE->value, $this->writeEvents) ? $this->writeEvents[EventType::PRE_DELETE->value] : null;
        }

        $this->writeEvents[EventType::PRE_DELETE->value] = $callback;

        return $this;
    }

    public function onPostDelete(?callable $callback = null)
    {
        if (!$callback) {
            return array_key_exists(EventType::POST_DELETE->value, $this->writeEvents) ? $this->writeEvents[EventType::POST_DELETE->value] : null;
        }

        $this->writeEvents[EventType::POST_DELETE->value] = $callback;

        return $this;
    }
}
