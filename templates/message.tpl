{**
 * templates/message.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * Display success/failure message after deposit
 *}

<div class="page page_message">
	<h2>{$title}</h2>
	<div class="description">
		{if $messageTranslated}
			{$messageTranslated}
		{else}
			{translate key=$message}
		{/if}
	</div>
	{if $backLink}
		<div class="cmp_back_link">
			<a href="{$backLink}">{translate key=$backLinkLabel}</a>
		</div>
	{/if}
</div>
