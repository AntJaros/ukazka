<?php

namespace App\FrontModule\Presenters;

use App\Model;


/**
 * Úvodní stránka na frontendu.
 */
class HomepagePresenter extends BasePresenter
{
	private $youtuberManager;
	private $commentManager;
	private $articleManager;
	private $categoryManager;


	/**
	 * HomepagePresenter constructor.
	 * @param Model\YoutuberManager $youtuberManager
	 * @param Model\CommentManager $commentManager
	 * @param Model\ArticleManager $articleManager
	 * @param Model\CategoryManager $categoryManager
	 */
	public function __construct(Model\YoutuberManager $youtuberManager, Model\CommentManager $commentManager, Model\ArticleManager $articleManager, Model\CategoryManager $categoryManager)
	{
		parent::__construct();

		$this->youtuberManager = $youtuberManager;
		$this->commentManager = $commentManager;
		$this->articleManager = $articleManager;
		$this->categoryManager = $categoryManager;
	}


	/**
	 * Výpis proměnných do šablony.
	 */
	public function renderDefault()
	{
		$this->template->articles = $this->articleManager->getArticles()
			->order('datum DESC')
			->limit(5)
			->fetchAll();
		$this->template->youtubersLastMonth = $this->youtuberManager->getYoutubersOnHP();
		$this->template->dateTableYt = $this->youtuberManager->dateOnTableYtHP();
		$this->template->comments = $this->commentManager->getTopComments();
	}


	/**
	 * Vytváří Sitemapy.
	 */
	public function renderSitemap() {
		$this->template->siteCategories = $this->categoryManager->getCategories()->fetchAll();
		$this->template->siteYoutubers = $this->youtuberManager->getYoutubers()->fetchAll();
		$this->template->siteArticles = $this->articleManager->getArticles()->fetchAll();
	}
}
