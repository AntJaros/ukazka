<?php

namespace App\AdminModule\Presenters;

use App\Model;

/**
 * Úvodní stránka pro administraci (nástěnka).
 */
class HomepagePresenter extends BasePresenter
{
	/**
	 * @var Model\YoutuberManager
	 */
	private $youtuberManager;
	/**
	 * @var Model\FrontUserManager
	 */
	private $userManager;
	/**
	 * @var Model\CommentManager
	 */
	private $commentManager;
	/**
	 * @var Model\ArticleManager
	 */
	private $articleManager;
	/**
	 * @var Model\NoticeManager
	 */
	private $notices;


	/**
	 * HomepagePresenter constructor.
	 * @param Model\YoutuberManager $youtuberManager
	 * @param Model\FrontUserManager $userManager
	 * @param Model\CommentManager $commentManager
	 * @param Model\ArticleManager $articleManager
	 * @param Model\NoticeManager $notices
	 */
	public function __construct(Model\YoutuberManager $youtuberManager, Model\FrontUserManager $userManager, Model\CommentManager $commentManager, Model\ArticleManager $articleManager, Model\NoticeManager $notices)
	{
		parent::__construct();

		$this->youtuberManager = $youtuberManager;
		$this->userManager = $userManager;
		$this->commentManager = $commentManager;
		$this->articleManager = $articleManager;
		$this->notices = $notices;
	}

	/**
	 * Výpis tabulek na nástěnce.
	 */
	public function renderDefault()
	{
		$this->template->youtubersCount = $this->youtuberManager->getNumberYoutubers();
		$this->template->usersCount = $this->userManager->getNumberUsers();
		$this->template->commentsCount = $this->commentManager->getNumberComments();
		$this->template->articlesCount = $this->articleManager->getNumberArticles();
		$this->template->notices = $this->notices->getNotices()->limit(15);
	}
}
