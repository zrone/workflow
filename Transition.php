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

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Transition
{
    private $name;
    private $froms;
    private $tos;
    private $event;

    /**
     * @param string $name
     * @param string|string[] $froms
     * @param string|string[] $tos
     * @param array $event
     */
    public function __construct(string $name, $froms, $tos, $event)
    {
        $this->name = $name;
        $this->froms = (array)$froms;
        $this->tos = (array)$tos;
        $this->event = $event;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string[]
     */
    public function getFroms()
    {
        return $this->froms;
    }

    /**
     * @return string[]
     */
    public function getTos()
    {
        return $this->tos;
    }

    public function getEvent()
    {
        return $this->event;
    }
}
