<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Selenium\Behat;

use Kdyby\Selenium\Sitemap;
use Nette\Loaders\RobotLoader;
use Behat\Behat\Definition\DefinitionDispatcher;
use Behat\Behat\Hook\HookDispatcher;
use Behat\Behat\Context\ContextInterface;
use Behat\Behat\Context\Loader\LoaderInterface;



/**
 * Loads step definitions from all available PageObjects
 *
 * Loader based on AnnotationRouter in Behat
 */
class DefinitionLoader implements LoaderInterface
{

	/** @var DefinitionDispatcher  */
	private $definitionDispatcher;

	/** @var HookDispatcher  */
	private $hookDispatcher;

	/** @var array annotation => className */
	private $annotationClasses = array(
		'given' => 'Kdyby\Selenium\Behat\Definitions\Given',
		'when' => 'Kdyby\Selenium\Behat\Definitions\When',
		'then' => 'Kdyby\Selenium\Behat\Definitions\Then',
		'transform' => 'Behat\Behat\Definition\Annotation\Transformation',
		'beforesuite' => 'Behat\Behat\Hook\Annotation\BeforeSuite',
		'aftersuite' => 'Behat\Behat\Hook\Annotation\AfterSuite',
		'beforefeature' => 'Behat\Behat\Hook\Annotation\BeforeFeature',
		'afterfeature' => 'Behat\Behat\Hook\Annotation\AfterFeature',
		'beforescenario' => 'Behat\Behat\Hook\Annotation\BeforeScenario',
		'afterscenario' => 'Behat\Behat\Hook\Annotation\AfterScenario',
		'beforestep' => 'Behat\Behat\Hook\Annotation\BeforeStep',
		'afterstep' => 'Behat\Behat\Hook\Annotation\AfterStep'
	);

	/** @var string */
	private $availableAnnotations;

	/** @var Sitemap */
	private $sitemap;



	/**
	 * Initializes context loader.
	 *
	 * @param DefinitionDispatcher $definitionDispatcher
	 * @param HookDispatcher $hookDispatcher
	 * @param string|string[] $sitemapDirs
	 */
	public function __construct(DefinitionDispatcher $definitionDispatcher, HookDispatcher $hookDispatcher, $sitemapDirs)
	{
		$this->definitionDispatcher = $definitionDispatcher;
		$this->hookDispatcher = $hookDispatcher;
		$this->availableAnnotations = implode("|", array_keys($this->annotationClasses));
		$this->sitemap = new Sitemap((array) $sitemapDirs);
	}



	public function supports(ContextInterface $context)
	{
		return $context instanceof BehatContext;
	}



	public function load(ContextInterface $context)
	{
		foreach ($this->sitemap->findClasses() as $className) {
			$reflection = new \ReflectionClass($className);
			if ($reflection->isSubclassOf('Behat\Behat\Context\BehatContext')) continue;

			foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $methodRefl) {
				if ($methodRefl->getDeclaringClass() != $reflection) continue; // only own methods; intentionally != because reflection is not identical

				foreach ($this->readMethodAnnotations($reflection->getName(), $methodRefl) as $annotation) {
					if ($annotation instanceof \Behat\Behat\Definition\DefinitionInterface) {
						$this->definitionDispatcher->addDefinition($annotation);
					} elseif ($annotation instanceof \Behat\Behat\Definition\TransformationInterface) {
						$this->definitionDispatcher->addTransformation($annotation);
					} elseif ($annotation instanceof \Behat\Behat\Hook\HookInterface) {
						$this->hookDispatcher->addHook($annotation);
					}
				}
			}
		}
	}



	/**
	 * Reads all supported method annotations.
	 *
	 * @param string            $className
	 * @param \ReflectionMethod $method
	 *
	 * @return array
	 */
	private function readMethodAnnotations($className, \ReflectionMethod $method)
	{
		$annotations = array();

		// read parent annotations
		try {
			$prototype = $method->getPrototype();
			$annotations = array_merge($annotations, $this->readMethodAnnotations($className, $prototype));
		} catch (\ReflectionException $e) {
		}

		// read method annotations
		if ($docBlock = $method->getDocComment()) {
			$description = null;

			foreach (explode("\n", $docBlock) as $docLine) {
				$docLine = preg_replace('/^\/\*\*\s*|^\s*\*\s*|\s*\*\/$|\s*$/', '', $docLine);

				if (preg_match('/^\@(' . $this->availableAnnotations . ')\s*(.*)?$/i', $docLine, $matches)) {
					$class = $this->annotationClasses[strtolower($matches[1])];
					$callback = array($className, $method->getName());

					if (isset($matches[2]) && !empty($matches[2])) {
						$annotation = new $class($callback, $matches[2]);
					} else {
						$annotation = new $class($callback);
					}

					if (null !== $description) {
						$annotation->setDescription($description);
					}

					$annotations[] = $annotation;
				} elseif (null === $description && '' !== $docLine && false === strpos($docLine, '@')) {
					$description = $docLine;
				}
			}
		}

		return $annotations;
	}

}
