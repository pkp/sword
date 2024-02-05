<?php

/**
 * @file controllers/grid/form/SwordDepositPointForm.inc.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class SwordDepositPointForm
 * @brief Form to create and modify deposit points
 */

namespace APP\plugins\generic\sword\controllers\grid\form;

use PKP\form\Form;
use PKP\db\DAORegistry;
use PKP\form\validation\FormValidatorPost;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorLocale;
use PKP\form\validation\FormValidator;
use PKP\form\validation\FormValidatorUrl;

use APP\template\TemplateManager;

use APP\plugins\generic\sword\SwordPlugin;
use APP\plugins\generic\sword\classes\DepositPoint;
use APP\plugins\generic\sword\classes\DepositPointsHelper;

class SwordDepositPointForm extends Form {
	/** @var int Context ID */
	protected $_contextId;

	/** @var int depositPoint ID */
	protected $_depositPointId;

	/** @var SwordPlugin SWORD plugin */
	protected $_plugin;

	/** @var int Selected deposit point type */
	protected $selectedType = null;

	/**
	 * Constructor
	 * @param $swordPlugin SwordPlugin SWORD plugin
	 * @param $contextId int Context ID
	 * @param $depositPointId int Deposit Point (if any)
	 */
	public function __construct(SwordPlugin $swordPlugin, $contextId, $depositPointId = null) {
		parent::__construct($swordPlugin->getTemplateResource('editDepositPointForm.tpl'));
		$this->_contextId = $contextId;
		$this->_depositPointId = $depositPointId;
		$this->_plugin = $swordPlugin;

		// Add form checks
		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
		$this->addCheck(new FormValidatorLocale($this, 'name', 'required', 'plugins.generic.sword.depositPoints.required.field'));
		$this->addCheck(new FormValidator($this, 'depositPointType', 'required', 'plugins.generic.sword.depositPoints.required.field'));
		$this->addCheck(new FormValidatorUrl($this, 'swordUrl', 'required', 'plugins.generic.sword.depositPoints.required.field'));
	}

	/**
	 * Initialize form data.
	 */
	public function initData() {
		if ($this->_depositPointId) {
			$depositPointDao = DAORegistry::getDAO('DepositPointDAO');
			$depositPoint = $depositPointDao->getById($this->_depositPointId, $this->_contextId);
			$this->setData('swordUrl', $depositPoint->getSwordUrl());
			$this->setData('name', $depositPoint->getName(null));
			$this->setData('description', $depositPoint->getDescription(null));
			$this->selectedType = $depositPoint->getType();
			$this->setData('type', $this->selectedType);
			$this->setData('swordUsername', $depositPoint->getSwordUsername());
			$this->setData('swordPassword', SWORD_PASSWORD_SLUG);
			$this->setData('swordApikey', $depositPoint->getSwordApikey());
		}
	}

	/**
	 * Assign form data to user-submitted data.
	 * @param $request PKPRequest
	 */
	public function readInputData($request = null) {
		$this->readUserVars(
			[
				'swordUrl',
				'swordUsername',
				'swordPassword',
				'swordApikey',
				'depositPointType'
			]
		);
		$this->setData('name', $request->getUserVar('name'));
		$this->setData('description', $request->getUserVar('description'));
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign([
			'depositPointId' => $this->_depositPointId,
			'depositPointTypes' => $this->_plugin->getTypeMap(),
			'selectedType' => $this->selectedType,
			'pluginJavaScriptURL' 	=> $this->_plugin->getJsUrl($request),
		]);
		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::execute
	 */
	function execute(...$functionArgs) {
		$plugin = $this->_plugin;

		$depositPointDao = DAORegistry::getDAO('DepositPointDAO');

		$depositPoint = null;
		if (isset($this->_depositPointId)) {
			$depositPoint = $depositPointDao->getById($this->_depositPointId, $this->_contextId);
		}
		if (is_null($depositPoint)) {
			$depositPoint = new DepositPoint();
		}

		$depositPoint->setContextId($this->_contextId);
		$depositPoint->setName($this->getData('name'));
		$depositPoint->setDescription($this->getData('description'));
		$depositPoint->setType($this->getData('depositPointType'));
		switch ($depositPoint->getType()) {
			case SWORD_DEPOSIT_TYPE_OPTIONAL_SELECTION:
			case SWORD_DEPOSIT_TYPE_MANAGER:
				// This deposit point specifies a service document URL.
				// Allow resolution of the actual URL using the SWORD auto-discovery mechanism.
				$depositPoint->setSwordUrl(DepositPointsHelper::resolveServiceDocumentUrl($this->getData('swordUrl')));
				break;
			default:
				$depositPoint->setSwordUrl($this->getData('swordUrl')); 
		}
		$depositPoint->setSwordUsername($this->getData('swordUsername'));
		$depositPoint->setSwordApikey($this->getData('swordApikey'));
		$swordPassword = $this->getData('swordPassword');
		if (($swordPassword == SWORD_PASSWORD_SLUG) && !empty($depositPoint->getId())) {
			$depositPoint->setSwordPassword($depositPoint->getSwordPassword());
		}
		else {
			$depositPoint->setSwordPassword($swordPassword);
		}
		// Update or insert deposit point
		if ($depositPoint->getId() != null) {
			$depositPointDao->updateObject($depositPoint);
		} else {
			$depositPoint->setSequence(REALLY_BIG_NUMBER);
			$depositPointDao->insertObject($depositPoint);
			$depositPointDao->resequenceDepositPoints($depositPoint->getContextId());
		}
		parent::execute(...$functionArgs);
	}
}
