{**
 * templates/swordSettingsTab.tpl
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Deposit articles in remote repositories
 *}

<tab id="swordSettings" label="Sword Settings">
	<div id="swordGridContainer"><div class="pkp_loading">
	<span class="pkp_spinner"></span>
	<span class="message">Loading</span>
	</div>
	 </div>
<script>
// Initialise JS handler.
	$(function() {ldelim}
		$('#swordGridContainer').pkpHandler(
			'$.pkp.controllers.UrlInDivHandler',
			{ldelim}
				sourceUrl: {$sourceUrl|json_encode},
				refreshOn: null
			{rdelim}
		);

		pageUrl = document.location.toString();
		if (pageUrl.match('#')) {ldelim}
			pageAnchor = pageUrl.split('#')[1];
			var $selectedTab = $(".pkpTabs").get(0);
			if ($selectedTab) {ldelim}
				$selectedTab.__vue__.setCurrentTab(pageAnchor);
			{rdelim}
		{rdelim}
	{rdelim});
</script>
</tab>
