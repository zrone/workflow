<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zrone\Component\Workflow\EventListener;

use Zrone\Component\Workflow\Transition;

class GuardExpression
{
    private $transition;
    private $expression;

    public function __construct(Transition $transition, string $expression)
    {
        $this->transition = $transition;
        $this->expression = $expression;
    }

    public function getTransition()
    {
        return $this->transition;
    }

    public function getExpression()
    {
        return $this->expression;
    }
}
