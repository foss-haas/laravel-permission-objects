<?php

namespace FossHaas\LaravelPermissionObjects;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Manages permissions in Laravel applications.
 */
class AsPermissions implements Arrayable, Castable, Jsonable
{
    /**
     * @var array<string, true|list<string>>
     */
    private $permissions = [];

    /**
     * Create a new instance and load initial permissions.
     *
     * @param  ?list<array{'name':string,'object_id':string|null}>|null  $items List of permissions to load
     */
    public function __construct(?array $items = null)
    {
        if ($items) {
            $this->load($items);
        }
    }

    /**
     * Load permissions from an array of items.
     *
     * @param  list<array{'name':string,'object_id':string|null}>  $items List of permissions to load
     */
    public function load(array $items): self
    {
        foreach ($items as $item) {
            if (! isset($item['name'])) {
                continue;
            }
            if (! isset($item['object_id']) || $item['object_id'] === null) {
                $this->permissions[$item['name']] = true;
            } elseif (
                ! isset($this->permissions[$item['name']]) ||
                $this->permissions[$item['name']] !== true
            ) {
                $this->permissions[$item['name']][] = (string) $item['object_id'];
            }
        }

        return $this;
    }

    /**
     * Laravel Gate-compatible check for permissions.
     *
     * @param  string  $ability Name of the permission to check
     * @param  string|object|null  $object Object or object type (i.e. class name) to check the permission for or null for simple permissions
     */
    public function can(string $ability, mixed $object): ?bool
    {
        $objectType = null;
        $objectId = null;
        if (isset($object)) {
            if (is_string($object)) {
                $objectType = $object;
            } elseif (is_object($object)) {
                $objectType = get_class($object);
                if (method_exists($object, 'getKey')) {
                    $objectId = (string) $object->getKey();
                }
            } else {
                throw new \InvalidArgumentException('Object must be a string or an object, ' . gettype($object) . ' given.');
            }
        }
        $permission = Permission::resolve($ability, $objectType);
        if (! $permission) {
            return null;
        }

        return $this->has($permission, $objectId);
    }

    /**
     * Check if the given permission exists for the specified object ID.
     *
     * @param  Permission  $permission Permission to check for
     * @param  string|null  $objectId Object ID to check the permission for or null for simple permissions
     */
    public function has(Permission $permission, ?string $objectId): bool
    {
        $id = $permission->getKey();
        if (! isset($this->permissions[$id])) {
            return false;
        }
        if ($this->permissions[$id] === true) {
            return true;
        }
        if ($objectId === null) {
            return false;
        }

        return in_array((string) $objectId, $this->permissions[$id]);
    }

    /**
     * Grant a permission for the specified object ID.
     *
     * @param  Permission  $permission Permission to grant
     * @param  string|null  $objectId Object ID to grant the permission for or null for simple permissions
     */
    public function grant(Permission $permission, ?string $objectId): self
    {
        $id = $permission->getKey();
        if ($objectId === null) {
            $this->permissions[$id] = true;
        } else {
            $objectId = (string) $objectId;
            if (! isset($this->permissions[$id]) || (
                is_array($this->permissions[$id])
                && ! in_array($objectId, $this->permissions[$id])
            )) {
                $this->permissions[$id][] = $objectId;
            }
        }

        return $this;
    }

    /**
     * Revoke a permission for the specified object ID.
     *
     * @param  Permission  $permission Permission to revoke
     * @param  string|null  $objectId Object ID to revoke the permission for or null for simple permissions
     */
    public function revoke(Permission $permission, ?string $objectId): self
    {
        $id = $permission->getKey();
        if (isset($this->permissions[$id])) {
            if ($this->permissions[$id] === true) {
                if ($objectId === null) {
                    unset($this->permissions[$id]);
                }
            } elseif ($objectId !== null) {
                $index = array_search((string) $objectId, $this->permissions[$id]);
                if ($index !== false) {
                    if (count($this->permissions[$id]) === 1) {
                        unset($this->permissions[$id]);
                    } else {
                        array_splice($this->permissions[$id], $index, 1);
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Revoke all permissions or a specific permission.
     *
     * @param  Permission|null  $permission Permission to revoke or null for all permissions
     */
    public function revokeAll(?Permission $permission = null): self
    {
        if ($permission === null) {
            array_splice($this->permissions, 0, count($this->permissions));
        } else {
            $id = $permission->getKey();
            unset($this->permissions[$id]);
        }

        return $this;
    }

    public function __debugInfo(): array
    {
        return Arr::mapWithKeys(
            $this->permissions,
            fn($value, $id) => [$id => $value === true ? null : $value]
        );
    }

    /**
     * @return list<array{'name':string,'object_id':string|null}>
     */
    public function toArray(): array
    {
        return array_merge(...array_map(
            fn($value, $id) => is_array($value) ? array_map(
                fn($value) => ['name' => $id, 'object_id' => $value],
                $value
            ) : [['name' => $id, 'object_id' => null]],
            $this->permissions,
            array_keys($this->permissions)
        ));
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * @param  list<array{'name':string,'object_id':string|null}>  $items
     */
    public static function fromArray(array $items): self
    {
        return new self($items);
    }

    public static function fromJson(string $json): self
    {
        $items = json_decode($json, associative: true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON provided: ' . json_last_error_msg());
        }

        if (! is_array($items)) {
            throw new \InvalidArgumentException('JSON must decode to an array');
        }

        return new self($items);
    }

    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            public function get(Model $model, string $key, mixed $value, array $attributes): mixed
            {
                if (! $value) {
                    return new AsPermissions;
                }

                return AsPermissions::fromJson($value);
            }

            public function set(Model $model, string $key, mixed $value, array $attributes): mixed
            {
                if ($value === null) {
                    return null;
                }

                return $value->toJson();
            }
        };
    }
}
