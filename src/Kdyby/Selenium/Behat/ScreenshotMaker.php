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
use Behat\Behat\Event\StepEvent;
use Behat\Behat\Event\SuiteEvent;
use Nette;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;



/**
 * Takes screenshots of each step and generates a html report afterwards
 *
 * Can be disabled by environment variable MAKE_SCREENSHOTS=0
 */
class ScreenshotMaker implements EventSubscriberInterface
{

	/** @var bool */
	private $enabled;

	/** @var string Where to store images */
	private $outDir;

	/** @var string Root directory of tests, to shorten filenames */
	private $rootDir;

	/** @var string Title of the current feature (file) being executed */
	private $currentFeatureTitle;

	/** @var string Title of the current scenario being executed */
	private $currentScenarioTitle;

	/** @var array [ { feature, scenario, result, steps: [ { text, file, result, exception } ] } ] */
	private $report = array();

	/** @var array Report being generated at the moment */
	private $currentReport;



	/**
	 * @param string $outDir Where to store images
	 * @param string $rootDir Root directory of tests, to shorten filenames
	 */
	public function __construct($outDir, $rootDir)
	{
		$enabled = getenv('MAKE_SCREENSHOTS');
		$this->enabled = ($enabled === FALSE) || (bool) $enabled; // false - not set, i.e. default
		if (!$this->enabled) return;

		if (!is_dir($outDir)) mkdir($outDir, 0777, TRUE);

		$this->outDir = $outDir;
		$this->rootDir = $rootDir;
	}



	public static function getSubscribedEvents()
	{
		return array(
			'beforeSuite' => 'beforeSuite',
			'afterSuite' => 'afterSuite',
			'beforeScenario' => 'beforeScenario',
			'afterScenario' => 'afterScenario',
			'beforeOutlineExample' => 'beforeScenario',
			'afterOutlineExample' => 'afterScenario',
			'afterStep' => 'afterStep',
		);
	}



	public function beforeSuite(SuiteEvent $event)
	{
		if (!$this->enabled) return;

		// nothing yet
	}



	public function afterSuite(SuiteEvent $event)
	{
		if (!$this->enabled) return;

		$generator = new ReportGenerator($this->outDir);
		$generator->generate($this->report);
	}


	/**
	 * @param ScenarioEvent|OutlineExampleEvent $event
	 */
	public function beforeScenario($event)
	{
		if (!$this->enabled) return;

		if ($event instanceof ScenarioEvent) {
			$this->currentScenarioTitle = $event->getScenario()->getTitle();
			$this->currentFeatureTitle = $event->getScenario()->getFeature()->getTitle();

		} elseif ($event instanceof OutlineExampleEvent) {
			$this->currentScenarioTitle = $event->getOutline()->getTitle();
			$this->currentFeatureTitle = $event->getOutline()->getFeature()->getTitle();

		} else {
			throw new \InvalidArgumentException;
		}

		$this->currentReport = array( // empty report
			'feature' => $this->currentFeatureTitle,
			'scenario' => $this->currentScenarioTitle,
			'steps' => array(),
		);
	}



	/**
	 * @param ScenarioEvent|OutlineExampleEvent $event
	 */
	public function afterScenario($event)
	{
		if (!$this->enabled) return;

		$this->currentReport['result'] = $event->getResult();
		$this->report[] = $this->currentReport;
	}



	public function afterStep(StepEvent $event)
	{
		if (!$this->enabled) return;

		$file = $event->getStep()->getParent()->getFeature()->getFile();
		$line = $event->getStep()->getLine();
		$filename = str_replace('/', '_', ltrim(str_replace($this->rootDir, '', $file), '/')) . '_' . $line . '.png';

		/** @var BehatContext $ctx */
		$ctx = $event->getContext();
		$screenData = $ctx->getSession()->currentScreenshot();

		file_put_contents($this->outDir . '/' . $filename, $screenData);

		$this->currentReport['steps'][] = array(
			'text' => $event->getStep()->getText(),
			'file' => $filename,
			'result' => $event->getResult(),
			'exception' => ($ex = $event->getException()) ? $ex->getMessage() : NULL,
		);
	}
	
}


