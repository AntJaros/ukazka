<?php

namespace App\Model;

use Nette;
use Nette\Utils\Image;
use Nette\Utils\Strings;


/**
 * Model pro práci s kategoriema.
 */
class ArticleManager extends DatabaseConnection
{

	/** Konstanty pro manipulaci s modelem. */
	const
		TABLE_NAME = 'db_novinky',
		COLUMN_ID = 'id',
		COLUMN_NADPIS = 'nadpis',
		COLUMN_SLUG ='slug',
		COLUMN_TEXT = 'text',
		COLUMN_OBRAZEK = 'obrazek',
		COLUMN_DATUM = 'datum',
		IMG_WIDTH = 720,
		IMG_HEIGHT = 377,
		IMG_WIDTH_SMALL = 450;

	private $imageEditor;
	public $path;
	private $noticeManager;


	/**
	 * ArticleManager constructor.
	 * @param Nette\Database\Context $database
	 * @param ImageEditor $imageEditor
	 * @param NoticeManager $noticeManager
	 */
	public function __construct(Nette\Database\Context $database, ImageEditor $imageEditor, NoticeManager $noticeManager) {
		parent::__construct($database);

		$this->imageEditor = $imageEditor;
		$this->path = $imageEditor->getDir();
		$this->noticeManager = $noticeManager;
	}


	/**
	 * Počet novinek.
	 * @return int
	 */
	public function getNumberArticles(): int
	{
		return $this->database->table(self::TABLE_NAME)->count('*');
	}


	/**
	 * Uloží novinku do databáze.
	 * @param $nadpis
	 * @param $hl_obr
	 * @param $youtuberi
	 * @param $text
	 * @throws \App\Model\ForeignException
	 */
	public function saveArticle($nadpis, $hl_obr, $youtuberi, $text)
	{
		$image = Image::fromFile($hl_obr);

		// kvůli serverovým omezením nesmí být nahrán obrázek s velkými rozměry
		$this->imageEditor->checkResolution($image->width, $image->height);

		if ($image->width !== self::IMG_WIDTH OR $image->height !== self::IMG_HEIGHT) {
			$image->resize(self::IMG_WIDTH,self::IMG_HEIGHT, Image::EXACT);
		}
		$image->sharpen();

		// přejmenuje se soubor na unikátní název dle času uložení
		$extension = explode('.', $hl_obr->name);
		$extension = end($extension);
		$new_name = 'nov_' . Time();
		$new_name_small = 'nov_' . Time() . '-small';
		$new_name = $new_name . '.' . $extension ;
		$new_name_small = $new_name_small . '.' . $extension ;
		$image->save($this->path . DIRECTORY_SEPARATOR . 'novinky/' . $new_name);

		$image->resize(self::IMG_WIDTH_SMALL, null);
		$image->sharpen();
		$image->save($this->path . DIRECTORY_SEPARATOR . 'novinky/' . $new_name_small);

		$slug = Strings::webalize($nadpis);
		$url_obrazek = 'novinky/' . $new_name;

		//musíme zjistit id nového článku pro uložení do jiné tabulky kterých youtuberů se článek týká
		$id_article = $this->database->table(self::TABLE_NAME)->insert([
			self::COLUMN_NADPIS => $nadpis,
			self::COLUMN_SLUG => $slug,
			self::COLUMN_TEXT => $text,
			self::COLUMN_OBRAZEK => $url_obrazek,
		])->id;

		//uloží se seznam youtuberů, kterých se článek týká
		if (!empty($youtuberi)) {
			$vals = explode(',', $youtuberi);

			//smazání mezer
			foreach($vals as $key => $val) {
				$vals[$key] = trim($val);
				if (ctype_digit($vals[$key])) {
					try {
						$this->database->table('db_nov_vs_you')
							->insert([
								'id_novinky' => $id_article,
								'id_youtuberi' => $vals[$key],
							]);
					} catch (Nette\Database\ForeignKeyConstraintViolationException $e) {
						throw new ForeignException('Youtuber se zadaným ID neexistuje.');
					}
				}
			}
		}

		$this->noticeManager->setNotice('Vložena novinka','fa-file-text-o'); //do tabulky oznámení vložíme informaci o vložení novinky
	}


	/**
	 * Editace novinky.
	 * @param $id
	 * @param $nadpis
	 * @param $hl_obr
	 * @param $youtuberi
	 * @param $text
	 * @throws ForeignException
	 */
	public function updateArticle($id, $nadpis, $hl_obr, $youtuberi, $text)
	{
		if ($hl_obr->name) {

			$image = Image::fromFile($hl_obr);

			// kvůli serverovým omezením nesmí být nahrán obrázek s velkými rozměry
			$this->imageEditor->checkResolution($image->width, $image->height);

			if ($image->width !== self::IMG_WIDTH OR $image->height !== self::IMG_HEIGHT) {
				$image->resize(self::IMG_WIDTH,self::IMG_HEIGHT, Image::EXACT);
			}
			$image->sharpen();

			//z FTP smažeme původní obrázek
			$oldRecord = $this->getArticle($id);
			$oldObrazek = $oldRecord->obrazek;
			//jestliže je cesta v databázi a zároveň původní obrázek existuje, smažeme ho
			if($oldObrazek && file_exists($this->path . DIRECTORY_SEPARATOR . $oldObrazek)) {
				unlink($this->path . DIRECTORY_SEPARATOR . $oldObrazek);
				$oldObrazekSmall = str_replace('.', '-small.', $oldObrazek);
				if(file_exists($this->path . DIRECTORY_SEPARATOR . $oldObrazekSmall)) {
					unlink($this->path . DIRECTORY_SEPARATOR . $oldObrazekSmall);
				}
			}

			// přejmenuje se soubor na unikátní název dle času uložení
			$extension = explode('.', $hl_obr->name);
			$extension = end($extension);
			$new_name = 'nov_' . Time();
			$new_name_small = 'nov_' . Time() . '-small';
			$new_name = $new_name . '.' . $extension ;
			$new_name_small = $new_name_small . '.' . $extension ;
			$image->save($this->path . DIRECTORY_SEPARATOR . 'novinky/' . $new_name);

			$image->resize(self::IMG_WIDTH_SMALL, null);
			$image->sharpen();
			$image->save($this->path . DIRECTORY_SEPARATOR . 'novinky/' . $new_name_small);

			$slug = Strings::webalize($nadpis);
			$url_obrazek = 'novinky/' . $new_name;
			$this->database->table(self::TABLE_NAME)
				->where(self::COLUMN_ID . '=?', $id)
				->update([
				self::COLUMN_NADPIS => $nadpis,
				self::COLUMN_SLUG => $slug,
				self::COLUMN_TEXT => $text,
				self::COLUMN_OBRAZEK => $url_obrazek,
			]);
		}
		else {
			$slug = Strings::webalize($nadpis);

			$this->database->table(self::TABLE_NAME)
				->where(self::COLUMN_ID . ' =?', $id)
				->update([
					self::COLUMN_NADPIS => $nadpis,
					self::COLUMN_SLUG => $slug,
					self::COLUMN_TEXT => $text,
				]);
		}

		//smaže se a updatuje se seznam youtuberů, kterých se článek týká
		if (!empty($youtuberi)) {
			$this->database->table('db_nov_vs_you')
				->where('id_novinky = ?', $id)
				->delete();

			$vals = explode(',', $youtuberi);
			//smazání mezer
			foreach($vals as $key => $val) {
				$vals[$key] = trim($val);
				if (ctype_digit($vals[$key])) {
					try {
						$this->database->table('db_nov_vs_you')
							->insert([
								'id_novinky' => $id,
								'id_youtuberi' => $vals[$key],
							]);
					} catch (Nette\Database\ForeignKeyConstraintViolationException $e) {
						throw new ForeignException('Youtuber se zadaným ID neexistuje.');
					}
				}
			}
		}

		$this->noticeManager->setNotice('Editována novinka','fa-pencil-square-o'); //do tabulky oznámení vložíme informaci o editacií novinky
	}


	/**
	 * Získání jedné novinky dle ID.
	 * @param $id
	 * @return mixed
	 */
	public function getArticle($id)
	{
		return $this->database->table(self::TABLE_NAME)
			->where(self::COLUMN_ID . ' = ?', $id)
			->fetch();

	}


	/**
	 * Získání jedné novinky dle slugu.
	 * @param $slug
	 * @return mixed
	 */
	public function getArticleSlug($slug)
	{
		return $this->database->fetch('SELECT id, nadpis, slug, text, obrazek, db_novinky.datum AS datum, SUM(IF(db_novinky_like.positive = 1, 1, 0)) AS positive, SUM(IF(db_novinky_like.positive = -1, 1, 0)) AS negative 
													FROM db_novinky 
													LEFT JOIN db_novinky_like ON db_novinky_like.id_novinky = db_novinky.id
													WHERE slug = ?
													GROUP BY id', $slug);
	}


	/**
	 * Získáme všechny novinky.
	 * @return Nette\Database\Table\Selection
	 */
	public function getArticles(): \Nette\Database\Table\Selection
	{
		return $this->database->table(self::TABLE_NAME);
	}


	/**
	 * Mazání novinek z tabulky datagridu.
	 * @param $id
	 */
	public function deleteArticle(int $id)
	{
		//z FTP smažeme původní obrázek
		$oldRecord = $this->getArticle($id);
		$oldObrazek = $oldRecord->obrazek;
		if($oldObrazek && file_exists($this->path . DIRECTORY_SEPARATOR . $oldObrazek)) {
			unlink($this->path . DIRECTORY_SEPARATOR . $oldObrazek);
			$oldObrazekSmall = str_replace('.', '-small.', $oldObrazek);
			if(file_exists($this->path . DIRECTORY_SEPARATOR . $oldObrazekSmall)) {
				unlink($this->path . DIRECTORY_SEPARATOR . $oldObrazekSmall);
			}
		}

		$this->database->table(self::TABLE_NAME)
			->where(self::COLUMN_ID . ' = ?', $id)
			->delete();

		$this->noticeManager->setNotice('Smazána novinka','fa-trash'); //do tabulky oznámení vložíme informaci o smazání novinky
	}


	/**
	 * Nahrávání obrázků na FTP pomocí WYSIWYGU Summernote.
	 * @param $obrazek
	 * @return array
	 * @throws \Nette\Utils\ImageException
	 * @throws \Nette\Utils\UnknownImageFileException
	 * @throws \Nette\NotSupportedException
	 */
	public function uploadImage($obrazek): array
	{
		$image = Image::fromFile($obrazek);

		// kvůli serverovým omezením nesmí být nahrán obrázek s velkými rozměry
		$this->imageEditor->checkResolution($image->width, $image->height);

		// přejmenuje se soubor na unikátní název dle času uložení
		$new_name = 'in_' . time();
		$extension = explode('.', $obrazek->name);
		$extension = end($extension);
		$new_name = $new_name . '.' . $extension;

		$image->save($this->path . '/novinky/' . $new_name);
		$destination = '../images/novinky/' . $new_name;

		return array($destination, $new_name);
	}


	/**
	 * Seznam novinek pro stránku novinky.
	 * @param int $step
	 * @param int $sort
	 * @param int $offset
	 * @return array
	 */
	public function getArticlesBySort(int $step, int $sort = 0, int $offset = 0): array
	{
		$order = $this->switchSort($sort);
		return $this->database->fetchAll('SELECT db_novinky.id AS id, nadpis, slug, text, obrazek, db_novinky.datum AS datum, SUM(IF(db_novinky_like.positive = 1, 1, 0)) AS positive, SUM(IF(db_novinky_like.positive = -1, 1, 0)) AS negative 
													FROM db_novinky 
													LEFT JOIN db_novinky_like ON db_novinky_like.id_novinky = db_novinky.id
													GROUP BY db_novinky.id 
													ORDER BY ' . $order . ' 
													LIMIT ?, ?', $offset, $step);
	}


	/**
	 * Přepínač dle čeho chceme řadit výpis novinek.
	 * @param int $sort
	 * @return string
	 */
	public function switchSort(int $sort = 0): string
	{
		switch ($sort) {
			case 0:
				$order = 'datum DESC';
				break;
			case 1:
				$order = 'SUM(IF(db_novinky_like.positive = 1, 1, 0))-SUM(IF(db_novinky_like.positive = -1, 1, 0)) DESC, datum DESC';
				break;
			default:
				$order = 'datum DESC';
		}
		return $order;
	}


	/**
	 * Najdeme v databázi, v kterých novinkách už uživatel hlasoval.
	 * @param int $userId
	 * @param int $articleId
	 * @return array|Nette\Database\Table\IRow[]|Nette\Database\Table\Selection
	 */
	public function getArticleLikes(int $userId, int $articleId)
	{
		return $this->database->table('db_novinky_like')
			->select('positive')
			->where('id_uzivatele = ? AND id_novinky = ?', $userId, $articleId)
			->fetch();
	}


	/**
	 * Uloží se lajky novinek do databáze.
	 * @param string $type
	 * @param int $id
	 * @param int $userId
	 * @return bool|mixed|Nette\Database\Table\IRow
	 */
	public function setLikeArticle(string $type, int $id, int $userId)
	{
		if ($type === 'positive' || $type === 'negative') { //když uživatel ještě nelajkoval
			// zjistíme, jestli se někdo nepokouší hlasovat vícekrát (nemělo by to jít)
			$stmt = $this->database->table('db_novinky_like')
				->where('id_uzivatele = ? AND id_novinky = ?', $userId, $id)
				->fetch();
			if ($stmt) {
				die('Hlasování je neplatné!');
			}
			//dle id uživatele zapíšem jeho like do samostatné tabulky, aby již nemohl znova hlasovat
			$value = ($type === 'positive') ? 1 : -1; //jestli uživatel hodnotil pozitivně nebo negativně
			$this->database->table('db_novinky_like')
				->insert([
					'id_uzivatele' => $userId,
					'id_novinky' => $id,
					'positive' => $value,
				]);
		} elseif ($type === 'positive-cancel' || $type === 'negative-cancel') { //když uživatel chce zrušit svůj (dis)lajk
			$this->database->table('db_novinky_like')
			->where('id_uzivatele = ?', $userId)
			->where('id_novinky = ?', $id)
			->delete();
		} elseif ($type === 'positive-change' || $type === 'negative-change') { //když uživatel chce změnit lajk na dislajk a naopak
			$value = ($type === 'positive-change') ? 1 : -1; //nastavíme inverzní hodnotu
			$this->database->table('db_novinky_like')
				->where('id_uzivatele = ?', $userId)
				->where('id_novinky = ?', $id)
				->update([
					'positive' => $value
				]);
		}

		//zjistíme aktuální počet pozitivních a negativních
		return $this->database->fetch('SELECT SUM(IF(db_novinky_like.positive = 1, 1, 0)) AS positive, SUM(IF(db_novinky_like.positive = -1, 1, 0)) AS negative 
													FROM db_novinky 
													LEFT JOIN db_novinky_like ON db_novinky_like.id_novinky = db_novinky.id
													WHERE id = ?
													GROUP BY db_novinky.id', $id);
	}


	/**
	 * Výběr předchozí a následující novinky.
	 * @param $mark
	 * @param $datum
	 * @param $order
	 * @return bool|mixed|Nette\Database\Table\IRow
	 */
	public function getOtherArticle($mark, $datum, $order)
	{
		return $this->database->table(self::TABLE_NAME)
			->where(self::COLUMN_DATUM . $mark . ' ?', $datum)
			->order($order)
			->limit(1)
			->fetch();
	}


	/**
	 * Získání komentářů k utčité novince.
	 * @param int $id
	 * @param int $step
	 * @return array|Nette\Database\Table\IRow[]|Nette\Database\Table\Selection
	 */
	public function getCommentsById(int $id, int $step = 5)
	{
		return $this->database->table('db_novinky_komentare')
			->where('id_novinky = ?', $id)
			->order('datum ASC')
			->limit($step)
			->fetchAll();
	}


	/**
	 * Uložení komentáře novinky.
	 * @param $values
	 * @param int $user_ID
	 */
	public function saveNewsComment($values, int $user_ID)
	{
		// nejprve získám ID novinky ze slugu
		$novinka = $this->database->table(self::TABLE_NAME)
			->select(self::COLUMN_ID)
			->where('slug = ?', $values->newsCommentSlug)
			->fetch();

		$this->database->table('db_novinky_komentare')
			->insert([
				'id_uzivatele' => $user_ID,
				'id_novinky' => $novinka->id,
				'komentar' => $values->komentar
			]);
	}


	/**
	 * Načtení dalších komentářů na stránce novinky.
	 * @param int $id
	 * @param int $offset
	 * @param int $step
	 * @return array
	 */
	public function loadMoreNewsComments(int $id, int $offset, int $step): array
	{
		return $this->database->table('db_novinky_komentare')
			->where('id_novinky = ?', $id)
			->limit($step, $offset)
			->fetchAll();
	}


	/**
	 * Počet lajků novinek do stránky detail uživatele.
	 * @param int $id
	 * @return int
	 */
	public function getLikeNewsCount(int $id): int
	{
		return $this->database->table('db_novinky_like')
			->where('id_uzivatele = ?', $id)
			->count('*');
	}


	/**
	 * Seznam youtuberů daného článku.
	 * @param $id
	 * @return object
	 */
	public function getListYoutubers(int $id)
	{
		return $this->database->table('db_nov_vs_you')
			->where('id_novinky = ?', $id)
			->fetchAll();
	}


	/**
	 * Seznam článků o youtuberovi na frontend.
	 * @param $id
	 * @return array|Nette\Database\Table\IRow[]|Nette\Database\Table\Selection
	 */
	public function getOneYoutuberArticles($id)
	{
		return $this->database->table('db_nov_vs_you')
			->where('id_youtuberi = ?', $id)
			->fetchAll();
	}
}


class ForeignException extends \Exception
{
}