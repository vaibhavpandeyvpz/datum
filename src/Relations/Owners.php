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
     * The pivot table name.
     */
    protected string $pivotTable;

    /**
     * The foreign pivot key column name.
     */
    protected string $foreignPivotKey;

    /**
     * The related pivot key column name.
     */
    protected string $relatedPivotKey;

    /**
     * The parent key column name.
     */
    protected string $parentKey;

    /**
     * The related key column name.
     */
    protected string $relatedKey;

    /**
     * Create a new "belongs to many" relationship instance.
     */
    public function __construct(
        Model $parent,
        string $related,
        string $pivotTable,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey = 'id',
        string $relatedKey = 'id'
    ) {
        parent::__construct($parent, $related);
        $this->pivotTable = $pivotTable;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
        $this->parentKey = $parentKey;
        $this->relatedKey = $relatedKey;
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

        $relatedIds = array_map(function ($row) {
            return is_object($row) ? $row->{$this->relatedPivotKey} : $row[$this->relatedPivotKey];
        }, $pivotResults);

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

        if ($results === false) {
            return [];
        }

        return array_map(fn ($result) => $this->related::preload($result), $results);
    }
}
