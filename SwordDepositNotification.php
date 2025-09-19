<?php

/**
 * @file SwordDepositNotification.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SwordDepositNotification
 * @brief Email sent to a submitting author when they have the opportunity to deposit their submission elsewhere via SWORD
 */

namespace APP\plugins\generic\sword;

use APP\mail\variables\ContextEmailVariable;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use APP\core\Application;
use PKP\security\Role;

class SwordDepositNotification extends Mailable
{
    use Recipient;
    use Configurable;

    protected Submission $submission;

    protected static ?string $name = 'emails.swordDepositNotification.name';
    protected static ?string $description = 'emails.swordDepositNotification.description';
    protected static ?string $emailTemplateKey = 'SWORD_DEPOSIT_NOTIFICATION';
    protected static bool $canDisable = false;
    protected static array $groupIds = [self::GROUP_SUBMISSION];
    protected static array $fromRoleIds = [self::FROM_SYSTEM];
    protected static array $toRoleIds = [Role::ROLE_ID_AUTHOR];

    public function __construct(Context $context, Submission $submission)
    {
        parent::__construct(func_get_args());
        $this->submission = $submission;
    }

    /**
     * @copydoc Mailable::setData()
     */
    public function setData(?string $locale = null): void
    {
        parent::setData($locale);
        $request = Application::get()->getRequest();
        $this->addData([
			'swordDepositUrl' => $request->getDispatcher()->url($request, ROUTE_PAGE, null, 'sword', 'index', [$this->submission->getId()])
        ]);
    }
}
