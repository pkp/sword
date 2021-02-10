<?php

/**
 * @file classes/DepositPointDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class DepositPointDAO
 * @brief Operations for retrieving and modifying DepositPoint objects.
 */

import('lib.pkp.classes.db.DAO');

class DepositPointDAO extends DAO {
	/** @var SwordPlugin reference to SWORD plugin */
	protected $_plugin = null;

	/**
	 * Constructor
	 * @param $parentPlugin SwordPlugin
	 */
	public function __construct(SwordPlugin $parentPlugin) {
		$this->_plugin = $parentPlugin;
		parent::__construct();
	}

	/**
	 * Instantiate a new data object.
	 * @return DepositPoint
	 */
	public function newDataObject() {
		$this->_plugin->import('classes.DepositPoint');
		return new DepositPoint();
	}

	/**
	 * Retrieve a deposit point by ID.
	 * @param $depositPointId int
	 * @param $contextId int
	 * @return DepositPoint
	 */
	public function getById($depositPointId, $contextId = null) {
		$params = array((int) $depositPointId);
		if ($contextId) $params[] = (int) $contextId;
		
		$result = $this->retrieve(
			'SELECT * FROM deposit_points WHERE deposit_point_id = ? ' . ($contextId?' AND context_id = ?':''),
			$params
		);
		
		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = $this->_fromRow($result->GetRowAssoc(false));
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Internal function to return DepositPoint object from a row.
	 * @param $row array
	 * @return DepositPoint
	 */
	public function _fromRow($row) {
		$depositPoint = $this->newDataObject();
		$depositPoint->setId($row['deposit_point_id']);
		$depositPoint->setContextId($row['context_id']);
		$depositPoint->setSequence($row['seq']);
		$depositPoint->setSwordUrl($row['url']);
		$depositPoint->setType($row['type']);
		$depositPoint->setSwordUsername($row['sword_username']);
		$depositPoint->setSwordPassword($row['sword_password']);
		$depositPoint->setSwordApikey($row['sword_apikey']);
		
		$this->getDataObjectSettings(
			'deposit_point_settings',
			'deposit_point_id',
			$row['deposit_point_id'],
			$depositPoint
		);
		
		return $depositPoint;
	}

	/**
	 * Insert a new deposit point.
	 * @param $depositPoint DepositPoint
	 * @return int
	 */
	public function insertObject($depositPoint) {
		$this->update(
			'INSERT INTO deposit_points
				(context_id,
				url,
				seq,
				type,
				sword_username,
				sword_password,
				sword_apikey)
			VALUES
				(?, ?, ?, ?, ?, ?, ?)',
			array(
				$depositPoint->getContextId(),
				$depositPoint->getSwordUrl(),
				$depositPoint->getSequence(),
				$depositPoint->getType(),
				$depositPoint->getSwordUsername(),
				$depositPoint->getSwordPassword(),
				$depositPoint->getSwordApikey(),
			)
		);
		$depositPoint->setId($this->getInsertId());
		
		$this->updateLocaleFields($depositPoint);
		
		return $depositPoint->getId();
	}

	/**
	 * Get a list of fields for which localized data is supported
	 * @return array
	 */
	public function getLocaleFieldNames() {
		return array('name');
	}

	/**
	 * Update the localized fields for this object.
	 * @param $depositPoint DepositPoint
	 */
	public function updateLocaleFields($depositPoint) {
		$this->updateDataObjectSettings('deposit_point_settings', $depositPoint, array(
			'deposit_point_id' => $depositPoint->getId()
		));
	}

	/**
	 * Update an existing deposit point.
	 * @param $depositPoint DepositPoint
	 * @return boolean
	 */
	public function updateObject($depositPoint) {
		$this->update(
			'UPDATE deposit_points
				SET
					context_id = ?,
					url = ?,
					seq = ?,
					type = ?,
					sword_username = ?,
					sword_password = ?,
					sword_apikey = ?
			WHERE deposit_point_id = ?',
			array(
				$depositPoint->getContextId(),
				$depositPoint->getSwordUrl(),
				$depositPoint->getSequence(),
				$depositPoint->getType(),
				$depositPoint->getSwordUsername(),
				$depositPoint->getSwordPassword(),
				$depositPoint->getSwordApikey(),
				$depositPoint->getId()
			)
		);
		
		$this->updateLocaleFields($depositPoint);
	}

	/**
	 * Delete a deposit point.
	 * @param $depositPoint DepositPoint
	 * @return boolean
	 */
	public function deleteObject($depositPoint) {
		return $this->deleteById($depositPoint->getId());
	}

	/**
	 * Check if a deposit point exists with the specified ID
	 * @param $depositPointId int Deposit Point ID
	 * @param $contextId int Context ID
	 */
	public function depositPointExists($depositPointId, $contextId) {
		$result = $this->retrieve(
			'SELECT COUNT(*) FROM deposit_points WHERE deposit_point_id = ? AND context_id = ?',
			array((int) $depositPointId, (int) $contextId)
		);
		$returner = isset($result->fields[0]) && $result->fields[0] == 1 ? true : false;
		$result->Close();
		return $returner;
	}

	/**
	 * Delete a deposit point by ID.
	 * @param $depositPointId int
	 * @param $contextId int
	 * @return boolean
	 */
	public function deleteById($depositPointId, $contextId = null) {
		if (isset($contextId) && !$this->depositPointExists($depositPointId, $contextId)) return false;
		$this->update(
			'DELETE FROM deposit_points WHERE deposit_point_id = ?', $depositPointId
		);
		$this->update(
			'DELETE FROM deposit_point_settings WHERE deposit_point_id = ?', $depositPointId
		);
		return true;
	}

	/**
	 * Delete deposit point by context ID.
	 * @param $contextId int
	 */
	public function deleteByContextId($contextId) {
		$depositPoints = $this->getByContextId($contextId);
		
		while ($depositPoint = $depositPoints->next()) {
			$this->deleteById($depositPoint->getId());
		}
	}

	/**
	 * Retrieve deposit points matching a particular context ID.
	 * @param $contextId int
	 * @param $rangeInfo object DBRangeInfo object describing range of results to return
	 * @param $type int limit results to a specific type
	 * @return object DAOResultFactory containing matching DepositPoints
	 */
	public function getByContextId($contextId, $rangeInfo = null, $type = null) {
		$params = array((int) $contextId);
		if ($type) $params[] = (int) $type;
		$result = $this->retrieveRange(
			'SELECT * FROM deposit_points WHERE context_id = ? '.($type?' AND type = ?':'').' ORDER BY seq ASC',
			$params,
			$rangeInfo
		);
		
		return new DAOResultFactory($result, $this, '_fromRow');
	}

	/**
	 * Sequentially renumber deposit points in their sequence order.
	 * @param $contextId int
	 */
	public function resequenceDepositPoints($contextId) {
		$result = $this->retrieve(
			'SELECT deposit_point_id FROM deposit_points WHERE context_id = ? ORDER BY seq',
			$contextId
		);
		for ($i=1; !$result->EOF; $i++) {
			list($depositPointId) = $result->fields;
			$this->update(
				'UPDATE deposit_points SET seq = ? WHERE deposit_point_id = ?',
				array(
					$i,
					$depositPointId
				)
			);
			
			$result->MoveNext();
		}
		$result->Close();
	}

	/**
	 * Get the ID of the last inserted deposit point.
	 * @return int
	 */
	public function getInsertId() {
		return $this->_getInsertId('deposit_points', 'deposit_point_id');
	}
}
