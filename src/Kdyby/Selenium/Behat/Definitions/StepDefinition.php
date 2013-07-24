<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Selenium\Behat\Definitions;

use Behat;
use Behat\Behat\Context\ContextInterface;
use Kdyby\Selenium\Behat\BehatContext;



/**
 * Custom step definition which passes the execution to context
 */
abstract class StepDefinition extends Behat\Behat\Definition\Annotation\Definition
{

	public function run(ContextInterface $context)
	{
		if (!$context instanceof BehatContext) return;

		list ($className, $methodName) = $this->getCallback();
		$context->run($className, $methodName, $this->getValues());
	}

}
