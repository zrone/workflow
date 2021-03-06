<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zrone\Component\Workflow\Event;

final class TransitionEvent extends Event
{
    public function setContext(array $context): void
    {
        $this->context = $context;
    }
}
