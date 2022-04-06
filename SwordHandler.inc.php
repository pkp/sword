<?php

/**
 * @file SwordHandler.inc.php
 *
 * Copyright (c) 2003-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class SwordHandler
 * @brief Handles request for sword plugin.
 */

import('classes.handler.Handler');
class SwordHandler extends Handler {
	/** @var SwordPlugin Sword plugin */
	protected $_parentPlugin = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		// set reference to markup plugin
		$this->_parentPlugin = PluginRegistry::getPlugin('generic', 'swordplugin');
		$this->addRoleAssignment(
			[ROLE_ID_MANAGER],
			['depositPoints','performManagerOnlyDeposit']
		);
		$this->addRoleAssignment(
			[ROLE_ID_MANAGER, ROLE_ID_AUTHOR],
			['index']
		);
	}

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
		$this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Get reference to the sword plugin
	 * @return SwordPlugin
	 */
	public function getSwordPlugin() {
		return $this->_parentPlugin;
	}

	/**
	 * Returns deposit point details
	 * @param $args array
	 * @param $request PKPRequest
	 *
	 * @return JSONMessage
	 */
	public function depositPoints($args, $request) {
		$context = $request->getContext();
		$depositPointId = $request->getUserVar('depositPointId');
		$this->getSwordPlugin()->import('classes.DepositPoint');
		$depositPointDao = DAORegistry::getDAO('DepositPointDAO');
		$depositPoint = $depositPointDao->getById($depositPointId, $context->getId());
		if (!$depositPoint) {
			return new JSONMessage(false);
		}

		$this->getSwordPlugin()->import('classes.DepositPointsHelper');
		$collections = DepositPointsHelper::loadCollectionsFromServer(
			$depositPoint->getSwordUrl(),
			$depositPoint->getSwordUsername(),
			$depositPoint->getSwordPassword(),
			$depositPoint->getSwordApikey()
		);
		return new JSONMessage(true, [
			'username' => $depositPoint->getSwordUsername(),
			'password' => SWORD_PASSWORD_SLUG,
			'apikey' => $depositPoint->getSwordApikey(),
			'depositPoints' => $collections,
		]);
	}

	/**
	 * Returns author deposit points page
	 * @param $args array
	 * @param $request PKPRequest
	 *
	 * @return JSONMessage
	 */
	public function index($args, $request) {
		$context = $request->getContext();
		$user = $request->getUser();
		$submissionId = (int) array_shift($args);
		$save = array_shift($args) == 'save';

		$submissionDao = DAORegistry::getDAO('SubmissionDAO');
		$submission = $submissionDao->getById($submissionId);

		if (!$submission || !$user || !$context ||
			($submission->getContextId() != $context->getId())) {
				$request->redirect(null, 'index');
		}

		$userCanDeposit = false;
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$daoResult = $stageAssignmentDao->getBySubmissionAndRoleId($submission->getId(), ROLE_ID_AUTHOR);
		while ($record = $daoResult->next()) {
			if($user->getId() == $record->getData('userId')) {
				$userCanDeposit = true;
				break;
			}
		}

		if (!$userCanDeposit) {
			$request->redirect(null, 'index');
		}

		$swordPlugin = $this->getSwordPlugin();
		$swordPlugin->import('AuthorDepositForm');
		$authorDepositForm = new AuthorDepositForm($swordPlugin, $context, $submission);

		if ($save) {
			$authorDepositForm->readInputData();
			if ($authorDepositForm->validate()) {
				$authorDepositForm->execute($request);
				$request->redirect(null, 'submissions');
			}
		}
		$authorDepositForm->initData();
		$authorDepositForm->display($request);
	}
}
