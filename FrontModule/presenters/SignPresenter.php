<?php

namespace App\FrontModule\Presenters;

use Nette;
use App\Model;
use Nette\Application\UI\Form;
use Nette\Security\User;
use Tracy\Debugger;
use \League\OAuth2\Client\Provider;


/**
 * Přihlašování a odhlašování uživatelů do administrace.
 */
class SignPresenter extends BasePresenter
{

	public $resetArray;
	private $user; //dle hlášky je už definovaná, ale nefunguje to bez ní
	private $frontUserManager;
	public $fbProvider;
	private $configFb;

	/**
	 * SignPresenter constructor.
	 * @param $configFb
	 * @param Model\FrontUserManager $frontUserManager
	 * @param User $user
	 */
	public function __construct($configFb, Model\FrontUserManager $frontUserManager, User $user)
	{
		parent::__construct();

		$this->user = $user;
		$this->frontUserManager = $frontUserManager;
		$this->configFb = $configFb;
	}


	/**
	 * Vytvoří proměnné potřebné pro přihlášení/registraci přes FB.
	 * @throws \InvalidArgumentException
	 */
	public function startup()
	{
		parent::startup();

		$this->fbProvider = new Provider\Facebook($this->configFb);
		$authUrl = $this->fbProvider->getAuthorizationUrl([
			'scope' => ['email', 'public_profile'],
		]);
		$this->template->authUrl = $authUrl;
	}


	/**
	 * Pokud je uživatel přihlášen, nebude se zobrazovat přihlašovací stránka.
	 * @throws \Nette\Application\AbortException
	 */
	public function actionIn ()
	{
		if ($this->getUser()->isLoggedIn()) {
			$this->flashMessage('Jsi již přihlášen!', 'alert-warning');
			$this->redirect('Homepage:');
		}
	}


	/**
	 * Přihlašovací formulář.
	 * @return Form
	 */
	protected function createComponentSignInForm(): Form
	{
		$form = new Form;

		$form->addText('nick_email')
			->setRequired('Vyplň nick nebo heslo.');

		$form->addPassword('heslo')
			->setRequired('Vyplň heslo.');

		$form->addCheckbox('remember');

		$form->addSubmit('send', 'PŘIHLÁSIT SE');

		$form->onSuccess[] = [$this, 'signInFormSucceeded'];

		return $form;
	}


	/**
	 * Zpracování přihlašovacího formuláře.
	 * @param Form $form
	 * @param $values
	 * @throws \Nette\Application\AbortException
	 */
	public function signInFormSucceeded(Form $form, $values)
	{
		try {
			$identity = $this->frontUserManager->authenticate($values->nick_email, $values->heslo);
			//$this->user->getStorage()->setNamespace('front'); // nastavíme namespace
			$this->user->setExpiration($values->remember ? '14 days' : '20 minutes');
			$this->user->login($identity); // uložíme přihlášenou identitu uživatele
			$this->flashMessage('Přihlášení proběho úspěšně! Nyní můžeš hodnotit, psát komentáře, sledovat nová videa nebo sdílet novinky s přáteli!', 'alert-success');
			$this->redirect('Homepage:');
		} catch (Nette\Security\AuthenticationException $e) {
			Debugger::log($e);
			$form->addError('Chybně zadaný nick / e-mail nebo heslo.');
			return;
		} catch (Nette\Application\ForbiddenRequestException $e) {
			Debugger::log($e);
			$this->flashMessage('Uživatel má BAN!', 'alert-danger');
		}
	}


	/**
	 * Registrační formulář.
	 * @return Form
	 */
	protected function createComponentSignUpForm(): Form
	{
		$form = new Form;

		$form->addText('jmeno')
			->setRequired('Vyplňte jméno.')
			->addRule(Form::MIN_LENGTH, 'Jméno musí mít alespoň %d znaky.', 2)
			->addRule(Form::MAX_LENGTH, 'Jméno může mít maximálně %d znaků.', 20);

		$form->addText('prijmeni')
			->setRequired('Vyplňte příjmení.')
			->addRule(Form::MIN_LENGTH, 'Příjmení musí mít alespoň %d znaky.', 2)
			->addRule(Form::MAX_LENGTH, 'Příjmení může mít maximálně %d znaků.', 20);

		$form->addText('nick')
			->setRequired('Vyplňte nick.')
			->addRule(Form::MIN_LENGTH, 'Nick musí mít alespoň %d znaky.', 2)
			->addRule(Form::MAX_LENGTH, 'Nick může mít maximálně %d znaků.', 15);

		$form->addText('email')
			->setRequired('Vyplňte e-mail.')
			->addRule(Form::EMAIL, 'Neplatná emailová adresa');

		$form->addPassword('heslo')
			->setRequired('Zvolte si heslo.')
			->addRule(Form::MIN_LENGTH, 'Heslo musí mít alespoň %d znaků', 6);

		$form->addPassword('heslo_potvrzeni')
			->setOmitted()
			->setRequired('Zvolte si heslo.')
			->addRule(Form::EQUAL, 'Hesla se neshoduji', $form['heslo']);

		$form->addCheckbox('podminky')
			->setRequired('Je nutné souhlasit s podmínkami');

		$form->addReCaptcha(
			'captcha', // control name
			'reCAPTCHA for you', // label
			'Prosím dokaž, že nejsi robot.' // error message
		);

		$form->addSubmit('send', 'ZAREGISTROVAT SE');

		$form->onSuccess[] = [$this, 'signUpFormSucceeded'];

		return $form;
	}


	/**
	 * Zpracování registračního formuláře
	 * @param Form $form
	 * @param $values
	 * @throws \Nette\Application\AbortException
	 */
	public function signUpFormSucceeded(Form $form, $values)
	{
		try {
			$this->frontUserManager->saveTempUser($values->jmeno, $values->prijmeni, $values->nick, $values->email, $values->heslo);
			$this->flashMessage('Registrace proběhla úspěšně! Pro dokončení registrace prosím potvrď email ve Tvojí schránce.', 'alert-info');
			$this->redirect('Homepage:');
		} catch (Model\DuplicateException $e) {
			Debugger::log($e);
			$form->addError('Nick nebo e-mail je již zaregistrován.');
			return;
		} catch (Model\MailException $e) {
			Debugger::log($e);
			$this->flashMessage('E-mail s potvrzovacím kódem se nepodařilo odeslat!', 'alert-danger');
		}
	}


	/**
	 * Potvrzení registrace.
	 * @param $code
	 * @throws \Nette\Application\AbortException
	 * @throws \App\Model\DuplicateException
	 */
	public function actionConfirm($code)
	{
		if (!$code) {
			$this->flashMessage('Chybí potvrzovací kód!', 'alert-danger');
			$this->redirect('Homepage:');
		}

		try {
			$this->frontUserManager->confirmRegistration($code);
			$message = $this->flashMessage('Registrace úspěšně dokončena. Nyní se můžeš přihlásit!', 'alert-success');
			$message->id = 'signup-success'; //kvůli trackování v GA
			$this->redirect('Homepage:');
		} catch (Model\BadConfirmException $e) {
			Debugger::log($e);
			$this->flashMessage('Špatný potvrzovací kód!', 'alert-danger');
			$this->redirect('Homepage:');
		}
	}


	/**
	 * Formulář na zapomenuté heslo.
	 * @return Form
	 */
	protected function createComponentSignForgotForm(): Form
	{
		$form = new Form;

		$form->addText('email')
			->setRequired('Vyplň e-mail.')
			->addRule(Form::EMAIL, 'Neplatná emailová adresa');

		$form->addSubmit('send', 'ZASLAT NOVÉ HESLO');

		$form->onSuccess[] = [$this, 'signForgotFormSucceeded'];

		return $form;
	}


	/**
	 * Zpracování formuláře na zapomenuté heslo.
	 * @param Form $form
	 * @param $values
	 * @throws \Nette\Application\AbortException
	 */
	public function signForgotFormSucceeded(Form $form, $values)
	{
		try {
			$this->frontUserManager->resetPassword($values->email);
			$this->flashMessage('Na Vaší adresu byl odeslán přístupový kód pro změnu hesla. V případě že jste e-mail neobdrželi, zkontrolujte prosím, zda Vám nezapadl do spamu.', 'alert-info');
			$this->redirect('Homepage:');
		} catch (Model\DuplicateException $e) {
			Debugger::log($e);
			$form->addError('Na tento email již byl zaslán přístupový kód dříve.');
			return;
		} catch (Model\BadEmailException $e) {
			Debugger::log($e);
			$this->flashMessage('Tento e-mail není zaregistrován nebo je registrován přes FB!', 'alert-warning');
		} catch (Model\MailException $e) {
			Debugger::log($e);
			$this->flashMessage('Chyba při odeslání přístupového kódu!', 'alert-danger');
		}
	}


	/**
	 * Jen se zkontroluje platnost kódu.
	 * @param $code
	 * @throws \Nette\Application\AbortException
	 */
	public function actionReset($code)
	{
		try {
			// do properties(vlastnosti) uložím email, který použiji ve zpracování formuláře
			if ($code) {
				$this->resetArray = $this->frontUserManager->checkCode($code);
			} else {
				$this->flashMessage('Chybí kód pro změnu hesla!', 'alert-danger');
				$this->redirect('Homepage:');
			}
		} catch (Model\BadCodeException $e) {
			Debugger::log($e);
			$this->flashMessage('Tvůj kód pro změnu hesla je neplatný!', 'alert-danger');
			$this->redirect('Homepage:');
		}
	}


	/**
	 * Vytvoří formulář pro napsání nového hesla.
	 * @return Form
	 */
	protected function createComponentSignResetForm(): Form
	{
		$form = new Form;

		$form->addPassword('heslo')
			->setRequired('Zvolte si heslo.')
			->addRule(Form::MIN_LENGTH, 'Heslo musí mít alespoň %d znaků', 6);

		$form->addPassword('heslo_potvrzeni')
			->setOmitted()
			->setRequired('Zvol si heslo.')
			->addRule(Form::EQUAL, 'Hesla se neshoduji', $form['heslo']);

		$form->addSubmit('send', 'ODESLAT HESLO');

		$form->onSuccess[] = [$this, 'signResetFormSucceeded'];

		return $form;
	}


	/**
	 * Zpracování nového hesla.
	 * @param Form $form
	 * @param $values
	 * @throws \Nette\Application\AbortException
	 */
	public function signResetFormSucceeded(Form $form, $values)
	{
		try {
			$this->frontUserManager->createNewPassword($values->heslo, $this->resetArray );
			$this->flashMessage('Tvoje nové heslo bylo uloženo. Nyní se můžeš přihlásit.', 'alert-success');
			$this->redirect('Homepage:');
		} catch (Nette\Application\BadRequestException $e) {
			Debugger::log($e);
			$this->flashMessage('Změna hesla se nezdařila!', 'alert-danger');
			$this->redirect('Homepage:');
		} catch (Model\BadCodeException $e) {
			Debugger::log($e);
			$this->flashMessage('Neplatný kód pro změnu hesla!', 'alert-danger');
			$this->redirect('Homepage:');
		}
	}


	/**
	 * Odhlášení uživatele.
	 * @throws \Nette\Application\AbortException
	 */
	public function actionOut()
	{
		$this->getUser()->logout();
		$this->flashMessage('Odhlášení proběhlo úspěšně, ale budeme se těšit brzy naviděnou, ať Ti ze světa youtuberů nic neunikne!', 'alert-success');
		$this->redirect('Homepage:');
	}


	/**
	 * Přihlášení přes FB.
	 * @throws \Nette\Security\AuthenticationException
	 * @throws \Nette\Application\AbortException
	 * @throws \League\OAuth2\Client\Provider\Exception\FacebookProviderException
	 * @throws \InvalidArgumentException
	 */
	public function actionFb()
	{
		if (isset($_GET['code'])) {
			$token = $this->fbProvider->getAccessToken('authorization_code', [
				'code' => $_GET['code']
			]);
		}

		try {
			$fbUserData = $this->fbProvider->getResourceOwner($token);
			$fbUser = $fbUserData->toArray();
		} catch (\Exception $e) {
			$this->flashMessage('Omlouváme se, přihlášení přes FB se nezdařilo!', 'alert-danger');
			Debugger::log($e);
			$this->redirect('Sign:in');
			return;
		}

		if (!$existing = $this->frontUserManager->findByFacebookId($fbUser['id'])) {
			try {
				dump($fbUser);exit;
				$this->frontUserManager->registerTempFacebook($fbUser);
				$this->redirect('Sign:nick', $fbUser['id'], $fbUser['first_name']);
			} catch (Model\DuplicateException $e) {
				Debugger::log($e);
				$this->flashMessage('Problém s e-mailem (asi byl již dříve zaregistrován)!', 'alert-warning');
				$this->redirect('Sign:in');
				return;
			}
		} elseif ($existing->ban === 1) {
			$this->flashMessage('Uživatel má BAN!', 'alert-danger');
			$this->redirect('Sign:in');
		}

		$this->user->login(new \Nette\Security\Identity($existing->id, ['user' => $existing->nick]));
		$this->flashMessage('Přihlášení proběho úspěšně! Nyní můžeš hodnotit, psát komentáře, sledovat nová videa nebo sdílet novinky s přáteli!', 'alert-success');
		$this->redirect('Homepage:');
	}


	/**
	 * Vložíme "example nick" do formuláře.
	 * @param $idFb
	 * @param $first_name
	 */
	public function actionNick ($idFb, $first_name)
	{
		$tempNick = substr($first_name . $idFb, 0, 14);
		$this['signNickForm']->setDefaults(['nick' => $tempNick, 'idFb' => $idFb]);
	}




	/**
	 * Formulář na zvolení nicku z FB.
	 * @return Form
	 */
	protected function createComponentSignNickForm(): Form
	{
		$form = new Form;

		$form->addText('nick')
			->setRequired('Vyplňte nick.')
			->addRule(Form::MIN_LENGTH, 'Nick musí mít alespoň %d znaky.', 2)
			->addRule(Form::MAX_LENGTH, 'Nick může mít maximálně %d znaků.', 15);

		$form->addHidden('idFb');

		$form->addSubmit('send', 'ODESLAT');

		$form->onSuccess[] = [$this, 'signNickFormSucceeded'];

		return $form;
	}


	/**
	 * Zpracování formuláře na zvolení nicku z FB.
	 * @param Form $form
	 * @param $values
	 * @throws \Nette\Security\AuthenticationException
	 * @throws \Nette\Application\AbortException
	 */
	public function signNickFormSucceeded(Form $form, $values)
	{
		try {
			$identity = $this->frontUserManager->registerFacebook($values->nick, $values->idFb);
			//$this->user->getStorage()->setNamespace('front'); // nastavíme namespace
			$this->user->setExpiration('14 days');
			$this->user->login($identity); // uložíme přihlášenou identitu uživatele
			$message = $this->flashMessage('Registrace proběhla úspěšně! Nyní můžete hodnotit, psát komentáře, sledovat nová videa nebo sdílet novinky s přáteli!', 'alert-success');
			$message->id = 'signup-success'; //kvůli trackování v GA
			$this->redirect('Homepage:');
		} catch (Model\DuplicateException $e) {
			Debugger::log($e);
			$form->addError('Tento nick je již zaregistrován.');
			return;
		}
	}


	/**
	 * Kontrola při registraci, jestli už není nick obsazený.
	 * @param $nick
	 * @throws \Nette\Application\AbortException
	 */
	public function handleCheckNick($nick)
	{
		if ($this->frontUserManager->getByNick($nick)) {
			$this->payload->available = false;
		}
		else {
			$this->payload->available = true;
		}
		$this->sendPayload();
	}
}
