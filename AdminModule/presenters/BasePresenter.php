<?php

namespace App\AdminModule\Presenters;

use Nette;


/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{

	/**
	 * metoda startup se volá vždy ihned po vytvoření presenteru
	 * pokud píšeme vlastní, musí se zavolat předek (parent)
	 * @throws \Nette\Application\AbortException
	 */
	public function startup()
	{
		parent::startup();
		if (! $this->isAllowed()) {
			$this->redirect('Sign:in');
		}
	}


	/**
	 * @param $element
	 */
	public function checkRequirements($element)
	{
		$this->getUser()->getStorage()->setNamespace('admin');
		parent::checkRequirements($element);
	}


	/**
	 * @return bool
	 */
	protected function isAllowed(): bool
	{
		return $this->getUser()->isLoggedIn();
	}
}
