<?php

/**
 * @file plugins/generic/sword/SwordImportExportPlugin.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SwordImportExportPlugin
 * @ingroup plugins_generic_sword
 *
 * @brief Sword deposit plugin
 */

import('lib.pkp.classes.plugins.ImportExportPlugin');

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
		HookRegistry::register('publishedarticledao::getAdditionalFieldNames', array($this, 'getAdditionalFieldNames'));
		return $success;
	}

	/**
	 * Add statementIRI element to the article
	 * @param $hookName string
	 * @param $params array
	 */
	function getAdditionalFieldNames($hookName, $params) {
		$fields =& $params[1];
		$fields[] = 'swordStatementIri';
		return false;
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
				$this->getSwordPlugin()->import('classes.DepositPoint');
				$depositPointDao = DAORegistry::getDAO('DepositPointDAO');
				$depositPoints = $depositPointDao->getByContextId($context->getId(), null, SWORD_DEPOSIT_TYPE_MANAGER);
				$depositPointsData = array('' => __('common.select'));
				while ($depositPoint = $depositPoints->next()) {
					$depositPointsData[$depositPoint->getId()] = $depositPoint->getLocalizedName();
				}
				$dispatcher = $request->getDispatcher();
				$settingUrl = $dispatcher->url(
					$request, ROUTE_PAGE,
					null, 'management', 'settings', 'website',
					array(),
					'swordSettings'
				);

				import('lib.pkp.controllers.list.submissions.SelectSubmissionsListHandler');
				$selectSubmissionsListHandler = new SelectSubmissionsListHandler(array(
					'title' 	=> 'navigation.submissions',
					'count' 	=> 100,
					'inputName' 	=> 'articleId[]',
					'getParams' => array(
						'status'	=> STATUS_PUBLISHED,
					),
				));
				$publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
				$selectSubmissionsConfig = $selectSubmissionsListHandler->getConfig();
				$depositedIds = [];
				foreach ($selectSubmissionsConfig['items'] as $item) {
					$publishedArticle = $publishedArticleDao->getByArticleId($item['id']);
					if (is_null($publishedArticle)) {
						continue;
					}
					if ($ssi = $publishedArticle->getData("swordStatementIri")) {
						$depositedIds[$item['id']] = $ssi;
					}
				}
				$selectSubmissionsConfig = json_encode($selectSubmissionsConfig);

				$templateMgr->assign(array(
					'selectedDepositPoint' 		=> $request->getUserVar('selectedDepositPoint'),
					'depositEditorial' 		=> $request->getUserVar('depositEditorial'),
					'depositGalleys' 		=> $request->getUserVar('depositGalleys'),
					'swordSettingsPageUrl' 		=> $settingUrl,
					'depositPoints' 		=> $depositPointsData,
					'pluginJavaScriptURL' 		=> $this->getSwordPlugin()->getJsUrl($request),
					'selectSubmissionsListData' 	=> $selectSubmissionsConfig
				));
				$script = '$(document).data("depositedIdMap", '. json_encode($depositedIds) .');';
				$templateMgr->addJavaScript('depositedIds', $script, ['inline' => true, 'contexts' => 'backend']);
				$templateMgr->addJavaScript(
					'disableDepositedIds',
					$this->getSwordPlugin()->getJsUrl($request) .'/SwordDisableDepositedItems.js',
					['contexts' => 'backend']);
				$templateMgr->display($this->_parentPlugin->getTemplateResource('articles.tpl'));
				break;

			case 'deposit':
				$context = $request->getContext();
				$publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
				$this->getSwordPlugin()->import('classes.OJSSwordDeposit');

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
				$depositIds = array();

				$backLink = $request->url(
					null, null, null,
					array('plugin', $this->getName()),
					array(
						'selectedDepositPoint' => $depositPointId,
						'depositEditorial' => $depositEditorial,
						'depositGalleys' => $depositGalleys,
					)
				);

				$errors = array();
				// select at least one article
				$articleIds = $request->getUserVar('articleId');
				if (empty($articleIds)) {
					$errors[] = array(
						'title' => __('plugins.importexport.sword.requiredFieldErrorTitle'),
						'message' => __('plugins.importexport.sword.requiredFieldErrorMessage'),
					);
				}
				else {
					foreach ($articleIds as $articleId) {
						$publishedArticle = $publishedArticleDao->getByArticleId($articleId);
						try {
							$deposit = new OJSSwordDeposit($publishedArticle);
							$deposit->setMetadata($request);
							if ($depositGalleys) $deposit->addGalleys();
							if ($depositEditorial) $deposit->addEditorial();
							$deposit->createPackage();
							$response = $deposit->deposit(
								$swordDepositPoint,
								$username,
								$password,
								$request->getUserVar('swordApiKey'));

							$stmt_link = array_shift(
								array_filter($response->sac_links, function($link) {
									return $link->sac_linkrel == 'http://purl.org/net/sword/terms/statement' || $link->sac_linkrel == 'http://purl.org/net/sword/terms/add';
								}));
							if (!is_null($stmt_link)) {
								$stmt_href = $stmt_link->sac_linkhref->__toString();
								$data = $publishedArticle->getAllData();
								$ssi = [];
								if (array_has($data, 'swordStatementIri')) {
									$ssi = unserialize($data['swordStatementIri'], true);
								}
								$ssi[$depositPointId] = $stmt_href;
								$publishedArticle->setData('swordStatementIri', serialize($ssi));
								$publishedArticleDao->updateDataObjectSettings(
									'submission_settings', $publishedArticle, ['submission_id' => $articleId]);
							}
							$deposit->cleanup();
							$depositIds[] = $response->sac_id;
						}
						catch (Exception $e) {
							$errors[] = array(
								'title' => $publishedArticle->getLocalizedTitle(),
								'message' => $e->getMessage(),
							);
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
					$templateMgr->assign(array(
						'title' => __('plugins.importexport.sword.depositFailed'),
						'messageTranslated' => $errorMessage,
						'backLink' => $backLink,
						'backLinkLabel' => 'common.back'
					));
				}
				else {
					$templateMgr->assign(array(
						'title' => __('plugins.importexport.sword.depositSuccessful'),
						'message' => 'plugins.importexport.sword.depositSuccessfulDescription',
						'backLink' => $backLink,
						'backLinkLabel' => 'common.continue'
					));
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
