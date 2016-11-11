jQuery(function() {
	setTimeout(function() {
		init_ecsp_autofill_sharing_message();

		init_ecsp_move_fields_below_acf_form();
	}, 500);
});

function init_ecsp_autofill_sharing_message() {
	var tmce_active_selector = window.ecsp_tmce_active_selector || '#wp-content-wrap'; // Checks if has the class "tmce-active"
	var text_selector = window.ecsp_text_selector || '#content';
	var sharing_message_selector = window.ecsp_sharing_message_selector|| '#acf-field_581c06ed4badd';

	console.log(tmce_active_selector, text_selector);

	var $share_message = jQuery(sharing_message_selector);
	if ( $share_message.length < 1 ) return;

	// If sharing message is not empty, then we don't want to overwrite it.
	if ( $share_message.val().length > 0 ) return;

	var get_tinymce_content = function() {
		if (jQuery(tmce_active_selector).hasClass("tmce-active")) {
			return tinyMCE.activeEditor.getContent();
		}else{
			return jQuery(text_selector).val();
		}
	};

	var autofill_message = function() {
		var message = get_tinymce_content();
		if ( typeof message != "string" ) message = "";

		// Remove HTML, linebreaks, and extra spaces.
		message = message.replace(/<(?:.|\n)*?>/gm, '').replace(/(\r\n|\r|\n)/gm, " ").replace(/[ \t]{2,}/gm, ' ');

		// Limit to 90 characters. Should give room for a full URL. 120 is the real target, for Twitter.
		if ( message.length > 90 ) message = message.substr(0, 90) + "...";

		// Add the %post_url% tag to the end.
		if ( message.indexOf('%post_url%') < 0 ) message += " %post_url%";

		$share_message.val( message );
	};

	// Bind clicking into share message area to autofill
	$share_message.on('click focus', function() {
		if ( $share_message.val().length < 1 ) autofill_message();
	});
}


function init_ecsp_move_fields_below_acf_form() {
	var $form = jQuery('#post').filter('.acf-form');
	if ( $form.length < 1 ) return;

	// ACF Forms, used on the front end, should show our fields at the bottom.
	var $acf_field_container = $form.children('.acf-fields');
	var fields = ".acf-field-581c10adffd8b, .acf-field-581c12c5fca54, .acf-field-581c40ba26aba, " +
				 ".acf-field-581c06a74bad8, .acf-field-581c06c64bad9, .acf-field-581c06d94badb, " +
				 ".acf-field-581c06e24badc, .acf-field-581c06ed4badd";
	var $move_fields = $acf_field_container.find(fields);

	// Move all fields to the end of the field container.
	$acf_field_container.append( $move_fields );

	// Show the title of the first field (eg, "Share on Facebook")
	$move_fields.filter(':visible').first().find('.acf-label').css('display', 'block');
}