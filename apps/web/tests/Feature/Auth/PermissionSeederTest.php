<?php

declare(strict_types=1);

use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('seeds the admin-access + audit.view permissions on web guard', function (): void {
    $this->seed(PermissionSeeder::class);

    expect(Permission::where('name', 'admin-access')->where('guard_name', 'web')->exists())->toBeTrue();
    expect(Permission::where('name', 'audit.view')->where('guard_name', 'web')->exists())->toBeTrue();
});

it('seeds super-admin role with all permissions', function (): void {
    $this->seed(PermissionSeeder::class);

    $role = Role::where('name', 'super-admin')->where('guard_name', 'web')->firstOrFail();
    expect($role->permissions->pluck('name'))->toContain('admin-access', 'audit.view');
});

it('is idempotent', function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(PermissionSeeder::class);

    expect(Permission::where('name', 'admin-access')->count())->toBe(1);
    expect(Role::where('name', 'super-admin')->count())->toBe(1);
});
