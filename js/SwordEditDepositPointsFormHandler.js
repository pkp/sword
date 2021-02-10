/**
 * @file js/SwordEditDepositPointsFormHandler.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class SwordEditDepositPointsFormHandler
 * @brief SWORD plugin deposit points form handler.
 */
(function($) {
	/** @type {Object} */
	$.pkp.plugins.sword =
			$.pkp.plugins.sword ||
			{ js: { } };

	function showFieldError($input) {
		label = $($.parseHTML("<label for='"+$input[0].id+"'><span class='error'>"+$input.data('error')+"</span></label>"));
		$input.after(label[0]);
	}

	function invalidFormState($usernameInput, $passwordInput, $apikeyInput) {
		if($usernameInput[0].value.length && $passwordInput[0].value.length) {
			return false;
		} else if (Math.max($usernameInput[0].value.length, $passwordInput[0].value.length)) {
			return true;
		} else if ($apikeyInput[0].value.length) {
			return false;
		}
		return true;
	}

	/**
	 * @constructor
	 *
	 * @param {jQueryObject} $form the wrapped HTML form element.
	 */
	$.pkp.plugins.sword.js.SwordEditDepositPointsFormHandler =
			function($form) {
		$swordUsername = $form.find( "input[name='swordUsername'] ");
		$swordPassword = $form.find( "input[name='swordPassword'] ");
		$swordApikey = $form.find( "input[name='swordApikey'] ");

		$.each([$swordUsername, $swordPassword, $swordApikey], function(i, $input) {
			$input.change(function(e) {
				$this = $(this);
				$.each([$swordUsername, $swordPassword, $swordApikey], function(i, $input) {
					$.each($("label[for='"+$input[0].id+"']").slice(1), function(i, labelDOM) {
						labelDOM.remove();
					});
				});

				if (invalidFormState($swordUsername, $swordPassword, $swordApikey)) {
					$.each([$swordUsername, $swordPassword, $swordApikey], function(i, $input) {
						if (!$input[0].value.length) {
							showFieldError($input);
						}
					});
					$('button.submitFormButton').attr('disabled', true);
				} else {
					$('button.submitFormButton').removeAttr('disabled');
				}
			});
		});
	}
}(jQuery));
