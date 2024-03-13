<?php

namespace SB;
use \Bitrix\Main\Loader as Loader;

class Defines
{
    const IBLOCK_ID__DEPARTMENT = 3;
    const IBLOCK_ID__IMPORT_COMPANIES_CONTACTS_DATABASE_XLS = 22;

    const IMPORT_COMPANIES_CONTACTS_DATABASE_XLS_STATUS_ENUM_ID_TO_IMPORT = 62;//enum_id значения статуса "К импорту"
    const IMPORT_COMPANIES_CONTACTS_DATABASE_XLS_STATUS_ENUM_ID_IN_PROGRESS = 63;//enum_id значения статуса "В процессе импорта"
    const IMPORT_COMPANIES_CONTACTS_DATABASE_XLS_STATUS_ENUM_ID_DONE = 64;//enum_id значения статуса "Обработан"

    const TASK_GROUP_ID__CONST_MANAGER_CALL = 1;

    const MAIN_SALE_DEPARTMENT_ID = 4;
    const TELEMARKETING_SALE_DEPARTMENT_ID = 19;

    const DMITRIY_TZAREV_USER_ID = 34;

    const CRM_COMPANY_UF_TAG_ID = 249;
    const CRM_CONTACT_UF_TAG_ID = 250;

    const DEAL_HR_CATEGORY_ID = 4;

    //* ПРОД-сайт
    const HLBLOCK_UF_TYPE__LEAD_CODE = 1758; // 1707;
    const HLBLOCK_UF_TYPE__DEAL_CODE = 1757; // 1706;
    const HLBLOCK_UF_TYPE__CONTACT_CODE = 1755; // 1704;
    const HLBLOCK_UF_TYPE__COMPANY_CODE = 1756; // 1705;
    //*/
    /* ДЕВ-сайт
    const HLBLOCK_UF_TYPE__LEAD_CODE = 1707;
    const HLBLOCK_UF_TYPE__DEAL_CODE = 1706;
    const HLBLOCK_UF_TYPE__CONTACT_CODE = 1704;
    const HLBLOCK_UF_TYPE__COMPANY_CODE = 1705;
    //*/

    const COMPANY_PROP_ABC_CATEGORY_UF_CODE = 'UF_CRM_AMO_760363'; //свойство компании "Категория A/B/C"

    const EXTENDED_CRM_RIGHTS_GROUP_ID = 41;

    public static function getCrmMultiFieldForEntityElement($entityType, $entityId)
    {
        Loader::IncludeModule('crm');

        $fmResult = \CCrmFieldMulti::GetList(
            array('ID' => 'asc'),
            array(
                'ENTITY_ID' => \CCrmOwnerType::ResolveName($entityType),
                'ELEMENT_ID' => $entityId,
                'TYPE_ID' => 'PHONE|EMAIL', // array('PHONE', 'EMAIL') // 'PHONE|EMAIL'
            )
        );
        $arResult = [];
        while ($arFm = $fmResult->fetch()) {
            $type = $arFm['TYPE_ID'];
            $arResult[$type][] = $arFm;
        }

        return $arResult;
    }

    static $_UF_LIST__BY_CODE = [];
    static $_UF_LIST__BY_ID = [];

    public static function getUfListValue($fieldCode = false, $fieldId = false)
    {
        if ($fieldCode && isset(self::$_UF_LIST__BY_CODE[$fieldCode])) {
            return self::$_UF_LIST__BY_CODE[$fieldCode];
        }
        if ($fieldId && isset(self::$_UF_LIST__BY_ID[$fieldId])) {
            return self::$_UF_LIST__BY_ID[$fieldId];
        }

        if (!$fieldCode && !$fieldId) {
            return [];
        }

        $filter = [
            "USER_FIELD_NAME" => $fieldCode,
        ];

        if ($fieldId) {
            $filter = [
                "USER_FIELD_ID" => $fieldId,
            ];
        }

        $result = [];
        $rsEnum = \CUserFieldEnum::GetList([], $filter);
        while ($arEnum = $rsEnum->GetNext()) {
            $result[] = $arEnum;
        }

        if ($fieldCode) {
            self::$_UF_LIST__BY_CODE[$fieldCode] = $result;
        }
        if ($fieldId) {
            self::$_UF_LIST__BY_ID[$fieldId] = $result;
        }

        return $result;
    }

    public static function getUfListValueById($enumID, $fieldCode = false, $fieldId = false)
    {
        if (!($enumID > 0)) {
            return [];
        }

        $result = self::getUfListValue($fieldCode, $fieldId);

        $enumData = [];
        foreach ($result as $resRow) {
            if ($resRow['ID'] == $enumID) {
                $enumData = $resRow;
                break;
            }
        }

        return $enumData;
    }

    public static function getUfListValueByIds($enumIDs, $fieldCode = false, $fieldId = false)
    {
        $enumData = [];

        if (!is_array($enumIDs)) {
            $enumIDs = [$enumIDs];
        }
        foreach ($enumIDs as $enumID) {
            $enumData[$enumID] = self::getUfListValueById($enumID, $fieldCode, $fieldId);
        }


        return $enumData;
    }

    public static function getUfListValueEnumIdByValue($value, $fieldCode = false, $fieldId = false)
    {
        if (!($value > 0)) {
            return [];
        }

        $result = self::getUfListValue($fieldCode, $fieldId);

        $enumId = false;
        foreach ($result as $resRow) {
            if ($resRow['VALUE'] == $value) {
                $enumId = $resRow['ID'];
                break;
            }
        }

        return $enumId;
    }

    static $_UF_ELEMENT_IBLOCK__BY_CODE = [];
    static $_UF_ELEMENT_IBLOCK__BY_ID = [];

    public static function getUfElementIBlockById($id, $fieldCode = false, $fieldId = false)
    {
        $result = [];
        if ($fieldCode
            && isset(self::$_UF_ELEMENT_IBLOCK__BY_CODE[$fieldCode])
            && isset(self::$_UF_ELEMENT_IBLOCK__BY_CODE[$fieldCode][$id])
        ) {
            $result = self::$_UF_ELEMENT_IBLOCK__BY_CODE[$fieldCode][$id];
        }
        if ($fieldId
            && isset(self::$_UF_ELEMENT_IBLOCK__BY_ID[$fieldId])
            && isset(self::$_UF_ELEMENT_IBLOCK__BY_ID[$fieldId][$id])
        ) {
            $result = self::$_UF_ELEMENT_IBLOCK__BY_ID[$fieldId][$id];
        }

        if (!empty($result)) {
            return $result;
        }

        $res = \CIBlockElement::GetByID($id);
        if ($ar_res = $res->GetNext()) {
            $result = $ar_res;

            if ($fieldCode) {
                self::$_UF_ELEMENT_IBLOCK__BY_CODE[$fieldCode][$id] = $ar_res;
            }
            if ($fieldId) {
                self::$_UF_ELEMENT_IBLOCK__BY_ID[$fieldId][$id] = $ar_res;
            }
        }

        return $result;
    }

    static $_crmStatusData = [];
    public static function getCrmStatusByEntityId($entityId)
    {
        if (self::$_crmStatusData[$entityId] )
        {
            return self::$_crmStatusData[$entityId];
        }

        \CModule::IncludeModule('crm');

        $orderBy = ['SORT' => 'ASC'];

        $result = [];
        $rs = \CCrmStatus::GetList(
            $orderBy,
            ['ENTITY_ID' => $entityId]
        );
        while ($ar = $rs->fetch()) {
            $result[$ar['STATUS_ID']] = $ar;
        }

        self::$_crmStatusData[$entityId] = $result;
        return $result;
    }
    public static function getCrmStatusNameByStatusId($entityId, $status_id)
    {
        $statusData = self::getCrmStatusByEntityId($entityId);
        if ($statusData[$status_id])
        {
            return $statusData[$status_id]['NAME'];
        }
        return '';
    }
    public static function getCrmDealStageNameByStatusId($status_id)
    {
        \CModule::IncludeModule('crm');

        $orderBy = [];
        $rs = \CCrmStatus::GetList(
            $orderBy,
            [
                'STATUS_ID' => $status_id,
                '%ENTITY_ID' => 'DEAL_STAGE',
            ]
        );
        if ($ar = $rs->fetch()) {
            return $ar['NAME'];
        }

        return $status_id;
    }
}