<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The `support` role is referenced by the Admin middleware, SupportThreadPolicy,
 * broadcast channels and the Admin support inbox, but was never seeded, so
 * User::role(['admin', 'support']) threw "There is no role named `support` for
 * guard `api`". Every role in this app uses the `api` guard (User::$guard_name),
 * so the missing row is created on that guard. Idempotent; touches no user data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Role::findOrCreate('support', 'api');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally left in place: removing it would detach agents from the inbox.
    }
};
