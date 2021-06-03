/**
 * 2007-2020 PrestaShop.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2020 PrestaShop SA
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

function  addNotification() {
  var ids = $('div.js-mailalert > input[type=hidden]');

  $.ajax({
    type: 'POST',
    url: $('div.js-mailalert').data('url'),
    data: 'id_product='+ids[0].value+'&id_product_attribute='+ids[1].value+'&customer_email='+$('div.js-mailalert > input[type=email]').val(),
    success: function (resp) {
      resp = JSON.parse(resp);

      const alertProperties = {
        class: resp.error ? 'danger' : 'success',
        data: resp.error ? 'error' : 'success'
      }

      $('div.js-mailalert > span').html('<article class="mt-1 alert alert-' + alertProperties.class + '" role="alert" data-alert="' + alertProperties.data + '">'+resp.message+'</article>').show();
      if (!resp.error) {
        $('div.js-mailalert > button').hide();
        $('div.js-mailalert > input[type=email]').hide();
        $('div.js-mailalert .gdpr_consent_wrapper').hide();
      }
    }
  });
  return false;
}

$(document).on('ready', function() {
  const mailAlertWrapper = $('.js-mailalert');
  const mailAlertSubmitButton = mailAlertWrapper.find('button');

  if (mailAlertWrapper.find('#gdpr_consent').length) {
    setTimeout(() => {
      mailAlertSubmitButton.prop('disabled', true);

      mailAlertWrapper.find('[name="psgdpr_consent_checkbox"]').on('change', function (e) {
        e.stopPropagation();
      
        mailAlertSubmitButton.prop('disabled', !$(this).prop('checked'));
      });
    }, 100);
  }

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
