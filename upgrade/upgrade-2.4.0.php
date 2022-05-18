<?php
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
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_4_0($object)
{
    $result = true;

    // migrate the saved e-mails to all new fields (if active)
    $merchants_emails = Configuration::get('MA_MERCHANT_MAILS');
    if (!empty($merchants_emails)) {
        // create an array from saved e-mails
        $merchants_emails = explode("\n", Configuration::get('MA_MERCHANT_MAILS'));
        // recreate string in the new format
        $merchants_emails = implode(',', $merchants_emails);

        // save e-mails to each new Configuration (if active)
        if (Configuration::get('MA_MERCHANT_ORDER')) {
            $result &= Configuration::updateValue('MA_MERCHANT_ORDER_EMAILS', $merchants_emails);
        }
        if (Configuration::get('MA_MERCHANT_OOS')) {
            $result &= Configuration::updateValue('MA_MERCHANT_OOS_EMAILS', $merchants_emails);
        }
        if (Configuration::get('MA_RETURN_SLIP')) {
            $result &= Configuration::updateValue('MA_RETURN_SLIP_EMAILS', $merchants_emails);
        }
    }

    $result &= (bool) $object->registerHook('actionAdminControllerSetMedia');

    return (bool) $result;
}
