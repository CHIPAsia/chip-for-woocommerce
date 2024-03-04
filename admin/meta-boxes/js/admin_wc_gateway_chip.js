/*jslint browser: true, plusplus: true */
(function ($, window, document) {
  'use strict';
  // execute when the DOM is ready
  $(document).ready(function () {
      // js 'click' event triggered on the wporg_field form field
      const gateway_name = 'wc_gateway_chip'
      const obj = wc_gateway_chip_meta_box_obj
      $('#chip-refresh-meta-box-' + gateway_name).on('submit', function (event) {
          event.preventDefault();
          // jQuery post method, a shorthand for $.ajax with POST
          $.post(obj.url,
                 {
                     action: obj.gateway_id + '_metabox_refresh',
                     gateway_id: obj.gateway_id
                 }, function (data) {
                      $('#' + gateway_name + '_balance').text(data.balance);
                      $('#' + gateway_name + '_incoming_count').text(data.incoming_count);
                      $('#' + gateway_name + '_incoming_fee').text(data.incoming_fee);
                      $('#' + gateway_name + '_incoming_turnover').text(data.incoming_turnover);
                      $('#' + gateway_name + '_outgoing_count').text(data.outgoing_count);
                      $('#' + gateway_name + '_outgoing_fee').text(data.outgoing_fee);
                      $('#' + gateway_name + '_outgoing_turnover').text(data.outgoing_turnover);
                  },
                 'json'
          );
      });
  });
}(jQuery, window, document));