<?php

namespace App\AdminModule\Presenters;

use Nette;
use App\Model;
use Nette\Application\UI\Form;
use Ublaboo\DataGrid\DataGrid;
use Nette\Application\Responses\JsonResponse;
use Tracy\Debugger;


/**
 * Vytváření, mazání a editace novinek.
 */
class ArticlePresenter extends BasePresenter
{
	private $articleManager;
	/**
	 * kvůli datagridu
	 * @var Nette\Database\Context
	 * @inject
	 */
	public $db;


	/**
	 * ArticlePresenter constructor.
	 * @param Model\ArticleManager $articleManager
	 */
	public function __construct(Model\ArticleManager $articleManager)
	{
		parent::__construct();

		$this->articleManager = $articleManager;
	}


	/**
	 * Kontrola oprávnění, zda uživatel může pracovat s novinkama.
	 * @throws \Nette\Application\AbortException
	 */
	public function startup()
	{
		parent::startup();
		if (!$this->user->isAllowed('Article')) {
			$this->flashMessage('Nemáte dostatečné oprávnění!', 'alert-danger');
			$this->redirect('Homepage:');
		}
	}


	/**
	 * Formuláře pro vložení novinky.
	 * @return Form
	 */
	protected function createComponentArticleCreateForm(): Form
	{
		$form = new Form;

		$form->addText('nadpis')
			->setRequired('Vyplňte nadpis.');

		$form->addUpload('hl_obr')
			->setRequired('Vložte obrázek')
			->addRule(Form::IMAGE, 'Obrázek musí být JPG, GIF nebo PNG')
			->addRule(Form::MAX_FILE_SIZE, 'Maximální velikost souboru 2MB', 2000 * 1024);

		$form->addTextArea('text')
			->setRequired('Vyplňte text.');

		$form->addText('youtuberi');

		$form->addSubmit('send', 'Odeslat');

		$form->addProtection();

		$form->onSuccess[] = [$this, 'articleCreateFormSucceeded'];

		return $form;
	}


	/**
	 * Odeslání a zpracování formuláře pro vložení novinky
	 * @param Form $form
	 * @param $values
	 * @throws \Nette\Application\AbortException
	 * @throws \Nette\NotSupportedException
	 */
	public function articleCreateFormSucceeded(Form $form, $values)
	{
		try {
			$this->articleManager->saveArticle($values->nadpis, $values->hl_obr, $values->youtuberi, $values->text);
			$this->flashMessage('Novinka byla přidána.', 'alert-success');
			$this->redirect('Article:show');
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			Debugger::log($e);
			$form->addError('Novinka s tímto názvem již existuje.');
			return;
		} catch (Nette\Utils\ImageException $e) {
			Debugger::log($e);
			$form->addError('Obrázek má příliš velké rozměry.');
			return;
		} catch (Model\ForeignException $e) {
			Debugger::log($e);
			$this->flashMessage('Novinka byla přidána, ale youtuber se zadaným ID neexistuje.', 'alert-warning');
			$this->redirect('Article:show');
		}
	}


	/**
	 * Získání záznamu dle id v URL a vložení hodnot do formuláře.
	 * @param $id
	 * @throws \Nette\Application\AbortException
	 */
	public function actionEdit($id)
	{
		$article = $this->articleManager->getArticle($id);
		if (!$article) {
			$this->flashMessage("Novinka nebyla nalezena.", 'alert-warning');
			$this->redirect('Article:show');
		}
		$this['articleEditForm']->setDefaults($article->toArray());
	}


	/**
	 * Získání seznamu youtuberů zmiňovaných v novince, která se edituje.
	 * @return string
	 */
	private function getYoutuberList(): string
	{
		$id = $this->request->getParameters();
		$fetchYoutubers = $this->articleManager->getListYoutubers($id['id']);
		if ($fetchYoutubers){
			foreach ($fetchYoutubers as $youtuber) {
				$listYoutubers[] = $youtuber->id_youtuberi;
			}
			return implode(',', $listYoutubers);
		}
		return '';
	}


	/**
	 * Odeslání a zpracování formuláře pro editaci novinky
	 * @return Form
	 */
	public function createComponentArticleEditForm(): Form
	{
		$form = new Form;

		$form->addText('nadpis')
			->setRequired('Vyplňte nadpis.');

		$form->addUpload('hl_obr')
			->setRequired(false)
			->addRule(Form::IMAGE, 'Obrázek musí být JPG, GIF nebo PNG')
			->addRule(Form::MAX_FILE_SIZE, 'Maximální velikost souboru 2MB', 2000 * 1024);

		$form->addTextArea('text')
			->setRequired('Vyplňte text.');

		$form->addText('youtuberi')
			->setDefaultValue($this->getYoutuberList());

		$form->addSubmit('send', 'Odeslat');

		$form->addProtection();

		$form->onSuccess[] = [$this, 'articleEditFormSucceeded'];

		return $form;
	}


	/** Odeslání a zpracování editovacího formuláře
	 * @param Form $form
	 * @param $values
	 * @throws \Nette\Application\AbortException
	 * @throws \Nette\NotSupportedException
	 */
	public function articleEditFormSucceeded(Form $form, $values)
	{
		try {
			$this->articleManager->updateArticle($this->getParameter('id'), $values->nadpis, $values->hl_obr, $values->youtuberi, $values->text);
			$this->flashMessage('Novinka byla editována.', 'alert-success');
			$this->redirect('Article:show');
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			Debugger::log($e);
			$form->addError('Novinka s tímto názvem již existuje.');
			return;
		} catch (Nette\Utils\ImageException $e) {
			Debugger::log($e);
			$form->addError('Obrázek má příliš velké rozměry.');
			return;
		} catch (Model\ForeignException $e) {
			Debugger::log($e);
			$this->flashMessage('Novinka byla editována, ale youtuber se zadaným ID neexistuje.', 'alert-warning');
			$this->redirect('Article:show');
		}
	}


	/** Vytvoření tabulky se seznamem novinek
	 * @param $name
	 * @throws \Nette\InvalidStateException
	 * @throws \Ublaboo\DataGrid\Exception\DataGridException
	 */
	public function createComponentArticlesGrid($name)
	{
		$grid = new DataGrid();
		$this->addComponent($grid, $name);

		$grid->setDataSource($this->db->table('db_novinky'));

		$grid->addColumnLink('nadpis', 'Nadpis', 'edit')
			->setClass('datagrid-link')
			->setSortable();

		$grid->addFilterText('nadpis', 'Search', ['nadpis']);

		$grid->addAction('delete', '', 'delete!')
			->setIcon('trash')
			->setTitle('Smazat')
			->setClass('btn btn-xs btn-danger ajax')
			->setConfirm('Opravdu chcete smazat novinku %s?', 'nadpis');

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


	/**
	 * Mazání novinek z Datagridu.
	 * @param $id
	 * @throws \Nette\Application\AbortException
	 */
	public function handleDelete($id)
	{
		$this->articleManager->deleteArticle($id);

		if ($this->isAjax()) {
			$this->redrawControl('flashes');
			$this['articlesGrid']->reload();
		} else {
			$this->redirect('this');
			$this->flashMessage('Novinka smazána.', 'alert-warning');
		}
	}


	/**
	 * Nahrávání obrázků na FTP pomocí WYSIWYGU Summernote.
	 * @return string
	 * @throws \Nette\Utils\UnknownImageFileException
	 * @throws \Nette\Utils\ImageException
	 * @throws \Nette\NotSupportedException
	 * @throws \Nette\Application\AbortException
	 */
	public function handleUploadImage() {
		$image = $this->getHttpRequest()->getFile('image');

		try {
			$array = $this->articleManager->uploadImage($image); // vrátí se cesta k souboru v poli
		} catch (Nette\Utils\ImageException $e) {
			Debugger::log($e);
			$this->flashMessage('Problém s uploadem obrázku.', 'alert-warning');
			return;
		}


		if ($this->getHttpRequest()->isAjax()) {
			$response = new JsonResponse(array('url' => $array[0]));
			$this->presenter->sendResponse($response);
		} else {
			return $array[1];
		}
	}
}