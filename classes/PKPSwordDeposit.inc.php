<?php

/**
 * @file classes/sword/PKPSwordDeposit.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class PKPSwordDeposit
 * @ingroup plugins_generic_sword_classes
 *
 * @brief Class providing a SWORD deposit wrapper for submissions
 */

require_once dirname(__FILE__) . '/../libs/swordappv2/swordappclient.php';
require_once dirname(__FILE__) . '/../libs/swordappv2/swordappentry.php';
require_once dirname(__FILE__) . '/PKPPackagerMetsSwap.php';

class PKPSwordDeposit {
	/** @var SWORD deposit METS package */
	protected $_package = null;

	/** @var Complete path and directory name to use for package creation files */
	protected $_outPath = null;

	/** @var Journal */
	protected $_context = null;

	/** @var Section */
	protected $_section = null;

	/** @var Issue */
	protected $_issue = null;

	/** @var Article */
	protected $_article = null;

	/**
	 * Constructor.
	 * Create a SWORD deposit object for a submission
	 * @param $submission Submission
	 */
	public function __construct($submission) {
		$this->_submission = $submission;

		// Create a directory for deposit contents
		$this->_outPath = tempnam('/tmp', 'sword');
		unlink($this->_outPath);
		mkdir($this->_outPath);
		mkdir($this->_outPath . '/files');

		// Create a package
		$this->_package = new PKPPackagerMetsSwap(
			$this->_outPath,
			'files',
			$this->_outPath,
			'deposit.zip'
		);

		$journalDao = DAORegistry::getDAO('JournalDAO');
		$this->_context = $journalDao->getById($submission->getContextId());

		$sectionDao = DAORegistry::getDAO('SectionDAO');
		$this->_section = $sectionDao->getById($submission->getSectionId());

		if (method_exists($submission, 'getIssueId')) {
			$issueDao = DAORegistry::getDAO('IssueDAO');
			$this->_issue = $issueDao->getById($submission->getIssueId());
		}
	}

	/**
	 * Register the article's metadata with the SWORD deposit.
	 * @param $request PKPRequest
	 */
	public function setMetadata($request) {
		$this->_package->setCustodian($this->_context->getContactName());
		$this->_package->setTitle(html_entity_decode($this->_submission->getLocalizedTitle(), ENT_QUOTES, 'UTF-8'));
		$this->_package->setAbstract(html_entity_decode(strip_tags($this->_submission->getLocalizedAbstract()), ENT_QUOTES, 'UTF-8'));
		$this->_package->setType($this->_section->getLocalizedIdentifyType());
		$publication = $this->_submission->getCurrentPublication();
		foreach ($publication->getData('authors') as $author) {
			$creator = $author->getFullName(true);
			$affiliation = $author->getLocalizedAffiliation();
			if (!empty($affiliation)) $creator .= "; $affiliation";
			$this->_package->addCreator($creator);
			$this->_package->sac_name_records[] = [
				'family' => $author->getFamilyName($publication->getData('locale')),
				'given' => $author->getGivenName($publication->getData('locale')),
				'email' => $author->getEmail(),
				'primary_contact' => ($author->getId() === $publication->getData('primaryContactId'))
			];
		}
	}

	/**
	 * Add a file to a package. Used internally.
	 * @param $submissionFile SubmissionFile
	 */
	public function _addFile($submissionFile) {
		$targetFilename = $this->_outPath . '/files/' . $submissionFile->getOriginalFileName();
		copy($submissionFile->getFilePath(), $targetFilename);
		$this->_package->addFile($submissionFile->getOriginalFileName(), $submissionFile->getFileType());
	}

	/**
	 * Add all article galleys to the deposit package.
	 */
	public function addGalleys() {
		foreach ($this->_submission->getGalleys() as $galley) {
			$this->_addFile($galley->getFile());
		}
	}

	/**
	 * Add the single most recent editorial file to the deposit package.
	 * @return boolean true iff a file was successfully added to the package
	 */
	public function addEditorial() {
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		$submissionFiles = $submissionFileDao->getBySubmissionId($this->_submission->getId());
		// getBySubmission orders results by id ASC, let's reverse the array to have recent files first
		$submissionFiles = array_reverse($submissionFiles, true);
		$files = array();
		foreach ($submissionFiles as $submissionFile) {
			$fileStage = $submissionFile->getFileStage();
			if (!isset($files[$fileStage])) {
				$files[$fileStage] = array();
			}
			$files[$fileStage][] = $submissionFile;
		}
		// Move through stages in reverse order and try to use them.
		$stages = array(
			SUBMISSION_FILE_PRODUCTION_READY,
			SUBMISSION_FILE_COPYEDIT,
			SUBMISSION_FILE_REVIEW_FILE,
			SUBMISSION_FILE_SUBMISSION
		);
		$mostRecentEditorialFile = null;
		foreach ($stages as $subFileStage) {
			if (isset($files[$subFileStage])) {
				$mostRecentEditorialFile = array_shift($files[$subFileStage]);
				$this->_addFile($mostRecentEditorialFile);
				return true;
			}
		}
		return false;
	}

	/**
	 * Build the package.
	 */
	public function createPackage() {
		$this->_package->create();
	}

	/**
	 * Deposit the package.
	 * @param $url string SWORD deposit URL
	 * @param $username string SWORD deposit username (i.e. email address for DSPACE)
	 * @param $password string SWORD deposit password
	 */
	public function deposit($url, $username, $password, $apikey = null) {
		if (!preg_match('/^http(s)?:\/\/.+/', $url)) {
			throw new Exception(__('plugins.generic.sword.badDepositPointUrl'));
		}
		$clientOpts = $apikey ? [CURLOPT_HTTPHEADER => ["X-Ojs-Sword-Api-Token:".$apikey]] : array();
		$client = new SWORDAPPClient($clientOpts);
		$response = $client->deposit(
			$url, $username, $password,
			'',
			$this->_outPath . '/deposit.zip',
			'http://purl.org/net/sword/package/METSDSpaceSIP',
			'application/zip', false, true
		);
		if ($response->sac_status > 299)
			throw new Exception("Status: $response->sac_status , summary: $response->sac_summary");

		return $response;
	}

	/**
	 * Clean up after a deposit, i.e. removing all created files.
	 */
	public function cleanup() {
		import('lib.pkp.classes.file.FileManager');
		$fileManager = new FileManager();
		$fileManager->rmtree($this->_outPath);
	}
}
