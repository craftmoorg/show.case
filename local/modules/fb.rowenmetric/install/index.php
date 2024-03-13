<?php
use Bitrix\Main\EventManager;       // Пространство для кратко- и долгосрочной регистрации обработчиков событий
use Bitrix\Main\ModuleManager;      // Пространство имен для управления (регистрации/удалении) модуля в системе/базе
use Bitrix\Main\Config\Option;      // Пространство имен для работы с параметрами модулей хранимых в базе данных
use Bitrix\Main\Application;        // Пространство имен с абстрактным классом для любых приложений, любой конкретный класс приложения является наследником этого абстрактного класса
use Bitrix\Main\IO\Directory;       // Пространство имен для работы с директориями
use Bitrix\Main\IO;                 // Пространство имен для работы с файлами
use Bitrix\Main\Localization\Loc;   // Пространство имен для подключений ленговых файлов
Loc::loadMessages(__FILE__);

class fb_rowenmetric extends CModule
{
    public  $MODULE_ID = 'fb.rowenmetric';
    public  $MODULE_VERSION;
    public  $MODULE_VERSION_DATE;
    public  $MODULE_NAME;
    public  $MODULE_DESCRIPTION;
    public  $PARTNER_NAME;
    public  $PARTNER_URI;
    public  $errors;

    function __construct()
    {
        $arModuleVersion = [];
        include_once(__DIR__ . '/version.php');
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('MODULE_DESCRIPTION');
        $this->PARTNER_NAME = Loc::getMessage('PARTNER_NAME');
        $this->PARTNER_URI = 'https://1cbit.ru';
    }

    // метод отрабатывает при установке модуля
    function doInstall()
    {
        global $APPLICATION;
        if (CheckVersion(ModuleManager::getVersion('main'), '14.00.00')){
            $this->installFiles();
            $this->installDB();
            ModuleManager::registerModule($this->MODULE_ID);
            $this->installEvents();
        }
        else{
            CAdminMessage::showMessage(Loc::getMessage('MODULE_INSTALL_ERROR'));
            return;
        }
        $APPLICATION->includeAdminFile(Loc::getMessage('MODULE_INSTALL_TITLE').' «'.$this->MODULE_NAME.'»',__DIR__.'/step.php'
        );
    }

    public function installFiles() {
        CopyDirFiles(
            __DIR__ . '/themes',
            Application::getDocumentRoot() . '/bitrix/themes',
            true,
            true
        );
        CopyDirFiles(
            __DIR__.'/assets/images',
            Application::getDocumentRoot().'/bitrix/images/'.$this->MODULE_ID,
            true,
            true
        );
        CopyDirFiles(
            __DIR__.'/assets/css',
            Application::getDocumentRoot() . '/bitrix/css/' . $this->MODULE_ID,
            true,
            true
        );
        CopyDirFiles(
            __DIR__.'/assets/js',
            Application::getDocumentRoot() . '/bitrix/js/' . $this->MODULE_ID,
            true,
            true
        );
        CopyDirFiles(
            __DIR__.'/components/',
            Application::getDocumentRoot() . '/bitrix/components/' . $this->MODULE_ID,
            true,
            true
        );
        CopyDirFiles(
            __DIR__.'/admin/admin_users_reports.php',
            Application::getDocumentRoot() . '/bitrix/admin/admin_users_reports.php',
            true,
            true
        );
    }

    function installDB()
    {
        /*
        global $DB;
        $this->errors = false;
        // метод выполняет пакет запросов из файла install.sql и возвращает false в случае успеха или массив ошибок
        $this->errors = $DB->RunSQLBatch(__DIR__. '/install/db/install.sql');
        if (!$this->errors) {
            return true;
        }
        else{
            return $this->errors;
        }
        */
        return;
    }

    public function installEvents()
    {
        EventManager::getInstance()->registerEventHandler(
            'main',
            'OnBuildGlobalMenu',
            $this->MODULE_ID,
            'FB\\Rowenmetric\\Main\\EventsHandler',
            'OnBuildGlobalMenuHandler'
        );
    }

    // метод отрабатывает при удалении модуля
    public function doUninstall()
    {
        global $APPLICATION;
        $this->uninstallFiles();
        $this->uninstallDB();
        $this->uninstallEvents();
        ModuleManager::unRegisterModule($this->MODULE_ID);
        $APPLICATION->includeAdminFile(Loc::getMessage('MODULE_UNINSTALL_TITLE').' «'.$this->MODULE_NAME.'»',__DIR__.'/unstep.php');
    }

    public function uninstallFiles() {
        Directory::deleteDirectory(
            Application::getDocumentRoot() . '/bitrix/themes/.default/icons/' . $this->MODULE_ID
        );
        IO\File::deleteFile(Application::getDocumentRoot() . '/bitrix/themes/.default/' . $this->MODULE_ID . '.css');
        Directory::deleteDirectory(
            Application::getDocumentRoot() . '/bitrix/images/' . $this->MODULE_ID
        );
        Directory::deleteDirectory(
            Application::getDocumentRoot() . '/bitrix/css/' . $this->MODULE_ID
        );
        Directory::deleteDirectory(
            Application::getDocumentRoot() . '/bitrix/js/' . $this->MODULE_ID
        );
        Directory::deleteDirectory(
            Application::getDocumentRoot().'/bitrix/components/' . $this->MODULE_ID
        );
        Directory::deleteDirectory(
            Application::getDocumentRoot().'/bitrix/admin/admin_users_reports.php'
        );
        Option::delete($this->MODULE_ID);
    }

    function unInstallDB()
    {
        /*
        global $DB;
        $this->errors = false;
        // метод выполняет пакет запросов из файла uninstall.sql и возвращает false в случае успеха или массив ошибок
        $this->errors = $DB->RunSQLBatch(__DIR__. '/install/db/uninstall.sql');
        if (!$this->errors) {
            return true;
        } else
            return $this->errors;
        */
        return;
    }

    public function uninstallEvents()
    {
        EventManager::getInstance()->unRegisterEventHandler(
            'main',
            'OnBuildGlobalMenu',
            $this->MODULE_ID,
            'FB\\Rowenmetric\\Main\\EventsHandler',
            'OnBuildGlobalMenuHandler'
        );
    }
}
