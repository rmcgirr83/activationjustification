/**
*
* @package Activation Justification
* @copyright (c) 2021 RMcGirr83
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

(function($) { // Avoid conflicts with other libraries
	'use strict';

    $("textarea[maxlength]").bind('input propertychange', function() {
        var maxLength = $(this).attr('maxlength');
		var charLimit = 255;

        var newlines = ($(this).val().match(/\n/g) || []).length
        if ($(this).val().length + newlines > maxLength) {
            $(this).val($(this).val().substring(0, maxLength - newlines));
        }
		else {
			$("#countdown").text(charLimit - $(this).val().length + newlines);
		}
    });
})(jQuery); // Avoid conflicts with other libraries
