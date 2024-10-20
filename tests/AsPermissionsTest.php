<?php

namespace FossHaas\LaravelPermissionObjects\Tests;

use FossHaas\LaravelPermissionObjects\AsPermissions;
use FossHaas\LaravelPermissionObjects\Permission;
use Illuminate\Database\Eloquent\Relations\Relation;
use PHPUnit\Framework\TestCase;

class AsPermissionsTest extends TestCase
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

  /**
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::grant
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::has
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::revoke
   */
  public function testSimplePermissions()
  {
    $permissions = new AsPermissions();
    $simplePermission = Permission::find('simple-permission');

    $this->assertFalse($permissions->has($simplePermission, null));

    $permissions->grant($simplePermission, null);
    $this->assertTrue($permissions->has($simplePermission, null));

    $permissions->revoke($simplePermission, null);
    $this->assertFalse($permissions->has($simplePermission, null));
  }

  /**
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::grant
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::has
   */
  public function testClassLevelPermissions()
  {
    $permissions = new AsPermissions();
    $viewPermission = Permission::find('test-model.view');

    $this->assertFalse($permissions->has($viewPermission, null));

    $permissions->grant($viewPermission, null);
    $this->assertTrue($permissions->has($viewPermission, null));
    $this->assertTrue($permissions->has($viewPermission, '1'));
    $this->assertTrue($permissions->has($viewPermission, '2'));

    $permissions->revoke($viewPermission, null);
    $this->assertFalse($permissions->has($viewPermission, null));
    $this->assertFalse($permissions->has($viewPermission, '1'));
  }

  /**
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::grant
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::has
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::revoke
   */
  public function testObjectLevelPermissions()
  {
    $permissions = new AsPermissions();
    $editPermission = Permission::find('test-model.edit');

    $this->assertFalse($permissions->has($editPermission, '1'));
    $this->assertFalse($permissions->has($editPermission, '2'));

    $permissions->grant($editPermission, '1');
    $this->assertTrue($permissions->has($editPermission, '1'));
    $this->assertFalse($permissions->has($editPermission, '2'));

    $permissions->grant($editPermission, '2');
    $this->assertTrue($permissions->has($editPermission, '1'));
    $this->assertTrue($permissions->has($editPermission, '2'));

    $permissions->revoke($editPermission, '1');
    $this->assertFalse($permissions->has($editPermission, '1'));
    $this->assertTrue($permissions->has($editPermission, '2'));
  }

  /**
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::grant
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::has
   */
  public function testMultipleObjectLevelPermissions()
  {
    $permissions = new AsPermissions();
    $viewPermission = Permission::find('test-model.view');
    $editPermission = Permission::find('test-model.edit');

    $permissions->grant($viewPermission, '1');
    $permissions->grant($editPermission, '1');

    $this->assertTrue($permissions->has($viewPermission, '1'));
    $this->assertTrue($permissions->has($editPermission, '1'));

    $permissions->revoke($viewPermission, '1');
    $this->assertFalse($permissions->has($viewPermission, '1'));
    $this->assertTrue($permissions->has($editPermission, '1'));
  }

  /**
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::grant
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::has
   */
  public function testClassLevelOverwritesObjectLevel()
  {
    $permissions = new AsPermissions();
    $viewPermission = Permission::find('test-model.view');

    $permissions->grant($viewPermission, '1');
    $this->assertTrue($permissions->has($viewPermission, '1'));
    $this->assertFalse($permissions->has($viewPermission, '2'));

    $permissions->grant($viewPermission, null);
    $this->assertTrue($permissions->has($viewPermission, '1'));
    $this->assertTrue($permissions->has($viewPermission, '2'));
  }

  /**
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::grant
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::has
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::revoke
   */
  public function testRevokeObjectLevelWhenClassLevelExists()
  {
    $permissions = new AsPermissions();
    $viewPermission = Permission::find('test-model.view');

    $permissions->grant($viewPermission, null);
    $this->assertTrue($permissions->has($viewPermission, '1'));

    $permissions->revoke($viewPermission, '1');
    $this->assertTrue($permissions->has($viewPermission, '1'));
  }

  /**
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::grant
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::has
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::revokeAll
   */
  public function testRevokeAll()
  {
    $permissions = new AsPermissions();
    $simplePermission = Permission::find('simple-permission');
    $viewPermission = Permission::find('test-model.view');
    $editPermission = Permission::find('test-model.edit');

    $permissions->grant($simplePermission, null);
    $permissions->grant($viewPermission, null);
    $permissions->grant($editPermission, '1');

    $permissions->revokeAll();

    $this->assertFalse($permissions->has($simplePermission, null));
    $this->assertFalse($permissions->has($viewPermission, null));
    $this->assertFalse($permissions->has($editPermission, '1'));
  }

  /**
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::grant
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::has
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::revokeAll
   */
  public function testRevokeAllForSpecificPermission()
  {
    $permissions = new AsPermissions();
    $viewPermission = Permission::find('test-model.view');
    $editPermission = Permission::find('test-model.edit');

    $permissions->grant($viewPermission, null);
    $permissions->grant($editPermission, '1');
    $permissions->grant($editPermission, '2');

    $permissions->revokeAll($editPermission);

    $this->assertTrue($permissions->has($viewPermission, null));
    $this->assertFalse($permissions->has($editPermission, '1'));
    $this->assertFalse($permissions->has($editPermission, '2'));
  }

  /**
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::grant
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::has
   */
  public function testPermissionsDoNotApplyToOtherModels()
  {
    $permissions = new AsPermissions();
    $viewTestPermission = Permission::find('test-model.view');
    $viewOtherPermission = Permission::find('other-model.view');

    $permissions->grant($viewTestPermission, null);
    $this->assertTrue($permissions->has($viewTestPermission, null));
    $this->assertFalse($permissions->has($viewOtherPermission, null));

    $permissions->grant($viewTestPermission, '1');
    $this->assertTrue($permissions->has($viewTestPermission, '1'));
    $this->assertFalse($permissions->has($viewOtherPermission, '1'));
  }

  /**
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::grant
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::can
   */
  public function testCanMethod()
  {
    $permissions = new AsPermissions();
    $simplePermission = Permission::find('simple-permission');
    $viewTestPermission = Permission::find('test-model.view');
    $editTestPermission = Permission::find('test-model.edit');

    $permissions->grant($simplePermission, null);
    $permissions->grant($viewTestPermission, null);
    $permissions->grant($editTestPermission, '1');

    // Simple permission
    $this->assertTrue($permissions->can('simple-permission', null));
    $this->assertNull($permissions->can('simple-permission', TestModel::class));
    $this->assertNull($permissions->can('simple-permission', new TestModel()));

    // Class-level permission
    $this->assertTrue($permissions->can('view', TestModel::class));
    $this->assertTrue($permissions->can('view', new TestModel()));
    $this->assertTrue($permissions->can('view', new TestModel(['id' => 1])));
    $this->assertTrue($permissions->can('view', new TestModel(['id' => 2])));

    // Object-level permission
    $this->assertTrue($permissions->can('edit', new TestModel(['id' => 1])));
    $this->assertFalse($permissions->can('edit', new TestModel(['id' => 2])));
    $this->assertFalse($permissions->can('edit', TestModel::class));

    // Non-matching permissions
    $this->assertNull($permissions->can('frobnicate', TestModel::class));
    $this->assertFalse($permissions->can('view', OtherModel::class));
    $this->assertFalse($permissions->can('view', new OtherModel()));
  }

  /**
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::grant
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::can
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::revoke
   */
  public function testClassLevelPermissionsApplyToObjects()
  {
    $permissions = new AsPermissions();
    $viewTestPermission = Permission::find('test-model.view');

    $permissions->grant($viewTestPermission, null);

    // Class-level permission applies to the class itself
    $this->assertTrue($permissions->can('view', TestModel::class));

    // Class-level permission applies to all objects of that class
    $this->assertTrue($permissions->can('view', new TestModel()));
    $this->assertTrue($permissions->can('view', new TestModel(['id' => 1])));
    $this->assertTrue($permissions->can('view', new TestModel(['id' => 2])));

    // Class-level permission doesn't apply to other classes or their objects
    $this->assertFalse($permissions->can('view', OtherModel::class));
    $this->assertFalse($permissions->can('view', new OtherModel()));

    // Revoking the class-level permission
    $permissions->revoke($viewTestPermission, null);

    // After revocation, the permission no longer applies to the class or its objects
    $this->assertFalse($permissions->can('view', TestModel::class));
    $this->assertFalse($permissions->can('view', new TestModel()));
    $this->assertFalse($permissions->can('view', new TestModel(['id' => 1])));
  }

  /**
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::__construct
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::can
   * @covers \FossHaas\LaravelPermissionObjects\AsPermissions::toArray
   */
  public function testLoadAndToArray()
  {
    $initialPermissions = [
      ['name' => 'simple-permission', 'object_id' => null],
      ['name' => 'test-model.view', 'object_id' => null],
      ['name' => 'test-model.edit', 'object_id' => '1'],
      ['name' => 'test-model.edit', 'object_id' => '2'],
    ];

    $permissions = new AsPermissions($initialPermissions);

    $this->assertTrue($permissions->can('simple-permission', null));
    $this->assertTrue($permissions->can('view', TestModel::class));
    $this->assertTrue($permissions->can('edit', new TestModel(['id' => 1])));
    $this->assertTrue($permissions->can('edit', new TestModel(['id' => 2])));
    $this->assertFalse($permissions->can('edit', new TestModel(['id' => 3])));

    $arrayRepresentation = $permissions->toArray();
    $this->assertCount(4, $arrayRepresentation);
    $this->assertContains(['name' => 'simple-permission', 'object_id' => null], $arrayRepresentation);
    $this->assertContains(['name' => 'test-model.view', 'object_id' => null], $arrayRepresentation);
    $this->assertContains(['name' => 'test-model.edit', 'object_id' => '1'], $arrayRepresentation);
    $this->assertContains(['name' => 'test-model.edit', 'object_id' => '2'], $arrayRepresentation);
  }
}
