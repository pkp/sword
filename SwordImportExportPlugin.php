<?php

/**
 * @file SwordImportExportPlugin.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class SwordImportExportPlugin
 * @brief Sword deposit plugin
 */

namespace APP\plugins\generic\sword;

use PKP\plugins\ImportExportPlugin;
use PKP\core\JSONMessage;
use PKP\db\DAORegistry;

use APP\template\TemplateManager;
use APP\facades\Repo;

use APP\plugins\generic\sword\classes\DepositPoint;
use APP\plugins\generic\sword\classes\PKPSwordDeposit;

class SwordImportExportPlugin extends ImportExportPlugin {
	/** @var SwordPlugin Parent plugin */
	protected $_parentPlugin = null;

	/**
	 * Constructor
	 * @param $parentPlugin SwordPlugin
	 *
	 */
	public function __construct($parentPlugin) {
		parent::__construct();
		$this->_parentPlugin = $parentPlugin;
	}

	/**
	 * @copydoc Plugin::register()
	 */
	public function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		$this->addLocaleData();
		return $success;
	}

	/**
	 * Get reference to the sword plugin
	 * @return SwordPlugin
	 */
	public function getSwordPlugin() {
		return $this->_parentPlugin;
	}

	/**
	 * Get the name of this plugin. The name must be unique within
	 * its category.
	 * @return String name of plugin
	 */
	public function getName() {
		return 'SwordImportExportPlugin';
	}

	/**
	 * @copydoc Plugin::getPluginPath()
	 */
	function getPluginPath() {
		return $this->_parentPlugin->getPluginPath();
	}

	/**
	 * Get the display name.
	 * @return string
	 */
	public function getDisplayName() {
		return __('plugins.importexport.sword.displayName');
	}

	/**
	 * Get the display description.
	 * @return string
	 */
	public function getDescription() {
		return __('plugins.importexport.sword.description');
	}

	/**
	 * Display the plugin.
	 * @param $args array
	 * @param $request PKPRequest
	 */

	public function display($args, $request) {
		parent::display($args, $request);
		$templateMgr = TemplateManager::getManager($request);
		$context = $request->getContext();
		switch (array_shift($args)) {
			case 'index':
			case '':
				$depositPointDao = DAORegistry::getDAO('DepositPointDAO');
				$depositPoints = $depositPointDao->getByContextId($context->getId(), SWORD_DEPOSIT_TYPE_MANAGER);
				$depositPointsData = ['' => __('common.select')];
				foreach ($depositPoints as $depositPoint) {
					$depositPointsData[$depositPoint->getId()] = $depositPoint->getLocalizedName();
				}
				$dispatcher = $request->getDispatcher();
				$settingUrl = $dispatcher->url(
					$request, ROUTE_PAGE,
					null, 'management', 'settings', 'website',
					[],
					'swordSettings'
				);

				$apiUrl = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'submissions');
				$submissionsListPanel = new \APP\components\listPanels\SubmissionsListPanel(
					'submissions',
					__('common.publications'),
					[
						'apiUrl' => $apiUrl,
						'count' => 100,
						'getParams' => new \stdClass(),
						'lazyLoad' => true,
					]
				);
				$submissionsConfig = $submissionsListPanel->getConfig();
				$submissionsConfig['addUrl'] = '';
				$submissionsConfig['filters'] = array_slice($submissionsConfig['filters'], 1);
				$templateMgr->setState([
					'components' => [
						'submissions' => $submissionsConfig,
					],
				]);
				$templateMgr->assign([
					'pageComponent' => 'ImportExportPage',
					'selectedDepositPoint' 		=> $request->getUserVar('selectedDepositPoint'),
					'depositEditorial' 		=> $request->getUserVar('depositEditorial'),
					'depositGalleys' 		=> $request->getUserVar('depositGalleys'),
					'swordSettingsPageUrl' 		=> $settingUrl,
					'depositPoints' 		=> $depositPointsData,
					'pluginJavaScriptURL' 		=> $this->getSwordPlugin()->getJsUrl($request),
				]);
				$templateMgr->display($this->getTemplateResource('submissions.tpl'));
				break;

			case 'deposit':
				$context = $request->getContext();

				$depositPointId = $request->getUserVar('depositPoint');
				$password = $request->getUserVar('swordPassword');
				if ($password == SWORD_PASSWORD_SLUG) {
					$depositPointDao = DAORegistry::getDAO('DepositPointDAO');
					$depositPoint = $depositPointDao->getById($depositPointId, $context->getId());
					if ($depositPoint) {
						$password = $depositPoint->getSwordPassword();
					}
				}

				$swordDepositPoint = $request->getUserVar('swordDepositPoint');
				$depositEditorial = $request->getUserVar('depositEditorial');
				$depositGalleys = $request->getUserVar('depositGalleys');
				$username = $request->getUserVar('swordUsername');
				$depositIds = [];

				$backLink = $request->url(
					null, null, null,
					['plugin', $this->getName()],
					[
						'selectedDepositPoint' => $depositPointId,
						'depositEditorial' => $depositEditorial,
						'depositGalleys' => $depositGalleys,
					]
				);

				$errors = [];

				$submissionIds = $request->getUserVar('selectedSubmissions');
				// select at least one submission
				if (empty($submissionIds)) {
					$errors[] = [
						'title' => __('plugins.importexport.sword.requiredFieldErrorTitle'),
						'message' => __('plugins.importexport.sword.requiredFieldErrorMessage'),
					];
				}
				else {
					foreach ($submissionIds as $submissionId) {
						$submission = Repo::submission()->get($submissionId, $request->getContext()->getId());
						if (!$submission) {
							continue;
						}
						try {
							$deposit = new PKPSwordDeposit($submission);
							$deposit->setMetadata($request);
							if ($depositGalleys) $deposit->addGalleys();
							if ($depositEditorial) $deposit->addEditorial();
							$deposit->createPackage();
							$response = $deposit->deposit(
								$swordDepositPoint,
								$username,
								$password,
								$request->getUserVar('swordApiKey'));

							$statementCandidates = array_filter($response->sac_links, function($link) {
								return $link->sac_linkrel == 'http://purl.org/net/sword/terms/statement' || $link->sac_linkrel == 'http://purl.org/net/sword/terms/add';
							});

							$statementLink = array_shift($statementCandidates);
							$statementHref = $statementLink->sac_linkhref->__toString();
							$data = $submission->getAllData();
							$ssi = [];
							if (isset($data['swordStatementIri'])) {
								$ssi = unserialize($data['swordStatementIri']);
							}
							$ssi[$depositPointId] = $statementHref;
							Repo::submission()->edit($submission, ['swordStatementIri' => serialize($ssi)]);
							$deposit->cleanup();
							$depositIds[] = $response->sac_id;
						}
						catch (\Exception $e) {
							$errors[] = [
								'title' => $submission->getLocalizedTitle(),
								'message' => $e->getMessage(),
							];
						}
					}
				}

				if (!empty($errors)) {
					$errorMessage = '<dl>';
					foreach ($errors as $error) {
						$errorMessage .= "<dt>" . htmlentities($error['title']) . "</dt>";
						$errorMessage .= "<dd>" . htmlentities($error['message']) . "</dd>";
					}
					$errorMessage .= '</dl>';
					$templateMgr->assign([
						'title' => __('plugins.importexport.sword.depositFailed'),
						'messageTranslated' => $errorMessage,
						'backLink' => $backLink,
						'backLinkLabel' => 'common.back'
					]);
				}
				else {
					$templateMgr->assign([
						'title' => __('plugins.importexport.sword.depositSuccessful'),
						'message' => 'plugins.importexport.sword.depositSuccessfulDescription',
						'backLink' => $backLink,
						'backLinkLabel' => 'common.continue'
					]);
				}
				$messageTemplateFile = $this->getSwordPlugin()->getTemplateResource('message.tpl');
				$output = $templateMgr->fetch($messageTemplateFile);
				return new JSONMessage(true, $output);
				break;
			default:
				$dispatcher = $request->getDispatcher();
				$dispatcher->handle404();
		}
	}

	/**
	 * @copydoc PKPImportExportPlugin::executeCLI()
	 */
	public function executeCLI($scriptName, &$args) {
		throw new Exception('SWORD plugin command-line import/export not yet implemented!');
	}

	/**
	 * @copydoc PKPImportExportPlugin::usage
	 */
	public function usage($scriptName) {
	}
}
