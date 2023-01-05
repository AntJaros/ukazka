<?php

namespace App\AdminModule\Presenters;

use App\Model;
use Nette\Application\UI\Form;


/**
 * Vytváření, mazání a editace kategorií youtuberů.
 */
class TablehpPresenter extends BasePresenter
{
	private $configManager;


	/**
	 * CategoryPresenter constructor.
	 * @param Model\ConfigManager $configManager
	 */
	public function __construct(Model\ConfigManager $configManager)
	{
		parent::__construct();

		$this->configManager = $configManager;
	}


	/**
	 * Kontrola oprávnění, zda uživatel může pracovat s kategoriema.
	 * @throws \Nette\Application\AbortException
	 */
	public function startup()
	{
		parent::startup();
		if (!$this->user->isAllowed('Tablehp')) {
			$this->flashMessage('Nemáte dostatečné oprávnění!', 'alert-danger');
			$this->redirect('Homepage:');
		}
	}


	/**
	 * Vytvoření formuláře pro editaci
	 * @return Form
	 */
	public function createComponentConfigureTableHPForm(): Form
	{
		$form = new Form;

		$interval = [
			0 => 'Minulý týden (kalendářně)',
			1 => 'Minulý týden (relativně ode dneška)',
			2 => 'Minulý měsíc (kalendářně)',
			3 => 'Minulý měsíc (relativně ode dneška)',
		];
		$form->addRadioList('interval', NULL, $interval)
			->setDefaultValue($this->configManager->getDefaultValueInterval())
			->setRequired('Vyberte jednu položku.');

		$minCount = [
			0 => 1,
			1 => 2,
			2 => 3,
			4 => 5,
			9 => 10,
		];
		$form->addSelect('minCount', NULL, $minCount)
			->setDefaultValue($this->configManager->getDefaultValueMinCount())
			->setRequired('Vyberte jednu položku.');

		$form->addSubmit('send', 'Odeslat');

		$form->addProtection();

		$form->onSuccess[] = [$this, 'configureTableHPFormSucceeded'];

		return $form;
	}


	/**
	 * Odeslání a zpracování editovacího formuláře
	 * @param Form $form
	 * @param $values
	 * @throws \Nette\Application\AbortException
	 */
	public function configureTableHPFormSucceeded(Form $form, $values)
	{
		$this->configManager->saveTableHP($values->interval, $values->minCount);
		$this->flashMessage('Configurace tabulky na HP byla změněna.', 'alert-success');
		$this->redirect('Homepage:default');
	}
}