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
abstract class PageElement
{

	/**
	 * @var BrowserSession
	 */
	protected $session;

	/**
	 * @var array
	 */
	private static $isPresenterCache = array();



	public function __construct(BrowserSession $session)
	{
		$this->session = $session;
	}



	/**
	 * @return BrowserSession
	 */
	public function getSession()
	{
		return $this->session;
	}


	/**
	 * @return string
	 */
	public function getName()
	{
		return Nette\Reflection\ClassType::from($this)->getAnnotation('Name');
	}



	private function getElementPresenter()
	{
		return ':' . ltrim(Nette\Reflection\ClassType::from($this)->getAnnotation('Presenter'), ':');
	}



	public function getPresenterName()
	{
		if (!$destination = $this->getElementPresenter()) {
			return NULL;
		}

		$action = ($i = strrpos($destination, ':')) === (strlen($destination) - 1) ? '' : substr($destination, $i + 1);

		return substr($destination, 0, -(strlen($action) + 1)); // trim ":"
	}



	public function getPresenterAction()
	{
		if (!$destination = $this->getElementPresenter()) {
			return NULL;
		}

		$action = ($i = strrpos($destination, ':')) === (strlen($destination) - 1) ? '' : substr($destination, $i + 1);

		return $action ? : 'default';
	}



	protected function accessing()
	{
		if (($requiredPresenter = $this->getElementPresenter()) !== ':' && !$this->isPresenter($requiredPresenter)) {
			$appRequest = $this->session->presenter();
			$currentPresenter = $appRequest->getPresenterName() . ':' . $appRequest->parameters['action'];

			throw new UnexpectedPresenterException("The page object " . get_called_class() . " is only in presenter $requiredPresenter, but currently is opened $currentPresenter");
		}
	}



	public function open()
	{
		$this->session->presenter($this->getElementPresenter());

		return $this;
	}



	public function getTitle()
	{
		$this->accessing();

		return trim($this->session->byTag('title')->text());
	}



	public function getMainHeading()
	{
		$this->accessing();

		return trim($this->session->byTag('h1')->text());
	}



	public function getFlashMessages()
	{
		$this->accessing();

		$flashes = array();
		foreach ($this->session->elements($this->using('css selector')->value('.flash')) as $flash) {
			/** @var \PHPUnit_Extensions_Selenium2TestCase_Element $flash */
			$flashes[] = trim($flash->text());
		}

		return $flashes;
	}



	public function isPresenter($destination, $args = array())
	{
		$destination = ltrim($destination, ':');
		$action = ($i = strrpos($destination, ':')) === (strlen($destination) - 1) ? '' : substr($destination, $i + 1);
		$destination = substr($destination, 0, -(strlen($action) + 1)); // trim ":"
		$args['action'] = $action ? : 'default';

		if (isset(self::$isPresenterCache[$destination][$jsonArgs = json_encode($args)])) {
			$appRequest = self::$isPresenterCache[$destination][$jsonArgs];

		} else {
			self::$isPresenterCache[$destination][$jsonArgs] = $appRequest = $this->session->presenter();
		}

		if (!$appRequest || $appRequest->getPresenterName() !== $destination) {
			return FALSE;
		}

		foreach ($args as $param => $value) {
			if (!array_key_exists($param, $appRequest->parameters) || $value !== $appRequest->parameters[$param]) {
				return FALSE;
			}
		}

		return TRUE;
	}



	/**
	 * @param \PHPUnit_Extensions_Selenium2TestCase_Element $form
	 * @param array $values
	 * @throws \RuntimeException
	 * @return \PHPUnit_Extensions_Selenium2TestCase_Element
	 */
	protected function fillForm(\PHPUnit_Extensions_Selenium2TestCase_Element $form, array $values)
	{
		// $this->accessing(); // todo: why not?

		foreach ($values as $name => $value) {
			$element = $form->byName($name);
			/** @var \PHPUnit_Extensions_Selenium2TestCase_Element $element */

			if (($tagName = strtolower($element->name())) === 'input') {
				if (($type = strtolower($element->attribute('type'))) === 'checkbox') {
					if ($value) {
						if (!$element->selected()) {
							if (!$this->tryClickOnLabel($form, $type, $name)) {
								$element->click();
							}
						}

					} elseif (!$value) {
						if ($element->selected()) {
							if (!$this->tryClickOnLabel($form, $type, $name)) {
								$element->click();
							}
						}
					}

				} elseif ($type === 'radio') {
					if ($this->tryClickOnLabel($form, $type, $name, $value)) {
						continue;
					}

					foreach ($form->elements($this->using('name')->value($name)) as $element) {
						/** @var \PHPUnit_Extensions_Selenium2TestCase_Element $element */
						if ($element->value() == $value) {
							$element->click();
							continue 2;
						}
					}

					throw new \RuntimeException("Radio input with value $value not found.");

				} else {
					$element->value($value);
				}

			} elseif ($tagName === 'textarea') {
				$element->value($value);

			} elseif ($tagName === 'select') {

			}
		}

		return $form;
	}


	public function clearForm(\PHPUnit_Extensions_Selenium2TestCase_Element $form, array $inputs)
	{
		foreach ($inputs as $name) {
			/** @var \PHPUnit_Extensions_Selenium2TestCase_Element $element */
			$element = $form->byName($name);

			if (($tagName = strtolower($element->name())) === 'input') {
				if (($type = strtolower($element->attribute('type'))) === 'checkbox') {
					if ($element->selected()) {
						if (!$this->tryClickOnLabel($form, $type, $name)) {
							$element->click();
						}
					}

				} elseif ($type === 'radio') {
					// TODO
					throw new Nette\NotImplementedException;

				} else {
					$element->clear();
				}

			} elseif ($tagName === 'textarea') {
				$element->clear();

			} elseif ($tagName === 'select') {
				// TODO
				throw new Nette\NotImplementedException;
			}
		}

		return $form;
	}



	private function tryClickOnLabel(\PHPUnit_Extensions_Selenium2TestCase_Element $form, $type, $name, $value = NULL)
	{
		try { // try finding label
			$label = $form->byXPath("//label[./input[@type='{$type}'][@name='{$name}']" . ($value ? "[@value='{$value}']" : '') . "]");

		} catch (\Exception $e) {
			return FALSE;
		}

		$label->click();

		return TRUE;
	}



	public function getFormValues(\PHPUnit_Extensions_Selenium2TestCase_Element $form)
	{
		// $this->accessing(); // todo: why not?

		$values = array();
		foreach ($form->elements($this->using('tag name')->value('input')) as $input) {
			/** @var \PHPUnit_Extensions_Selenium2TestCase_Element $input */
			$values[$input->attribute('name')] = $input->value();
		}

		foreach ($form->elements($this->using('tag name')->value('textarea')) as $textarea) {
			/** @var \PHPUnit_Extensions_Selenium2TestCase_Element $textarea */
			$values[$textarea->attribute('name')] = $textarea->value();
		}

		foreach ($form->elements($this->using('tag name')->value('select')) as $select) {
			/** @var \PHPUnit_Extensions_Selenium2TestCase_Element $select */
			$values[$select->attribute('name')] = $select->value();
		}

		return $values;
	}



	protected function using($strategy)
	{
		return new \PHPUnit_Extensions_Selenium2TestCase_ElementCriteria($strategy);
	}

}
