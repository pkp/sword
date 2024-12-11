<?php

/**
 * @file SwordPlugin.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class SwordPlugin
 * @brief SWORD deposit plugin class
 */

import('lib.pkp.classes.plugins.GenericPlugin');

define('SWORD_DEPOSIT_TYPE_AUTOMATIC',		1);
define('SWORD_DEPOSIT_TYPE_OPTIONAL_SELECTION',	2);
define('SWORD_DEPOSIT_TYPE_OPTIONAL_FIXED',	3);
define('SWORD_DEPOSIT_TYPE_MANAGER',		4);

define('SWORD_PASSWORD_SLUG', '******');

class SwordPlugin extends GenericPlugin {
	/**
	 * @copydoc Plugin::register()
	 */
	public function register($category, $path, $mainContextId = null) {
		if (parent::register($category, $path, $mainContextId)) {
			HookRegistry::register('PluginRegistry::loadCategory', array(&$this, 'callbackLoadCategory'));
			if ($this->getEnabled()) {
				$this->import('classes.DepositPointDAO');
				$depositPointDao = new DepositPointDAO($this);
				DAORegistry::registerDAO('DepositPointDAO', $depositPointDao);

				HookRegistry::register('TemplateManager::display', [$this, 'callbackDisplayTemplate']);

				HookRegistry::register('LoadHandler', array($this, 'callbackSwordLoadHandler'));
				HookRegistry::register('Template::Settings::website', array($this, 'callbackSettingsTab'));
				HookRegistry::register('LoadComponentHandler', array($this, 'setupGridHandler'));
				HookRegistry::register('EditorAction::recordDecision', array($this, 'callbackAuthorDeposits'));
				// Preprints
				HookRegistry::register('Publication::publish', array($this, 'callbackPublish'));
			}
			return true;
		}
		return false;
	}

	public function callbackDisplayTemplate($hookName, $args) {
		$templateMgr = $args[0];
		$template = $args[1];
		if ($template == 'authorDashboard/authorDashboard.tpl') {
			$request = Application::get()->getRequest();
	                $journal = $request->getJournal();
			if ($this->getSetting($journal->getId(), 'showDepositButton')) {
				$templateMgr->registerFilter("output", [$this, 'authorDashboardFilter']);
			}
		}

		return false;
	}

	public function authorDashboardFilter($output, $templateMgr) {
		if ($index = strrpos($output, '</pkp-header>')) {
			$request =& Registry::get('request');
			$headerAddition = '<form action="' . $request->url(null, 'sword', 'index', $request->getRequestedArgs()) . '"><button class="pkpButton">' . __('plugins.importexport.sword.deposit') . '</button></form>';
			$output = substr($output, 0, $index) . $headerAddition . substr($output, $index);
			$templateMgr->unregisterFilter('output', [$this, 'authorDashboardFilter']);
		}
		return $output;
	}

	/**
	 * Performs automatic deposit on publication
	 * @param $hookName string
	 * @param $args array
	 */
	public function callbackPublish($hookName, $args) {
		$newPublication =& $args[0];

		if ($newPublication->getData('status') != STATUS_PUBLISHED) return false;
		$submission = Services::get('submission')->get($newPublication->getData('submissionId'));

		$this->performAutomaticDeposits($submission);
	}

	/**
	 * Performs automatic deposit on accept decision
	 * @param $hookName string
	 * @param $args array
	 */
	public function callbackAuthorDeposits($hookName, $args) {
		$submission =& $args[0];
		$editorDecision =& $args[1];
		$decision = $editorDecision['decision'];
		// Determine if the decision was an "Accept"
		if ($decision != SUBMISSION_EDITOR_DECISION_ACCEPT) return false;

		$this->performAutomaticDeposits($submission);
	}

	/**
	 * Performs automatic deposits and mails authors
	 */
	function performAutomaticDeposits(Submission $submission) {
		// Perform Automatic deposits
		$request = Registry::get('request');
		$user = $request->getUser();
		$context = $request->getContext();
		$dispatcher = $request->getDispatcher();
		$this->import('classes.PKPSwordDeposit');
		$depositPointDao = DAORegistry::getDAO('DepositPointDAO');
		$depositPoints = $depositPointDao->getByContextId($context->getId());
		$sendDepositNotification = $this->getSetting($context->getId(), 'allowAuthorSpecify') ? true : false;
		$notificationMgr = new NotificationManager();
		foreach ($depositPoints as $depositPoint) {
			$depositType = $depositPoint->getType();
			if (($depositType == SWORD_DEPOSIT_TYPE_OPTIONAL_SELECTION)
				|| $depositType == SWORD_DEPOSIT_TYPE_OPTIONAL_FIXED) {
				$sendDepositNotification = true;
			}
			if ($depositType != SWORD_DEPOSIT_TYPE_AUTOMATIC)
				continue;

			try {
				$deposit = new PKPSwordDeposit($submission);
				$deposit->setMetadata($request);
				$deposit->addEditorial();
				$deposit->createPackage();
				$deposit->deposit(
					$depositPoint->getSwordUrl(),
					$depositPoint->getSwordUsername(),
					$depositPoint->getSwordPassword(),
					$depositPoint->getSwordApikey()
				);
				$deposit->cleanup();

				$notificationMgr->createTrivialNotification(
					$user->getId(),
					NOTIFICATION_TYPE_SUCCESS,
					[
						'contents' => __('plugins.generic.sword.automaticDepositComplete', [
							'itemTitle' => $submission->getLocalizedTitle(),
							'repositoryName' => $depositPoint->getLocalizedName()
						])
					]
				);
			} catch (Exception $e) {
				$notificationMgr->createTrivialNotification(
					$user->getId(),
					NOTIFICATION_TYPE_ERROR,
					['contents' => __('plugins.importexport.sword.depositFailed') . ': ' . $e->getMessage()]
				);
				error_log($e->getTraceAsString());
			}
		}

		if ($sendDepositNotification) {
			$submissionAuthors = [];
			$dao = DAORegistry::getDAO('StageAssignmentDAO');
			$daoResult = $dao->getBySubmissionAndRoleId($submission->getId(), ROLE_ID_AUTHOR);
			while ($record = $daoResult->next()) {
				$userId = $record->getData('userId');
				if (!in_array($userId, $submissionAuthors)) {
					array_push($submissionAuthors, $userId);
				}
			}

			$userDao = DAORegistry::getDAO('UserDAO');

			foreach ($submissionAuthors as $userId) {
				$submittingUser = $userDao->getById($userId);
				$contactName = $context->getSetting('contactName');
				$contactEmail = $context->getSetting('contactEmail');

				import('lib.pkp.classes.mail.SubmissionMailTemplate');
				$mail = new SubmissionMailTemplate($submission, 'SWORD_DEPOSIT_NOTIFICATION', null, $context, true);

				$mail->setFrom($contactEmail, $contactName);
				$mail->addRecipient($submittingUser->getEmail(), $submittingUser->getFullName());

				$mail->assignParams([
					'contextName' => htmlspecialchars($context->getLocalizedName()),
					'submissionTitle' => htmlspecialchars($submission->getLocalizedTitle()),
					'swordDepositUrl' => $dispatcher->url(
						$request, ROUTE_PAGE, null, 'sword', 'index', $submission->getId()
					)
				]);

				$mail->send($request);
			}
		}

		return false;
	}

	/**
	 * @copydoc PluginRegistry::loadCategory()
	 */
	public function callbackLoadCategory($hookName, $args) {
		$category =& $args[0];
		$plugins =& $args[1];
		switch ($category) {
			case 'importexport':
				$this->import('SwordImportExportPlugin');
				$importExportPlugin = new SwordImportExportPlugin($this);
				$plugins[$importExportPlugin->getSeq()][$importExportPlugin->getPluginPath()] =& $importExportPlugin;
				break;
		}
		return false;
	}

	/**
	 * @see PKPPageRouter::route()
	 */
	public function callbackSwordLoadHandler($hookName, $args) {
		// Check the page.
		$page = $args[0];
		if ($page !== 'sword') return;

		// Check the operation.
		$op = $args[1];

		if ($op == 'swordSettings') { // settings tab
			define('HANDLER_CLASS', 'SwordSettingsTabHandler');
			$args[2] = $this->getPluginPath() . '/' . 'SwordSettingsTabHandler.inc.php';
		}
		else {
			$publicOps = array(
				'depositPoints',
				'performManagerOnlyDeposit',
				'index',
			);

			if (!in_array($op, $publicOps)) return;

			define('HANDLER_CLASS', 'SwordHandler');
			$args[2] = $this->getPluginPath() . '/' . 'SwordHandler.inc.php';
		}
	}

	/**
	 * Extend the website settings tabs to include sword settings
	 * @param $hookName string The name of the invoked hook
	 * @param $args array Hook parameters
	 * @return boolean Hook handling status
	 */
	public function callbackSettingsTab($hookName, $args) {
		$output =& $args[2];
		$request =& Registry::get('request');
		$templateMgr = TemplateManager::getManager($request);
		$dispatcher = $request->getDispatcher();
		$tabLabel = __('plugins.generic.sword.settingsTabLabel');
		$templateMgr->assign(['sourceUrl' => $dispatcher->url($request, ROUTE_PAGE, null, 'sword', 'swordSettings')]);
		$output .= $templateMgr->fetch($this->getTemplateResource('swordSettingsTab.tpl'));
		return false;
	}

	/**
	 * Permit requests to SWORD deposit points grid handler
	 * @param $hookName string The name of the hook being invoked
	 * @param $args array The parameters to the invoked hook
	 */
	public function setupGridHandler($hookName, $params) {
		$component = $params[0];
		if ($component == 'plugins.generic.sword.controllers.grid.SwordDepositPointsGridHandler') {
			import($component);
			SwordDepositPointsGridHandler::setPlugin($this);
			return true;
		}
		if ($component == 'plugins.generic.sword.controllers.grid.SubmissionListGridHandler') {
			import($component);
			SubmissionListGridHandler::setPlugin($this);
			return true;
		}
		return false;
	}

	/**
	 * Get the display name of this plugin
	 * @return string
	 */
	public function getDisplayName() {
		return __('plugins.generic.sword.displayName');
	}

	/**
	 * Get the description of this plugin
	 * @return string
	 */
	public function getDescription() {
		return __('plugins.generic.sword.description');
	}

	/**
	 * @see Plugin::getActions()
	 */
	public function getActions($request, $verb) {
		$router = $request->getRouter();
		$dispatcher = $request->getDispatcher();
		import('lib.pkp.classes.linkAction.request.RedirectAction');
		return array_merge(
			// Settings
			$this->getEnabled()?array(
				new LinkAction(
					'swordSettings',
					new RedirectAction($dispatcher->url(
						$request, ROUTE_PAGE,
						null, 'management', 'settings', 'website',
						array('uid' => uniqid()),
						'swordSettings'
						)),
					__('manager.plugins.settings'),
					null
					),
			):array(),
			parent::getActions($request, $verb)
		);
	}

	/**
	 * Get plugin JS URL
	 *
	 * @return string Public plugin JS URL
	 */
	public function getJsUrl($request) {
		return $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js';
	}

	public function getTypeMap() {
		return array(
			SWORD_DEPOSIT_TYPE_AUTOMATIC		=> __('plugins.generic.sword.depositPoints.type.automatic'),
			SWORD_DEPOSIT_TYPE_OPTIONAL_SELECTION	=> __('plugins.generic.sword.depositPoints.type.optionalSelection'),
			SWORD_DEPOSIT_TYPE_OPTIONAL_FIXED	=> __('plugins.generic.sword.depositPoints.type.optionalFixed'),
			SWORD_DEPOSIT_TYPE_MANAGER		=> __('plugins.generic.sword.depositPoints.type.manager'),
		);
	}

	/**
	 * @copydoc PKPPlugin::getInstallMigration()
	 */
	function getInstallMigration() {
		$this->import('classes.SwordSchemaMigration');
		return new SwordSchemaMigration();
	}

	/**
	 * @see PKPPlugin::getInstallEmailTemplatesFile()
	 */
	function getInstallEmailTemplatesFile() {
		return ($this->getPluginPath() . '/emailTemplates.xml');
	}

	/**
	 * Given SWORD deposit receipt XML, find the atom:link@rel="alternate" and return its value.
	 * @return ?string
	 */
	public function getAlternateLink($xml) {
		$doc = new DOMDocument();
		$doc->loadXML($xml);
		$xpath = new DOMXpath($doc);
		$xpath->registerNamespace('atom', 'http://www.w3.org/2005/Atom');
		$elements = $xpath->query('//atom:link[@rel="alternate"]/@href');
		foreach ($elements as $element) {
			return $element->value;
		}
		return null;
	}
}
