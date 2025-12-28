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
     * The foreign key column name.
     */
    protected string $foreignKey;

    /**
     * The owner key column name.
     */
    protected string $ownerKey;

    /**
     * Create a new "belongs to" relationship instance.
     */
    public function __construct(Model $parent, string $related, string $foreignKey, string $ownerKey = 'id')
    {
        parent::__construct($parent, $related);
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
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

        if ($result === false) {
            return null;
        }

        return $this->related::preload($result);
    }
}
