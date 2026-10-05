<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    /** Authenticate as a fresh user holding a real JWT. */
    protected function actingAsUser(array $attributes = [], string $role = 'user'): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        $token = auth('api')->login($user);
        $this->withHeader('Authorization', 'Bearer '.$token);

        return $user;
    }
}
