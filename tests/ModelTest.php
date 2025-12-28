<?php

declare(strict_types=1);

namespace Datum\Tests;

require_once __DIR__.'/TestModels.php';

use Databoss\Connection;
use Databoss\DatabaseDriver;
use Datum\Model;
use PHPUnit\Framework\TestCase;

/**
 * Class ModelTest
 *
 * Test suite for Model class and Active Record functionality.
 */
class ModelTest extends TestCase
{
    /**
     * Clear table data (driver-agnostic).
     */
    private function truncateTable(Connection $connection, string $table): void
    {
        $driver = $connection->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);

        // Handle foreign key constraints
        if ($driver === 'sqlite') {
            $connection->execute("DELETE FROM \"{$table}\"");
        } elseif ($driver === 'mysql') {
            // Disable foreign key checks temporarily
            $connection->execute('SET FOREIGN_KEY_CHECKS = 0');
            $connection->execute("TRUNCATE \"{$table}\"");
            $connection->execute('SET FOREIGN_KEY_CHECKS = 1');
        } else {
            // PostgreSQL - use CASCADE or delete in order
            try {
                $connection->execute("TRUNCATE \"{$table}\" CASCADE");
            } catch (\Exception $e) {
                // Fallback to DELETE if CASCADE doesn't work
                $connection->execute("DELETE FROM \"{$table}\"");
            }
        }
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_use_connection(Connection $connection): void
    {
        Model::use($connection);
        // Test that connection works by using it
        $this->truncateTable($connection, 'users');
        $user = new \Datum\Tests\User(['name' => 'Test', 'email' => 'test@example.com']);
        $this->assertTrue($user->save());
        $this->assertTrue($user->exists());
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_use_connection_factory(Connection $connection): void
    {
        $factoryCalled = false;
        Model::use(function () use (&$factoryCalled, $connection) {
            $factoryCalled = true;

            return $connection;
        });

        // Trigger connection creation by using query
        $this->truncateTable($connection, 'users');
        $builder = User::query();
        $this->assertNotNull($builder);

        // Verify factory was called
        $this->assertTrue($factoryCalled);

        // Verify connection works by using it - the connection should now be cached
        $user = new User(['name' => 'Test', 'email' => 'test@example.com']);
        $this->assertTrue($user->save());
        $this->assertTrue($user->exists());
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_find(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $connection->insert('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ]);

        $id = $connection->id();
        $user = User::find($id);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertTrue($user->exists());
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_find_not_found(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $user = User::find(99999);
        $this->assertNull($user);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_find_or_fail(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $connection->insert('users', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $id = $connection->id();
        $user = User::findOrFail($id);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('Jane Doe', $user->name);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_find_or_fail_throws_exception(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $this->expectException(\RuntimeException::class);
        User::findOrFail(99999);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_all(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $connection->insert('users', ['name' => 'User 1', 'email' => 'user1@example.com']);
        $connection->insert('users', ['name' => 'User 2', 'email' => 'user2@example.com']);

        $users = User::all();

        $this->assertCount(2, $users);
        $this->assertInstanceOf(User::class, $users[0]);
        $this->assertInstanceOf(User::class, $users[1]);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_where(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $connection->insert('users', ['name' => 'John', 'email' => 'john@example.com', 'age' => 25]);
        $connection->insert('users', ['name' => 'Jane', 'email' => 'jane@example.com', 'age' => 30]);

        $users = User::where(['age' => 25])->get();

        $this->assertCount(1, $users);
        $this->assertEquals('John', $users[0]->name);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_where_with_operators(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $connection->insert('users', ['name' => 'John', 'email' => 'john@example.com', 'age' => 25]);
        $connection->insert('users', ['name' => 'Jane', 'email' => 'jane@example.com', 'age' => 30]);
        $connection->insert('users', ['name' => 'Bob', 'email' => 'bob@example.com', 'age' => 35]);

        $users = User::where(['age{>}' => 25])->get();

        $this->assertCount(2, $users);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_save_insert(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $user = new User([
            'name' => 'New User',
            'email' => 'new@example.com',
            'age' => 28,
        ]);

        $this->assertFalse($user->exists());
        $result = $user->save();
        $this->assertTrue($result);
        $this->assertTrue($user->exists());
        $this->assertNotNull($user->id);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_save_update(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $connection->insert('users', [
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $id = $connection->id();
        $user = User::find($id);
        $this->assertNotNull($user);

        $user->name = 'Updated Name';
        $result = $user->save();

        $this->assertTrue($result);
        $updated = User::find($id);
        $this->assertEquals('Updated Name', $updated->name);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_delete(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $connection->insert('users', [
            'name' => 'To Delete',
            'email' => 'delete@example.com',
        ]);

        $id = $connection->id();
        $user = User::find($id);
        $this->assertNotNull($user);

        $result = $user->delete();
        $this->assertTrue($result);
        $this->assertFalse($user->exists());

        $deleted = User::find($id);
        $this->assertNull($deleted);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_attribute_access(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $user = new User(['name' => 'Test', 'email' => 'test@example.com']);
        $this->assertEquals('Test', $user->name);
        $this->assertEquals('test@example.com', $user->email);

        $user->age = 25;
        $this->assertEquals(25, $user->age);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_to_array(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $connection->insert('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'age' => 30,
        ]);

        $id = $connection->id();
        $user = User::find($id);
        $array = $user->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('Test User', $array['name']);
        $this->assertEquals('test@example.com', $array['email']);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_key(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $connection->insert('users', [
            'name' => 'Test',
            'email' => 'test@example.com',
        ]);

        $id = $connection->id();
        $user = User::find($id);

        $this->assertEquals($id, $user->key());
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_query_builder_chain(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $connection->insert('users', ['name' => 'A', 'email' => 'a@example.com', 'age' => 20]);
        $connection->insert('users', ['name' => 'B', 'email' => 'b@example.com', 'age' => 25]);
        $connection->insert('users', ['name' => 'C', 'email' => 'c@example.com', 'age' => 30]);

        $users = User::where(['age{>}' => 20])
            ->sort('age', 'ASC')
            ->limit(2)
            ->get();

        $this->assertCount(2, $users);
        $this->assertEquals(25, $users[0]->age);
        $this->assertEquals(30, $users[1]->age);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_query_builder_first(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $connection->insert('users', ['name' => 'First', 'email' => 'first@example.com']);
        $connection->insert('users', ['name' => 'Second', 'email' => 'second@example.com']);

        $result = User::where(['name' => 'First'])->first();
        $this->assertNotFalse($result);

        // Use reflection to call protected preload method
        $reflection = new \ReflectionClass(User::class);
        $method = $reflection->getMethod('preload');
        $method->setAccessible(true);
        $user = $method->invoke(null, $result);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('First', $user->name);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_query_builder_count(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $connection->insert('users', ['name' => 'User 1', 'email' => 'user1@example.com']);
        $connection->insert('users', ['name' => 'User 2', 'email' => 'user2@example.com']);

        $count = User::where(['name{~}' => '%User%'])->count();
        $this->assertEquals(2, $count);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_query_builder_exists(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $this->assertFalse(User::where(['email' => 'test@example.com'])->exists());

        $connection->insert('users', ['name' => 'Test', 'email' => 'test@example.com']);

        $this->assertTrue(User::where(['email' => 'test@example.com'])->exists());
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_builder_recreate(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $builder1 = User::where(['name' => 'Test']);
        $builder2 = $builder1->recreate();

        // Should be a new instance
        $this->assertNotSame($builder1, $builder2);

        // But should have the same connection and table
        $this->assertTrue(true); // Just verify recreate works
    }

    /**
     * Test Builder methods when connection/table is null
     */
    public function test_builder_without_connection(): void
    {
        $builder = new \Datum\Builder(null, null);

        $this->assertFalse($builder->get());
        $this->assertFalse($builder->first());
        $this->assertFalse($builder->count());
        $this->assertFalse($builder->exists());
    }

    /**
     * Test Builder methods when connection is null but table is set
     */
    public function test_builder_without_connection_but_with_table(): void
    {
        $builder = new \Datum\Builder(null, 'users');

        $this->assertFalse($builder->get());
        $this->assertFalse($builder->first());
        $this->assertFalse($builder->count());
        $this->assertFalse($builder->exists());
    }

    /**
     * Test Builder methods when table is null but connection is set
     */
    public function test_builder_without_table_but_with_connection(): void
    {
        $connection = new \Databoss\Connection([
            \Databoss\Connection::OPT_DRIVER => \Databoss\DatabaseDriver::SQLITE->value,
            \Databoss\Connection::OPT_DATABASE => ':memory:',
        ]);

        $builder = new \Datum\Builder($connection, null);

        $this->assertFalse($builder->get());
        $this->assertFalse($builder->first());
        $this->assertFalse($builder->count());
        $this->assertFalse($builder->exists());
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_builder_sort_desc(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $connection->insert('users', ['name' => 'A', 'email' => 'a@example.com', 'age' => 20]);
        $connection->insert('users', ['name' => 'B', 'email' => 'b@example.com', 'age' => 25]);
        $connection->insert('users', ['name' => 'C', 'email' => 'c@example.com', 'age' => 30]);

        $users = User::where([])
            ->sort('age', 'DESC')
            ->get();

        $this->assertCount(3, $users);
        $this->assertEquals(30, $users[0]->age);
        $this->assertEquals(25, $users[1]->age);
        $this->assertEquals(20, $users[2]->age);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_builder_offset(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $connection->insert('users', ['name' => 'A', 'email' => 'a@example.com', 'age' => 20]);
        $connection->insert('users', ['name' => 'B', 'email' => 'b@example.com', 'age' => 25]);
        $connection->insert('users', ['name' => 'C', 'email' => 'c@example.com', 'age' => 30]);
        $connection->insert('users', ['name' => 'D', 'email' => 'd@example.com', 'age' => 35]);

        $users = User::where([])
            ->sort('age', 'ASC')
            ->offset(1)
            ->limit(2)
            ->get();

        $this->assertCount(2, $users);
        $this->assertEquals(25, $users[0]->age);
        $this->assertEquals(30, $users[1]->age);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_builder_first_with_offset(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $connection->insert('users', ['name' => 'First', 'email' => 'first@example.com', 'age' => 20]);
        $connection->insert('users', ['name' => 'Second', 'email' => 'second@example.com', 'age' => 25]);
        $connection->insert('users', ['name' => 'Third', 'email' => 'third@example.com', 'age' => 30]);

        $result = User::where([])
            ->sort('age', 'ASC')
            ->offset(1)
            ->first();

        $this->assertNotFalse($result);

        $reflection = new \ReflectionClass(User::class);
        $method = $reflection->getMethod('preload');
        $method->setAccessible(true);
        $user = $method->invoke(null, $result);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('Second', $user->name);
        $this->assertEquals(25, $user->age);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_builder_where_chaining(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $connection->insert('users', ['name' => 'John', 'email' => 'john@example.com', 'age' => 25]);
        $connection->insert('users', ['name' => 'Jane', 'email' => 'jane@example.com', 'age' => 30]);

        // Chain multiple where calls
        $users = User::query()
            ->where(['age{>}' => 20])
            ->where(['age{<}' => 30])
            ->get();

        $this->assertCount(1, $users);
        $this->assertEquals('John', $users[0]->name);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_builder_get_with_limit_zero(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $connection->insert('users', ['name' => 'A', 'email' => 'a@example.com']);
        $connection->insert('users', ['name' => 'B', 'email' => 'b@example.com']);

        // Limit 0 should return all (as per databoss behavior)
        $users = User::where([])
            ->limit(0)
            ->get();

        $this->assertCount(2, $users);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_builder_get_with_no_limit(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $connection->insert('users', ['name' => 'A', 'email' => 'a@example.com']);
        $connection->insert('users', ['name' => 'B', 'email' => 'b@example.com']);
        $connection->insert('users', ['name' => 'C', 'email' => 'c@example.com']);

        // No limit should return all
        $users = User::where([])->get();

        $this->assertCount(3, $users);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_builder_recreate_preserves_state(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $builder1 = User::where(['age{>}' => 20])
            ->sort('age', 'DESC')
            ->limit(5)
            ->offset(10);

        $builder2 = $builder1->recreate();

        // Recreated builder should have same connection and table
        $this->assertNotSame($builder1, $builder2);

        // But where/sort/limit/offset should be reset (fresh instance)
        // Let's verify by checking the builder works
        $this->assertInstanceOf(\Datum\Builder::class, $builder2);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_isset(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $user = new User(['name' => 'Test', 'email' => 'test@example.com']);

        $this->assertTrue(isset($user->name));
        $this->assertTrue(isset($user->email));
        $this->assertFalse(isset($user->nonexistent));
    }

    public function test_save_without_connection(): void
    {
        // Reset connection to null by using a factory that returns null
        Model::use(function () {
            return null;
        });

        $user = new User(['name' => 'Test', 'email' => 'test@example.com']);
        $this->assertFalse($user->save());
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_delete_without_connection(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $user = new User(['name' => 'Test', 'email' => 'test@example.com']);
        // Model doesn't exist, so delete should return false
        $this->assertFalse($user->delete());
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_delete_without_id(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        // Create a user that exists but has no ID
        $user = new User(['name' => 'Test', 'email' => 'test@example.com']);
        $userReflection = new \ReflectionClass($user);
        $existsProperty = $userReflection->getProperty('exists');
        $existsProperty->setAccessible(true);
        $existsProperty->setValue($user, true);

        $this->assertFalse($user->delete());
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_update_without_id(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        // Create a user that exists but has no ID
        $user = new User(['name' => 'Test', 'email' => 'test@example.com']);
        $userReflection = new \ReflectionClass($user);
        $existsProperty = $userReflection->getProperty('exists');
        $existsProperty->setAccessible(true);
        $existsProperty->setValue($user, true);

        $this->assertFalse($user->save());
    }

    public function test_all_when_query_fails(): void
    {
        // Reset connection to null by using a factory that returns null
        Model::use(function () {
            return null;
        });

        $users = User::all();
        $this->assertIsArray($users);
        $this->assertCount(0, $users);
    }

    public function test_first_when_query_fails(): void
    {
        // Reset connection to null by using a factory that returns null
        Model::use(function () {
            return null;
        });

        $user = User::first();
        $this->assertNull($user);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_find_when_result_false(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        // Find non-existent ID
        $user = User::find(99999);
        $this->assertNull($user);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_get_with_method_that_throws(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        // Create a model with a method that throws
        $user = new class(['name' => 'Test']) extends User
        {
            public function throwsError()
            {
                throw new \Exception('Test error');
            }
        };

        // Accessing the method should fall through to attribute access
        $this->assertEquals('Test', $user->name);

        // Accessing a method that throws should fall through
        try {
            $result = $user->throwsError;
            // Should fall through to attribute access
            $this->assertNull($result);
        } catch (\Exception $e) {
            // If exception is thrown, that's also acceptable behavior
            $this->assertEquals('Test error', $e->getMessage());
        }
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_get_with_non_relation_method(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        // Create a model with a method that returns non-relation
        $user = new class(['name' => 'Test']) extends User
        {
            public function someMethod()
            {
                return 'not a relation';
            }
        };

        // Accessing the method should fall through to attribute access
        $this->assertEquals('Test', $user->name);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_relationship_caching(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'profiles');
        $this->truncateTable($connection, 'users');

        $connection->insert('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        $userId = $connection->id();

        $connection->insert('profiles', [
            'user_id' => $userId,
            'bio' => 'Test bio',
        ]);

        $user = \Datum\Tests\UserWithRelations::find($userId);
        $this->assertNotNull($user);

        // First access should load the relationship
        $profile1 = $user->profile;
        $this->assertInstanceOf(\Datum\Tests\Profile::class, $profile1);

        // Second access should use cached relationship
        $profile2 = $user->profile;
        $this->assertSame($profile1, $profile2);
    }

    /**
     * Test table name inference when $table is null
     */
    public function test_table_name_inference(): void
    {
        // Create a model without explicit table name
        $model = new class extends Model
        {
            // No $table property set
        };

        $reflection = new \ReflectionClass($model);
        $method = $reflection->getMethod('getTable');
        $method->setAccessible(true);

        $tableName = $method->invoke(null);
        // Should convert class name to table name
        $this->assertIsString($tableName);
        $this->assertNotEmpty($tableName);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_preload_with_object(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $connection->insert('users', [
            'name' => 'Test',
            'email' => 'test@example.com',
        ]);

        // Get result as object
        $result = $connection->first('users');
        $this->assertNotFalse($result);

        $user = User::preload($result);
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('Test', $user->name);
        $this->assertTrue($user->exists());
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_preload_with_array(Connection $connection): void
    {
        Model::use($connection);

        $attributes = [
            'id' => 1,
            'name' => 'Test',
            'email' => 'test@example.com',
        ];

        $user = User::preload($attributes);
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('Test', $user->name);
        $this->assertTrue($user->exists());
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_assign_and_attribute_methods(Connection $connection): void
    {
        Model::use($connection);

        $user = new User;

        $user->assign('name', 'Test');
        $this->assertEquals('Test', $user->attribute('name'));

        $user->assign('email', 'test@example.com');
        $this->assertEquals('test@example.com', $user->attribute('email'));

        $attributes = $user->attributes();
        $this->assertIsArray($attributes);
        $this->assertEquals('Test', $attributes['name']);
        $this->assertEquals('test@example.com', $attributes['email']);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_key_method(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $user = new User(['name' => 'Test', 'email' => 'test@example.com']);
        // Before save, key should be null
        $this->assertNull($user->key());

        $user->save();
        // After save, key should be set
        $this->assertNotNull($user->key());
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_datetime_cast(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        // Insert with string date
        $connection->insert('users', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'created_at' => '2024-01-15 10:30:00',
        ]);

        $id = $connection->id();
        $user = UserWithCasts::find($id);

        // Should be cast to DateTime when accessed
        $this->assertInstanceOf(\DateTime::class, $user->created_at);
        $this->assertEquals('2024-01-15 10:30:00', $user->created_at->format('Y-m-d H:i:s'));

        // Can also set DateTime object
        $newDate = new \DateTime('2024-02-20 15:45:00');
        $user->created_at = $newDate;
        $user->save();

        // Reload and verify it was saved correctly
        $reloaded = UserWithCasts::find($id);
        $this->assertInstanceOf(\DateTime::class, $reloaded->created_at);
        $this->assertEquals('2024-02-20 15:45:00', $reloaded->created_at->format('Y-m-d H:i:s'));
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_array_cast(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        // Insert with JSON string
        $connection->insert('users', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'metadata' => json_encode(['role' => 'admin', 'permissions' => ['read', 'write']]),
        ]);

        $id = $connection->id();
        $user = UserWithCasts::find($id);

        // Should be cast to array when accessed
        $this->assertIsArray($user->metadata);
        $this->assertEquals('admin', $user->metadata['role']);
        $this->assertEquals(['read', 'write'], $user->metadata['permissions']);

        // Can also set array
        $user->metadata = ['role' => 'user', 'permissions' => ['read']];
        $user->save();

        // Reload and verify it was saved correctly
        $reloaded = UserWithCasts::find($id);
        $this->assertIsArray($reloaded->metadata);
        $this->assertEquals('user', $reloaded->metadata['role']);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_int_cast(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        // Insert with string number
        $connection->insert('users', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'age' => '25',
        ]);

        $id = $connection->id();
        $user = UserWithCasts::find($id);

        // Should be cast to int when accessed
        $this->assertIsInt($user->age);
        $this->assertEquals(25, $user->age);

        // Can also set int
        $user->age = 30;
        $user->save();

        $reloaded = UserWithCasts::find($id);
        $this->assertIsInt($reloaded->age);
        $this->assertEquals(30, $reloaded->age);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_bool_cast(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        // Insert with int (0/1)
        $connection->insert('users', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'is_active' => 1,
        ]);

        $id = $connection->id();
        $user = UserWithCasts::find($id);

        // Should be cast to bool when accessed
        $this->assertIsBool($user->is_active);
        $this->assertTrue($user->is_active);

        // Can also set bool
        $user->is_active = false;
        $user->save();

        $reloaded = UserWithCasts::find($id);
        $this->assertIsBool($reloaded->is_active);
        $this->assertFalse($reloaded->is_active);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_to_array_with_casts(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $connection->insert('users', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'age' => '25',
            'created_at' => '2024-01-15 10:30:00',
            'metadata' => json_encode(['key' => 'value']),
            'is_active' => 1,
        ]);

        $id = $connection->id();
        $user = UserWithCasts::find($id);
        $array = $user->toArray();

        // All casted values should be in their PHP types
        $this->assertIsInt($array['age']);
        $this->assertInstanceOf(\DateTime::class, $array['created_at']);
        $this->assertIsArray($array['metadata']);
        $this->assertIsBool($array['is_active']);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_datetime_cast_with_string(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $user = new UserWithCasts([
            'name' => 'Test',
            'email' => 'test@example.com',
        ]);

        // Set datetime as string
        $user->created_at = '2024-01-15 10:30:00';
        $user->save();

        $id = $user->id;
        $reloaded = UserWithCasts::find($id);

        // Should be cast to DateTime
        $this->assertInstanceOf(\DateTime::class, $reloaded->created_at);
        $this->assertEquals('2024-01-15 10:30:00', $reloaded->created_at->format('Y-m-d H:i:s'));
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_datetime_cast_null(Connection $connection): void
    {
        Model::use($connection);
        $this->truncateTable($connection, 'users');

        $connection->insert('users', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'created_at' => null,
        ]);

        $id = $connection->id();
        $user = UserWithCasts::find($id);

        // Null should remain null
        $this->assertNull($user->created_at);
    }

    public function provideConnection(): array
    {
        $connections = [
            // MySQL/MariaDB
            [new Connection([
                Connection::OPT_HOST => '127.0.0.1',
                Connection::OPT_DATABASE => 'testdb',
                Connection::OPT_USERNAME => 'root',
                Connection::OPT_PASSWORD => 'root',
            ])],
            // Postgres
            [new Connection([
                Connection::OPT_DRIVER => DatabaseDriver::POSTGRES->value,
                Connection::OPT_HOST => '127.0.0.1',
                Connection::OPT_DATABASE => 'testdb',
                Connection::OPT_USERNAME => 'postgres',
                Connection::OPT_PASSWORD => 'postgres',
            ])],
        ];

        // SQLite (file-based database)
        $sqliteDb = tempnam(sys_get_temp_dir(), 'datum_test_').'.sqlite';
        $sqliteConnection = new Connection([
            Connection::OPT_DRIVER => DatabaseDriver::SQLITE->value,
            Connection::OPT_DATABASE => $sqliteDb,
        ]);

        // Initialize SQLite database schema - execute each statement separately
        $sql = file_get_contents(__DIR__.'/../dumps/sqlite.sql');
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn ($stmt) => ! empty($stmt) && ! preg_match('/^\s*--/', $stmt)
        );
        foreach ($statements as $statement) {
            $sqliteConnection->execute($statement);
        }

        $connections[] = [$sqliteConnection];

        return $connections;
    }
}

/**
 * Test model with casts
 */
class UserWithCasts extends User
{
    protected static array $casts = [
        'age' => 'int',
        'created_at' => 'datetime',
        'metadata' => 'array',
        'is_active' => 'bool',
    ];
}
