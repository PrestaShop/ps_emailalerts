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

$(document).on('ready', function() {
  let $ma_merchant_order_radio_on = $('#MA_MERCHANT_ORDER_on');
  let $ma_merchant_oos_radio_on = $('#MA_MERCHANT_OOS_on');
  let $ma_merchant_return_slip_radio_on = $('#MA_RETURN_SLIP_on');

  let $order_emails_form_group = $('#MA_MERCHANT_ORDER_EMAILS').parents('.form-group');
  let $oos_emails_form_group = $('#MA_MERCHANT_OOS_EMAILS').parents('.form-group');
  let $return_slip_emails_form_group = $('#MA_RETURN_SLIP_EMAILS').parents('.form-group');

  // Bind change event
  $(document).on('change', '#MA_MERCHANT_ORDER_on, #MA_MERCHANT_ORDER_off', function(){
    $order_emails_form_group.toggle($ma_merchant_order_radio_on.is(':checked') && $ma_merchant_order_radio_on.val() === '1');
  });

  $(document).on('change', '#MA_MERCHANT_OOS_on, #MA_MERCHANT_OOS_off', function(){
    $oos_emails_form_group.toggle($ma_merchant_oos_radio_on.is(':checked') && $ma_merchant_oos_radio_on.val() === '1');
  });

  $(document).on('change', '#MA_RETURN_SLIP_on, #MA_RETURN_SLIP_off', function(){
    $return_slip_emails_form_group.toggle($ma_merchant_return_slip_radio_on.is(':checked') && $ma_merchant_return_slip_radio_on.val() === '1');
  });

  // Check at page load if we need to show or hide the inputs
  $ma_merchant_order_radio_on.trigger('change');
  $ma_merchant_oos_radio_on.trigger('change');
  $ma_merchant_return_slip_radio_on.trigger('change');
});
