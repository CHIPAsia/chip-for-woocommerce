/**
 * Logo settings behaviour for the CHIP gateway settings screen.
 *
 * Two jobs, both additive:
 *
 *  1. keep the Display Logo preview in step with the selected option, including
 *     the "Custom" option whose image comes from the merchant's media library;
 *  2. let the Custom Logo Image field pick an image from the WordPress media
 *     library. The stored value is the attachment id and nothing else, so the
 *     logo used at checkout is always an image uploaded to this store.
 *
 * Without this file the field still saves and still renders its stored image;
 * only the picker and the live preview need JavaScript.
 */
(function ($) {
	'use strict';

	var strings = (window.chipLogoAdmin && window.chipLogoAdmin.i18n) || {};

	/**
	 * Refresh the thumbnail and buttons inside one Custom Logo field.
	 *
	 * @param {jQuery} $field The .chip-custom-logo fieldset.
	 */
	function refreshCustomField($field) {
		var hasImage = '' !== String($field.find('.chip-custom-logo__value').val() || '').trim();
		var $img = $field.find('.chip-custom-logo__img');
		var $empty = $field.find('.chip-custom-logo__empty');
		var $remove = $field.find('.chip-custom-logo__remove');

		$img.prop('hidden', !hasImage);
		$empty.prop('hidden', hasImage);
		$remove.prop('hidden', !hasImage);
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

			// The attachment id is what gets saved; the URL only drives the
			// previews, and saving re-resolves it from the media library.
			$field.find('.chip-custom-logo__img').attr('src', url);
			$field.find('.chip-custom-logo__value').val(attachment.id).trigger('change');

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
			var $field = $(this).closest('.chip-custom-logo');
			$field.find('.chip-custom-logo__img').removeAttr('src');
			$field.find('.chip-custom-logo__value').val('').trigger('change');
		});

		$(document).on('change', '.chip-custom-logo__value', function () {
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
			var $customImg = $customInput.closest('tr').find('.chip-custom-logo__img');

			// Check if logo URLs are available for this gateway.
			if ('undefined' === typeof window['chipLogoUrls_' + gatewayId]) {
				return;
			}

			var logoUrls = window['chipLogoUrls_' + gatewayId];

			function updatePreview() {
				var selectedValue = $select.val();
				var url = logoUrls[selectedValue];

				// "Custom" has no bundled file: it shows the image currently in the
				// field, which the picker keeps in the thumbnail. Read the src, not
				// the hidden state: the thumbnail is unhidden by the same change
				// event that runs this, and handler order is not a contract.
				if ('custom' === selectedValue) {
					url = $customImg.length && $customImg.attr('src')
						? $customImg.attr('src')
						: (logoUrls[selectedValue] || '');
				}

				if (url) {
					$previewImg.attr('src', url).show();
				} else {
					$previewImg.removeAttr('src').hide();
				}
			}

			// Update on change.
			$select.on('change', updatePreview);

			// Picking or removing an image changes what the preview shows.
			$customInput.on('change', updatePreview);

			// Initial update.
			updatePreview();
		});
	});
})(jQuery);
