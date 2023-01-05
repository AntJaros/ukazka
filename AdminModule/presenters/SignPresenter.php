<?php

namespace App\AdminModule\Presenters;

use Nette;
use App\Model;
use Nette\Application\UI\Form;
use Nette\Security\User;
use Tracy\Debugger;


/**
 * Přihlašování a odhlašování uživatelů do administrace.
 */
class SignPresenter extends BasePresenter
{
	/** @var Model\AdminUserManager @inject */
	public $adminUserManager;

	private $user;


	/**
	 * Metoda zděděná od předka, kde se kontroluje, zda je uživatel přihlášen
	 * na přihlašovací stránce. Je to nežádoucí, dostali bysme se do smyčky.
	 */
	protected function isAllowed(): bool
	{
		parent::isAllowed();
		return true;
	}


	/**
	 * SignPresenter constructor.
	 * @param User $user
	 */
	public function __construct(User $user)
	{
		parent::__construct();

		$this->user = $user;
	}


	/**
	 * Přihlašovací formulář.
	 * @return Form
	 */
	protected function createComponentSignInForm(): Form
	{
		$form = new Form;
		$form->addText('adminuser')
			->setHtmlAttribute('placeholder', 'Adminuser')
			->setRequired('Vyplňte jméno.');

		$form->addPassword('password')
			->setHtmlAttribute('placeholder', 'Password')
			->setRequired('Vyplňte heslo.');

		$form->addCheckbox('remember');

		$form->addSubmit('send', 'Přihlásit se');

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
			$identity = $this->adminUserManager->authenticate($values->adminuser, $values->password);
			//$this->user->getStorage()->setNamespace('admin'); // nastavíme namespace
			$this->user->setExpiration($values->remember ? '14 days' : '20 minutes');
			$this->user->login($identity); // uložíme přihlášenou identitu uživatele
			$this->redirect('Homepage:');
		} catch (Nette\Security\AuthenticationException $e) {
			Debugger::log($e);
			$form->addError('Nesprávné jméno nebo heslo.');
			return;
		}
	}


	/**
	 * Odhlášení uživatele.
	 * @throws \Nette\Application\AbortException
	 */
	public function actionOut()
	{
		$this->getUser()->logout();
		$this->flashMessage('Byl jste odhlášen.', 'alert-info');
		$this->redirect('Sign:in');
	}
}
