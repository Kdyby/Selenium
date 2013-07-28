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



/**
 * @author Filip Procházka <filip@prochazka.su>
 *
 * @method string screenshot()
 */
class BrowserSession extends \PHPUnit_Extensions_Selenium2TestCase_Session
{

	/**
	 * @var \Nette\DI\Container
	 */
	private $serviceLocator;

	/**
	 * @var LinkGeneratorPresenter
	 */
	private $linkGenerator;

	/**
	 * @var \Nette\Http\UrlScript
	 */
	private $httpServerUrl;

	/**
	 * @var \Kdyby\Doctrine\Connection
	 */
	private $database;

	/**
	 * @var array
	 */
	private $parameters;

	/**
	 * @var \PHPUnit_Extensions_Selenium2TestCase_KeysHolder
	 */
	private $keysHolder;

	/**
	 * @var SeleniumContext
	 */
	private $context;



	public function __construct(
		Nette\DI\Container $serviceLocator,
		\PHPUnit_Extensions_Selenium2TestCase_Driver $driver,
		\PHPUnit_Extensions_Selenium2TestCase_URL $url,
		array $parameters,
		\PHPUnit_Extensions_Selenium2TestCase_Session_Timeouts $timeouts)
	{
		parent::__construct($driver, $url, $parameters['browserUrl'], $timeouts);

		$this->serviceLocator = $serviceLocator;
		$this->linkGenerator = new LinkGeneratorPresenter($this->serviceLocator);
		$this->httpServerUrl = $serviceLocator->getByType('Nette\Http\IRequest')->getUrl();
		$this->parameters = $parameters;

		$this->keysHolder = new \PHPUnit_Extensions_Selenium2TestCase_KeysHolder();

		$this->currentWindow()->maximize();
	}



	/**
	 * @param SeleniumContext $context
	 * @return BrowserSession
	 */
	public function setContext(SeleniumContext $context)
	{
		$this->context = $context;

		return $this;
	}



	/**
	 * @return SeleniumContext
	 */
	public function getContext()
	{
		return $this->context;
	}



	/**
	 * Set's this browser session instance as default
	 */
	public function makeDefault()
	{
		$this->context->changeDefaultSession($this);
	}



	/**
	 * @param $destination
	 * @param array $args
	 * @return BrowserSession|Nette\Application\Request
	 */
	public function presenter($destination = NULL, $args = array())
	{
		if ($destination !== NULL) {
			if (preg_match('~^\:?([a-z0-9_]+)(:[a-z0-9_]+)*\:?$~i', $destination)) {
				$args = array(0 => '//' . ltrim($destination, '/')) + func_get_args();
				$url = new Nette\Http\UrlScript(call_user_func_array(array($this->linkGenerator, 'link'), $args));

			} elseif (Nette\Utils\Validators::isUrl($destination)) {
				$url = new Nette\Http\UrlScript($destination);

			} else {
				$url = clone $this->httpServerUrl;
				$url->setPath($destination);
			}

			$this->url((string) $url);

			return $this;
		}

		$appRequest = NULL;
		$currentUrl = new Nette\Http\UrlScript($this->url());
		parse_str($currentUrl->query, $query);

		$router = $this->serviceLocator->getByType('Nette\Application\IRouter');
		/** @var Nette\Application\IRouter[]|Nette\Application\IRouter $router */

		if (!class_exists('Kdyby\Console\CliRouter')) {
			$appRequest = $router->match(new Nette\Http\Request($currentUrl, $query, array(), array(), array(), array(), 'GET'));

		} else {
			foreach ($router as $route) {
				if (($route instanceof Kdyby\Console\CliRouter) || ($route instanceof Nette\Application\Routers\CliRouter)) {
					continue;
				}

				if ($appRequest = $route->match(new Nette\Http\Request($currentUrl, $query, array(), array(), array(), array(), 'GET'))) {
					break;
				}
			}
		}

		return $appRequest;
	}



	/**
	 * @return BrowserSession
	 */
	public function waitForAjax()
	{
		$wasWaiting = FALSE;

		do {
			$finished = $this->execute(array(
				'script' => 'return jQuery.active == 0;',
				'args' => array(),
			));
			usleep(250);
			if (!$finished) {
				$wasWaiting = TRUE;
			}
		} while (!$finished);

		if ($wasWaiting) {
			usleep(500); // time for processing nette.ajax.js snippets

		} else {
			usleep(250); // I'm kind of super-paranoid
		}

		return $this;
	}



	/**
	 * @param string $name
	 * @return string
	 */
	public function specialKey($name)
	{
		return $this->keys($this->keysHolder->specialKey($name));
	}



	/**
	 * @param \PHPUnit_Extensions_Selenium2TestCase_ElementCriteria $criteria
	 * @throws \PHPUnit_Extensions_Selenium2TestCase_WebDriverException
	 * @return Element
	 */
	public function element(\PHPUnit_Extensions_Selenium2TestCase_ElementCriteria $criteria)
	{
		if (($all = $this->elements($criteria)) && ($element = reset($all))) {
			return $element;
		}

		throw new \PHPUnit_Extensions_Selenium2TestCase_WebDriverException("Element '{$criteria['value']}' not found using strategy '{$criteria['using']}'.");
	}



	/**
	 * @param \PHPUnit_Extensions_Selenium2TestCase_ElementCriteria $criteria
	 * @return \PHPUnit_Extensions_Selenium2TestCase_ElementCriteria[]
	 */
	public function elements(\PHPUnit_Extensions_Selenium2TestCase_ElementCriteria $criteria)
	{
		$elements = array();
		foreach ($this->postCommand('elements', $criteria) as $value) {
			$elements[] = Element::fromResponseValue($value, $this->getSessionUrl()->descend('element'), $this->driver);
		}

		return $elements;
	}



	public function captureDebugState()
	{
		try {
			return array(
				'url' => $this->url(),
				'screenshot' => $this->screenshot(),
			);

		} catch (\Exception $e) {
			return NULL;
		}
	}

}
