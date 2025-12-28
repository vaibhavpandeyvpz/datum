<?php

declare(strict_types=1);

namespace Datum;

use Databoss\ConnectionInterface;
use Datum\Relations\Many;
use Datum\Relations\One;
use Datum\Relations\Owner;
use Datum\Relations\Owners;
use Psr\Clock\ClockInterface;

/**
 * Class Model
 *
 * Base model class implementing Active Record pattern.
 */
abstract class Model
{
    /**
     * The table name for the model.
     */
    protected static ?string $table = null;

    /**
     * The primary key for the model.
     */
    protected static string $primaryKey = 'id';

    /**
     * The attribute casts.
     *
     * @var array<string, string>
     */
    protected static array $casts = [];

    /**
     * Indicates if the model should be timestamped.
     */
    protected static bool $timestamps = true;

    /**
     * The name of the "created at" column.
     */
    protected static string $createdAt = 'created_at';

    /**
     * The name of the "updated at" column.
     */
    protected static string $updatedAt = 'updated_at';

    /**
     * The connection instance.
     */
    protected static ?ConnectionInterface $connection = null;

    /**
     * The connection factory (callable that returns ConnectionInterface).
     *
     * @var (callable(): ConnectionInterface)|null
     */
    protected static $connectionFactory = null;

    /**
     * The clock instance for timestamp generation.
     */
    protected static ?ClockInterface $clock = null;

    /**
     * The model's attributes.
     *
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * Whether the model exists in the database.
     */
    protected bool $exists = false;

    /**
     * The loaded relationships.
     *
     * @var array<string, mixed>
     */
    protected array $relations = [];

    // <editor-fold desc="Constructor">

    /**
     * Create a new model instance.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    // </editor-fold>

    // <editor-fold desc="Static Configuration Methods">

    /**
     * Set the connection instance or factory.
     *
     * @param  ConnectionInterface|(callable(): ConnectionInterface)  $connectionOrFactory
     */
    public static function connect(ConnectionInterface|callable $connectionOrFactory): void
    {
        if ($connectionOrFactory instanceof ConnectionInterface) {
            static::$connection = $connectionOrFactory;
            static::$connectionFactory = null;
        } else {
            static::$connectionFactory = $connectionOrFactory;
            static::$connection = null;
        }
    }

    /**
     * Set the clock instance for timestamp generation.
     */
    public static function clock(ClockInterface $clock): void
    {
        static::$clock = $clock;
    }

    /**
     * Get the clock instance, creating a default system clock if needed.
     */
    protected static function getClock(): ClockInterface
    {
        return static::$clock ??= new class implements ClockInterface
        {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable;
            }
        };
    }

    /**
     * Get the connection instance, creating it lazily from factory if needed.
     */
    protected static function getConnection(): ?ConnectionInterface
    {
        // If connection is already set, return it
        if (static::$connection !== null) {
            return static::$connection;
        }

        // If factory is set, create connection from factory
        return static::$connectionFactory !== null
            ? static::$connection = (static::$connectionFactory)()
            : null;
    }

    /**
     * Get the table name for the model.
     */
    protected static function getTable(): string
    {
        return static::$table ??= match (true) {
            default => (function () {
                // Convert class name to table name (e.g., User -> users)
                $className = basename(str_replace('\\', '/', static::class));
                $table = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));

                return $table.'s';
            })()
        };
    }

    /**
     * Get the primary key for the model.
     */
    protected static function getPrimaryKey(): string
    {
        return static::$primaryKey;
    }

    // </editor-fold>

    // <editor-fold desc="Static Query Methods">

    /**
     * Create a new query builder instance.
     */
    public static function query(): Builder
    {
        return new Builder(static::getConnection(), static::getTable());
    }

    /**
     * Create a new query builder with WHERE conditions.
     *
     * @param  array<string, mixed>  $conditions
     */
    public static function where(array $conditions): Builder
    {
        return static::query()->where($conditions);
    }

    /**
     * Find a model by its primary key.
     */
    public static function find(int|string $id): ?static
    {
        $result = static::where([static::getPrimaryKey() => $id])->first();

        return $result === false ? null : static::preload($result);
    }

    /**
     * Find a model by its primary key or throw an exception.
     *
     * @throws \RuntimeException
     */
    public static function findOrFail(int|string $id): static
    {
        return static::find($id) ?? throw new \RuntimeException("Model not found with id: {$id}");
    }

    /**
     * Get all models.
     *
     * @return array<int, static>
     */
    public static function all(): array
    {
        $results = static::query()->get();

        return $results === false ? [] : array_map(fn ($result) => static::preload($result), $results);
    }

    /**
     * Execute the query and return the first model.
     */
    public static function first(): ?static
    {
        $result = static::query()->first();

        return $result === false ? null : static::preload($result);
    }

    // </editor-fold>

    // <editor-fold desc="Static Helper Methods">

    /**
     * Create a new model instance from a database result.
     *
     * @param  object|array<string, mixed>  $result
     */
    public static function preload(object|array $result): static
    {
        $attributes = is_object($result) ? (array) $result : $result;
        $model = new static($attributes);
        $model->exists = true;

        return $model;
    }

    // </editor-fold>

    // <editor-fold desc="Instance Attribute Methods">

    /**
     * Get an attribute value.
     */
    public function attribute(string $key): mixed
    {
        $value = $this->attributes[$key] ?? null;

        return $value === null ? null : $this->castAttribute($key, $value);
    }

    /**
     * Cast an attribute value from database format to PHP type.
     */
    protected function castAttribute(string $key, mixed $value): mixed
    {
        $cast = static::$casts[$key] ?? null;

        if ($cast === null) {
            return $value;
        }

        return match ($cast) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'string' => (string) $value,
            'array', 'json' => is_string($value) ? json_decode($value, true) : $value,
            'datetime', 'date' => $this->castToDateTime($value),
            default => $value,
        };
    }

    /**
     * Cast a value to DateTime.
     */
    protected function castToDateTime(mixed $value): ?\DateTime
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTime) {
            return $value;
        }

        if (is_numeric($value)) {
            return (new \DateTime)->setTimestamp((int) $value);
        }

        if (is_string($value)) {
            try {
                return new \DateTime($value);
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Set an attribute value.
     */
    public function assign(string $key, mixed $value): void
    {
        $this->attributes[$key] = $this->castAttributeForStorage($key, $value);
    }

    /**
     * Cast an attribute value from PHP type to database format.
     */
    protected function castAttributeForStorage(string $key, mixed $value): mixed
    {
        $cast = static::$casts[$key] ?? null;

        if ($cast === null || $value === null) {
            return $value;
        }

        return match ($cast) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => (bool) $value ? 1 : 0,
            'string' => (string) $value,
            'array', 'json' => is_array($value) ? json_encode($value) : $value,
            'datetime', 'date' => $this->castDateTimeForStorage($value),
            default => $value,
        };
    }

    /**
     * Cast a DateTime value for storage.
     */
    protected function castDateTimeForStorage(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value)) {
            try {
                $dateTime = new \DateTime($value);

                return $dateTime->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                return $value;
            }
        }

        return $value;
    }

    /**
     * Get all attributes.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get an attribute via property access.
     */
    public function __get(string $key): mixed
    {
        // Check if it's a relationship
        if (method_exists($this, $key) && ! isset($this->attributes[$key])) {
            try {
                $relation = $this->$key();
                if ($relation instanceof \Datum\Relations\Relation) {
                    // Cache the relationship result
                    return $this->relations[$key] ??= $relation->results();
                }
            } catch (\Throwable) {
                // If the method throws an error, fall through to attribute access
            }
        }

        return $this->attribute($key);
    }

    /**
     * Set an attribute via property access.
     */
    public function __set(string $key, mixed $value): void
    {
        $this->assign($key, $value);
    }

    /**
     * Check if an attribute exists.
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    // </editor-fold>

    // <editor-fold desc="Instance Persistence Methods">

    /**
     * Save the model to the database.
     */
    public function save(): bool
    {
        $connection = static::getConnection();

        if ($connection === null) {
            return false;
        }

        if ($this->exists) {
            return $this->update();
        }

        return $this->insert();
    }

    /**
     * Insert the model into the database.
     */
    protected function insert(): bool
    {
        $connection = static::getConnection();

        if ($connection === null) {
            return false;
        }

        // Set timestamps if enabled
        if (static::$timestamps) {
            $createdAt = static::$createdAt;
            $updatedAt = static::$updatedAt;

            // Set created_at if not already set
            if (! isset($this->attributes[$createdAt])) {
                $this->attributes[$createdAt] = $this->freshTimestamp();
            }

            // Set updated_at if not already set
            if (! isset($this->attributes[$updatedAt])) {
                $this->attributes[$updatedAt] = $this->freshTimestamp();
            }
        }

        $result = $connection->insert(static::getTable(), $this->attributes);

        if ($result !== false) {
            $primaryKey = static::getPrimaryKey();
            $id = $connection->id();

            if ($id !== false) {
                $this->attributes[$primaryKey] = $id;
            }

            $this->exists = true;

            return true;
        }

        return false;
    }

    /**
     * Update the model in the database.
     */
    protected function update(): bool
    {
        $connection = static::getConnection();

        if ($connection === null) {
            return false;
        }

        $primaryKey = static::getPrimaryKey();
        $id = $this->attributes[$primaryKey] ?? null;

        if ($id === null) {
            return false;
        }

        // Set updated_at if timestamps are enabled
        if (static::$timestamps) {
            $updatedAt = static::$updatedAt;
            $this->attributes[$updatedAt] = $this->freshTimestamp();
        }

        $attributes = $this->attributes;
        unset($attributes[$primaryKey]);

        $result = $connection->update(
            static::getTable(),
            $attributes,
            [$primaryKey => $id]
        );

        return $result !== false;
    }

    /**
     * Delete the model from the database.
     */
    public function delete(): bool
    {
        if (! $this->exists) {
            return false;
        }

        $connection = static::getConnection();

        if ($connection === null) {
            return false;
        }

        $primaryKey = static::getPrimaryKey();
        $id = $this->attributes[$primaryKey] ?? null;

        if ($id === null) {
            return false;
        }

        $result = $connection->delete(
            static::getTable(),
            [$primaryKey => $id]
        );

        if ($result !== false) {
            $this->exists = false;

            return true;
        }

        return false;
    }

    /**
     * Check if the model exists in the database.
     */
    public function exists(): bool
    {
        return $this->exists;
    }

    // </editor-fold>

    // <editor-fold desc="Instance Utility Methods">

    /**
     * Get a fresh timestamp for the model.
     */
    public function freshTimestamp(): string
    {
        return static::getClock()->now()->format('Y-m-d H:i:s');
    }

    /**
     * Get the primary key value.
     */
    public function key(): mixed
    {
        return $this->attributes[static::getPrimaryKey()] ?? null;
    }

    /**
     * Convert the model to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_combine(
            array_keys($this->attributes),
            array_map(
                fn ($key, $value) => $this->castAttribute($key, $value),
                array_keys($this->attributes),
                $this->attributes
            )
        ) ?: [];
    }

    // </editor-fold>

    // <editor-fold desc="Relationship Methods">

    /**
     * Define a "has one" relationship.
     */
    protected function one(string $related, string $foreignKey, string $localKey = 'id'): One
    {
        return new One($this, $related, $foreignKey, $localKey);
    }

    /**
     * Define a "has many" relationship.
     */
    protected function many(string $related, string $foreignKey, string $localKey = 'id'): Many
    {
        return new Many($this, $related, $foreignKey, $localKey);
    }

    /**
     * Define a "belongs to" relationship (this model is owned by another).
     */
    protected function owner(string $related, string $foreignKey, string $ownerKey = 'id'): Owner
    {
        return new Owner($this, $related, $foreignKey, $ownerKey);
    }

    /**
     * Define a "belongs to many" relationship (this model is owned by many).
     */
    protected function owners(
        string $related,
        string $pivotTable,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey = 'id',
        string $relatedKey = 'id'
    ): Owners {
        return new Owners(
            $this,
            $related,
            $pivotTable,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey
        );
    }
    // </editor-fold>
}
