<?php

namespace FossHaas\LaravelPermissionObjects\Traits;

use FossHaas\LaravelPermissionObjects\Permission;

/**
 * Provides methods to access permissions associated with a class.
 */
trait HasPermissions
{
    /**
     * Get all permissions associated with this class.
     *
     * @return array An array of Permission objects associated with this class
     */
    public static function getPermissions(): array
    {
        return Permission::for(static::class);
    }

    /**
     * Get a specific permission for this class by name.
     *
     * @param string $name The name of the permission to retrieve, relative to this class
     * @return Permission|null The Permission object if found, or null if not found
     */
    public static function getPermission(string $name): ?Permission
    {
        return Permission::resolve($name, static::class);
    }
}
