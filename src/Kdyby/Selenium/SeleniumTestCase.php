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
use Tester\Assert;
use Tester\TestCase;



/**
 * @author Filip Procházka <filip@prochazka.su>
 *
 * @method open($destination)
 * @method openConcurrentSession($destination)
 */
abstract class SeleniumTestCase extends TestCase
{

	/**
	 * @var \Nette\DI\Container
	 */
	private $serviceLocator;

	/**
	 * @var SeleniumContext
	 */
	private $seleniumContext;



	public function __construct()
	{
		$this->seleniumContext = $this->createSeleniumContext();
		Bootstrap::registerPanel();
	}



	/**
	 * @return SeleniumContext
	 */
	protected function createSeleniumContext()
	{
		return new SeleniumContext();
	}



	protected function setOption($option, $value)
	{
		$this->seleniumContext->setOption($option, $value);
	}



	protected function setUp()
	{
		$this->serviceLocator = $this->createContainer();
		$this->seleniumContext->boot($this->serviceLocator, $this->createDatabase($this->serviceLocator));
	}



	/**
	 * @return BrowserSession
	 */
	public function getSession()
	{
		return $this->seleniumContext->getSession();
	}



	/**
	 * This method should create testing database and return it's name.
	 *
	 * @param Nette\DI\Container $container
	 * @return string
	 */
	protected function createDatabase(Nette\DI\Container $container)
	{

	}



	public function __call($name, $arguments)
	{
		return call_user_func_array(array($this->seleniumContext, $name), $arguments);
	}



	/**
	 * @param $destination
	 * @param array $args
	 */
	protected function assertDestination($destination, $args = array())
	{
		$appRequest = $this->seleniumContext->getSession()->presenter();
		Assert::true(empty($appRequest));
		Assert::same($destination, $appRequest->getPresenterName());

		foreach ($args as $param => $value) {
			Assert::true(array_key_exists($param, $appRequest->parameters));
			Assert::same($value, $appRequest->parameters[$param]);
		}
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

		return $configurator->createContainer();
	}



	public function runTest($name, array $args = array())
	{
		try {
			parent::runTest($name, $args);
			$this->seleniumContext->takeDown();

		} catch (\Exception $e) {
			if (Debugger::$browser && ($tracy = Debugger::log($e))) {
				exec(Debugger::$browser . ' ' . escapeshellarg($tracy));
			}

			$this->seleniumContext->takeDown();

			throw $e;
		}
	}

}
