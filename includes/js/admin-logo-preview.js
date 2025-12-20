(function($) {
	'use strict';

	$(document).ready(function() {
		// Find all CHIP gateway logo selects.
		$('select[id*="_display_logo"]').each(function() {
			var $select = $(this);
			var selectId = $select.attr('id');
			
			// Extract gateway ID from select ID (e.g., woocommerce_chip_woocommerce_gateway_display_logo).
			var match = selectId.match(/woocommerce_(.+)_display_logo/);
			if (!match) {
				return;
			}
			
			var gatewayId = match[1];
			var previewImgId = 'chip-logo-preview-img-' + gatewayId;
			var $previewImg = $('#' + previewImgId);
			
			// Check if logo URLs are available for this gateway.
			if ('undefined' === typeof window['chipLogoUrls_' + gatewayId]) {
				return;
			}
			
			var logoUrls = window['chipLogoUrls_' + gatewayId];
			
			function updatePreview() {
				var selectedValue = $select.val();
				if (logoUrls[selectedValue]) {
					$previewImg.attr('src', logoUrls[selectedValue]).show();
				} else {
					$previewImg.hide();
				}
			}
			
			// Update on change.
			$select.on('change', updatePreview);
			
			// Initial update.
			updatePreview();
		});
	});
})(jQuery);

