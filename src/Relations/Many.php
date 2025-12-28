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
     * The foreign key column name.
     */
    protected string $foreignKey;

    /**
     * The local key column name.
     */
    protected string $localKey;

    /**
     * Create a new "has many" relationship instance.
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
     * Get the related models.
     *
     * @return array<int, Model>
     */
    public function results(): array
    {
        $results = $this->query()->get();

        if ($results === false) {
            return [];
        }

        return array_map(fn ($result) => $this->related::preload($result), $results);
    }
}
