<?php

namespace FossHaas\LaravelPermissionObjects;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class ScopedPermissions implements Arrayable, Castable, Jsonable
{
    public const DEFAULT_SCOPE = '';

    public const ALL_SCOPES = '*';

    /**
     * @var array<string, Permissions>
     */
    private $scoped = [];

    /**
     * @param  ?list<array{'name':string,'object_id':string|null,'scope':string|null}>|null  $items
     */
    public function __construct(?array $items = null)
    {
        if ($items) {
            $this->load($items);
        }
    }

    /**
     * @param  list<array{'name':string,'object_id':string|null,'scope':string|null}>  $items
     */
    public function load(array $items): self
    {
        $scoped = [];
        foreach ($items as $item) {
            $scope = isset($item['scope']) ? $item['scope'] : self::DEFAULT_SCOPE;
            $scoped[$scope][] = $item;
        }
        foreach ($scoped as $scope => $scopedItems) {
            if (! isset($this->scoped[$scope])) {
                $this->scoped[$scope] = new Permissions;
            }
            $this->scoped[$scope]->load($scopedItems);
        }

        return $this;
    }

    /**
     * @param  string|object|null  $object
     * @param  string|list<string>  $scopes
     */
    public function can(string $ability, mixed $object, string|array $scopes = self::DEFAULT_SCOPE): ?bool
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
                throw new \InvalidArgumentException('Object must be a string or an object, '.gettype($object).' given.');
            }
        }
        $permission = Permission::resolve($ability, $objectType);
        if (! $permission) {
            return null;
        }

        return $this->has($permission, $objectId, $scopes);
    }

    /**
     * @param  string|list<string>  $scopes
     */
    public function has(Permission $permission, ?string $objectId, string|array $scopes = self::DEFAULT_SCOPE): bool
    {
        if ($scopes === self::ALL_SCOPES) {
            $scopes = array_keys($this->scoped);
        } elseif (! is_array($scopes)) {
            $scopes = [$scopes];
        }
        if (! in_array(self::DEFAULT_SCOPE, $scopes)) {
            $scopes[] = self::DEFAULT_SCOPE;
        }
        foreach ($scopes as $scope) {
            if (isset($this->scoped[$scope]) && $this->scoped[$scope]->has($permission, $objectId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  string|list<string>  $scopes
     */
    public function grant(Permission $permission, ?string $objectId, string|array $scopes = self::DEFAULT_SCOPE): self
    {
        if ($scopes === self::ALL_SCOPES) {
            $scopes = array_keys($this->scoped);
        } elseif (! is_array($scopes)) {
            $scopes = [$scopes];
        }
        foreach ($scopes as $scope) {
            if (! isset($this->scoped[$scope])) {
                $this->scoped[$scope] = new Permissions;
            }
            $this->scoped[$scope]->grant($permission, $objectId);
        }

        return $this;
    }

    /**
     * @param  string|list<string>  $scopes
     */
    public function revoke(Permission $permission, ?string $objectId, string|array $scopes = self::DEFAULT_SCOPE): self
    {
        if ($scopes === self::ALL_SCOPES) {
            $scopes = array_keys($this->scoped);
        } elseif (! is_array($scopes)) {
            $scopes = [$scopes];
        }
        foreach ($scopes as $scope) {
            if (isset($this->scoped[$scope])) {
                $this->scoped[$scope]->revoke($permission, $objectId);
            }
        }

        return $this;
    }

    /**
     * @param  string|list<string>  $scopes
     */
    public function revokeAll(?Permission $permission = null, string|array $scopes = self::ALL_SCOPES): self
    {
        if ($scopes === self::ALL_SCOPES) {
            $scopes = array_keys($this->scoped);
        } elseif (! is_array($scopes)) {
            $scopes = [$scopes];
        }
        foreach ($scopes as $scope) {
            if (isset($this->scoped[$scope])) {
                if ($permission === null) {
                    unset($this->scoped[$scope]);
                } else {
                    $this->scoped[$scope]->revokeAll($permission);
                }
            }
        }

        return $this;
    }

    public function scope(string $scope): Permissions
    {
        if (! isset($this->scoped[$scope])) {
            $this->scoped[$scope] = new Permissions;
        }

        return $this->scoped[$scope];
    }

    public function __debugInfo(): array
    {
        return Arr::mapWithKeys(
            $this->scoped,
            fn ($value, $scope) => [$scope => $value->__debugInfo()]
        );
    }

    /**
     * @return list<array{'name':string,'object_id':string|null,'scope':string|null}>
     */
    public function toArray(): array
    {
        return array_merge(
            ...array_map(
                fn (Permissions $permissions, string $scope) => array_map(
                    fn (array $item) => array_merge($item, ['scope' => $scope ?: null]),
                    $permissions->toArray()
                ),
                $this->scoped,
                array_keys($this->scoped)
            )
        );
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * @param  list<array{'name':string,'object_id':string|null,'scope':string|null}>  $items
     */
    public static function fromArray(array $items): self
    {
        return new self($items);
    }

    public static function fromJson(string $json): self
    {
        $items = json_decode($json, associative: true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON provided: '.json_last_error_msg());
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
                    return new ScopedPermissions;
                }

                return ScopedPermissions::fromJson($value);
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
