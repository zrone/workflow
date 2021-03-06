<?php

namespace Zrone\Component\Workflow\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Zrone\Component\Workflow\EventListener\AuditTrailListener;
use Zrone\Component\Workflow\MarkingStore\MethodMarkingStore;
use Zrone\Component\Workflow\Tests\Subject;
use Zrone\Component\Workflow\Tests\WorkflowBuilderTrait;
use Zrone\Component\Workflow\Workflow;

class AuditTrailListenerTest extends TestCase
{
    use WorkflowBuilderTrait;

    public function testItWorks()
    {
        $definition = $this->createSimpleWorkflowDefinition();

        $object = new Subject();

        $logger = new Logger();

        $ed = new EventDispatcher();
        $ed->addSubscriber(new AuditTrailListener($logger));

        $workflow = new Workflow($definition, new MethodMarkingStore(), $ed);

        $workflow->apply($object, 't1');

        $expected = [
            'Leaving "a" for subject of class "Symfony\Component\Workflow\Tests\Subject" in workflow "unnamed".',
            'Transition "t1" for subject of class "Symfony\Component\Workflow\Tests\Subject" in workflow "unnamed".',
            'Entering "b" for subject of class "Symfony\Component\Workflow\Tests\Subject" in workflow "unnamed".',
        ];

        $this->assertSame($expected, $logger->logs);
    }
}

class Logger extends AbstractLogger
{
    public $logs = [];

    public function log($level, $message, array $context = []): void
    {
        $this->logs[] = $message;
    }
}
