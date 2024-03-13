<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\DateTime as DT;
use Bitrix\Main\Entity;
use Bitrix\Main\Web\Uri as Uri;
use Bitrix\Sale\Order;
Loader::IncludeModule('iblock');

class AdminUsersReport extends \CBitrixComponent implements \Bitrix\Main\Engine\Contract\Controllerable
{
    public $stagesList = [];
    public $filter = [];
    public $order = [];
    public $siteUrl = '';
    public $catalogIblockIds = [];

    public function executeComponent()
    {
        $this->arResult['GRID_ID'] = __CLASS__ .'_grid';
        $this->arResult['FILTER_ID'] = __CLASS__ . '_filter';
        $this->arResult['DATA'] = [];
        $this->arResult['TOTAL_ROWS'] = 0;
        $this->siteUrl = 'https://' . $_SERVER['SERVER_NAME'];
        $this->catalogIblockIds = array_filter(array_unique([intval($this->arParams['IBLOCK_CATALOG_ID']), intval($this->arParams['IBLOCK_OFFER_ID'])]));

        $rules = $this->checkUserRules();
        if ($rules === false) {
            $message = '<div>Доступ к отчету закрыт</div>';
            echo $message;
            return;
        }

        try {
            $this->setHeaders();
            $this->initFilter();
            $this->prepareFilter();
            $this->getUsersData();

            CJSCore::Init(['fx']);
            \Bitrix\Main\UI\Extension::load('ui.vue');
            \Bitrix\Main\UI\Extension::load('ui.vue.vuex');
            \Bitrix\Main\UI\Extension::load('ui.buttons');

            $this->includeComponentTemplate();
        }
        catch (\Exception $e)
        {
            ShowError($e->getMessage());
        }
    }

    protected function setHeaders()
    {
        $data = [];
        $data[] = [
            'id' => 'ID',
            'name' => 'ID',
            'sort' => 'ID',
            'default' => true,
        ];
        $data[] = [
            'id' => 'EMAIL',
            'name' => 'Email',
            'sort' => 'EMAIL',
            'default' => true,
        ];
        $data[] = [
            'id' => 'NAME',
            'name' => 'ФИО',
            'sort' => 'NAME',
            'default' => true,
        ];
        $data[] = [
            'id' => 'DATE_REGISTER',
            'name' => 'Дата регистрации',
            'sort' => 'DATE_REGISTER',
            'default' => true,
        ];
        $data[] = [
            'id' => 'LAST_LOGIN',
            'name' => 'Дата последней авторизации',
            'sort' => 'LAST_LOGIN',
            'default' => false,
        ];
        $data[] = [
            'id' => 'CART',
            'name' => 'Список товаров в корзине',
            'sort' => false,
            'default' => false,
        ];
        $data[] = [
            'id' => 'ORDERS',
            'name' => 'Список заказов',
            'sort' => false,
            'default' => false,
        ];

        $this->arResult['HEADERS'] = $data;
    }

    protected function initFilter()
    {
        $this->arResult['FILTER_ID'] = __CLASS__ . '_filter';
        $this->arResult['FILTER_FIELDS'] = [];

        $this->arResult['FILTER_FIELDS']['REGISTRATION_TYPE'] = [
            'name' => 'Способ регистраци',
            'id' => 'REGISTRATION_TYPE',
            'default' => 'Y',
            'type' => 'list',
            'items' => [
                'site' => 'Регистрации с сайта',
                'bim' => 'Регистрации Bim',
                'onec' => 'Регистрации из 1С',
            ],
        ];

        $this->arResult['FILTER_FIELDS']['USER_ACTIVITY'] = [
            'name' => 'Активность пользователя',
            'id' => 'USER_ACTIVITY',
            'default' => 'Y',
            'type' => 'list',
            'items' => [
                'inactive' => 'Бездействующие',
                'active' => 'Есть товары в корзине или заказы',
            ],
        ];

        $this->arResult['FILTER_FIELDS']['REG_DATE'] = [
            'name' => 'Дата регистрации',
            'id' => 'REG_DATE',
            'default' => \Bitrix\Main\UI\Filter\DateType::CURRENT_MONTH,
            'type' => 'date'
        ];

        $this->arResult['FILTER_FIELDS']['AUTH_DATE'] = [
            'name' => 'Дата последней авторизации',
            'id' => 'AUTH_DATE',
            'default' => \Bitrix\Main\UI\Filter\DateType::CURRENT_MONTH,
            'type' => 'date'
        ];

        $this->arResult['FILTER_FIELDS']['ACTIVITY_DATE'] = [
            'name' => 'Дата активности в корзине',
            'id' => 'ACTIVITY_DATE',
            'default' => \Bitrix\Main\UI\Filter\DateType::CURRENT_MONTH,
            'type' => 'date'
        ];

        $this->arResult['FILTER_FIELDS']['ORDER_DATE'] = [
            'name' => 'Дата оформления заказа',
            'id' => 'ORDER_DATE',
            'default' => \Bitrix\Main\UI\Filter\DateType::CURRENT_MONTH,
            'type' => 'date'
        ];
    }

    protected function prepareFilter()
    {
        global $DB;
        $arFilter = [];
        if($this->arResult['FILTER_ID']) {
            $filterOptions = new \Bitrix\Main\UI\Filter\Options($this->arResult['FILTER_ID']);
            $filterFields = $filterOptions->getFilter();

            $filterFields = $filterOptions->getFilter($this->arResult['FILTER_FIELDS']);
            $dateFilter = self::getDateLogicFilter($filterFields);
            $preparedFilterFields = $filterOptions->getFilterLogic($this->arResult['FILTER_FIELDS']);
            foreach ($preparedFilterFields as $key => $value) {
                if (!empty($value) || (is_string($value) && strlen($value) > 0)) {
                    $arFilter[$key] = $value;
                }
            }

            if (is_array($dateFilter) && !empty($dateFilter)) {
                $arFilter = array_merge($arFilter, $dateFilter);
            }
        }

        $arUserFilter = [];
        if ($arFilter["REGISTRATION_TYPE"] == 'site') {
            $arUserFilter['!LOGIN'] = 'buyer%';
            $arUserFilter['!UF_BIM_ACCESS'] = 1;
        } elseif ($arFilter["REGISTRATION_TYPE"] == 'bim') {
            $arUserFilter['!LOGIN'] = 'buyer%';
            $arUserFilter['UF_BIM_ACCESS'] = 1;
        } elseif ($arFilter["REGISTRATION_TYPE"] == 'onec') {
            $arUserFilter['LOGIN'] = 'buyer%';
        }

        if ($arFilter['USER_ACTIVITY'] == 'inactive') {
            $userIds = [];

            $rsUsers = $DB->Query('SELECT
                 DISTINCT b_user.ID as USER_ID
                FROM
                 b_user
                LEFT JOIN b_sale_fuser ON b_sale_fuser.USER_ID=b_user.ID
                LEFT JOIN b_sale_basket ON b_sale_basket.FUSER_ID=b_sale_fuser.ID
                WHERE b_sale_basket.ID IS NOT NULL');
            while ($user = $rsUsers->Fetch()) {
                $userIds[] = $user['USER_ID'];
            }

            if (!$userIds) {
                $userIds = [0];
            }

            if ($arUserFilter['!ID']) {
                $arUserFilter['!ID'] = array_merge($arUserFilter['!ID'], $userIds);
            } else {
                $arUserFilter['!ID'] = $userIds;
            }
        } elseif ($arFilter['USER_ACTIVITY'] == 'active') {
            $userIds = [];

            $rsUsers = $DB->Query('SELECT
                 DISTINCT b_user.ID as USER_ID
                FROM
                 b_user
                LEFT JOIN b_sale_fuser ON b_sale_fuser.USER_ID=b_user.ID
                LEFT JOIN b_sale_basket ON b_sale_basket.FUSER_ID=b_sale_fuser.ID
                WHERE b_sale_basket.ID IS NOT NULL');
            while ($user = $rsUsers->Fetch()) {
                $userIds[] = $user['USER_ID'];
            }

            if (!$userIds) {
                $userIds = [0];
            }

            if ($arUserFilter['ID']) {
                $arUserFilter['ID'] = array_intersect($arUserFilter['ID'], $userIds);
            } else {
                $arUserFilter['ID'] = $userIds;
            }
        }

        if ($arFilter['>=REG_DATE'] && $arFilter['<=REG_DATE']) {
            $arUserFilter['>=DATE_REGISTER'] = $arFilter['>=REG_DATE'];
            $arUserFilter['<=DATE_REGISTER'] = $arFilter['<=REG_DATE'];
        }

        if ($arFilter['>=AUTH_DATE'] && $arFilter['<=AUTH_DATE']) {
            $arUserFilter['>=LAST_LOGIN'] = $arFilter['>=AUTH_DATE'];
            $arUserFilter['<=LAST_LOGIN'] = $arFilter['<=AUTH_DATE'];
        }

        if ($arFilter['>=ACTIVITY_DATE'] && $arFilter['<=ACTIVITY_DATE']) {
            $userIds = [];

            $rsUsers = $DB->Query("SELECT
             DISTINCT b_user.ID as USER_ID
            FROM
             b_user
            LEFT JOIN b_sale_fuser ON b_sale_fuser.USER_ID=b_user.ID
            LEFT JOIN b_sale_basket ON b_sale_basket.FUSER_ID=b_sale_fuser.ID AND b_sale_basket.DATE_UPDATE BETWEEN '" . date('Y-m-d H:i:s', strtotime($arFilter['>=ACTIVITY_DATE'])) . "' AND '" . date('Y-m-d H:i:s', strtotime($arFilter['<=ACTIVITY_DATE'])) . "'
            WHERE b_sale_basket.ID IS NOT NULL");
            while ($user = $rsUsers->Fetch()) {
                $userIds[] = $user['USER_ID'];
            }

            if (!$userIds) {
                $userIds = [0];
            }

            if ($arUserFilter['ID']) {
                $arUserFilter['ID'] = array_intersect($arUserFilter['ID'], $userIds);
            } else {
                $arUserFilter['ID'] = $userIds;
            }
        }

        if ($arFilter['>=ORDER_DATE'] && $arFilter['<=ORDER_DATE']) {
            $userIds = [];

            $rsUsers = $DB->Query("SELECT
                 DISTINCT b_sale_order.USER_ID as USER_ID
                FROM
                 b_sale_order
                WHERE 
                 b_sale_order.DATE_INSERT BETWEEN  '" . date('Y-m-d H:i:s', strtotime($arFilter['>=ORDER_DATE'])) . "' AND '" . date('Y-m-d H:i:s', strtotime($arFilter['<=ORDER_DATE'])) . "'");
            while ($user = $rsUsers->Fetch()) {
                $userIds[] = $user['USER_ID'];
            }

            if (!$userIds) {
                $userIds = [0];
            }

            if ($arUserFilter['ID']) {
                $arUserFilter['ID'] = array_intersect($arUserFilter['ID'], $userIds);
            } else {
                $arUserFilter['ID'] = $userIds;
            }
        }

        $this->filter = $arUserFilter;
    }

    protected function getUsersData()
    {
        $this->setOrders();
        $this->setTotalRows();
        $this->setNavigation();

        if (!empty($this->filter)){
            global $DB;
            $userIds = [];
            $usersData = [];
            $productIds = [];
            $items = [];

            $rsUsers = \Bitrix\Main\UserTable::getList([
                'order' => $this->order,
                'filter' => $this->filter,
                'select' => ['ID', 'NAME', 'EMAIL', 'LAST_NAME', 'SECOND_NAME', 'DATE_REGISTER', 'LAST_LOGIN'],
                "offset" => $this->arResult['NAV']->getOffset(),
                "limit" => $this->arResult['NAV']->getLimit(),
            ]);
            while ($arUser = $rsUsers->Fetch()) {
                $userIds[] = $arUser['ID'];
                $usersData[$arUser['ID']]['HEADERS'] = [
                    'ID' => "<a href='/bitrix/admin/user_edit.php?ID=$arUser[ID]' title='Посмотреть информацию о пользователе' target='_blank'>$arUser[ID]</a>",
                    'EMAIL' => "<a href='/bitrix/admin/user_edit.php?lang=ru&ID=$arUser[ID]' title='Посмотреть информацию о пользователе' target='_blank'>$arUser[EMAIL]</a>",
                    'NAME' => $arUser['LAST_NAME'] . ' ' . $arUser['NAME'] . ' ' . $arUser['SECOND_NAME'],
                    'DATE_REGISTER' => $arUser['DATE_REGISTER'],
                    'LAST_LOGIN' => $arUser['LAST_LOGIN'],
                    'CART' => '',
                    'ORDERS' => '',
                ];

                $rsProducts = $DB->Query("
                    SELECT
                        PRODUCT_ID, NAME, b_sale_basket.DATE_UPDATE, PRICE, QUANTITY, MEASURE_NAME
                    FROM
                        b_sale_fuser
                    INNER JOIN
                        b_sale_basket ON b_sale_fuser.ID=b_sale_basket.FUSER_ID
                    WHERE
                        b_sale_fuser.USER_ID=$arUser[ID] AND ORDER_ID IS NULL 
                ");
                while ($arProducts = $rsProducts->Fetch()) {
                    $productIds[] = $arProducts['PRODUCT_ID'];
                    $usersData[$arUser['ID']]['PRODUCTS'][$arProducts['PRODUCT_ID']] = [
                        'USER_ID' => $arUser['ID'],
                        'PRODUCT_ID' => $arProducts['PRODUCT_ID'],
                        'NAME' => $arProducts['NAME'],
                        'DATE_UPDATE' => date('d.m.Y H:i:s', strtotime($arProducts['DATE_UPDATE'])),
                        'PRICE' => number_format($arProducts['PRICE'], 2, '.', ' ') . ' ₽',
                        'QUANTITY' => (floor($arProducts['QUANTITY']) == $arProducts['QUANTITY'] ? floor($arProducts['QUANTITY']) : number_format($arProducts['QUANTITY'], 2, '.', '')),
                        'MEASURE_NAME' => $arProducts['MEASURE_NAME'],
                    ];
                }
            }

            if (!empty($this->catalogIblockIds)){
                $rsElements = CIBlockElement::GetList(
                    $arOrder = ['ID' => 'ASC'],
                    $arFilter = [
                        'ID' => $productIds,
                        'IBLOCK_ID' => $this->catalogIblockIds,
                    ],
                    $arGroupBy = false,
                    $arNavStartParams = false,
                    $arSelectFields = [
                        'ID',
                        'IBLOCK_TYPE_ID',
                        'IBLOCK_ID',
                        'IBLOCK_SECTION_ID',
                    ]
                );
                while($element = $rsElements->Fetch())
                {
                    foreach ($usersData as &$data){
                        if (!empty($data['PRODUCTS'][$element['ID']])){
                            $data['HEADERS']['CART'] .= "<a href='/bitrix/admin/iblock_element_edit.php?IBLOCK_ID=$element[IBLOCK_ID]&type=$element[IBLOCK_TYPE_ID]&lang=ru&ID=$element[ID]&find_section_section=" . (!empty($element['IBLOCK_SECTION_ID']) ? "$element[IBLOCK_SECTION_ID]" : "-1") . "&WF=Y' target='_blank'>" . $data['PRODUCTS'][$element['ID']]['NAME'] . "</a> (" . $data['PRODUCTS'][$element['ID']]['DATE_UPDATE'] . ", " . $data['PRODUCTS'][$element['ID']]['QUANTITY'] . " " . $data['PRODUCTS'][$element['ID']]['MEASURE_NAME'] . ") - " . $data['PRODUCTS'][$element['ID']]['PRICE'];
                            $data['HEADERS']['CART'] .= "<br>";
                        }
                    }
                    unset($data);
                }
            }
            else{
                foreach ($usersData as &$data){
                    foreach ($data['PRODUCTS'] as $product){
                        $data['HEADERS']['CART'] .= $product['NAME'] . ' ('. $product['DATE_UPDATE'] . ', ' . $product['QUANTITY'] . ' ' . $product['MEASURE_NAME'] . ') - ' . $product['PRICE'];
                        $data['HEADERS']['CART'] .= '<br>';
                    }
                }
                unset($data);
            }

            $rsOrders = Order::getList([
                'order' => ['USER_ID' => 'ASC'],
                'filter' => ['USER_ID' => $userIds],
                'select' => ['ID', 'USER_ID', 'DATE_INSERT', 'PRICE'],
            ]);
            while ($arOrders = $rsOrders->Fetch()) {
                $usersData[$arOrders['USER_ID']]['HEADERS']['ORDERS'] .= "<a href=/bitrix/admin/sale_order_view.php?amp%3Bfilter=Y&amp;%3Bset_filter=Y&amp;lang=ru&amp;ID=$arOrders[ID]' title='Посмотреть подробную информацию о заказе'>№$arOrders[ID]</a> (" . $arOrders['DATE_INSERT']->format('d.m.Y H:i:s') . ") - " . number_format($arOrders['PRICE'], 2, '.', ' ') . " ₽";
                $usersData[$arOrders['USER_ID']]['HEADERS']['ORDERS'] .= "<br>";

            }

            foreach ($usersData as $data){
                $items[] = [
                    'data' => $data['HEADERS'],
                    'actions' => [],
                ];
            }
            $this->arResult['DATA'] = $items;
        }
    }

    protected function setOrders(){
        if(isset($_GET['by']) && isset($_GET['order'])){
            $this->order = [$_GET['by'] => $_GET['order']];
        }
        else{
            $this->order = ['ID' => 'ASC'];
        }
    }

    protected function setTotalRows()
    {
        $rsUsers = \Bitrix\Main\UserTable::getList([
            'order' => ['ID' => 'ASC'],
            'filter' => $this->filter,
            'select' => ['ID'],
        ]);
        $this->arResult['TOTAL_ROWS'] = $rsUsers->getSelectedRowsCount();
    }

    protected function setNavigation()
    {
        $this->arResult['NAV'] = new Bitrix\Main\UI\PageNavigation('s_count');
        $gridOption = \CUserOptions::GetOption('main.interface.grid', $this->arResult['GRID_ID']);
        if($_GET['s_count'] == 'page-all'){
            $pageSize = '';
        }
        else{
            $pageSize = $gridOption['views']['default']['page_size'] ? $gridOption['views']['default']['page_size'] : 5;
        }

        $this->arResult['NAV']->allowAllRecords(true)
            ->setPageSize($pageSize)
            ->setRecordCount($this->arResult['TOTAL_ROWS'])
            ->initFromUri();
    }


    protected function checkUserRules()
    {
        global $USER;
        return $USER->IsAdmin();
    }

    public function configureActions()
    {
        return [];
    }

    public static function getDateLogicFilter(array $data)
    {
        $filter = [];
        $keys = array_filter($data, function($key) { return (mb_substr($key, 0 - mb_strlen(\Bitrix\Main\UI\Filter\DateType::getPostfix())) == \Bitrix\Main\UI\Filter\DateType::getPostfix()); }, ARRAY_FILTER_USE_KEY);
        foreach ($keys as $key => $val)
        {
            $id = mb_substr($key, 0, 0 - mb_strlen(\Bitrix\Main\UI\Filter\DateType::getPostfix()));
            if (array_key_exists($id."_from", $data))
                $filter[">=".$id] = $data[$id."_from"];
            if (array_key_exists($id."_to", $data))
                $filter["<=".$id] = $data[$id."_to"];
        }
        return $filter;
    }
}
