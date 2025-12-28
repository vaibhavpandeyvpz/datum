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
     * The foreign key column name.
     */
    protected string $foreignKey;

    /**
     * The local key column name.
     */
    protected string $localKey;

    /**
     * Create a new "has one" relationship instance.
     */
    public function __construct(Model $parent, string $related, string $foreignKey, string $localKey = 'id')
    {
        parent::__construct($parent, $related);
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
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

        if ($result === false) {
            return null;
        }

        return $this->related::preload($result);
    }
}
