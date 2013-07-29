<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Selenium\Behat;

use Kdyby\Selenium\Element;
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



	public function __construct(array $options)
	{
		$this->seleniumContext = new SeleniumContext($options['sitemapDirs']);
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
		if ($this->getCurrentPage() instanceof $className) { // current object is good enough
			$page = $this->getCurrentPage();

		} elseif ($page = $this->getPageObject($className)) {
			// nothing
			// is this valid anyway? because we're accessing an object which is no longer current in stack

		} else {
			throw new \Nette\InvalidStateException("Not found page object of class $className");

		}

		// dispatch
		$ret = call_user_func_array(array($page, $methodName), $values);

		if ($ret === NULL) {
			if (!$className = $this->seleniumContext->findPageObjectClass()) {
				throw new \RuntimeException("Router didn't match the url " . $this->getSession()->url());
			}

			if (!$this->getCurrentPage() instanceof $className) {
				$this->pushPage(new $className($this->getSession()));
			}

		} elseif ($ret !== $this->getCurrentPage() && $ret instanceof PageElement) {
			$this->pushPage($ret);
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



	/**
	 * @return PageElement
	 */
	public function getCurrentPage()
	{
		return $this->stack[0];
	}



	/**
	 * @return array|PageElement[]
	 */
	public function getPageHistory()
	{
		return $this->stack;
	}



	public function getPageObject($className)
	{
		return !empty($this->pageObjects[$className]) ? $this->pageObjects[$className] : NULL;
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
		$page = $this->getCurrentPage();

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
		$page = $this->getCurrentPage();

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
	 * @Given /^kliknu na (tlačítko|odkaz) (.+)$/
	 */
	public function clickButton($type, $text)
	{
		switch ($type) {
			case 'tlačítko':
				$buttons = $this->getSession()->elements($this->getSession()->using('xpath')->value("//input[@type='submit'][@value='$text']"));
				if (!$buttons) {
					$buttons = $this->getSession()->elements($this->getSession()->using('xpath')->value("//button[@type='submit'][./text()[contains(.,'$text')]]"));
				}
				break;

			case 'odkaz':
				$buttons = $this->getSession()->elements($this->getSession()->using('partial link text')->value($text));
				break;
		}

		if ($button = reset($buttons)) {
			/** @var Element $button */
			$button->click();

		} else {
			Assert::fail("Button with title '$text' was not found");
		}

		if (!$className = $this->seleniumContext->findPageObjectClass()) {
			throw new \RuntimeException("Router didn't match the url " . $this->getSession()->url());
		}

		if (!$this->getCurrentPage() instanceof $className) {
			$this->pushPage(new $className($this->getSession()));
		}

		$this->getSession()->waitForAjax();
	}



	/**
	 * @When /^do pole (['"]?)(.+)\1 vyplním (.+)$/
	 */
	public function fillInputByLabel($x, $label, $value)
	{
		$el = NULL;

		try { // by label
			$labels = $this->getSession()->elements($this->getSession()->using('xpath')->value("//label[./text()[contains(.,'$label')]]"));
			if ($labelEl = reset($labels)) {
				$for = $labelEl->attribute('for');
				$el = $this->getSession()->byId($for);
			}
		} catch (\Exception $e) {
		}

		if (!$el) {
			try { // by name
				$inputs = $this->getSession()->elements($this->getSession()->using('name')->value($label));
				if ($inputs) {
					$el = reset($inputs);
				}

			} catch (\Exception $e) {
			}
		}

		if (!$el) {
			throw new \RuntimeException("Form field $label not found");
		}

		$el->clear();
		$el->value($value);
	}

	/**
	 * @When /^vyplním (['"]?)(.+)\1 do pole (.+)$/
	 */
	public function fillInputByLabel2($x, $value, $label)
	{
		$this->fillInputByLabel($x, $label, $value); // just different params order
	}


	/**
	 * @Given /^((?:za|od)škrtnu) (.*)$/
	 */
	public function willCheckOrUncheck($type, $checkbox)
	{
		$labels = $this->getSession()->elements($this->getSession()->using('xpath')->value("//label[./text()[contains(.,'$checkbox')]]"));
		if ($clickTarget = reset($labels)) {
			/** @var Element $clickTarget */

			$inputs = $this->getSession()->elements($this->getSession()->using('xpath')->value("//input[@type='checkbox']"));
			if (!$input = reset($inputs)) {
				try {
					if ($for = $clickTarget->attribute('for')) {
						$input = $this->getSession()->byId($for);
					}

				} catch (\Exception $e) {

				}
			}
		}

		if (empty($clickTarget) || empty($input)) {
			throw new \RuntimeException("Checkbox $checkbox not found");
		}

		switch ($type) {
			case 'zaškrtnu':
				if (!$input->selected()) {
					$clickTarget->click();
				}

				break;

			case 'odškrtnu':
				if ($input->selected()) {
					$clickTarget->click();
				}
				break;

			default:
				throw new \InvalidArgumentException("Unknown operation $type");
		}
	}

}
