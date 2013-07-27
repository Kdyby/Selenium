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
use Kdyby\Selenium\Bootstrap;
use Kdyby\Selenium\BrowserSession;
use Kdyby\Selenium\ComponentElement;
use Kdyby\Selenium\HttpServer;
use Kdyby\Selenium\PageElement;
use Kdyby\Selenium\SeleniumContext;



/**
 * Base context used in BDD tests
 *
 * Dispatches commands to *page objects*.
 *
 * Also defines dome basic steps.
 */
class BehatContext extends Behat\Behat\Context\BehatContext
{

	/**
	 * @var Nette\DI\Container
	 */
	protected $serviceLocator;

	/**
	 * @var SeleniumContext
	 */
	protected $seleniumContext;

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
		$this->seleniumContext = new SeleniumContext();
		Bootstrap::registerPanel();
	}


	public function __destruct()
	{
		$this->tearDown();
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



	/**
	 * This method should create testing database and return it's name.
	 *
	 * @param Nette\DI\Container $container
	 * @return string
	 */
	protected function createDatabase(Nette\DI\Container $container) {

	}



	/**
	 * Like in a test case, executed before each scenario
	 *
	 * @return void
	 */
	public function setUp()
	{
		if ($this->serviceLocator) return; // already initialized

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
	 * Like in a test case, executed after each scenario
	 *
	 * @return void
	 */
	public function tearDown()
	{
		$this->seleniumContext->takeDown();
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



	/**
	 * @Then /^u?vidím (komponentu|stránku) (.+)$/
	 */
	public function willSeeComponent($type, $componentName)
	{
		$page = $this->stack[0];

		switch ($type) {
			case 'komponentu':
				if ($page instanceof ComponentElement) { // mam primo komponentu
					Assert::same($componentName, $page->getName());
					//if ($componentName === $page->getName()) return; // ok

				} elseif ($page instanceof PageElement) {
					if ( ! $page->tryFindComponent($componentName)) Assert::fail("Komponenta '$componentName' nenalezena");
					// jinak ok

				} else {
					Assert::fail("Expected a component '$componentName', but got something else: " . get_class($page));

				}
				break;

			case 'stránku':
				if ($page instanceof ComponentElement) Assert::fail("Expected page, component");
				Assert::same($componentName, $page->getName());
				break;

			default:
				throw new \InvalidArgumentException;
		}
	}



	/**
	 * @Then /^neu?vidím (komponentu|stránku) (.+)$/
	 */
	public function wontSeeComponent($type, $componentName)
	{
		$page = $this->stack[0];

		switch ($type) {
			case 'komponentu':
				if ($page instanceof ComponentElement) { // mam primo komponentu
					Assert::false($componentName === $page->getName());

				} elseif ($page instanceof PageElement) {
					if ($page->tryFindComponent($componentName)) Assert::fail("Komponenta '$componentName' byla nalezena");

				} else throw new \Nette\InvalidStateException;
				break;

			case 'stránku':
				if ($page instanceof ComponentElement) Assert::fail("Expected page, component");
				Assert::false($componentName === $page->getName());
				break;

			default:
				throw new \InvalidArgumentException;
		}
	}



	/**
	 * @Given /^kliknu na tlačítko (.+)$/
	 */
	public function clickButton($text)
	{
		$this->session->byXPath("//input[@type='submit'][@value='$text']")->click();
		// TODO: where to go? Need new PageObject
	}



	/**
	 * @When /^vyplním (['"]?)(.+)\1 do pole (.+)$/
	 */
	public function fillForm($quote, $text, $filedName)
	{
		$el = NULL;

		if ( ! $el) try { // by name
			$el = $this->getSession()->byName($filedName);
		} catch(\Exception $e) {}

		if ( ! $el) try { // by label
			$label = $this->getSession()->byXPath("//label[./text()[contains(.,'$filedName')]]");
			if ($label) {
				$for = $label->attribute('for');
				$el = $this->getSession()->byId($for);
			}
		} catch (\Exception $e) {}

		if ( ! $el) throw new \RuntimeException("Form field not found");

		// TODO: radeji hosiplanovo PageElement::fillForm, ktere je univerzalni na vsechny mozne inputy
		$el->clear();
		$el->value($text);
	}

}
