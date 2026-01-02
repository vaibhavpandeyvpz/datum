<?php

declare(strict_types=1);

namespace Datum\Events;

use Datum\Model;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Base class for model events.
 */
abstract class ModelEvent implements StoppableEventInterface
{
    protected bool $propagationStopped = false;

    /**
     * Create a new model event instance.
     */
    public function __construct(
        public readonly Model $model
    ) {}

    /**
     * {@inheritDoc}
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * Stop the propagation of the event.
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
