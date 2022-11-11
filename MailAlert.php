<?php
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
class MailAlert extends ObjectModel
{
    public $id_customer;

    public $customer_email;

    public $id_product;

    public $id_product_attribute;

    public $id_shop;

    public $id_lang;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table' => 'mailalert_customer_oos',
        'primary' => 'id_customer',
        'fields' => [
            'id_customer' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'customer_email' => ['type' => self::TYPE_STRING, 'validate' => 'isEmail', 'required' => true],
            'id_product' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'id_product_attribute' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'id_shop' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
            'id_lang' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true],
        ],
    ];

    public static function customerHasNotification($id_customer, $id_product, $id_product_attribute, $id_shop = null, $id_lang = null, $guest_email = '')
    {
        if ($id_shop == null) {
            $id_shop = Context::getContext()->shop->id;
        }

        if ($id_lang == null) {
            $id_lang = Context::getContext()->language->id;
        }

        $customer = new Customer($id_customer);
        $customer_email = $customer->email;
        $guest_email = pSQL($guest_email);

        $id_customer = (int) $id_customer;
        $customer_email = pSQL($customer_email);
        $where = $id_customer == 0 ? "customer_email = '$guest_email'" : "(id_customer=$id_customer OR customer_email='$customer_email')";
        $sql = '
			SELECT *
			FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`
			WHERE ' . $where . '
			AND `id_product` = ' . (int) $id_product . '
			AND `id_product_attribute` = ' . (int) $id_product_attribute . '
			AND `id_shop` = ' . (int) $id_shop;

        return count(Db::getInstance((bool) _PS_USE_SQL_SLAVE_)->executeS($sql));
    }

    public static function deleteAlert($id_customer, $customer_email, $id_product, $id_product_attribute, $id_shop = null)
    {
        $sql = '
			DELETE FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`
			WHERE ' . (($id_customer > 0) ? '(`customer_email` = \'' . pSQL($customer_email) . '\' OR `id_customer` = ' . (int) $id_customer . ')' :
                '`customer_email` = \'' . pSQL($customer_email) . '\'') .
            ' AND `id_product` = ' . (int) $id_product . '
			AND `id_product_attribute` = ' . (int) $id_product_attribute . '
			AND `id_shop` = ' . ($id_shop != null ? (int) $id_shop : (int) Context::getContext()->shop->id);

        return Db::getInstance()->execute($sql);
    }

    /*
     * Get objects that will be viewed on "My alerts" page
     */
    public static function getMailAlerts($id_customer, $id_lang, Shop $shop = null)
    {
        if (!Validate::isUnsignedId($id_customer) || !Validate::isUnsignedId($id_lang)) {
            exit(Tools::displayError());
        }

        if (!$shop) {
            $shop = Context::getContext()->shop;
        }

        $customer = new Customer($id_customer);
        $products = self::getProducts($customer, $id_lang);
        $products_number = count($products);

        if (empty($products) === true || !$products_number) {
            return [];
        }

        for ($i = 0; $i < $products_number; ++$i) {
            $obj = new Product((int) $products[$i]['id_product'], false, (int) $id_lang);
            if (!Validate::isLoadedObject($obj)) {
                continue;
            }

            if (isset($products[$i]['id_product_attribute']) &&
                Validate::isUnsignedInt($products[$i]['id_product_attribute'])) {
                $attributes = self::getProductAttributeCombination($products[$i]['id_product_attribute'], $id_lang);
                $products[$i]['attributes_small'] = '';

                if ($attributes) {
                    foreach ($attributes as $row) {
                        $products[$i]['attributes_small'] .= $row['attribute_name'] . ', ';
                    }
                }

                $products[$i]['attributes_small'] = rtrim($products[$i]['attributes_small'], ', ');
                $products[$i]['id_shop'] = $shop->id;

                /* Get cover */
                $attrgrps = $obj->getAttributesGroups((int) $id_lang);
                foreach ($attrgrps as $attrgrp) {
                    if ($attrgrp['id_product_attribute'] == (int) $products[$i]['id_product_attribute']
                        && $images = Product::_getAttributeImageAssociations((int) $attrgrp['id_product_attribute'])) {
                        $products[$i]['cover'] = $obj->id . '-' . array_pop($images);
                        break;
                    }
                }
            }

            if (!isset($products[$i]['cover']) || !$products[$i]['cover']) {
                $images = $obj->getImages((int) $id_lang);
                foreach ($images as $image) {
                    if ($image['cover']) {
                        $products[$i]['cover'] = $obj->id . '-' . $image['id_image'];
                        break;
                    }
                }
            }

            if (!isset($products[$i]['cover'])) {
                $products[$i]['cover'] = Language::getIsoById($id_lang) . '-default';
            }
            $products[$i]['link'] = $obj->getLink();
            $context = Context::getContext();
            $products[$i]['cover_url'] = $context->link->getImageLink($obj->link_rewrite, $products[$i]['cover'], 'small_default');
        }

        return $products;
    }

    public static function sendCustomerAlert($id_product, $id_product_attribute)
    {
        $link = new Link();
        $context = Context::getContext();
        $id_product = (int) $id_product;
        $id_product_attribute = (int) $id_product_attribute;
        $current_shop = $context->shop->id;
        $customers = self::getCustomers($id_product, $id_product_attribute, $current_shop);

        foreach ($customers as $customer) {
            $id_shop = (int) $customer['id_shop'];
            $id_lang = (int) $customer['id_lang'];
            $context->shop->id = $id_shop;

            $product = new Product($id_product, false, $id_lang, $id_shop);
            $product_name = Product::getProductName($product->id, $id_product_attribute, $id_lang);
            $product_link = $link->getProductLink($product, $product->link_rewrite, null, null, $id_lang, $id_shop, $id_product_attribute);
            $template_vars = [
                '{product}' => $product_name,
                '{product_link}' => $product_link,
            ];

            if ($customer['id_customer']) {
                $customer = new Customer((int) $customer['id_customer']);
                $customer_email = (string) $customer->email;
                $customer_id = (int) $customer->id;
            } else {
                $customer_id = 0;
                $customer_email = (string) $customer['customer_email'];
            }

            $iso = Language::getIsoById($id_lang);
            $locale = Language::getLocaleByIso($iso);

            $translator = Context::getContext()->getTranslatorFromLocale($locale);

            if (file_exists(dirname(__FILE__) . '/mails/' . $iso . '/customer_qty.txt') &&
                file_exists(dirname(__FILE__) . '/mails/' . $iso . '/customer_qty.html')) {
                try {
                    Mail::Send(
                        $id_lang,
                        'customer_qty',
                        $translator->trans('Product available', [], 'Emails.Subject', $locale),
                        $template_vars,
                        $customer_email,
                        null,
                        (string) Configuration::get('PS_SHOP_EMAIL', null, null, $id_shop),
                        (string) Configuration::get('PS_SHOP_NAME', null, null, $id_shop),
                        null,
                        null,
                        dirname(__FILE__) . '/mails/',
                        false,
                        $id_shop
                    );
                } catch (Exception $e) {
                    /*
                     * Something went wrong but don't care we need to continue.
                     * This can be caused by an invalid e-mail address.
                     */
                    PrestaShopLogger::addLog(
                        sprintf(
                            'Mailalert error: Could not send email to address [%s] because %s',
                            $customer_email,
                            $e->getMessage()
                        ),
                        3 // It means error
                    );
                }
            }

            Hook::exec(
                'actionModuleMailAlertSendCustomer',
                [
                    'product' => $product_name,
                    'link' => $product_link,
                    'customer' => $customer,
                    'product_obj' => $product,
                ]
            );

            self::deleteAlert(
                $customer_id,
                $customer_email,
                $id_product,
                $id_product_attribute,
                $id_shop
            );
        }
        $context->shop->id = $current_shop;
    }

    /*
     * Generate correctly the address for an email
     */
    public static function getFormatedAddress(Address $address, $line_sep, $fields_style = [])
    {
        return AddressFormat::generateAddress($address, ['avoid' => []], $line_sep, ' ', $fields_style);
    }

    /*
     * Get products according to alerts
     */
    public static function getProducts($customer, $id_lang)
    {
        $list_shop_ids = Shop::getContextListShopID(false);

        $sql = '
			SELECT ma.`id_product`, p.`quantity` AS product_quantity, pl.`name`, ma.`id_product_attribute`
			FROM `' . _DB_PREFIX_ . self::$definition['table'] . '` ma
			JOIN `' . _DB_PREFIX_ . 'product` p ON (p.`id_product` = ma.`id_product`)
			' . Shop::addSqlAssociation('product', 'p') . '
			LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (pl.`id_product` = p.`id_product` AND pl.id_shop IN (' . implode(', ', $list_shop_ids) . '))
			WHERE product_shop.`active` = 1
			AND (ma.`id_customer` = ' . (int) $customer->id . ' OR ma.`customer_email` = \'' . pSQL($customer->email) . '\')
			AND pl.`id_lang` = ' . (int) $id_lang . Shop::addSqlRestriction(false, 'ma');

        return Db::getInstance((bool) _PS_USE_SQL_SLAVE_)->executeS($sql);
    }

    /*
     * Get product combinations
     */
    public static function getProductAttributeCombination($id_product_attribute, $id_lang)
    {
        $sql = '
			SELECT al.`name` AS attribute_name
			FROM `' . _DB_PREFIX_ . 'product_attribute_combination` pac
			LEFT JOIN `' . _DB_PREFIX_ . 'attribute` a ON (a.`id_attribute` = pac.`id_attribute`)
			LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group` ag ON (ag.`id_attribute_group` = a.`id_attribute_group`)
			LEFT JOIN `' . _DB_PREFIX_ . 'attribute_lang` al ON (a.`id_attribute` = al.`id_attribute` AND al.`id_lang` = ' . (int) $id_lang . ')
			LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group_lang` agl ON (ag.`id_attribute_group` = agl.`id_attribute_group` AND agl.`id_lang` = ' . (int) $id_lang . ')
			LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON (pac.`id_product_attribute` = pa.`id_product_attribute`)
			' . Shop::addSqlAssociation('product_attribute', 'pa') . '
			WHERE pac.`id_product_attribute` = ' . (int) $id_product_attribute;

        return Db::getInstance((bool) _PS_USE_SQL_SLAVE_)->executeS($sql);
    }

    /*
     * Get customers waiting for alert on the specified product/product attribute
     * in shop `$id_shop` and if the shop group shares the stock in all shops of the shop group
     */
    public static function getCustomers($id_product, $id_product_attribute, $id_shop)
    {
        $sql = '
			SELECT mc.id_customer, mc.customer_email, mc.id_shop, mc.id_lang
			FROM `' . _DB_PREFIX_ . self::$definition['table'] . '` mc
			INNER JOIN `' . _DB_PREFIX_ . 'shop` s on s.id_shop = mc.id_shop
			INNER JOIN `' . _DB_PREFIX_ . 'shop_group` sg on s.id_shop_group = sg.id_shop_group and (s.id_shop = ' . (int) $id_shop . ' or sg.share_stock = 1)
			INNER JOIN `' . _DB_PREFIX_ . 'shop` s2 on s2.id_shop = mc.id_shop and s2.id_shop = ' . (int) $id_shop . '
			WHERE mc.`id_product` = ' . (int) $id_product . ' AND mc.`id_product_attribute` = ' . (int) $id_product_attribute;

        return Db::getInstance((bool) _PS_USE_SQL_SLAVE_)->executeS($sql);
    }
}
