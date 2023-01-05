<?php

namespace App\AdminModule\Presenters;

use Nette\Application\UI;
use App\Model;
use Tracy\Debugger;


/**
 * Vytváření, mazání a editace kategorií youtuberů.
 */
class UpdatePresenter extends BasePresenter
{
	private $youtuberManager;


	/**
	 * CategoryPresenter constructor.
	 * @param Model\YoutuberManager $youtuberManager
	 */
	public function __construct(Model\YoutuberManager $youtuberManager)
	{
		parent::__construct();

		$this->youtuberManager = $youtuberManager;
	}


	/**
	 * Kontrola oprávnění, zda uživatel může pracovat s kategoriema.
	 * @throws \Nette\Application\AbortException
	 */
	public function startup()
	{
		parent::startup();
		if (!$this->user->isAllowed('Update')) {
			$this->flashMessage('Nemáte dostatečné oprávnění!', 'alert-danger');
			$this->redirect('Homepage:');
		}
	}


	/**
	 *
	 */
	public function renderDefault()
	{

	}


	/**
	 * Odeslání a zpracování editovacího formuláře
	 * @param $min
	 * @param $max
	 */
	public function handleUpdateSubViews($min, $max)
	{
		try {
			$count = $this->youtuberManager->updateYoutubersDates($min, $max);
			$this->flashMessage('Úspěšně updatováno ' . $count . ' youtuberů.', 'alert-success');
			$this->redirect('Update:default');
		} catch (UI\InvalidLinkException $e) {
			Debugger::log($e);
			$this->flashMessage($e->getMessage(), 'alert-danger');
			$this->redirect('this');
		}
	}
}