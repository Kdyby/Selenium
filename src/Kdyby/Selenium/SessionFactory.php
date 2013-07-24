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
 */
class SessionFactory
{

	/**
	 * @var Nette\DI\Container
	 */
	private $sl;

	/**
	 * @var array
	 */
	private $defaults;

	/**
	 * @var HttpServer
	 */
	private $httpServer;

	/**
	 * @var array
	 */
	public static $sessionDefaults = array(
		'host' => '127.0.0.1',
		'port' => '4444',
		'desiredCapabilities' => array(),
		'browserName' => 'firefox',
		'seleniumServerRequestsTimeout' => 60,
	);



	public function __construct(Nette\DI\Container $sl, HttpServer $httpServer, array $defaults = array())
	{
		$this->sl = $sl;
		$this->httpServer = $httpServer;
		$this->defaults = $defaults + array('browserUrl' => $this->createBrowserUrl()) + self::$sessionDefaults;
	}



	/**
	 * @return \PHPUnit_Extensions_Selenium2TestCase_URL
	 */
	public function createBrowserUrl()
	{
		return \PHPUnit_Extensions_Selenium2TestCase_URL::fromHostAndPort($this->httpServer->getUrl()->host, $this->httpServer->getUrl()->port);
	}



	/**
	 * @param array $parameters
	 * @return BrowserSession
	 */
	public function create(array $parameters = array())
	{
		$parameters += $this->defaults;

		$seleniumServerUrl = \PHPUnit_Extensions_Selenium2TestCase_URL::fromHostAndPort($parameters['host'], $parameters['port']);
		$driver = new \PHPUnit_Extensions_Selenium2TestCase_Driver($seleniumServerUrl, $parameters['seleniumServerRequestsTimeout']);
		$capabilities = array_merge($parameters['desiredCapabilities'], array(
			'browserName' => $parameters['browserName']
		));

		$sessionCreation = $seleniumServerUrl->descend("/wd/hub/session");
		$response = $driver->curl('POST', $sessionCreation, array(
			'desiredCapabilities' => $capabilities
		));
		$sessionPrefix = $response->getURL();

		$timeouts = new \PHPUnit_Extensions_Selenium2TestCase_Session_Timeouts(
			$driver,
			$sessionPrefix->descend('timeouts'),
			$parameters['seleniumServerRequestsTimeout'] * 1000
		);

		return new BrowserSession($this->sl, $driver, $sessionPrefix, $parameters, $timeouts);
	}

}
