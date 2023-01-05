<?php

namespace App\FrontModule\Presenters;

use App\Model;
use Nette\Application\UI\Form;
use Tracy\Debugger;


/**
 * Stránka s detailem youtubera.
 */
class YoutuberPresenter extends BasePresenter
{
	const
		STEP = 5;

	private $youtuberManager;
	private $articleManager;
	private $commentManager;
	private $comments;
	private $offset;
	private $sortCom;
	private $activeRating;
	private $stars = array();
	private $rating;
	private $monthRated;
	private $videos;
	private $newVideos; // určuje, zda vypisujeme nové nebo nejlepší videa


	/**
	 * HomepagePresenter constructor.
	 * @param Model\YoutuberManager $youtuberManager
	 * @param Model\ArticleManager $articleManager
	 * @param Model\CommentManager $commentManager
	 */
	public function __construct(Model\YoutuberManager $youtuberManager, Model\ArticleManager $articleManager,  Model\CommentManager $commentManager)
	{
		parent::__construct();

		$this->youtuberManager = $youtuberManager;
		$this->articleManager = $articleManager;
		$this->commentManager = $commentManager;
	}


	/**
	 * Formulář pro psaní komentářů k youtuberům (první fáze před potvrzením).
	 * @return Form
	 */
	protected function createComponentPrepareCommentForm(): Form
	{
		$form = new Form;

		$form->addTextArea('komentar')
			->setRequired('Vyplň prosím komentář.')
			->addRule(Form::MAX_LENGTH, 'Komentář může mít maximálně %d znaků.', 1500);

		$form->addSubmit('send', 'Odeslat komentář');

		return $form;
	}


	/**
	 * Formulář pro psaní komentářů k youtuberům (v modálním okně s potvrzením).
	 * @return Form
	 */
	protected function createComponentWriteCommentForm(): Form
	{
		$form = new Form;

		$form->addTextArea('komentar')
			->setRequired('Vyplň prosím komentář.')
			->addRule(Form::MAX_LENGTH, 'Komentář může mít maximálně %d znaků.', 1500);

		$form->addHidden('youtuberSlug')
			->setDefaultValue($this->getParameter('slug'));

		$form->addSubmit('send', 'Odeslat');

		$form->onSuccess[] = [$this, 'writeCommentFormSucceeded'];

		return $form;
	}


	/**
	 * Odeslání a zpracování editovacího formuláře.
	 * @param Form $form
	 * @param $values
	 * @throws \Nette\Application\AbortException
	 */
	public function writeCommentFormSucceeded(Form $form, $values)
	{
		try {
			$this->commentManager->saveComment($values, $this->userID);
			$this->flashMessage('Komentář byl úspěšně vložen!', 'alert-success');
			$this->redirect('this');
		} catch (Model\DuplicateComException $e) {
			Debugger::log($e);
			$this->flashMessage('Pokoušíš se napsat druhý komentář během měsíce!', 'alert-error');
			$this->redirect('this');
		}
	}


	/**
	 * Informace o youtuberovi do šablony.
	 * @param $slug
	 * @throws \Nette\Application\BadRequestException
	 * @throws \Nette\Application\UI\InvalidLinkException
	 */
	public function renderDefault($slug)
	{
		// zobrazení údajů o youtuberovi
		$youtuber = $this->youtuberManager->getYoutuberSlug($slug);
		if (!$youtuber) {
			$this->error('Stránka s youtuberem nenalezena.');
		}
		$this->template->youtuber = $youtuber; // Předá youtubera do šablony.

		// dotaz, zda uživatele je přihlášet a tento měsíc hlasoval (rating)
		if ($this->userID) {
			if ($this->youtuberManager->getRateMonth($youtuber->id, $this->userID)) {
				$this->activeRating = false;
			} else {
				$this->activeRating = true; //je přihlášený a zároveň nehlasoval tento měsíc - může hlasovat
			}
		} else {
			$this->activeRating = false;
		}

		// zobrazení ratingu
		$this->rating = $this->youtuberManager->getYoutuberRating($youtuber->id);
		$this->template->rating = $this->rating;

		for ($i=1; $i<6; $i++) {
			if ($this->rating->hodnoceni + 0.5 > $i) {
				$class = 'star_' . $i . ' ratings_vote';
			} else {
				$class = 'star_' . $i;
			}

			if ($this->activeRating) {
				$this->stars[$i] = '<a href="' . $this->link('rateYoutuber!', $youtuber->id, $i) . '" class="ratings_stars ' . $class . '"></a>';
			} else {
				$this->stars[$i] = '<a class="ratings_stars1 ' . $class . '"></a>';
			}
		}
		$this->template->stars = $this->stars;

		// zobrazení kategorií youtubera
		$catYoutuber = $this->youtuberManager->getCatYoutuber($youtuber->id);
		$this->template->catYoutuber = $catYoutuber;

		// zobrazení seznamu videí
		if ($this->videos === NULL) {
			$this->videos = $this->youtuberManager->getNewVideos($youtuber->channel);
			$this->newVideos = true;
		}
		$this->template->videos = $this->videos;
		$this->template->newVideos = $this->newVideos;

		// výpis článků o youtuberovi
		$this->template->youtuberArticles = $this->articleManager->getOneYoutuberArticles($youtuber->id);

		//dotaz, zda uživatel už v tomto měsíci psal komentář
		if ($this->userID) {
			$this->template->monthCom = $this->commentManager->getMonthCom($this->userID, $youtuber->id);
		} else {
			$this->template->monthCom = false;
		}

		//dotaz, zda uživatel v tomto měsíc již hodnotil (aby moh psát komentář a nemoh znovu hodnotit)
		if ($this->userID) {
			$this->monthRated = $this->youtuberManager->getMonthRate($this->userID, $youtuber->id);
		} else {
			$this->monthRated = false;
		}
		$this->template->monthRated = $this->monthRated;

		// zobrazení komentářů
		if ($this->comments === NULL) {
			$this->comments = $this->commentManager->getCommentsBySort($youtuber->id, self::STEP);
		}
		$this->template->comments = $this->comments;

		// zobrazení možnosti lajkování komentářů
		if ($this->userID) {
			$this->template->likes = $this->commentManager->getLikes($this->userID);
		}
		else {
			$this->template->likes = NULL;
		}

		// výchozí sortování potřebné pro tlačítko load more u komentářů
		if ($this->sortCom === NULL) {
			$this->sortCom = 0;
		}
		$this->template->sortCom = $this->sortCom;

		// od kolikátého komentáře se mají načítat po stisku načíst další
		if ($this->offset === NULL) {
			$this->offset = 0;
		}
		$this->template->offset = $this->offset;
	}


	/**
	 * Řazení komentářů.
	 * @param $id
	 * @param $sort
	 */
	public function handleSortComments(int $sort = 0, $id)
	{
		$this->comments = $this->commentManager->getCommentsBySort($id, self::STEP, $sort);
		$this->sortCom = $sort;
		if ($this->isAjax()) {
			$this->redrawControl('sortCom');
		}
	}


	/**
	 * Načítání dalších komentářů.
	 * @param $offset
	 * @param $id
	 * @param $sort
	 */
	public function handleMoreComments($offset, $id, $sort)
	{
		$offset += self::STEP;
		$this->comments = $this->commentManager->getCommentsBySort($id, self::STEP, $sort, $offset);
		$this->offset = $offset;
		$this->sortCom = $sort;
		if ($this->isAjax()) {
			$this->redrawControl('moreCom');
			$this->redrawControl('moreComButton');
		}
	}


	/**
	 * Změna čísla a zašedění po hlasování.
	 * @param $type
	 * @param $comId
	 * @throws \Nette\Application\AbortException
	 */
	public function handleLikeComment($type, $comId)
	{
		$stmt = $this->commentManager->setLikeComment($type, $comId, $this->userID);
		$likeNumber = array($stmt->positive, $stmt->negative); //v databázovém dotazu dostanem součet lajků i dislajků
		$this->sendResponse(new \Nette\Application\Responses\JsonResponse($likeNumber)); //posíláme metodou JSON nové číslo
	}


	/**
	 * Rating youtuberů.
	 * @param $id_youtuberi
	 * @param $stars
	 * @throws \Nette\Application\AbortException
	 */
	public function handleRateYoutuber($id_youtuberi, $stars)
	{
		// uloží se rating
		$vote_sent = preg_replace('/\D/', '', $stars);
		$this->youtuberManager->setRateYoutuber($id_youtuberi, $vote_sent, $this->userID);

		$this->flashMessage('Hodnocení proběhlo úspěšně, nyní můžeš napsat komentář!', 'alert-success');

		$this->redirect('this');
	}


	/**
	 * Řazení videí.
	 * @param int $sort
	 * @param $id
	 * @param $channel
	 */
	public function handleSortVideos(int $sort = 0, $id, $channel)
	{
		if ($sort === 0) {
			$this->videos = $this->youtuberManager->getNewVideos($channel);
			$this->newVideos = true;
		}
		else {
			$this->videos = $this->youtuberManager->getBestVideosFromDB($id);
			$this->newVideos = false;
		}
		if ($this->isAjax()) {
			$this->redrawControl('sortVideos');
		}
	}
}