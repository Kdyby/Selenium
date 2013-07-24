<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Selenium\Diagnostics;

use Kdyby;
use Kdyby\Selenium\BrowserSession;
use Nette;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Panel
{

	/**
	 * @var array|\Exception[]
	 */
	private $ignoreExceptions = array();



	/**
	 * @param array|BrowserSession[] $windows
	 * @param \Exception $e
	 * @return array|bool
	 */
	public function renderException(array $windows, \Exception $e = NULL)
	{
		if (!$e || in_array($e, $this->ignoreExceptions, TRUE)) {
			return FALSE;
		}

		do {
			$this->ignoreExceptions[] = $e;
		} while ($e = $e->getPrevious());

		$screenshots = array();
		foreach ($windows as $session) {
			if (!$status = $session->captureDebugState()) {
				continue;
			}

			$screenshots[] = "<p><b>URL:</b> <a href='javascript:;'>{$status['url']}</a></p>" .
				"<div><img src='data:image/png;base64,{$status['screenshot']}' alt='screenshot' /></div>";
		}

		if (!$screenshots) {
			return FALSE;
		}

		return array(
			'tab' => 'Selenium',
			'panel' => '<div>' . implode("</div><br>\n<div>", $screenshots) . '</div>',
		);
	}

}
