{**
 * templates/editDepositPointForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * Form for editing a deposit point
 *}

<script type="text/javascript">
	$(function() {ldelim}
		// Attach the form handler.
		$('#depositPointForm').pkpHandler(
			'$.pkp.controllers.form.AjaxFormHandler'
		);
	{rdelim});
</script>

<form id="depositPointForm" class="pkp_form" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT component="plugins.generic.sword.controllers.grid.SwordDepositPointsGridHandler" op="updateDepositPoint" existingPageName=$blockName escape=false}">
	{csrf}

	{if $depositPointId}
		<input type="hidden" name="depositPointId" value="{$depositPointId|escape}" />
	{/if}

	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="DepositPointFormNotification"}

	{fbvFormSection}
		{fbvFormSection for="name" title="plugins.generic.sword.depositPoints.name"}
			{fbvElement type="text" id="name" name="name" value=$name multilingual=true}
		{/fbvFormSection}

		{fbvFormSection for="name" title="plugins.generic.sword.depositPoints.description"}
			{fbvElement type="textarea" multilingual=true id="description" value=$description rich=true}
		{/fbvFormSection}

		{fbvFormSection for="swordUrl" title="plugins.importexport.sword.depositUrl"}
			{fbvElement type="text" id="swordUrl" value=$swordUrl}
		{/fbvFormSection}

		{fbvFormSection for="swordUsername" title="user.username"}
			{fbvElement type="text" id="swordUsername" value=$swordUsername}
			<div>{translate key="plugins.generic.sword.depositPoints.leaveBlank"}</div>
		{/fbvFormSection}

		{fbvFormSection for="swordPassword" title="user.password"}
			{fbvElement type="text" password="true" id="swordPassword" value=$swordPassword}
			<div>{translate key="plugins.generic.sword.depositPoints.leaveBlank"}</div>
		{/fbvFormSection}

		{fbvFormSection for="swordApikey" title="plugins.generic.sword.depositPoints.apikey"}
			{fbvElement type="text" id="swordApikey" value=$swordApikey}
		{/fbvFormSection}

		{fbvFormSection title="common.type"}
			{fbvElement type="select" id="depositPointType" from=$depositPointTypes selected=$selectedType translate=false}
		{/fbvFormSection}

		{fbvFormSection description="plugins.generic.sword.depositPoints.type.description"}{/fbvFormSection}
	{/fbvFormSection}

	{fbvFormButtons id="depositPointSubmit" submitText="common.save"}
</form>
