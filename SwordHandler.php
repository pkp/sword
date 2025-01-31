<?php

/**
 * @file SwordHandler.php
 *
 * Copyright (c) 2003-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class SwordHandler
 * @brief Handles request for sword plugin.
 */

namespace APP\plugins\generic\sword;

use APP\facades\Repo;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\core\JSONMessage;
use PKP\plugins\PluginRegistry;
use PKP\security\Role;
use PKP\db\DAORegistry;
use PKP\security\Validation;

use APP\handler\Handler;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use APP\plugins\generic\sword\classes\DepositPoint;
use APP\plugins\generic\sword\classes\DepositPointsHelper;
use APP\plugins\generic\sword\DepositPointForm;
use APP\template\TemplateManager;

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
			[Role::ROLE_ID_MANAGER],
			['performManagerOnlyDeposit']
		);
		$this->addRoleAssignment(
			[Role::ROLE_ID_MANAGER, Role::ROLE_ID_AUTHOR],
			['index', 'depositPoints']
		);
	}

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
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
		/** @var DepositPointDAO $depositPointDao */
		$depositPointDao = DAORegistry::getDAO('DepositPointDAO');
		$depositPoint = $depositPointDao->getById($depositPointId, $context->getId());
		if (!$depositPoint) {
			return new JSONMessage(false);
		}

		$isManager = Validation::isAuthorized(Role::ROLE_ID_MANAGER, $context->getId());
		if (!$isManager && $depositPoint->getType() != SWORD_DEPOSIT_TYPE_OPTIONAL_SELECTION) {
			return new JSONMessage(false);
		}

		$collections = DepositPointsHelper::loadCollectionsFromServer(
			$depositPoint->getSwordUrl(),
			$depositPoint->getSwordUsername() ?: $request->getUserVar('username'),
			$depositPoint->getSwordPassword() ?: $request->getUserVar('password'),
			$depositPoint->getSwordApikey()
		);
		return new JSONMessage(true, [
			'username' => $isManager ? $depositPoint->getSwordUsername() : null,
			'password' => SWORD_PASSWORD_SLUG,
			'apikey' => $isManager ? $depositPoint->getSwordApikey() : null,
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

		$submission = Repo::submission()->get($submissionId);

		if (!$submission || !$user || !$context ||
			($submission->getData('contextId') != $context->getId())) {
				$request->redirect(null, 'index');
		}

		$userCanDeposit = false;
		/** @var StageAssignmentDAO $stageAssignmentDao */
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$daoResult = $stageAssignmentDao->getBySubmissionAndRoleId($submission->getId(), Role::ROLE_ID_AUTHOR);
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
		$authorDepositForm = new AuthorDepositForm($swordPlugin, $context, $submission);

		if ($save) {
			$authorDepositForm->readInputData();
			if ($authorDepositForm->validate()) {
				try {
					$responses = $authorDepositForm->execute($request);
					$templateMgr = TemplateManager::getManager($request);
					$results = [];
					/** @var DepositPointDAO $depositPointDao */
					$depositPointDao = DAORegistry::getDAO('DepositPointDAO');
					$depositPoints = iterator_to_array($depositPointDao->getByContextId($context->getId()));
					foreach ($responses as $url => $response) {
						// Identify the deposit point this result relates to
						$depositPoint = null;
						foreach ($depositPoints as $candidateDepositPoint) {
							if ($candidateDepositPoint->getSwordUrl() == $url) $depositPoint = $candidateDepositPoint;
						}

						// Add the result to the list
						$results[] = [
							'url' => $url,
							'depositPoint' => $depositPoint,
							'itemTitle' => $response->sac_title,
							'treatment' => $response->sac_treatment,
							'alternateLink' => $this->_parentPlugin->getAlternateLink($response->sac_xml),
						];
					}
					$templateMgr->assign('results', $results);
					$templateMgr->display($this->_parentPlugin->getTemplateResource('results.tpl'));
					return;
				} catch (\Exception $e) {
					$notificationManager = new NotificationManager();
					$notificationManager->createTrivialNotification(
						$user->getId(),
						Notification::NOTIFICATION_TYPE_ERROR,
						['contents' => __('plugins.importexport.sword.depositFailed') . ': ' . $e->getMessage()]
					);
					error_log($e->getTraceAsString());
				}
			}
		} else {
			$authorDepositForm->initData();
		}
		$authorDepositForm->display($request);
	}
}
