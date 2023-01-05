<?php

namespace App\FrontModule\Presenters;

use App\Model;
use Nette\Application\UI\Form;


/**
 * Výpis seznamu novinek a detailu novinky.
 */
class ArticlePresenter extends BasePresenter
{
	const
		STEP = 5,
		STEP_COM = 5;

	private $articleManager;
	private $articles;
	private $newsComments;
	private $offset;
	private $sortArticles;
	private $noMoreBig;
	private $hodnoceni;


	/**
	 * HomepagePresenter constructor.
	 * @param Model\ArticleManager $articleManager
	 */
	public function __construct(Model\ArticleManager $articleManager)
	{
		parent::__construct();

		$this->articleManager = $articleManager;
	}


	/**
	 * Výpis seznamu novinek.
	 */
	public function renderList()
	{
		//vykreslení seznamu novinek do šablony
		if ($this->articles === NULL) {
			$this->articles = $this->articleManager->getArticlesBYSort(self::STEP);
		}
		$this->template->articles = $this->articles;

		// výchozí sortování potřebné pro tlačítko load more u novinek
		if ($this->sortArticles === NULL) {
			$this->sortArticles = 0;
		}
		$this->template->sortArticles = $this->sortArticles;

		// od kolikáté novinky se mají načítat po stisku načíst další
		if ($this->offset === NULL) {
			$this->offset = 0;
		}
		$this->template->offset = $this->offset;

		// po stisku tlačítka načíst další nechceme, aby se první další novinka zobrazila velká
		if ($this->noMoreBig === NULL) {
			$this->noMoreBig = false;
		}
		$this->template->noMoreBig = $this->noMoreBig;
	}


	/**
	 * Řazení novinek.
	 * @param $sort
	 */
	public function handleSortArticles(int $sort = 0)
	{
		$this->articles = $this->articleManager->getArticlesBySort(self::STEP, $sort);
		$this->sortArticles = $sort;
		if ($this->isAjax()) {
			$this->redrawControl('sortArticles');
		}
	}


	/**
	 * Načítání dalších novinek.
	 * @param $offset
	 * @param $sort
	 */
	public function handleMoreArticles($offset, $sort)
	{
		$offset += self::STEP;
		$this->articles = $this->articleManager->getArticlesBySort(self::STEP, $sort, $offset);
		$this->noMoreBig = true;
		$this->offset = $offset;
		$this->sortArticles = $sort;
		if ($this->isAjax()) {
			$this->redrawControl('moreArticles');
			$this->redrawControl('moreArticlesButton');
		}
	}


	/**
	 * Výpis novinky do šablony.
	 * @throws \Nette\Application\BadRequestException
	 */
	public function renderDetail($slug)
	{
		// předá novinku do šablony
		$article = $this->articleManager->getArticleSlug($slug);
		if (!$article) {
			$this->error('Stránka s novinkou nenalezena.');
		}
		$this->template->article = $article;

		// zobrazení možnosti lajkování novinek
		if ($this->userID) {
			$this->template->likes = $this->articleManager->getArticleLikes($this->userID, $article->id);
		}

		// další a předchozí novinka
		$next = $this->articleManager->getOtherArticle('>', $article->datum, 'datum ASC');
		$this->template->next = $next;
		$previous = $this->articleManager->getOtherArticle('<', $article->datum, 'datum DESC');
		$this->template->previous = $previous;

		// zobrazení komentářů
		if ($this->newsComments === NULL) {
			$this->newsComments = $this->articleManager->getCommentsById($article->id, self::STEP_COM);
		}
		$this->template->newsComments = $this->newsComments;

		// od kolikátého komentáře se mají načítat po stisku načíst další
		if ($this->offset === NULL)
		{
			$this->offset = 0;
		}
		$this->template->offset = $this->offset;
	}


	/**
	 * Lajkování novinek.
	 * @param $type
	 * @param $id
	 * @throws \Nette\Application\AbortException
	 */
	public function handleLikeArticle($type, $id)
	{
		$stmt = $this->articleManager->setLikeArticle($type, $id, $this->userID);
		$likeNumber = array($stmt->positive, $stmt->negative); //v databázovém dotazu dostanem součet lajků i dislajků
		$this->sendResponse(new \Nette\Application\Responses\JsonResponse($likeNumber)); //posíláme metodou JSON nové číslo
	}


	/**
	 * Formulář pro psaní komentářů k novinkám.
	 * @return Form
	 */
	protected function createComponentNewsCommentForm(): Form
	{
		$form = new Form;

		$form->addTextArea('komentar')
			->setRequired('Vyplň prosím komentář.')
			->addRule(Form::MAX_LENGTH, 'Komentář může mít maximálně %d znaků.', 1000);

		$form->addHidden('newsCommentSlug')
			->setDefaultValue($this->getParameter('slug'));

		$form->addSubmit('send', 'Odeslat komentář');

		$form->onSuccess[] = [$this, 'newsCommentFormSucceeded'];

		return $form;
	}


	/** Odeslání a zpracování formuláře s komentářem.
	 * @param Form $form
	 * @param $values
	 * @throws \Nette\Application\AbortException
	 */
	public function newsCommentFormSucceeded(Form $form, $values)
	{
		$this->articleManager->saveNewsComment($values, $this->userID);
		$this->flashMessage('Komentář byl úspěšně vložen!', 'alert-success');
		$this->redirect('this');
	}


	/**
	 * Načítání dalších komentářů.
	 * @param $offset
	 * @param $id
	 */
	public function handleMoreNewsComments($offset, $id)
	{
		$offset += self::STEP;
		$this->newsComments = $this->articleManager->loadMoreNewsComments($id, $offset, self::STEP_COM);
		$this->offset = $offset;
		if ($this->isAjax()) {
			$this->redrawControl('moreNewsCom');
			$this->redrawControl('moreNewsComButton');
		}
	}
}
