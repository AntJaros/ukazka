<?php

namespace App\FrontModule\Presenters;

use App\Model;


/**
 * Stránka s detailem uživatele
 * @package App\FrontModule\Presenters
 */
class UserPresenter extends BasePresenter
{
	private $userManager;
	private $commentManager;
	private $youtuberManager;
	private $articleManager;
	private $ratings;
	private $comments;
	private $sortYoutubers;
	private $sortComments;


	/**
	 * HomepagePresenter constructor.
	 * @param Model\FrontUserManager $userManager
	 * @param Model\CommentManager $commentManager
	 * @param Model\YoutuberManager $youtuberManager
	 * @param Model\ArticleManager $articleManager
	 */
	public function __construct(Model\FrontUserManager $userManager, Model\CommentManager $commentManager, Model\YoutuberManager $youtuberManager, Model\ArticleManager $articleManager)
	{
		parent::__construct();

		$this->userManager = $userManager;
		$this->commentManager = $commentManager;
		$this->youtuberManager = $youtuberManager;
		$this->articleManager = $articleManager;
	}


	/**
	 * Výpis do šablony.
	 * @param $id
	 * @param $nick
	 */
	public function renderDefault($id, $nick)
	{
		$this->template->userDetail = $this->userManager->getById($id);
		if ($this->ratings === NULL) {
			$this->ratings = $this->youtuberManager->getRatingOneUser($id);
		}
		$this->template->ratings = $this->ratings;
		$this->template->ratingCount = $this->youtuberManager->getRatingCount($id);
		if ($this->comments === NULL) {
			$this->comments = $this->commentManager->getCommentsOneUser($id);
		}
		$this->template->comments = $this->comments;
		$this->template->commentCount = $this->commentManager->getCommentCount($id);
		$this->template->likeCommentCount = $this->commentManager->getLikeCommentCount($id);
		$this->template->likeNewsCount = $this->articleManager->getLikeNewsCount($id);
	}


	/**
	 * Řazení hodnocení youtuberů.
	 * @param $id
	 * @param $sort
	 */
	public function handleSortYoutubers($id, $sort)
	{
		$this->ratings = $this->youtuberManager->getRatingOneUser($id, $sort);
		$this->sortYoutubers = $sort;
		if ($this->isAjax()) {
			$this->redrawControl('sortYoutubers');
		}
	}


	/**
	 * Řazení komentářů.
	 * @param $id
	 * @param $sort
	 */
	public function handleSortComments($id, $sort)
	{
		$this->comments = $this->commentManager->getCommentsOneUser($id, $sort);
		$this->sortComments = $sort;
		if ($this->isAjax()) {
			$this->redrawControl('sortComments');
		}
	}
}