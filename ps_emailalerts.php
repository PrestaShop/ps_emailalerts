<?php
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
if (!defined('_CAN_LOAD_FILES_')) {
    exit;
}

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

include_once dirname(__FILE__) . '/MailAlert.php';

class Ps_EmailAlerts extends Module
{
    /** @var string Page name */
    public $page_name;

    /**
     * @var string Name of the module running on PS 1.6.x. Used for data migration.
     */
    const PS_16_EQUIVALENT_MODULE = 'mailalerts';

    protected $html = '';

    protected $merchant_mails;
    protected $merchant_order;
    protected $merchant_oos;
    protected $customer_qty;
    protected $merchant_coverage;
    protected $product_coverage;
    protected $order_edited;
    protected $return_slip;

    const __MA_MAIL_DELIMITOR__ = "\n";

    public function __construct()
    {
        $this->name = 'ps_emailalerts';
        $this->tab = 'administration';
        $this->version = '2.3.3';
        $this->author = 'PrestaShop';
        $this->need_instance = 0;

        $this->controllers = ['account'];

        $this->bootstrap = true;
        parent::__construct();

        if ($this->id) {
            $this->init();
        }

        $this->displayName = $this->trans('Mail alerts', [], 'Modules.Emailalerts.Admin');
        $this->description = $this->trans('Make your everyday life easier, handle mail alerts about stock and orders, addressed to you as well as your customers.', [], 'Modules.Emailalerts.Admin');
        $this->ps_versions_compliancy = [
            'min' => '1.7.6.0',
            'max' => _PS_VERSION_,
        ];
    }

    protected function init()
    {
        $this->merchant_mails = str_replace(',', self::__MA_MAIL_DELIMITOR__, (string) Configuration::get('MA_MERCHANT_MAILS'));
        $this->merchant_order = (int) Configuration::get('MA_MERCHANT_ORDER');
        $this->merchant_oos = (int) Configuration::get('MA_MERCHANT_OOS');
        $this->customer_qty = (int) Configuration::get('MA_CUSTOMER_QTY');
        $this->merchant_coverage = (int) Configuration::getGlobalValue('MA_MERCHANT_COVERAGE');
        $this->product_coverage = (int) Configuration::getGlobalValue('MA_PRODUCT_COVERAGE');
        $this->order_edited = (int) Configuration::getGlobalValue('MA_ORDER_EDIT');
        $this->return_slip = (int) Configuration::getGlobalValue('MA_RETURN_SLIP');
    }

    public function install($delete_params = true)
    {
        if (!parent::install() ||
            !$this->registerHook('actionValidateOrder') ||
            !$this->registerHook('actionUpdateQuantity') ||
            !$this->registerHook('displayProductButtons') ||
            !$this->registerHook('displayCustomerAccount') ||
            !$this->registerHook('displayMyAccountBlock') ||
            !$this->registerHook('actionProductDelete') ||
            !$this->registerHook('actionProductAttributeDelete') ||
            !$this->registerHook('actionProductAttributeUpdate') ||
            !$this->registerHook('actionProductCoverage') ||
            !$this->registerHook('actionOrderReturn') ||
            !$this->registerHook('actionOrderEdited') ||
            !$this->registerHook('actionDeleteGDPRCustomer') ||
            !$this->registerHook('actionExportGDPRData') ||
            !$this->registerHook('displayProductAdditionalInfo') ||
            !$this->registerHook('actionFrontControllerSetMedia')) {
            return false;
        }

        if ($delete_params && $this->uninstallPrestaShop16Module()) {
            Configuration::updateValue('MA_MERCHANT_ORDER', 1);
            Configuration::updateValue('MA_MERCHANT_OOS', 1);
            Configuration::updateValue('MA_CUSTOMER_QTY', 1);
            Configuration::updateValue('MA_ORDER_EDIT', 1);
            Configuration::updateValue('MA_RETURN_SLIP', 1);
            Configuration::updateValue('MA_MERCHANT_MAILS', Configuration::get('PS_SHOP_EMAIL'));
            Configuration::updateValue('MA_LAST_QTIES', (int) Configuration::get('PS_LAST_QTIES'));
            Configuration::updateGlobalValue('MA_MERCHANT_COVERAGE', 0);
            Configuration::updateGlobalValue('MA_PRODUCT_COVERAGE', 0);

            $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . MailAlert::$definition['table'] . '`
				(
					`id_customer` int(10) unsigned NOT NULL,
					`customer_email` varchar(128) NOT NULL,
					`id_product` int(10) unsigned NOT NULL,
					`id_product_attribute` int(10) unsigned NOT NULL,
					`id_shop` int(10) unsigned NOT NULL,
					`id_lang` int(10) unsigned NOT NULL,
					PRIMARY KEY  (`id_customer`,`customer_email`,`id_product`,`id_product_attribute`,`id_shop`)
				) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci';

            if (!Db::getInstance()->execute($sql)) {
                return false;
            }
        }

        return true;
    }

    public function uninstall($delete_params = true)
    {
        if ($delete_params) {
            Configuration::deleteByName('MA_MERCHANT_ORDER');
            Configuration::deleteByName('MA_MERCHANT_OOS');
            Configuration::deleteByName('MA_CUSTOMER_QTY');
            Configuration::deleteByName('MA_MERCHANT_MAILS');
            Configuration::deleteByName('MA_LAST_QTIES');
            Configuration::deleteByName('MA_MERCHANT_COVERAGE');
            Configuration::deleteByName('MA_PRODUCT_COVERAGE');
            Configuration::deleteByName('MA_ORDER_EDIT');
            Configuration::deleteByName('MA_RETURN_SLIP');

            if (!Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . MailAlert::$definition['table'])) {
                return false;
            }
        }

        return parent::uninstall();
    }

    /**
     * Migrate data from 1.6 equivalent module (if applicable), then uninstall
     */
    public function uninstallPrestaShop16Module()
    {
        if (!Module::isInstalled(self::PS_16_EQUIVALENT_MODULE)) {
            return true;
        }
        $oldModule = Module::getInstanceByName(self::PS_16_EQUIVALENT_MODULE);
        if ($oldModule) {
            // This closure calls the parent class to prevent data to be erased
            // It allows the new module to be configured without migration
            $parentUninstallClosure = function () {
                return parent::uninstall();
            };
            $parentUninstallClosure = $parentUninstallClosure->bindTo($oldModule, get_class($oldModule));
            $parentUninstallClosure();
        }

        return true;
    }

    public function reset()
    {
        if (!$this->uninstall(false)) {
            return false;
        }
        if (!$this->install(false)) {
            return false;
        }

        return true;
    }

    public function getContent()
    {
        $this->html = '';

        $this->postProcess();

        $this->html .= $this->renderForm();

        return $this->html;
    }

    protected function postProcess()
    {
        $errors = [];

        if (Tools::isSubmit('submitMailAlert')) {
            if (!Configuration::updateValue('MA_CUSTOMER_QTY', (int) Tools::getValue('MA_CUSTOMER_QTY'))) {
                $errors[] = $this->trans('Cannot update settings', [], 'Modules.Emailalerts.Admin');
            } elseif (!Configuration::updateGlobalValue('MA_ORDER_EDIT', (int) Tools::getValue('MA_ORDER_EDIT'))) {
                $errors[] = $this->trans('Cannot update settings', [], 'Modules.Emailalerts.Admin');
            }
        } elseif (Tools::isSubmit('submitMAMerchant')) {
            $emails = (string) Tools::getValue('MA_MERCHANT_MAILS');

            if (!$emails || empty($emails)) {
                $errors[] = $this->trans('Please type one (or more) email address', [], 'Modules.Emailalerts.Admin');
            } else {
                $emails = str_replace(',', self::__MA_MAIL_DELIMITOR__, $emails);
                $emails = explode(self::__MA_MAIL_DELIMITOR__, $emails);
                foreach ($emails as $k => $email) {
                    $email = trim($email);
                    if (!empty($email) && !Validate::isEmail($email)) {
                        $errors[] = $this->trans('Invalid email:', [], 'Modules.Emailalerts.Admin') . ' ' . Tools::safeOutput($email);
                        break;
                    } elseif (!empty($email)) {
                        $emails[$k] = $email;
                    } else {
                        unset($emails[$k]);
                    }
                }

                $emails = implode(self::__MA_MAIL_DELIMITOR__, $emails);

                if (!Configuration::updateValue('MA_MERCHANT_MAILS', (string) $emails)) {
                    $errors[] = $this->trans('Cannot update settings', [], 'Modules.Emailalerts.Admin');
                } elseif (!Configuration::updateValue('MA_MERCHANT_ORDER', (int) Tools::getValue('MA_MERCHANT_ORDER'))) {
                    $errors[] = $this->trans('Cannot update settings', [], 'Modules.Emailalerts.Admin');
                } elseif (!Configuration::updateValue('MA_MERCHANT_OOS', (int) Tools::getValue('MA_MERCHANT_OOS'))) {
                    $errors[] = $this->trans('Cannot update settings', [], 'Modules.Emailalerts.Admin');
                } elseif (!Configuration::updateValue('MA_LAST_QTIES', (int) Tools::getValue('MA_LAST_QTIES'))) {
                    $errors[] = $this->trans('Cannot update settings', [], 'Modules.Emailalerts.Admin');
                } elseif (!Configuration::updateGlobalValue('MA_MERCHANT_COVERAGE', (int) Tools::getValue('MA_MERCHANT_COVERAGE'))) {
                    $errors[] = $this->trans('Cannot update settings', [], 'Modules.Emailalerts.Admin');
                } elseif (!Configuration::updateGlobalValue('MA_PRODUCT_COVERAGE', (int) Tools::getValue('MA_PRODUCT_COVERAGE'))) {
                    $errors[] = $this->trans('Cannot update settings', [], 'Modules.Emailalerts.Admin');
                } elseif (!Configuration::updateGlobalValue('MA_RETURN_SLIP', (int) Tools::getValue('MA_RETURN_SLIP'))) {
                    $errors[] = $this->trans('Cannot update settings', [], 'Modules.Emailalerts.Admin');
                }
            }
        }

        if (count($errors) > 0) {
            $this->html .= $this->displayError(implode('<br />', $errors));
        } elseif (Tools::isSubmit('submitMailAlert') || Tools::isSubmit('submitMAMerchant')) {
            $this->html .= $this->displayConfirmation($this->trans('Settings updated successfully', [], 'Modules.Emailalerts.Admin'));
        }

        $this->init();
    }

    public function getAllMessages($id)
    {
        $messages = Db::getInstance()->executeS('
			SELECT `message`
			FROM `' . _DB_PREFIX_ . 'message`
			WHERE `id_order` = ' . (int) $id . '
			ORDER BY `id_message` ASC');
        $result = [];
        foreach ($messages as $message) {
            $result[] = $message['message'];
        }

        return implode('<br/>', $result);
    }

    /**
     * Return current locale
     *
     * @param Context $context
     *
     * @return \PrestaShop\PrestaShop\Core\Localization\Locale|null
     *
     * @throws Exception
     */
    public static function getContextLocale(Context $context)
    {
        $locale = $context->getCurrentLocale();
        if (null !== $locale) {
            return $locale;
        }

        $containerFinder = new \PrestaShop\PrestaShop\Adapter\ContainerFinder($context);
        $container = $containerFinder->getContainer();
        if (null === $context->container) {
            // @phpstan-ignore-next-line
            $context->container = $container;
        }

        /** @var \PrestaShop\PrestaShop\Core\Localization\CLDR\LocaleRepository $localeRepository */
        $localeRepository = $container->get(Controller::SERVICE_LOCALE_REPOSITORY);
        $locale = $localeRepository->getLocale(
            $context->language->getLocale()
        );

        // @phpstan-ignore-next-line
        return $locale;
    }

    public function hookActionValidateOrder($params)
    {
        if (!$this->merchant_order || empty($this->merchant_mails)) {
            return;
        }

        // Getting differents vars
        $context = Context::getContext();
        $id_lang = (int) $context->language->id;
        $locale = $context->language->getLocale();
        // We use use static method from current class to prevent retro compatibility issues with PrestaShop < 1.7.7
        $contextLocale = static::getContextLocale($context);

        $id_shop = (int) $context->shop->id;
        $currency = $params['currency'];
        $order = $params['order'];
        $customer = $params['customer'];
        $configuration = Configuration::getMultiple(
            [
                'PS_SHOP_EMAIL',
                'PS_MAIL_METHOD',
                'PS_MAIL_SERVER',
                'PS_MAIL_USER',
                'PS_MAIL_PASSWD',
                'PS_SHOP_NAME',
                'PS_MAIL_COLOR',
            ], $id_lang, null, $id_shop
        );
        $delivery = new Address((int) $order->id_address_delivery);
        $invoice = new Address((int) $order->id_address_invoice);
        $order_date_text = Tools::displayDate($order->date_add);
        $carrier = new Carrier((int) $order->id_carrier);
        $message = $this->getAllMessages($order->id);

        if (!$message || empty($message)) {
            $message = $this->trans('No message', [], 'Modules.Emailalerts.Admin');
        }

        $items_table = '';

        $products = $params['order']->getProducts();
        $customized_datas = Product::getAllCustomizedDatas((int) $params['cart']->id);
        Product::addCustomizationPrice($products, $customized_datas);
        foreach ($products as $key => $product) {
            $unit_price = Product::getTaxCalculationMethod($customer->id) == PS_TAX_EXC ? $product['product_price'] : $product['product_price_wt'];

            $customization_text = '';
            if (isset($customized_datas[$product['product_id']][$product['product_attribute_id']][$order->id_address_delivery][$product['id_customization']])) {
                foreach ($customized_datas[$product['product_id']][$product['product_attribute_id']][$order->id_address_delivery][$product['id_customization']] as $customization) {
                    if (isset($customization[Product::CUSTOMIZE_TEXTFIELD])) {
                        foreach ($customization[Product::CUSTOMIZE_TEXTFIELD] as $text) {
                            $customization_text .= $text['name'] . ': ' . $text['value'] . '<br />';
                        }
                    }

                    if (isset($customization[Product::CUSTOMIZE_FILE])) {
                        $customization_text .= count($customization[Product::CUSTOMIZE_FILE]) . ' ' . $this->trans('image(s)', [], 'Modules.Emailalerts.Admin') . '<br />';
                    }

                    $customization_text .= '---<br />';
                }
                if (method_exists('Tools', 'rtrimString')) {
                    $customization_text = Tools::rtrimString($customization_text, '---<br />');
                } else {
                    $customization_text = preg_replace('/---<br \/>$/', '', $customization_text);
                }
            }

            $url = $context->link->getProductLink($product['product_id']);
            $items_table .=
                '<tr style="background-color:' . ($key % 2 ? '#DDE2E6' : '#EBECEE') . ';">
					<td style="padding:0.6em 0.4em;">' . $product['product_reference'] . '</td>
					<td style="padding:0.6em 0.4em;">
						<strong><a href="' . $url . '">' . $product['product_name'] . '</a>'
                            . (isset($product['attributes_small']) ? ' ' . $product['attributes_small'] : '')
                            . (!empty($customization_text) ? '<br />' . $customization_text : '')
                        . '</strong>
					</td>
					<td style="padding:0.6em 0.4em; text-align:right;">' . $contextLocale->formatPrice($unit_price, $currency->iso_code) . '</td>
					<td style="padding:0.6em 0.4em; text-align:center;">' . (int) $product['product_quantity'] . '</td>
					<td style="padding:0.6em 0.4em; text-align:right;">'
                        . $contextLocale->formatPrice(($unit_price * $product['product_quantity']), $currency->iso_code)
                    . '</td>
				</tr>';
        }
        foreach ($params['order']->getCartRules() as $discount) {
            $items_table .=
                '<tr style="background-color:#EBECEE;">
						<td colspan="4" style="padding:0.6em 0.4em; text-align:right;">' . $this->trans('Voucher code:', [], 'Modules.Emailalerts.Admin') . ' ' . $discount['name'] . '</td>
					<td style="padding:0.6em 0.4em; text-align:right;">-' . $contextLocale->formatPrice($discount['value'], $currency->iso_code) . '</td>
			</tr>';
        }
        if ($delivery->id_state) {
            $delivery_state = new State((int) $delivery->id_state);
        }
        if ($invoice->id_state) {
            $invoice_state = new State((int) $invoice->id_state);
        }

        if (Product::getTaxCalculationMethod($customer->id) == PS_TAX_EXC) {
            $total_products = $order->getTotalProductsWithoutTaxes();
        } else {
            $total_products = $order->getTotalProductsWithTaxes();
        }

        $order_state = $params['orderStatus'];

        // Filling-in vars for email
        $template_vars = [
            '{firstname}' => $customer->firstname,
            '{lastname}' => $customer->lastname,
            '{email}' => $customer->email,
            '{delivery_block_txt}' => MailAlert::getFormatedAddress($delivery, "\n"),
            '{invoice_block_txt}' => MailAlert::getFormatedAddress($invoice, "\n"),
            '{delivery_block_html}' => MailAlert::getFormatedAddress(
                $delivery, '<br />', [
                    'firstname' => '<span style="color:' . $configuration['PS_MAIL_COLOR'] . '; font-weight:bold;">%s</span>',
                    'lastname' => '<span style="color:' . $configuration['PS_MAIL_COLOR'] . '; font-weight:bold;">%s</span>',
                ]
            ),
            '{invoice_block_html}' => MailAlert::getFormatedAddress(
                $invoice, '<br />', [
                    'firstname' => '<span style="color:' . $configuration['PS_MAIL_COLOR'] . '; font-weight:bold;">%s</span>',
                    'lastname' => '<span style="color:' . $configuration['PS_MAIL_COLOR'] . '; font-weight:bold;">%s</span>',
                ]
            ),
            '{delivery_company}' => $delivery->company,
            '{delivery_firstname}' => $delivery->firstname,
            '{delivery_lastname}' => $delivery->lastname,
            '{delivery_address1}' => $delivery->address1,
            '{delivery_address2}' => $delivery->address2,
            '{delivery_city}' => $delivery->city,
            '{delivery_postal_code}' => $delivery->postcode,
            '{delivery_country}' => $delivery->country,
            '{delivery_state}' => isset($delivery_state->name) ? $delivery_state->name : '',
            '{delivery_phone}' => $delivery->phone ? $delivery->phone : $delivery->phone_mobile,
            '{delivery_other}' => $delivery->other,
            '{invoice_company}' => $invoice->company,
            '{invoice_firstname}' => $invoice->firstname,
            '{invoice_lastname}' => $invoice->lastname,
            '{invoice_address2}' => $invoice->address2,
            '{invoice_address1}' => $invoice->address1,
            '{invoice_city}' => $invoice->city,
            '{invoice_postal_code}' => $invoice->postcode,
            '{invoice_country}' => $invoice->country,
            '{invoice_state}' => isset($invoice_state->name) ? $invoice_state->name : '',
            '{invoice_phone}' => $invoice->phone ? $invoice->phone : $invoice->phone_mobile,
            '{invoice_other}' => $invoice->other,
            '{order_name}' => $order->reference,
            '{order_status}' => $order_state->name,
            '{shop_name}' => $configuration['PS_SHOP_NAME'],
            '{date}' => $order_date_text,
            '{carrier}' => (($carrier->name == '0') ? $configuration['PS_SHOP_NAME'] : $carrier->name),
            '{payment}' => Tools::substr($order->payment, 0, 32),
            '{items}' => $items_table,
            '{total_paid}' => $contextLocale->formatPrice($order->total_paid, $currency->iso_code),
            '{total_products}' => $contextLocale->formatPrice($total_products, $currency->iso_code),
            '{total_discounts}' => $contextLocale->formatPrice($order->total_discounts, $currency->iso_code),
            '{total_shipping}' => $contextLocale->formatPrice($order->total_shipping, $currency->iso_code),
            '{total_shipping_tax_excl}' => $contextLocale->formatPrice($order->total_shipping_tax_excl, $currency->iso_code),
            '{total_shipping_tax_incl}' => $contextLocale->formatPrice($order->total_shipping_tax_incl, $currency->iso_code),
            '{total_tax_paid}' => $contextLocale->formatPrice(
                $order->total_paid_tax_incl - $order->total_paid_tax_excl,
                $currency->iso_code
            ),
            '{total_wrapping}' => $contextLocale->formatPrice($order->total_wrapping, $currency->iso_code),
            '{currency}' => $currency->sign,
            '{gift}' => (bool) $order->gift,
            '{gift_message}' => $order->gift_message,
            '{message}' => $message,
        ];

        // Shop iso
        $iso = Language::getIsoById((int) Configuration::get('PS_LANG_DEFAULT'));

        // Send 1 email by merchant mail, because Mail::Send doesn't work with an array of recipients
        $merchant_mails = explode(self::__MA_MAIL_DELIMITOR__, $this->merchant_mails);
        foreach ($merchant_mails as $merchant_mail) {
            // Default language
            $mail_id_lang = $id_lang;
            $mail_iso = $iso;

            // Use the merchant lang if he exists as an employee
            $results = Db::getInstance()->executeS('
				SELECT `id_lang` FROM `' . _DB_PREFIX_ . 'employee`
				WHERE `email` = \'' . pSQL($merchant_mail) . '\'
			');
            if ($results) {
                $user_iso = Language::getIsoById((int) $results[0]['id_lang']);
                if ($user_iso) {
                    $mail_id_lang = (int) $results[0]['id_lang'];
                    $mail_iso = $user_iso;
                }
            }

            $dir_mail = false;
            if (file_exists(dirname(__FILE__) . '/mails/' . $mail_iso . '/new_order.txt') &&
                file_exists(dirname(__FILE__) . '/mails/' . $mail_iso . '/new_order.html')) {
                $dir_mail = dirname(__FILE__) . '/mails/';
            }

            if (file_exists(_PS_MAIL_DIR_ . $mail_iso . '/new_order.txt') &&
                file_exists(_PS_MAIL_DIR_ . $mail_iso . '/new_order.html')) {
                $dir_mail = _PS_MAIL_DIR_;
            }

            if ($dir_mail) {
                Mail::send(
                    $mail_id_lang,
                    'new_order',
                    $this->trans(
                        'New order : #%d - %s',
                        [
                            $order->id,
                            $order->reference,
                        ],
                        'Emails.Subject',
                        $locale),
                    $template_vars,
                    $merchant_mail,
                    null,
                    $configuration['PS_SHOP_EMAIL'],
                    $configuration['PS_SHOP_NAME'],
                    null,
                    null,
                    $dir_mail,
                    false,
                    $id_shop
                );
            }
        }
    }

    public function hookDisplayProductAdditionalInfo($params)
    {
        if ($params['product']['minimal_quantity'] <= $params['product']['quantity'] ||
            !$this->customer_qty ||
            !Configuration::get('PS_STOCK_MANAGEMENT') ||
            Product::isAvailableWhenOutOfStock($params['product']['out_of_stock'])) {
            return;
        }
        $context = Context::getContext();
        $id_product = (int) $params['product']['id'];
        $id_product_attribute = $params['product']['id_product_attribute'];
        $id_customer = (int) $context->customer->id;
        if ((int) $context->customer->id <= 0) {
            $this->context->smarty->assign('email', 1);
        } elseif (MailAlert::customerHasNotification($id_customer, $id_product, $id_product_attribute, (int) $context->shop->id)) {
            return;
        }
        $this->context->smarty->assign(
            [
                'id_product' => $id_product,
                'id_product_attribute' => $id_product_attribute,
                'id_module' => $this->id,
            ]
        );

        return $this->display(__FILE__, 'product.tpl');
    }

    public function hookActionUpdateQuantity($params)
    {
        $id_product = (int) $params['id_product'];
        $id_product_attribute = (int) $params['id_product_attribute'];

        $context = Context::getContext();
        $id_shop = (int) $context->shop->id;
        $id_lang = (int) $context->language->id;
        $locale = $context->language->getLocale();
        $product = new Product($id_product, false, $id_lang, $id_shop, $context);

        if (!Validate::isLoadedObject($product) || $product->active != 1) {
            return;
        }

        $quantity = (int) $params['quantity'];
        $product_has_attributes = $product->hasAttributes();
        $configuration = Configuration::getMultiple(
            [
                'MA_LAST_QTIES',
                'PS_STOCK_MANAGEMENT',
                'PS_SHOP_EMAIL',
                'PS_SHOP_NAME',
            ], null, null, $id_shop
        );
        $ma_last_qties = (int) $configuration['MA_LAST_QTIES'];
        $check_oos = ($product_has_attributes && $id_product_attribute) || (!$product_has_attributes && !$id_product_attribute);

        if ($check_oos &&
            (int) $quantity <= $ma_last_qties &&
            !(!$this->merchant_oos || empty($this->merchant_mails)) &&
            $configuration['PS_STOCK_MANAGEMENT']) {
            $iso = Language::getIsoById($id_lang);
            $product_name = Product::getProductName($id_product, $id_product_attribute, $id_lang);
            $template_vars = [
                '{qty}' => $quantity,
                '{last_qty}' => $ma_last_qties,
                '{product}' => $product_name,
            ];

            // Do not send mail if multiples product are created / imported.
            if (!defined('PS_MASS_PRODUCT_CREATION') &&
                file_exists(dirname(__FILE__) . '/mails/' . $iso . '/productoutofstock.txt') &&
                file_exists(dirname(__FILE__) . '/mails/' . $iso . '/productoutofstock.html')) {
                // Send 1 email by merchant mail, because Mail::Send doesn't work with an array of recipients
                $merchant_mails = explode(self::__MA_MAIL_DELIMITOR__, $this->merchant_mails);
                foreach ($merchant_mails as $merchant_mail) {
                    Mail::Send(
                        $id_lang,
                        'productoutofstock',
                        $this->trans('Product out of stock', [], 'Emails.Subject', $locale),
                        $template_vars,
                        $merchant_mail,
                        null,
                        (string) $configuration['PS_SHOP_EMAIL'],
                        (string) $configuration['PS_SHOP_NAME'],
                        null,
                        null,
                        dirname(__FILE__) . '/mails/',
                        false,
                        $id_shop
                    );
                }
            }
        }

        if ($product_has_attributes) {
            $sql = 'SELECT `minimal_quantity`, `id_product_attribute`
                FROM ' . _DB_PREFIX_ . 'product_attribute
                WHERE id_product_attribute = ' . (int) $id_product_attribute;

            $result = Db::getInstance()->getRow($sql);

            if ($result && $this->customer_qty && $quantity >= $result['minimal_quantity']) {
                MailAlert::sendCustomerAlert((int) $product->id, (int) $params['id_product_attribute']);
            }
        } else {
            if ($this->customer_qty && $quantity >= $product->minimal_quantity) {
                MailAlert::sendCustomerAlert((int) $product->id, (int) $params['id_product_attribute']);
            }
        }
    }

    public function hookActionProductAttributeUpdate($params)
    {
        $sql = 'SELECT sa.`id_product`, sa.`quantity`, pa.`minimal_quantity`
            FROM `' . _DB_PREFIX_ . 'stock_available` sa
            LEFT JOIN ' . _DB_PREFIX_ . 'product_attribute pa ON sa.id_product_attribute = pa.id_product_attribute
            WHERE sa.`id_product_attribute` = ' . (int) $params['id_product_attribute'];

        $result = Db::getInstance()->getRow($sql);

        if ($result && $this->customer_qty && $result['quantity'] >= $result['minimal_quantity']) {
            MailAlert::sendCustomerAlert((int) $result['id_product'], (int) $params['id_product_attribute']);
        }
    }

    public function hookDisplayCustomerAccount($params)
    {
        return $this->customer_qty ? $this->display(__FILE__, 'my-account.tpl') : null;
    }

    public function hookDisplayMyAccountBlock($params)
    {
        return $this->customer_qty ? $this->display(__FILE__, 'my-account-footer.tpl') : null;
    }

    public function hookActionProductDelete($params)
    {
        $sql = '
			DELETE FROM `' . _DB_PREFIX_ . MailAlert::$definition['table'] . '`
			WHERE `id_product` = ' . (int) $params['product']->id;

        Db::getInstance()->execute($sql);
    }

    public function hookActionProductAttributeDelete($params)
    {
        if ($params['deleteAllAttributes']) {
            $sql = '
				DELETE FROM `' . _DB_PREFIX_ . MailAlert::$definition['table'] . '`
				WHERE `id_product` = ' . (int) $params['id_product'];
        } else {
            $sql = '
				DELETE FROM `' . _DB_PREFIX_ . MailAlert::$definition['table'] . '`
				WHERE `id_product_attribute` = ' . (int) $params['id_product_attribute'] . '
				AND `id_product` = ' . (int) $params['id_product'];
        }

        Db::getInstance()->execute($sql);
    }

    public function hookActionProductCoverage($params)
    {
        // if not advanced stock management, nothing to do
        if (!Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')) {
            return;
        }

        // retrieves informations
        $id_product = (int) $params['id_product'];
        $id_product_attribute = (int) $params['id_product_attribute'];
        $warehouse = $params['warehouse'];
        $product = new Product($id_product);

        if (!Validate::isLoadedObject($product)) {
            return;
        }

        if (!$product->advanced_stock_management) {
            return;
        }

        // sets warehouse id to get the coverage
        if (!Validate::isLoadedObject($warehouse)) {
            $id_warehouse = 0;
        } else {
            $id_warehouse = (int) $warehouse->id;
        }

        // coverage of the product
        $warning_coverage = (int) Configuration::getGlobalValue('MA_PRODUCT_COVERAGE');

        $coverage = StockManagerFactory::getManager()->getProductCoverage($id_product, $id_product_attribute, $warning_coverage, $id_warehouse);

        // if we need to send a notification
        if ($product->active == 1 &&
            ($coverage < $warning_coverage) && !empty($this->merchant_mails) &&
            Configuration::getGlobalValue('MA_MERCHANT_COVERAGE')) {
            $context = Context::getContext();
            $id_lang = (int) $context->language->id;
            $locale = $context->language->getLocale();
            $id_shop = (int) $context->shop->id;
            $iso = Language::getIsoById($id_lang);
            $product_name = Product::getProductName($id_product, $id_product_attribute, $id_lang);
            $template_vars = [
                '{current_coverage}' => $coverage,
                '{warning_coverage}' => $warning_coverage,
                '{product}' => pSQL($product_name),
            ];

            if (file_exists(dirname(__FILE__) . '/mails/' . $iso . '/productcoverage.txt') &&
                file_exists(dirname(__FILE__) . '/mails/' . $iso . '/productcoverage.html')) {
                // Send 1 email by merchant mail, because Mail::Send doesn't work with an array of recipients
                $merchant_mails = explode(self::__MA_MAIL_DELIMITOR__, $this->merchant_mails);
                foreach ($merchant_mails as $merchant_mail) {
                    Mail::send(
                        $id_lang,
                        'productcoverage',
                        $this->trans('Stock coverage', [], 'Emails.Subject', $locale),
                        $template_vars,
                        $merchant_mail,
                        null,
                        (string) Configuration::get('PS_SHOP_EMAIL'),
                        (string) Configuration::get('PS_SHOP_NAME'),
                        null,
                        null,
                        dirname(__FILE__) . '/mails/',
                        false,
                        $id_shop
                    );
                }
            }
        }
    }

    public function hookActionFrontControllerSetMedia()
    {
        $this->context->controller->registerJavascript(
            'mailalerts-js',
            'modules/' . $this->name . '/js/mailalerts.js'
        );
        $this->context->controller->registerStylesheet(
            'mailalerts-css',
            'modules/' . $this->name . '/css/mailalerts.css'
        );
    }

    /**
     * Send a mail when a customer return an order.
     *
     * @param array $params Hook params
     */
    public function hookActionOrderReturn($params)
    {
        if (!$this->return_slip || empty($this->return_slip)) {
            return;
        }

        $context = Context::getContext();
        $id_lang = (int) $context->language->id;
        $locale = $context->language->getLocale();
        $id_shop = (int) $context->shop->id;
        $configuration = Configuration::getMultiple(
            [
                'PS_SHOP_EMAIL',
                'PS_MAIL_METHOD',
                'PS_MAIL_SERVER',
                'PS_MAIL_USER',
                'PS_MAIL_PASSWD',
                'PS_SHOP_NAME',
                'PS_MAIL_COLOR',
            ], $id_lang, null, $id_shop
        );

        // Shop iso
        $iso = Language::getIsoById((int) Configuration::get('PS_LANG_DEFAULT'));

        $order = new Order((int) $params['orderReturn']->id_order);
        $customer = new Customer((int) $params['orderReturn']->id_customer);
        $delivery = new Address((int) $order->id_address_delivery);
        $invoice = new Address((int) $order->id_address_invoice);
        $order_date_text = Tools::displayDate($order->date_add);
        if ($delivery->id_state) {
            $delivery_state = new State((int) $delivery->id_state);
        }
        if ($invoice->id_state) {
            $invoice_state = new State((int) $invoice->id_state);
        }

        $order_return_products = OrderReturn::getOrdersReturnProducts($params['orderReturn']->id, $order);

        $items_table = '';
        foreach ($order_return_products as $key => $product) {
            $url = $context->link->getProductLink($product['product_id']);
            $items_table .=
                '<tr style="background-color:' . ($key % 2 ? '#DDE2E6' : '#EBECEE') . ';">
					<td style="padding:0.6em 0.4em;">' . $product['product_reference'] . '</td>
					<td style="padding:0.6em 0.4em;">
						<strong><a href="' . $url . '">' . $product['product_name'] . '</a>
					</strong>
					</td>
					<td style="padding:0.6em 0.4em; text-align:center;">' . (int) $product['product_quantity'] . '</td>
				</tr>';
        }

        $template_vars = [
            '{firstname}' => $customer->firstname,
            '{lastname}' => $customer->lastname,
            '{email}' => $customer->email,
            '{delivery_block_txt}' => MailAlert::getFormatedAddress($delivery, "\n"),
            '{invoice_block_txt}' => MailAlert::getFormatedAddress($invoice, "\n"),
            '{delivery_block_html}' => MailAlert::getFormatedAddress(
                $delivery, '<br />', [
                    'firstname' => '<span style="color:' . $configuration['PS_MAIL_COLOR'] . '; font-weight:bold;">%s</span>',
                    'lastname' => '<span style="color:' . $configuration['PS_MAIL_COLOR'] . '; font-weight:bold;">%s</span>',
                ]
            ),
            '{invoice_block_html}' => MailAlert::getFormatedAddress(
                $invoice, '<br />', [
                    'firstname' => '<span style="color:' . $configuration['PS_MAIL_COLOR'] . '; font-weight:bold;">%s</span>',
                    'lastname' => '<span style="color:' . $configuration['PS_MAIL_COLOR'] . '; font-weight:bold;">%s</span>',
                ]
            ),
            '{delivery_company}' => $delivery->company,
            '{delivery_firstname}' => $delivery->firstname,
            '{delivery_lastname}' => $delivery->lastname,
            '{delivery_address1}' => $delivery->address1,
            '{delivery_address2}' => $delivery->address2,
            '{delivery_city}' => $delivery->city,
            '{delivery_postal_code}' => $delivery->postcode,
            '{delivery_country}' => $delivery->country,
            '{delivery_state}' => isset($delivery_state->name) ? $delivery_state->name : '',
            '{delivery_phone}' => isset($delivery->phone) ? $delivery->phone : $delivery->phone_mobile,
            '{delivery_other}' => $delivery->other,
            '{invoice_company}' => $invoice->company,
            '{invoice_firstname}' => $invoice->firstname,
            '{invoice_lastname}' => $invoice->lastname,
            '{invoice_address2}' => $invoice->address2,
            '{invoice_address1}' => $invoice->address1,
            '{invoice_city}' => $invoice->city,
            '{invoice_postal_code}' => $invoice->postcode,
            '{invoice_country}' => $invoice->country,
            '{invoice_state}' => isset($invoice_state->name) ? $invoice_state->name : '',
            '{invoice_phone}' => isset($invoice->phone) ? $invoice->phone : $invoice->phone_mobile,
            '{invoice_other}' => $invoice->other,
            '{order_name}' => $order->reference,
            '{shop_name}' => $configuration['PS_SHOP_NAME'],
            '{date}' => $order_date_text,
            '{items}' => $items_table,
            '{message}' => Tools::purifyHTML($params['orderReturn']->question),
        ];

        // Send 1 email by merchant mail, because Mail::Send doesn't work with an array of recipients
        $merchant_mails = explode(self::__MA_MAIL_DELIMITOR__, $this->merchant_mails);
        foreach ($merchant_mails as $merchant_mail) {
            // Default language
            $mail_id_lang = $id_lang;
            $mail_iso = $iso;
            $mail_locale = $locale;

            // Use the merchant lang if he exists as an employee
            $results = Db::getInstance()->executeS('
				SELECT `id_lang` FROM `' . _DB_PREFIX_ . 'employee`
				WHERE `email` = \'' . pSQL($merchant_mail) . '\'
			');
            if ($results) {
                $user_iso = Language::getIsoById((int) $results[0]['id_lang']);
                if ($user_iso) {
                    $mail_id_lang = (int) $results[0]['id_lang'];
                    $mail_iso = $user_iso;
                    $mail_locale = Language::getLocaleByIso($user_iso);
                }
            }

            $dir_mail = false;
            if (file_exists(dirname(__FILE__) . '/mails/' . $mail_iso . '/return_slip.txt') &&
                file_exists(dirname(__FILE__) . '/mails/' . $mail_iso . '/return_slip.html')) {
                $dir_mail = dirname(__FILE__) . '/mails/';
            }

            if (file_exists(_PS_MAIL_DIR_ . $mail_iso . '/return_slip.txt') &&
                file_exists(_PS_MAIL_DIR_ . $mail_iso . '/return_slip.html')) {
                $dir_mail = _PS_MAIL_DIR_;
            }

            if ($dir_mail) {
                Mail::send(
                    $mail_id_lang,
                    'return_slip',
                    $this->trans(
                        'New return from order #%d - %s',
                        [
                            $order->id,
                            $order->reference,
                        ],
                        'Emails.Subject',
                        $mail_locale
                    ),
                    $template_vars,
                    $merchant_mail,
                    null,
                    $configuration['PS_SHOP_EMAIL'],
                    $configuration['PS_SHOP_NAME'],
                    null,
                    null,
                    $dir_mail,
                    false,
                    $id_shop
                );
            }
        }
    }

    /**
     * Send a mail when an order is modified.
     *
     * @param array $params Hook params
     */
    public function hookActionOrderEdited($params)
    {
        if (!$this->order_edited || empty($this->order_edited)) {
            return;
        }

        $order = $params['order'];
        $id_lang = (int) $order->id_lang;
        $lang = new Language($id_lang);
        if (Validate::isLoadedObject($lang)) {
            $locale = $lang->getLocale();
        } else {
            $locale = $this->context->language->getLocale();
        }

        $data = [
            '{lastname}' => $order->getCustomer()->lastname,
            '{firstname}' => $order->getCustomer()->firstname,
            '{id_order}' => (int) $order->id,
            '{order_name}' => $order->getUniqReference(),
        ];

        Mail::Send(
            (int) $order->id_lang,
            'order_changed',
            $this->trans('Your order has been changed', [], 'Emails.Subject', $locale),
            $data,
            $order->getCustomer()->email,
            $order->getCustomer()->firstname . ' ' . $order->getCustomer()->lastname,
            null, null, null, null, _PS_MAIL_DIR_, true, (int) $order->id_shop);
    }

    public function renderForm()
    {
        $fields_form_1 = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Customer notifications', [], 'Modules.Emailalerts.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'is_bool' => true, //retro compat 1.5
                        'label' => $this->trans('Product availability', [], 'Modules.Emailalerts.Admin'),
                        'name' => 'MA_CUSTOMER_QTY',
                        'desc' => $this->trans('Give the customer the option of receiving a notification when an out of stock product is available again.', [], 'Modules.Emailalerts.Admin'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Yes', [], 'Admin.Global'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('No', [], 'Admin.Global'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'is_bool' => true, //retro compat 1.5
                        'label' => $this->trans('Order edit', [], 'Modules.Emailalerts.Admin'),
                        'name' => 'MA_ORDER_EDIT',
                        'desc' => $this->trans('Send a notification to the customer when an order is edited.', [], 'Modules.Emailalerts.Admin'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Yes', [], 'Admin.Global'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('No', [], 'Admin.Global'),
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                    'class' => 'btn btn-default pull-right',
                    'name' => 'submitMailAlert',
                ],
            ],
        ];

        $inputs = [
            [
                'type' => 'switch',
                'is_bool' => true, //retro compat 1.5
                'label' => $this->trans('New order', [], 'Modules.Emailalerts.Admin'),
                'name' => 'MA_MERCHANT_ORDER',
                'desc' => $this->trans('Receive a notification when an order is placed.', [], 'Modules.Emailalerts.Admin'),
                'values' => [
                    [
                        'id' => 'active_on',
                        'value' => 1,
                        'label' => $this->trans('Yes', [], 'Admin.Global'),
                    ],
                    [
                        'id' => 'active_off',
                        'value' => 0,
                        'label' => $this->trans('No', [], 'Admin.Global'),
                    ],
                ],
            ],
            [
                'type' => 'switch',
                'is_bool' => true, //retro compat 1.5
                'label' => $this->trans('Out of stock', [], 'Modules.Emailalerts.Admin'),
                'name' => 'MA_MERCHANT_OOS',
                'desc' => $this->trans('Receive a notification if the available quantity of a product is below the following threshold.', [], 'Modules.Emailalerts.Admin'),
                'values' => [
                    [
                        'id' => 'active_on',
                        'value' => 1,
                        'label' => $this->trans('Yes', [], 'Admin.Global'),
                    ],
                    [
                        'id' => 'active_off',
                        'value' => 0,
                        'label' => $this->trans('No', [], 'Admin.Global'),
                    ],
                ],
            ],
            [
                'type' => 'text',
                'label' => $this->trans('Threshold', [], 'Modules.Emailalerts.Admin'),
                'name' => 'MA_LAST_QTIES',
                'class' => 'fixed-width-xs',
                'desc' => $this->trans('Quantity for which a product is considered out of stock.', [], 'Modules.Emailalerts.Admin'),
            ],
        ];

        if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')) {
            $inputs[] = [
                'type' => 'switch',
                'is_bool' => true, //retro compat 1.5
                'label' => $this->trans('Coverage warning', [], 'Modules.Emailalerts.Admin'),
                'name' => 'MA_MERCHANT_COVERAGE',
                'desc' => $this->trans('Receive a notification when a product has insufficient coverage.', [], 'Modules.Emailalerts.Admin'),
                'values' => [
                    [
                        'id' => 'active_on',
                        'value' => 1,
                        'label' => $this->trans('Yes', [], 'Admin.Global'),
                    ],
                    [
                        'id' => 'active_off',
                        'value' => 0,
                        'label' => $this->trans('No', [], 'Admin.Global'),
                    ],
                ],
            ];
            $inputs[] = [
                'type' => 'text',
                'label' => $this->trans('Coverage', [], 'Modules.Emailalerts.Admin'),
                'name' => 'MA_PRODUCT_COVERAGE',
                'class' => 'fixed-width-xs',
                'desc' => $this->trans('Stock coverage, in days. Also, the stock coverage of a given product will be calculated based on this number.', [], 'Modules.Emailalerts.Admin'),
            ];
        }

        $inputs[] = [
                'type' => 'switch',
                'is_bool' => true, //retro compat 1.5
                'label' => $this->trans('Returns', [], 'Modules.Emailalerts.Admin'),
                'name' => 'MA_RETURN_SLIP',
                'desc' => $this->trans('Receive a notification when a customer requests a merchandise return.', [], 'Modules.Emailalerts.Admin'),
                'values' => [
                    [
                        'id' => 'active_on',
                        'value' => 1,
                        'label' => $this->trans('Yes', [], 'Admin.Global'),
                    ],
                    [
                        'id' => 'active_off',
                        'value' => 0,
                        'label' => $this->trans('No', [], 'Admin.Global'),
                    ],
                ],
        ];
        $inputs[] = [
                'type' => 'textarea',
                'cols' => 36,
                'rows' => 4,
                'label' => $this->trans('Email addresses', [], 'Modules.Emailalerts.Admin'),
                'name' => 'MA_MERCHANT_MAILS',
                'desc' => $this->trans('One email address per line (e.g. bob@example.com).', [], 'Modules.Emailalerts.Admin'),
        ];

        $fields_form_2 = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Merchant notifications', [], 'Modules.Emailalerts.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'input' => $inputs,
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                    'class' => 'btn btn-default pull-right',
                    'name' => 'submitMAMerchant',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->module = $this;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitMailAlertConfiguration';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name
            . '&tab_module=' . $this->tab
            . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$fields_form_1, $fields_form_2]);
    }

    public function hookActionDeleteGDPRCustomer($customer)
    {
        if (!empty($customer['email']) && Validate::isEmail($customer['email'])) {
            $sql = 'DELETE FROM ' . _DB_PREFIX_ . "mailalert_customer_oos WHERE customer_email = '" . pSQL($customer['email']) . "'";
            if (Db::getInstance()->execute($sql)) {
                return json_encode(true);
            }

            return json_encode($this->trans('Mail alert: Unable to delete customer using email.', [], 'Modules.Emailalerts.Admin'));
        }
    }

    public function hookActionExportGDPRData($customer)
    {
        if (!Tools::isEmpty($customer['email']) && Validate::isEmail($customer['email'])) {
            $sql = 'SELECT * FROM ' . _DB_PREFIX_ . "mailalert_customer_oos WHERE customer_email = '" . pSQL($customer['email']) . "'";
            if ($res = Db::getInstance()->ExecuteS($sql)) {
                return json_encode($res);
            }

            return json_encode($this->trans('Mail alert: Unable to export customer using email.', [], 'Modules.Emailalerts.Admin'));
        }
    }

    public function getConfigFieldsValues()
    {
        return [
            'MA_CUSTOMER_QTY' => Tools::getValue('MA_CUSTOMER_QTY', Configuration::get('MA_CUSTOMER_QTY')),
            'MA_MERCHANT_ORDER' => Tools::getValue('MA_MERCHANT_ORDER', Configuration::get('MA_MERCHANT_ORDER')),
            'MA_MERCHANT_OOS' => Tools::getValue('MA_MERCHANT_OOS', Configuration::get('MA_MERCHANT_OOS')),
            'MA_LAST_QTIES' => Tools::getValue('MA_LAST_QTIES', Configuration::get('MA_LAST_QTIES')),
            'MA_MERCHANT_COVERAGE' => Tools::getValue('MA_MERCHANT_COVERAGE', Configuration::get('MA_MERCHANT_COVERAGE')),
            'MA_PRODUCT_COVERAGE' => Tools::getValue('MA_PRODUCT_COVERAGE', Configuration::get('MA_PRODUCT_COVERAGE')),
            'MA_MERCHANT_MAILS' => Tools::getValue('MA_MERCHANT_MAILS', Configuration::get('MA_MERCHANT_MAILS')),
            'MA_ORDER_EDIT' => Tools::getValue('MA_ORDER_EDIT', Configuration::get('MA_ORDER_EDIT')),
            'MA_RETURN_SLIP' => Tools::getValue('MA_RETURN_SLIP', Configuration::get('MA_RETURN_SLIP')),
        ];
    }

    public function isUsingNewTranslationSystem()
    {
        return true;
    }
}
