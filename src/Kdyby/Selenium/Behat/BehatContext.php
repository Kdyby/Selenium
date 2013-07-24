<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Selenium\Behat;

use Nette;
use Behat;
use Tester\Assert;
use Kdyby\Selenium\BrowserSession;
use Kdyby\Selenium\HttpServer;
use Kdyby\Selenium\PageElement;


class BehatContext extends Behat\Behat\Context\BehatContext
{

	/**
	 * @var Nette\DI\Container
	 */
	protected $serviceLocator;

	/**
	 * @var BrowserSession
	 */
	protected $session;

	/**
	 * @var DummyTestCase
	 */
	private $tc;

	/**
	 * @var HttpServer
	 */
	private $httpServer;

	/**
	 * @var PageElement[] indexed by class name
	 */
	private $pageObjects = array();

	/**
	 * @var PageElement
	 */
	private $stack = array();



	public function __construct()
	{
		$this->serviceLocator = Nette\Environment::getContext(); // there is no better way
	}



	public function __destruct()
	{
		if ($this->tc) {
			$this->tc->tearDown();
			$this->tc = NULL;
		}

		if ($this->session) {
			$this->session->stop();
			$this->session = NULL;
		}
	}



	public function init()
	{
		$this->tc = new DummyTestCase;
		$this->tc->setUp();
		$this->session = $this->tc->getSession();
	}



	/*****************  delegating page object  *****************j*d*/



	/**
	 * Execute an action on current page object
	 *
	 * @param string $className
	 * @param string $methodName
	 * @param array $values
	 * @throws Nette\InvalidStateException
	 */
	public function run($className, $methodName, $values)
	{
		if ($this->stack[0] instanceof $className) { // current object is good enough
			$page = $this->stack[0];

		} elseif ($page = $this->getPageObject($className)) {
			// nothing
			// is this valid anyway? because we're accessing an object which is no longer current in stack

		} else {
			throw new \Nette\InvalidStateException("Not found page object of class $className");

		}

		// dispatch
		$ret = call_user_func_array(array($page, $methodName), $values);


		if ($ret === $this->stack[0]) { // the current page object -> don't care

		} elseif ($ret instanceof PageElement) { // another page object back
			$this->pushPage($ret);

		} else { // something else
			$x = 1;
		}

	}



	/**
	 * New page spotted, add to stack
	 * @param $page
	 */
	protected function pushPage($page)
	{
		$this->registerPageObject($page);
		array_unshift($this->stack, $page);
	}



	protected function popPage()
	{
		array_shift($this->stack);
	}


	public function getPageObject($className)
	{
		return $this->pageObjects[$className];
	}



	protected function registerPageObject($page)
	{
		$this->pageObjects[get_class($page)] = $page;
	}



	/********************** basic definitions **********************/


}
