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
	public static function resolveServiceDocumentUrl($url) {
		try {
			$client = Application::get()->getHttpClient();
			$response = $client->request('GET', $url);
			$matches = null;
			// Attempt to match one of the service point auto-discovery link forms specified at:
			// http://swordapp.github.io/SWORDv2-Profile/SWORDProfile.html#autodiscovery
			if (preg_match('/<html:link\s+rel="(sword|http:\/\/purl.org\/net\/sword\/discovery\/service-document)"\s+href="([^"]+)"\s*[\/]?>/i', $response->getBody(), $matches)) {error_log('SUCCESSFULLY RESOLVED ' . $matches[2]);
				return $matches[2];
			}
		} catch (Throwable $e) {
			// In case of any error, just return the provided URL.
			return $url;
		}
	}

	/**
	 * Connects to a SWORD server and return a list of deposit points
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
