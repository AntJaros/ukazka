<?php

namespace App\Model;

use Nette;
use Nette\Neon\Neon;


class ConfigManager extends DatabaseConnection
{
	/** Konstanty pro manipulaci s modelem. */
	const
		TABLE_NAME = 'db_konfig_hp',
		COLUMN_CAS = 'cas',
		COLUMN_MINIMUM = 'minimum';


	private $minCountRateHP;
	private $dateRateHP;
	private $dir;
	private $noticeManager;


	/**
	 * ConfigManager constructor.
	 * @param Nette\Database\Context $dir
	 * @param Nette\Database\Context $database
	 * @param NoticeManager $noticeManager
	 */
	public function __construct($dir, Nette\Database\Context $database, NoticeManager $noticeManager) {
		parent::__construct($database);

		$this->minCountRateHP = $this->getConfigHP()->minimum;
		$this->dateRateHP = $this->getConfigHP()->cas;
		$this->dir = $dir;
		$this->noticeManager = $noticeManager;
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
	 * Získá cestu k adresáři configů.
	 */
	public function getDir()
	{
		return $this->dir;
	}


	/**
	 * Přepsání konfigurace tabulky na HP.
	 * @param $interval
	 * @param $minCount
	 */
	public function saveTableHP($interval, $minCount)
	{
		$this->database->table(self::TABLE_NAME)
			->update([
				self::COLUMN_CAS => $interval,
				self::COLUMN_MINIMUM => $minCount,
			]);

		$this->noticeManager->setNotice('Změna konfigurace tabulky na HP','fa-table'); //do tabulky oznámení vložíme informaci o vloženém youtuberovi
	}


	/**
	 * Získání defaultní hodnoty do formuláře na změnu konfigurace tabulky na HP.
	 * @return mixed
	 */
	public function getDefaultValueInterval()
	{
		return $this->dateRateHP;
	}


	/**
	 * Získání defaultní hodnoty do formuláře na změnu konfigurace tabulky na HP.
	 * @return mixed
	 */
	public function getDefaultValueMinCount()
	{
		return $this->minCountRateHP;
	}
}