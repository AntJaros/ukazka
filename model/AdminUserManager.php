<?php

namespace App\Model;

use Nette;
use Nette\Security;


/**
 * Model pro práci s uživateli administrace.
 */
class AdminUserManager extends DatabaseConnection
{
	const
		TABLE_NAME = 'db_admin_login',
		COLUMN_ID = 'id',
		COLUMN_NAME = 'adminuser',
		COLUMN_PASSWORD_HASH = 'password',
		COLUMN_ROLE = 'role';

	private $noticeManager;

	/**
	 * AdminUserManager constructor.
	 * @param Nette\Database\Context $database
	 * @param NoticeManager $noticeManager
	 */
	public function __construct(Nette\Database\Context $database, NoticeManager $noticeManager) {
		parent::__construct($database);

		$this->noticeManager = $noticeManager;
	}


	/**
	 * Kontrola přihlášení adminusera.
	 * @param $adminuser
	 * @param $password
	 * @return Security\Identity
	 * @throws Security\AuthenticationException
	 */
	public function authenticate($adminuser, $password): Nette\Security\Identity
	{
		$row = $this->database->table(self::TABLE_NAME)
			->where(self::COLUMN_NAME . ' = ?', $adminuser)
			->fetch();

		if (!$row) {
			throw new Nette\Security\AuthenticationException('The adminuser is incorrect.');

		} elseif (!Security\Passwords::verify($password, $row[self::COLUMN_PASSWORD_HASH])) {

			throw new Nette\Security\AuthenticationException('The password is incorrect.');

		} elseif (Security\Passwords::needsRehash($row[self::COLUMN_PASSWORD_HASH])) {
			$row->update([
				self::COLUMN_PASSWORD_HASH => Security\Passwords::hash($password),
			]);
		}

		$arr = $row->toArray();
		unset($arr[self::COLUMN_PASSWORD_HASH]);
		return new Security\Identity($row[self::COLUMN_ID], $row[self::COLUMN_ROLE], $arr);
	}


	/**
	 * Registrace nového adminusera.
	 * @param $values
	 * @throws DuplicateNameException
	 */
	public function addNewAdminuser($values)
	{
		try {
			$this->database->table(self::TABLE_NAME)->insert([
				self::COLUMN_NAME => $values->jmeno,
				self::COLUMN_PASSWORD_HASH => Security\Passwords::hash($values->heslo),
				self::COLUMN_ROLE => $values->role,
			]);
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			throw new DuplicateNameException;
		}
	}


	/**
	 * Změna administrátorského jména
	 * @param $newname
	 * @param $password
	 * @param $currentId
	 * @throws Nette\Security\AuthenticationException
	 */
	public function changeName($newname, $password, $currentId)
	{
		$row = $this->database->table(self::TABLE_NAME)
			->where(self::COLUMN_ID . ' = ?', $currentId)
			->fetch();

		if (!$row) {
			throw new Nette\Security\AuthenticationException('The adminuser is incorrect.');
		} elseif (!Security\Passwords::verify($password, $row[self::COLUMN_PASSWORD_HASH])) {
			throw new Nette\Security\AuthenticationException('The password is incorrect.');
		} else {
			$this->database->table(self::TABLE_NAME)
				->where(self::COLUMN_ID . ' = ?', $currentId)
				->update([
					self::COLUMN_NAME => $newname
				]);
		}
		$this->noticeManager->setNotice('Změněno jméno','fa-exchange'); //do tabulky oznámení vložíme informaci o změně jména
	}


	/**
	 * Změna administrátorského hesla
	 * @param $passold
	 * @param $passnew
	 * @param $currentId
	 * @throws Nette\Security\AuthenticationException
	 */
	public function changePassword($passold, $passnew, $currentId)
	{
		$row = $this->database->table(self::TABLE_NAME)
			->where(self::COLUMN_ID . ' = ?', $currentId)
			->fetch();

		if (!$row) {
			throw new Nette\Security\AuthenticationException('The adminuser is incorrect.');
		} elseif (!Security\Passwords::verify($passold, $row[self::COLUMN_PASSWORD_HASH])) {
			throw new Nette\Security\AuthenticationException('The password is incorrect.');
		} else {
			$this->database->table(self::TABLE_NAME)
				->where(self::COLUMN_ID . ' = ?', $currentId)
				->update([
					self::COLUMN_PASSWORD_HASH => Security\Passwords::hash($passnew)
			]);
		}
		$this->noticeManager->setNotice('Změněno heslo','fa-exchange'); //do tabulky oznámení vložíme informaci o změně hesla
	}
}


/**
 * Výjimka pro duplicitní uživatelské jméno.
 * @package App\Model
 */
class DuplicateNameException extends \Exception
{
	/** Konstruktor s definicích výchozí chybové zprávy. */
	public function __construct()
	{
		parent::__construct();
		$this->message = 'Uživatel s tímto jménem je již zaregistrovaný.';
	}
}
