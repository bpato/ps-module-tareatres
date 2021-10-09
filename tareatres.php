<?php
/**
 * Copyright (C) 2020 Brais Pato
 *
 * NOTICE OF LICENSE
 *
 * This file is part of Simplerecaptcha <https://github.com/bpato/simplerecaptcha.git>.
 * 
 * Simplerecaptcha is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Simplerecaptcha is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Foobar. If not, see <https://www.gnu.org/licenses/>.
 *
 * @author    Brais Pato <patodevelop@gmail.com>
 * @copyright 2020 Brais Pato
 * @license   https://www.gnu.org/licenses/ GNU GPLv3
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

use Bpato\Tareatres\openweather\OpenweatherFetcher;

class Tareatres extends Module
{
    
    /** @var string Unique name */
    public $name = 'tareatres';

    /** @var string Version */
    public $version = '1.0.0';

    /** @var string author of the module */
    public $author = 'Brais Pato';

    /** @var int need_instance */
    public $need_instance = 0;

    /** @var string Admin tab corresponding to the module */
    public $tab = 'front_office_features';

    /** @var array filled with known compliant PS versions */
    public $ps_versions_compliancy = [
        'min' => '1.7.3.3',
        'max' => '1.7.9.99'
    ];

    /** @var array Hooks used */
    public $hooks = [
        'displayNav1',
    ];

    /** Name of ModuleAdminController used for configuration */
    const MODULE_ADMIN_CONTROLLER = 'AdminTareatres';

    /** Configuration variable names */
    const CONF_KEY_HOME = 'TAR_FRASE_BIENVENIDA_HOME';
    const CONF_KEY_FOOTER = 'TAR_FRASE_BIENVENIDA_FOOTER';

    /**
     * Constructor of module
     */
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('Modulo Tarea 3', [], 'Modules.Tareados.Admin');
        $this->description = $this->trans('Crear un módulo que ataque a alguna API del tiempo', [], 'Modules.Tareados.Admin');
        $this->confirmUninstall = $this->trans('¿Estás seguro de que quieres desinstalar el módulo?', array(), 'Modules.Tareados.Admin');
    }

    /**
     * @return bool
     */
    public function install()
    {
        return parent::install() 
            && $this->registerHook($this->hooks)
            && $this->installTab()
            && $this->installConfig();
    }


    public function installConfig() {
        return Configuration::updateValue('PS_GEOLOCATION_ENABLED', true);
    }

    /**
     * @return bool
     */
    public function installTab()
    {
        $tab = new Tab();
        
        $tab->class_name = static::MODULE_ADMIN_CONTROLLER;
        $tab->name = array_fill_keys(
            Language::getIDs(false),
            $this->name
        );
        $tab->active = false;
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminModulesManage');
        $tab->module = $this->name;

        return $tab->add();
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        return parent::uninstall()
            && $this->uninstallTabs()
            && $this->uninstallConfiguration();
    }

    /**
     * @return bool
     */
    public function uninstallTabs()
    {
        $id_tab = (int) Tab::getIdFromClassName(static::MODULE_ADMIN_CONTROLLER);

        if ($id_tab) {
            $tab = new Tab($id_tab);

            return (bool) $tab->delete();
        }

        return true;
    }

    /**
     * @return bool
     */
    public function uninstallConfiguration()
    {
        return true;
    }

    public function hookDisplayNav1($params)
    {
        $args = ['city' => \Country::getNameById((int) \Context::getContext()->language->id, (int) \Configuration::get('PS_COUNTRY_DEFAULT'))];
        if (!in_array(Tools::getRemoteAddr(), ['localhost', '127.0.0.1', '::1'])) {
            if (@filemtime(_PS_GEOIP_DIR_ . _PS_GEOIP_CITY_FILE_)) {
                //if (!isset($this->context->cookie->iso_code_country) ) {
                    $reader = new GeoIp2\Database\Reader(_PS_GEOIP_DIR_ . _PS_GEOIP_CITY_FILE_);

                    try {
                        $record = $reader->city(Tools::getRemoteAddr());
                    } catch (\GeoIp2\Exception\AddressNotFoundException $e) {
                        $record = null;
                    }
                    
                    if (is_object($record) && \Validate::isLanguageIsoCode($record->country->isoCode) && (int) \Country::getByIso(strtoupper($record->country->isoCode)) != 0) {
                        $args['city'] = $record->city->name;
                    }
                //}
            }
        }

        $weatherfetcher = new OpenweatherFetcher();
        $weatherdata = $weatherfetcher->getData($args);

        $this->context->smarty->assign('weatherdata', $weatherdata);
        return $this->fetch('module:tareatres/views/templates/hook/displayNav.tpl');
    }

    // https://mirrors-cdn.liferay.com/geolite.maxmind.com/download/geoip/database/GeoLiteCity.dat.xz
}