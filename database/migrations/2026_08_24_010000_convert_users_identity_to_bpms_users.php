<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Switches local identity to mirror bpms.users (external BPMS schema, never modified here):
 * users.id becomes a string matching bpms.users.id, email is dropped in favour of username.
 * Dependent FK columns (tokens, RBAC pivot, refresh tokens, sessions) are migrated to match.
 *
 * This is a destructive dev-only migration: all existing local auth/session data is discarded
 * (founder-approved, see plan/login-bpms-users-migration-2026-08-24.md) because there is no
 * meaningful way to preserve a bigint-keyed row once the identity source changes underneath it.
 *
 * Postgres (real dev DB) uses raw ALTER statements (no data to preserve, but a live table).
 * SQLite (test suite, phpunit.xml) cannot ALTER a column's type/PK affinity without
 * doctrine/dbal (not installed) — it drops and recreates the affected tables instead, which
 * is safe there because RefreshDatabase always runs this migration against an empty DB.
 */
return new class extends Migration
{
    private const ID_LENGTH = 64;

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->upSqlite();

            return;
        }

        $this->upPgsql();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->downSqlite();

            return;
        }

        $this->downPgsql();
    }

    private function upPgsql(): void
    {
        DB::statement('TRUNCATE TABLE personal_access_tokens, refresh_tokens, model_has_roles, model_has_permissions, sessions, users RESTART IDENTITY CASCADE');

        DB::statement('ALTER TABLE refresh_tokens DROP CONSTRAINT refresh_tokens_user_id_foreign');

        DB::statement('ALTER TABLE users ALTER COLUMN id DROP DEFAULT');
        DB::statement('ALTER TABLE users ALTER COLUMN id TYPE VARCHAR('.self::ID_LENGTH.') USING id::VARCHAR('.self::ID_LENGTH.')');
        DB::statement('DROP SEQUENCE IF EXISTS users_id_seq');

        DB::statement('ALTER TABLE personal_access_tokens ALTER COLUMN tokenable_id TYPE VARCHAR('.self::ID_LENGTH.') USING tokenable_id::VARCHAR('.self::ID_LENGTH.')');
        DB::statement('ALTER TABLE model_has_roles ALTER COLUMN model_id TYPE VARCHAR('.self::ID_LENGTH.') USING model_id::VARCHAR('.self::ID_LENGTH.')');
        DB::statement('ALTER TABLE model_has_permissions ALTER COLUMN model_id TYPE VARCHAR('.self::ID_LENGTH.') USING model_id::VARCHAR('.self::ID_LENGTH.')');
        DB::statement('ALTER TABLE refresh_tokens ALTER COLUMN user_id TYPE VARCHAR('.self::ID_LENGTH.') USING user_id::VARCHAR('.self::ID_LENGTH.')');
        DB::statement('ALTER TABLE sessions ALTER COLUMN user_id TYPE VARCHAR('.self::ID_LENGTH.') USING user_id::VARCHAR('.self::ID_LENGTH.')');

        DB::statement('ALTER TABLE refresh_tokens ADD CONSTRAINT refresh_tokens_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->dropColumn('email');
            $table->string('username', 50)->unique()->after('id');
        });

        // Credentials are now verified by bpms.validate_login_apps, never locally — this
        // column is vestigial (kept only so nothing else in the schema assumes it away).
        DB::statement('ALTER TABLE users ALTER COLUMN password DROP NOT NULL');
    }

    private function downPgsql(): void
    {
        // Structural rollback only (dev use) — discards whatever bpms-sourced string ids exist,
        // since they generally cannot cast back to bigint. See plan's rollback_notes.
        DB::statement('TRUNCATE TABLE personal_access_tokens, refresh_tokens, model_has_roles, model_has_permissions, sessions, users RESTART IDENTITY CASCADE');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_username_unique');
            $table->dropColumn('username');
            $table->string('email')->unique()->after('name');
        });
        DB::statement("UPDATE users SET password = '' WHERE password IS NULL");
        DB::statement('ALTER TABLE users ALTER COLUMN password SET NOT NULL');

        DB::statement('ALTER TABLE refresh_tokens DROP CONSTRAINT refresh_tokens_user_id_foreign');

        DB::statement('CREATE SEQUENCE IF NOT EXISTS users_id_seq');
        DB::statement('ALTER TABLE users ALTER COLUMN id TYPE BIGINT USING NULL');
        DB::statement("ALTER TABLE users ALTER COLUMN id SET DEFAULT nextval('users_id_seq')");
        DB::statement('ALTER SEQUENCE users_id_seq OWNED BY users.id');

        DB::statement('ALTER TABLE personal_access_tokens ALTER COLUMN tokenable_id TYPE BIGINT USING NULL');
        DB::statement('ALTER TABLE model_has_roles ALTER COLUMN model_id TYPE BIGINT USING NULL');
        DB::statement('ALTER TABLE model_has_permissions ALTER COLUMN model_id TYPE BIGINT USING NULL');
        DB::statement('ALTER TABLE refresh_tokens ALTER COLUMN user_id TYPE BIGINT USING NULL');
        DB::statement('ALTER TABLE sessions ALTER COLUMN user_id TYPE BIGINT USING NULL');

        DB::statement('ALTER TABLE refresh_tokens ADD CONSTRAINT refresh_tokens_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
    }

    private function upSqlite(): void
    {
        Schema::drop('personal_access_tokens');
        Schema::drop('refresh_tokens');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::drop('sessions');
        Schema::drop('users');

        Schema::create('users', function (Blueprint $table) {
            $table->string('id', self::ID_LENGTH)->primary();
            $table->string('username', 50)->unique();
            $table->string('name');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id', self::ID_LENGTH)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type');
            $table->string('tokenable_id', self::ID_LENGTH);
            $table->index(['tokenable_type', 'tokenable_id']);
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';
        $modelMorphKey = $columnNames['model_morph_key'];

        Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $modelMorphKey, $pivotPermission) {
            $table->unsignedBigInteger($pivotPermission);
            $table->string('model_type');
            $table->string($modelMorphKey, self::ID_LENGTH);
            $table->index([$modelMorphKey, 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign($pivotPermission)->references('id')->on($tableNames['permissions'])->cascadeOnDelete();
            $table->primary([$pivotPermission, $modelMorphKey, 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $modelMorphKey, $pivotRole) {
            $table->unsignedBigInteger($pivotRole);
            $table->string('model_type');
            $table->string($modelMorphKey, self::ID_LENGTH);
            $table->index([$modelMorphKey, 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign($pivotRole)->references('id')->on($tableNames['roles'])->cascadeOnDelete();
            $table->primary([$pivotRole, $modelMorphKey, 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', self::ID_LENGTH);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    private function downSqlite(): void
    {
        Schema::drop('personal_access_tokens');
        Schema::drop('refresh_tokens');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::drop('sessions');
        Schema::drop('users');

        // Recreates the pre-migration (bigint-keyed, email-based) shape for local rollback.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->boolean('is_active')->default(false);
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';

        Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $columnNames, $pivotPermission) {
            $table->unsignedBigInteger($pivotPermission);
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign($pivotPermission)->references('id')->on($tableNames['permissions'])->cascadeOnDelete();
            $table->primary([$pivotPermission, $columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $columnNames, $pivotRole) {
            $table->unsignedBigInteger($pivotRole);
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign($pivotRole)->references('id')->on($tableNames['roles'])->cascadeOnDelete();
            $table->primary([$pivotRole, $columnNames['model_morph_key'], 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }
};
