<?php

/**
* @file AuthorDepositForm.inc.php
*
* Copyright (c) 2003-2021 Simon Fraser University
* Copyright (c) 2003-2021 John Willinsky
* Distributed under the GNU GPL v3. For full terms see the file LICENSE.
*
* @class AuthorDepositForm
* @brief Form to perform an author's SWORD deposit(s)
*/

import('lib.pkp.classes.form.Form');

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
	public function display() {
		$request = Application::getRequest();
		$templateMgr = TemplateManager::getManager($request);
		$depositPoints = $this->_getDepositableDepositPoints($this->_context);
		$templateMgr->assign([
			'depositPoints' 	=> $depositPoints,
			'submission'		=> $this->_submission,
			'allowAuthorSpecify' 	=> $this->getSwordPlugin()->getSetting($this->_context->getId(), 'allowAuthorSpecify'),
			'pluginJavaScriptURL' 	=> $this->_plugin->getJsUrl($request),
		]);
		parent::display();
	}

	/**
	 * Save form.
	 * @param $request PKPRequest
	 */
	public function execute($request) {
		$user = $request->getUser();
		$notificationManager = new NotificationManager();
		$this->getSwordPlugin()->import('classes.PKPSwordDeposit');
		
		$deposit = new PKPSwordDeposit($this->_submission);
		$deposit->setMetadata($request);
		$deposit->addEditorial();
		$deposit->createPackage();
		
		$allowAuthorSpecify = $this->getSwordPlugin()->getSetting($this->_context->getId(), 'allowAuthorSpecify');
		$authorDepositUrl = $this->getData('authorDepositUrl');
		if (($allowAuthorSpecify) && ($authorDepositUrl != '')) {
			try {
				$deposit->deposit(
					$this->getData('authorDepositUrl'),
					$this->getData('authorDepositUsername'),
					$this->getData('authorDepositPassword')
				);
				$params = [
					'itemTitle' => $this->_submission->getLocalizedTitle(), 
					'repositoryName' => $this->getData('authorDepositUrl')
				];
				$notificationManager->createTrivialNotification(
					$user->getId(), 
					NOTIFICATION_TYPE_SUCCESS, 
					['contents' => __('plugins.generic.sword.depositComplete', $params)]
				);
			}
			catch(Exception $e) {
				$contents = __('plugins.importexport.sword.depositFailed') . ': ' . $e->getMessage();
				$notificationManager->createTrivialNotification(
					$user->getId(),
					NOTIFICATION_TYPE_ERROR,
					['contents' => $contents]
				);
				error_log($e->getTraceAsString());
			}
		}
		
		$url = '';
		$this->getSwordPlugin()->import('classes.DepositPoint');
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
			try {
				$deposit->deposit(
					$url,
					$depositPoint['username'],
					$depositPoint['password'],
					$depositPoint['apikey']
				);
				$params = [
					'itemTitle' => $this->_submission->getLocalizedTitle(), 
					'repositoryName' => $depositPoint['name']
				];
				$notificationManager->createTrivialNotification(
					$user->getId(), 
					NOTIFICATION_TYPE_SUCCESS,
					['contents' => __('plugins.generic.sword.depositComplete', $params)]
				);
			}
			catch(Exception $e) {
				$contents = __('plugins.importexport.sword.depositFailed') . ': ' . $e->getMessage();
				$notificationManager->createTrivialNotification(
					$user->getId(),
					NOTIFICATION_TYPE_ERROR,
					['contents' => $contents]
				);
				error_log($e->getTraceAsString());
			}
		}
		$deposit->cleanup();
	}

	/**
	 * Build a list of collections available for deposit points of type SWORD_DEPOSIT_TYPE_OPTIONAL_SELECTION
	 * @param $context Context
	 * @return array
	 */
	protected function _getDepositableDepositPoints($context) {
		$list = [];
		$this->getSwordPlugin()->import('classes.DepositPoint');
		$depositPointDao = DAORegistry::getDAO('DepositPointDAO');
		$this->getSwordPlugin()->import('classes.DepositPointsHelper');
		$depositPoints = $depositPointDao->getByContextId($context->getId());
		foreach ($depositPoints as $depositPoint) {
			if (!in_array($depositPoint->getType(), [SWORD_DEPOSIT_TYPE_OPTIONAL_SELECTION, SWORD_DEPOSIT_TYPE_OPTIONAL_FIXED]))
				continue;

			$list[$depositPoint->getId()]['name'] = $depositPoint->getLocalizedName();
			$list[$depositPoint->getId()]['url'] = $depositPoint->getSwordUrl();
			$list[$depositPoint->getId()]['type'] = $depositPoint->getType();
			$list[$depositPoint->getId()]['username'] = $depositPoint->getSwordUsername();
			$list[$depositPoint->getId()]['password'] = $depositPoint->getSwordPassword();
			$list[$depositPoint->getId()]['apikey'] = $depositPoint->getSwordApikey();
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
