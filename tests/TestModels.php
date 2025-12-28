<?php

declare(strict_types=1);

namespace Datum\Tests;

use Datum\Model;

/**
 * Base test models
 */
class User extends Model
{
    protected static ?string $table = 'users';
}

class Profile extends Model
{
    protected static ?string $table = 'profiles';
}

class Post extends Model
{
    protected static ?string $table = 'posts';
}

class Role extends Model
{
    protected static ?string $table = 'roles';
}

/**
 * Test models with relationships
 */
class UserWithRelations extends User
{
    public function profile()
    {
        return $this->one(Profile::class, 'user_id');
    }

    public function posts()
    {
        return $this->many(Post::class, 'user_id');
    }

    public function roles()
    {
        return $this->owners(
            Role::class,
            'user_roles',
            'user_id',
            'role_id'
        );
    }
}

class ProfileWithRelations extends Profile
{
    public function user()
    {
        return $this->owner(UserWithRelations::class, 'user_id');
    }
}

class PostWithRelations extends Post
{
    public function user()
    {
        return $this->owner(UserWithRelations::class, 'user_id');
    }
}
