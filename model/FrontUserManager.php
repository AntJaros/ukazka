<?php

namespace App\Model;

use Nette;
use Nette\Security;
use Nette\Security\Passwords;
use Nette\Mail\Message;
use Nette\Mail\SendmailMailer;
use Nette\Utils\Strings;

/**
 * Model pro práci s uživateli.
 */

class FrontUserManager extends DatabaseConnection
{
	const
		TABLE_NAME = 'db_uzivatele',
		COLUMN_ID = 'id',
		COLUMN_NAME = 'jmeno',
		COLUMN_SURNAME = 'prijmeni',
		COLUMN_PASSWORD_HASH = 'heslo',
		COLUMN_NICK = 'nick',
		COLUMN_SLUG = 'slug',
		COLUMN_EMAIL = 'email',
		COLUMN_IP = 'ip_adresa',
		COLUMN_ID_FB = 'id_fb',
		COLUMN_BAN = 'ban';

	private $noticeManager;


	/**
	 * FrontUserManager constructor.
	 * @param Nette\Database\Context $database
	 * @param NoticeManager $noticeManager
	 */
	public function __construct(Nette\Database\Context $database, NoticeManager $noticeManager) {
		parent::__construct($database);

		$this->noticeManager = $noticeManager;
	}


	/**
	 * Kontrola přihlášení uživatele.
	 * @param $login
	 * @param $heslo
	 * @return Security\Identity
	 * @throws Nette\Application\ForbiddenRequestException
	 * @throws Security\AuthenticationException
	 */
	public function authenticate($login, $heslo): Nette\Security\Identity
	{
		$row = $this->database->table(self::TABLE_NAME)
			->where(self::COLUMN_NICK . ' = ? OR ' . self::COLUMN_EMAIL . ' = ?', $login, $login)
			->fetch();

		if (!$row) {
			throw new Nette\Security\AuthenticationException('Chybně zadaný nick nebo email.');

		} elseif ($row[self::COLUMN_BAN] === 1) {

			throw new Nette\Application\ForbiddenRequestException('Uživatel má BAN.');

		} elseif (!Passwords::verify($heslo, $row[self::COLUMN_PASSWORD_HASH])) {

			throw new Nette\Security\AuthenticationException('Chybně zadané heslo.');

		} elseif (Passwords::needsRehash($row[self::COLUMN_PASSWORD_HASH])) {
			$row->update([
				self::COLUMN_PASSWORD_HASH => Passwords::hash($heslo),
			]);
		}

		$arr = $row->toArray();
		unset($arr[self::COLUMN_PASSWORD_HASH]); //odstraní heslo kvůli bezpečnosti
		return new Security\Identity($row->id, ['user' => $row->nick], $arr);
	}


	/**
	 * Získání počtu registrovaných uživatelů pro administraci
	 * @return int
	 */
	public function getNumberUsers(): int
	{
		return $this->database->table(self::TABLE_NAME)->count('*');
	}


	/**
	 * Uložení registračních údajů do temp tabulky.
	 * @param $jmeno
	 * @param $prijmeni
	 * @param $nick
	 * @param $email
	 * @param $heslo
	 * @throws MailException
	 * @throws DuplicateException
	 */
	public function saveTempUser($jmeno, $prijmeni, $nick, $email, $heslo)
	{
		$checkEmail = $this->database->fetch('SELECT * FROM (SELECT email FROM db_uzivatele UNION SELECT email FROM db_uzivatele_temp) AS e WHERE email = ?', $email);
		if (!empty($checkEmail)) {
			throw new DuplicateException('Email již existuje.');
		}

		$checkNick = $this->database->fetch('SELECT * FROM (SELECT nick FROM db_uzivatele UNION SELECT nick FROM db_uzivatele_temp) AS n WHERE nick = ?', $nick);
		if (!empty($checkNick)) {
			throw new DuplicateException('Nick již existuje.');
		}

		$code = md5(uniqid(random_bytes(5), false)); //vygeneruje se kód pro potvrzení emailem
		$ip_adresa = $_SERVER['REMOTE_ADDR']; //ukládáme do databáze IP adresu uživatele
		$slug = Strings::webalize($nick);

		//registrace se vloží do dočasné tabulky - tam zůstane, dokud uživatel registraci nepotvrdí
		try {
			$this->database->table('db_uzivatele_temp')->insert([
				'kod' => $code,
				self::COLUMN_NAME => $jmeno,
				self::COLUMN_SURNAME => $prijmeni,
				self::COLUMN_PASSWORD_HASH => Passwords::hash($heslo),
				self::COLUMN_NICK => $nick,
				self::COLUMN_SLUG => $slug,
				self::COLUMN_EMAIL => $email,
				self::COLUMN_IP => $ip_adresa,
			]);
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			throw new DuplicateException('Nick nebo email již existuje.');
		}

		$mail = new Message;
		$mail->setFrom('Dejbod <info@dejbod.cz>')
			->addTo($email)
			->setSubject('Potvrzení registrace z webu dejbod.cz')
			->setBody("Kliknutím na odkaz (nebo zkopírováním do URL řádku) dokončíš registrační proces. \r\n 
			https://dejbod.cz/confirm?code=" . $code . "\r\n \r\n 
			Po potvrzení se staneš členem týmu dejbod.cz a kritikem youtubeů v jedné osobě! Zpřístupníme pro tebe následující možnosti: \r\n 
			1. Po přihlášení můžeš hodnotit všechny youtubery v databázi. Tvůj názor jistě ocení i další uživatelé! \r\n 
			2. Po ohodnocení youtubera můžeš napsat komentář, který se následně může umístit v žebříčku těch nejlepších! \r\n 
			3. Můžeš číst i hodnotit novinky, které je možné sdílet se svými přáteli. Nyní Ti ze světa youtuberů nic neunikne! \r\n 
			4. Máš možnost sledovat videa přímo na stránce youtubera nebo si najdeš svého oblíbence v příslušných kategoriích! \r\n 
			5. Po rozkliknutí dalších uživatelů se můžeš podívat na nejnovější hodnocení od Tvých kolegů! \r\n \r\n 
			https://dejbod.cz/confirm?code=" . $code);

		$mailer = new SendmailMailer;
		try {
			$mailer->send($mail);
		} catch (Nette\Mail\SendException $e) {
			throw new MailException('E-mail s potvrzovacím kódem se nepodařilo odeslat.');
		}
	}


	/**
	 * Potvrzení registrace.
	 * @param $code
	 * @throws BadConfirmException
	 * @throws \App\Model\DuplicateException
	 */
	public function confirmRegistration($code)
	{
		$tempUser = $this->database->table('db_uzivatele_temp')
			->select('kod')
			->where('kod = ?', $code)
			->fetch();

		if ($tempUser) {
			$this->moveRegistration($code);
		}
		else {
			throw new BadConfirmException('Špatný potvrzovací kód.');
		}
	}


	/**
	 * Přesunutí registrační údajů z temp tabulky do ostré.
	 * @param $code
	 * @throws DuplicateException
	 */
	public function moveRegistration($code)
	{
		// zkopírují se údaje
		try {
			$this->database->query("INSERT INTO db_uzivatele (jmeno, prijmeni, heslo, nick, slug, email, ip_adresa) SELECT jmeno, prijmeni, heslo, nick, slug, email, ip_adresa FROM db_uzivatele_temp WHERE kod = ?", $code);
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			throw new DuplicateException('Nick nebo email již existuje.');
		}
		// smaže se záznam v dočasné tabulce
		$this->database->table('db_uzivatele_temp')
			->where('kod = ?', $code)
			->delete();

		// do tabulky oznámení vložíme informaci o editacií kategorie
		$this->noticeManager->setNotice('Zaregistrován nový uživatel','fa-user-plus');
	}


	/**
	 * Vytvoří se kód pro vytvoření nového hesla a odešle na email uživatele.
	 * @param $email
	 * @throws BadEmailException
	 * @throws DuplicateException
	 * @throws MailException
	 */
	public function resetPassword($email)
	{
		// zjistíme, jestli v databázi existuje zadaný e-mail
		$checkEmail = $this->database->table(self::TABLE_NAME)
			->select(self::COLUMN_EMAIL)
			->where(self::COLUMN_EMAIL . " = ? AND NOT (heslo = '')", $email) // druhá podmínka je zjištění, zda email nebyl registrovanej přes FB
			->fetch();

		if (!$checkEmail) {
			throw new BadEmailException('Tento e-mail není zaregistrován nebo je registrován přes FB.');
		}

		// vylepšíme šifrování
		$salt = "A5C*2D8%F231%3810GBQ!8#1607D37E3CZ8B";

		// vytvoříme zašifrovaný unikátní kód
		$code = password_hash($salt . $email, PASSWORD_DEFAULT);

		// uložíme kód do provizorní tabulky
		try {
			$this->database->table('db_reset_pass')->insert([
				'kod' => $code,
				'email' => $email,
			]);
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			throw new DuplicateException('Na tento email již byl zaslán přístupový kód dříve.');
		}

		// odešle se email s kódem
		$mail = new Message;
		$mail->setFrom('Dejbod <info@dejbod.cz>')
			->addTo($email)
			->setSubject('Zapomenuté heslo z webu dejbod.cz')
			->setBody("Kliknutím na odkaz (nebo zkopírováním do URL řádku) si můžeš zvolit nové heslo. \r\n https://dejbod.cz/reset?code=" . $code);

		$mailer = new SendmailMailer;
		try {
			$mailer->send($mail);
		} catch (Nette\Mail\SendException $e) {
			throw new MailException('Chyba při odeslání přístupového kódu.');
		}
	}


	/**
	 * Zkontroluje se platnost kódu a odešle email do presenteru.
	 * @param $code
	 * @return bool|mixed|Nette\Database\Table\IRow
	 * @throws BadCodeException
	 */
	public function checkCode($code)
	{
		$check = $this->database->table('db_reset_pass')
			->where('kod = ?', $code)
			->fetch();

		if (!$check) {
			throw new BadCodeException('Neplatný kód na změnu hesla.');
		} else {
			return $check;
		}
	}


	/**
	 * Vytvoření kódu pro změnu hesla.
	 * @param $heslo
	 * @param $resetArray
	 * @throws BadCodeException
	 */
	public function createNewPassword($heslo, $resetArray)
	{
		$code = $resetArray['kod'];
		$email = $resetArray['email'];

		// stejný "salt" jako při odeslání
		$salt = 'A5C*2D8%F231%3810GBQ!8#1607D37E3CZ8B';
		// otestuje se platnost kódu
		if (!password_verify($salt . $email, $code)) {
			throw new BadCodeException('Neplatný kód pro změnu hesla.');
		}

		$this->database->table(self::TABLE_NAME)
			->where(self::COLUMN_EMAIL . " = ? AND NOT (heslo = '')", $email)
			->update([
				'heslo' => Passwords::hash($heslo)
			]);

		$this->database->table('db_reset_pass')
			->where('email = ?', $email)
			->delete();
	}


	/**
	 * Nalezení uživatele dle FB ID.
	 * @param $fbId
	 * @return bool|mixed|Nette\Database\Table\IRow
	 */
	public function findByFacebookId($fbId)
	{
		return $this->database->table(self::TABLE_NAME)
			->where(self::COLUMN_ID_FB . ' = ?', $fbId)
			->fetch();
	}


	/**
	 * Dočasná registrace uživatele přes FB, než si zvolí nick.
	 * @param $fbUser
	 * @throws DuplicateException
	 */
	public function registerTempFacebook($fbUser)
	{
		//kontrola emailu pouze v ostré tabulce, v temp může být
		if (isset($fbUser['email'])) {
			$checkEmail = $this->database->table(self::TABLE_NAME)
				->where(self::COLUMN_EMAIL . ' = ?', $fbUser['email'])
				->fetch();
			if (!empty($checkEmail)) {
				throw new DuplicateException('Email již existuje.');
			}
		}
		else {
			$fbUser['email'] = '';
		}

		$ip_adresa = $_SERVER['REMOTE_ADDR']; //ukládáme do databáze IP adresu uživatele

		//registrace se vloží do dočasné tabulky - tam zůstane, dokud uživatel registraci nepotvrdí
		$this->database->table('db_uzivatele_temp')->insert([
			self::COLUMN_ID_FB => $fbUser['id'],
			self::COLUMN_NAME => $fbUser['first_name'],
			self::COLUMN_SURNAME => $fbUser['last_name'],
			self::COLUMN_EMAIL => $fbUser['email'],
			self::COLUMN_IP => $ip_adresa,
		]);
	}


	/**
	 * Přesun uživatele registrovaného z FB z dočasné tabulky do uživatelů.
	 * @param $nick
	 * @param $idFb
	 * @return Security\Identity
	 * @throws DuplicateException
	 */
	public function registerFacebook($nick, $idFb) :Nette\Security\Identity
	{
		$checkNick = $this->database->fetch('SELECT * FROM (SELECT nick FROM db_uzivatele UNION SELECT nick FROM db_uzivatele_temp) AS n WHERE nick = ?', $nick);
		if (!empty($checkNick)) {
			throw new DuplicateException('Nick již existuje.');
		}

		$slug = Strings::webalize($nick);
		try {
			$this->database->table('db_uzivatele_temp')
				->where(self::COLUMN_ID_FB . ' = ?', $idFb)
				->limit(1)
				->update([
					'nick' => $nick,
					'slug' => $slug
				]);
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			throw new DuplicateException('Nick již existuje.');
		}

		// zkopírují se údaje
		try {
			$this->database->query('INSERT INTO db_uzivatele (jmeno, prijmeni, nick, slug, email, id_fb, ip_adresa) SELECT jmeno, prijmeni, nick, slug, email, id_fb, ip_adresa FROM db_uzivatele_temp WHERE id_fb = ? AND nick = ?', $idFb, $nick);
		} catch (Nette\Database\UniqueConstraintViolationException $e) {
			throw new DuplicateException('Nick již existuje.');
		}

		// smaže se záznam v dočasné tabulce
		$this->database->table('db_uzivatele_temp')
			->where(self::COLUMN_ID_FB . ' = ?', $idFb)
			->delete();

		// do tabulky oznámení vložíme informaci o editacií kategorie
		$this->noticeManager->setNotice('Zaregistrován nový uživatel','fa-user-plus');

		//vytvoříme identitu přihlášení (potřebujeme nejdřív zjistit ID z ostré tabulky
		$row = $this->database->table(self::TABLE_NAME)
			->where(self::COLUMN_NICK . ' = ?', $nick)
			->fetch();

		return new Security\Identity($row->id, ['user' => $row->nick]);
	}


	/**
	 * Metoda pro kontrolu nicku v live checkeru při registraci.
	 * @param $nick
	 * @return object
	 */
	public function getByNick($nick)
	{
		return $this->database->fetch('SELECT * FROM (SELECT nick FROM db_uzivatele UNION SELECT nick FROM db_uzivatele_temp) AS n WHERE nick = ?', $nick);
	}


	/**
	 * Získání údajů jednoho uživatele.
	 * @param int $id
	 * @return bool|mixed|Nette\Database\Table\IRow
	 */
	public function getById(int $id)
	{
		return $this->database->table(self::TABLE_NAME)
			->where('id = ?', $id)
			->fetch();
	}
}

class DuplicateException extends \Exception
{
}

class MailException extends \Exception
{
}

class BadConfirmException extends \Exception
{
}

class BadEmailException extends \Exception
{
}

class BadCodeException extends \Exception
{
}