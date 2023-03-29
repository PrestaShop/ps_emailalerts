{**
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
 *}
{extends file='customer/page.tpl'}

{block name='page_title'}
  {l s='My alerts' d='Modules.Emailalerts.Shop'}
{/block}

{block name='page_content'}
  {if $mailAlerts}
    <ul>
      {foreach from=$mailAlerts item=mailAlert}
        <li class="p-1 m-1" style="display:flex;align-items:center;background:white">{include 'module:ps_emailalerts/views/templates/front/mailalerts-account-line.tpl' mailAlert=$mailAlert}</li>
      {/foreach}
    </ul>
  {else}
    <div class="alert alert-info" role="alert" data-alert="info">{l s='No mail alerts yet.' d='Modules.Emailalerts.Shop'}</div>
  {/if}
{/block}
