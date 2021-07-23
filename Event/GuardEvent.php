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

use Zrone\Component\Workflow\Marking;
use Zrone\Component\Workflow\Transition;
use Zrone\Component\Workflow\TransitionBlocker;
use Zrone\Component\Workflow\TransitionBlockerList;
use Zrone\Component\Workflow\WorkflowInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
final class GuardEvent extends Event
{
    private $transitionBlockerList;

    /**
     * {@inheritdoc}
     */
    public function __construct(object $subject, Marking $marking, Transition $transition, WorkflowInterface $workflow = null)
    {
        parent::__construct($subject, $marking, $transition, $workflow);

        $this->transitionBlockerList = new TransitionBlockerList();
    }

    public function isBlocked(): bool
    {
        return !$this->transitionBlockerList->isEmpty();
    }

    public function setBlocked(bool $blocked, string $message = null): void
    {
        if (!$blocked) {
            $this->transitionBlockerList->clear();

            return;
        }

        $this->transitionBlockerList->add(TransitionBlocker::createUnknown($message));
    }

    public function getTransitionBlockerList(): TransitionBlockerList
    {
        return $this->transitionBlockerList;
    }

    public function addTransitionBlocker(TransitionBlocker $transitionBlocker): void
    {
        $this->transitionBlockerList->add($transitionBlocker);
    }
}
