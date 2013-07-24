<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Selenium\Behat;

use Behat\Behat\Console\BehatApplication;
use Kdyby;
use Nette;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Application extends BehatApplication
{

	/**
	 * Creates container instance, loads extensions and freezes it.
	 *
	 * @param InputInterface $input
	 *
	 * @return ContainerInterface
	 */
	protected function createContainer(InputInterface $input)
	{
		$container = new ContainerBuilder();
		$this->loadCoreExtension($container, $this->loadConfiguration($container, $input));
		$container->compile();

		return $container;
	}

}
