<?php

namespace App\Model;


/**
 * Model pro práci s oznámením v databázi.
 */
class NoticeManager extends DatabaseConnection
{
	/** Konstanty pro manipulaci s databází. */
	const
		TABLE_NAME = 'db_oznameni',
		COLUMN_NAZEV = 'nazev',
		COLUMN_DATUM = 'datum',
		COLUMN_IKONA = 'ikona';


	/**
	 * @return object
	 */
	public function getNotices()
	{
		return $this->database->table(self::TABLE_NAME)
			->order(self::COLUMN_DATUM . ' DESC');
	}


	/**
	 * @param $nazev
	 * @param $ikona
	 */
	public function setNotice($nazev, $ikona)
	{
		$this->database->table(self::TABLE_NAME)->insert([
			self::COLUMN_NAZEV => $nazev,
			self::COLUMN_IKONA => $ikona,
		]);
	}
}
