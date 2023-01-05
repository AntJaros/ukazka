<?php

namespace App\FrontModule\Presenters;

use App\Model;


/**
 * výpis jednotlivých kategorií
 */
class CategoryPresenter extends BasePresenter
{
	const
		STEP = 20;

	private $categoryManager;
	private $youtubers;
	private $offset;
	private $sortCategory;
	public $idCategory;


	/**
	 * HomepagePresenter constructor.
	 * @param Model\CategoryManager $categoryManager
	 */
	public function __construct(Model\CategoryManager $categoryManager)
	{
		parent::__construct();

		$this->categoryManager = $categoryManager;
	}


	/**
	 * Výpis seznamu youtuberů v dané kategorii.
	 * @param $slug
	 */
	public function renderDefault($slug)
	{
		// předá kategorii do šablony
		$category = $this->categoryManager->getCategorySlug($slug);
		if (!$category) {
			$this->error('Stránka s kategorií nenalezena.');
		}
		//zjistíme ID ze slugu
		$idCategory = $category->id;
		$this->template->idCategory = $idCategory;
		$this->template->category = $category;


		//vytáhneme všechny youtubery dané kategorie
		if ($this->youtubers === NULL) {
			$this->youtubers = $this->categoryManager->getYoutubersByCategory($idCategory, self::STEP);
		}
		$this->template->youtubers = $this->youtubers;

		//vytáhneme počet youtuberů dané kategorie
		$this->template->numberYoutubers = $this->categoryManager->getNumberYoutubersByCategory($idCategory);

		// výchozí sortování potřebné pro tlačítko load more u youtuberů
		if ($this->sortCategory === NULL)
		{
			$this->sortCategory = 0;
		}
		$this->template->sortCategory = $this->sortCategory;

		// od kolikátého youtubera se mají načítat po stisku načíst další
		if ($this->offset === NULL)
		{
			$this->offset = 0;
		}
		$this->template->offset = $this->offset;
	}


	/**
	 * Řazení youtuberů v kategorii.
	 * @param $id
	 * @param $sort
	 */
	public function handleSortCategory($id, $sort)
	{
		$this->youtubers = $this->categoryManager->getYoutubersByCategory($id, self::STEP, $sort);
		$this->sortCategory = $sort;
		if ($this->isAjax()) {
			$this->redrawControl('sortCat');
		}
	}


	/**
	 * Načítání dalších youtuberů v kategorii.
	 * @param $offset
	 * @param $id
	 * @param $sort
	 */
	public function handleMoreCategories($offset, $id, $sort)
	{
		$offset += self::STEP;
		$this->youtubers = $this->categoryManager->loadMoreCategories($id, $offset, self::STEP, $sort);
		$this->offset = $offset;
		$this->sortCategory = $sort;
		if ($this->isAjax()) {
			$this->redrawControl('moreCat');
			$this->redrawControl('moreCatButton');
		}
	}
}
