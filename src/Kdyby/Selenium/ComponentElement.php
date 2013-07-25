<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Selenium;


/**
 * Common ancestor for all components
 */
class ComponentElement extends PageElement
{

	/** @var PageElement */
	private $container;



	public function __construct(BrowserSession $session, PageElement $container = NULL)
	{
		parent::__construct($session);
		$this->container = $container;
	}



	public function getContainer()
	{
		return $this->container;
	}

}


