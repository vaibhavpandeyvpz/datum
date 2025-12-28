<?php

declare(strict_types=1);

namespace Datum\Relations;

use Datum\Builder;
use Datum\Model;

/**
 * Class Relation
 *
 * Base class for model relationships.
 */
abstract class Relation
{
    /**
     * Create a new relationship instance.
     */
    public function __construct(
        protected readonly Model $parent,
        protected readonly string $related
    ) {}

    /**
     * Get the query builder for the relationship.
     */
    abstract public function query(): Builder;

    /**
     * Get the related model(s).
     */
    abstract public function results(): mixed;

    /**
     * Execute the relationship query and get the results.
     */
    public function __invoke(): mixed
    {
        return $this->results();
    }
}
