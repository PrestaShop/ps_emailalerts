{*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
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
* @copyright 2007-2015 PrestaShop SA
* @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
* International Registered Trademark & Property of PrestaShop SA
*}

<script type="text/javascript">{literal}
// <![CDATA[
function clearText() {
	if ($('#oos_customer_email').val() == '{/literal}{l s='your@email.com' d='Modules.MailAlerts.Shop'}{literal}')
		$('#oos_customer_email').val('');
}

function oosHookJsCodeMailAlert() {
	$.ajax({
		type: 'POST',
		url: "{/literal}{$link->getModuleLink('mailalerts', 'actions', ['process' => 'check'])}{literal}",
		data: 'id_product={/literal}{$id_product}{literal}&id_product_attribute='+$('#idCombination').val(),
		success: function (msg) {
			if ($.trim(msg) == '0') {
				$('#mailalert_link').show();
				$('#oos_customer_email').show();
			}
			else {
				$('#mailalert_link').hide();
				$('#oos_customer_email').hide();
			}
		}
	});
}

function  addNotification() {
	$.ajax({
		type: 'POST',
		url: "{/literal}{$link->getModuleLink('mailalerts', 'actions', ['process' => 'add'])}{literal}",
		data: 'id_product={/literal}{$id_product}{literal}&id_product_attribute='+$('input#mailalert_combination').val()+'&customer_email='+$('#oos_customer_email').val()+'',
		success: function (msg) {
			if ($.trim(msg) == '1') {
				$('#mailalert_link').hide();
				$('#oos_customer_email').hide();
				$('#oos_customer_email_result').html("{/literal}{l s='Request notification registered' d='Modules.MailAlerts.Shop'}{literal}");
				$('#oos_customer_email_result').css('color', 'green').show();
			}
			else if ($.trim(msg) == '2') {
				$('#oos_customer_email_result').html("{/literal}{l s='You already have an alert for this product' d='Modules.MailAlerts.Shop'}{literal}");
				$('#oos_customer_email_result').css('color', 'red').show();
			} else {
				$('#oos_customer_email_result').html("{/literal}{l s='Your e-mail address is invalid' d='Modules.MailAlerts.Shop'}{literal}");
				$('#oos_customer_email_result').css('color', 'red').show();
			}
		}
	});
	return false;
}

$(document).ready(function() {
	oosHookJsCodeMailAlert();
	$('#oos_customer_email').bind('keypress', function(e) {
		if(e.keyCode == 13)
		{
			addNotification();
			return false;
		}
	});
});
{/literal}
//]]>
</script>

<!-- MODULE MailAlerts -->
<div class="mailalert" style="display: none;">
	{if isset($email) AND $email}
		<input type="text" id="oos_customer_email" name="customer_email" size="20" value="{l s='your@email.com' d='Modules.MailAlerts.Shop'}" class="mailalerts_oos_email" onclick="clearText();" /><br />
	{/if}
  <input type="hidden" id="mailalert_combination"/>
	<a href="#" title="{l s='Notify me when available' d='Modules.MailAlerts.Shop'}" onclick="return addNotification();" id="mailalert_link" rel="nofollow">{l s='Notify me when available' d='Modules.MailAlerts.Shop'}</a>
	<span id="oos_customer_email_result" style="display:none;"></span>
</div>
<!-- END : MODULE MailAlerts -->
