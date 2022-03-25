<?php

/**
 * @file classes/DepositPointsHelper.inc.php
 *
 * Copyright (c) 2003-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class DepositPointsHelper
 * @brief Deposit points Helper class
 */

require_once dirname(__FILE__) . '/../libs/swordappv2/swordappclient.php';

class DepositPointsHelper {
	/**
	 * Connects to a DSpace server and return a list of deposit points
	 * @param $url string
	 * @param $username string
	 * @param $password string
	 * @return array|null
	 */
	public static function loadCollectionsFromServer($url, $username, $password, $apikey = null) {
		$depositPoints = [];
		$clientOpts = $apikey ? [CURLOPT_HTTPHEADER => ["X-Ojs-Sword-Api-Token:".$apikey]] : [];
		$client = new SWORDAPPClient($clientOpts);
		$doc = $client->servicedocument($url, $username, $password, '');
		if ($doc->sac_status != 200) {
			return ['#' => 'Service Document Unreachable'];
		}
		if (is_array($doc->sac_workspaces)) {
			foreach ($doc->sac_workspaces as $workspace) {
				if (is_array($workspace->sac_collections)) {
					foreach ($workspace->sac_collections as $collection) {
						$depositPoints["$collection->sac_href"] = "$collection->sac_colltitle";
					}
				}
			}
		}
		return $depositPoints;
	}
}
