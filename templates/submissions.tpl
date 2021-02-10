{**
 * templates/articles.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * Deposit articles in remote repositories
 *}
{extends file="layouts/backend.tpl"}
{block name="page"}
<script src="{$pluginJavaScriptURL}/SwordDepositPointsFormHandler.js"></script>
<script type="text/javascript">
	$(function() {ldelim}
		$('#importExportTabs').pkpHandler('$.pkp.controllers.TabHandler');
		$('#articlesForm').pkpHandler(
			'$.pkp.plugins.sword.js.SwordDepositPointsFormHandler',
			{ldelim}
				depositPointsUrl: {url|json_encode router=$smarty.const.ROUTE_PAGE page='sword' op="depositPoints"}
			{rdelim}
		);
	{rdelim});
</script>

<div id="importExportTabs">
	<ul>
		<li><a href="#importExportDeposits-tab">{translate key="plugins.importexport.sword.displayName"}</a></li>
	</ul>
	<div id="importExportDeposits-tab">
		<form id="articlesForm" class="pkp_form" method="post" action="{plugin_url path="deposit"}">
			{csrf}
			{include file="controllers/notification/inPlaceNotification.tpl" notificationId="ArticlesNotification"}
			{fbvFormSection}
				{fbvFormSection title="plugins.importexport.sword.depositPoint"}
					{fbvElement type="select" id="depositPoint" from=$depositPoints selected=$selectedDepositPoint translate=false}
					<a href="{$swordSettingsPageUrl}">{translate key="plugins.importexport.sword.depositPoint.addRemove"}</a>
					<span id="depositPointsSpinner" class="pkp_spinner"></span>
				{/fbvFormSection}
				{fbvFormSection for="swordUsername" title="user.username"}
					{fbvElement type="text" id="swordUsername" value=$swordUsername|escape}
				{/fbvFormSection}
				{fbvFormSection for="swordPassword" title="user.password"}
					{fbvElement type="text" password="true" id="swordPassword" value=$swordPassword|escape}
				{/fbvFormSection}
				{fbvFormSection for="swordApiKey" title="plugins.importexport.sword.apikey"}
					{fbvElement type="text" id="swordApiKey" value=$swordApiKey}
				{/fbvFormSection}
				{fbvFormSection title="plugins.importexport.sword.depositPoint"}
					{fbvElement type="select" id="swordDepositPoint" translate=false}
					{fbvElement type="button" label="common.refresh" id="refreshBtn" inline=true}
				{/fbvFormSection}
			{/fbvFormSection}
			{fbvFormSection title="common.options" list=true}
				{fbvElement type="checkbox" id="depositGalleys" value="1" checked=$depositGalleys label="plugins.importexport.sword.depositGalleys"}
				{fbvElement type="checkbox" id="depositEditorial" value="1" checked=$depositEditorial label="plugins.importexport.sword.depositEditorial"}
			{/fbvFormSection}
			{assign var="uuid" value=""|uniqid|escape}
			{if $usingApi}
			<div id="select-submissions-{$uuid}">
				<select-submissions-list-panel
					v-bind="components.selectSubmissionsListPanel"
					@set="set"
					/>
			</div>
			<script type="text/javascript">
				pkp.registry.init('select-submissions-{$uuid}', 'Container', {$selectSubmissionsListData|json_encode});
			</script>
			{else}
			<div id="select-submissions-list-handler-{$uuid}">
				<script type="text/javascript">
					pkp.registry.init('select-submissions-list-handler-{$uuid}', 'SelectSubmissionsListPanel', {$selectSubmissionsListData});
				</script>
			</div>
			{/if}
			{fbvElement type="submit" label="plugins.importexport.sword.deposit" id="depositBtn" inline=true}
			{fbvElement type="button" label="common.selectAll" id="selectAllBtn" inline=true}
		</form>
	</div>
</div>
{/block}
