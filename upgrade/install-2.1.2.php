<?php
/**
 * 2007-2020 PrestaShop SA and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0  Academic Free License (AFL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_'))
	exit;

function upgrade_module_2_1_2($object)
{
	return Db::getInstance()->execute(
		'ALTER TABLE `'._DB_PREFIX_.MailAlert::$definition['table'].'`
		ADD COLUMN `id_emailalert` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
		ADD COLUMN `date_add` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `id_lang`,
		DROP PRIMARY KEY,
		DROP INDEX `id_emailalert`,
		ADD PRIMARY KEY (`id_emailalert`);'
	);
}
