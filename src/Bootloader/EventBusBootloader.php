<?php

declare(strict_types=1);

namespace Spiral\EventBus\Bootloader;

use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Spiral\Attributes\AttributeReader;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Config\ConfiguratorInterface;
use Spiral\EventBus\Config\EventBusConfig;
use Spiral\EventBus\EventDispatcher;
use Spiral\EventBus\ListenerFactory;
use Spiral\EventBus\ListenerRegistryInterface;
use Spiral\EventBus\ListenersLocator;
use Spiral\EventBus\ListenersLocatorInterface;
use Spiral\Tokenizer\Bootloader\TokenizerBootloader;
use Spiral\Tokenizer\ScopedClassesInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EventBusBootloader extends Bootloader
{
    protected const LISTENS = [];

    protected const DEPENDENCIES = [
        TokenizerBootloader::class,
    ];

    protected const SINGLETONS = [
        ListenerRegistryInterface::class => EventDispatcher::class,
        EventDispatcherInterface::class => EventDispatcher::class,
        PsrEventDispatcherInterface::class => EventDispatcher::class,
        EventDispatcher::class => EventDispatcher::class,
        ListenersLocatorInterface::class => ListenersLocator::class,
        ListenersLocator::class => [self::class, 'initListenersLocator']
    ];

    /**
     * @return array<class-string, array<class-string>>
     */
    protected function listens(): array
    {
        return static::LISTENS;
    }

    public function __construct(
        private readonly ConfiguratorInterface $config
    ) {
    }

    public function init(EnvironmentInterface $env): void
    {
        $this->config->setDefaults(
            EventBusConfig::CONFIG,
            [
                'queueConnection' => $env->get('EVENT_BUS_QUEUE_CONNECTION'),
                'discoverListeners' => (bool)$env->get('EVENT_BUS_DISCOVER_LISTENERS', true),
            ]
        );
    }

    /**
     * @param EventDispatcher $dispatcher
     */
    public function boot(
        EventDispatcherInterface $dispatcher,
        ListenersLocatorInterface $listenersLocator,
        ListenerFactory $listenerFactory,
        EventBusConfig $config
    ): void {
        foreach ($this->listens() as $event => $listeners) {
            foreach (array_unique($listeners) as $listener) {
                $dispatcher->addListener($event, $listener);
            }
        }

        foreach ($config->getListeners() as $event => $listeners) {
            foreach (array_unique($listeners) as $listener) {
                $dispatcher->addListener($event, $listener);
            }
        }

        if (! $config->discoverListeners()) {
            return;
        }

        foreach ($listenersLocator->getListeners() as $event => $listeners) {
            foreach ($listeners as $listener) {
                $dispatcher->addListener($event, $listenerFactory->createQueueable($listener[0], $listener[1]));
            }
        }
    }

    private function initListenersLocator(ScopedClassesInterface $classes): ListenersLocatorInterface
    {
        return new ListenersLocator(
            $classes,
            new AttributeReader()
        );
    }
}
