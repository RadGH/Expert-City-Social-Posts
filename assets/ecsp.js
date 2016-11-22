jQuery(function() {
	setTimeout(function() {
		var $eca = jQuery('#eca-submit-article-form');
		if ( $eca.length < 1 ) return;

		init_ecsp_autofill_sharing_message( $eca );

		init_ecsp_move_fields_below_acf_form( $eca );

		init_ecsp_refresh_social_fields( $eca );
	}, 500);
});

function init_ecsp_refresh_social_fields( $eca ) {
	var $fields = {
		cb_all:             $eca.find( 'div.acf-field.acf-field-581c06a74bad8' ),
		cb_facebook:        $eca.find( 'div.acf-field.acf-field-581c06c64bad9' ),
		cb_twitter:         $eca.find( 'div.acf-field.acf-field-581c06d94badb' ),
		cb_linkedin:        $eca.find( 'div.acf-field.acf-field-581c06e24badc' ),
		sharing_message:    $eca.find( 'div.acf-field.acf-field-581c06ed4badd' ),
		connect_accounts:   $eca.find( 'div.acf-field.acf-field-581c40ba26aba' )
	};

	var networks_connected = {
		facebook: -1,
		twitter: -1,
		linkedin: -1
	};

	var ajaxQuery = null;

	// Toggles visibility of fields based on network availability.
	var networksChanged = function() {
		var show_facebook = networks_connected.facebook;
		var show_twitter = networks_connected.twitter;
		var show_linkedin = networks_connected.linkedin;

		var show_all = atLeastTwo( show_facebook, show_twitter, show_linkedin ); // Two or more networks are enabled
		var show_message = show_facebook || show_twitter || show_linkedin; // Any network is connected
		var show_connect = !(show_facebook && show_twitter && show_linkedin); // Fewer than all accounts are connected

		// The message field should only show if one of the checkboxes are ticked though.
		if ( show_message ) {
			var any_checkboxes = false;
			if ( $fields.cb_all.find('input:checkbox:checked:visible').length ) any_checkboxes = true;
			if ( $fields.cb_facebook.find('input:checkbox:checked:visible').length ) any_checkboxes = true;
			if ( $fields.cb_twitter.find('input:checkbox:checked:visible').length ) any_checkboxes = true;
			if ( $fields.cb_linkedin.find('input:checkbox:checked:visible').length ) any_checkboxes = true;

			if ( !any_checkboxes ) show_message = false;
		}

		var states = {
			cb_all:           show_all,
			cb_facebook:      show_facebook,
			cb_twitter:       show_twitter,
			cb_linkedin:      show_linkedin,
			sharing_message:  show_message,
			connect_accounts: show_connect
		};

		for( var key in states ) {
			if ( !states.hasOwnProperty(key) ) continue;

			var new_state = states[key];
			var current_state = $fields[key].data('ecsp-view-state');

			if ( new_state == current_state ) continue;
			$fields[key].data('ecsp-view-state', new_state );

			if ( new_state ) {
				acf.conditional_logic.show_field( $fields[key] );
			}else{
				acf.conditional_logic.hide_field( $fields[key] );
			}
		}
	};

	// Returns true if two or more values are true.
	var atLeastTwo = function(a,b,c) { return a ? (b || c) : (b && c); };

	// Queries the server for sharing connections. Calls networksChanged() if they change.
	var updateSharingOptions = function() {
		if ( ajaxQuery !== null ) ajaxQuery.abort();

		ajaxQuery = jQuery.ajax({
			url: '',
			data: { "ecsp-ajax": "update-acf-form" },
			dataType: "json",
			complete: function( d ) {
				ajaxQuery = null;
				if ( typeof d.responseJSON == "undefined" ) return;

				var facebook = (d.responseJSON.facebook === 1);
				var twitter = (d.responseJSON.twitter === 1);
				var linkedin = (d.responseJSON.linkedin === 1);

				var changes = 0; // Increment when value changes.

				if ( facebook !== networks_connected.facebook ) {
					changes++;
					networks_connected.facebook = facebook;
				}

				if ( twitter !== networks_connected.twitter ) {
					changes++;
					networks_connected.twitter = twitter;
				}

				if ( linkedin !== networks_connected.linkedin ) {
					changes++;
					networks_connected.linkedin = linkedin;
				}

				if ( changes ) networksChanged();
			}
		});
	};

	// Poll every 10 seconds for updates, in case window.onfocus doesn't work.
	setTimeout( updateSharingOptions, 10000 );

	// Poll when tab is opened
	jQuery(window).on('focus', updateSharingOptions);

	// Poll immediately, to get the initial values
	updateSharingOptions();
}

function init_ecsp_autofill_sharing_message( $eca ) {
	var tmce_active_selector = window.ecsp_tmce_active_selector || '#wp-content-wrap'; // Checks if has the class "tmce-active"
	var text_selector = window.ecsp_text_selector || '#content';
	var sharing_message_selector = window.ecsp_sharing_message_selector|| '#acf-field_581c06ed4badd';

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


function init_ecsp_move_fields_below_acf_form( $eca ) {
	var $form = $eca.find('.acf-form');
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