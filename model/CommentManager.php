<?php

namespace App\Model;

use Nette;


/**
 * Model pro práci s komentářema.
 */
class CommentManager extends DatabaseConnection
{
	const
		TABLE_NAME = 'db_komentare',
		COLUMN_ID = 'id',
		COLUMN_ID_UZIVATELE = 'id_uzivatele',
		COLUMN_ID_YOUTUBERI = 'id_youtuberi',
		COLUMN_KOMENTAR = 'komentar',
		COLUMN_RATING = 'rating';

	private $noticeManager;


	/**
	 * CommentManager constructor.
	 * @param Nette\Database\Context $database
	 * @param NoticeManager $noticeManager
	 */
	public function __construct(Nette\Database\Context $database, NoticeManager $noticeManager) {
		parent::__construct($database);

		$this->noticeManager = $noticeManager;
	}


	/**
	 * Získání celkového počtu komentářů pro administraci.
	 * @return int
	 */
	public function getNumberComments(): int
	{
		return $this->database->table(self::TABLE_NAME)->count('*');
	}


	/**
	 * Získání nejlepších komentářů na homepage.
	 * @return array
	 */
	public function getTopComments(): array
	{
		return $this->database->fetchAll('SELECT db_youtuberi.slug AS slug,
														db_youtuberi.foto AS foto,
                                                        db_uzivatele.nick AS nick,
                                                        db_uzivatele.slug AS userSlug,
                                                        db_komentare.komentar AS komentar,
                                                        db_komentare.datum AS datum,
                                                        db_komentare.id_uzivatele,
                                                        SUM(db_like.positive) AS hodnoceni
                                                 FROM   db_komentare 
                                                        LEFT JOIN db_uzivatele 
                                                          ON db_komentare.id_uzivatele = db_uzivatele.id 
                                                        LEFT JOIN db_youtuberi 
                                                          ON db_komentare.id_youtuberi = db_youtuberi.id 
													    LEFT JOIN db_like
                                                          ON db_komentare.id = db_like.id_komentare
                                                 GROUP BY db_like.id_komentare
                                                 ORDER  BY hodnoceni DESC, nick ASC 
                                                 LIMIT  5');
	}


	/**
	 * Uloží se lajky komentářů do databáze.
	 * @param string $type
	 * @param int $comId
	 * @param int $userId
	 * @return false|mixed
	 */
	public function setLikeComment(string $type, int $comId, int $userId)
	{
		if ($type === 'positive' || $type === 'negative') { //když uživatel ještě nelajkoval
			// zjistíme, jestli se někdo nepokouší hlasovat vícekrát (nemělo by to jít)
			$stmt = $this->database->table('db_like')
				->where('id_uzivatele = ? AND id_komentare = ?', $userId, $comId)
				->fetch();
			if ($stmt) {
				die('Hlasování je neplatné!');
			}
			$value = ($type === 'positive') ? 1 : -1; //jestli uživatel hodnotil pozitivně nebo negativně
			$this->database->table('db_like')
				->insert([
				'id_uzivatele' => $userId,
				'id_komentare' => $comId,
				'positive' => $value,
				]);
		} elseif ($type === 'positive-cancel' || $type === 'negative-cancel') { //když uživatel chce zrušit svůj (dis)lajk
			$this->database->table('db_like')
				->where('id_uzivatele = ?', $userId)
				->where('id_komentare = ?', $comId)
				->delete();
		} elseif ($type === 'positive-change' || $type === 'negative-change') { //když uživatel chce změnit lajk na dislajk a naopak
			$value = ($type === 'positive-change') ? 1 : -1; //nastavíme inverzní hodnotu
			$this->database->table('db_like')
				->where('id_uzivatele = ?', $userId)
				->where('id_komentare = ?', $comId)
				->update([
					'positive' => $value
				]);
		}

		//zjistíme aktuální počet pozitivních a negativních
		return $this->database->fetch('SELECT SUM(IF(db_like.positive = 1, 1, 0)) AS positive, SUM(IF(db_like.positive = -1, 1, 0)) AS negative 
													FROM db_komentare 
													LEFT JOIN db_like ON db_like.id_komentare = db_komentare.id
													WHERE id = ?
													GROUP BY db_komentare.id', $comId);
	}


	/**
	 * Najdeme v databázi, v kterých komentářích už uživatel hlasoval.
	 * @param int $id
	 * @return array|\Nette\Database\Table\IRow[]|\Nette\Database\Table\Selection
	 */
	public function getLikes(int $id)
	{
		$result = $this->database->table('db_like')
			->where('id_uzivatele = ?', $id)
			->fetchAll();
		if ($result) {
			foreach ($result as $row) {
				$arrayCom[$row->id_komentare] = $row->positive;
			}
		}
		else {
			$arrayCom[] = 'no';
		}
		return $arrayCom;
	}


	/**
	 * Získání a sortování prvních pěti komentářů u youtuberů.
	 * @param int $id
	 * @param int $step
	 * @param int $sort
	 * @param int $offset
	 * @return array
	 */
	public function getCommentsBySort(int $id, int $step, int $sort = 0, int $offset = 0 ): array
	{
		$order = $this->switchSort($sort);

		return $this->database->fetchAll('SELECT db_komentare.id AS id, nick, db_komentare.datum AS datum, komentar, SUM(IF(db_like.positive = 1, 1, 0)) AS positive, SUM(IF(db_like.positive = -1, 1, 0)) AS negative, rating, db_komentare.id_uzivatele, db_uzivatele.slug AS userSlug 
													FROM db_komentare 
													JOIN db_uzivatele ON db_komentare.id_uzivatele = db_uzivatele.id
													LEFT JOIN db_like ON db_like.id_komentare = db_komentare.id
													WHERE id_youtuberi = ?
													GROUP BY db_komentare.id 
													ORDER BY ' . $order . ' 
													LIMIT ?, ?', $id, $offset, $step);
	}


	/**
	 * Přepínač dle čeho chceme řadit výpis komentářů.
	 * @param $sort
	 * @return string
	 */
	public function switchSort(int $sort = 0): string
	{
		switch ($sort) {
			case 0:
				$order = 'datum DESC';
				break;
			case 1:
				$order = 'datum ASC';
				break;
			case 2:
				$order = 'SUM(IF(db_like.positive = 1, 1, 0))-SUM(IF(db_like.positive = -1, 1, 0)) DESC, datum DESC';
				break;
			case 3:
				$order = 'SUM(IF(db_like.positive = 1, 1, 0))-SUM(IF(db_like.positive = -1, 1, 0)) ASC, datum DESC';
				break;
			default:
				$order = 'datum DESC';
		}
		return $order;
	}


	/**
	 * Uložení komentáře od uživatele.
	 * @param $values
	 * @param int $userID
	 * @throws DuplicateComException
	 */
	public function saveComment ($values, int $userID)
	{
		// nejprve získám ID youtubera ze slugu
		$youtuber = $this->database->table('db_youtuberi')
			->where('slug = ?', $values->youtuberSlug)
			->fetch();

		// zjistíme, jestli se někdo nepokouší napsat víc komentářů k jenomu youtuberovi (nemělo by to jít)
		if ($this->getMonthCom($userID, $youtuber->id)) {
			throw new DuplicateComException('Druhý komentář během měsíce.');
		}

		// zjistíme, jaké hodnocení dal uživatel tento měsíc a přiřadíme ke komentáři (ve skutečnosti neověřujem datum, ale hodnotu 1 u aktual)
		$stmt = $this->database->table('db_rate')
			->select('hodnoceni')
			->where('id_uzivatele = ? AND id_youtuberi = ? AND aktual = ?', $userID, $youtuber->id, 1)
			->fetch();

		//záznam se vloží do databáze
		$this->database->table(self::TABLE_NAME)
			->insert([
				self::COLUMN_ID_UZIVATELE => $userID,
				self::COLUMN_ID_YOUTUBERI => $youtuber->id,
				self::COLUMN_KOMENTAR => $values->komentar,
				self::COLUMN_RATING => $stmt->hodnoceni,
			]);

		$this->noticeManager->setNotice('Vložen nový komentář','fa-comment'); //do tabulky oznámení vložíme informaci o vloženém komentáři
	}


	/**
	 * Zda uživatel v tomto měsíci již napsal komentář k určitému youtuberovi.
	 * @param $userId
	 * @param $youtuberId
	 * @return int
	 */
	public function getMonthCom(int $userId, int $youtuberId): int
	{
		$result = $this->database->query('SELECT * FROM db_komentare WHERE id_uzivatele = ? AND id_youtuberi = ? AND datum > DATE_SUB(NOW(), INTERVAL 1 MONTH)', $userId, $youtuberId);
		return $result->getRowCount();
	}


	/**
	 * Seznam komentářů do stránky detail uživatele.
	 * @param int $id
	 * @param int $sort
	 * @return array|Nette\Database\Table\IRow[]|Nette\Database\Table\Selection
	 */
	public function getCommentsOneUser(int $id, int $sort = 3)
	{
		$setSort = $this->switchSortOneUser($sort);
		return $this->database->fetchAll('SELECT db_youtuberi.slug AS slug,
														db_youtuberi.jmeno AS jmeno,
                                                        db_komentare.komentar AS komentar,
                                                        db_komentare.datum AS datum,
                                                        SUM(db_like.positive) AS hodnoceni
                                                 FROM   db_komentare 
                                                        LEFT JOIN db_youtuberi 
                                                          ON db_komentare.id_youtuberi = db_youtuberi.id 
													    LEFT JOIN db_like
                                                          ON db_komentare.id = db_like.id_komentare
                                                  WHERE db_komentare.id_uzivatele = ?
                                                  GROUP BY db_komentare.id
                                                  ORDER BY ' . $setSort, $id);
	}


	/**
	 * Přepínač dle čeho chceme řadit výpis hodnocení youtuberů.
	 * @param int $sort
	 * @return string
	 */
	private function switchSortOneUser(int $sort): string
	{
		switch ($sort) {
			case 0:
				$order = 'jmeno ASC';
				break;
			case 1:
				$order = 'hodnoceni DESC, jmeno ASC';
				break;
			case 2:
				$order = 'hodnoceni ASC, jmeno ASC';
				break;
			case 3:
				$order = 'datum DESC';
				break;
			case 4:
				$order = 'datum ASC';
				break;
			default:
				$order = 'datum DESC';
		}
		return $order;
	}


	/**
	 * Počet komentářů do stránky detail uživatele.
	 * @param int $id
	 * @return int
	 */
	public function getCommentCount(int $id): int
	{
		return $this->database->table('db_komentare')
			->where('id_uzivatele = ?', $id)
			->count('*');
	}


	/**
	 * Počet lajků komentářů do stránky detail uživatele.
	 * @param int $id
	 * @return int
	 */
	public function getLikeCommentCount(int $id): int
	{
		return $this->database->table('db_like')
			->where('id_uzivatele = ?', $id)
			->count('*');
	}
}

class DuplicateComException extends \Exception
{
}