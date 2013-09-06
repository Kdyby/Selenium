<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Selenium\Behat;

use Behat\Behat\Event\ScenarioEvent;
use Behat\Behat\Event\StepEvent;
use Nette;
use Nette\Diagnostics\Debugger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tester;



/**
 */
class TracyWatcher implements EventSubscriberInterface
{

	public static function getSubscribedEvents()
	{
		return array(
			'afterScenario' => 'after',
		);
	}

	public function after(ScenarioEvent $event)
	{
		if ($event->getResult() !== StepEvent::FAILED || !defined('TEMP_DIR')) {
			return;
		}

		echo "\nDumping error logs... \n";
		foreach (array_merge(glob(TEMP_DIR . '/error.log'), glob(TEMP_DIR . '/info.log'), glob(TEMP_DIR . '/critical.log')) as $logfile) {
			readfile($logfile);
			echo "\n";
		}

		if (!Debugger::$browser) {
			return;
		}

		echo "\nOpening bluescreens... \n";
		foreach (glob(TEMP_DIR . '/exception-*.html') as $file) {
			exec(Debugger::$browser . ' ' . escapeshellarg($file));
		}
	}

}


