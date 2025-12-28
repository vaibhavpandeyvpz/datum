<?php

declare(strict_types=1);

namespace Datum;

use Databoss\ConnectionInterface;

/**
 * Class Builder
 *
 * Fluent query builder for building database queries using databoss.
 */
class Builder
{
    /**
     * WHERE conditions array.
     *
     * @var array<string, mixed>
     */
    protected array $where = [];

    /**
     * ORDER BY clauses.
     *
     * @var array<string, string>
     */
    protected array $orderBy = [];

    /**
     * Maximum number of records to return.
     */
    protected ?int $limit = null;

    /**
     * Starting offset for records.
     */
    protected ?int $offset = null;

    // <editor-fold desc="Constructor">

    /**
     * Create a new query builder instance.
     */
    public function __construct(
        protected ?ConnectionInterface $connection = null,
        protected ?string $table = null
    ) {}

    // </editor-fold>

    // <editor-fold desc="Query Building Methods">

    /**
     * Add WHERE conditions using databoss filter syntax.
     *
     * @param  array<string, mixed>  $conditions
     */
    public function where(array $conditions): self
    {
        $this->where = array_merge($this->where, $conditions);

        return $this;
    }

    /**
     * Add ORDER BY clause.
     */
    public function sort(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[$column] = strtoupper($direction);

        return $this;
    }

    /**
     * Set the limit.
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Set the offset.
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    // </editor-fold>

    // <editor-fold desc="Execution Methods">

    /**
     * Execute the query and return all results.
     *
     * @return array<int, object|array<string, mixed>>|false
     */
    public function get(): array|false
    {
        if ($this->connection === null || $this->table === null) {
            return false;
        }

        $max = $this->limit ?? 0;
        $start = $this->offset ?? 0;

        return $this->connection->select(
            $this->table,
            '*',
            $this->where,
            $this->orderBy,
            $max,
            $start
        );
    }

    /**
     * Execute the query and return the first result.
     *
     * @return object|array<string, mixed>|false
     */
    public function first(): object|array|false
    {
        if ($this->connection === null || $this->table === null) {
            return false;
        }

        $start = $this->offset ?? 0;

        return $this->connection->first(
            $this->table,
            $this->where,
            $this->orderBy,
            $start
        );
    }

    /**
     * Count the number of records.
     */
    public function count(): int|false
    {
        if ($this->connection === null || $this->table === null) {
            return false;
        }

        return $this->connection->count(
            $this->table,
            '*',
            $this->where
        );
    }

    /**
     * Check if any records exist.
     */
    public function exists(): bool
    {
        if ($this->connection === null || $this->table === null) {
            return false;
        }

        return $this->connection->exists($this->table, $this->where);
    }

    // </editor-fold>

    // <editor-fold desc="Utility Methods">

    /**
     * Get a fresh instance of the query builder.
     */
    public function recreate(): self
    {
        return new self($this->connection, $this->table);
    }
    // </editor-fold>
}
