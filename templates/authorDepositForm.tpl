{**
 * templates/authorDepositForm.tpl
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * Display list of deposit points.
 *}

{include file="frontend/components/header.tpl" pageTitle="user.register"}

{literal}
<script type="text/javascript">
	function updateCheckboxDisplay(checkbox) {
		var $checkbox = $(checkbox);
		var $depositPointDetails = $checkbox.parent().next('.depositPointDetails');
		if ($checkbox.prop('checked')) {
			$depositPointDetails.show(200);
		} else {
			$depositPointDetails.hide(200);
		}
	}
	function refreshDepositPoint(depositPointId) {
		var username = $('#depositPoint-' + depositPointId + '-username').val(),
			password = $('#depositPoint-' + depositPointId + '-password').val(),
			$depositPointList = $('#depositPoint-' + depositPointId + '-depositPoint');

		$('#depositPoint-' + depositPointId + '-spinner').show();
		$.post("{/literal}{url op="depositPoints" depositPointId="DEPOSIT_POINT_ID_SLUG"}{literal}".replace('DEPOSIT_POINT_ID_SLUG', depositPointId), {"username": username, "password": password}, function(data) {
			$('#depositPoint-' + depositPointId + '-spinner').hide();
			$depositPointList.empty();
			$.each(data.content.depositPoints, function(url, label) {
				$depositPointList.append($('<option>', {
					"value": url,
					"text": label
				}));
			});
		});
	}
</script>
{/literal}

<form id="authDepositForm" class="cmp_form" method="post" action="{url path="index" path=$submission->getId()|to_array:"save"}">
	{csrf}

	{if !empty($depositPoints)}
		{translate key="plugins.generic.sword.authorDepositDescription" submissionTitle=$submission->getLocalizedTitle()}
		<fieldset id="authorDepositPoints">
			<ul class="prefabDepositPoints" style="list-style: none;">
				{foreach from=$depositPoints item=depositPoint key=depositPointKey name="depositPoints"}
					<li>
						<label>
							<input onclick="updateCheckboxDisplay(this);" type="checkbox" name="depositPoint[{$depositPointKey|escape}][enabled]" id="depositPoint-{$depositPointKey|escape}-enabled" label="{$depositPoint.name|escape}">
							{$depositPoint.name|escape}
						</label>
						<div class="depositPointDetails" style="display: none; padding: 0em 1em 2em 0em;">
							<div class="depositPointdescription">{$depositPoint.description}</div>
							{if empty($depositPoint.username)}
								<div class="section">
									<label>
										<span class="label">{translate key="user.username"}</span>
										<input type="text" id="depositPoint-{$depositPointKey|escape}-username" name="depositPoint[{$depositPointKey|escape}][username]"
											{if $depositPoint.type == $smarty.const.SWORD_DEPOSIT_TYPE_OPTIONAL_SELECTION}onfocusout="refreshDepositPoint('{$depositPointKey|escape:"quotes"}');"{/if}
										/>
									</label>
								</div>
							{/if}

							{if empty($depositPoint.password)}
								<div class="section">
									<label>
										<span class="label">{translate key="user.password"}</span>
										<input type="password" id="depositPoint-{$depositPointKey|escape}-password" name="depositPoint[{$depositPointKey|escape}][password]"
											{if $depositPoint.type == $smarty.const.SWORD_DEPOSIT_TYPE_OPTIONAL_SELECTION}onfocusout="refreshDepositPoint('{$depositPointKey|escape:"quotes"}');"{/if}
										 />
									</label>
								</div>
							{/if}
							{if $depositPoint.type == $smarty.const.SWORD_DEPOSIT_TYPE_OPTIONAL_SELECTION}
								<label>
									<span class="label">{translate key="plugins.importexport.sword.depositPoint"}</span>
									<select id="depositPoint-{$depositPointKey|escape}-depositPoint" name="depositPoint[{$depositPointKey|escape}][depositPoint]">
										{foreach from=$depositPoint.depositPoints key=depositPointKey item=depositPointValue}
											<option value="{$depositPointKey|escape}">{$depositPointValue|escape}</option>
										{/foreach}
									</select>
									<button onclick="refreshDepositPoint('{$depositPointKey|escape:"quotes"}'); return false;" class="pkp_button">{translate key="plugins.importexport.sword.reload"}</button>
									<span id="depositPoint-{$depositPointKey|escape}-spinner" class="pkp_spinner" style="display: none;"></span>
								</label>
							{/if}
						</div>
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
						<input type="password" id="authorDepositPassword" name="authorDepositPassword" />
					</label>
				</div>
			</div>
		</fieldset>
	{/if}{* $allowAuthorSpecify *}

	<button id="depositButton" class="pkp_button pkp_button_primary" type="submit">{translate key="plugins.importexport.sword.deposit"}</button>
</form>

{include file="frontend/components/footer.tpl"}
