/**
 * 2007-2022 PrestaShop.
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
 * @copyright 2007-2022 PrestaShop SA
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
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
