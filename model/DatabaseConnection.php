<?php

namespace App\Model;

use Nette;


/**
 * Připojení do databáze.
 */
abstract class DatabaseConnection
{
	use Nette\SmartObject;


	/**
	 * @var Nette\Database\Context
	 */
	protected $database;


	/**
	 * DatabaseConnection constructor.
	 * @param Nette\Database\Context $database
	 */
	public function __construct(Nette\Database\Context $database)
	{
		$this->database = $database;
	}
}