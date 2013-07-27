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
use PHPUnit_Extensions_Selenium2TestCase_Driver;
use PHPUnit_Extensions_Selenium2TestCase_URL;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Element extends \PHPUnit_Extensions_Selenium2TestCase_Element
{

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

		throw new \PHPUnit_Extensions_Selenium2TestCase_WebDriverException("Element '{$criteria['value']}' using '{$criteria['using']}'.");
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



	/**
	 * @param array $value
	 * @param PHPUnit_Extensions_Selenium2TestCase_URL $parentFolder
	 * @param PHPUnit_Extensions_Selenium2TestCase_Driver $driver
	 * @throws \InvalidArgumentException
	 * @return Element
	 */
	public static function fromResponseValue(
		array $value,
		PHPUnit_Extensions_Selenium2TestCase_URL $parentFolder,
		PHPUnit_Extensions_Selenium2TestCase_Driver $driver)
	{
		if (!isset($value['ELEMENT'])) {
			throw new \InvalidArgumentException('Element not found.');
		}
		$url = $parentFolder->descend($value['ELEMENT']);

		return new static($driver, $url);
	}


}
