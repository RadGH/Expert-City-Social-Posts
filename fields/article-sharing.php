<?php
if( function_exists('acf_add_local_field_group') ):

	acf_add_local_field_group(array (
		'key' => 'group_581c069089f23',
		'title' => 'Article Sharing',
		'fields' => array (
			array (
				'key' => 'field_581c10adffd8b',
				'label' => 'Author Only Setting',
				'name' => '',
				'type' => 'message',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => array (
					array (
						array (
							'field' => 'field_581c06a74bad8',
							'operator' => '==',
							'value' => '1',
						),
						array (
							'field' => 'field_581c06a74bad8',
							'operator' => '!=',
							'value' => '1',
						),
					),
				),
				'wrapper' => array (
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'message' => 'Only the author of the article is allowed to change where this article is shared.',
				'new_lines' => 'wpautop',
				'esc_html' => 0,
			),
			array (
				'key' => 'field_581c12c5fca54',
				'label' => 'Post has been published',
				'name' => '',
				'type' => 'message',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => array (
					array (
						array (
							'field' => 'field_581c06a74bad8',
							'operator' => '==',
							'value' => '1',
						),
						array (
							'field' => 'field_581c06a74bad8',
							'operator' => '!=',
							'value' => '1',
						),
					),
				),
				'wrapper' => array (
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'message' => 'This post has already been published, so you can no longer set it to automatically share.',
				'new_lines' => 'wpautop',
				'esc_html' => 0,
			),
			array (
				'key' => 'field_581c06a74bad8',
				'label' => 'Share when article becomes published',
				'name' => 'ecsp_share_all',
				'type' => 'true_false',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array (
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'message' => 'Share on All Social Networks',
				'default_value' => 0,
			),
			array (
				'key' => 'field_581c06c64bad9',
				'label' => 'Share on Facebook',
				'name' => 'ecsp_share_facebook',
				'type' => 'true_false',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => array (
					array (
						array (
							'field' => 'field_581c06a74bad8',
							'operator' => '!=',
							'value' => '1',
						),
					),
				),
				'wrapper' => array (
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'message' => 'Share on Facebook when the article becomes published',
				'default_value' => 0,
			),
			array (
				'key' => 'field_581c06d94badb',
				'label' => 'Share on Twitter',
				'name' => 'ecsp_share_twitter',
				'type' => 'true_false',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => array (
					array (
						array (
							'field' => 'field_581c06a74bad8',
							'operator' => '!=',
							'value' => '1',
						),
					),
				),
				'wrapper' => array (
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'message' => 'Share on Twitter when the article becomes published',
				'default_value' => 0,
			),
			array (
				'key' => 'field_581c06e24badc',
				'label' => 'Share on LinkedIn',
				'name' => 'ecsp_share_linkedin',
				'type' => 'true_false',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => array (
					array (
						array (
							'field' => 'field_581c06a74bad8',
							'operator' => '!=',
							'value' => '1',
						),
					),
				),
				'wrapper' => array (
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'message' => 'Share on LinkedIn when the article becomes published',
				'default_value' => 0,
			),
			array (
				'key' => 'field_581c06ed4badd',
				'label' => 'Sharing Message',
				'name' => 'ecsp_share_message',
				'type' => 'textarea',
				'instructions' => 'A link to the article will be added to the end of your message automatically, or you can enter %post_url% to insert it manually instead.

Note: Twitter has a maximum length of 140, and the end of your message may be truncated to meet that limit.',
				'required' => 1,
				'conditional_logic' => array (
					array (
						array (
							'field' => 'field_581c06a74bad8',
							'operator' => '==',
							'value' => '1',
						),
					),
					array (
						array (
							'field' => 'field_581c06c64bad9',
							'operator' => '==',
							'value' => '1',
						),
					),
					array (
						array (
							'field' => 'field_581c06d94badb',
							'operator' => '==',
							'value' => '1',
						),
					),
					array (
						array (
							'field' => 'field_581c06e24badc',
							'operator' => '==',
							'value' => '1',
						),
					),
				),
				'wrapper' => array (
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'default_value' => '',
				'placeholder' => '',
				'maxlength' => '',
				'rows' => 4,
				'new_lines' => 'wpautop',
			),
			array (
				'key' => 'field_581c40ba26aba',
				'label' => 'Configure Sharing Accounts',
				'name' => '',
				'type' => 'message',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => array (
					array (
						array (
							'field' => 'field_581c06a74bad8',
							'operator' => '==',
							'value' => '1',
						),
						array (
							'field' => 'field_581c06a74bad8',
							'operator' => '!=',
							'value' => '1',
						),
					),
				),
				'wrapper' => array (
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'message' => '<a href="/wp-admin/profile.php" target="_blank">Connect your social networking accounts within your profile</a> and you can automatically post the article there when the article is published.',
				'new_lines' => 'wpautop',
				'esc_html' => 0,
			),
		),
		'location' => array (
			array (
				array (
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'post',
				),
			),
		),
		'menu_order' => 0,
		'position' => 'normal',
		'style' => 'default',
		'label_placement' => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen' => '',
		'active' => 1,
		'description' => '',
	));

endif;