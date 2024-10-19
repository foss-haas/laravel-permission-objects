<?php

namespace FossHaas\LaravelPermissionObjects\Tests;

use FossHaas\LaravelPermissionObjects\Permission;
use FossHaas\LaravelPermissionObjects\Permissions;
use Mockery;
use PHPUnit\Framework\TestCase;

class PermissionsTest extends TestCase
{
    private Permissions $permissions;

    private Permission $testPermission;

    private Permission $globalPermission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->permissions = new Permissions;
        $this->testPermission = Mockery::mock(Permission::class);
        $this->testPermission->shouldReceive('getKey')->andReturn('test.permission');
        $this->globalPermission = Mockery::mock(Permission::class);
        $this->globalPermission->shouldReceive('getKey')->andReturn('global.permission');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testConstructorWithItems(): void
    {
        $items = [
            ['name' => 'test.permission', 'object_id' => '1'],
            ['name' => 'global.permission', 'object_id' => null],
        ];
        $permissions = new Permissions($items);

        $this->assertTrue($permissions->has($this->testPermission, '1'));
        $this->assertTrue($permissions->has($this->globalPermission, null));
    }

    public function testLoad(): void
    {
        $items = [
            ['name' => 'test.permission', 'object_id' => '1'],
            ['name' => 'test.permission', 'object_id' => '2'],
            ['name' => 'global.permission', 'object_id' => null],
        ];
        $this->permissions->load($items);

        $this->assertTrue($this->permissions->has($this->testPermission, '1'));
        $this->assertTrue($this->permissions->has($this->testPermission, '2'));
        $this->assertTrue($this->permissions->has($this->globalPermission, null));
    }

    public function testCan(): void
    {
        $this->permissions->grant($this->testPermission, '1');
        $this->permissions->grant($this->globalPermission, null);

        // Create a simple object with a getKey method
        $mockObject = new class
        {
            public function getKey()
            {
                return '1';
            }
        };

        // Mock the static Permission::resolve method
        Mockery::mock('alias:FossHaas\LaravelPermissionObjects\Permission')
            ->shouldReceive('resolve')
            ->andReturnUsing(function ($ability, $objectType) {
                if ($ability === 'test.permission' && $objectType === get_class($mockObject)) {
                    return $this->testPermission;
                } elseif ($ability === 'global.permission' && $objectType === null) {
                    return $this->globalPermission;
                }

                return null;
            });

        $this->assertTrue($this->permissions->can('test.permission', $mockObject));
        $this->assertTrue($this->permissions->can('global.permission', null));
        $this->assertNull($this->permissions->can('non.existent', null));
    }

    public function testHas(): void
    {
        $this->permissions->grant($this->testPermission, '1');
        $this->permissions->grant($this->globalPermission, null);

        $this->assertTrue($this->permissions->has($this->testPermission, '1'));
        $this->assertFalse($this->permissions->has($this->testPermission, '2'));
        $this->assertTrue($this->permissions->has($this->globalPermission, null));
        $this->assertTrue($this->permissions->has($this->globalPermission, '1')); // Global permission applies to all objects
    }

    public function testGrant(): void
    {
        $this->permissions->grant($this->testPermission, '1');
        $this->permissions->grant($this->globalPermission, null);

        $this->assertTrue($this->permissions->has($this->testPermission, '1'));
        $this->assertTrue($this->permissions->has($this->globalPermission, null));
    }

    public function testRevoke(): void
    {
        $this->permissions->grant($this->testPermission, '1');
        $this->permissions->grant($this->testPermission, '2');
        $this->permissions->grant($this->globalPermission, null);

        $this->permissions->revoke($this->testPermission, '1');
        $this->permissions->revoke($this->globalPermission, null);

        $this->assertFalse($this->permissions->has($this->testPermission, '1'));
        $this->assertTrue($this->permissions->has($this->testPermission, '2'));
        $this->assertFalse($this->permissions->has($this->globalPermission, null));
    }

    public function testRevokeAll(): void
    {
        $this->permissions->grant($this->testPermission, '1');
        $this->permissions->grant($this->testPermission, '2');
        $this->permissions->grant($this->globalPermission, null);

        $this->permissions->revokeAll($this->testPermission);

        $this->assertFalse($this->permissions->has($this->testPermission, '1'));
        $this->assertFalse($this->permissions->has($this->testPermission, '2'));
        $this->assertTrue($this->permissions->has($this->globalPermission, null));

        $this->permissions->revokeAll();

        $this->assertFalse($this->permissions->has($this->globalPermission, null));
    }

    public function testToArray(): void
    {
        $this->permissions->grant($this->testPermission, '1');
        $this->permissions->grant($this->testPermission, '2');
        $this->permissions->grant($this->globalPermission, null);

        $array = $this->permissions->toArray();

        $expected = [
            ['name' => 'test.permission', 'object_id' => '1'],
            ['name' => 'test.permission', 'object_id' => '2'],
            ['name' => 'global.permission', 'object_id' => null],
        ];

        $this->assertEquals($expected, $array);
    }

    public function testToJson(): void
    {
        $this->permissions->grant($this->testPermission, '1');
        $this->permissions->grant($this->globalPermission, null);

        $json = $this->permissions->toJson();

        $expected = json_encode([
            ['name' => 'test.permission', 'object_id' => '1'],
            ['name' => 'global.permission', 'object_id' => null],
        ]);

        $this->assertEquals($expected, $json);
    }

    public function testFromArray(): void
    {
        $array = [
            ['name' => 'test.permission', 'object_id' => '1'],
            ['name' => 'global.permission', 'object_id' => null],
        ];

        $permissions = Permissions::fromArray($array);

        $this->assertTrue($permissions->has($this->testPermission, '1'));
        $this->assertTrue($permissions->has($this->globalPermission, null));
    }

    public function testFromJson(): void
    {
        $json = json_encode([
            ['name' => 'test.permission', 'object_id' => '1'],
            ['name' => 'global.permission', 'object_id' => null],
        ]);

        $permissions = Permissions::fromJson($json);

        $this->assertTrue($permissions->has($this->testPermission, '1'));
        $this->assertTrue($permissions->has($this->globalPermission, null));
    }

    public function testCastUsing(): void
    {
        $caster = Permissions::castUsing([]);

        $this->assertInstanceOf(\Illuminate\Contracts\Database\Eloquent\CastsAttributes::class, $caster);

        $model = $this->createMock(\Illuminate\Database\Eloquent\Model::class);

        $permissions = new Permissions;
        $permissions->grant($this->testPermission, '1');

        $json = $permissions->toJson();

        $result = $caster->get($model, 'permissions', $json, []);
        $this->assertInstanceOf(Permissions::class, $result);
        $this->assertTrue($result->has($this->testPermission, '1'));

        $setResult = $caster->set($model, 'permissions', $permissions, []);
        $this->assertEquals($json, $setResult);
    }
}
