<?php

namespace App\FrontModule\Presenters;

use Nette;
use Nette\Application\UI\Form;
use App\Model;
use Nette\Application\Responses\JsonResponse;


/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{
	/** @var Model\YoutuberManager */
	private $youtuberBaseManager;
	/** @var Model\CategoryManager */
	public $categoryBaseManager;
	public $userID;


	/**
	 * Způsob předání závislosti. Výhoda je, že youtuberManagera nemusím dále předávat potomkům. Potřebujeme to k vyhledávání youtuberů na každý stránce.
	 * @param Model\youtuberManager $youtuberBaseManager
	 */
	public function injectYoutuberManager (Model\youtuberManager $youtuberBaseManager)
	{
		$this->youtuberBaseManager = $youtuberBaseManager;
	}

	/**
	 * Způsob předání závislosti. Výhoda je, že categoryManagera nemusím dále předávat potomkům. Potřebujeme to do navigace.
	 * @param Model\categoryManager $categoryBaseManager
	 */
	public function injectCategoryManager (Model\categoryManager $categoryBaseManager)
	{
		$this->categoryBaseManager = $categoryBaseManager;
	}


	/**
	 * uložíme si userID
	 */
	public function startup()
	{
		parent::startup();
		if ($this->user->isLoggedIn()) {
			$this->userID = $this->getUser()->getId();
		}

	}


	/**
	 * Vytvoření vlastního filteru pro odpočet datumu.
	 */
	public function beforeRender()
	{
		// Vytvoření vlastního filteru pro odpočet datumu.
		parent::beforeRender();
		$this->template->addFilter('timeAgo', function ($time) {
			return $this->timeAgoInWords($time);
		});

		// změna pozadí
		$this->template->bodyClass = 'body-' . $this->getPureName() . ' body-view' . ucfirst($this->action);

		// kategorie v menu
		$this->template->baseCategories = $this->categoryBaseManager->getCategories()->order('nazev');
	}


	/**
	 * Metoda pro zjištění názvu stránky (pro CSS pozadí).
	 * @return string
	 */
	public function getPureName(): string
	{
		$temp = mb_split(':', $this->name);
		return lcfirst(end($temp));
	}


	/**
	 *  Vlastní filtr na odpočet datumu.
	 * @param $time
	 * @return string
	 */
	public static function timeAgoInWords($time): string
	{
		if (!$time) {
			return FALSE;
		} elseif (is_numeric($time)) {
			$time = (int) $time;
		} elseif ($time instanceof DateTime) {
			$time = $time->format('U');
		} else {
			$time = strtotime($time);
		}
		$delta = time() - $time;

		$delta = round($delta / 60);
		if ($delta === 0) return 'před okamžikem';
		if ($delta === 1) return 'před minutou';
		if ($delta < 45) return "před $delta minutami";
		if ($delta < 90) return 'před hodinou';
		if ($delta < 1440) return 'před ' . round($delta / 60) . ' hodinami';
		if ($delta < 2880) return 'včera';
		if ($delta < 43200) return 'před ' . round($delta / 1440) . ' dny';
		if ($delta < 86400) return 'před měsícem';
		if ($delta < 525960) return 'před ' . round($delta / 43200) . ' měsíci';
		if ($delta < 1051920) return 'před rokem';
		return 'před ' . round($delta / 525960) . ' lety';
	}


	/**
	 * Nastavení názvu sessions pro přihlášení na frontendu.
	 * @param $element
	 */
	public function checkRequirements($element)
	{
		$this->getUser()->getStorage()->setNamespace('front');
		parent::checkRequirements($element);
	}


	/**
	 * Vytvoření formuláře pro vyhledávání v horním menu.
	 * @return Form
	 */
	protected function createComponentSearchYoutuberForm(): Form
	{
		$form = new Form;
		$form->addText('youtuberi');
		$form->addSubmit('search', '');
		$form->onSubmit[] = [$this, 'searchYoutuberFormSucceeded'];
		return $form;
	}


	/**
	 * zpracovnání formuláře pro vyhledávání youtuberů
	 * @param Form $form
	 * @internal param $values
	 * @throws \Nette\Application\AbortException
	 */
	public function searchYoutuberFormSucceeded(Form $form)
	{
		$youtuber = $this->youtuberBaseManager->getYoutuberName($form->values->youtuberi);
		if ($youtuber) {
			$this->redirect('Youtuber:default', $youtuber->slug);
		}
		else {
			$this->flashMessage('Zadaný youtuber není v databázi!', 'alert-warning');
		}
	}


	/**
	 * funkce pro autocomplete jmen youtuberů ve vyhledávání
	 * @param $whichData
	 * @param string $typedText
	 * @throws \Nette\Application\AbortException
	 */
	public function renderAutocomplete($whichData, $typedText = '')
	{
		$youtubers = $this->youtuberBaseManager->getYoutubers()->fetchAll();

		foreach ($youtubers as $youtuber) {
			$data['youtuberi'][] = $youtuber->jmeno;
		}

		$matches = preg_grep("/$typedText/i", $data[$whichData]);
		$this->sendResponse(new JsonResponse($matches));
	}
}
