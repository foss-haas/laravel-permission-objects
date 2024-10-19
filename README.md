# Laravel Permission Objects

This package implements object-level, model-level and global permissions for
Laravel.

Once installed you can do stuff like this:

```php
$permission = $article::getPermission("edit");
$user->permissions->grant($permission, $article);

$user->permissions->revoke($permission);

$user->permissions->revokeAll();
```

## Basic Usage

### Modify User Model

If you want to add permissions to your users, you will need to add an attribute
to store the permissions. You can call it anything you want:

```php
$table->jsonb('permissions');
```

You need to cast it to `Permissions` or `ScopedPermissions` depending on
whether you need permissions to be global or scoped (e.g. to a tenant):

```php
use FossHaas\LaravelPermissionObjects\Permissions;

protected function casts(): array
{
    return [
        'permissions' => Permissions::class,
    ];
}
```

### Define Permissions

You can define permissions in your service provider's `boot` method:

```php
use App\Models\User;
use FossHaas\LaravelPermissionObjects\Permission;

Permission::register(User::class, [
    'manage' => fn() => __('Manage users'),
    'change-passwords' => fn() => __('Change user passwords'),
]);
```

By default permission names will be qualified with the full name of the model
class they're defined for. If you want nicer looking (shorter) names, you can
use morph maps. Make sure to define your morph maps before looking up
permissions:

```php
use Illuminate\Database\Eloquent\Relations\Relation;

Relation::morphMap([
    'user' => \App\Models\User::class,
]);
```

You can also define permissions for classes that are not models:

```php
Permission::register(Permission::class, [
    'assign' => fn() => __('Assign permissions'),
]);
```

### Look Up Definitions

You can look up permissions you have defined using the short name and the name
of the class they were defined for:

```php
use App\Models\User;

$manageUsersPermission = Permission::resolve('manage', User::class);

// This also works if your morph map is set up correctly:
$manageUsersPermission = Permission::resolve('manage', 'user');
```

You can also look them up using the fully qualified name:

```php
use App\Models\User;

$manageUsersPermission = Permission::find(User::class . '.' . 'manage');

// This also works if your morph map is set up correctly:
$manageUsersPermission = Permission::find('user.manage');
```

#### Using Models

If you want to look up permission objects from the models you define them for,
you can also add the `HasPermissions` trait to them:

```php
use FossHaas\LaravelPermissionObjects\Traits\HasPermissions;

class User extends Authenticatable {
    use HasPermissions;
    // ...
}
```

Now you can look up permissions defined for your model on the model:

```php
$manageUsersPermission = User::getPermission('manage');
```

The `Permission` class comes with this trait already baked in so there is no
need to extend it if you want to define permissions for it:

```php
use FossHaas\LaravelPermissionObjects\Permission;

$assignPermissionsPermission = Permission::getPermission('assign');
```

### Assign permissions

You can use the methods on your model's permissions attribute to manage its
assigned permissions. You can either pass the key (or ID) of the instance (object)
you want the permission to be restricted to or `null` if you want the
permission to be valid for any instance of its type.

When passing an ID make sure it is cast to a string if it isn't one already:

```php
use App\Models\User;
use FossHaas\LaravelPermissionObjects\Permission;

$permission = Permission::find('article.edit');

// grant permission to edit only a specific article
$user->permissions->grant($permission, (string) $article->id);

// grant permission to edit any article
$user->permissions->grant($permission, null);

// revoke only permission to edit a specific article, if it was granted
$user->permissions->revoke($permission, (string) $article->id);

// revoke only permission to edit any article, if it was granted
$user->permissions->revoke($permission, null);
```

Granting a model level permission (using `null`) will override any existing
object level permissions (using object IDs) of the same permission type.

Revoking a model level permission has no effect if the user was only granted
object level permissions. In this case `revokeAll` can be used to revoke any
model or object level permissions of the permission type:

```php
// revoke all permissions of the given permission type
$user->permissions->revokeAll($permission);

// revoke all permissions ever granted to the user
$user->permissions->revokeAll();
```

### Check permissions

The permissions attribute supports a simple presence check:

```php
use App\Models\User;
use FossHaas\LaravelPermissionObjects\Permission;

$permission = Permission::find('article.edit');

// Check if the user has the permission for this instance
$user->permissions->has($permission, (string) $article->id);

// Check if the user has the permission for all instances
$user->permissions->has($permission, null);
```

If a permission was granted at the model level (using `null`), any instance
level checks will also pass:

```php
$user->permissions->grant($permission, null);
// This will always pass
$user->permissions->has($permission, (string) $article->id);
```

#### Using Gates

The permissions attribute also provides a `can` method which can be used in a
`Gate::after` fallback in your service provider if you don't want to set up
gates or policies yourself:

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;

Gate::after(function (User $user, string $ability, bool|null $result, mixed $arguments) {
    if ($result !== null) return $result;
    $object = isset($arguments[0]) ? $arguments[0] : null;
    return $user->permissions->can($ability, $object);
});

// Elsewhere ...
Gate::authorize('edit', [$article]);
```

This also works when using `ScopedPermissions`:

```php
Gate::after(function (User $user, string $ability, bool|null $result, mixed $arguments) {
    if ($result !== null) return $result;
    $object = isset($arguments[0]) ? $arguments[0] : null;
    $scopes = isset($arguments[1]) ? $arguments[1] : "";
    return $user->permissions->can($ability, $object, $scopes);
});

// Elsewhere ...
Gate::authorize('edit', [$article, $scope]);
```

Note that the `can` method returns `null` when passed a permission name it does
not recognize or that can't be resolved using the object or object type it is
passed.

## Global Permissions

Permissions don't have to be tied to specific models or classes. You can
define global permissions by passing `null` instead of a class when registering
them:

```php
Permission::register(null, [
    'self-destruct' => fn() => __('Initiate self-destruct')
]);
```

Note that you will still need to pass `null` as an object ID when using this
permission as this argument is intentionally not optional to avoid mistakes:

```php
$permission = Permission::find('self-destruct');
$user->permissions->has($permission, null);

// This also works:
$user->permissions->can('self-destruct', null);
```

If you want to misuse the object ID for your own purposes, keep in mind that
the `can` method will not work correctly as it expects the `string` argument
to be a class name and will attempt to resolve the permission name using it:

```php
// This DOES NOT work:
$user->permissions->can('self-destruct', '1234'); // Always returns null!
```

### Super Admins

Although not built for this purpose, global permissions can be used to
implement as "super admin" flag that will pass all `Gate` or `Policy` checks:

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use FossHaas\LaravelPermissionObjects\Permission;

Permission::register(null, [
    'is-super-admin' => fn() => __('Is Super Admin'),
]);

// Use as a fallback check if no other Gate or Policy applied
Gate::after(function (User $user): bool {
    return $user->permissions->has('is-super-admin', null);
});

// Alternatively, if super admins should always bypass all rules
Gate::before(function (User $user): bool|null {
    return $user->permissions->has('is-super-admin', null) ?: null;
});
```

## Scoped Permissions

When using `ScopedPermissions`, you can pass in an additional `scopes`
parameter to method calls to define which scope or scopes the method should
consider:

```php
$user->permissions->grant($permission, $objectId, $scope);
```

Alternatively, you can use the `scope` method to access the `Permissions`
for that scope directly:

```php
$user->permissions->scope($scope)->grant($permission, $objectId);
```

Scopes are identified by their name as string values. The meaning of scopes is
up to your application's needs but could range from organizational units of
your company to different customers in a poor man's single-database
multi-tenancy implementation.

The default or global scope is identified by the empty string and will be used
if no scope is passed explicitly.

All permission checks using `has` or `can` will always also check the default
scope in addition to any scopes passed explicitly.

To revoke all permissions in all scopes, `null` can be passed as scope when
using `revokeAll`:

```php
$user->permissions->revokeAll($permission, scopes: null);
```

## Roles

If you want to implement role-based authorization, you can create a role model
and give it a `Permissions` attribute just as you would for a user model. As
this package aims to be unopinionated, how you use this model is up to you,
but a possible schema could look like this:

```php
Schema::create('roles', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->json('permissions');
});

Schema::create('user_roles', function (Blueprint $table) {
    $table->foreignIdFor(User::class)
        ->constrained('users')->cascadeOnDelete();
    $table->foreignIdFor(Role::class)
        ->constrained('roles')->cascadeOnDelete();
});
```

## License

Copyright (c) 2004 Foss & Haas GmbH.

This package is licensed under the terms of the MIT license.
