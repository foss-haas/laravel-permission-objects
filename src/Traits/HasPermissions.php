<?php

namespace FossHaas\LaravelPermissionObjects\Traits;

use FossHaas\LaravelPermissionObjects\Permission;

trait HasPermissions
{
    public static function getPermissions(): array
    {
        return Permission::for(static::class);
    }

    public static function getPermission(string $name): ?Permission
    {
        return Permission::resolve($name, static::class);
    }
}
