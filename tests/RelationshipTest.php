<?php

declare(strict_types=1);

namespace Datum\Tests;

require_once __DIR__.'/TestModels.php';

use Databoss\Connection;
use Databoss\DatabaseDriver;
use Datum\Model;
use PHPUnit\Framework\TestCase;

/**
 * Class RelationshipTest
 *
 * Test suite for model relationships.
 */
class RelationshipTest extends TestCase
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
    public function test_one_relationship(Connection $connection): void
    {
        Model::connect($connection);
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
            'avatar' => 'avatar.jpg',
        ]);

        $user = UserWithRelations::find($userId);
        $this->assertNotNull($user);

        $profile = $user->profile;
        $this->assertInstanceOf(\Datum\Tests\Profile::class, $profile);
        $this->assertEquals('Test bio', $profile->bio);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_many_relationship(Connection $connection): void
    {
        Model::connect($connection);
        $this->truncateTable($connection, 'posts');
        $this->truncateTable($connection, 'users');

        $connection->insert('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        $userId = $connection->id();

        $connection->insert('posts', [
            'user_id' => $userId,
            'title' => 'Post 1',
            'content' => 'Content 1',
        ]);
        $connection->insert('posts', [
            'user_id' => $userId,
            'title' => 'Post 2',
            'content' => 'Content 2',
        ]);

        $user = UserWithRelations::find($userId);
        $this->assertNotNull($user);

        $posts = $user->posts;
        $this->assertIsArray($posts);
        $this->assertCount(2, $posts);
        $this->assertInstanceOf(Post::class, $posts[0]);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_owner_relationship(Connection $connection): void
    {
        Model::connect($connection);
        $this->truncateTable($connection, 'profiles');
        $this->truncateTable($connection, 'users');

        $connection->insert('users', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);
        $userId = $connection->id();

        $connection->insert('profiles', [
            'user_id' => $userId,
            'bio' => 'Profile bio',
        ]);
        $profileId = $connection->id();

        $profile = ProfileWithRelations::find($profileId);
        $this->assertNotNull($profile);

        $user = $profile->user;
        $this->assertInstanceOf(UserWithRelations::class, $user);
        $this->assertEquals('Jane Doe', $user->name);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_owners_relationship(Connection $connection): void
    {
        Model::connect($connection);
        $this->truncateTable($connection, 'user_roles');
        $this->truncateTable($connection, 'roles');
        $this->truncateTable($connection, 'users');

        $connection->insert('users', [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);
        $userId = $connection->id();

        $connection->insert('roles', ['name' => 'admin']);
        $adminRoleId = $connection->id();
        $connection->insert('roles', ['name' => 'editor']);
        $editorRoleId = $connection->id();

        $connection->insert('user_roles', ['user_id' => $userId, 'role_id' => $adminRoleId]);
        $connection->insert('user_roles', ['user_id' => $userId, 'role_id' => $editorRoleId]);

        $user = UserWithRelations::find($userId);
        $this->assertNotNull($user);

        $roles = $user->roles;
        $this->assertIsArray($roles);
        $this->assertCount(2, $roles);
        $this->assertInstanceOf(Role::class, $roles[0]);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_owners_relationship_empty(Connection $connection): void
    {
        Model::connect($connection);
        $this->truncateTable($connection, 'user_roles');
        $this->truncateTable($connection, 'roles');
        $this->truncateTable($connection, 'users');

        $connection->insert('users', [
            'name' => 'User Without Roles',
            'email' => 'user@example.com',
        ]);
        $userId = $connection->id();

        $user = UserWithRelations::find($userId);
        $this->assertNotNull($user);

        // User has no roles, so should return empty array
        $roles = $user->roles;
        $this->assertIsArray($roles);
        $this->assertCount(0, $roles);
    }

    /**
     * @dataProvider provideConnection
     */
    public function test_relation_invoke(Connection $connection): void
    {
        Model::connect($connection);
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

        $user = UserWithRelations::find($userId);
        $this->assertNotNull($user);

        // Test __invoke() method by calling the relation as a function
        $profileRelation = $user->profile();
        $profile = $profileRelation();
        $this->assertInstanceOf(\Datum\Tests\Profile::class, $profile);
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
