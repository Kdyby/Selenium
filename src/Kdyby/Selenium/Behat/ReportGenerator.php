<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Selenium\Behat;

use Behat\Behat\Event\StepEvent;
use Nette;



/**
 * Generates HTML report with screenshots
 */
class ReportGenerator
{
	/** @var string */
	private $outDir;



	/**
	 * @param string $outDir
	 */
	public function __construct($outDir)
	{
		$this->outDir = $outDir;
	}



	/**
	 * @param array $report See ScreenshotMaker::$report
	 */
	public function generate(array $report)
	{
		$tpl = new Nette\Templating\FileTemplate(__DIR__ . '/../templates/report.latte');
		$tpl->registerFilter(new Nette\Latte\Engine());
		$tpl->registerHelperLoader('Nette\Templating\Helpers::loader');
		$tpl->registerHelper('resultName', function($result) {
			static $names = array(
				StepEvent::PASSED => 'passed',
				StepEvent::SKIPPED => 'skipped',
				StepEvent::PENDING => 'pending',
				StepEvent::UNDEFINED => 'undefined',
				StepEvent::FAILED => 'failed',
			);
			return isset($names[$result]) ? $names[$result] : '[unknown]';
		});
		$tpl->registerHelper('resultClass', function($result) {
			static $names = array(
				StepEvent::PASSED => 'passed',
				StepEvent::SKIPPED => 'skipped',
				StepEvent::PENDING => 'pending',
				StepEvent::UNDEFINED => 'undefined',
				StepEvent::FAILED => 'failed',
			);
			return isset($names[$result]) ? $names[$result] : '[unknown]';
		});

		// render
		$tpl->report = $report;
		file_put_contents($this->outDir . '/index.html', $tpl->__toString());
	}

}


