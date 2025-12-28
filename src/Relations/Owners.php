<?php

declare(strict_types=1);

namespace Datum\Relations;

use Datum\Model;

/**
 * Class Owners
 *
 * Represents a "belongs to many" (many-to-many) relationship (this model is owned by many).
 */
class Owners extends Relation
{
    /**
     * Create a new "belongs to many" relationship instance.
     */
    public function __construct(
        Model $parent,
        string $related,
        protected readonly string $pivotTable,
        protected readonly string $foreignPivotKey,
        protected readonly string $relatedPivotKey,
        protected readonly string $parentKey = 'id',
        protected readonly string $relatedKey = 'id'
    ) {
        parent::__construct($parent, $related);
    }

    /**
     * Get the query builder for the relationship.
     */
    public function query(): \Datum\Builder
    {
        $parentKeyValue = $this->parent->attribute($this->parentKey);

        // Use the related model's connection to query the pivot table
        // Get connection through the related model's query builder
        $tempBuilder = $this->related::query();

        // Access connection via reflection since Builder's connection is protected
        $reflection = new \ReflectionClass($tempBuilder);
        $connectionProperty = $reflection->getProperty('connection');
        $connectionProperty->setAccessible(true);
        $connection = $connectionProperty->getValue($tempBuilder);

        if ($connection === null) {
            // If no connection, return a query that will return no results
            return $this->related::query()->where([$this->relatedKey => -1]);
        }

        // Get related IDs from pivot table
        $pivotResults = $connection->select(
            $this->pivotTable,
            $this->relatedPivotKey,
            [$this->foreignPivotKey => $parentKeyValue]
        );

        if ($pivotResults === false || $pivotResults === []) {
            // Return a query that will return no results
            return $this->related::query()->where([$this->relatedKey => -1]);
        }

        $relatedIds = array_map(
            fn ($row) => is_object($row) ? $row->{$this->relatedPivotKey} : $row[$this->relatedPivotKey],
            $pivotResults
        );

        return $this->related::query()
            ->where([$this->relatedKey => $relatedIds]);
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
