<?php

/**
 * @file SwordSettingsTabHandler.inc.php
 *
 * Copyright (c) 2003-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class SwordSettingsTabHandler
 * @brief Responds to requests for SWORD settings page
 */

import('classes.handler.Handler');

class SwordSettingsTabHandler extends Handler {
	/** @var SwordPlugin Reference to SWORD plugin */
	protected $_plugin = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		$this->_plugin = PluginRegistry::getPlugin('generic', 'swordplugin');

		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER),
			array('swordSettings')
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
	 * SWORD plugin settings page
	 * @param $args array
	 * @param $request PKPRequest
	 *
	 * @return JSONMessage
	 */
	public function swordSettings($args, $request) {
		$context = $request->getContext();
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON,  LOCALE_COMPONENT_PKP_MANAGER);
		$templateMgr = TemplateManager::getManager($request);

		$this->_plugin->import('SwordSettingsForm');
		$form = new SwordSettingsForm($this->_plugin, $context);
		if ($request->getUserVar('save')) {
			$form->readInputData();
			if ($form->validate()) {
				$form->execute();
				$notificationManager = new NotificationManager();
				$notificationManager->createTrivialNotification(
					$request->getUser()->getId(),
					NOTIFICATION_TYPE_SUCCESS,
					array('contents' => __('plugins.generic.sword.settings.saved'))
				);
			}
		} else {
			$form->initData();
		}
		return new JSONMessage(true, $form->fetch($request));
	}
}
