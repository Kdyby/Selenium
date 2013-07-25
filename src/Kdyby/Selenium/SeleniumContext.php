<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Selenium;

use Kdyby;
use Nette;
use Nette\Diagnostics\Debugger;
use Tester\Helpers as TesterHelpers;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class SeleniumContext extends Nette\Object
{

	const DEFAULT_CONCURRENCY = 5;
	const DEFAULT_BROWSER = 'firefox';

	const OPTION_CONCURRENCY = 'seleniumConcurrency';
	const OPTION_BROWSER = 'browserName';
	const OPTION_ROUTER = 'routerPath';
	const OPTION_ENV_PREFIX = 'KDYBY';

	/**
	 * @var HttpServer
	 */
	private $httpServer;

	/**
	 * @var \Nette\DI\Container
	 */
	private $serviceLocator;

	/**
	 * @var SessionFactory
	 */
	private $sessionFactory;

	/**
	 * @var BrowserSession
	 */
	private $browserSession;

	/**
	 * @var BrowserSession[]
	 */
	private $windows = array();

	/**
	 * @var array
	 */
	private $options = array(
		self::OPTION_CONCURRENCY => self::DEFAULT_CONCURRENCY,
		self::OPTION_BROWSER => self::DEFAULT_BROWSER,
		self::OPTION_ROUTER => '%wwwDir%/index.php',
		self::OPTION_ENV_PREFIX => 'KDYBY',
	);



	public function setOption($option, $value)
	{
		$this->options[$option] = $value;
	}



	public function waitForSeleniumSlot()
	{
		static $lock;
		foreach (new \InfiniteIterator(new \ArrayIterator(range(1, $this->options[self::OPTION_CONCURRENCY] + 1))) as $i) {
			if ($i === $this->options[self::OPTION_CONCURRENCY] + 1) {
				sleep(1);
				continue;
			}

			$lock = fopen(dirname(TEMP_DIR) . '/selenium-' . $i . '.lock', 'w');
			if (flock($lock, LOCK_EX | LOCK_NB, $wouldBlock) && !$wouldBlock) {
				break;
			}

			@fclose($lock);
			unset($lock);
		}
	}



	public function boot(Nette\DI\Container $container, $databaseName)
	{
		$this->windows = array();
		$this->waitForSeleniumSlot();

		$this->serviceLocator = $container;
		TesterHelpers::setup(); // ensure error & exception helpers are registered

		$this->httpServer = new HttpServer();
		$this->httpServer->start($this->serviceLocator->expand($this->options[self::OPTION_ROUTER]), array(
			$this->options[self::OPTION_ENV_PREFIX] . '_DEBUG' => '0',
			$this->options[self::OPTION_ENV_PREFIX] . '_SELENIUM' => '1',
			$this->options[self::OPTION_ENV_PREFIX] . '_DATABASE' => $databaseName,
			$this->options[self::OPTION_ENV_PREFIX] . '_LOG_DIR' => TEMP_DIR,
			$this->options[self::OPTION_ENV_PREFIX] . '_TEMP_DIR' => TEMP_DIR,
		));

		$httpRequest = new Nette\Http\Request($this->httpServer->getUrl(), array(), array(), array(), array(), array(), 'GET');
		$this->serviceLocator->addService('httpRequest', $httpRequest);
		$this->sessionFactory = new SessionFactory($this->serviceLocator, $this->httpServer, $this->options);

		$this->browserSession = $this->sessionFactory->create();
		$this->windows[] = $this->browserSession;
	}



	/**
	 * @param string $destination
	 * @return BrowserSession
	 */
	public function open($destination)
	{
		$args = func_get_args();

		return call_user_func_array(array($this->browserSession, 'presenter'), $args);
	}



	/**
	 * @param string $destination
	 * @return BrowserSession
	 */
	public function openConcurrentSession($destination)
	{
		$this->windows[] = $copy = $this->sessionFactory->create();

		$args = func_get_args();

		return call_user_func_array(array($copy, 'presenter'), $args);
	}



	/**
	 * @return BrowserSession
	 */
	public function getSession()
	{
		return $this->browserSession;
	}



	public function takeDown()
	{
		if ($this->httpServer === NULL) {
			return;
		}

		foreach ($this->windows as $window) {
			$window->stop();
		}
		$this->windows = array();
		$this->httpServer->slaughter();
		$this->httpServer = NULL;
	}

}
