<?php

/**
 * @file classes/DepositPointDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class DepositPointDAO
 * @brief Operations for retrieving and modifying DepositPoint objects.
 */

namespace APP\plugins\generic\sword\classes;

use APP\plugins\generic\sword\classes\DepositPoint;
use APP\plugins\generic\sword\SwordPlugin;

use PKP\db\DAO;

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
		return new DepositPoint();
	}

	/**
	 * Retrieve a deposit point by ID.
	 * @param $depositPointId int
	 * @param $contextId int
	 * @return DepositPoint
	 */
	public function getById($depositPointId, $contextId = null) {
		$params = [(int) $depositPointId];
		if ($contextId) $params[] = (int) $contextId;
		
		$result = $this->retrieve(
			'SELECT * FROM deposit_points WHERE deposit_point_id = ? ' . ($contextId?' AND context_id = ?':''),
			$params
		);
		$row = $result->current();
		return $row ? $this->_fromRow((array) $row) : null;
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
			[
				$depositPoint->getContextId(),
				$depositPoint->getSwordUrl(),
				$depositPoint->getSequence(),
				$depositPoint->getType(),
				$depositPoint->getSwordUsername(),
				$depositPoint->getSwordPassword(),
				$depositPoint->getSwordApikey(),
			]
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
		return ['name', 'description'];
	}

	/**
	 * Update the localized fields for this object.
	 * @param $depositPoint DepositPoint
	 */
	public function updateLocaleFields($depositPoint) {
		$this->updateDataObjectSettings(
			'deposit_point_settings', $depositPoint,
			['deposit_point_id' => $depositPoint->getId()]
		);
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
			[
				$depositPoint->getContextId(),
				$depositPoint->getSwordUrl(),
				$depositPoint->getSequence(),
				$depositPoint->getType(),
				$depositPoint->getSwordUsername(),
				$depositPoint->getSwordPassword(),
				$depositPoint->getSwordApikey(),
				$depositPoint->getId()
			]
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
			'SELECT COUNT(*) AS row_count FROM deposit_points WHERE deposit_point_id = ? AND context_id = ?',
			[(int) $depositPointId, (int) $contextId]
		);
		$row = $result->current();
		return $row ? (boolean) $row->row_count : false;
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
			'DELETE FROM deposit_points WHERE deposit_point_id = ?', [$depositPointId]
		);
		$this->update(
			'DELETE FROM deposit_point_settings WHERE deposit_point_id = ?', [$depositPointId]
		);
		return true;
	}

	/**
	 * Delete deposit point by context ID.
	 * @param $contextId int
	 */
	public function deleteByContextId($contextId) {
		foreach ($this->getByContextId($contextId) as $depositPoint) {
			$this->deleteById($depositPoint->getId());
		}
	}

	/**
	 * Retrieve deposit points matching a particular context ID.
	 * @param $contextId int
	 * @param $rangeInfo object DBRangeInfo object describing range of results to return
	 * @param $type int limit results to a specific type
	 * @return Generator Set of matching DepositPoints
	 */
	public function getByContextId($contextId, $type = null) {
		$params = [(int) $contextId];
		if ($type) $params[] = (int) $type;
		$result = $this->retrieve(
			'SELECT * FROM deposit_points WHERE context_id = ? '.($type?' AND type = ?':'').' ORDER BY seq ASC',
			$params
		);
		foreach ($result as $row) {
			yield $row->deposit_point_id => $this->_fromRow((array) $row);
		}
	}

	/**
	 * Sequentially renumber deposit points in their sequence order.
	 * @param $contextId int
	 */
	public function resequenceDepositPoints($contextId) {
		$result = $this->retrieve(
			'SELECT deposit_point_id FROM deposit_points WHERE context_id = ? ORDER BY seq',
			[$contextId]
		);
		$i=1;
		foreach ($result as $row) {
			$this->update(
				'UPDATE deposit_points SET seq = ? WHERE deposit_point_id = ?',
				[$i, $row->deposit_point_id]
			);
			$i++;
		}
	}
}
