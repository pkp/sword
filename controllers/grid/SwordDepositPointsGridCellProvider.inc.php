<?php

/**
 * @file controllers/grid/SwordDepositPointsGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class SwordDepositPointsGridCellProvider
 * @brief Class for a cell provider to display information about deposit point
 */

import('lib.pkp.classes.controllers.grid.GridCellProvider');
import('lib.pkp.classes.linkAction.request.RedirectAction');

class SwordDepositPointsGridCellProvider extends GridCellProvider {
	/**
	 * Extracts variables for a given column from a data element
	 * so that they may be assigned to template before rendering.
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return array
	 */
	public function getTemplateVarsFromRowColumn($row, $column) {
		$depositPoint = $row->getData();
		switch ($column->getId()) {
			case 'name':
				return array('label' => $depositPoint->getLocalizedName());
			case 'url':
				return array('label' => $depositPoint->getSwordUrl());
			case 'type':
				switch ($depositPoint->getType()) {
					case SWORD_DEPOSIT_TYPE_AUTOMATIC:
						return array('label' => __('plugins.generic.sword.depositPoints.type.automatic'));
					case SWORD_DEPOSIT_TYPE_OPTIONAL_SELECTION:
						return array('label' => __('plugins.generic.sword.depositPoints.type.optionalSelection'));
					case SWORD_DEPOSIT_TYPE_OPTIONAL_FIXED:
						return array('label' => __('plugins.generic.sword.depositPoints.type.optionalFixed'));
					case SWORD_DEPOSIT_TYPE_MANAGER:
						return array('label' => __('plugins.generic.sword.depositPoints.type.manager'));
					default:
						return assert(false);
				}
		}
		return parent::getTemplateVarsFromRowColumn($row, $column);
	}
}
