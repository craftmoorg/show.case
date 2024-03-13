<?php defined('B_PROLOG_INCLUDED') and (B_PROLOG_INCLUDED === true) or die();

use Bitrix\Highloadblock\HighloadBlockTable;
use FB\Rowenmetric\Constants;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
Loader::includeModule('fb.rowenmetric');
Loc::loadMessages(__FILE__);
$hlblockId = HighloadBlockTable::getList(['filter' => ['NAME' => 'SpyFields']])->fetch()['ID'];


$menuList = [
    0 => [
        "parent_menu" => Constants::MODULE_GLOBAL_MENU,
        "sort" => 100,
        "text" => Loc::getMessage('MODULE_MAIN_MENU_1'),
        "title" => '',
        "url" => '/bitrix/admin/admin_users_reports.php',
        "icon" => 'user_menu_icon',
        "items" => []
    ],
    1 => [
        "parent_menu" => Constants::MODULE_GLOBAL_MENU,
        "sort" => 100,
        "text" => Loc::getMessage('MODULE_MAIN_MENU_2'),
        "title" => '',
        "url" => '/bitrix/admin/highloadblock_rows_list.php?ENTITY_ID=' . $hlblockId . '&lang=ru',
        "icon" => 'iblock_menu_icon_iblocks',
        "items" => []
    ],
    2 => [
        "parent_menu" => Constants::MODULE_GLOBAL_MENU,
        "sort" => 100,
        "text" => Loc::getMessage('MODULE_MAIN_MENU_SETTINGS'),
        "title" => '',
        "url" => '/bitrix/admin/settings.php?mid=' . Constants::MODULE_ID . '&lang=ru',
        "icon" => 'sys_menu_icon',
        "items" => []
    ],
];
return $menuList;


