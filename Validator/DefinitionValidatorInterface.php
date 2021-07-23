<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zrone\Component\Workflow\Validator;

use Zrone\Component\Workflow\Definition;
use Zrone\Component\Workflow\Exception\InvalidDefinitionException;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
interface DefinitionValidatorInterface
{
    /**
     * @throws InvalidDefinitionException on invalid definition
     */
    public function validate(Definition $definition, string $name);
}
