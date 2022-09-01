{**
 * templates/results.tpl
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * Display results of a deposit operation.
 *}

{include file="frontend/components/header.tpl" pageTitle="user.register"}

<style type="text/css">
div.depositPointTitle {
	font-weight: bold;
}
</style>

<div id="swordDepositResults">
	<h2>{translate key="plugins.generic.sword.depositsComplete"}</h2>
	<ul>
	{foreach from=$results item=result}
		<li>
			<div class="depositPointTitle">
				{if $result.depositPoint}
					{$result.depositPoint->getLocalizedName()|escape}
				{else}
					{$result.url|escape}
				{/if}
			</div>
			<div class="treatment">
				{if $result.alternateLink}<a href="{$result.alternateLink|escape}">{/if}
				{$result.treatment|escape}
				{if $result.alternateLink}</a>{/if}
			</div>
		</li>
	{/foreach}
	</ul>
</div>

{include file="frontend/components/footer.tpl"}
