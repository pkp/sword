<?php

/**
 * @file SwordSettingsTabHandler.php
 *
 * Copyright (c) 2003-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class SwordSettingsTabHandler
 * @brief Responds to requests for SWORD settings page
 */

namespace APP\plugins\generic\sword;

use PKP\security\authorization\ContextAccessPolicy;
use PKP\db\DAORegistry;
use PKP\plugins\PluginRegistry;
use PKP\security\Role;
use PKP\i18n\Locale;
use PKP\core\JSONMessage;

use APP\handler\Handler;
use APP\template\TemplateManager;

use APP\plugins\generic\sword\SwordSettingsForm;
use APP\plugins\generic\sword\SwordPlugin;

class SwordSettingsTabHandler extends Handler {
	/** @var SwordPlugin Reference to SWORD plugin */
	protected $_plugin = null;

	/**
	 * Constructor
	 */
	public function __construct(SwordPlugin $plugin) {
		parent::__construct();

		$this->_plugin = $plugin;

		$this->addRoleAssignment(
			[Role::ROLE_ID_MANAGER],
			['swordSettings']
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
	 * SWORD plugin settings page
	 * @param $args array
	 * @param $request PKPRequest
	 *
	 * @return JSONMessage
	 */
	public function swordSettings($args, $request) {
		$context = $request->getContext();
		$templateMgr = TemplateManager::getManager($request);

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
