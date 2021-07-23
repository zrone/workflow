<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zrone\Component\Workflow\MarkingStore;

use Zrone\Component\Workflow\Marking;

/**
 * MarkingStoreInterface is the interface between the Workflow Component and a
 * plain old PHP object: the subject.
 *
 * It converts the Marking into something understandable by the subject and vice
 * versa.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
interface MarkingStoreInterface
{
    /**
     * Gets a Marking from a subject.
     *
     * @return Marking The marking
     */
    public function getMarking(object $subject);

    /**
     * Sets a Marking to a subject.
     */
    public function setMarking(object $subject, Marking $marking, array $context = []);
}
