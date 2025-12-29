IF NOT EXISTS (SELECT * FROM sys.databases WHERE name = 'testdb')
BEGIN
    CREATE DATABASE testdb;
END
GO

USE testdb;
GO

IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'users' AND schema_id = SCHEMA_ID('dbo'))
BEGIN
    CREATE TABLE users (
        id BIGINT IDENTITY(1,1) NOT NULL,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        age SMALLINT NULL,
        created_at DATETIME2 NULL,
        updated_at DATETIME2 NULL,
        metadata NVARCHAR(MAX) NULL,
        is_active TINYINT NULL,
        PRIMARY KEY (id)
    );
END
GO

IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'profiles' AND schema_id = SCHEMA_ID('dbo'))
BEGIN
    CREATE TABLE profiles (
        id BIGINT IDENTITY(1,1) NOT NULL,
        user_id BIGINT NOT NULL,
        bio NVARCHAR(MAX) NULL,
        avatar VARCHAR(255) NULL,
        PRIMARY KEY (id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );
END
GO

IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'posts' AND schema_id = SCHEMA_ID('dbo'))
BEGIN
    CREATE TABLE posts (
        id BIGINT IDENTITY(1,1) NOT NULL,
        user_id BIGINT NOT NULL,
        title VARCHAR(255) NOT NULL,
        content NVARCHAR(MAX) NULL,
        created_at DATETIME2 NULL,
        updated_at DATETIME2 NULL,
        PRIMARY KEY (id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );
END
GO

IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'roles' AND schema_id = SCHEMA_ID('dbo'))
BEGIN
    CREATE TABLE roles (
        id BIGINT IDENTITY(1,1) NOT NULL,
        name VARCHAR(255) NOT NULL,
        PRIMARY KEY (id)
    );
END
GO

IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'user_roles' AND schema_id = SCHEMA_ID('dbo'))
BEGIN
    CREATE TABLE user_roles (
        user_id BIGINT NOT NULL,
        role_id BIGINT NOT NULL,
        PRIMARY KEY (user_id, role_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
    );
END
GO

IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'uuid_items' AND schema_id = SCHEMA_ID('dbo'))
BEGIN
    CREATE TABLE uuid_items (
        uuid CHAR(36) NOT NULL,
        name VARCHAR(255) NOT NULL,
        created_at DATETIME2 NULL,
        updated_at DATETIME2 NULL,
        PRIMARY KEY (uuid)
    );
END
GO

