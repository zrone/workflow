<?php

namespace Zrone\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Zrone\Component\Workflow\Definition;
use Zrone\Component\Workflow\Exception\InvalidArgumentException;
use Zrone\Component\Workflow\MarkingStore\MarkingStoreInterface;
use Zrone\Component\Workflow\Registry;
use Zrone\Component\Workflow\SupportStrategy\WorkflowSupportStrategyInterface;
use Zrone\Component\Workflow\Workflow;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class RegistryTest extends TestCase
{
    private $registry;

    protected function setUp(): void
    {
        $this->registry = new Registry();

        $this->registry->addWorkflow(new Workflow(new Definition([], []), $this->createMock(MarkingStoreInterface::class), $this->createMock(EventDispatcherInterface::class), 'workflow1'), $this->createWorkflowSupportStrategy(Subject1::class));
        $this->registry->addWorkflow(new Workflow(new Definition([], []), $this->createMock(MarkingStoreInterface::class), $this->createMock(EventDispatcherInterface::class), 'workflow2'), $this->createWorkflowSupportStrategy(Subject2::class));
        $this->registry->addWorkflow(new Workflow(new Definition([], []), $this->createMock(MarkingStoreInterface::class), $this->createMock(EventDispatcherInterface::class), 'workflow3'), $this->createWorkflowSupportStrategy(Subject2::class));
    }

    protected function tearDown(): void
    {
        $this->registry = null;
    }

    public function testHasWithMatch()
    {
        $this->assertTrue($this->registry->has(new Subject1()));
    }

    public function testHasWithoutMatch()
    {
        $this->assertFalse($this->registry->has(new Subject1(), 'nope'));
    }

    public function testGetWithSuccess()
    {
        $workflow = $this->registry->get(new Subject1());
        $this->assertInstanceOf(Workflow::class, $workflow);
        $this->assertSame('workflow1', $workflow->getName());

        $workflow = $this->registry->get(new Subject1(), 'workflow1');
        $this->assertInstanceOf(Workflow::class, $workflow);
        $this->assertSame('workflow1', $workflow->getName());

        $workflow = $this->registry->get(new Subject2(), 'workflow2');
        $this->assertInstanceOf(Workflow::class, $workflow);
        $this->assertSame('workflow2', $workflow->getName());
    }

    public function testGetWithMultipleMatch()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Too many workflows (workflow2, workflow3) match this subject (Symfony\Component\Workflow\Tests\Subject2); set a different name on each and use the second (name) argument of this method.');
        $w1 = $this->registry->get(new Subject2());
        $this->assertInstanceOf(Workflow::class, $w1);
        $this->assertSame('workflow1', $w1->getName());
    }

    public function testGetWithNoMatch()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to find a workflow for class "stdClass".');
        $w1 = $this->registry->get(new \stdClass());
        $this->assertInstanceOf(Workflow::class, $w1);
        $this->assertSame('workflow1', $w1->getName());
    }

    public function testAllWithOneMatchWithSuccess()
    {
        $workflows = $this->registry->all(new Subject1());
        $this->assertIsArray($workflows);
        $this->assertCount(1, $workflows);
        $this->assertInstanceOf(Workflow::class, $workflows[0]);
        $this->assertSame('workflow1', $workflows[0]->getName());
    }

    public function testAllWithMultipleMatchWithSuccess()
    {
        $workflows = $this->registry->all(new Subject2());
        $this->assertIsArray($workflows);
        $this->assertCount(2, $workflows);
        $this->assertInstanceOf(Workflow::class, $workflows[0]);
        $this->assertInstanceOf(Workflow::class, $workflows[1]);
        $this->assertSame('workflow2', $workflows[0]->getName());
        $this->assertSame('workflow3', $workflows[1]->getName());
    }

    public function testAllWithNoMatch()
    {
        $workflows = $this->registry->all(new \stdClass());
        $this->assertIsArray($workflows);
        $this->assertCount(0, $workflows);
    }

    private function createWorkflowSupportStrategy($supportedClassName)
    {
        $strategy = $this->createMock(WorkflowSupportStrategyInterface::class);
        $strategy->expects($this->any())->method('supports')
            ->willReturnCallback(function ($workflow, $subject) use ($supportedClassName) {
                return $subject instanceof $supportedClassName;
            });

        return $strategy;
    }
}

class Subject1
{
}
class Subject2
{
}
