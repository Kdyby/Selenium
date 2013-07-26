<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Selenium\Behat;

use Behat\Behat\Event\OutlineExampleEvent;
use Behat\Behat\Event\ScenarioEvent;
use Pd;
use Nette;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;



/**
 * Calls setUp() and tearDown()
 */
class SetupWrapper implements EventSubscriberInterface
{

	public static function getSubscribedEvents()
	{
		return array(
			'beforeScenario' => 'before',
			'afterScenario'  => 'after',
			'beforeOutlineExample' => 'before',
			'afterOutlineExample' => 'after',
		);
	}



	/**
	 * @param ScenarioEvent|OutlineExampleEvent $event
	 * @return void
	 */
	public function before($event)
	{
		$ctx = $event->getContext();
		if ($ctx instanceof BehatContext) {
			$ctx->setUp();
		}
	}



	/**
	 * @param ScenarioEvent|OutlineExampleEvent $event
	 * @return void
	 */
	public function after($event)
	{
		$ctx = $event->getContext();
		if ($ctx instanceof BehatContext) {
			$ctx->tearDown();
		}
	}

}
