<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zrone\Component\Workflow;

use Zrone\Component\Workflow\Event\AnnounceEvent;
use Zrone\Component\Workflow\Event\CompletedEvent;
use Zrone\Component\Workflow\Event\EnteredEvent;
use Zrone\Component\Workflow\Event\EnterEvent;
use Zrone\Component\Workflow\Event\GuardEvent;
use Zrone\Component\Workflow\Event\LeaveEvent;
use Zrone\Component\Workflow\Event\TransitionEvent;
use Zrone\Component\Workflow\Exception\LogicException;
use Zrone\Component\Workflow\Exception\NotEnabledTransitionException;
use Zrone\Component\Workflow\Exception\UndefinedTransitionException;
use Zrone\Component\Workflow\MarkingStore\MarkingStoreInterface;
use Zrone\Component\Workflow\MarkingStore\MethodMarkingStore;
use Zrone\Component\Workflow\Metadata\MetadataStoreInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Carlos Pereira De Amorim <carlos@shauri.fr>
 */
class Workflow implements WorkflowInterface
{
    public const DISABLE_LEAVE_EVENT = 'workflow_disable_leave_event';
    public const DISABLE_TRANSITION_EVENT = 'workflow_disable_transition_event';
    public const DISABLE_ENTER_EVENT = 'workflow_disable_enter_event';
    public const DISABLE_ENTERED_EVENT = 'workflow_disable_entered_event';
    public const DISABLE_COMPLETED_EVENT = 'workflow_disable_completed_event';
    public const DISABLE_ANNOUNCE_EVENT = 'workflow_disable_announce_event';

    public const DEFAULT_INITIAL_CONTEXT = ['initial' => true];

    private const DISABLE_EVENTS_MAPPING = [
        WorkflowEvents::LEAVE => self::DISABLE_LEAVE_EVENT,
        WorkflowEvents::TRANSITION => self::DISABLE_TRANSITION_EVENT,
        WorkflowEvents::ENTER => self::DISABLE_ENTER_EVENT,
        WorkflowEvents::ENTERED => self::DISABLE_ENTERED_EVENT,
        WorkflowEvents::COMPLETED => self::DISABLE_COMPLETED_EVENT,
        WorkflowEvents::ANNOUNCE => self::DISABLE_ANNOUNCE_EVENT,
    ];

    private $definition;
    private $markingStore;
    private $dispatcher;
    private $name;

    /**
     * When `null` fire all events (the default behaviour).
     * Setting this to an empty array `[]` means no events are dispatched (except the Guard Event).
     * Passing an array with WorkflowEvents will allow only those events to be dispatched plus
     * the Guard Event.
     *
     * @var array|string[]|null
     */
    private $eventsToDispatch = null;

    public function __construct(Definition $definition, MarkingStoreInterface $markingStore = null, EventDispatcherInterface $dispatcher = null, string $name = 'unnamed', array $eventsToDispatch = null)
    {
        $this->definition = $definition;
        $this->markingStore = $markingStore ?? new MethodMarkingStore();
        $this->dispatcher = $dispatcher;
        $this->name = $name;
        $this->eventsToDispatch = $eventsToDispatch;
    }

    /**
     * {@inheritdoc}
     */
    public function getMarking(object $subject, array $context = [])
    {
        $marking = $this->markingStore->getMarking($subject);

        if (!$marking instanceof Marking) {
            throw new LogicException(sprintf('The value returned by the MarkingStore is not an instance of "%s" for workflow "%s".', Marking::class, $this->name));
        }

        // check if the subject is already in the workflow
        if (!$marking->getPlaces()) {
            if (!$this->definition->getInitialPlaces()) {
                throw new LogicException(sprintf('The Marking is empty and there is no initial place for workflow "%s".', $this->name));
            }
            foreach ($this->definition->getInitialPlaces() as $place) {
                $marking->mark($place);
            }

            // update the subject with the new marking
            $this->markingStore->setMarking($subject, $marking);

            if (!$context) {
                $context = self::DEFAULT_INITIAL_CONTEXT;
            }

            $this->entered($subject, null, $marking, $context);
        }

        // check that the subject has a known place
        $places = $this->definition->getPlaces();
        foreach ($marking->getPlaces() as $placeName => $nbToken) {
            if (!isset($places[$placeName])) {
                $message = sprintf('Place "%s" is not valid for workflow "%s".', $placeName, $this->name);
                if (!$places) {
                    $message .= ' It seems you forgot to add places to the current workflow.';
                }

                throw new LogicException($message);
            }
        }

        return $marking;
    }

    /**
     * {@inheritdoc}
     */
    public function can(object $subject, string $transitionName)
    {
        $transitions = $this->definition->getTransitions();
        $marking = $this->getMarking($subject);

        foreach ($transitions as $transition) {
            if ($transition->getName() !== $transitionName) {
                continue;
            }

            $transitionBlockerList = $this->buildTransitionBlockerListForTransition($subject, $marking, $transition);

            if ($transitionBlockerList->isEmpty()) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function buildTransitionBlockerList(object $subject, string $transitionName): TransitionBlockerList
    {
        $transitions = $this->definition->getTransitions();
        $marking = $this->getMarking($subject);
        $transitionBlockerList = null;

        foreach ($transitions as $transition) {
            if ($transition->getName() !== $transitionName) {
                continue;
            }

            $transitionBlockerList = $this->buildTransitionBlockerListForTransition($subject, $marking, $transition);

            if ($transitionBlockerList->isEmpty()) {
                return $transitionBlockerList;
            }

            // We prefer to return transitions blocker by something else than
            // marking. Because it means the marking was OK. Transitions are
            // deterministic: it's not possible to have many transitions enabled
            // at the same time that match the same marking with the same name
            if (!$transitionBlockerList->has(TransitionBlocker::BLOCKED_BY_MARKING)) {
                return $transitionBlockerList;
            }
        }

        if (!$transitionBlockerList) {
            throw new UndefinedTransitionException($subject, $transitionName, $this);
        }

        return $transitionBlockerList;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(object $subject, string $transitionName, array $context = [])
    {
        $marking = $this->getMarking($subject, $context);

        $transitionExist = false;
        $approvedTransitions = [];
        $bestTransitionBlockerList = null;

        foreach ($this->definition->getTransitions() as $transition) {
            if ($transition->getName() !== $transitionName) {
                continue;
            }

            $transitionExist = true;

            $tmpTransitionBlockerList = $this->buildTransitionBlockerListForTransition($subject, $marking, $transition);

            if ($tmpTransitionBlockerList->isEmpty()) {
                $approvedTransitions[] = $transition;
                continue;
            }

            if (!$bestTransitionBlockerList) {
                $bestTransitionBlockerList = $tmpTransitionBlockerList;
                continue;
            }

            // We prefer to return transitions blocker by something else than
            // marking. Because it means the marking was OK. Transitions are
            // deterministic: it's not possible to have many transitions enabled
            // at the same time that match the same marking with the same name
            if (!$tmpTransitionBlockerList->has(TransitionBlocker::BLOCKED_BY_MARKING)) {
                $bestTransitionBlockerList = $tmpTransitionBlockerList;
            }
        }

        if (!$transitionExist) {
            throw new UndefinedTransitionException($subject, $transitionName, $this, $context);
        }

        if (!$approvedTransitions) {
            throw new NotEnabledTransitionException($subject, $transitionName, $this, $bestTransitionBlockerList, $context);
        }

        foreach ($approvedTransitions as $transition) {
            $this->leave($subject, $transition, $marking, $context);

            $context = $this->transition($subject, $transition, $marking, $context);

            $this->enter($subject, $transition, $marking, $context);

            $this->markingStore->setMarking($subject, $marking, $context);

            $this->entered($subject, $transition, $marking, $context);

            $this->completed($subject, $transition, $marking, $context);

            $this->announce($subject, $transition, $marking, $context);
        }

        return $marking;
    }

    /**
     * {@inheritdoc}
     */
    public function getEnabledTransitions(object $subject)
    {
        $enabledTransitions = [];
        $marking = $this->getMarking($subject);

        foreach ($this->definition->getTransitions() as $transition) {
            $transitionBlockerList = $this->buildTransitionBlockerListForTransition($subject, $marking, $transition);
            if ($transitionBlockerList->isEmpty()) {
                $enabledTransitions[] = $transition;
            }
        }

        return $enabledTransitions;
    }

    public function getEnabledTransition(object $subject, string $name): ?Transition
    {
        $marking = $this->getMarking($subject);

        foreach ($this->definition->getTransitions() as $transition) {
            if ($transition->getName() !== $name) {
                continue;
            }
            $transitionBlockerList = $this->buildTransitionBlockerListForTransition($subject, $marking, $transition);
            if (!$transitionBlockerList->isEmpty()) {
                continue;
            }

            return $transition;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefinition()
    {
        return $this->definition;
    }

    /**
     * {@inheritdoc}
     */
    public function getMarkingStore()
    {
        return $this->markingStore;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadataStore(): MetadataStoreInterface
    {
        return $this->definition->getMetadataStore();
    }

    private function buildTransitionBlockerListForTransition(object $subject, Marking $marking, Transition $transition): TransitionBlockerList
    {
        foreach ($transition->getFroms() as $place) {
            if (!$marking->has($place)) {
                return new TransitionBlockerList([
                    TransitionBlocker::createBlockedByMarking($marking),
                ]);
            }
        }

        if (null === $this->dispatcher) {
            return new TransitionBlockerList();
        }

        $event = $this->guardTransition($subject, $marking, $transition);

        if ($event->isBlocked()) {
            return $event->getTransitionBlockerList();
        }

        return new TransitionBlockerList();
    }

    private function guardTransition(object $subject, Marking $marking, Transition $transition): ?GuardEvent
    {
        if (null === $this->dispatcher) {
            return null;
        }

        $event = new GuardEvent($subject, $marking, $transition, $this);

        $this->dispatcher->dispatch($event, WorkflowEvents::GUARD);

        return $event;
    }

    private function leave(object $subject, Transition $transition, Marking $marking, array $context = []): void
    {
        $places = $transition->getFroms();

        if ($this->shouldDispatchEvent(WorkflowEvents::LEAVE, $context)) {
            $event = new LeaveEvent($subject, $marking, $transition, $this, $context);

            $this->dispatcher->dispatch($event, WorkflowEvents::LEAVE);
        }

        foreach ($places as $place) {
            $marking->unmark($place);
        }
    }

    private function transition(object $subject, Transition $transition, Marking $marking, array $context): array
    {
        if (!$this->shouldDispatchEvent(WorkflowEvents::TRANSITION, $context)) {
            return $context;
        }

        $event = new TransitionEvent($subject, $marking, $transition, $this, $context);

        $this->dispatcher->dispatch($event, WorkflowEvents::TRANSITION);

        return $event->getContext();
    }

    private function enter(object $subject, Transition $transition, Marking $marking, array $context): void
    {
        $places = $transition->getTos();

        if ($this->shouldDispatchEvent(WorkflowEvents::ENTER, $context)) {
            $event = new EnterEvent($subject, $marking, $transition, $this, $context);

            $this->dispatcher->dispatch($event, WorkflowEvents::ENTER);
        }

        foreach ($places as $place) {
            $marking->mark($place);
        }
    }

    private function entered(object $subject, ?Transition $transition, Marking $marking, array $context): void
    {
        if (!$this->shouldDispatchEvent(WorkflowEvents::ENTERED, $context)) {
            return;
        }

        $event = new EnteredEvent($subject, $marking, $transition, $this, $context);

        $this->dispatcher->dispatch($event, WorkflowEvents::ENTERED);
    }

    private function completed(object $subject, Transition $transition, Marking $marking, array $context): void
    {
        if (!$this->shouldDispatchEvent(WorkflowEvents::COMPLETED, $context)) {
            return;
        }

        $event = new CompletedEvent($subject, $marking, $transition, $this, $context);

        $this->dispatcher->dispatch($event, WorkflowEvents::COMPLETED);
    }

    private function announce(object $subject, Transition $initialTransition, Marking $marking, array $context): void
    {
        if (!$this->shouldDispatchEvent(WorkflowEvents::ANNOUNCE, $context)) {
            return;
        }

        $event = new AnnounceEvent($subject, $marking, $initialTransition, $this, $context);

        $this->dispatcher->dispatch($event, WorkflowEvents::ANNOUNCE);
        // 执行自定义事件
        if (is_array($logicEvent = $initialTransition->getEvent()) && method_exists($logicEvent[0], $logicEvent[1])) {
            call_user_func(array(new $logicEvent[0], $logicEvent[1]), $event);
        }
    }

    private function shouldDispatchEvent(string $eventName, array $context): bool
    {
        if (null === $this->dispatcher) {
            return false;
        }

        if ($context[self::DISABLE_EVENTS_MAPPING[$eventName]] ?? false) {
            return false;
        }

        if (null === $this->eventsToDispatch) {
            return true;
        }

        if ([] === $this->eventsToDispatch) {
            return false;
        }

        return \in_array($eventName, $this->eventsToDispatch, true);
    }
}
