<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Selenium\Behat;

use Kdyby\Selenium\SeleniumTestCase;
use Pd;
use Nette;



/**
 * Hackity hack
 *
 * Offers setUp & tearDown to public
 */
class DummyTestCase extends SeleniumTestCase
{

	public function __construct()
	{
		parent::__construct();
	}



	public function setUp()
	{
		parent::setUp();
	}



	public function tearDown()
	{
		parent::tearDown();
	}

}
