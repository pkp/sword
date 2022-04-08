{**
 * templates/authorDepositForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * Display list of deposit points.
 *}

{include file="frontend/components/header.tpl" pageTitle="user.register"}

<form id="authDepositForm" class="cmp_form" method="post" action="{url path="index" path=$submission->getId()|to_array:"save"}">
	{csrf}

	{if !empty($depositPoints)}
		{translate key="plugins.generic.sword.authorDepositDescription" submissionTitle=$submission->getLocalizedTitle()}
		<fieldset id="authorDepositPoints">
			<ul class="prefabDepositPoints" style="list-style: none;">
				{foreach from=$depositPoints item=depositPoint key=depositPointKey name="depositPoints"}
					<li>
						<label>
							<input type="checkbox" name="depositPoint[{$depositPointKey|escape}][enabled]" id="depositPoint-{$depositPointKey|escape}-enabled" label="{$depositPoint.name|escape}">
							{$depositPoint.name|escape}
						</label>
						{if $depositPoint.type == $smarty.const.SWORD_DEPOSIT_TYPE_OPTIONAL_SELECTION}
							<label>
								{translate key="plugins.importexport.sword.depositPoint"}
								<select id="depositPoint-{$depositPointKey|escape}-depositPoint" name="depositPoint[{$depositPointKey|escape}][depositPoint]">
									{foreach from=$depositPoint.depositPoints key=depositPointKey item=depositPointValue}
										<option value="{$depositPointKey|escape}">{$depositPointValue|escape}</option>
									{/foreach}
								</select>
							</label>
						{/if}
					</li>
				{/foreach}
			</ul>
		</fieldset>
	{/if}{* !empty($depositPoints) *}

	{if $allowAuthorSpecify}
		{translate key="plugins.generic.sword.authorCustomDepositDescription" submissionTitle=$submission->getLocalizedTitle()}
		<fieldset class="authorSelfDeposit">
			<div class="fields">
				<div class="section">
					<label>
						<span class="label">{translate key="plugins.importexport.sword.depositUrl"}</span>
						<input type="text" name="authorDepositUrl" id="authorDepositUrl" value="{$authorDepositUrl|escape}" maxlength="255">
					</label>
				</div>

				<div class="section">
					<label>
						<span class="label">{translate key="user.username"}</span>
						<input type="text" id="authorDepositUsername" name="authorDepositUsername" value="{$authorDepositUsername|escape}" />
					</label>
				</div>

				<div class="section">
					<label>
						<span class="label">{translate key="user.password"}</span>
						<input type="text" id="authorDepositPassword" name="authorDepositPassword" />
					</label>
				</div>
			</div>
		</fieldset>
	{/if}{* $allowAuthorSpecify *}

	<button id="depositButton" class="pkp_button" type="submit">{translate key="plugins.importexport.sword.deposit"}</button>
</form>

{include file="frontend/components/footer.tpl"}
