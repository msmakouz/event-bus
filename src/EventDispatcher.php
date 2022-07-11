<?php

declare(strict_types=1);

namespace Spiral\EventBus;

use Spiral\Core\CoreInterface;
use Symfony\Component\EventDispatcher\EventDispatcher as BaseEventDispatcher;

class EventDispatcher extends BaseEventDispatcher implements ListenerRegistryInterface
{
    public function __construct(
        private readonly ListenerFactory $listenerFactory,
        private readonly CoreInterface $core
    ) {
        parent::__construct();
    }

    /**{@inheritDoc}*/
    protected function callListeners(iterable $listeners, string $eventName, object $event)
    {
        $this->core->callAction($eventName, 'dispatch', [
            'event' => $event,
            'listeners' => $listeners,
        ]);
    }

    public function addListener(string $eventName, callable|array|string $listener, int $priority = 0): void
    {
        if (\is_string($listener)) {
            $listener = $this->listenerFactory->createQueueable($listener);
        } elseif (\is_array($listener) && \count($listener) === 2) {
            $listener = $this->listenerFactory->createQueueable($listener[0], $listener[1]);
        }

        parent::addListener($eventName, $listener, $priority);
    }
}
