<?php

use Humbug\SelfUpdate\Updater;

if (!defined('_PS_VERSION_')) {
    die;
}
class ps_console_manager extends Module {
    const CONSOLE_URL = 'https://github.com/sas-adilis/ps_console/raw/master/bin/ps.phar';
    const CONSOLE_VERSION_URL = 'https://github.com/sas-adilis/ps_console/raw/master/bin/current.version';

    private $hooks = ['backOfficeHeader'];
    private static $console_path = _PS_ROOT_DIR_ . '/psc' ;

    public function __construct() {
        $this->name = 'ps_console_manager';
        $this->author = 'Adilis';
        $this->need_instance = 0;
        $this->tab = 'administration';
        $this->version = '0.1.0';
        $this->bootstrap = true;
        $this->displayName = $this->l('Manage Prestashop console');
        $this->description = $this->l('Install and upgrade Prestashop console');
        $this->confirmUninstall = $this->l('Are you sure ?');

        parent::__construct();
    }

    public function install() {
        return parent::install() and $this->registerHook($this->hooks);
    }

    public function uninstall() {
        return parent::uninstall() && self::removeConsole();
    }

    private static function getDistantConsoleVersion() {
        return Tools::file_get_contents(self::CONSOLE_VERSION_URL);
    }

    private static function removeConsole() {
        if (Tools::file_exists_no_cache(self::$console_path)) {
            Tools::deleteFile(self::$console_path);
        }
    }

    private static function updateConsole() {
        if (Tools::file_exists_no_cache(self::$console_path . '.back')) {
            Tools::deleteFile(self::$console_path . '.back');
        }

        if (Tools::file_exists_no_cache(self::$console_path)) {
            rename(self::$console_path, self::$console_path . '.back');
        }
        $remoteFile = fopen(self::CONSOLE_URL, 'rb');
        if (!$remoteFile) {
            return false;
        }
        $localFile = fopen(self::$console_path, 'wb');
        if (!$localFile) {
            return false;
        }

        while (!feof($remoteFile)) {
            $data = fread($remoteFile, 1024);
            fwrite($localFile, $data, 1024);
        }

        fclose($remoteFile);
        fclose($localFile);
        chmod(self::$console_path, 0755);
        return true;
    }

    public function hookBackOfficeHeader() {
        $this->runUpdateConsole();
    }

    private function runUpdateConsole($ignore_last_update = false) {
        $ps_console_last_update = Configuration::get('PSCM_LAST_UPDATE');

        if ($ignore_last_update || $ps_console_last_update != date('Y-m-d')) {
            $ps_console_local_version = Configuration::get('PSCM_LOCAL_VERSION');
            $ps_console_distant_version = self::getDistantConsoleVersion();
            if ($ps_console_local_version != $ps_console_distant_version) {
                if (self::updateConsole()) {
                    $this->context->controller->confirmations[] = $this->l('Prestashop console was updated with success to last version');
                    Configuration::updateValue('PSCM_LOCAL_VERSION', $ps_console_distant_version);
                } else {
                    $this->context->controller->errors[] = $this->l('Unable to update console');
                }
            }
            Configuration::updateValue('PSCM_LAST_UPDATE', date('Y-m-d'));
        }
    }

    public function getContent() {
        if (Tools::isSubmit('submit' . $this->name . 'Module')) {
            if (!Tools::getValue('PSC_AUTHOR_DEFAULT') || !Validate::isName(Tools::getValue('PSC_AUTHOR_DEFAULT'))) {
                $this->context->controller->errors[] = $this->l('Defaut author name is invalid');
            }

            if (!count($this->context->controller->errors)) {
                Configuration::updateGlobalValue('PSC_AUTHOR_DEFAULT', Tools::getValue('PSC_AUTHOR_DEFAULT'));

                $redirect_after = $this->context->link->getAdminLink('AdminModules', true);
                $redirect_after .= '&conf=4&configure=' . $this->name . '&module_name=' . $this->name;
                Tools::redirectAdmin($redirect_after);
            }
        }

        return $this->renderForm();
    }

    private function renderForm() {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit' . $this->name . 'Module';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false);
        $helper->currentIndex .= '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
            'fields_value' => [
                'PSC_AUTHOR_DEFAULT' => Tools::getValue('PSC_AUTHOR_DEFAULT', Configuration::get('PSC_AUTHOR_DEFAULT'))
            ]
        ];

        return $helper->generateForm([
            [
                'form' => [
                    'legend' => [
                        'title' => $this->l('Modules parameters'),
                        'icon' => 'icon-cogs'
                    ],
                    'input' => [
                        [
                            'type' => 'text',
                            'name' => 'PSC_AUTHOR_DEFAULT',
                            'id' => 'PSC_AUTHOR_DEFAULT',
                            'label' => $this->l('Defaut author name'),
                            'required' => true,
                        ],
                    ],
                    'submit' => [
                        'title' => $this->l('Save')
                    ]
                ]
            ]
        ]);
    }
}
