<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Selenium\Behat\DI;

use Kdyby;
use Nette;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use \InvalidArgumentException;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class LoaderExtension implements ExtensionInterface
{

	/**
	 * Loads a specific configuration.
	 *
	 * @param array $config    An array of configuration values
	 * @param ContainerBuilder $container A ContainerBuilder instance
	 *
	 * @throws InvalidArgumentException When provided tag is not defined in this extension
	 *
	 * @api
	 */
	public function load(array $config, ContainerBuilder $container)
	{
		$loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/config'));
		$loader->load('behat.xml');
	}



	/**
	 * Returns the namespace to be used for this extension (XML namespace).
	 *
	 * @return string The XML namespace
	 *
	 * @api
	 */
	public function getNamespace()
	{
		return 'http://kdyby.org/schema/dic/behat';
	}



	/**
	 * Returns the base path for the XSD files.
	 *
	 * @return string The XSD base path
	 *
	 * @api
	 */
	public function getXsdValidationBasePath()
	{
		return __DIR__ . '/config/schema';
	}



	/**
	 * Returns the recommended alias to use in XML.
	 *
	 * This alias is also the mandatory prefix to use when using YAML.
	 *
	 * @return string The alias
	 *
	 * @api
	 */
	public function getAlias()
	{
		return 'kdyby_loader';
	}

}
