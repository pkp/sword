<?php

/**
 * @file controllers/grid/SwordDepositPointsGridRow.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class SwordDepositPointsGridRow
 * @brief Handle custom blocks grid row requests.
 */

import('lib.pkp.classes.controllers.grid.GridRow');

class SwordDepositPointsGridRow extends GridRow {
	/**
	 * @copydoc GridRow::initialize()
	 */
	public function initialize($request, $template = null) {
		parent::initialize($request, $template);
		$depositPointId = $this->getId();

		if (!empty($depositPointId)) {
			$router = $request->getRouter();

			// edit action
			import('lib.pkp.classes.linkAction.request.AjaxModal');
			$this->addAction(
				new LinkAction(
					'editDepositPoint',
					new AjaxModal(
						$router->url($request, null, null, 'editDepositPoint', null, array('depositPointId' => $depositPointId)),
						__('grid.action.edit'),
						'modal_edit',
						true),
					__('grid.action.edit'),
					'edit'
				)
			);

			// delete action
			import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');
			$this->addAction(
				new LinkAction(
					'delete',
					new RemoteActionConfirmationModal(
						$request->getSession(),
						__('common.confirmDelete'),
						__('grid.action.delete'),
						$router->url($request, null, null, 'delete', null, array('depositPointId' => $depositPointId)), 'modal_delete'
					),
					__('grid.action.delete'),
					'delete'
				)
			);
		}
	}
}
