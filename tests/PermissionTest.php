<?php

namespace FossHaas\LaravelPermissionObjects\Tests;

use FossHaas\LaravelPermissionObjects\Permission;
use Illuminate\Database\Eloquent\Relations\Relation;
use PHPUnit\Framework\TestCase;

class PermissionTest extends TestCase
{
  protected $currentLocale = 'en';

  protected function setUp(): void
  {
    parent::setUp();
    Permission::reset();
    Permission::register(null, [
      'global-permission' => fn() => $this->__('Global Permission'),
    ]);
    Permission::register(TestModel::class, [
      'view' => fn() => $this->__('View Test Model'),
      'edit' => fn() => $this->__('Edit Test Model'),
    ]);
    Relation::morphMap([
      'test-model' => TestModel::class,
    ]);
  }

  /**
   * @covers \FossHaas\LaravelPermissionObjects\Permission::register
   * @covers \FossHaas\LaravelPermissionObjects\Permission::find
   * @covers \FossHaas\LaravelPermissionObjects\Permission::getKey
   */
  public function testRegisterAndFind()
  {
    $globalPermission = Permission::find('global-permission');
    $this->assertNotNull($globalPermission);
    $this->assertEquals('global-permission', $globalPermission->getKey());

    $viewPermission = Permission::find('test-model.view');
    $this->assertNotNull($viewPermission);
    $this->assertEquals('test-model.view', $viewPermission->getKey());
  }

  /**
   * @covers \FossHaas\LaravelPermissionObjects\Permission::resolve
   */
  public function testResolve()
  {
    $globalPermission = Permission::resolve('global-permission', null);
    $this->assertNotNull($globalPermission);
    $this->assertEquals('global-permission', $globalPermission->getKey());

    $viewPermission = Permission::resolve('view', TestModel::class);
    $this->assertNotNull($viewPermission);
    $this->assertEquals('test-model.view', $viewPermission->getKey());
  }

  /**
   * @covers \FossHaas\LaravelPermissionObjects\Permission::all
   */
  public function testAll()
  {
    $allPermissions = Permission::all();
    $this->assertCount(3, $allPermissions);
    $this->assertArrayHasKey('global-permission', $allPermissions);
    $this->assertArrayHasKey('test-model.view', $allPermissions);
    $this->assertArrayHasKey('test-model.edit', $allPermissions);
  }

  /**
   * @covers \FossHaas\LaravelPermissionObjects\Permission::for
   */
  public function testFor()
  {
    $testModelPermissions = Permission::for(TestModel::class);
    $this->assertCount(2, $testModelPermissions);
    $this->assertArrayHasKey('view', $testModelPermissions);
    $this->assertArrayHasKey('edit', $testModelPermissions);
  }

  /**
   * @covers \FossHaas\LaravelPermissionObjects\Permission::isApplicableTo
   */
  public function testIsApplicableTo()
  {
    $globalPermission = Permission::find('global-permission');
    $viewPermission = Permission::find('test-model.view');

    $this->assertTrue($globalPermission->isApplicableTo(null));
    $this->assertFalse($globalPermission->isApplicableTo('any-string'));
    $this->assertFalse($globalPermission->isApplicableTo(new OtherModel()));

    $this->assertFalse($viewPermission->isApplicableTo(null));
    $this->assertTrue($viewPermission->isApplicableTo(TestModel::class));
    $this->assertFalse($viewPermission->isApplicableTo(OtherModel::class));
    $this->assertTrue($viewPermission->isApplicableTo(new TestModel()));
    $this->assertFalse($viewPermission->isApplicableTo(new OtherModel()));
  }

  /**
   * @covers \FossHaas\LaravelPermissionObjects\Permission::isNotApplicableTo
   */
  public function testIsNotApplicableTo()
  {
    $globalPermission = Permission::find('global-permission');
    $viewPermission = Permission::find('test-model.view');

    $this->assertFalse($globalPermission->isNotApplicableTo(null));
    $this->assertTrue($globalPermission->isNotApplicableTo('any-string'));
    $this->assertTrue($globalPermission->isNotApplicableTo(new OtherModel()));

    $this->assertTrue($viewPermission->isNotApplicableTo(null));
    $this->assertFalse($viewPermission->isNotApplicableTo(TestModel::class));
    $this->assertTrue($viewPermission->isNotApplicableTo(OtherModel::class));
    $this->assertFalse($viewPermission->isNotApplicableTo(new TestModel()));
    $this->assertTrue($viewPermission->isNotApplicableTo(new OtherModel()));
  }

  /**
   * @covers \FossHaas\LaravelPermissionObjects\Permission::getLabel
   */
  public function testDynamicLabel()
  {
    $globalPermission = Permission::find('global-permission');
    $viewPermission = Permission::find('test-model.view');
    $editPermission = Permission::find('test-model.edit');

    // Test English labels
    $this->currentLocale = 'en';
    $this->assertEquals('Global Permission', $globalPermission->getLabel());
    $this->assertEquals('View Test Model', $viewPermission->getLabel());
    $this->assertEquals('Edit Test Model', $editPermission->getLabel());

    // Test French labels
    $this->currentLocale = 'fr';
    $this->assertEquals('Permission Globale', $globalPermission->getLabel());
    $this->assertEquals('Voir le Modèle de Test', $viewPermission->getLabel());
    $this->assertEquals('Modifier le Modèle de Test', $editPermission->getLabel());
  }

  // Simulate a translation function
  protected function __(string $key): string
  {
    $translations = [
      'en' => [
        'Global Permission' => 'Global Permission',
        'View Test Model' => 'View Test Model',
        'Edit Test Model' => 'Edit Test Model',
      ],
      'fr' => [
        'Global Permission' => 'Permission Globale',
        'View Test Model' => 'Voir le Modèle de Test',
        'Edit Test Model' => 'Modifier le Modèle de Test',
      ],
    ];

    return $translations[$this->currentLocale][$key] ?? $key;
  }
}
