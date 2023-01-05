<?php

namespace App\Model;

use Nette;
use Nette\Application\UI;
use Nette\Utils\DateTime;
use Nette\Utils\Strings;
use Tracy\Debugger;


/**
 * Model pro práci s youtuberama.
 */
class YoutuberManager extends DatabaseConnection
{
	/** Konstanty pro manipulaci s modelem. */
	const
		TABLE_NAME = 'db_youtuberi',
		COLUMN_ID = 'id',
		COLUMN_JMENO = 'jmeno',
		COLUMN_SLUG = 'slug',
		COLUMN_CHANNEL = 'channel',
		COLUMN_ODBERATELE = 'odberatele',
		COLUMN_ZHLEDNUTI = 'zhlednuti',
		COLUMN_URL_YOUTUBE = 'url_youtube',
		COLUMN_URL_VLASTNI = 'url_vlastni',
		COLUMN_POPIS = 'popis',
		COLUMN_FOTO = 'foto';


	private $noticeManager;
	private $youtubeapikey;
	private $minCountRateHP;
	private $dateRateHP;


	/**
	 * CategoryManager constructor.
	 * @param Nette\Database\Context $youtubeapikey
	 * @param Nette\Database\Context $database
	 * @param NoticeManager $noticeManager
	 */
	public function __construct($youtubeapikey, Nette\Database\Context $database, NoticeManager $noticeManager) {
		parent::__construct($database);

		$this->noticeManager = $noticeManager;
		$this->youtubeapikey = $youtubeapikey;
		$this->minCountRateHP = $this->getConfigHP()->minimum;
		$this->dateRateHP = $this->getConfigHP()->cas;
	}


	/**
	 * Získání konfigurace tabulky na HP.
	 * @return false|Nette\Database\Table\ActiveRow
	 */
	protected function getConfigHP() {
		return $this->database->table('db_konfig_hp')
			->fetch();
	}


	/**
	 * Získání počtu youtuberů pro nástěnku.
	 * @return int
	 */
	public function getNumberYoutubers(): int
	{
		return $this->database->table(self::TABLE_NAME)
			->count('*');
	}


	/**
	 * Získání jednoho youtubera dle ID.
	 * @param $id
	 * @return mixed
	 */
	public function getYoutuber($id)
	{
		return $this->database->table(self::TABLE_NAME)
			->where(self::COLUMN_ID . ' = ?', $id)
			->fetch();
	}


	/**
	 * Získání jednoho youtubera dle slugu.
	 * @param $slug
	 * @return mixed
	 */
	public function getYoutuberSlug($slug)
	{
		return $this->database->table(self::TABLE_NAME)
			->where(self::COLUMN_SLUG . ' = ?', $slug)
			->fetch();
	}


	/**
	 * Získání ratingu youtubera.
	 * @param int $id
	 * @return Nette\Database\Row
	 */
	public function getYoutuberRating( int $id): \Nette\Database\Row
	{
		return $this->database->fetch('SELECT AVG(hodnoceni) AS hodnoceni, SUM(pocet) AS pocet 
                                             FROM (
                                                  SELECT id_uzivatele, id_youtuberi, AVG(hodnoceni) AS hodnoceni, COUNT(*) AS pocet
                                                  FROM db_rate
                                                  GROUP BY id_uzivatele, id_youtuberi
                                                ) unikatni
                                             WHERE id_youtuberi = ? 
                                             GROUP BY id_youtuberi', $id);
	}


	/**
	 * Získání kategorií daného youtubera.
	 * @param int $id
	 * @return array
	 */
	public function getCatYoutuber(int $id): array
	{
		return $this->database->fetchAll('SELECT db_kategorie.id AS idKat, db_kategorie.nazev AS nazev, db_kategorie.slug AS slug FROM db_youtuberi JOIN db_you_vs_kat ON id_youtuberi=db_youtuberi.id JOIN db_kategorie ON id_kategorie=db_kategorie.id WHERE db_youtuberi.id = ?', $id);
	}


	/**
	 * Získání jednoho youtubera dle jména (pro autocomplete ve vyhledávači v menu).
	 * @param $jmeno
	 * @return mixed
	 */
	public function getYoutuberName($jmeno)
	{
		return $this->database->table(self::TABLE_NAME)
			->where(self::COLUMN_JMENO . ' = ?', $jmeno)
			->fetch();
	}


	/**
	 * Získání seznamu všech youtuberů.
	 * @return object
	 */
	public function getYoutubers()
	{
		return $this->database->table(self::TABLE_NAME);
	}


	/**
	 * Třídění zda se v tabulce na HP zobrazí youtubeři za poslední měsíc nebo týden (a absolutně nebo relativně).
	 * @param $sort
	 * @return string
	 */
	private function sortWhereHP(int $sort = 2): string
	{
		$start = new DateTime('last week Monday');
		$end = new DateTime('this week Monday');

		switch ($sort) {
			case 0:
				$order = "db_rate.datum BETWEEN '" . $start . "' AND '" . $end . "'";
				break;
			case 1:
				$order = 'db_rate.datum BETWEEN DATE_SUB(NOW(),INTERVAL 1 WEEK) and NOW()';
				break;
			case 2:
				$order = 'YEAR(db_rate.datum) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH) AND MONTH(db_rate.datum) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)';
				break;
			case 3:
				$order = 'db_rate.datum BETWEEN DATE_SUB(NOW(),INTERVAL 1 MONTH) and NOW()';
				break;
			default:
				$order = 'YEAR(db_rate.datum) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH) AND MONTH(db_rate.datum) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)';
		}
		return $order;
	}


	/**
	 * Žebříček youtuberů na hlavní stranu: zde nemusíme dělat průměr z průměru, od každého uživatele se započítává jen jeden hlas.
	 * @return array
	 */
	public function getYoutubersOnHP(): array
	{
		$where = $this->sortWhereHP($this->dateRateHP);

		return $this->database->fetchAll('SELECT AVG(hodnoceni) AS hodnoceni,
                                                        db_youtuberi.jmeno AS jmeno, 
                                                        db_youtuberi.slug AS slug,
                                                        db_youtuberi.foto AS foto 
                                                 FROM   db_rate 
                                                        JOIN db_youtuberi 
                                                          ON id_youtuberi = db_youtuberi.id 
													 WHERE ' . $where . '
                                                 GROUP  BY id_youtuberi
                                                 HAVING COUNT(hodnoceni) > ' .$this->minCountRateHP . '
                                                 ORDER  BY hodnoceni DESC, jmeno ASC
                                                 LIMIT  9');
	}


	/**
	 * Zobrazení u tabulky na HP, zda se jedná o týden nebo měsíc
	 * @return string
	 */
	public function dateOnTableYtHP(): string
	{
		switch ($this->dateRateHP) {
			case 0:
				$dateTableYt = 'týden';
				break;
			case 1:
				$dateTableYt = 'týden';
				break;
			case 2:
				$dateTableYt = 'měsíc';
				break;
			case 3:
				$dateTableYt = 'měsíc';
				break;
			default:
				$dateTableYt = 'týden';
		}
		return $dateTableYt;
	}


	/**
	 * Uložení nového youtubera.
	 * @param $values
	 * @throws \Nette\Application\UI\InvalidLinkException
	 */
	public function saveYoutuber($values)
	{
		$foto = $this->getPhotoUrl($values->channel);
		$jmeno = $this->getName($values->channel);
		$popis = $this->getDescription($values->channel);
		$odberatele = $this->getNumberSubscribers($values->channel);
		$zhlednuti = $this->getNumberView($values->channel);
		$slug = Strings::webalize($jmeno);

		//při vložení zjistíme id youtubera, kterého jsme vložili, abychom mu v jiné tabulce přidělili kategorie
		$id_youtuber = $this->database->table(self::TABLE_NAME)
			->insert([
				self::COLUMN_JMENO => $jmeno,
				self::COLUMN_SLUG => $slug,
				self::COLUMN_CHANNEL => $values->channel,
				self::COLUMN_ODBERATELE => $odberatele,
				self::COLUMN_ZHLEDNUTI => $zhlednuti,
				self::COLUMN_URL_VLASTNI => $values->url_vlastni,
				self::COLUMN_POPIS => $popis,
				self::COLUMN_FOTO => $foto,
			])->id;

		//vložíme do jiné tabulky záznam o přidělených kategoriích
		foreach ($values->kategorie as $value) {
			$this->database->table('db_you_vs_kat')->insert([
				'id_youtuberi' => $id_youtuber,
				'id_kategorie' => $value,
			]);
		}

		//vložíme první falešné hlasování(3) od uživatele s ID 1, aby tam byl nějaký údaj
		$this->database->table('db_rate')->insert([
			'id_uzivatele' => 1,
			'id_youtuberi' => $id_youtuber,
			'hodnoceni' => 3,
		]);

		//vložíme 50 nejlepších videí do databáze
		$this->getBestVideos($values->channel, $id_youtuber);

		$this->noticeManager->setNotice('Vložen nový youtuber','fa-plus-square'); //do tabulky oznámení vložíme informaci o vloženém youtuberovi
	}


	/**
	 * Pomocí channelID najdeme URL fotky.
	 * @param $channel
	 * @return mixed
	 * @throws UI\InvalidLinkException
	 */
	public function getPhotoUrl($channel)
	{
		$request = 'https://www.googleapis.com/youtube/v3/channels?part=snippet&id=' . $channel . '&fields=items%2Fsnippet%2Fthumbnails&key=' . $this->youtubeapikey;
		$response = file_get_contents($request);
		$searchResponse = json_decode($response, true);
		$data = $searchResponse['items'];

		//pokud je platná podmínka, znamená to, že jsme vložili špatný channelID
		if(empty($data)) {
			throw new UI\InvalidLinkException('Neplatné channelID - ' . $channel . '!');
		}
		return $data[0]['snippet']['thumbnails']['medium']['url'];
	}


	/**
	 * Pomocí channelID najdeme nick youtubera.
	 * @param $channel
	 * @return mixed
	 * @throws UI\InvalidLinkException
	 */
	public function getName($channel)
	{
		$request = 'https://www.googleapis.com/youtube/v3/channels?part=snippet&id=' . $channel . '&key=' . $this->youtubeapikey;
		$response = file_get_contents($request);
		$searchResponse = json_decode($response, true);
		$data = $searchResponse['items'];

		//pokud je platná podmínka, znamená to, že jsme vložili špatný channelID
		if(empty($data)) {
			throw new UI\InvalidLinkException('Neplatné channelID - ' . $channel . '!');
		}
		return $data[0]['snippet']['title'];
	}


	/**
	 * Pomocí channelID najdeme popis youtubera.
	 * @param $channel
	 * @return mixed
	 * @throws UI\InvalidLinkException
	 */
	public function getDescription($channel)
	{
		$request = 'https://www.googleapis.com/youtube/v3/channels?part=snippet&id=' . $channel . '&key=' . $this->youtubeapikey;
		$response = file_get_contents($request);
		$searchResponse = json_decode($response, true);
		$data = $searchResponse['items'];

		//pokud je platná podmínka, znamená to, že jsme vložili špatný channelID
		if(empty($data)) {
			throw new UI\InvalidLinkException('Neplatné channelID - ' . $channel . '!');
		}
		return $data[0]['snippet']['description'];
	}


	/**
	 * Pomocí channelID zjistíme počet odběratelů.
	 * @param $channel
	 * @return mixed
	 * @throws \Nette\Application\UI\InvalidLinkException
	 */
	public function getNumberSubscribers($channel)
	{
		$request = 'https://www.googleapis.com/youtube/v3/channels?part=statistics&id=' . $channel . '&key=' . $this->youtubeapikey;
		$response = file_get_contents($request);
		$searchResponse = json_decode($response, true);
		$data = $searchResponse['items'];
		//pokud je platná podmínka, znamená to, že jsme vložili špatný channelID
		if(empty($data)) {
			throw new UI\InvalidLinkException('Neplatné channelID - ' . $channel . '!');
		}
		return $data[0]['statistics']['subscriberCount'];
	}


	/**
	 * Pomocí channelID zjistíme počet zhlédnutí.
	 * @param $channel
	 * @return mixed
	 * @throws \Nette\Application\UI\InvalidLinkException
	 */
	public function getNumberView($channel)
	{
		$request = 'https://www.googleapis.com/youtube/v3/channels?part=statistics&id=' . $channel . '&key=' . $this->youtubeapikey;
		$response = file_get_contents($request);
		$searchResponse = json_decode($response, true);
		$data = $searchResponse['items'];
		//pokud je platná podmínka, znamená to, že jsme vložili špatný channelID
		if(empty($data)) {
			throw new UI\InvalidLinkException('Neplatné channelID - ' . $channel . '!');
		}
		return $data[0]['statistics']['viewCount'];
	}


	/**
	 * Pomocí channel-ID zjistíme 50 nejsledovanějších videí.
	 * @param $channel
	 * @param $id_youtuber
	 */
	public function getBestVideos($channel, $id_youtuber)
	{
		$request = 'https://www.googleapis.com/youtube/v3/search?part=snippet&order=viewCount&maxResults=50&channelId=' . $channel . '&key=' . $this->youtubeapikey;
		$response = file_get_contents($request);
		$searchResponse = json_decode($response,true);
		$data = $searchResponse['items'];

		//vložíme videa do databáze
		foreach($data as $value) {
			if (!empty($value['id']['videoId'])) {
				$videoId = $value['id']['videoId'];
				$title = $value['snippet']['title'];

				$this->database->table('db_videa')
					->insert([
					'id_youtuberi' => $id_youtuber,
					'id_video' => $videoId,
					'titulek' => $title,
				]);
			}
		}
	}


	/**
	 * Zjištění nových videí od konkrétního youtubera.
	 * @param $channel
	 * @return mixed
	 */
	public function getNewVideos($channel)
	{
		// rozparsujeme stránku dle channel ID a zjistíme uploadsID, což potřebujem k najití videí
		$request = 'https://www.googleapis.com/youtube/v3/channels?key=' . $this->youtubeapikey . '&id='.$channel.'&part=contentDetails';
		$response = file_get_contents($request);
		$searchResponse = json_decode($response,true);
		$data = $searchResponse['items'];
		if (isset($data[0]['contentDetails']['relatedPlaylists']['uploads']))
		{
			$pid = @$data[0]['contentDetails']['relatedPlaylists']['uploads'];

			// rozparsujeme stránku s použitím uploadsID a zjistíme všechny title a videoId kvůli URL
			$request = 'https://www.googleapis.com/youtube/v3/playlistItems?key=' . $this->youtubeapikey . '&playlistId=' . $pid . '&part=snippet&maxResults=50';
			$response = file_get_contents($request);
			$searchResponse = json_decode($response,true);
			return $searchResponse['items'];
		} else {
			Debugger::log('Channel ' . $channel . ' neexistuje');
			return false;
		}
	}


	/**
	 * Uložení editace youtubera.
	 * @param $values
	 */
	public function updateYoutuber($values)
	{
		$slug = Strings::webalize($values->jmeno);

		$this->database->table(self::TABLE_NAME)
			->where(self::COLUMN_ID . ' = ?', $values->id)
			->update([
			self::COLUMN_JMENO => $values->jmeno,
			self::COLUMN_SLUG => $slug,
			self::COLUMN_URL_VLASTNI => $values->url_vlastni,
			self::COLUMN_POPIS => $values->popis,
		]);

		//z databáze se smaže napojení youtubera na kategorie a vytvoří se nové napojení
		$this->database->table('db_you_vs_kat')
			->where('id_youtuberi = ?', $values->id)
			->delete();

		foreach ($values->kategorie as $value) {
			$this->database->table('db_you_vs_kat')->insert([
				'id_youtuberi' => $values->id,
				'id_kategorie' => $value,
			]);
		};

		$this->noticeManager->setNotice('Upraven youtuber','fa-pencil'); //do tabulky oznámení vložíme informaci o vloženém youtuberovi
	}


	/**
	 * Mazání youtuberů z tabulky datagridu.
	 * @param $id
	 * @return int
	 */
	public function deleteYoutuber(int $id): int
	{
		$this->noticeManager->setNotice('Smazán youtuber','fa-bomb'); //do tabulky oznámení vložíme informaci o smazání youtubera

		return $this->database->table(self::TABLE_NAME)
			->where(self::COLUMN_ID . ' = ?', $id)
			->delete();
	}


	/**
	 * Seznam kategorií youtuberů.
	 * @param $id
	 * @return object
	 */
	public function getListCategories(int $id)
	{
		return $this->database->table('db_you_vs_kat')
			->where('id_youtuberi = ?', $id)
			->fetchAll();
	}


	/**
	 * Zjištění, zda uživatel už tento měsíc hodnotil youtubera.
	 * @param int $id_youtuberi
	 * @param int $id_uzivatele
	 * @return bool
	 */
	public function getRateMonth(int $id_youtuberi, int $id_uzivatele): bool
	{
		$stmt = $this->database->fetch('SELECT * FROM db_rate WHERE id_youtuberi = ? AND id_uzivatele = ? AND datum > DATE_SUB(NOW(), INTERVAL 1 MONTH)', $id_youtuberi, $id_uzivatele);
		if (!empty($stmt)) {
			return true;
		}
		return false;
	}


	/**
	 * Uložení ratingu youtubera.
	 * @param int $id_youtuberi
	 * @param int $vote_sent
	 * @param int $id_uzivatele
	 */
	public function setRateYoutuber(int $id_youtuberi, int $vote_sent, int $id_uzivatele)
	{
		if ($vote_sent > 5 || $vote_sent < 1) {
			die("Hlasování je neplatné!"); //nikdy by k tomuto nemělo dojít
		}

		// zjistíme, jestli se někdo nepokouší hlasovat vícekrát během měsíce (nemělo by to jít)
		$stmt = $this->database->fetch('SELECT * FROM db_rate WHERE id_uzivatele = ? AND id_youtuberi = ? AND datum > DATE_SUB(NOW(), INTERVAL 1 MONTH)', $id_uzivatele, $id_youtuberi);
		if ($stmt) {
			die("Hlasování je neplatné!");
		}

		// zjistíme, jestli už uživatel hlasoval před více než měsícem
		$stmt = $this->database->fetch('SELECT * FROM db_rate WHERE id_uzivatele = ? AND id_youtuberi = ?', $id_uzivatele, $id_youtuberi);
		if ($stmt) {
			//víc než měsíc starý záznam změníme na 0
			$this->database->table('db_rate')
				->where('id_uzivatele = ? AND id_youtuberi = ?', $id_uzivatele, $id_youtuberi)
				->update([
					'aktual' => 0
				]);
		}

		//dle id uživatele zapíšem jeho like do samostatné tabulky
		$this->database->table('db_rate')
			->insert([
				'id_uzivatele' => $id_uzivatele,
				'id_youtuberi' => $id_youtuberi,
				'hodnoceni' => $vote_sent,
				'aktual' => 1,
			]);
	}


	/**
	 * Výpis 50 nejlepších videí z databáze.
	 * @param int $id
	 * @return array|Nette\Database\Table\IRow[]|Nette\Database\Table\Selection
	 */
	public function getBestVideosFromDB(int $id)
	{
		return $this->database->table('db_videa')
			->where('id_youtuberi = ?', $id)
			->fetchAll();
	}


	/**
	 * Zjistíme zda uživatel hodnotil, aby moh napsat komentář
	 * @param int $userId
	 * @param int $youtuberId
	 * @return int
	 */
	public function getMonthRate(int $userId, int $youtuberId): int
	{
		$result = $this->database->query('SELECT * FROM db_rate WHERE id_uzivatele = ? AND id_youtuberi = ? AND datum > DATE_SUB(NOW(), INTERVAL 1 MONTH)', $userId, $youtuberId);
		return $result->getRowCount();
	}


	/**
	 * Seznam hodnocení jednoho užiuvatele do stránky detail uživatele.
	 * @param int $id
	 * @param int $sort
	 * @return array|Nette\Database\Table\IRow[]|Nette\Database\Table\Selection
	 */
	public function getRatingOneUser(int $id, int $sort = 3)
	{
		$setSort = $this->switchSortOneUser($sort);
		return $this->database->fetchAll('SELECT jmeno, slug, hodnoceni, db_rate.datum AS datum FROM db_rate JOIN db_youtuberi ON db_youtuberi.id = db_rate.id_youtuberi WHERE id_uzivatele = ? ORDER BY ' . $setSort, $id);
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
	 * Počet hodnocení youtuberů do stránky detail uživatele.
	 * @param int $id
	 * @return int
	 */
	public function getRatingCount(int $id): int
	{
		return $this->database->table('db_rate')
			->where('id_uzivatele = ?', $id)
			->count('*');
	}


	/**
	 * Update youtuberů (odběratelé)
	 * @param int $min
	 * @param int $max
	 * @return int
	 */
	public function updateYoutubersDates(int $min, int $max): int
	{
		$youtubers = $this->getYoutubers()->select(self::COLUMN_ID . ',' . self::COLUMN_CHANNEL . ',' .  self::COLUMN_ODBERATELE)->where(self::COLUMN_ID . ' BETWEEN ' . $min . ' AND ' . $max)->fetchAll();

		foreach ($youtubers as $youtuber) {
			$numberSub = $this->getNumberSubscribers($youtuber->channel);
			$numberView = $this->getNumberView($youtuber->channel);
			$this->database->table(self::TABLE_NAME)
				->where(self::COLUMN_ID . ' = ' . $youtuber->id)
				->update([
				self::COLUMN_ODBERATELE => $numberSub,
				self::COLUMN_ZHLEDNUTI => $numberView,
			]);
		}

		return \count($youtubers);
	}
}