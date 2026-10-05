<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'brands.manage', 'content.generate', 'content.publish', 'designs.manage',
            'goals.manage', 'schedule.manage', 'social.connect',
            'admin.users', 'admin.plans', 'admin.settings', 'admin.stats', 'admin.design_requests',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'api');
        }

        $user = Role::findOrCreate('user', 'api');
        $user->syncPermissions([
            'brands.manage', 'content.generate', 'content.publish', 'designs.manage',
            'goals.manage', 'schedule.manage', 'social.connect',
        ]);

        $designer = Role::findOrCreate('designer', 'api');
        $designer->syncPermissions(['designs.manage', 'admin.design_requests']);

        // Support agents: referenced by the Admin inbox, policies, channels and the
        // `admin` middleware. Same `api` guard as every other role (User::$guard_name).
        Role::findOrCreate('support', 'api');

        // Admin passes every gate via Gate::before, so it holds all permissions too.
        Role::findOrCreate('admin', 'api')->syncPermissions(Permission::all());
    }
}
