{**
 * templates/authorDepositForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * Display list of deposit points.
 *}

{include file="common/header.tpl"}

 <script type="text/javascript">
	$(function() {ldelim}
		$('#depositPointsTabs').pkpHandler('$.pkp.controllers.TabHandler');
	{rdelim});
</script>
<style type="text/css">
	fieldset#authorDepositPoints li {ldelim} list-style: none; {rdelim}
</style>

<div id="depositPointsTabs">
	<ul>
		<li><a href="#authorDepositPoints-tab">{translate key="plugins.generic.sword.displayName"}</a></li>
	</ul>
	<div id="authorDepositPoints-tab">
		<form id="authDepositForm" class="pkp_form" method="post" action="{url path="index" path=$submission->getId()|to_array:"save"}">
			{csrf}
			{include file="controllers/notification/inPlaceNotification.tpl" notificationId="swordSettingsFormNotification"}

			{if !empty($depositPoints)}
				{translate key="plugins.generic.sword.authorDepositDescription" submissionTitle=$submission->getLocalizedTitle()}
				{fbvFormArea id="authorDepositPoints"}
					{foreach from=$depositPoints item=depositPoint key=depositPointKey name="depositPoints"}
						{fbvFormSection}
							{fbvElement
								type="checkbox"
								name="depositPoint[$depositPointKey][enabled]"
								id="depositPoint-$depositPointKey-enabled"
								label=$depositPoint.name
								translate=false
								inline=true}
							{if $depositPoint.type == $smarty.const.SWORD_DEPOSIT_TYPE_OPTIONAL_SELECTION}
								{fbvElement
									type="select"
									id="depositPoint"
									from=$depositPoint.depositPoints
									translate=false
									name="depositPoint[$depositPointKey][depositPoint]"
									id="depositPoint-$depositPointKey-depositPoint"
									inline=true}
							{/if}
						{/fbvFormSection}
					{/foreach}
				{/fbvFormArea}
			{/if}{* !empty($depositPoints) *}
			&nbsp;
			{if $allowAuthorSpecify}
				{translate key="plugins.generic.sword.authorCustomDepositDescription" submissionTitle=$submission->getLocalizedTitle()}
				{fbvFormSection for="authorDepositUrl" title="plugins.importexport.sword.depositUrl"}
					{fbvElement type="text" id="authorDepositUrl" value=$authorDepositUrl}
				{/fbvFormSection}
				{fbvFormSection for="authorDepositUsername" title="user.username"}
					{fbvElement type="text" id="authorDepositUsername" value=$authorDepositUsername}
				{/fbvFormSection}
				{fbvFormSection for="authorDepositPassword" title="user.password"}
					{fbvElement type="text" password="true" id="authorDepositPassword"}
				{/fbvFormSection}
			{/if}{* $allowAuthorSpecify *}

			<br/>
			{fbvElement type="submit" label="plugins.importexport.sword.deposit" id="depositBtn" inline=true}
		</form>
	</div>
 </div>{* depositPoints *}
 
 {include file="common/footer.tpl"}
