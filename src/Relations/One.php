<?php

declare(strict_types=1);

namespace Datum\Relations;

use Datum\Model;

/**
 * Class One
 *
 * Represents a "has one" relationship.
 */
class One extends Relation
{
    /**
     * Create a new "has one" relationship instance.
     */
    public function __construct(
        Model $parent,
        string $related,
        protected readonly string $foreignKey,
        protected readonly string $localKey = 'id'
    ) {
        parent::__construct($parent, $related);
    }

    /**
     * Get the query builder for the relationship.
     */
    public function query(): \Datum\Builder
    {
        $localKeyValue = $this->parent->attribute($this->localKey);

        return $this->related::query()
            ->where([$this->foreignKey => $localKeyValue]);
    }

    /**
     * Get the related model.
     */
    public function results(): ?Model
    {
        $result = $this->query()->first();

        return $result === false ? null : $this->related::preload($result);
    }
}
