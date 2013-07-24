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
	}



	public function prepareDatabase()
	{
		$db = $this->serviceLocator->getByType('Kdyby\Doctrine\Connection'); // default connection
		/** @var Connection $db */

		$testDbName = 'damejidlo_test_' . $this->httpServerUrl->getPort();
		$db->exec("DROP DATABASE IF EXISTS `$testDbName`");
		$db->exec("CREATE DATABASE `$testDbName`");
		$db->exec("USE `$testDbName`");
		$this->database = $db;
		\Kdyby\Doctrine\Helpers::loadFromFile($db, __DIR__ . '/../sql/empty-database.sql');

		// drop on shutdown
		register_shutdown_function(function () use ($db, $testDbName) {
			$db->exec("DROP DATABASE IF EXISTS `$testDbName`");
		});

		return $this;
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
				if ($route instanceof Kdyby\Console\CliRouter) {
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
		do {
			$finished = $this->execute(array(
				'script' => 'return jQuery.active == 0;',
				'args' => array(),
			));
			usleep(250);
		} while (!$finished);
		sleep(1); // time for processing nette.ajax.js snippets

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
