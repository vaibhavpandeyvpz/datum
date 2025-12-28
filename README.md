# Datum

[![Tests](https://github.com/vaibhavpandeyvpz/datum/workflows/Tests/badge.svg)](https://github.com/vaibhavpandeyvpz/datum/actions)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Code Coverage](https://img.shields.io/badge/coverage-90%25-brightgreen.svg)](https://github.com/vaibhavpandeyvpz/datum)

A simple Active Record ORM for PHP built on top of [vaibhavpandeyvpz/databoss](https://github.com/vaibhavpandeyvpz/databoss).

## Features

- **Active Record Pattern**: Simple, intuitive model definitions
- **Fluent Query Builder**: Chain methods like `where()`, `sort()`, `limit()`, `offset()`, `get()`, `first()`
- **Relationships**: Support for `one` (has one), `many` (has many), `owner` (belongs to), and `owners` (belongs to many) relationships
- **Attribute Casting**: Automatic type conversion for DateTime, arrays, JSON, integers, floats, and booleans
- **Automatic Timestamps**: Automatically manages `created_at` and `updated_at` timestamps (enabled by default)
- **Lazy Connection Loading**: Connection factory support for lazy database connection creation
- **Built on Databoss**: Leverages the powerful databoss filtering syntax
- **Multi-Database Support**: Works with MySQL, PostgreSQL, and SQLite
- **Type-safe**: Full PHP 8.2+ type declarations
- **Well Tested**: 90%+ code coverage with comprehensive test suite

## Requirements

- PHP >= 8.2
- PDO extension
- One of: `ext-pdo_mysql`, `ext-pdo_pgsql`, or `ext-pdo_sqlite` (depending on your database)

## Installation

```bash
composer require vaibhavpandeyvpz/datum
```

Or if you want to install the databoss dependency separately:

```bash
composer require vaibhavpandeyvpz/databoss ^2.0
composer require vaibhavpandeyvpz/datum
```

## Quick Start

### Setting Up the Connection

You can set the connection directly or use a connection factory for lazy connection creation using the `use()` method:

#### Direct Connection

```php
<?php

use Databoss\Connection;
use Datum\Model;

// Create a databoss connection
$connection = new Connection([
    Connection::OPT_DATABASE => 'mydb',
    Connection::OPT_USERNAME => 'root',
    Connection::OPT_PASSWORD => 'password',
]);

// Set the connection for all models
Model::connect($connection);
```

#### Connection Factory (Lazy Loading)

```php
<?php

use Databoss\Connection;
use Datum\Model;

// Set a connection factory that will be called lazily when needed
Model::connect(function () {
    return new Connection([
        Connection::OPT_DATABASE => 'mydb',
        Connection::OPT_USERNAME => 'root',
        Connection::OPT_PASSWORD => 'password',
    ]);
});

// The connection will only be created when you first use a model
$user = User::find(1); // Connection is created here
```

### Defining a Model

```php
<?php

use Datum\Model;

class User extends Model
{
    protected static ?string $table = 'users';
    protected static string $primaryKey = 'id';

    /**
     * Define attribute casts for automatic type conversion.
     *
     * @var array<string, string>
     */
    protected static array $casts = [
        'age' => 'int',
        'created_at' => 'datetime',
        'metadata' => 'array',
        'is_active' => 'bool',
    ];
}
```

### Basic CRUD Operations

```php
// Create
$user = new User([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);
$user->save(); // created_at and updated_at are automatically set

// Read
$user = User::find(1);
$user = User::findOrFail(1); // Throws exception if not found

// Update
$user->name = 'Jane Doe';
$user->save(); // updated_at is automatically updated

// Delete
$user->delete();

// Get all
$users = User::all();
```

### Automatic Timestamps

Datum automatically manages `created_at` and `updated_at` timestamps by default. When you save a model:

- **On Insert**: Both `created_at` and `updated_at` are automatically set to the current timestamp
- **On Update**: Only `updated_at` is automatically updated

```php
$user = new User(['name' => 'John', 'email' => 'john@example.com']);
$user->save(); // created_at and updated_at are set automatically

// Later...
$user->name = 'Jane';
$user->save(); // updated_at is automatically updated, created_at remains unchanged
```

**Disabling Timestamps:**

If you want to disable automatic timestamps for a model, set the `$timestamps` property to `false`:

```php
class User extends Model
{
    protected static bool $timestamps = false;
}
```

**Custom Timestamp Column Names:**

You can customize the timestamp column names:

```php
class User extends Model
{
    protected static string $createdAt = 'created_at';
    protected static string $updatedAt = 'updated_at';
}
```

**Manually Setting Timestamps:**

You can still manually set timestamps, and they will be respected:

```php
$user = new User([
    'name' => 'John',
    'email' => 'john@example.com',
    'created_at' => '2020-01-01 10:00:00',
    'updated_at' => '2020-01-02 10:00:00',
]);
$user->save(); // Your custom timestamps are preserved
```

**Using PSR-20 Clock:**

Datum uses PSR-20 `ClockInterface` for timestamp generation, allowing you to inject a custom clock implementation for testing or time manipulation:

```php
use Psr\Clock\ClockInterface;
use Datum\Model;

// Set a custom clock
Model::clock($yourClockInstance);
```

For testing, you can use `vaibhavpandeyvpz/samay` to control time:

```php
use Samay\FrozenClock;
use Datum\Model;

// Freeze time at a specific moment
$frozenTime = new \DateTimeImmutable('2024-01-15 10:30:00');
Model::clock(new FrozenClock($frozenTime));

$user = new User(['name' => 'Test']);
$user->save(); // Will use the frozen time for timestamps
```

### Attribute Casting

Datum supports automatic type casting for attributes. Define casts in your model's `$casts` property:

```php
class User extends Model
{
    protected static array $casts = [
        'age' => 'int',
        'created_at' => 'datetime',
        'metadata' => 'array',
        'is_active' => 'bool',
    ];
}
```

**Supported Cast Types:**

- `int` or `integer` - Casts to integer
- `float` or `double` - Casts to float
- `bool` or `boolean` - Casts to boolean (stored as 0/1 in database)
- `string` - Casts to string
- `array` or `json` - Automatically JSON encodes/decodes
- `datetime` or `date` - Casts to/from `DateTime` objects

**Example:**

```php
// When loading from database
$user = User::find(1);
$user->created_at; // DateTime object
$user->metadata;   // Array (decoded from JSON)
$user->age;        // Integer

// When setting values
$user->created_at = new DateTime('2024-01-15');
$user->metadata = ['role' => 'admin'];
$user->age = 25;
$user->save(); // Values are automatically cast for storage
```

### Querying

```php
// Using where() with databoss filter syntax
$users = User::where(['status' => 'active'])->get();
$user = User::where(['email' => 'john@example.com'])->first();

// Complex queries
$users = User::where(['age{>}' => 18])
    ->sort('created_at', 'DESC')
    ->limit(10)
    ->get();

// Count
$count = User::where(['status' => 'active'])->count();

// Check existence
$exists = User::where(['email' => 'john@example.com'])->exists();
```

### Relationships

#### Has One

```php
class User extends Model
{
    public function profile()
    {
        return $this->one(Profile::class, 'user_id');
    }
}

// Usage
$user = User::find(1);
$profile = $user->profile; // Automatically loaded
```

#### Has Many

```php
class User extends Model
{
    public function posts()
    {
        return $this->many(Post::class, 'user_id');
    }
}

// Usage
$user = User::find(1);
$posts = $user->posts; // Array of Post models
```

#### Belongs To (Owner)

```php
class Post extends Model
{
    public function user()
    {
        return $this->owner(User::class, 'user_id');
    }
}

// Usage
$post = Post::find(1);
$user = $post->user; // User model
```

#### Belongs To Many (Owners)

```php
class User extends Model
{
    public function roles()
    {
        return $this->owners(
            Role::class,
            'user_roles', // pivot table
            'user_id',    // foreign pivot key
            'role_id'     // related pivot key
        );
    }
}

// Usage
$user = User::find(1);
$roles = $user->roles; // Array of Role models
```

### Advanced Filtering

Datum supports all databoss filter syntax:

```php
// Comparison operators
User::where(['age{>}' => 18])->get();
User::where(['price{<=}' => 100])->get();
User::where(['status{!}' => 'inactive'])->get();

// LIKE
User::where(['name{~}' => '%John%'])->get();

// IN clause
User::where(['category' => ['electronics', 'books']])->get();

// NULL handling
User::where(['deleted_at' => null])->get();
User::where(['deleted_at{!}' => null])->get();

// Nested conditions
User::where([
    'age{>}' => 18,
    'OR' => [
        'status' => 'active',
        'verified' => true,
    ],
])->get();
```

## API Reference

### Model Static Properties

- `protected static ?string $table` - The table name (auto-inferred from class name if not set)
- `protected static string $primaryKey` - The primary key column name (default: `'id'`)
- `protected static array $casts` - Attribute casting configuration
- `protected static bool $timestamps` - Enable/disable automatic timestamp management (default: `true`)
- `protected static string $createdAt` - The name of the "created at" column (default: `'created_at'`)
- `protected static string $updatedAt` - The name of the "updated at" column (default: `'updated_at'`)

### Model Static Clock Methods

- `Model::clock(ClockInterface $clock)` - Set a PSR-20 clock instance for timestamp generation

### Model Static Methods

- `Model::connect(ConnectionInterface|callable(): ConnectionInterface $connectionOrFactory)` - Set the database connection directly or use a factory for lazy connection creation
- `Model::query()` - Create a new query builder instance
- `Model::where(array $conditions)` - Create a query with WHERE conditions
- `Model::find(int|string $id)` - Find a model by primary key (returns `null` if not found)
- `Model::findOrFail(int|string $id)` - Find a model or throw `RuntimeException` if not found
- `Model::all()` - Get all models from the table
- `Model::first()` - Execute the query and return the first model

### Model Instance Methods

- `$model->save()` - Save the model to database (inserts if new, updates if exists)
- `$model->delete()` - Delete the model from database
- `$model->exists()` - Check if model exists in database
- `$model->key()` - Get the primary key value
- `$model->toArray()` - Convert model to array (with casts applied)
- `$model->attribute(string $key)` - Get an attribute value (with casting)
- `$model->assign(string $key, mixed $value)` - Set an attribute value (with casting)
- `$model->attributes()` - Get all attributes as array (raw, without casting)
- `$model->freshTimestamp()` - Get a fresh timestamp string (used internally for automatic timestamps)

### Builder Methods

- `where(array $conditions)` - Add WHERE conditions (supports databoss filter syntax)
- `sort(string $column, string $direction = 'ASC')` - Add ORDER BY clause
- `limit(int $limit)` - Set LIMIT clause
- `offset(int $offset)` - Set OFFSET clause
- `get()` - Execute and return all results (returns `array|false`)
- `first()` - Execute and return first result (returns `object|array|false`)
- `count()` - Count matching records (returns `int|false`)
- `exists()` - Check if any records exist (returns `bool`)
- `recreate()` - Get a fresh instance of the query builder

### Relationship Methods

- `one(string $related, string $foreignKey, string $localKey = 'id')` - Define has one relationship
- `many(string $related, string $foreignKey, string $localKey = 'id')` - Define has many relationship
- `owner(string $related, string $foreignKey, string $ownerKey = 'id')` - Define belongs to relationship (this model is owned by another)
- `owners(string $related, string $pivotTable, string $foreignPivotKey, string $relatedPivotKey, string $parentKey = 'id', string $relatedKey = 'id')` - Define belongs to many relationship (this model is owned by many)

### Property Access

Models support magic property access for attributes and relationships:

```php
$user = User::find(1);
$user->name;        // Attribute access (with casting)
$user->profile;     // Relationship access (lazy loaded)
$user->name = 'New'; // Attribute assignment (with casting)
isset($user->name); // Check if attribute exists
```

## Examples

### Complete Example

```php
<?php

use Databoss\Connection;
use Datum\Model;

// Setup connection
$connection = new Connection([
    Connection::OPT_DATABASE => 'mydb',
    Connection::OPT_USERNAME => 'root',
    Connection::OPT_PASSWORD => 'password',
]);

Model::connect($connection);

// Define model
class User extends Model
{
    protected static ?string $table = 'users';

    protected static array $casts = [
        'age' => 'int',
        'created_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function profile()
    {
        return $this->one(Profile::class, 'user_id');
    }

    public function posts()
    {
        return $this->many(Post::class, 'user_id');
    }
}

// Create
$user = new User([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30,
    'created_at' => new DateTime(),
    'metadata' => ['role' => 'admin'],
]);
$user->save();

// Query
$users = User::where(['age{>}' => 25])
    ->sort('created_at', 'DESC')
    ->limit(10)
    ->get();

// Relationships
$profile = $user->profile;
$posts = $user->posts;
```

## Testing

The project includes Docker Compose configuration for running tests:

```bash
# Start database containers (MySQL and PostgreSQL)
docker compose up -d

# Wait for databases to be ready, then run tests
./vendor/bin/phpunit

# Run tests with coverage
XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-text

# Stop database containers
docker compose down
```

Tests run against MySQL, PostgreSQL, and SQLite to ensure compatibility across all supported databases.

The test suite includes:

- 163+ tests
- 426+ assertions
- 90%+ code coverage
- Tests for all CRUD operations
- Tests for all relationship types
- Tests for attribute casting
- Tests for query builder methods
- Edge case and error handling tests

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

MIT License - see [LICENSE](LICENSE) file for details.
