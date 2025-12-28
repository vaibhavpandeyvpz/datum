<?php

declare(strict_types=1);

namespace Datum\Relations;

use Datum\Model;

/**
 * Class Many
 *
 * Represents a "has many" relationship.
 */
class Many extends Relation
{
    /**
     * Create a new "has many" relationship instance.
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
     * Get the related models.
     *
     * @return array<int, Model>
     */
    public function results(): array
    {
        $results = $this->query()->get();

        return $results === false ? [] : array_map(fn ($result) => $this->related::preload($result), $results);
    }
}
