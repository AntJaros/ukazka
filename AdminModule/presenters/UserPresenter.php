<?php

namespace App\AdminModule\Presenters;

use Nette;
use App\Model;
use Nette\Application\UI\Form;
use Tracy\Debugger;


/**
 * Změna jména a hesla uživatelů administrace.
 */
class UserPresenter extends BasePresenter
{
	private $adminUserManager;


	/**
	 * UserPresenter constructor.
	 * @param Model\AdminUserManager $adminUserManager
	 */
	public function __construct( Model\AdminUserManager $adminUserManager)
	{
		parent::__construct();

		$this->adminUserManager = $adminUserManager;
	}


	/**
	 * Nový admin.
	 * @return Form
	 */
	protected function createComponentNewAdminuserForm(): Form
	{
		$form = new Form;

		$form->addText('jmeno')
			->setRequired('Vyplňte jméno.');

		$form->addPassword('heslo')
			->setRequired('Vyplňte heslo.');

		$form->addSelect('role', '', ['administrator' => 'Administrator', 'editor' =>  'Editor'])
			->setPrompt('Zvolte roli')
			->setRequired('Vyberte roli.');

		$form->addSubmit('send', 'Potvrdit');

		$form->addProtection();

		$form->onSuccess[] = [$this, 'newAdminuserFormSucceeded'];

		return $form;
	}


	/**
	 * Odeslání a zpracování formuláře pro nového admina.
	 * @param Form $form
	 * @param $values
	 * @throws \Nette\Application\AbortException
	 */
	public function newAdminuserFormSucceeded(Form $form, $values)
	{
		if (!$this->user->isAllowed('Backend')) {
			$this->flashMessage('Nemáte dostatečné oprávnění!', 'alert-danger');
			$this->redirect('Homepage:');
		}
		try {
			$this->adminUserManager->addNewAdminuser($values);
		} catch (Model\DuplicateNameException $e) {
			Debugger::log($e);
			$form->addError('Toto jméno je již v databázi.');
			return;
		}
		$this->flashMessage('Uživatel byl přidán.', 'alert-success');
		$this->redirect('Homepage:default');
	}


	/**
	 * Změna jména.
	 * @return Form
	 */
	protected function createComponentChangeNameForm(): Form
	{
		$form = new Form;

		$form->addText('newname')
			->setRequired('Vyplňte nové jméno.');

		$form->addPassword('password')
			->setRequired('Vyplňte heslo.');

		$form->addSubmit('send', 'Potvrdit');

		$form->addProtection();

		$form->onSuccess[] = [$this, 'changeNameFormSucceeded'];

		return $form;
	}


	/**
	 * Odeslání a zpracování formuláře pro změnu jména.
	 * @param Form $form
	 * @param $values
	 * @throws \Nette\Application\AbortException
	 */
	public function changeNameFormSucceeded(Form $form, $values)
	{
		try {
			$this->adminUserManager->changeName($values->newname, $values->password, $this->getUser()->id);
		} catch (Nette\Security\AuthenticationException $e) {
			Debugger::log($e);
			$form->addError('Chybné heslo.');
			return;
		}
		$this->getUser()->logout();
		$this->flashMessage('Jméno bylo změněno, prosím znovu se přihlašte.', 'alert-info');
		$this->redirect('Sign:in');
	}


	/**
	 * Změna hesla.
	 * @return Form
	 */
	protected function createComponentChangePasswordForm(): Form
	{
		$form = new Form;

		$form->addPassword('passold')
			->setRequired('Vyplňte původní heslo.');

		$form->addPassword('passnew1')
			->setRequired('Vyplňte nové heslo.');

		$form->addPassword('passnew2')
			->setOmitted()
			->setRequired('Vyplňte znovu nové heslo.')
			->addRule(Form::EQUAL, 'Hesla se neshoduji', $form['passnew1']);

		$form->addSubmit('send', 'Potvrdit');

		$form->addProtection();

		$form->onSuccess[] = [$this, 'changePasswordFormSucceeded'];

		return $form;
	}


	/**
	 * Odeslání a zpracování formuláře pro změnu hesla.
	 * @param Form $form
	 * @param $values
	 * @throws \Nette\Application\AbortException
	 */
	public function changePasswordFormSucceeded(Form $form, $values)
	{
		try {
			$this->adminUserManager->changePassword($values->passold, $values->passnew1, $this->getUser()->id);
		} catch (Nette\Security\AuthenticationException $e) {
			Debugger::log($e);
			$form->addError('Chybné heslo.');
			return;
		}
		$this->getUser()->logout();
		$this->flashMessage('Heslo bylo změněno, prosím znovu se přihlašte.', 'alert-info');
		$this->redirect('Sign:in');
	}
}