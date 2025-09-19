<?php

/**
 * @file SwordSettingsForm.php
 *
 * Copyright (c) 2003-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class SwordSettingsForm
 * @brief Form for SWORD plugin settings
 */

namespace APP\plugins\generic\sword;

use PKP\form\Form;
use PKP\context\Context;

use APP\template\TemplateManager;

use APP\plugins\generic\sword\SwordPlugin;


class SwordSettingsForm extends Form {
	/** @var $_context Context */
	protected $_context = null;

	/** @var $_plugin SwordPlugin */
	protected $_plugin = null;
	
	/**
	 * Constructor
	 * @param $plugin SwordPlugin
	 * @param $context Context
	 */
	public function __construct(SwordPlugin $plugin, Context $context) {
		$this->_plugin = $plugin;
		$this->_context = $context;
		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
	}

	/**
	 * Initialize plugin settings form
	 *
	 * @return void
	 */
	public function initData() {
		$this->setData('allowAuthorSpecify', $this->_plugin->getSetting($this->_context->getId(), 'allowAuthorSpecify'));
		$this->setData('showDepositButton', $this->_plugin->getSetting($this->_context->getId(), 'showDepositButton'));
		$this->setData('showDepositButtonPublishedOnly', $this->_plugin->getSetting($this->_context->getId(), 'showDepositButtonPublishedOnly'));
	}

	/**
	 * Assign form data to user-submitted data
	 *
	 * @return void
	 */
	public function readInputData() {
		$this->readUserVars(['allowAuthorSpecify', 'showDepositButton', 'showDepositButtonPublishedOnly']);
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pluginJavaScriptURL', $this->_plugin->getJsUrl($request));
		return parent::fetch($request, $template, $display);
	}

	/**
	 * Save form.
	 */
	public function execute(...$functionArgs) {
		$allowAuthorSpecify = intval($this->getData('allowAuthorSpecify'));
		$this->_plugin->updateSetting($this->_context->getId(), 'allowAuthorSpecify', $allowAuthorSpecify);

		$showDepositButton = intval($this->getData('showDepositButton'));
		$this->_plugin->updateSetting($this->_context->getId(), 'showDepositButton', $showDepositButton);

		$showDepositButtonPublishedOnly = intval($this->getData('showDepositButtonPublishedOnly'));
		$this->_plugin->updateSetting($this->_context->getId(), 'showDepositButtonPublishedOnly', $showDepositButtonPublishedOnly);

		parent::execute(...$functionArgs);
	}
}
