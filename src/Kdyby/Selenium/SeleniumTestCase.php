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
use Tester\Assert;
use Tester\TestCase;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
abstract class SeleniumTestCase extends TestCase
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
	protected $options = array(
		self::OPTION_CONCURRENCY => self::DEFAULT_CONCURRENCY,
		self::OPTION_BROWSER => self::DEFAULT_BROWSER,
		self::OPTION_ROUTER => '%wwwDir%/index.php',
		self::OPTION_ENV_PREFIX => 'KDYBY',
	);



	public function __construct()
	{
		$testCaseRefl = new \ReflectionClass('\PHPUnit_Extensions_Selenium2TestCase');
		Debugger::$blueScreen->collapsePaths[] = dirname($testCaseRefl->getFileName());
		Debugger::$blueScreen->addPanel(array(new Diagnostics\Panel(), 'renderException'));
	}



	private function waitForSeleniumSlot()
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



	protected function setUp()
	{
		$this->windows = array();
		$this->waitForSeleniumSlot();

		$this->serviceLocator = $this->createContainer();
		$this->httpServer = new HttpServer();
		$this->httpServer->start($this->serviceLocator->expand($this->options[self::OPTION_ROUTER]), array(
			$this->options[self::OPTION_ENV_PREFIX] . '_DEBUG' => '0',
			$this->options[self::OPTION_ENV_PREFIX] . '_SELENIUM' => TRUE,
			$this->options[self::OPTION_ENV_PREFIX] . '_DATABASE' => $this->createDatabase(),
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
	 * This method should create testing database and return it's name.
	 */
	protected function createDatabase()
	{

	}



	/**
	 * @param string $destination
	 * @return BrowserSession
	 */
	protected function open($destination)
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
	 * @param $destination
	 * @param array $args
	 */
	protected function assertDestination($destination, $args = array())
	{
		$appRequest = $this->browserSession->presenter();
		Assert::true(empty($appRequest));
		Assert::same($destination, $appRequest->getPresenterName());

		foreach ($args as $param => $value) {
			Assert::true(array_key_exists($param, $appRequest->parameters));
			Assert::same($value, $appRequest->parameters[$param]);
		}
	}



	/**
	 * @return BrowserSession
	 */
	public function getSession()
	{
		return $this->browserSession;
	}



	/**
	 * This method is meant to be overridden, for you to configure your application
	 *
	 * @return \SystemContainer|\Nette\DI\Container
	 */
	protected function createContainer()
	{
		$configurator = new Nette\Config\Configurator();
		$configurator->setTempDirectory(TEMP_DIR);
		$container = $configurator->createContainer();
		TesterHelpers::setup();

		return $container;
	}



	private function takeDownSession()
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



	public function runTest($name, array $args = array())
	{
		try {
			parent::runTest($name, $args);
			$this->takeDownSession();

		} catch (\Exception $e) {
			if (Debugger::$browser && ($tracy = Debugger::log($e))) {
				exec(Debugger::$browser . ' ' . escapeshellarg($tracy));
			}

			$this->takeDownSession();

			throw $e;
		}
	}

}
