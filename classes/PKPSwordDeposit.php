<?php

/**
 * @file classes/sword/PKPSwordDeposit.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class PKPSwordDeposit
 * @ingroup plugins_generic_sword_classes
 *
 * @brief Class providing a SWORD deposit wrapper for submissions
 */

namespace APP\plugins\generic\sword\classes;

use PKP\submissionFile\SubmissionFile;
use PKP\file\FileManager;
use PKP\db\DAORegistry;

use APP\facades\Repo;
use APP\core\Application;
use APP\core\Services;

use APP\plugins\generic\sword\classes\PKPPackagerMetsSwap;

require_once dirname(__FILE__) . '/../libs/swordappv2/swordappclient.php';
require_once dirname(__FILE__) . '/../libs/swordappv2/swordappentry.php';

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

		$application = Application::get();
		$publication = $submission->getCurrentPublication();

		$this->_context = $application->getContextDao()->getById($submission->getContextId());

		$this->_section = Repo::section()->get($publication->getData('sectionId'));

		if ($application->getName() === 'ojs2') {
			$issueId = $publication->getData('issueId');
			if ($issueId) $this->_issue = Repo::issue()->get($issueId);
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
		$fileService = Services::get('file');
		$file = $fileService->get($submissionFile->getData('fileId'));
		$targetFilename = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $submissionFile->getLocalizedData('name'));
		$targetFilePath = $this->_outPath . '/files/' . $targetFilename;
		file_put_contents($targetFilePath, $fileService->fs->read($file->path));
		$this->_package->addFile($targetFilename, $file->mimetype);
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
		$fileStages = [
			SubmissionFile::SUBMISSION_FILE_PRODUCTION_READY,
			SubmissionFile::SUBMISSION_FILE_COPYEDIT,
			SubmissionFile::SUBMISSION_FILE_REVIEW_FILE,
			SubmissionFile::SUBMISSION_FILE_SUBMISSION
		];
		$submissionFiles = iterator_to_array(Repo::submissionFile()->getCollector()
			->filterBySubmissionIds([$this->_submission->getId()])
			->filterByFileStages($fileStages)
			->getMany()
		);
		// getBySubmission orders results by id ASC, let's reverse the array to have recent files first
		$submissionFiles = array_reverse($submissionFiles, true);
		$files = [];
		foreach ($submissionFiles as $submissionFile) {
			$fileStage = $submissionFile->getFileStage();
			if (!isset($files[$fileStage])) {
				$files[$fileStage] = [];
			}
			$files[$fileStage][] = $submissionFile;
		}
		// Move through stages in reverse order and try to use them.
		$mostRecentEditorialFile = null;
		foreach ($fileStages as $subFileStage) {
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
		$clientOpts = $apikey ? [CURLOPT_HTTPHEADER => ["X-Ojs-Sword-Api-Token:".$apikey]] : [];
		$client = new \SWORDAPPClient($clientOpts);
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
		$fileManager = new FileManager();
		$fileManager->rmtree($this->_outPath);
	}
}
