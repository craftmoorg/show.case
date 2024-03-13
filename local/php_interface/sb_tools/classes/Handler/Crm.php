<?php

namespace SB\Handler;

use SB\Company;
use SB\Contacts;
use SB\Defines;
use SB\Handler;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;

class Crm
{
    use Singleton, CheckFields;

    protected static $beforeUpdateCompanyData = [];
    protected static $beforeUpdateContactCompany = false;
    protected static $beforeUpdateCompanyContacts = [];
    public static $companyUpdate = true;
    public static $updateFromCode = false;
    public static $cronSearchDuplicates = false;

    public static $prevCompanyData = [];

    protected static $disabledToUpdateRevenueCompanyIds = [];

    public function __construct()
    {
        EventManager::getInstance()->addEventHandler(
            "crm",
            "OnBeforeCrmContactAdd",
            array($this, 'OnContact_checkDuplicates')
        );
        EventManager::getInstance()->addEventHandler(
            "crm",
            "OnBeforeCrmContactUpdate",
            array($this, 'OnContact_checkDuplicates')
        );

        EventManager::getInstance()->addEventHandler(
            "crm",
            "OnBeforeCrmCompanyAdd",
            array($this, 'OnCompanyUpdate')
        );

        EventManager::getInstance()->addEventHandler(
            "crm",
            "OnBeforeCrmCompanyUpdate",
            array($this, 'OnBeforeCrmCompanyUpdate_checkResponsible')
        );

        EventManager::getInstance()->addEventHandler(
            "crm",
            "OnBeforeCrmCompanyUpdate",
            array($this, 'OnCompanyUpdate')
        );

        EventManager::getInstance()->addEventHandler(
            "crm",
            "OnBeforeCrmDealUpdate",
            array($this, 'OnBeforeCrmDealUpdate')
        );

        EventManager::getInstance()->addEventHandler(
            "crm",
            "OnAfterCrmDealUpdate",
            array($this, 'checkContactType')
        );

        EventManager::getInstance()->addEventHandler(
            "crm",
            "OnAfterCrmDealAdd",
            array($this, 'checkContactType')
        );

        EventManager::getInstance()->addEventHandler(
            "crm",
            "OnAfterCrmCompanyUpdate",
            array($this, 'OnAfterCrmCompanyUpdate')
        );

        EventManager::getInstance()->addEventHandler(
            "crm",
            "OnBeforeCrmContactUpdate",
            array($this, 'OnBeforeCrmContactUpdate_checkResponsible')
        );

        EventManager::getInstance()->addEventHandler(
            "crm",
            "OnAfterCrmContactUpdate",
            array($this, 'OnAfterCrmContactUpdate_checkResponsible')
        );

        EventManager::getInstance()->addEventHandler(
            "crm",
            "OnAfterCrmCompanyAdd",
            array($this, 'OnCompany_checkNameDuplicates')
        );
        EventManager::getInstance()->addEventHandler(
            "crm",
            "OnAfterCrmCompanyUpdate",
            array($this, 'OnCompany_checkNameDuplicates')
        );
        EventManager::getInstance()->addEventHandler(
            "crm",
            "OnBeforeCrmLeadAdd",
            array($this, 'onBeforeCrmLeadAdd')
        );

        EventManager::getInstance()->addEventHandler(
            'crm', 'OnAfterCrmAddEvent',
            [$this, 'OnAfterCrmAddEvent']
        );

        EventManager::getInstance()->addEventHandler(
            'crm', 'OnBeforeCrmLeadAdd',
            [$this, 'checkLeadFieldsForLinks']
        );
    }

    public static function checkContactType(&$arFields)
    {
        global $APPLICATION;
        $fieldsSpam = false;
        $fieldsToCheck = ['TITLE', 'COMPANY_TITLE', 'NAME', 'LAST_NAME'];
        foreach ($fieldsToCheck as $fieldName) {
            if (preg_match('/<a\s+.*<\/a>/i', $arFields[$fieldName]) || preg_match('/(http|www\.|<a>|<\/a>)/', $arFields[$fieldName])) {
                $fieldsSpam = true;
                break;
            }
        }

        if ($fieldsSpam){
            $message = 'Ссылки в полях запрещены!';
            $arFields['RESULT_MESSAGE'] = $message;
            $APPLICATION->ThrowException($message);
            return false;
        }
    }
    
    public static function checkContactType($arFields)
    {
        $arDeal = \SB\Deals::getDealData($arFields['ID']);
        if ($arDeal['CATEGORY_ID'] == \SB\Defines::DEAL_HR_CATEGORY_ID && $arDeal['CONTACT_ID']) {
            $arContact = \SB\Contacts::getContactData($arDeal['CONTACT_ID']);
            if ($arContact['TYPE_ID'] != 'UC_G2SGDH') {
                $contact = new \CCrmContact(false);
                $fields = ['TYPE_ID' => 'UC_G2SGDH'];
                $contact->Update($arContact['ID'], $fields);
            }
        }
    }

    public static function OnBeforeCrmDealUpdate(&$arFields)
    {
        global $APPLICATION, $USER;

        $arDeal = \SB\Deals::getDealData($arFields['ID']);

        // Если у сделки нет активности в виде дел или задач, то запрещаем ее изменять
        if (
            $arFields['STAGE_ID']
            && $arFields['STAGE_ID'] != $arDeal['STAGE_ID']
            && $arDeal['CATEGORY_ID'] == 0
            && strpos($arFields['STAGE_ID'], 'WON') === false
            && strpos($arFields['STAGE_ID'], 'LOSE') === false
        ) {
            $rsActivities = \CCrmActivity::GetList(
                ['ID' => 'DESC'],
                [
                    'BINDINGS' => [[
                        'OWNER_ID' => $arFields['ID'],
                        'OWNER_TYPE_ID' => 2 // \CCrmOwnerType::Deal не работает, потому что Битрикс
                    ]],
                    '!COMPLETED' => 'Y',
                ],
                false,
                false,
                ['PROVIDER_TYPE_ID', 'ASSOCIATED_ENTITY_ID'] // 'ASSOCIATED_ENTITY_ID'
            );
            while ($activity = $rsActivities->Fetch()) {
                if ($activity['PROVIDER_TYPE_ID'] == 'TODO' || $activity['PROVIDER_TYPE_ID'] == 'TASKS_TASK') {
                    $arActivities[] = $activity['ASSOCIATED_ENTITY_ID'];
                }
            }
            if (empty($arActivities)) {
                $message = 'Для смены стадии сделки необходимо создать дело или задачу по сделке!';
                if (\CModule::IncludeModule('im')) {
                    $arMessageFields = array(
                        "TO_USER_ID" => $USER->GetID(),
                        "FROM_USER_ID" => 0,
                        "NOTIFY_TYPE" => IM_NOTIFY_SYSTEM,
                        "NOTIFY_MODULE" => "im",
                        "NOTIFY_TAG" => "",
                        "NOTIFY_EVENT" => "default",
                        "NOTIFY_MESSAGE" => $message,
                    );
                    \CIMNotify::Add($arMessageFields);
                }
                $arFields['RESULT_MESSAGE'] = $message;
                $APPLICATION->ThrowException($message);
                return false;
            }
        }

        if (
            array_key_exists('ASSIGNED_BY_ID', $arFields)
            && $arFields['ASSIGNED_BY_ID'] != $arDeal['ASSIGNED_BY_ID']
            && $arFields['ASSIGNED_BY_ID'] != $arDeal['UF_CRM_642D664F3BB57']
            && !\SB\Users::hasExtendedCrmRights()
            && \SB\Users::isUserFromSaleDepartment($arDeal['ASSIGNED_BY_ID'])
            && time() - strtotime($arDeal['DATE_CREATE']) >= 120
        ) {
            $message = 'Менять ответсвенного в сделке могут только руководители отдела и администраторы';
            \CModule::IncludeModule('im');
            $arMessageFields = array(
                "TO_USER_ID" => $USER->GetId(),
                "FROM_USER_ID" => 0,
                "NOTIFY_TYPE" => IM_NOTIFY_SYSTEM,
                "NOTIFY_MODULE" => "im",
                "NOTIFY_TAG" => "",
                "NOTIFY_EVENT" => "default",
                "NOTIFY_MESSAGE" => $message,
            );
            \CIMNotify::Add($arMessageFields);

            unset($arFields['ASSIGNED_BY_ID']);
        }

        // Если у компании есть тег "ТОП-клиент", то добавляем тег "ТОП-продажа"
        $isDealStageWon = (strpos($arFields['STAGE_ID'], 'WON') !== false ? true : false);
        if ($isDealStageWon) {
            $companyId = array_key_exists('COMPANY_ID', $arFields) ? $arFields['COMPANY_ID'] : $arDeal['COMPANY_ID'];
            if ($companyId) {
                $tagTopClientId = 747;
                $tagTopSaleId = 760;
                $arCompany = \SB\Company::getCompanyData($companyId);
                if (is_array($arCompany['UF_TAG']) && in_array($tagTopClientId, $arCompany['UF_TAG'])) {
                    array_push($arCompany['UF_TAG'], $tagTopSaleId);
                    $arCompany['UF_TAG'] = array_unique($arCompany['UF_TAG']);
                    $GLOBALS['USER_FIELD_MANAGER']->Update('CRM_COMPANY', $companyId, ['UF_TAG' => $arCompany['UF_TAG']]);
                }
            }
        }

        if (
            $arFields['STAGE_ID']
            && $arFields['STAGE_ID'] != $arDeal['STAGE_ID']
            && in_array($arFields['STAGE_ID'], ['UC_7UG3YJ', 'UC_GIR5LQ', 'UC_79RSI9', 'UC_0GA79Q', 'UC_ZZQEZ9'])
        ) {
            $contactId = array_key_exists('CONTACT_ID', $arFields) ? $arFields['CONTACT_ID'] : $arDeal['CONTACT_ID'];
            $companyId = array_key_exists('COMPANY_ID', $arFields) ? $arFields['COMPANY_ID'] : $arDeal['COMPANY_ID'];

            $arNeedFeelFields = [];
            if ($companyId) {
                $arCompany = \SB\Company::getCompanyData($companyId);
                $arNeedFeelFields = \SB\Company::checkFields($arCompany);
                if (!empty($arNeedFeelFields) && intval($arCompany['UF_PARENT_COMPANY'])) {
                    $arParentCompany = \SB\Company::getCompanyData(intval($arCompany['UF_PARENT_COMPANY']));
                    $arNeedFeelFields = \SB\Company::checkFields($arParentCompany, 'Родительская компания');
                }
            } else {
                if (empty($arCompany['PHONE'])) {
                    $arNeedFeelFields[] = 'Компания (добавить в сделку)';
                }
            }

            if ($contactId) {
                $arContact = \SB\Contacts::getContactData($contactId);
                if (empty($arContact['FULL_NAME'])) {
                    $arNeedFeelFields[] = 'Контакт: ФИО';
                }

                if (empty($arContact['EMAIL']) && empty($arContact['PHONE'])) {
                    $arNeedFeelFields[] = 'Контакт: E-mail или телефон';
                }

                if (empty($arContact['POST'])) {
                    $arNeedFeelFields[] = 'Контакт: Должность';
                }
            }

            if ($arNeedFeelFields) {
                $message = 'Для смены стадии сделки необходимо заполнить поля контактов и компаний: ' . implode(', ', $arNeedFeelFields);
                $arFields['RESULT_MESSAGE'] = $message;

                \CModule::IncludeModule('im');
                $arMessageFields = array(
                    "TO_USER_ID" => $USER->GetId(),
                    "FROM_USER_ID" => 0,
                    "NOTIFY_TYPE" => IM_NOTIFY_SYSTEM,
                    "NOTIFY_MODULE" => "im",
                    "NOTIFY_TAG" => "",
                    "NOTIFY_EVENT" => "default",
                    "NOTIFY_MESSAGE" => $message,
                );
                \CIMNotify::Add($arMessageFields);

                $APPLICATION->ThrowException($message);
                return false;
            }
        }

        if ($arFields['ASSIGNED_BY_ID'] && $arFields['ASSIGNED_BY_ID'] != $arDeal['ASSIGNED_BY_ID'] && intval($arDeal["LEAD_ID"])) {
            $observersIds = \Bitrix\Crm\Observer\ObserverManager::getEntityObserverIDs(\CCrmOwnerType::Lead, intval($arDeal["LEAD_ID"]));
            $observersIds[] = $arFields['ASSIGNED_BY_ID'];
            $observersIds = array_unique($observersIds);
            \Bitrix\Crm\Observer\ObserverManager::registerBulk($observersIds, \CCrmOwnerType::Lead, intval($arDeal["LEAD_ID"]));
        }

        // запрет закрытия сделок менеджерами
        if (
            $arFields['STAGE_ID']
            && $arFields['STAGE_ID'] != $arDeal['STAGE_ID']
        ) {
            $arStages = \SB\Deals::getStagesList();
            $closeStagesIds = [];
            $skipedWonStage = false;
            foreach ($arStages as $stageId => $stageName) {
                if ($stageId == 'WON') {
                    $skipedWonStage = true;
                }

                if ($skipedWonStage) {
                    $closeStagesIds[] = $stageId;
                }
            }

            if (
                in_array($arFields['STAGE_ID'], $closeStagesIds)
                && !\SB\Users::hasExtendedCrmRights()
            ) {
                $message = 'Закрывать сделку может только руководитель отдела';
                $arFields['RESULT_MESSAGE'] = $message;

                \CModule::IncludeModule('im');
                $arMessageFields = array(
                    "TO_USER_ID" => $USER->GetId(),
                    "FROM_USER_ID" => 0,
                    "NOTIFY_TYPE" => IM_NOTIFY_SYSTEM,
                    "NOTIFY_MODULE" => "im",
                    "NOTIFY_TAG" => "",
                    "NOTIFY_EVENT" => "default",
                    "NOTIFY_MESSAGE" => $message,
                );
                \CIMNotify::Add($arMessageFields);

                $APPLICATION->ThrowException($message);
                return false;
            }
        }

        // проброс компании в задачу сделки
        if ($arFields["COMPANY_ID"] && $arFields["COMPANY_ID"] != $arDeal["COMPANY_ID"]) {
            \Bitrix\Main\Loader::includeModule('tasks');
            $rsTasks = \CTasks::GetList(
                ['ID' => 'ASC'],
                [
                    "UF_CRM_TASK" => 'D_' . $arFields['ID'],
                    "CHECK_PERMISSIONS" => 'N',
                ],
                ['ID', 'UF_CRM_TASK', 'TITLE']
            );
            while ($taskData = $rsTasks->fetch()) {
                if (!in_array('CO_' . $arFields["COMPANY_ID"], $taskData['UF_CRM_TASK'])) {
                    $taskData['UF_CRM_TASK'][] = 'CO_' . $arFields["COMPANY_ID"];

                    $obTask = new \CTasks(false);
                    $obTask->Update($taskData['ID'], ['UF_CRM_TASK' => $taskData["UF_CRM_TASK"]], array('USER_ID' => 1));
                }
            }
        }
    }

    public static function OnContact_checkDuplicates(&$arFields)
    {
        global $APPLICATION, $USER;

        if (strpos($_SERVER['REQUEST_URI'], 'crm.entity.merger') !== false || self::$cronSearchDuplicates !== false) {
            return;
        }

        $phone = [];
        $email = [];

        $isPhone = false;
        $isEmail = false;

        $FM = $arFields['FM'];
        foreach ($FM['PHONE'] as $data) {
            $phoneValue = $result = preg_replace('/[^0-9,.]/', '', $data['VALUE']);
            if (strlen($result) > 10) {
                $phoneValue = substr($phoneValue, (strlen($phoneValue) - 10));
            }

            if (strlen($phoneValue) == 0)
                continue;

            $phone[] = $phoneValue;
        }

        foreach ($FM['EMAIL'] as $data) {
            if (strlen($data['VALUE']) == 0)
                continue;

            $email[] = $data['VALUE'];
        }
        $result = [];
        if (count($phone) > 0) {
            $result = Contacts::findByCommunication('PHONE', $phone, $arFields['ID']);
            if (!empty($result)) {
                $isPhone = true;
            }

        }


        if (count($email) > 0 && count($result) == 0) {
            $result = Contacts::findByCommunication('EMAIL', $email, $arFields['ID']);
            if (!empty($result)) {
                $isEmail = true;
            }
        }

        $isCompany = false;
        if (count($result) == 0) {
            if (count($phone) > 0) {
                $result = Company::findByCommunication('PHONE', $phone, $arFields['ID']);
                if (!empty($result)) {
                    $isPhone = true;
                    $isCompany = true;
                }
            }
            if (count($email) > 0 && count($result) == 0) {
                $result = Company::findByCommunication('EMAIL', $email, $arFields['ID']);
                if (!empty($result)) {
                    $isEmail = true;
                    $isCompany = true;
                }
            }
        }

        if (count($result) > 0) {
            $type = '';
            if ($isPhone) {
                $type = 'телефонов';
            }
            if ($isEmail) {
                if (strlen($type) > 0) {
                    $type .= ' и ';
                }
                $type .= 'е-маилов';
            }

            $curr = array_shift($result);

            if ($isCompany) {
                $arCompany = \SB\Company::getCompanyData($curr['ID']);
                $dupName = $arCompany['TITLE'];
                $dupArray = $arCompany;
            } else {
                $arContact = \SB\Contacts::getContactData($curr['ID']);
                $dupName = $arContact['FULL_NAME'];
                $dupArray = $arContact;
            }

            $dupString = '';
            if ($isPhone && is_array($dupArray['AR_PHONE'])) {
                foreach ($phone as $phoneString) {
                    foreach ($dupArray['AR_PHONE'] as $phoneStringEx) {
                        if (
                            \Bitrix\Main\UserPhoneAuthTable::normalizePhoneNumber($phoneString) == \Bitrix\Main\UserPhoneAuthTable::normalizePhoneNumber($phoneStringEx)
                        ) {
                            $dupString = \Bitrix\Main\UserPhoneAuthTable::normalizePhoneNumber($phoneString);
                            break 2;
                        }
                    }
                }
            }
            if ($isEmail && is_array($dupArray['AR_EMAIL'])) {
                foreach ($email as $emailString) {
                    foreach ($dupArray['AR_EMAIL'] as $emailStringEx) {
                        if (trim($emailString) == trim($emailStringEx)) {
                            $dupString = $emailString;
                            break 2;
                        }
                    }
                }
            }

            $message = 'Уже существует %s %s [ID = %s] с указанными данными %s %s , ответственный - %s [%s]';
            $message = sprintf($message,
                $isCompany ? 'компания' : 'контакт',
                $curr['ID'],
                $dupName,
                $type,
                $dupString,
                $curr['ASSIGNED_BY_LAST_NAME'] . ' ' . $curr['ASSIGNED_BY_NAME'],
                $curr['ASSIGNED_BY_ID']
            );

            if (\CModule::IncludeModule('im')) {
                $arMessageFields = array(
                    "TO_USER_ID" => $USER->GetID(),
                    "FROM_USER_ID" => 0,
                    "NOTIFY_TYPE" => IM_NOTIFY_SYSTEM,
                    "NOTIFY_MODULE" => "im",
                    "NOTIFY_TAG" => "",
                    "NOTIFY_EVENT" => "default",
                    "NOTIFY_MESSAGE" => $message,
                );
                \CIMNotify::Add($arMessageFields);
            }

            $arFields['RESULT_MESSAGE'] = $message;
            $APPLICATION->ThrowException($message);
            return false;
        }
    }

    public static function OnBeforeCrmCompanyUpdate_checkResponsible(&$arFields)
    {
        global $USER, $DB;

        $arCompany = \SB\Company::getCompanyData($arFields['ID']);
        if (
            array_key_exists('ASSIGNED_BY_ID', $arFields)
            && $arFields['ASSIGNED_BY_ID'] != $arCompany['ASSIGNED_BY_ID']
            && !\SB\Users::hasExtendedCrmRights()
            && \SB\Users::isUserFromSaleDepartment($arCompany['ASSIGNED_BY_ID'])
        ) {
            $message = 'Менять ответсвенного в компании могут только руководители отдела и администраторы';
            \CModule::IncludeModule('im');
            $arMessageFields = array(
                "TO_USER_ID" => $USER->GetId(),
                "FROM_USER_ID" => 0,
                "NOTIFY_TYPE" => IM_NOTIFY_SYSTEM,
                "NOTIFY_MODULE" => "im",
                "NOTIFY_TAG" => "",
                "NOTIFY_EVENT" => "default",
                "NOTIFY_MESSAGE" => $message,
            );
            \CIMNotify::Add($arMessageFields);

            unset($arFields['ASSIGNED_BY_ID']);
        }

        self::$beforeUpdateCompanyData = $arCompany;

        $rsComIds = $DB->Query('SELECT * FROM b_crm_contact_company  WHERE COMPANY_ID=' . intval($arFields['ID']));
        $contactsIds = [];
        if ($arComId = $rsComIds->fetch()) {
            $contactsIds[] = intval($arComId['CONTACT_ID']);
        }
        self::$beforeUpdateCompanyContacts = $contactsIds;

        $rsChildCompany = \CCrmCompany::GetList(
            $arOrder = array('ID' => 'DESC'),
            $arFilter = array(
                'UF_PARENT_COMPANY' => $arFields['ID'],
                'CHECK_PERMISSIONS' => 'N',
            ),
            $arSelect = array()
        );
        $arChildCompanies = [];
        while ($arChildCompany = $rsChildCompany->Fetch()) {
            $arChildCompanies[] = $arChildCompany;
        }

        if ($arFields['UF_PARENT_COMPANY']) {
            $arParentCompany = \SB\Company::getCompanyData($arFields['UF_PARENT_COMPANY']);
            if (!\SB\Users::hasExtendedCrmRights()) {
                unset($arFields['UF_PARENT_COMPANY']);
                if (\CModule::IncludeModule('im')) {
                    $arMessageFields = array(
                        "TO_USER_ID" => $USER->GetID(),
                        "FROM_USER_ID" => 0,
                        "NOTIFY_TYPE" => IM_NOTIFY_SYSTEM,
                        "NOTIFY_MODULE" => "im",
                        "NOTIFY_TAG" => "",
                        "NOTIFY_EVENT" => "default",
                        "NOTIFY_MESSAGE" => 'Устанавливать родительские компании могут только руководители отделов и администраторы.',
                    );
                    \CIMNotify::Add($arMessageFields);
                }
            } elseif ($arChildCompanies) {
                unset($arFields['UF_PARENT_COMPANY']);
                if (\CModule::IncludeModule('im')) {
                    $arMessageFields = array(
                        "TO_USER_ID" => $USER->GetID(),
                        "FROM_USER_ID" => 0,
                        "NOTIFY_TYPE" => IM_NOTIFY_SYSTEM,
                        "NOTIFY_MODULE" => "im",
                        "NOTIFY_TAG" => "",
                        "NOTIFY_EVENT" => "default",
                        "NOTIFY_MESSAGE" => 'Данная компания является родительской. Ее нельзя установить как дочернюю для другой компании',
                    );
                    \CIMNotify::Add($arMessageFields);
                }
            } elseif ($arParentCompany['UF_PARENT_COMPANY']) {
                unset($arFields['UF_PARENT_COMPANY']);
                if (\CModule::IncludeModule('im')) {
                    $arMessageFields = array(
                        "TO_USER_ID" => $USER->GetID(),
                        "FROM_USER_ID" => 0,
                        "NOTIFY_TYPE" => IM_NOTIFY_SYSTEM,
                        "NOTIFY_MODULE" => "im",
                        "NOTIFY_TAG" => "",
                        "NOTIFY_EVENT" => "default",
                        "NOTIFY_MESSAGE" => 'Компания, которую вы устанавливаете как родительскую, является дочерней для другой компании.',
                    );
                    \CIMNotify::Add($arMessageFields);
                }
            } elseif ($arFields['UF_PARENT_COMPANY'] == $arFields['ID']) {
                unset($arFields['UF_PARENT_COMPANY']);
                if (\CModule::IncludeModule('im')) {
                    $arMessageFields = array(
                        "TO_USER_ID" => $USER->GetID(),
                        "FROM_USER_ID" => 0,
                        "NOTIFY_TYPE" => IM_NOTIFY_SYSTEM,
                        "NOTIFY_MODULE" => "im",
                        "NOTIFY_TAG" => "",
                        "NOTIFY_EVENT" => "default",
                        "NOTIFY_MESSAGE" => 'Нельзя устанавливать для компании в качестве родительской эту же компанию',
                    );
                    \CIMNotify::Add($arMessageFields);
                }
            }
        }

        $parentId = intval(array_key_exists('UF_PARENT_COMPANY', $arFields) ? $arFields['UF_PARENT_COMPANY'] : $arCompany['UF_PARENT_COMPANY']);
        if ($parentId && $parentId != $arFields['ID']) {
            $arParentCompany = \SB\Company::getCompanyData($parentId);
            $assignedById = intval(array_key_exists('ASSIGNED_BY_ID', $arFields) ? $arFields['ASSIGNED_BY_ID'] : $arCompany['ASSIGNED_BY_ID']);
            if ($arParentCompany['ASSIGNED_BY_ID'] && $assignedById != $arParentCompany['ASSIGNED_BY_ID']) {
                if (\CModule::IncludeModule('im')) {
                    $arMessageFields = array(
                        "TO_USER_ID" => $USER->GetID(),
                        "FROM_USER_ID" => 0,
                        "NOTIFY_TYPE" => IM_NOTIFY_SYSTEM,
                        "NOTIFY_MODULE" => "im",
                        "NOTIFY_TAG" => "",
                        "NOTIFY_EVENT" => "default",
                        "NOTIFY_MESSAGE" => 'Ответсвенным за компанию установлен сотрудник, ответственный за родительскую компанию "' . $arParentCompany['TITLE'] . '".',
                    );
                    \CIMNotify::Add($arMessageFields);
                }

                $arFields['ASSIGNED_BY_ID'] = $arParentCompany['ASSIGNED_BY_ID'];
            } elseif (!$arParentCompany) {
                $arFields['UF_PARENT_COMPANY'] = false;
            }

            $arFields['UF_CRM_1697021221741'] = $arParentCompany['UF_CRM_1697021221741'];
        }
    }

    public static function OnAfterCrmCompanyUpdate(&$arFields)
    {
        global $USER, $DB;

        $arCompany = \SB\Company::getCompanyData($arFields['ID']);
        if ($arCompany['ASSIGNED_BY_ID'] != self::$beforeUpdateCompanyData['ASSIGNED_BY_ID']) {
            \SB\Company::delegate($arCompany['ID'], $arCompany['ASSIGNED_BY_ID'], self::$beforeUpdateCompanyData['ASSIGNED_BY_ID']);
        }

        $rsChildCompany = \CCrmCompany::GetList(
            $arOrder = array('ID' => 'DESC'),
            $arFilter = array(
                'UF_PARENT_COMPANY' => $arFields['ID'],
                'CHECK_PERMISSIONS' => 'N',
            ),
            $arSelect = array()
        );
        $obCompany = new \CCrmCompany();
        while ($arChildCompany = $rsChildCompany->Fetch()) {
            if ($arChildCompany['ID'] == $arFields['ID']) {
                continue;
            }
            $arChildCompanyFields = ['UF_CRM_1697021221741' => $arCompany['UF_CRM_1697021221741']];
            $obCompany->Update($arChildCompany['ID'], $arChildCompanyFields);
        }

        $rsComIds = $DB->Query('SELECT * FROM b_crm_contact_company  WHERE COMPANY_ID=' . intval($arFields['ID']));
        $contactsIds = [];
        if ($arComId = $rsComIds->fetch()) {
            $contactsIds[] = intval($arComId['CONTACT_ID']);
        }
        $newContacts = array_diff($contactsIds, self::$beforeUpdateCompanyContacts);
        if ($newContacts && is_array($newContacts)) {
            foreach ($newContacts as $newContactId) {
                $arCompany = \CCrmCompany::GetById($arFields['ID'], false);
                $arContact = \CCrmContact::GetById($newContactId, false);
                if ($arCompany['ASSIGNED_BY_ID'] != $arContact['ASSIGNED_BY_ID']) {
                    \SB\Contacts::delegate($newContactId, $arCompany['ASSIGNED_BY_ID'], $arContact['ASSIGNED_BY_ID']);
                    if (\CModule::IncludeModule('im')) {
                        $arMessageFields = array(
                            "TO_USER_ID" => $USER->GetID(),
                            "FROM_USER_ID" => 0,
                            "NOTIFY_TYPE" => IM_NOTIFY_SYSTEM,
                            "NOTIFY_MODULE" => "im",
                            "NOTIFY_TAG" => "",
                            "NOTIFY_EVENT" => "default",
                            "NOTIFY_MESSAGE" => 'Ответсвенным за контакт установлен сотрудник, ответственный за компанию "' . $arCompany['TITLE'] . '".',
                        );
                        \CIMNotify::Add($arMessageFields);
                    }
                }
            }
        }

        if (
            $arFields['REVENUE'] 
            && $arCompany['UF_PARENT_COMPANY']
            && $arFields['ID'] != $arCompany['UF_PARENT_COMPANY']
        ) {
            $arParentFields = \SB\Company::getCompanyABCFields($arCompany['UF_PARENT_COMPANY']);
            $company = new \CCrmCompany(false);
            $company->Update($arCompany['UF_PARENT_COMPANY'], $arParentFields);
        }

        if (
            array_key_exists('UF_PARENT_COMPANY', $arFields)
            && $arFields['UF_PARENT_COMPANY'] != self::$prevCompanyData['UF_PARENT_COMPANY']
        ) {
            if ($arFields['ID'] != $arFields['UF_PARENT_COMPANY'] && intval($arFields['UF_PARENT_COMPANY'])) {
                $arParentFields = \SB\Company::getCompanyABCFields($arFields['UF_PARENT_COMPANY']);
                $company = new \CCrmCompany(false);
                $company->Update($arFields['UF_PARENT_COMPANY'], $arParentFields);
            }
            if ($arFields['ID'] != self::$prevCompanyData['UF_PARENT_COMPANY'] && intval(self::$prevCompanyData['UF_PARENT_COMPANY'])) {
                $arParentFields = \SB\Company::getCompanyABCFields(self::$prevCompanyData['UF_PARENT_COMPANY']);
                $company = new \CCrmCompany(false);
                $company->Update(self::$prevCompanyData['UF_PARENT_COMPANY'], $arParentFields);
            }
        }
    }

    public static function OnBeforeCrmContactUpdate_checkResponsible(&$arFields)
    {
        global $USER, $DB;

        $arContact = \SB\Contacts::getContactData($arFields['ID']);
        if (
            array_key_exists('ASSIGNED_BY_ID', $arFields)
            && $arFields['ASSIGNED_BY_ID'] != $arContact['ASSIGNED_BY_ID']
            && !\SB\Users::hasExtendedCrmRights()
            && \SB\Users::isUserFromSaleDepartment($arContact['ASSIGNED_BY_ID'])
        ) {
            $message = 'Менять ответсвенного в контакте могут только руководители отдела и администраторы';
            \CModule::IncludeModule('im');
            $arMessageFields = array(
                "TO_USER_ID" => $USER->GetId(),
                "FROM_USER_ID" => 0,
                "NOTIFY_TYPE" => IM_NOTIFY_SYSTEM,
                "NOTIFY_MODULE" => "im",
                "NOTIFY_TAG" => "",
                "NOTIFY_EVENT" => "default",
                "NOTIFY_MESSAGE" => $message,
            );
            \CIMNotify::Add($arMessageFields);

            unset($arFields['ASSIGNED_BY_ID']);
        }

        $rsComIds = $DB->Query('SELECT * FROM b_crm_contact_company  WHERE CONTACT_ID=' . intval($arFields['ID']));
        $companyId = false;
        if ($arComId = $rsComIds->fetch()) {
            $companyId = intval($arComId['COMPANY_ID']);
        }
        self::$beforeUpdateContactCompany = $companyId;
    }

    public static function OnAfterCrmContactUpdate_checkResponsible(&$arFields)
    {
        global $DB, $USER;
        \CModule::IncludeModule('crm');

        $rsComIds = $DB->Query('SELECT * FROM b_crm_contact_company  WHERE CONTACT_ID=' . intval($arFields['ID']));
        $companyId = false;
        if ($arComId = $rsComIds->fetch()) {
            $companyId = intval($arComId['COMPANY_ID']);
        }

        if (
            $companyId
            && $companyId != self::$beforeUpdateContactCompany
        ) {
            $arCompany = \CCrmCompany::GetById($companyId, false);
            $arContact = \CCrmContact::GetById($arFields['ID'], false);
            if ($arCompany['ASSIGNED_BY_ID'] != $arContact['ASSIGNED_BY_ID']) {
                \SB\Contacts::delegate($arFields['ID'], $arCompany['ASSIGNED_BY_ID'], $arContact['ASSIGNED_BY_ID']);
                if (\CModule::IncludeModule('im')) {
                    $arMessageFields = array(
                        "TO_USER_ID" => $USER->GetID(),
                        "FROM_USER_ID" => 0,
                        "NOTIFY_TYPE" => IM_NOTIFY_SYSTEM,
                        "NOTIFY_MODULE" => "im",
                        "NOTIFY_TAG" => "",
                        "NOTIFY_EVENT" => "default",
                        "NOTIFY_MESSAGE" => 'Ответсвенным за контакт установлен сотрудник, ответственный за компанию "' . $arCompany['TITLE'] . '".',
                    );
                    \CIMNotify::Add($arMessageFields);
                }
            }
        }
    }

    public static function OnAfterCrmAddEvent($event)
    {
        global $DB;
        if ($event instanceof \Bitrix\Main\Event) {
            $parameters = $event->getParameters();
            $id = $parameters[0];
            $arFields = $parameters[1];
            if (
                $arFields["ENTITY_TYPE"] == "CONTACT"
                && $arFields["EVENT_TEXT_1"] == "COMPANY"
            ) {
                $crmEvent = $DB->Query('SELECT * FROM b_crm_event  WHERE ID=' . $id)->fetch();
                if ($crmEvent["EVENT_NAME"] == "Добавлена связь") {
                    $contactID = intval($arFields["ENTITY_ID"]);
                    $companyId = intval($arFields["EVENT_TEXT_2"]);
                    if ($contactID && $companyId) {
                        $arCompany = \CCrmCompany::GetById($companyId, false);
                        $arContact = \CCrmContact::GetById($contactID, false);
                        if ($arCompany && $arCompany['ASSIGNED_BY_ID'] != $arContact['ASSIGNED_BY_ID']) {
                            \SB\Contacts::delegate($contactID, $arCompany['ASSIGNED_BY_ID'], $arContact['ASSIGNED_BY_ID']);
                            if (\CModule::IncludeModule('im')) {
                                $arMessageFields = array(
                                    "TO_USER_ID" => $crmEvent["CREATED_BY_ID"],
                                    "FROM_USER_ID" => 0,
                                    "NOTIFY_TYPE" => IM_NOTIFY_SYSTEM,
                                    "NOTIFY_MODULE" => "im",
                                    "NOTIFY_TAG" => "",
                                    "NOTIFY_EVENT" => "default",
                                    "NOTIFY_MESSAGE" => 'Ответсвенным за контакт установлен сотрудник, ответственный за компанию "' . $arCompany['TITLE'] . '".',
                                );
                                \CIMNotify::Add($arMessageFields);
                            }
                        }
                    }
                }
            }
        }
    }

    public static function OnCompanyUpdate(&$arFields)
    {
        if (self::$companyUpdate === false){
            return;
        }

        global $APPLICATION, $USER;

        if (strpos($_SERVER['REQUEST_URI'], 'crm.entity.merger') !== false || self::$cronSearchDuplicates !== false) {
            return;
        }

        $phone = [];
        $email = [];

        $isPhone = false;
        $isEmail = false;
        $isInn = false;

        $FM = $arFields['FM'];
        foreach ($FM['PHONE'] as $data) {
            $phoneValue = preg_replace('/[^0-9,.]/', '', $data['VALUE']);
            if (strlen($phoneValue) > 10) {
                $phoneValue = substr($phoneValue, (strlen($phoneValue) - 10));
            }
            if (strlen($phoneValue) == 0)
                continue;

            $phone[] = $phoneValue;
        }
        foreach ($FM['EMAIL'] as $data) {
            if (strlen($data['VALUE']) == 0)
                continue;

            $email[] = $data['VALUE'];
        }
        $result = [];

        $entityRequisites = [];
        $entityBankDetails = [];
        $REQUISITES = [];
        $reqName = '';
        if (isset($_POST['REQUISITES']) && is_array($_POST['REQUISITES'])) {
            \Bitrix\Crm\EntityRequisite::intertalizeFormData(
                $_POST['REQUISITES'],
                \CCrmOwnerType::Company,
                $entityRequisites,
                $entityBankDetails
            );
            foreach ($entityRequisites as $requestData) {
                $item = [];
                $innValue = $requestData['fields']['RQ_INN'];
                if (strlen($innValue) > 0) {
                    $item['RQ_INN'] = $innValue;
                }
                $kppValue = $requestData['fields']['RQ_KPP'];
                if (strlen($kppValue) > 0) {
                    $item['RQ_KPP'] = $kppValue;
                }

                if (
                    strlen($requestData['fields']['RQ_COMPANY_NAME']) > 0
                    && $requestData['fields']['ENTITY_ID'] == $arFields['ID']
                ) {
                    $reqName = $requestData['fields']['RQ_COMPANY_NAME'];
                }

                if (
                    $item['RQ_INN']
                    && $requestData['fields']['ENTITY_ID'] == $arFields['ID']
                    && !in_array($arFields['ID'], self::$disabledToUpdateRevenueCompanyIds)
                ) {
                    //получаем оборот текущей компании из Dadata по ИНН
                    $companyRevenue = \SB\Dadata\General::getCompany_revenue($item['RQ_INN']);
                    $arAbcFields = \SB\Company::getCompanyABCFields($arFields['ID'], $companyRevenue);
                    $arFields = array_merge($arFields, $arAbcFields);
                    self::$disabledToUpdateRevenueCompanyIds[] = $arFields['ID'];
                }

                if (count($item) > 0) {
                    $REQUISITES[] = $item;
                }
            }
        }

        if (count($phone) > 0) {
            $result = Company::findByCommunication('PHONE', $phone, $arFields['ID']);
            if (!empty($result)) {
                $isPhone = true;
            }
        }
        if (count($email) > 0 && count($result) == 0) {
            $result = Company::findByCommunication('EMAIL', $email, $arFields['ID']);
            if (!empty($result)) {
                $isEmail = true;
            }
        }

        if (count($REQUISITES) > 0 && count($result) == 0) {
            $result = Company::findByInn($REQUISITES, $arFields['ID']);
            if (!empty($result)) {
                $isInn = true;
            }
        }

        $isContact = false;
        if (count($result) == 0) {
            if (count($phone) > 0) {
                $result = Contacts::findByCommunication('PHONE', $phone, $arFields['ID']);
                if (!empty($result)) {
                    $isContact = true;
                    $isPhone = true;
                }

            }


            if (count($email) > 0 && count($result) == 0) {
                $result = Contacts::findByCommunication('EMAIL', $email, $arFields['ID']);
                if (!empty($result)) {
                    $isContact = true;
                    $isEmail = true;
                }
            }
        }

        if (count($result) > 0) {
            $type = '';
            if ($isPhone) {
                $type = 'телефонов';
            }
            if ($isEmail) {
                if (strlen($type) > 0) {
                    $type .= ' и ';
                }
                $type .= 'е-маилов';
            }
            if ($isInn) {
                if (strlen($type) > 0) {
                    $type .= ' и ';
                }
                $type .= 'ИНН';
            }

            $curr = array_shift($result);

            $dupArray = [];
            if ($isContact) {
                $arContact = \SB\Contacts::getContactData($curr['ID']);
                $dupName = $arContact['FULL_NAME'];
                $dupArray = $arContact;
            } else {
                $arCompany = \SB\Company::getCompanyData($curr['ID']);
                $dupName = $arCompany['TITLE'];
                $dupArray = $arCompany;
            }

            $dupString = '';
            if ($isPhone && is_array($dupArray['AR_PHONE'])) {
                foreach ($phone as $phoneString) {
                    foreach ($dupArray['AR_PHONE'] as $phoneStringEx) {
                        if (
                            \Bitrix\Main\UserPhoneAuthTable::normalizePhoneNumber($phoneString) == \Bitrix\Main\UserPhoneAuthTable::normalizePhoneNumber($phoneStringEx)
                        ) {
                            $dupString = \Bitrix\Main\UserPhoneAuthTable::normalizePhoneNumber($phoneString);
                            break 2;
                        }
                    }
                }
            }
            if ($isEmail && is_array($dupArray['AR_EMAIL'])) {
                foreach ($email as $emailString) {
                    foreach ($dupArray['AR_EMAIL'] as $emailStringEx) {
                        if (trim($emailString) == trim($emailStringEx)) {
                            $dupString = $emailString;
                            break 2;
                        }
                    }
                }
            }

            $message = 'Уже существует %s %s [ID = %s] с указанными данными %s %s , ответственный - %s[%s]';
            $message = sprintf($message,
                $isContact ? 'контакт' : 'компания',
                $dupName,
                $curr['ID'],
                $type,
                $dupString,
                $curr['ASSIGNED_BY_LAST_NAME'] . ' ' . $curr['ASSIGNED_BY_NAME'],
                $curr['ASSIGNED_BY_ID']
            );

            if (\CModule::IncludeModule('im')) {
                $arMessageFields = array(
                    "TO_USER_ID" => $USER->GetID(),
                    "FROM_USER_ID" => 0,
                    "NOTIFY_TYPE" => IM_NOTIFY_SYSTEM,
                    "NOTIFY_MODULE" => "im",
                    "NOTIFY_TAG" => "",
                    "NOTIFY_EVENT" => "default",
                    "NOTIFY_MESSAGE" => $message,
                );
                \CIMNotify::Add($arMessageFields);
            }

            $arFields['RESULT_MESSAGE'] = $message;
            $APPLICATION->ThrowException($message);
            return false;
        }

        $arCompany = $arFields;
        self::$prevCompanyData = \SB\Company::getCompanyData($arFields['ID']);
        if ($arFields['ID']) {
            $arCompany = self::$prevCompanyData;
        }
        if ($arCompany['RQ_COMPANY_NAME'] || $reqName) {
            $arFields['TITLE'] = $reqName ? $reqName : $arCompany['RQ_COMPANY_NAME'];
        }
        $parentCompanyId = array_key_exists('UF_PARENT_COMPANY', $arFields) ? $arFields['UF_PARENT_COMPANY'] : $arCompany['UF_PARENT_COMPANY'];
        $status = array_key_exists('UF_CRM_1686820569902', $arFields) && (\SB\Users::hasExtendedCrmRights() || self::$updateFromCode) ? $arFields['UF_CRM_1686820569902'] : $arCompany['UF_CRM_1686820569902'];
        $arFields['UF_MAIN_DEP'] = $parentCompanyId ? 0 : 1;
        $arFields['UF_CRM_1686820569902'] = $status ? $status : 1558;

        //проверка первого номера телефона на добавление нового или изменение измеющегося
        //и определение по нему часового пояса
        if (!empty($arFields['FM']['PHONE'])) {
            $updateTimeZone = false;
            if ($arCompany) {
                //проверяем изменение номера
                $entityType = \CCrmOwnerType::Company;
                //получаем старую инфу о телефонах
                $oldData['FM'] = \SB\Defines::getCrmMultiFieldForEntityElement($entityType, $arCompany['ID']);
                if (!empty($oldData['FM']['PHONE'])) {
                    $firstPhone = reset($oldData['FM']['PHONE']);

                    if ($firstPhone['ID'] && $firstPhone['VALUE']) {
                        //если номера удалён или изменён
                        if (!isset($arFields['FM']['PHONE'][$firstPhone['ID']]) ||
                            isset($arFields['FM']['PHONE'][$firstPhone['ID']]) && $arFields['FM']['PHONE'][$firstPhone['ID']]['VALUE'] != $firstPhone['VALUE']) {

                            $updateTimeZone = true;
                        }
                    }
                } else {
                    $updateTimeZone = true;
                }
            } else {
                $updateTimeZone = true;
            }

            if ($updateTimeZone) {
                $phoneForTimezone = false;
                //берём первый непустой номер
                foreach ($arFields['FM']['PHONE'] as $arPhone) {
                    if ($arPhone['VALUE']) {
                        $phoneForTimezone = $arPhone['VALUE'];
                        break;
                    }
                }
                if ($phoneForTimezone) {
                    //обновляем временную зону по первому номеру из нового массива телефонов
                    $timezone = \SB\Dadata\General::getPhone_timezone($phoneForTimezone);
                    if ($timezone) {
                        $timezone_prop_enumId = \SB\Defines::getUfListValueEnumIdByValue($timezone, 'UF_PHONE_TIMEZONE');
                        if ($timezone_prop_enumId) $arFields['UF_PHONE_TIMEZONE'] = $timezone_prop_enumId;
                    }
                }
            }
        }
    }

    public static function OnCompany_checkNameDuplicates(&$arFields)
    {
        global $USER;
        if ($USER->GetID() && \Bitrix\Main\Application::getInstance()->getContext()->getRequest()->isAjaxRequest()) {
            $arCompany = \SB\Company::getCompanyData($arFields['ID']);
            $rsCompany = \CCrmCompany::GetList(
                $arOrder = array('ID' => 'DESC'),
                $arFilter = array(
                    '!ID' => $arFields['ID'],
                    '=TITLE' => trim($arCompany['TITLE']),
                    'CHECK_PERMISSIONS' => 'N',
                ),
                $arSelect = array()
            );
            if ($arCompanyDup = $rsCompany->fetch()) {
                $arCompanyDup = \CCrmCompany::GetById($arCompanyDup['ID'], false);
                $message = 'Компания с указанным названием "<a href="/crm/company/details/%s/" target="_blank">%s</a>" уже заведена в системе,<br/>ответственный - %s';
                $message = sprintf($message,
                    $arCompanyDup['ID'],
                    $arCompanyDup['TITLE'],
                    $arCompanyDup['ASSIGNED_BY_LAST_NAME'] . ' ' . $arCompanyDup['ASSIGNED_BY_NAME']
                );

                if (\CModule::IncludeModule('im')) {
                    $arMessageFields = array(
                        "TO_USER_ID" => $USER->GetID(),
                        "FROM_USER_ID" => 0,
                        "NOTIFY_TYPE" => IM_NOTIFY_SYSTEM,
                        "NOTIFY_MODULE" => "im",
                        "NOTIFY_TAG" => "",
                        "NOTIFY_EVENT" => "default",
                        "NOTIFY_MESSAGE" => $message,
                    );
                    \CIMNotify::Add($arMessageFields);
                }
            }
        }
    }

    public function onBeforeCrmLeadAdd(&$arFields)
    {
        global $APPLICATION;

        // если звонок не ресепшну, то запрещаем создавать лид
        if ($arFields['SOURCE_ID'] == 'CALL' && $arFields['ASSIGNED_BY_ID'] != 37) {
            $message = 'Создание лидов по звонкам запрещено';
            $arFields['RESULT_MESSAGE'] = $message;
            $APPLICATION->ThrowException($message);
            return false;
        }

        // если звонок не ресепшну, то запрещаем создавать лид
        if ($arFields['SOURCE_ID'] == 'EMAIL' && \SB\Users::isUserFromSaleDepartment($arFields['ASSIGNED_BY_ID'])) {
            $message = 'Создание лидов из почты менеджерам ОП запрещена';
            $arFields['RESULT_MESSAGE'] = $message;
            $APPLICATION->ThrowException($message);
            return false;
        }
    }
}

