<?php

namespace App\AdminModule\Presenters;

use Nette;
use App\Model;
use Nette\Application\UI\Form;
use Ublaboo\DataGrid\DataGrid;
use Tracy\Debugger;


/**
 * Vytváření, mazání a editace kategorií youtuberů.
 */
class CategoryPresenter extends BasePresenter
{
	private $categoryManager;
	/**
	 * kvůli datagridu
	 * @var Nette\Database\Context
	 * @inject
	 */
	public $db;


	/**
	 * CategoryPresenter constructor.
	 * @param Model\CategoryManager $categoryManager
	 */
	public function __construct(Model\CategoryManager $categoryManager)
	{
		parent::__construct();

		$this->categoryManager = $categoryManager;
	}


	/**
	 * Kontrola oprávnění, zda uživatel může pracovat s kategoriema.
	 * @throws \Nette\Application\AbortException
	 */
	public function startup()
	{
		parent::startup();
		if (!$this->user->isAllowed('Category')) {
			$this->flashMessage('Nemáte dostatečné oprávnění!', 'alert-danger');
			$this->redirect('Homepage:');
		}
	}


	/**
	 * Vytvoření formuláře pro novou kategorii.
	 * @return Form
	 */
	protected function createComponentCategoryCreateForm(): Form
	{
		$form = new Form;

		$form->addText('nazev')
			->setRequired('Vyplňte název.');

		$form->addUpload('obrazek')
			->setRequired('Vložte obrázek.')
			->addCondition(Form::IMAGE)
			->addRule(Form::MIME_TYPE, 'Soubor musí být obrázek typu JPEG', 'image/jpeg')
			->addRule(Form::MAX_FILE_SIZE, 'Maximální velikost souboru 500kB', 500 * 1024);

		$form->addTextArea('popis')
			->setRequired('Vyplňte popis.');

		$form->addSubmit('send', 'Odeslat');

		$form->addProtection();

		$form->onSuccess[] = [$this, 'categoryCreateFormSucceeded'];

		return $form;
	}


	/** Odeslání a zpracování formuláře pro vložení kategorie
	 * @param Form $form
	 * @param $values
	 * @throws \Nette\Application\AbortException
	 */
	public function categoryCreateFormSucceeded(Form $form, $values)
	{
		try {
			$this->categoryManager->saveCategory($values->nazev, $values->obrazek, $values->popis);
			$this->flashMessage('Kategorie byla přidána.', 'alert-success');
			$this->redirect('Category:show');
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			Debugger::log($e);
			$form->addError('Kategorie s tímto názvem již existuje.');
			return;
		} catch (Nette\Utils\ImageException $e) {
			Debugger::log($e);
			$form->addError('Obrázek má příliš velké rozměry.');
			return;
		}
	}


	/** Získání záznamu dle id v URL a vložení hodnot do formuláře
	 * @param $id
	 * @throws \Nette\Application\AbortException
	 */
	public function actionEdit(int $id)
	{
		$category = $this->categoryManager->getCategory($id);
		if (!$category) {
			$this->flashMessage("Kategorie nebyla nalezena.", 'alert-warning');
			$this->redirect('Category:show');
		}
		$this['categoryEditForm']->setDefaults($category->toArray());
	}


	/**
	 * Vytvoření formuláře pro editaci
	 * @return Form
	 */
	public function createComponentCategoryEditForm(): Form
	{
		$form = new Form;

		$form->addText('nazev')
			->setRequired('Vyplňte název.');

		$form->addUpload('obrazek')
			->setRequired(FALSE)
			->addCondition(Form::IMAGE)
			->addRule(Form::MIME_TYPE, 'Soubor musí být obrázek typu JPEG', 'image/jpeg')
			->addRule(Form::MAX_FILE_SIZE, 'Maximální velikost souboru 500kB', 500 * 1024);

		$form->addTextArea('popis')
			->setRequired('Vyplňte popis.');

		$form->addSubmit('send', 'Odeslat');

		$form->addProtection();

		$form->onSuccess[] = [$this, 'categoryEditFormSucceeded'];

		return $form;
	}


	/** Odeslání a zpracování editovacího formuláře
	 * @param Form $form
	 * @param $values
	 * @throws \Nette\Application\AbortException
	 */
	public function categoryEditFormSucceeded(Form $form, $values)
	{
		try {
			$this->categoryManager->updateCategory($this->getParameter('id'), $values->nazev, $values->obrazek, $values->popis);
			$this->flashMessage('Kategorie byla editována.', 'alert-success');
			$this->redirect('Category:show');
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			Debugger::log($e);
			$form->addError('Kategorie s tímto názvem již existuje.');
			return;
		} catch (Nette\Utils\ImageException $e) {
			Debugger::log($e);
			$form->addError('Obrázek má příliš velké rozměry.');
			return;
		}
	}


	/** Vytvoření tabulky se seznamem kategorií
	 * @param $name
	 * @throws \Nette\InvalidStateException
	 * @throws \Ublaboo\DataGrid\Exception\DataGridException
	 */
	public function createComponentCategoriesGrid($name)
	{
		$grid = new DataGrid();
		$this->addComponent($grid, $name);

		$grid->setDataSource($this->db->table('db_kategorie'));

		$grid->addColumnLink('nazev', 'Název', 'edit')
			->setClass('datagrid-link')
			->setSortable();

		$grid->addFilterText('nazev', 'Search', ['nazev']);

		$grid->addAction('delete', '', 'delete!')
			->setIcon('trash')
			->setTitle('Smazat')
			->setClass('btn btn-xs btn-danger ajax')
			->setConfirm('Opravdu chcete smazat kategorii %s?', 'nazev');

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


	/** Metoda pro mazání položek z tabulky kategorií
	 * @param $id
	 * @throws \Nette\Application\AbortException
	 */
	public function handleDelete($id)
	{
		$this->categoryManager->deleteCategory($id);

		if ($this->isAjax()) {
			$this->redrawControl('flashes');
			$this['categoriesGrid']->reload();
		} else {
			$this->redirect('this');
			$this->flashMessage("Kategorie smazána.", 'alert-warning');
		}
	}
}