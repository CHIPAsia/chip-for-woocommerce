/**
 * Logo settings behaviour for the CHIP gateway settings screen.
 *
 * Two jobs, both additive:
 *
 *  1. keep the Display Logo preview in step with the selected option, including
 *     the "Custom" option whose image URL lives in its own field;
 *  2. let the Custom Logo Image field pick an image from the WordPress media
 *     library instead of pasting a URL by hand.
 *
 * The screen works without this file: the field is a plain text input holding a
 * URL, so a merchant who never touches the picker still gets the right logo.
 */
(function ($) {
	'use strict';

	var strings = (window.chipLogoAdmin && window.chipLogoAdmin.i18n) || {};

	/**
	 * Refresh the thumbnail inside one Custom Logo field.
	 *
	 * @param {jQuery} $field The .chip-custom-logo fieldset.
	 */
	function refreshCustomField($field) {
		var url = $.trim(String($field.find('.chip-custom-logo__value').val() || ''));
		var $img = $field.find('.chip-custom-logo__img');
		var $empty = $field.find('.chip-custom-logo__empty');
		var $remove = $field.find('.chip-custom-logo__remove');

		if (url) {
			$img.attr('src', url).prop('hidden', false);
			$empty.prop('hidden', true);
			$remove.prop('hidden', false);
		} else {
			$img.attr('src', '').prop('hidden', true);
			$empty.prop('hidden', false);
			$remove.prop('hidden', true);
		}
	}

	/**
	 * Open the media library for one Custom Logo field.
	 *
	 * @param {jQuery} $field The .chip-custom-logo fieldset.
	 */
	function openPicker($field) {
		if ('undefined' === typeof wp || !wp.media) {
			return;
		}

		var frame = wp.media({
			title: strings.title || 'Choose a logo image',
			button: { text: strings.button || 'Use this image' },
			library: { type: 'image' },
			multiple: false
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			var url = attachment.sizes && attachment.sizes.full ? attachment.sizes.full.url : attachment.url;

			// Triggering 'change' lets the Display Logo preview pick the value up
			// through its own handler instead of duplicating the lookup here.
			$field.find('.chip-custom-logo__value').val(url).trigger('change');

			// The frame is built directly with wp.media(), whose "Use this image"
			// button only fires this event — closing is the consumer's job.
			frame.close();
		});

		frame.open();
	}

	$(document).ready(function () {
		// Media library picker and Remove button.
		$(document).on('click', '.chip-custom-logo__upload', function (event) {
			event.preventDefault();
			openPicker($(this).closest('.chip-custom-logo'));
		});

		$(document).on('click', '.chip-custom-logo__remove', function (event) {
			event.preventDefault();
			$(this).closest('.chip-custom-logo').find('.chip-custom-logo__value').val('').trigger('change');
		});

		$(document).on('input change', '.chip-custom-logo__value', function () {
			refreshCustomField($(this).closest('.chip-custom-logo'));
		});

		// Find all CHIP gateway logo selects.
		$('select[id*="_display_logo"]').each(function () {
			var $select = $(this);
			var selectId = $select.attr('id');

			// Extract gateway ID from select ID (e.g., woocommerce_wc_gateway_chip_display_logo).
			var match = selectId.match(/woocommerce_(.+)_display_logo/);
			if (!match) {
				return;
			}

			var gatewayId = match[1];
			var previewImgId = 'chip-logo-preview-img-' + gatewayId;
			var $previewImg = $('#' + previewImgId);
			var $customInput = $('#woocommerce_' + gatewayId + '_custom_logo');

			// Check if logo URLs are available for this gateway.
			if ('undefined' === typeof window['chipLogoUrls_' + gatewayId]) {
				return;
			}

			var logoUrls = window['chipLogoUrls_' + gatewayId];

			function updatePreview() {
				var selectedValue = $select.val();
				var url = logoUrls[selectedValue];

				// "Custom" has no bundled file: its image is whatever the merchant
				// uploaded (or pasted) into the field below.
				if ('custom' === selectedValue) {
					url = $.trim(String($customInput.val() || ''));
				}

				if (url) {
					$previewImg.attr('src', url).show();
				} else {
					$previewImg.removeAttr('src').hide();
				}
			}

			// Update on change.
			$select.on('change', updatePreview);

			// A pasted, typed or picked custom URL changes what the preview shows.
			$customInput.on('input change', updatePreview);

			// Initial update.
			updatePreview();
		});
	});
})(jQuery);
