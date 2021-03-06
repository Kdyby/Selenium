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
	const OPTION_ENV_PREFIX = 'environmentPrefix';
	const OPTION_ENV_VARIABLES = 'environmentVariables';
	const OPTION_VIDEO_ENABLE = 'videoEnable';

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
	private $currentSession;

	/**
	 * @var BrowserSession[]
	 */
	private $windows = array();

	/** @var VideoRecorder */
	private $videoRecorder;

	/** @var Sitemap */
	private $sitemap;

	/**
	 * @var array
	 */
	private $options = array(
		self::OPTION_CONCURRENCY => self::DEFAULT_CONCURRENCY,
		self::OPTION_BROWSER => self::DEFAULT_BROWSER,
		self::OPTION_ROUTER => '%wwwDir%/index.php',
		self::OPTION_ENV_PREFIX => 'KDYBY',
		self::OPTION_ENV_VARIABLES => array(),
		self::OPTION_VIDEO_ENABLE => FALSE,
	);



	/**
	 * @param string[]|string $dirs Directories where to search
	 */
	public function __construct($sitemapDirs)
	{
		$this->sitemap = new Sitemap((array) $sitemapDirs);
	}



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
		$env = (array) $this->options[self::OPTION_ENV_VARIABLES] + array(
			$this->options[self::OPTION_ENV_PREFIX] . '_DEBUG'    => '0',
			$this->options[self::OPTION_ENV_PREFIX] . '_SELENIUM' => '1',
			$this->options[self::OPTION_ENV_PREFIX] . '_DATABASE' => $databaseName,
			$this->options[self::OPTION_ENV_PREFIX] . '_LOG_DIR'  => TEMP_DIR,
			$this->options[self::OPTION_ENV_PREFIX] . '_TEMP_DIR' => TEMP_DIR,
		);
		$this->httpServer->start($this->serviceLocator->expand($this->options[self::OPTION_ROUTER]), $env);

		$httpRequest = new Nette\Http\Request($this->httpServer->getUrl(), array(), array(), array(), array(), array(), 'GET');
		$this->serviceLocator->removeService('httpRequest');
		$this->serviceLocator->addService('httpRequest', $httpRequest);
		$this->sessionFactory = new SessionFactory($this->serviceLocator, $this->httpServer, $this->options);

		$this->currentSession = $this->sessionFactory->create();
		$this->currentSession->setContext($this);
		$this->windows[] = $this->currentSession;

		if ($this->options[self::OPTION_VIDEO_ENABLE]) {
			$this->videoRecorder = new VideoRecorder(TEMP_DIR);
			$this->videoRecorder->start();
		}
	}



	/**
	 * @param string $destination
	 * @return BrowserSession
	 */
	public function open($destination)
	{
		$args = func_get_args();

		return call_user_func_array(array($this->currentSession, 'presenter'), $args);
	}



	/**
	 * @param string $destination
	 * @return BrowserSession
	 */
	public function openConcurrentSession($destination = NULL)
	{
		$this->windows[] = $this->currentSession = $this->sessionFactory->create();
		$this->currentSession->setContext($this);

		if ($destination) {
			$args = func_get_args();
			call_user_func_array(array($this->currentSession, 'presenter'), $args);
		}

		return $this->currentSession;
	}



	public function changeDefaultSession(BrowserSession $session)
	{
		$this->currentSession = $session;
	}



	/**
	 * @return BrowserSession
	 */
	public function getSession()
	{
		return $this->currentSession;
	}



	/**
	 * @param Nette\Application\Request $appRequest
	 * @return string
	 */
	public function findPageObjectClass(Nette\Application\Request $appRequest = NULL)
	{
		if (!($appRequest = $appRequest ? : $this->getSession()->presenter())) {
			return NULL;
		}

		return $this->sitemap->findPageByPresenter($appRequest);
	}



	public function takeDown()
	{
		if ($this->httpServer === NULL) {
			return;
		}

		foreach ($this->windows as $window) {
			$window->stop();
		}

		if ($this->videoRecorder) {
			sleep(2); // give it some time before it disappears
			$this->videoRecorder->stop();
		}

		$this->windows = array();
		$this->httpServer->slaughter();
		$this->httpServer = NULL;
		$this->videoRecorder = NULL;
	}

}
