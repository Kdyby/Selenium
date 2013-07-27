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
use Nette\Diagnostics\Debugger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Application extends BehatApplication
{

	public function __construct($version)
	{
		parent::__construct($version);

		$this->setCatchExceptions(FALSE);
		$this->setAutoExit(FALSE);
	}



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
		$definitionLoaderExtension = new DI\LoaderExtension;
		$definitionLoaderExtension->load(array(), $container);

		$container->compile();

		return $container;
	}



	/**
	 * @param \Symfony\Component\Console\Input\InputInterface $input
	 * @param \Symfony\Component\Console\Output\OutputInterface $output
	 * @return int
	 * @throws \Exception
	 */
	public function run(InputInterface $input = NULL, OutputInterface $output = NULL)
	{
		if (NULL === $output) {
			$output = new ConsoleOutput();
		}

		try {
			return parent::run($input, $output);

		} catch (\Exception $e) {
			if ($output instanceof ConsoleOutputInterface) {
				$this->renderException($e, $output->getErrorOutput());

			} else {
				$this->renderException($e, $output);
			}

			if ($file = Debugger::log($e, Debugger::ERROR)) {
				$output->writeln(sprintf('<error>  (Tracy output was stored in %s)  </error>', basename($file)));
				$output->writeln('');

				if (Debugger::$browser) {
					exec(Debugger::$browser . ' ' . escapeshellarg($file));
				}
			}

			return min((int) $e->getCode(), 255);
		}
	}

}
