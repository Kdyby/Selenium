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
class LinkGeneratorPresenter extends Nette\Application\UI\Presenter
{

	public function __construct(Nette\DI\Container $sl)
	{
		parent::__construct();
		$sl->callInjects($this);
		$this->run(new Nette\Application\Request('Front:LinkGenerator', 'GET', array('action' => 'default')));
	}



	protected function startup()
	{
		parent::startup();
		$this->autoCanonicalize = FALSE;
		$this->terminate();
	}

}
