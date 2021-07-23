<?php

namespace Zrone\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Zrone\Component\Workflow\Transition;

class TransitionTest extends TestCase
{
    public function testConstructor()
    {
        $transition = new Transition('name', 'a', 'b');

        $this->assertSame('name', $transition->getName());
        $this->assertSame(['a'], $transition->getFroms());
        $this->assertSame(['b'], $transition->getTos());
    }
}
