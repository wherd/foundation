<?php

declare(strict_types=1);

namespace Wherd\Foundation;

class Event
{
    /** @var array<string,\SplPriorityQueue<int,callable>> */
    protected array $queue = [];

    public function on(string $name, callable $callback, int $priority=10): void
    {
        if (!isset($this->queue[$name])) {
            $this->queue[$name] = new \SplPriorityQueue();
        }

        $this->queue[$name]->insert($callback, $priority);
    }

    public function dispatch(string $name, mixed ...$arguments): void
    {
        /** @var \SplPriorityQueue<int,callable> */
        $filters = $this->queue[$name] ?? new \SplPriorityQueue();

        foreach ($filters as $filter) {
            $filter(...$arguments);
        }
    }

    public function first(string $name, mixed ...$arguments): mixed
    {
        /** @var \SplPriorityQueue<int,callable> */
        $filters = $this->queue[$name] ?? new \SplPriorityQueue();

        foreach ($filters as $filter) {
            $value = $filter(...$arguments);

            if (null !== $value) {
                return $value;
            }
        }

        return null;
    }

    public function filter(string $name, mixed $value, mixed ...$arguments): mixed
    {
        /** @var \SplPriorityQueue<int,callable> */
        $filters = $this->queue[$name] ?? new \SplPriorityQueue();

        foreach ($filters as $filter) {
            $value = $filter($value, ...$arguments);
        }

        return $value;
    }
}
