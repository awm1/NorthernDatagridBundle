<?php

namespace APY\DataGridBundle\Tests\Grid\Action;

use APY\DataGridBundle\Grid\Action\RowAction;
use APY\DataGridBundle\Grid\Row;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RowActionTest extends TestCase
{
    private string $title = 'title';

    private string $route = 'vendor.bundle.controller.route_name';

    private bool $confirm = true;

    private string $target = '_parent';

    private array $attributes = ['foo' => 'foo', 'bar' => 'bar'];

    private string $role = 'ROLE_FOO';

    private array $callbacks = [];

    private RowAction $rowAction;

    private MockObject $row;

    protected function setUp(): void
    {
        $this->rowAction = new RowAction(
            $this->title, $this->route, $this->confirm, $this->target, $this->attributes, $this->role
        );
        $this->row       = $this->createMock(Row::class);
    }

    public function testGetTitle(): void
    {
        $title = 'foo_title';
        $this->rowAction->setTitle($title);

        $this->assertEquals($title, $this->rowAction->getTitle());
    }

    public function testGetRoute(): void
    {
        $route = 'another_vendor.another_bundle.controller.route_name';
        $this->rowAction->setRoute($route);

        $this->assertEquals($route, $this->rowAction->getRoute());
    }

    public function testGetConfirmation(): void
    {
        $confirm = true;
        $this->rowAction->setConfirm($confirm);

        $this->assertTrue($this->rowAction->getConfirm());
    }

    public function testDefaultConfirmMessage(): void
    {
        $this->assertIsString($this->rowAction->getConfirmMessage());
    }

    public function testGetConfirmMessage(): void
    {
        $message = 'A bar test message';
        $this->rowAction->setConfirmMessage($message);

        $this->assertEquals($message, $this->rowAction->getConfirmMessage());
    }

    public function testGetTarget(): void
    {
        $target = '_blank';
        $this->rowAction->setTarget($target);

        $this->assertEquals($target, $this->rowAction->getTarget());
    }

    public function testGetColumn(): void
    {
        $col = 'bar';
        $this->rowAction->setColumn($col);

        $this->assertEquals($col, $this->rowAction->getColumn());
    }

    public function testAddRouteParameters(): void
    {
        $stringParam = 'aParam';
        $this->rowAction->addRouteParameters($stringParam);

        $string2Param = 'secondStringParam';
        $this->rowAction->addRouteParameters($string2Param);

        $intKeyParam = [1 => 'paramOne', 2 => 'paramTwo'];
        $this->rowAction->addRouteParameters($intKeyParam);

        $associativeParam = ['foo' => 'fooParam', 'bar' => 'barParam'];
        $this->rowAction->addRouteParameters($associativeParam);

        $this->assertSame(
            array_merge([0 => $stringParam, 1 => $string2Param, 2 => $intKeyParam[1], 3 => $intKeyParam[2]], $associativeParam),
            $this->rowAction->getRouteParameters()
        );
    }

    public function testSetStringRouteParameters(): void
    {
        $param = 'param';
        $this->rowAction->setRouteParameters($param);

        $this->assertSame([0 => $param], $this->rowAction->getRouteParameters());
    }

    public function testGetRouteParameters(): void
    {
        $params = ['foo' => 'foo_param', 'bar' => 'bar_param'];
        $this->rowAction->setRouteParameters($params);

        $this->assertEquals($params, $this->rowAction->getRouteParameters());
    }

    public function testGetRouteParametersMapping(): void
    {
        $routeParamKey      = 'foo.bar.city';
        $routeParamValue    = 'cityId';
        $routeParamsMapping = [$routeParamKey => $routeParamValue];
        $this->rowAction->setRouteParametersMapping($routeParamsMapping);

        $this->assertEquals('cityId', $this->rowAction->getRouteParametersMapping('foo.bar.city'));
        $this->assertNull($this->rowAction->getRouteParametersMapping('foo.bar.country'));
    }

    public function testSetAttributes(): void
    {
        $attr = ['foo' => 'foo_val', 'bar' => 'bar_val'];
        $this->rowAction->setAttributes($attr);

        $this->assertSame($attr, $this->rowAction->getAttributes());
    }

    public function testAddAttribute(): void
    {
        $attrName = 'foo1';
        $attrVal  = 'foo_val1';
        $this->rowAction->addAttribute($attrName, $attrVal);

        $this->assertSame(
            array_merge($this->attributes, [$attrName => $attrVal]),
            $this->rowAction->getAttributes()
        );
    }

    public function testGetAttributes(): void
    {
        $this->assertEquals($this->attributes, $this->rowAction->getAttributes());
    }

    public function testGetRole(): void
    {
        $role = 'ROLE_SUPER_ADMIN';
        $this->rowAction->setRole($role);

        $this->assertEquals($role, $this->rowAction->getRole());
    }

    public function testManipulateRender(): void
    {
        $callback1 = function () {
            return 1;
        };
        $callback2 = function () {
            return 2;
        };

        $this->rowAction->manipulateRender($callback1);
        $this->rowAction->manipulateRender($callback2);

        $this->assertSame([$callback1, $callback2], $this->rowAction->getCallbacks());
    }

    public function testAddManipulateRender(): void
    {
        $this->addCalbacks();
        $this->assertSame($this->callbacks, $this->rowAction->getCallbacks());
    }

    private function addCalbacks(): void
    {
        $callback1 = function ($action, $row) {
            /** @var $row Row */
            if ($row->getField('foo') == 0) {
                return;
            }

            return $action;
        };

        $this->rowAction->addManipulateRender($callback1);

        $callback2 = function ($action, $row) {
            /** @var $row Row */
            if ($row->getField('bar') == 0) {
                return;
            }

            return $action;
        };

        $this->rowAction->addManipulateRender($callback2);

        $this->callbacks = [$callback1, $callback2];
    }

    public function testExecuteAllCallbacks(): void
    {
        $this->addCalbacks();

        $this->row
            ->expects($this->exactly(2))
            ->method('getField')
            ->with($this->logicalOr('foo', 'bar'))
            ->willReturn(1);

        $this->assertEquals($this->rowAction, $this->rowAction->render($this->row));
    }

    public function testStopOnFirstCallbackFailed(): void
    {
        $this->addCalbacks();

        $this->row
            ->expects($this->exactly(1))
            ->method('getField')
            ->with('foo')
            ->willReturn(0);

        $this->assertEquals(null, $this->rowAction->render($this->row));
    }

    public function testSetEnabled(): void
    {
        $enabled = true;
        $this->rowAction->setEnabled($enabled);

        $this->assertSame($enabled, $this->rowAction->getEnabled());
    }

    public function testGetEnabled(): void
    {
        $enabled = true;
        $this->rowAction->setEnabled($enabled);

        $this->assertTrue($this->rowAction->getEnabled());
    }
}
