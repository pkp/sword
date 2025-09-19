<?php

/**
 * @file SwordPlugin.php
 *
 * Copyright (c) 2013-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class SwordPlugin
 * @brief SWORD deposit plugin class
 */

namespace APP\plugins\generic\sword;

use PKP\mail\SubmissionMailTemplate;
use PKP\plugins\GenericPlugin;
use PKP\linkAction\request\RedirectAction;
use PKP\plugins\Hook;
use PKP\db\DAORegistry;
use PKP\linkAction\LinkAction;
use PKP\core\Registry;
use APP\core\Services;
use APP\facades\Repo;
use APP\template\TemplateManager;
use APP\core\Application;
use APP\submission\Submission;
use APP\plugins\generic\sword\classes\DepositPointDAO;
use APP\plugins\generic\sword\classes\PKPSwordDeposit;
use APP\plugins\generic\sword\SwordImportExportPlugin;
use APP\plugins\generic\sword\classes\SwordSchemaMigration;
use APP\plugins\generic\sword\controllers\grid\SwordDepositPointsGridHandler;
use APP\plugins\generic\sword\controllers\grid\SubmissionListGridHandler;

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
			Hook::add('PluginRegistry::loadCategory', array(&$this, 'callbackLoadCategory'));
			if ($this->getEnabled()) {
				$depositPointDao = new DepositPointDAO($this);
				DAORegistry::registerDao('DepositPointDAO', $depositPointDao);

				Hook::add('TemplateManager::display', [$this, 'callbackDisplayTemplate']);

				Hook::add('LoadHandler', array($this, 'callbackSwordLoadHandler'));
				Hook::add('Template::Settings::website', array($this, 'callbackSettingsTab'));
				Hook::add('LoadComponentHandler', array($this, 'setupGridHandler'));
				Hook::add('EditorAction::recordDecision', array($this, 'callbackAuthorDeposits'));

				// Preprints
				Hook::add('Publication::publish', array($this, 'callbackPublish'));

				// Extend the submission schema to include the SWORD statement IRI
				Hook::add('Schema::get::submission', function ($hookName, $args) {
					$schema = &$args[0];

					$schema->properties->swordStatementIri = (object)[
						'type' => 'string',
						'apiSummary' => false,
						'validation' => ['nullable']
					];
				});
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
			$journal = $request->getContext();
			$submission = $templateMgr->getTemplateVars('submission');
			$publication = $submission->getCurrentPublication();
			if ($this->getSetting($journal->getId(), 'showDepositButton') && ($publication->getData('status') == Submission::STATUS_PUBLISHED || !$this->getSetting($journal->getId(), 'showDepositButtonPublishedOnly'))) {
				$templateMgr->registerFilter("output", [$this, 'authorDashboardFilter']);
			}
		}

		return false;
	}

	public function authorDashboardFilter($output, $templateMgr) {
		if (strpos($output, '<div class="pkpWorkflow">') !== false
			&& preg_match_all('/<\/pkp-header>/', $output, $matches, PREG_OFFSET_CAPTURE)
		) {
			$match = $matches[0][1];
			$index = $match[1];
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
		$submission = Repo::submission()->get($newPublication->getData('submissionId'));

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
							'itemTitle' => $submission->getCurrentPublication()->getLocalizedTitle(),
							'repositoryName' => $depositPoint->getLocalizedName()
						])
					]
				);
			} catch (\Exception $e) {
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

				$mail = new SubmissionMailTemplate($submission, 'SWORD_DEPOSIT_NOTIFICATION', null, $context, true);

				$mail->setFrom($contactEmail, $contactName);
				$mail->addRecipient($submittingUser->getEmail(), $submittingUser->getFullName());

				$mail->assignParams([
					'contextName' => htmlspecialchars($context->getLocalizedName()),
					'submissionTitle' => htmlspecialchars($submission->getCurrentPublication()->getLocalizedTitle()),
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
		$op = $args[1];
		$handler =& $args[3];

		if ($page === 'sword' && $op == 'swordSettings') { // settings tab
			$handler = new SwordSettingsTabHandler($this);
			return true;
		} elseif ($page === 'sword' && in_array($op, ['depositPoints', 'performManagerOnlyDeposit', 'index'])) {
			$handler = new SwordHandler($this);
			return true;
		}
		return false;
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
		$componentInstance =& $params[2];
		if ($component == 'plugins.generic.sword.controllers.grid.SwordDepositPointsGridHandler') {
			import($component);
			$componentInstance = new SwordDepositPointsGridHandler($this);
			return true;
		}
		if ($component == 'plugins.generic.sword.controllers.grid.SubmissionListGridHandler') {
			$componentInstance = new SubmissionListGridHandler($this);
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
		$doc = new \DOMDocument();
		$doc->loadXML($xml);
		$xpath = new \DOMXpath($doc);
		$xpath->registerNamespace('atom', 'http://www.w3.org/2005/Atom');
		$elements = $xpath->query('//atom:link[@rel="alternate"]/@href');
		foreach ($elements as $element) {
			return $element->value;
		}
		return null;
	}
}
