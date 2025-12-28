<?php

declare(strict_types=1);

namespace Datum\Relations;

use Datum\Model;

/**
 * Class Owner
 *
 * Represents a "belongs to" relationship (this model is owned by another).
 */
class Owner extends Relation
{
    /**
     * Create a new "belongs to" relationship instance.
     */
    public function __construct(
        Model $parent,
        string $related,
        protected readonly string $foreignKey,
        protected readonly string $ownerKey = 'id'
    ) {
        parent::__construct($parent, $related);
    }

    /**
     * Get the query builder for the relationship.
     */
    public function query(): \Datum\Builder
    {
        $ownerKeyValue = $this->parent->attribute($this->foreignKey);

        return $this->related::query()
            ->where([$this->ownerKey => $ownerKeyValue]);
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
