<?php

/**
 * @file controllers/grid/SwordDepositPointsGridHandler.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class SwordDepositPointsGridHandler
 * @brief Handle sword deposit points grid requests.
 */

import('lib.pkp.classes.controllers.grid.GridHandler');
import('plugins.generic.sword.controllers.grid.SwordDepositPointsGridRow');
import('plugins.generic.sword.controllers.grid.SwordDepositPointsGridCellProvider');

class SwordDepositPointsGridHandler extends GridHandler {
	/** @var SwordPlugin The SWORD plugin */
	public static $plugin;
	
	/**
	 * Set SWORD plugin.
	 * @param $plugin SwordPlugin
	 */
	static public function setPlugin($plugin) {
		self::$plugin = $plugin;
	}
	
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER),
			array('index', 'fetchGrid', 'fetchRow', 'addDepositPoint', 'editDepositPoint', 'updateDepositPoint', 'delete')
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
	 * @copydoc Gridhandler::initialize()
	 */
	public function initialize($request, $args = null) {
		parent::initialize($request);
		$context = $request->getContext();

		// Set the grid details.
		$this->setTitle('plugins.generic.sword.settings.depositPoints');
		$this->setEmptyRowText('plugins.generic.sword.manager.noneCreated');
		
		// Get the pages and add the data to the grid
		self::$plugin->import('classes.DepositPoint');
		$depositPointDao = DAORegistry::getDAO('DepositPointDAO');
		$depositPoints = $depositPointDao->getByContextId($context->getId());
		$this->setGridDataElements($depositPoints);

		// Add grid-level actions
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$this->addAction(
			new LinkAction(
				'addDepositPoint',
				new AjaxModal(
					$router->url($request, null, null, 'addDepositPoint'),
					__('plugins.generic.sword.depositPoints.create'),
					'modal_add_item'
					),
				__('plugins.generic.sword.depositPoints.create'),
				'add_item'
			)
		);
		
		// Columns
		$cellProvider = new SwordDepositPointsGridCellProvider();
		$this->addColumn(new GridColumn(
			'name',
			'plugins.generic.sword.depositPoints.name',
			null,
			'controllers/grid/gridCell.tpl',
			$cellProvider
		));
		$this->addColumn(new GridColumn(
			'url',
			'plugins.importexport.sword.depositUrl',
			null,
			'controllers/grid/gridCell.tpl',
			$cellProvider
		));
		$this->addColumn(new GridColumn(
			'type',
			'common.type',
			null,
			'controllers/grid/gridCell.tpl',
			$cellProvider
		));
	}
	
	/**
	 * @copydoc Gridhandler::getRowInstance()
	 */
	public function getRowInstance() {
		return new SwordDepositPointsGridRow();
	}

	/**
	 * Display the grid's containing page.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	public function index($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$dispatcher = $request->getDispatcher();
		return $templateMgr->fetchAjax(
			'swordDepositPointsGridContainer',
			$dispatcher->url(
				$request, ROUTE_COMPONENT, null,
				'plugins.generic.sword.controllers.grid.SwordDepositPointsGridHandler',
				'fetchGrid'
			)
		);
	}

	/**
	 * An action to add a new deposit point
	 * @param $args array Arguments to the request
	 * @param $request PKPRequest Request object
	 */
	public function addDepositPoint($args, $request) {
		return $this->editDepositPoint($args, $request);
	}

	/**
	 * An action to edit a deposit point
	 * @param $args array Arguments to the request
	 * @param $request PKPRequest Request object
	 * @return string Serialized JSON object
	 */
	public function editDepositPoint($args, $request) {
		$depositPointId = $request->getUserVar('depositPointId');
		$context = $request->getContext();
		$this->setupTemplate($request);
		self::$plugin->import('controllers.grid.form.SwordDepositPointForm');
		$swordDepositPointForm = new SwordDepositPointForm(self::$plugin, $context->getId(), $depositPointId);
		$swordDepositPointForm->initData();
		return new JSONMessage(true, $swordDepositPointForm->fetch($request));
	}

	/**
	 * Update deposit point
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	public function updateDepositPoint($args, $request) {
		$depositPointId = $request->getUserVar('depositPointId');
		$context = $request->getContext();
		$this->setupTemplate($request);

		self::$plugin->import('controllers.grid.form.SwordDepositPointForm');
		$swordDepositPointForm = new SwordDepositPointForm(self::$plugin, $context->getId(), $depositPointId);
		$swordDepositPointForm->readInputData($request);

		if ($swordDepositPointForm->validate()) {
			$swordDepositPointForm->execute();
			return DAO::getDataChangedEvent();
		} else {
			// Present any errors
			return new JSONMessage(true, $swordDepositPointForm->fetch($request));
		}
	}

	/**
	 * Delete a deposit point entry
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	public function delete($args, $request) {
		$depositPointId = $request->getUserVar('depositPointId');
		$context = $request->getContext();

		$depositPointDao = DAORegistry::getDAO('DepositPointDAO');
		$depositPointDao->deleteById($depositPointId, $context->getId());

		return DAO::getDataChangedEvent();
	}
}
