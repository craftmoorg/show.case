<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/subscribe/prolog.php");
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
global $APPLICATION;
use Bitrix\Main\Config\Option;
use FB\Rowenmetric\Constants;
$APPLICATION->SetTitle('Отчет по пользователям');

$APPLICATION->IncludeComponent(
    'fb.rowenmetric:admin.users.report',
    '.default',
    [
        'IBLOCK_CATALOG_ID' => Option::get(Constants::MODULE_ID, 'IBLOCK_CATALOG_ID'),
        'IBLOCK_OFFER_ID' => Option::get(Constants::MODULE_ID, 'IBLOCK_OFFER_ID')
    ]
);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");