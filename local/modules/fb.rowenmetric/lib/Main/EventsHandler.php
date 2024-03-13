<?php
namespace FB\Rowenmetric\Main;
use Bitrix\Main\Localization\Loc;
use FB\Rowenmetric\Constants;

class EventsHandler
{
    public static function OnBuildGlobalMenuHandler(&$aGlobalMenu, &$aModuleMenu)
    {
        global $APPLICATION;
        $aGlobalMenu[Constants::MODULE_GLOBAL_MENU] = [
            "menu_id" => Constants::MODULE_GLOBAL_MENU_ID,
            "text" => Loc::getMessage('MODULE_NAME'),
            "title" => '',
            "sort" => '80',
            "items_id" => Constants::MODULE_GLOBAL_MENU_ID . '_items',
            "help_section" => Constants::MODULE_GLOBAL_MENU_ID,
            "items" => []
        ];
    }
}
