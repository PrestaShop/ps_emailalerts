/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

function  addNotification(productId, productAttributeId) {
  // to keep backward compatibility
  if (typeof productId === 'undefined') {
    var ids = $('div.js-mailalert > input[type=hidden]');
    productId = ids.eq(0).val();
    productIdAttribute = ids.eq(1).val();
  }

  $.ajax({
    type: 'POST',
    url: $('div.js-mailalert').data('url'),
    data: 'id_product='+productId+'&id_product_attribute='+productAttributeId+'&customer_email='+$('div.js-mailalert > input[type=email]').val(),
    success: function (resp) {
      resp = JSON.parse(resp);

      $('.js-mailalert-alerts').html('<article class="mt-1 alert alert-' + (resp.error ? 'danger' : 'success') + '" role="alert" data-alert="' + (resp.error ? 'error' : 'success') + '">'+ resp.message +'</article>').show();
      if (!resp.error) {
        $('div.js-mailalert > .js-mailalert-add, div.js-mailalert > input[type=email], div.js-mailalert .gdpr_consent_wrapper').hide();
      }
    }
  });
  return false;
}

$(document).ready(function() {
  const mailAlertSubmitButtonClass = '.js-mailalert-add';
  const mailAlertWrapper = $('.js-mailalert');
  const mailAlertSubmitButton = mailAlertWrapper.find(mailAlertSubmitButtonClass);

  if (mailAlertWrapper.find('#gdpr_consent, .gdpr_consent').length) {
    // We use a timeout to put this at the end of the callstack, so it's executed after GPDR module. 
    setTimeout(() => {
      mailAlertSubmitButton.prop('disabled', true);

      mailAlertWrapper.find('[name="psgdpr_consent_checkbox"]').on('change', function (e) {
        e.stopPropagation();
      
        mailAlertSubmitButton.prop('disabled', !$(this).prop('checked'));
      });
    }, 0);
  }

  $(document).on('click', mailAlertSubmitButtonClass, function (e)
  {
    e.preventDefault();

    addNotification($(this).data('product'), $(this).data('product-attribute'));
  });

  $(document).on('click', '.js-remove-email-alert', function()
  {
    var self = $(this);
    var ids = self.attr('rel').replace('js-id-emailalerts-', '');
    ids = ids.split('-');
    var id_product_mail_alert = ids[0];
    var id_product_attribute_mail_alert = ids[1];
    var parent = self.closest('li');

    $.ajax({
      url: self.data('url'),
      type: "POST",
      data: {
        'id_product': id_product_mail_alert,
        'id_product_attribute': id_product_attribute_mail_alert
      },
      success: function(result)
      {
        if (result == '0')
        {
          parent.fadeOut("normal", function()
          {
            parent.remove();
          });
        }
      }
    });
  });
});
