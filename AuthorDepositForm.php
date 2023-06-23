<?php

/**
* @file AuthorDepositForm.php
*
* Copyright (c) 2003-2021 Simon Fraser University
* Copyright (c) 2003-2021 John Willinsky
* Distributed under the GNU GPL v3. For full terms see the file LICENSE.
*
* @class AuthorDepositForm
* @brief Form to perform an author's SWORD deposit(s)
*/

namespace APP\plugins\generic\sword;

use PKP\form\Form;

use APP\plugins\generic\sword\PKPSwordDeposit;
use APP\plugins\generic\sword\classes\DepositPoint;
use APP\plugins\generic\sword\classes\DepositPointsHelper;

class AuthorDepositForm extends Form {
	/** @var $_context Context */
	protected $_context = null;

	/** @var $_plugin SwordPlugin */
	protected $_plugin = null;

	/** @var $_submission Submission */
	protected $_submission = null;

	/**
	 * Constructor
	 * @param $plugin SwordPlugin
	 * @param $context Context
	 * @param $submission Submission
	 */
	public function __construct(SwordPlugin $plugin, Context $context, Submission $submission) {
		$this->_plugin = $plugin;
		$this->_context = $context;
		$this->_submission = $submission;
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_USER);
		parent::__construct($plugin->getTemplateResource('authorDepositForm.tpl'));
	}

	/**
	 * Get reference to the sword plugin
	 * @return SwordPlugin
	 */
	public function getSwordPlugin() {
		return $this->_plugin;
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	public function readInputData() {
		$this->readUserVars([
			'authorDepositUrl',
			'authorDepositUsername',
			'authorDepositPassword',
			'depositPoint',
		]);
	}

	/**
	 * @copydoc Form::display()
	 */
	public function display($request = null, $template = null) {
		$templateMgr = TemplateManager::getManager($request);
		$depositPoints = $this->_getDepositableDepositPoints($this->_context);
		$templateMgr->assign([
			'depositPoints' 	=> $depositPoints,
			'submission'		=> $this->_submission,
			'allowAuthorSpecify' 	=> $this->getSwordPlugin()->getSetting($this->_context->getId(), 'allowAuthorSpecify'),
			'pluginJavaScriptURL' 	=> $this->_plugin->getJsUrl($request),
		]);
		parent::display($request, $template);
	}

	/**
	 * Save form.
	 * @param $request PKPRequest
	 * @return array Set of SWORDAPPEntry responses
	 */
	public function execute(...$functionArgs) {
		parent::execute(...$functionArgs);
		$request = $functionArgs[0];
		
		$deposit = new PKPSwordDeposit($this->_submission);
		$deposit->setMetadata($request);
		$deposit->addEditorial();
		$deposit->createPackage();

		$responses = [];

		$allowAuthorSpecify = $this->getSwordPlugin()->getSetting($this->_context->getId(), 'allowAuthorSpecify');
		$authorDepositUrl = $this->getData('authorDepositUrl');
		if (($allowAuthorSpecify) && ($authorDepositUrl != '')) {
			$responses[$this->getData('authorDepositUrl')] = $deposit->deposit(
				$this->getData('authorDepositUrl'),
				$this->getData('authorDepositUsername'),
				$this->getData('authorDepositPassword')
			);
			$deposit->cleanup();
		}
		
		$url = '';
		$depositPoints = $this->getData('depositPoint');
		$depositableDepositPoints = $this->_getDepositableDepositPoints($this->_context);
		foreach ($depositableDepositPoints as $key => $depositPoint) {
			if (!isset($depositPoints[$key]['enabled']))
				continue;
			if ($depositPoint['type'] == SWORD_DEPOSIT_TYPE_OPTIONAL_SELECTION) {
				$url = $depositPoints[$key]['depositPoint'];
			} else { // SWORD_DEPOSIT_TYPE_OPTIONAL_FIXED
				$url = $depositPoint['url'];
			}
			$responses[$depositPoint['url']] = $deposit->deposit(
				$url,
				$depositPoint['username'] ?: $depositPoints[$key]['username'],
				$depositPoint['password'] ?: $depositPoints[$key]['password'],
				$depositPoint['apikey']
			);
			$deposit->cleanup();
		}
		return $responses;
	}

	/**
	 * Build a list of collections available for deposit points of type SWORD_DEPOSIT_TYPE_OPTIONAL_SELECTION
	 * @param $context Context
	 * @return array
	 */
	protected function _getDepositableDepositPoints($context) {
		$list = [];
		$depositPointDao = DAORegistry::getDAO('DepositPointDAO');
		$depositPoints = $depositPointDao->getByContextId($context->getId());
		foreach ($depositPoints as $depositPoint) {
			if (!in_array($depositPoint->getType(), [SWORD_DEPOSIT_TYPE_OPTIONAL_SELECTION, SWORD_DEPOSIT_TYPE_OPTIONAL_FIXED]))
				continue;

			$list[$depositPoint->getId()] = [
				'name' => $depositPoint->getLocalizedName(),
				'description' => $depositPoint->getLocalizedDescription(),
				'url' => $depositPoint->getSwordUrl(),
				'type' => $depositPoint->getType(),
				'username' => $depositPoint->getSwordUsername(),
				'password' => $depositPoint->getSwordPassword(),
				'apikey' => $depositPoint->getSwordApikey(),
			];
			if ($depositPoint->getType() == SWORD_DEPOSIT_TYPE_OPTIONAL_SELECTION) {
				$collections = DepositPointsHelper::loadCollectionsFromServer(
					$depositPoint->getSwordUrl(),
					$depositPoint->getSwordUsername(),
					$depositPoint->getSwordPassword(),
					$depositPoint->getSwordApikey()
				);
				$list[$depositPoint->getId()]['depositPoints'] = $collections;
			}
		}
		return $list;
	}
}
