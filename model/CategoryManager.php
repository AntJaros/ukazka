<?php

namespace App\Model;

use Nette;
use Nette\Utils\Image;
use Nette\Utils\Strings;


/**
 * Model pro práci s kategoriema.
 */
class CategoryManager extends DatabaseConnection
{

	/** Konstanty pro manipulaci s modelem. */
	const
		TABLE_NAME = 'db_kategorie',
		COLUMN_ID = 'id',
		COLUMN_NAZEV = 'nazev',
		COLUMN_SLUG ='slug',
		COLUMN_POPIS = 'popis',
		COLUMN_OBRAZEK = 'obrazek',
		IMG_WIDTH = 1920,
		IMG_HEIGHT = 400;

	private $imageEditor;
	public $path;
	private $noticeManager;


	/**
	 * CategoryManager constructor.
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
	 * Uloží novou kategorii do databáze
	 * @param $nazev
	 * @param $obrazek
	 * @param $popis
	 * @throws \Nette\NotSupportedException
	 * @throws \Nette\Utils\UnknownImageFileException
	 * @throws \Nette\Utils\ImageException
	 */
	public function saveCategory($nazev, $obrazek, $popis)
	{
		$image = Image::fromFile($obrazek);

		// kvůli serverovým omezením nesmí být nahrán obrázek s velkými rozměry
		$this->imageEditor->checkResolution($image->width, $image->height);

		// uložíme údaje bez obrázku, musíme zjistit ID a dle toho obrázek přejmenovat
		$slug = Strings::webalize($nazev);

		//při vložení zjistíme id kategorie, kterou jsme vložili, kvůli názvu obrázku
		$id_kategorie = $this->database->table(self::TABLE_NAME)->insert([
			self::COLUMN_NAZEV => $nazev,
			self::COLUMN_SLUG => $slug,
			self::COLUMN_POPIS => $popis,
		])->id;

		if ($image->width !== self::IMG_WIDTH OR $image->height !== self::IMG_HEIGHT) {
			$image->resize(self::IMG_WIDTH,self::IMG_HEIGHT, Image::EXACT);
		}
		$image->sharpen();

		// přejmenuje se obrázek dle ID
		$extension = explode('.', $obrazek->name);
		$extension = end($extension);
		$new_name = 'kat_' . $id_kategorie;
		$new_name = $new_name . '.' . $extension ;
		$image->save($this->path . DIRECTORY_SEPARATOR . 'kategorie/' . $new_name);

		$url_obrazek = 'kategorie/' . $new_name;
		$this->database->table(self::TABLE_NAME)
			->where('id = ?', $id_kategorie)
			->update([
			self::COLUMN_OBRAZEK => $url_obrazek,
		]);

		$this->noticeManager->setNotice('Vložena nová kategorie','fa-list'); //do tabulky oznámení vložíme informaci o vytvoření kategorie
	}


	/**
	 * Editace kategorie
	 * @param $id
	 * @param $nazev
	 * @param $obrazek
	 * @param $popis
	 * @throws \Nette\Utils\ImageException
	 */
	public function updateCategory($id, $nazev, $obrazek, $popis)
	{
		if ($obrazek->name) {

			$image = Image::fromFile($obrazek);

			// kvůli serverovým omezením nesmí být nahrán obrázek s velkými rozměry
			$this->imageEditor->checkResolution($image->width, $image->height);

			if ($image->width !== self::IMG_WIDTH OR $image->height !== self::IMG_HEIGHT) {
				$image->resize(self::IMG_WIDTH,self::IMG_HEIGHT, Image::EXACT);
			}
			$image->sharpen();

			//z FTP smažeme původní obrázek
			$oldRecord = $this->getCategory($id);
			$oldObrazek = $oldRecord->obrazek;
			//jestliže je cesta v databázi a zároveň původní obrázek existuje, smažeme ho
			if($oldObrazek && file_exists($this->path . DIRECTORY_SEPARATOR . $oldObrazek)) {
				unlink($this->path . DIRECTORY_SEPARATOR . $oldObrazek);
			}

			// přejmenuje se obrázek dle ID
			$extension = explode('.', $obrazek->name);
			$extension = end($extension);
			$new_name = 'kat_' . $id;
			$new_name = $new_name . '.' . $extension ;
			$image->save($this->path . DIRECTORY_SEPARATOR . 'kategorie/' . $new_name);

			$slug = Strings::webalize($nazev);
			$url_obrazek = 'kategorie/' . $new_name;
			$this->database->table(self::TABLE_NAME)
				->where(self::COLUMN_ID . ' = ?', $id)
				->update([
				self::COLUMN_NAZEV => $nazev,
				self::COLUMN_SLUG => $slug,
				self::COLUMN_POPIS => $popis,
				self::COLUMN_OBRAZEK => $url_obrazek,
			]);
		}
		else {
			$slug = Strings::webalize($nazev);

			$this->database->table(self::TABLE_NAME)
				->where('id = ?', $id)
				->update([
					self::COLUMN_NAZEV => $nazev,
					self::COLUMN_SLUG => $slug,
					self::COLUMN_POPIS => $popis,
				]);
		}

		$this->noticeManager->setNotice('Editována kategorie','fa-pencil-square-o'); //do tabulky oznámení vložíme informaci o editacií kategorie
	}


	/**
	 * získání jedné kategorie dle ID
	 * @param $id
	 * @return mixed
	 */
	public function getCategory($id)
	{
		return $this->database->table(self::TABLE_NAME)
			->where(self::COLUMN_ID . ' = ?', $id)
			->fetch();
	}


	/**
	 * získání jedné kategorie dle slugu
	 * @param $slug
	 * @return mixed
	 */
	public function getCategorySlug($slug)
	{
		return $this->database->table(self::TABLE_NAME)
			->where(self::COLUMN_SLUG . ' = ?', $slug)
			->fetch();
	}


	/**
	 * získání seznamu všech kategorií
	 * @return object
	 */
	public function getCategories()
	{
		return $this->database->table(self::TABLE_NAME);
	}


	/**
	 * mazání kategorií z tabulky datagridu
	 * @param $id
	 * @return int
	 */
	public function deleteCategory($id): int
	{
		//z FTP smažeme původní obrázek
		$oldRecord = $this->getCategory($id);
		$oldObrazek = $oldRecord->obrazek;
		if($oldObrazek && file_exists($this->path . DIRECTORY_SEPARATOR . $oldObrazek)) {
			unlink($this->path . DIRECTORY_SEPARATOR . $oldObrazek);
		}

		$this->noticeManager->setNotice('Smazána kategorie','fa-trash'); //do tabulky oznámení vložíme informaci o smazání kategorie

		return $this->database->table(self::TABLE_NAME)
			->where(self::COLUMN_ID . ' = ?', $id)
			->delete();
	}


	/**
	 * Získání počtu youtuberů v daný kategorii.
	 * @param $id
	 * @return int
	 */
	public function getNumberYoutubersByCategory($id): int
	{
		$stmt = $this->database->query('SELECT jmeno
                                                 FROM  db_youtuberi
													  JOIN db_you_vs_kat ON db_youtuberi.id = db_you_vs_kat.id_youtuberi WHERE id_kategorie = ?', $id);
		return $stmt->getRowCount();
	}


	/**
	 * Výpis youtuberů dané kategorie.
	 * @param int $id
	 * @param int $step
	 * @param int $sort
	 * @return array
	 */
	public function getYoutubersByCategory(int $id, int $step, int $sort = 0): array
	{
		$setSort = $this->switchSort($sort);
		return $this->database->fetchAll('SELECT AVG(hodnoceni) AS hodnoceni,
                                                        db_youtuberi.jmeno AS jmeno,
                                                        db_youtuberi.foto AS foto,
                                                        db_youtuberi.slug AS slug,
                                                        db_youtuberi.odberatele AS odberatele
                                                 FROM (SELECT id_uzivatele, id_youtuberi, AVG(hodnoceni) AS hodnoceni FROM db_rate
                                                  GROUP BY id_uzivatele, id_youtuberi) unikatni
                                                        JOIN db_youtuberi
                                                          ON id_youtuberi = db_youtuberi.id
													  JOIN db_you_vs_kat ON db_youtuberi.id = db_you_vs_kat.id_youtuberi WHERE id_kategorie = ?
                                                 GROUP BY db_you_vs_kat.id_youtuberi
                                                 ORDER BY ' . $setSort .', jmeno ASC
												LIMIT ?', $id, $step);
	}


	/**
	 * Výpis youtuberů dané kategorie.
	 * @param int $id
	 * @param int $offset
	 * @param int $step
	 * @param int $sort
	 * @return array
	 */
	public function loadMoreCategories(int $id, int $offset, int $step, int $sort): array
	{
		$setSort = $this->switchSort($sort);
		return $this->database->fetchAll('SELECT AVG(hodnoceni) AS hodnoceni,
                                                        db_youtuberi.jmeno AS jmeno,
                                                        db_youtuberi.foto AS foto,
                                                        db_youtuberi.slug AS slug,
                                                        db_youtuberi.odberatele AS odberatele
                                                 FROM   db_rate
                                                        JOIN db_youtuberi
                                                          ON id_youtuberi = db_youtuberi.id
													  JOIN db_you_vs_kat ON db_youtuberi.id = db_you_vs_kat.id_youtuberi WHERE id_kategorie = ?
                                                 GROUP  BY db_you_vs_kat.id_youtuberi
                                                 ORDER  BY ' . $setSort .', jmeno ASC
												LIMIT ?, ?', $id, $offset, $step);
	}


	/**
	 * Přepínač dle čeho chceme řadit výpis youtuberů v kategorii.
	 * @param int $sort
	 * @return string
	 */
	private function switchSort(int $sort = 0): string
	{
		switch ($sort) {
			case 0:
				$order = 'hodnoceni DESC, jmeno ASC';
				break;
			case 1:
				$order = 'odberatele DESC, jmeno ASC';
				break;
			case 2:
				$order = 'jmeno ASC';
				break;
			default:
				$order = 'hodnoceni DESC, jmeno ASC';
		}
		return $order;
	}
}
