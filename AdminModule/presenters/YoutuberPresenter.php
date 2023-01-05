<?php

namespace App\AdminModule\Presenters;

use Nette;
use App\Model;
use Nette\Application\UI\Form;
use Ublaboo\DataGrid\DataGrid;
use Nette\Application\UI;
use Tracy\Debugger;


/**
 * Vytváření, mazání a editace youtuberů.
 */
class YoutuberPresenter extends BasePresenter
{
	private $youtuberManager;
	private $categoryManager;
	/**
	 * kvůli datagridu
	 * @var Nette\Database\Context
	 * @inject
	 */
	public $db;


	/**
	 * youtuberPresenter constructor.
	 * @param Model\youtuberManager $youtuberManager
	 * @param Model\CategoryManager $categoryManager
	 */
	public function __construct(Model\youtuberManager $youtuberManager, Model\CategoryManager $categoryManager)
	{
		parent::__construct();

		$this->youtuberManager = $youtuberManager;
		$this->categoryManager = $categoryManager;
	}


	/**
	 * Získáme seznam kategorií pro formulář.
	 * @throws \Nette\Application\AbortException
	 */
	private function actionCreateCategory()
	{
		$categories = $this->categoryManager->getCategories();
		if (!$categories) {
			$this->flashMessage('Žádné kategorie nejsou k dispozici.', 'alert-warning');
			$this->redirect('Youtuber:show');
		}

		foreach ($categories as $category) {
			$kategorie[$category->id] = $category;
		}
		return $kategorie;
	}


	/**
	 * Vložení nového youtubera.
	 * @return Form
	 * @throws \Nette\Application\AbortException
	 */
	public function createComponentYoutuberCreateForm(): Form
	{
		$form = new Form;

		$form->addCheckboxList('kategorie', null, $this->actionCreateCategory())
			->setRequired('Zaškrtněte 1-3 kategorie.')
			->addRule(Form::COUNT, 'Zaškrtněte max. 3 kategorie.', [1, 3]);

		$form->addText('channel')
			->setRequired('Vyplňte channel-ID.');

		$form->addText('url_vlastni')
			->setRequired(false);

		$form->addSubmit('send', 'Odeslat');

		$form->addProtection();

		$form->onSuccess[] = [$this, 'youtuberCreateFormSucceeded'];

		return $form;
	}

	/**
	 * Odeslání a zpracování formuláře pro vložení youtubera.
	 * @param Form $form
	 * @param $values
	 * @throws \Nette\Application\AbortException
	 */
	public function youtuberCreateFormSucceeded(Form $form, $values)
	{
		if (!$this->user->isAllowed('Youtuber', 'edit')) {
			$this->flashMessage('Nemáte dostatečné oprávnění!', 'alert-danger');
			$this->redirect('Homepage:');
		}

		try {
			$this->youtuberManager->saveYoutuber($values);
			$this->flashMessage('Youtuber byl přidán.', 'alert-success');
			$this->redirect('Youtuber:show');
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			Debugger::log($e);
			$form->addError('Tento channel-ID je již v databázi.');
			return;
		} catch (UI\InvalidLinkException $e) {
			Debugger::log($e);
			$form->addError($e->getMessage());
			return;
		}
	}


	/**
	 * Získání záznamu dle id v URL a vložení hodnot do formuláře + získání pole kategorií.
	 * @param $id
	 * @throws \Nette\Application\AbortException
	 */
	public function actionEdit($id)
	{
		$youtuber = $this->youtuberManager->getYoutuber($id);
		if (!$youtuber) {
			$this->flashMessage('Youtuber nebyl nalezen.', 'alert-warning');
			$this->redirect('Youtuber:show');
		}
		$this['youtuberEditForm']->setDefaults($youtuber->toArray());
	}


	/**
	 * Získání seznamu kategorií jednoho youtubera.
	 * @return array
	 */
	private function getCategoriesList(): array
	{
		$id = $this->request->getParameters();
		$fetchCategory = $this->youtuberManager->getListCategories($id['id']);
		foreach ($fetchCategory as $category) {
			$listCategory[] = $category->id_kategorie;
		}
		return $listCategory;
	}


	/**
	 * Editace youtuberů.
	 * @return Form
	 * @throws \Nette\Application\AbortException
	 */
	public function createComponentYoutuberEditForm(): Form
	{
		$form = new Form;

		$form->addCheckboxList('kategorie', null, $this->actionCreateCategory())
			->setRequired('Zaškrtněte 1-3 kategorie.')
			->setDefaultValue($this->getCategoriesList())
			->addRule(Form::COUNT, 'Zaškrtněte max. 3 kategorie.', [1, 3]);

		$form->addText('jmeno')
			->setRequired('Vyplňte jméno.');

		$form->addText('url_vlastni')
			->setRequired(false);

		$form->addTextArea('popis')
			->setRequired('Vyplňte popis.');

		$form->addHidden('id');

		$form->addSubmit('send', 'Odeslat');

		$form->addProtection();

		$form->onSuccess[] = [$this, 'youtuberEditFormSucceeded'];

		return $form;
	}


	/** Odeslání a zpracování editovacího formuláře.
	 * @param Form $form
	 * @param $values
	 * @throws \Nette\Application\AbortException
	 */
	public function youtuberEditFormSucceeded(Form $form, $values)
	{
		if (!$this->user->isAllowed('Youtuber', 'edit')) {
			$this->flashMessage('Nemáte dostatečné oprávnění!', 'alert-danger');
			$this->redirect('Homepage:');
		}

		$this->youtuberManager->updateYoutuber($values);
		$this->flashMessage('Youtuber byl editován.', 'alert-success');
		$this->redirect('Youtuber:show');
	}


	/** Vytvoření tabulky se seznamem youtuberů
	 * @param $name
	 * @throws \Nette\InvalidStateException
	 * @throws \Ublaboo\DataGrid\Exception\DataGridException
	 */
	public function createComponentYoutubersGrid($name)
	{
		$grid = new DataGrid();
		$this->addComponent($grid, $name);

		$grid->setDataSource($this->db->table('db_youtuberi'));

		$grid->addColumnNumber('id', 'ID')
			->setSortable();

		$grid->addColumnLink('jmeno', 'Jméno', 'edit')
			->setClass('datagrid-link')
			->setSortable();

		$grid->addFilterText('jmeno', 'Search', ['jmeno']);

		$grid->addAction('delete', '', 'delete!')
			->setIcon('trash')
			->setTitle('Smazat')
			->setClass('btn btn-xs btn-danger ajax')
			->setConfirm('Opravdu chcete smazat youtubera %s?', 'jmeno');

		$translator = new \Ublaboo\DataGrid\Localization\SimpleTranslator([
			'ublaboo_datagrid.no_item_found_reset' => 'Žádné položky nenalezeny. Filtr můžete vynulovat',
			'ublaboo_datagrid.no_item_found' => 'Žádné položky nenalezeny.',
			'ublaboo_datagrid.here' => 'zde',
			'ublaboo_datagrid.items' => 'Položky',
			'ublaboo_datagrid.all' => 'všechny',
			'ublaboo_datagrid.from' => 'z',
			'ublaboo_datagrid.reset_filter' => 'Resetovat filtr',
			'ublaboo_datagrid.group_actions' => 'Hromadné akce',
			'ublaboo_datagrid.show_all_columns' => 'Zobrazit všechny sloupce',
			'ublaboo_datagrid.hide_column' => 'Skrýt sloupec',
			'ublaboo_datagrid.action' => 'Akce',
			'ublaboo_datagrid.previous' => 'Předchozí',
			'ublaboo_datagrid.next' => 'Další',
			'ublaboo_datagrid.choose' => 'Vyberte',
			'ublaboo_datagrid.execute' => 'Provést',

			'Name' => 'Jméno',
			'Inserted' => 'Vloženo'
		]);
		$grid->setTranslator($translator);
	}


	/** Metoda pro mazání položek z tabulky youtuberů
	 * @param $id
	 * @throws \Nette\Application\AbortException
	 */
	public function handleDelete($id)
	{
		if (!$this->user->isAllowed('Youtuber', 'edit')) {
			$this->flashMessage('Nemáte dostatečné oprávnění!', 'alert-danger');
			$this->redirect('Homepage:');
		}

		$this->youtuberManager->deleteYoutuber($id);

		if ($this->isAjax()) {
			$this->redrawControl('flashes');
			$this['youtubersGrid']->reload();
		} else {
			$this->redirect('this');
			$this->flashMessage('Youtuber smazán.', 'alert-warning');
		}
	}
}