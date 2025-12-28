CREATE TABLE IF NOT EXISTS "users" (
    "id" BIGSERIAL PRIMARY KEY,
    "name" VARCHAR(255) NOT NULL,
    "email" VARCHAR(255) NOT NULL,
    "age" SMALLINT NULL,
    "created_at" TIMESTAMP NULL,
    "updated_at" TIMESTAMP NULL,
    "metadata" TEXT NULL,
    "is_active" SMALLINT NULL
);

CREATE TABLE IF NOT EXISTS "profiles" (
    "id" BIGSERIAL PRIMARY KEY,
    "user_id" BIGINT NOT NULL,
    "bio" TEXT NULL,
    "avatar" VARCHAR(255) NULL,
    FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS "posts" (
    "id" BIGSERIAL PRIMARY KEY,
    "user_id" BIGINT NOT NULL,
    "title" VARCHAR(255) NOT NULL,
    "content" TEXT NULL,
    "created_at" TIMESTAMP NULL,
    "updated_at" TIMESTAMP NULL,
    FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS "roles" (
    "id" BIGSERIAL PRIMARY KEY,
    "name" VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS "user_roles" (
    "user_id" BIGINT NOT NULL,
    "role_id" BIGINT NOT NULL,
    PRIMARY KEY ("user_id", "role_id"),
    FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE CASCADE,
    FOREIGN KEY ("role_id") REFERENCES "roles"("id") ON DELETE CASCADE
);

