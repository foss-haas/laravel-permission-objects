<?php

namespace FossHaas\LaravelPermissionObjects;

use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;

/**
 * Represents a permission type that can be assigned, revoked or checked.
 *
 * @property string|null $qualifier The qualifier of the permission (i.e. the
 * morph alias of the object type) or null for global permissions
 * @property string $name The name of the permission
 * @property string|null $objectType The object type (i.e. class name) the
 * permission is applicable to or null for global permissions
 */
class Permission
{
    use Traits\HasPermissions;

    /**
     * @var array<string,array<string,string|Closure>>
     */
    private static array $definitions = [];

    /**
     * @var array<string,Permission>
     */
    private static array $instances = [];

    private function __construct(
        private ?string $qualifier,
        private string $name,
        private string|Closure $label,
        private ?string $objectType
    ) {}

    /**
     * Get the ID of the permission.
     *
     * For global permissions, the ID is just the name. For object/class
     * permissions, the ID is prefixed by the morph alias of the object type.
     */
    public function getKey(): string
    {
        return $this->qualifier ? "{$this->qualifier}.{$this->name}" : $this->name;
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'qualifier' => $this->qualifier,
            'name' => $this->name,
            'objectType' => $this->objectType,
            default => null,
        };
    }

    /**
     * Get the human-readable label of the permission.
     */
    public function getLabel(): string
    {
        $label = $this->label;
        if ($label instanceof Closure) {
            return $label();
        }

        return $label;
    }

    /**
     * Check if the permission is applicable to the given object.
     *
     * @param  string|object|null  $object The object or object type (i.e. class
     * name) to check against or null for global permissions
     */
    public function isApplicableTo(mixed $object): bool
    {
        if (is_string($object)) {
            return Relation::getMorphAlias($object) === $this->qualifier;
        }
        if ($object === null && ! $this->objectType) {
            return true;
        }
        if (! is_object($object) || ! $this->objectType) {
            return false;
        }

        return $object instanceof $this->objectType;
    }

    /**
     * Check if the permission is not applicable to the given object.
     *
     * @param  string|object|null  $object The object or object type (i.e. class
     * name) to check against or null for global permissions
     */
    public function isNotApplicableTo(mixed $object): bool
    {
        return ! $this->isApplicableTo($object);
    }

    public function __debugInfo(): array
    {
        return [
            'id' => $this->getKey(),
            'objectType' => $this->objectType,
            'name' => $this->name,
            'label' => $this->getLabel(),
        ];
    }

    /**
     * Register permissions for a class.
     *
     * @param string|null $class The class to register permissions for or null
     * for global permissions
     * @param array<string,string|Closure> $permissions The permissions to
     * register (name => label)
     */
    public static function register(?string $class, array $permissions): void
    {
        if (! $class) {
            $class = '';
        }
        static::$definitions[$class] = isset(static::$definitions[$class])
            ? static::$definitions[$class] + $permissions
            : $permissions;
    }

    /**
     * Find a permission by its ID.
     *
     * @param string $id The ID of the permission to find
     * @return Permission|null The found permission or null if not found
     */
    public static function find(string $id): ?Permission
    {
        static::loadValues();
        if (! isset(static::$instances[$id])) {
            return null;
        }

        return static::$instances[$id];
    }

    /**
     * Resolve a permission by name and object type.
     *
     * @param string $name The name of the permission
     * @param string|null $objectType The object type (i.e. class name) or null
     * for global permissions
     * @return Permission|null The resolved permission or null if not found
     */
    public static function resolve(string $name, ?string $objectType): ?Permission
    {
        if ($objectType === null) {
            return static::find($name);
        }
        $qualifier = Relation::getMorphAlias($objectType);

        return static::find("{$qualifier}.{$name}");
    }

    /**
     * Get all registered permissions.
     *
     * @return array<string,Permission> All registered permissions by ID
     */
    public static function all(): array
    {
        static::loadValues();

        return static::$instances;
    }
    /**
     * Get all permissions for a specific class.
     *
     * @param string|null $objectType The object type (i.e. class name) to get
     * permissions for or null for global permissions
     * @return array<string,Permission> Permissions for the specified object
     * type (i.e. class name) by name (relative to the class name)
     */
    public static function for(string|null $objectType): array
    {
        static::loadValues();

        return Arr::mapWithKeys(
            array_filter(
                static::$instances,
                fn($permission) => $permission->objectType === $objectType
            ),
            fn($permission) => [$permission->name => $permission]
        );
    }

    /**
     * Create instances for all previously defined permissions if they don't
     * exist yet.
     */
    protected static function loadValues(): void
    {
        if (! static::$definitions) {
            return;
        }
        foreach (static::$definitions as $objectType => $permissions) {
            $qualifier = $objectType ? Relation::getMorphAlias($objectType) : null;
            foreach ($permissions as $name => $label) {
                $id = $qualifier ? "{$qualifier}.{$name}" : $name;
                if (isset(static::$instances[$id])) {
                    continue;
                }
                $instance = new static($qualifier, $name, $label, $objectType ?: null);
                static::$instances[$id] = $instance;
            }
        }
        static::$definitions = [];
    }

    /**
     * Reset registered permissions.
     */
    public static function reset(): void
    {
        static::$definitions = [];
        static::$instances = [];
    }
}
