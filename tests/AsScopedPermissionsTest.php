<?php

namespace FossHaas\LaravelPermissionObjects\Tests;

use FossHaas\LaravelPermissionObjects\AsScopedPermissions;
use FossHaas\LaravelPermissionObjects\Permission;
use Illuminate\Database\Eloquent\Relations\Relation;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversMethod(AsScopedPermissions::class, '__construct')]
#[CoversMethod(AsScopedPermissions::class, 'can')]
#[CoversMethod(AsScopedPermissions::class, 'grant')]
#[CoversMethod(AsScopedPermissions::class, 'has')]
#[CoversMethod(AsScopedPermissions::class, 'revoke')]
#[CoversMethod(AsScopedPermissions::class, 'revokeAll')]
#[CoversMethod(AsScopedPermissions::class, 'scope')]
#[CoversMethod(AsScopedPermissions::class, 'toArray')]
class AsScopedPermissionsTest extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();
    Permission::reset();
    Permission::register(null, [
      'simple-permission' => fn() => 'Simple Permission',
    ]);
    Permission::register(TestModel::class, [
      'view' => fn() => 'View Test Model',
      'edit' => fn() => 'Edit Test Model',
    ]);
    Permission::register(OtherModel::class, [
      'view' => fn() => 'View Other Model',
    ]);
    Relation::morphMap([
      'test-model' => TestModel::class,
      'other-model' => OtherModel::class,
    ]);
  }

  public function testScopedPermissions()
  {
    $permissions = new AsScopedPermissions();
    $viewPermission = Permission::find('test-model.view');

    $permissions->grant($viewPermission, null, 'scope1');
    $this->assertTrue($permissions->has($viewPermission, null, 'scope1'));
    $this->assertFalse($permissions->has($viewPermission, null, 'scope2'));

    $permissions->grant($viewPermission, '1', 'scope2');
    $this->assertTrue($permissions->has($viewPermission, '1', 'scope2'));
    $this->assertFalse($permissions->has($viewPermission, '2', 'scope2'));
  }

  public function testDefaultScope()
  {
    $permissions = new AsScopedPermissions();
    $viewPermission = Permission::find('test-model.view');

    $permissions->grant($viewPermission, null);
    $this->assertTrue($permissions->has($viewPermission, null));
    $this->assertTrue($permissions->has($viewPermission, null, AsScopedPermissions::DEFAULT_SCOPE));
    $this->assertTrue($permissions->has($viewPermission, null, 'other-scope'));
  }

  public function testMultipleScopesCheck()
  {
    $permissions = new AsScopedPermissions();
    $viewPermission = Permission::find('test-model.view');

    $permissions->grant($viewPermission, null, 'scope1');
    $this->assertTrue($permissions->has($viewPermission, null, ['scope1', 'scope2']));
    $this->assertTrue($permissions->has($viewPermission, null, ['scope2', 'scope1']));
    $this->assertFalse($permissions->has($viewPermission, null, ['scope2', 'scope3']));
  }

  public function testAllScopesCheck()
  {
    $permissions = new AsScopedPermissions();
    $viewPermission = Permission::find('test-model.view');

    $permissions->grant($viewPermission, null, 'scope1');
    $this->assertTrue($permissions->has($viewPermission, null, AsScopedPermissions::ALL_SCOPES));

    $permissions->grant($viewPermission, '1', 'scope2');
    $this->assertTrue($permissions->has($viewPermission, '1', AsScopedPermissions::ALL_SCOPES));
    $this->assertTrue($permissions->has($viewPermission, '2', AsScopedPermissions::ALL_SCOPES));
  }

  public function testRevokeScoped()
  {
    $permissions = new AsScopedPermissions();
    $viewPermission = Permission::find('test-model.view');

    $permissions->grant($viewPermission, null, 'scope1');
    $permissions->grant($viewPermission, null, 'scope2');

    $permissions->revoke($viewPermission, null, 'scope1');
    $this->assertFalse($permissions->has($viewPermission, null, 'scope1'));
    $this->assertTrue($permissions->has($viewPermission, null, 'scope2'));
  }

  public function testRevokeAllScoped()
  {
    $permissions = new AsScopedPermissions();
    $viewPermission = Permission::find('test-model.view');
    $editPermission = Permission::find('test-model.edit');

    $permissions->grant($viewPermission, null, 'scope1');
    $permissions->grant($editPermission, null, 'scope1');
    $permissions->grant($viewPermission, null, 'scope2');

    $permissions->revokeAll($viewPermission, 'scope1');
    $this->assertFalse($permissions->has($viewPermission, null, 'scope1'));
    $this->assertTrue($permissions->has($editPermission, null, 'scope1'));
    $this->assertTrue($permissions->has($viewPermission, null, 'scope2'));

    $permissions->revokeAll(null, 'scope1');
    $this->assertFalse($permissions->has($editPermission, null, 'scope1'));
    $this->assertTrue($permissions->has($viewPermission, null, 'scope2'));
  }

  public function testRevokeAllWithAllScopes()
  {
    $permissions = new AsScopedPermissions();
    $viewPermission = Permission::find('test-model.view');
    $editPermission = Permission::find('test-model.edit');
    $simplePermission = Permission::find('simple-permission');

    $permissions->grant($viewPermission, null, 'scope1');
    $permissions->grant($editPermission, '1', 'scope2');
    $permissions->grant($simplePermission, null, 'scope3');

    // Revoke all permissions for a specific permission across all scopes
    $permissions->revokeAll($viewPermission, AsScopedPermissions::ALL_SCOPES);
    $this->assertFalse($permissions->has($viewPermission, null, 'scope1'));
    $this->assertTrue($permissions->has($editPermission, '1', 'scope2'));
    $this->assertTrue($permissions->has($simplePermission, null, 'scope3'));

    // Revoke all permissions across all scopes
    $permissions->revokeAll(null, AsScopedPermissions::ALL_SCOPES);
    $this->assertFalse($permissions->has($editPermission, '1', 'scope2'));
    $this->assertFalse($permissions->has($simplePermission, null, 'scope3'));

    // Verify that all permissions have been revoked
    $this->assertEmpty($permissions->toArray());
  }

  public function testCanMethod()
  {
    $permissions = new AsScopedPermissions();
    $viewPermission = Permission::find('test-model.view');
    $editPermission = Permission::find('test-model.edit');

    $permissions->grant($viewPermission, null, 'scope1');
    $permissions->grant($editPermission, '1', 'scope2');

    $this->assertTrue($permissions->can('view', TestModel::class, 'scope1'));
    $this->assertFalse($permissions->can('view', TestModel::class, 'scope2'));
    $this->assertTrue($permissions->can('edit', new TestModel(['id' => 1]), 'scope2'));
    $this->assertFalse($permissions->can('edit', new TestModel(['id' => 2]), 'scope2'));
  }

  public function testScopeMethod()
  {
    $permissions = new AsScopedPermissions();
    $viewPermission = Permission::find('test-model.view');

    $permissions->scope('scope1')->grant($viewPermission, null);
    $this->assertTrue($permissions->has($viewPermission, null, 'scope1'));
    $this->assertFalse($permissions->has($viewPermission, null, 'scope2'));
  }

  public function testLoadAndToArray()
  {
    $initialPermissions = [
      ['name' => 'simple-permission', 'object_id' => null, 'scope' => 'scope1'],
      ['name' => 'test-model.view', 'object_id' => null, 'scope' => 'scope1'],
      ['name' => 'test-model.edit', 'object_id' => '1', 'scope' => 'scope2'],
      ['name' => 'test-model.edit', 'object_id' => '2', 'scope' => 'scope2'],
    ];

    $permissions = new AsScopedPermissions($initialPermissions);

    $this->assertTrue($permissions->can('simple-permission', null, 'scope1'));
    $this->assertTrue($permissions->can('view', TestModel::class, 'scope1'));
    $this->assertTrue($permissions->can('edit', new TestModel(['id' => 1]), 'scope2'));
    $this->assertTrue($permissions->can('edit', new TestModel(['id' => 2]), 'scope2'));
    $this->assertFalse($permissions->can('edit', new TestModel(['id' => 3]), 'scope2'));

    $arrayRepresentation = $permissions->toArray();
    $this->assertCount(4, $arrayRepresentation);
    $this->assertContains(['name' => 'simple-permission', 'object_id' => null, 'scope' => 'scope1'], $arrayRepresentation);
    $this->assertContains(['name' => 'test-model.view', 'object_id' => null, 'scope' => 'scope1'], $arrayRepresentation);
    $this->assertContains(['name' => 'test-model.edit', 'object_id' => '1', 'scope' => 'scope2'], $arrayRepresentation);
    $this->assertContains(['name' => 'test-model.edit', 'object_id' => '2', 'scope' => 'scope2'], $arrayRepresentation);
  }

  public function testMixedScopeAndLevelPermissions()
  {
    $permissions = new AsScopedPermissions();
    $viewPermission = Permission::find('test-model.view');
    $editPermission = Permission::find('test-model.edit');

    // Grant class-level view permission in the default scope
    $permissions->grant($viewPermission, null);

    // Grant object-level edit permission in a specific scope
    $permissions->grant($editPermission, '1', 'scope1');

    // Test class-level permission in default scope
    $this->assertTrue($permissions->can('view', TestModel::class));
    $this->assertTrue($permissions->can('view', TestModel::class, 'any-scope'));

    // Test object-level permission in specific scope
    $this->assertTrue($permissions->can('edit', new TestModel(['id' => 1]), 'scope1'));
    $this->assertFalse($permissions->can('edit', new TestModel(['id' => 2]), 'scope1'));

    // Test fallback to default scope
    $this->assertTrue($permissions->can('view', new TestModel(['id' => 1]), 'scope1'));
    $this->assertTrue($permissions->can('view', new TestModel(['id' => 2]), 'scope2'));

    // Test that edit permission doesn't fall back to default scope
    $this->assertFalse($permissions->can('edit', new TestModel(['id' => 1]), 'scope2'));
    $this->assertFalse($permissions->can('edit', TestModel::class));

    // Grant class-level edit permission in a specific scope
    $permissions->grant($editPermission, null, 'scope2');

    // Test class-level permission in specific scope
    $this->assertTrue($permissions->can('edit', TestModel::class, 'scope2'));
    $this->assertTrue($permissions->can('edit', new TestModel(['id' => 3]), 'scope2'));
    $this->assertFalse($permissions->can('edit', TestModel::class, 'scope1'));

    // Test that specific scope doesn't affect default scope
    $this->assertFalse($permissions->can('edit', TestModel::class));
  }
}
