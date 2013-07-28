<?php


namespace Kdyby\Selenium;

use Kdyby;
use Nette\Application\Request;
use Nette\Loaders\RobotLoader;
use Nette\Reflection\ClassType;



/**
 * Index for all page objects
 *
 * Maps URLs to PageElements
 */
class Sitemap
{

	/** @var array */
	private $dirs;

	/** @var array Fully qualified presenter name -> Page element class name */
	public $presenterMap;

	/** @var array Page element class name => title / human readable name */
	public $nameMap;



	/**
	 * @param string[]|string $dirs Directories where to search
	 */
	public function __construct($dirs)
	{
		$this->dirs = (array) $dirs;
	}



	public function getDirectories()
	{
		return $this->dirs;
	}



	/**
	 * Find all sitemap classes
	 *
	 * @return string[] Class names
	 */
	public function findClasses()
	{
		// find all classes
		$robot = new RobotLoader;
		foreach ($this->dirs as $dir) {
			if (!is_dir($dir)) {
				continue;
			}
			$robot->addDirectory($dir);
		}
		$robot->setCacheStorage(new \Nette\Caching\Storages\DevNullStorage());
		$robot->rebuild();
		$classes = array_keys($robot->getIndexedClasses());

		return $classes;
	}



	public function init()
	{
		if ($this->presenterMap !== NULL) return;

		$this->presenterMap = $this->nameMap = array();
		foreach ($this->findClasses() as $className) {
			$class = new ClassType($className);

			$presenter = ltrim($class->getAnnotation('Presenter'), ':');
			$name = $class->getAnnotation('Name');

			if ($presenter) $this->presenterMap[substr($presenter, -1) === ':' ? $presenter . 'default' : $presenter] = $className;
			if ($name) $this->nameMap[$className] = $name;
		}
	}



	/**
	 * @param Request $appRequest
	 * @return string Page element class name
	 */
	public function findPageByPresenter(Request $appRequest)
	{
		$this->init();

		$presenter = $appRequest->presenterName . ':' . $appRequest->parameters['action'];

		if (isset($this->presenterMap[$presenter])) {
			return $this->presenterMap[$presenter];

		} else {
			throw new \RuntimeException("Not found");
		}
	}

}


