<?php

namespace SB;

class Company
{
    public static function getCompanyData($companyId)
    {
        \CModule::IncludeModule('crm');

        $arCompanyData = \CCrmCompany::GetById($companyId, false);

        $rsContact = \CCrmCompany::GetList(
            $arOrder = array('ID' => 'DESC'),
            $arFilter = array(
                'ID' => $companyId,
                'CHECK_PERMISSIONS' => 'N',
            ),
            $arSelect = array()
        );

        if ($arCompany = $rsContact->Fetch()) {
            if ($arCompanyData) {
                $arCompany = array_merge($arCompanyData, $arCompany);
            }

            // Получаем Email и телефон
            $fmResult = \CCrmFieldMulti::GetList(
                array('ID' => 'asc'),
                array(
                    'ENTITY_ID' => \CCrmOwnerType::ResolveName(\CCrmOwnerType::Company),
                    'ELEMENT_ID' => $arCompany['ID'],
                    'TYPE_ID' => 'PHONE|EMAIL',
                )
            );
            $arCompany['AR_EMAIL'] = [];
            $arCompany['AR_PHONE'] = [];
            while ($arFm = $fmResult->fetch()) {
                if ($arFm['TYPE_ID'] == 'EMAIL') {
                    if (!$arCompany['EMAIL']) {
                        $arCompany['EMAIL'] = $arFm['VALUE'];
                    }
                    $arCompany['AR_EMAIL'][] = $arFm['VALUE'];
                }

                if ($arFm['TYPE_ID'] == 'PHONE') {
                    if (!$arCompany['PHONE']) {
                        $arCompany['PHONE'] = preg_replace('/\D/ui', '', $arFm['VALUE']);
                    }
                    $arCompany['AR_PHONE'][] = preg_replace('/\D/ui', '', $arFm['VALUE']);
                }
            }

            $req = new \Bitrix\Crm\EntityRequisite();
            $res = $req->getList(array(
                'filter' => array(
                    '=ENTITY_TYPE_ID' => \CCrmOwnerType::Company,
                    '=ENTITY_ID' => $arCompany['ID'],
                )
            ));

            if ($data = $res->fetch()) {
                $arCompany['INN'] = $data["RQ_INN"];
                $arCompany['PRESET_ID'] = $data["PRESET_ID"];

                if ($data["RQ_COMPANY_NAME"] && !$arCompany['TITLE']) {
                    $arCompany['TITLE'] = $data["RQ_COMPANY_NAME"];
                }
                $arCompany['RQ_COMPANY_NAME'] = $data["RQ_COMPANY_NAME"];

                $arCompany['KPP'] = $data["RQ_KPP"];

                $address = reset(\Bitrix\Crm\EntityRequisite::getAddresses($data['ID']));
                $arCompany['ADDRESS'] = implode(', ', array_filter([$address['POSTAL_CODE'], $address['COUNTRY'],  $address['PROVINCE'],  $address['CITY'], $address['ADDRESS_1'], $address['ADDRESS_2']]));
            }

            $arCompany['CONTACT_IDs'] = \Bitrix\Crm\Binding\ContactCompanyTable::getCompanyContactIDs($arCompany['ID']);
        }

        return $arCompany;
    }

    public static function getSubCompaniesData($companyId)
    {
        \CModule::IncludeModule('crm');

        $rsCompanies = \CCrmCompany::GetListEx(
            $arOrder = array('ID' => 'DESC'),
            $arFilter = array(
                '!ID' => $companyId,
                'UF_PARENT_COMPANY' => $companyId,
                'CHECK_PERMISSIONS' => 'N',
            ),
            $arSelect = array('*')
        );

        while ($company = $rsCompanies->Fetch()) {

            // Получаем Email и телефон
            $fmResult = \CCrmFieldMulti::GetList(
                array('ID' => 'asc'),
                array(
                    'ENTITY_ID' => \CCrmOwnerType::ResolveName(\CCrmOwnerType::Company),
                    'ELEMENT_ID' => $company['ID'],
                    'TYPE_ID' => 'PHONE|EMAIL',
                )
            );
            $company['AR_EMAIL'] = [];
            $company['AR_PHONE'] = [];
            while ($arFm = $fmResult->fetch()) {
                if ($arFm['TYPE_ID'] == 'EMAIL') {
                    if (!$company['EMAIL']) {
                        $company['EMAIL'] = $arFm['VALUE'];
                    }
                    $company['AR_EMAIL'][] = $arFm['VALUE'];
                }

                if ($arFm['TYPE_ID'] == 'PHONE') {
                    if (!$company['PHONE']) {
                        $company['PHONE'] = preg_replace('/\D/ui', '', $arFm['VALUE']);
                    }
                    $company['AR_PHONE'][] = preg_replace('/\D/ui', '', $arFm['VALUE']);
                }
            }

            $req = new \Bitrix\Crm\EntityRequisite();
            $res = $req->getList(array(
                'filter' => array(
                    '=ENTITY_TYPE_ID' => \CCrmOwnerType::Company,
                    '=ENTITY_ID' => $company['ID'],
                )
            ));

            if ($data = $res->fetch()) {
                $company['INN'] = $data["RQ_INN"];

                if ($data["RQ_COMPANY_NAME"] && !$company['TITLE']) {
                    $company['TITLE'] = $data["RQ_COMPANY_NAME"];
                }
                $company['RQ_COMPANY_NAME'] = $data["RQ_COMPANY_NAME"];

                $company['KPP'] = $data["RQ_KPP"];

                $address = reset(\Bitrix\Crm\EntityRequisite::getAddresses($data['ID']));
                $company['ADDRESS'] = implode(', ', array_filter([$address['POSTAL_CODE'], $address['COUNTRY'],  $address['PROVINCE'],  $address['CITY'], $address['ADDRESS_1'], $address['ADDRESS_2']]));
            }
            $arCompany[] = $company;
        }

        return $arCompany;
    }


    public static function findByCommunication($type, $value, $excludeID = [], $customSearchCondition = false)
    {
        \CModule::IncludeModule('crm');
        $searchCondition = '=%VALUE';
        if($type == 'PHONE')
        {
            $searchCondition = '%VALUE';
        }
        if ($customSearchCondition) {
            $searchCondition = $customSearchCondition;
        }

        $arFilter = array(
            'FM' => array(
                array(
                    'TYPE_ID' => $type,
                    $searchCondition => $value
                )
            ),
            'CHECK_PERMISSIONS' => 'N'
        );
        if (!is_array($excludeID))
        {
            $excludeID = [$excludeID];
        }
        if (count($excludeID) > 0)
        {
            $IDs = [];
            foreach ($excludeID as $id)
            {
                if (intval($id) > 0)
                {
                    $IDs[] =  $id;
                }
            }
            $arFilter['!ID'] = $IDs;
        }


        $obEntity = \CCrmCompany::GetListEx(
            array('ID' => 'ASC'),
            $arFilter,
            false,
            false //,
            //array('ID', )
        );
        $arResult = [];
        while($arEntity = $obEntity->Fetch()){
            $arResult[] = $arEntity;
        }

        if (!$arResult && $type == 'PHONE') {
            if (!is_array($value)) {
                $value = [$value];
            }
            $fieldNames[] = 'FM.PHONE';
            foreach ($value as $phone) {
                $fields['FM']['PHONE'][] = array('VALUE' => \Bitrix\Main\UserPhoneAuthTable::normalizePhoneNumber($phone));
            }
            $checker = new \Bitrix\Crm\Integrity\CompanyDuplicateChecker();
            $adapter = \Bitrix\Crm\EntityAdapterFactory::create($fields, \CCrmOwnerType::Company);
            $dups = $checker->findDuplicates($adapter, new \Bitrix\Crm\Integrity\DuplicateSearchParams($fieldNames));
            $duplicateIds = [];
            foreach ($dups as $dup) {
                if (!($dup instanceof \Bitrix\Crm\Integrity\Duplicate)) {
                    continue;
                }

                $entities = $dup->getEntities();
                if (!(is_array($entities) && !empty($entities))) {
                    continue;
                }

                foreach ($entities as &$entity) {
                    if (!($entity instanceof \Bitrix\Crm\Integrity\DuplicateEntity)) {
                        continue;
                    }

                    $entityTypeID = $entity->getEntityTypeID();
                    if ($entityTypeID == \CCrmOwnerType::Company && !in_array($entity->getEntityID(), $excludeID)) {
                        $duplicateIds[] = $entity->getEntityID();
                    }
                }
            }
            if ($duplicateIds) {
                $obEntity = \CCrmCompany::GetListEx(
                    array('ID' => 'ASC'),
                    [
                        'CHECK_PERMISSIONS' => 'N',
                        'ID' => $duplicateIds,
                    ],
                    false,
                    false
                );
                $arResult = [];
                while($arEntity = $obEntity->Fetch()){
                    $arResult[] = $arEntity;
                }
            }
        }

        return $arResult;
    }

    public static function findByInn($REQUISITES, $excludeID = [])
    {
        \CModule::IncludeModule('crm');

        if (!is_array($excludeID))
        {
            $excludeID = [$excludeID];
        }
        $baseFilter = [];
        if (count($excludeID) > 0)
        {
            $IDs = [];
            foreach ($excludeID as $id)
            {
                if (intval($id) > 0)
                {
                    $IDs[] =  $id;
                }
            }
            $baseFilter['!ENTITY_ID'] = $IDs;
        }

        $arResult = [];
        foreach ($REQUISITES as $REQUISITE)
        {
            $filter = $baseFilter;

            $kpp = $REQUISITE['RQ_KPP'];
            unset($REQUISITE['RQ_KPP']);

            foreach ($REQUISITE as $param => $value)
            {
                $filter[$param] = $value;
            }

            $requisiteEntity = new \Bitrix\Crm\EntityRequisite(false);
            $res = $requisiteEntity->getList([
                'filter' => $filter,
            ]);
            $arResult = [];
            while ($ar = $res->fetch())
            {
                if ($ar["ENTITY_TYPE_ID"] == 4) {
                    if ($kpp && $ar["RQ_KPP"] && $kpp != $ar["RQ_KPP"]) {
                        continue;
                    }

                    $arFilter = [
                        'ID' => intval($ar['ENTITY_ID']),
                        'CHECK_PERMISSIONS' => 'N',
                    ];
                    $obEntity = \CCrmCompany::GetListEx(
                        array('ID' => 'ASC'),
                        $arFilter,
                        false,
                        false
                    );
                    while($arEntity = $obEntity->Fetch()){
                        $arResult[$arEntity['ID']] = $arEntity;
                    }
                }
            }

            if (count($arResult) > 0)
            {
                break;
            }
        }

        return array_values($arResult);
    }

    public static function delegate($companyId, $toUser, $fromUser)
    {
        global $USER, $DB;

        \Bitrix\Main\Loader::includeModule('crm');
        \Bitrix\Main\Loader::includeModule('tasks');

        $companyId = intval($companyId);
        $toUser = intval($toUser);
        $fromUser = intval(str_replace('user_', '', $fromUser));
        $arCompany = self::getCompanyData(intval($companyId));
        if ($arCompany) {
            if (!$fromUser) {
                return;
            }

            // перепроверяем ответсвенных
            // меняем ответсвенного
            if ($arCompany['ASSIGNED_BY_ID'] == $fromUser) {
                $company = new \CCrmCompany(false);
                $fields = [
                    'ASSIGNED_BY_ID' => $toUser
                ];
                $company->Update($companyId, $fields);
            }
            // меняем постоянного менеджера
            if ($arCompany['UF_CO_CONST_MANAGER'] == $fromUser) {
                $company = new \CCrmCompany(false);
                $fields = [
                    'UF_CO_CONST_MANAGER' => $toUser
                ];
                $company->Update($companyId, $fields);
            }

            // меняем ответсвенного в задачах
            $rsTasks = \CTasks::GetList(
                ["ID" => "DESC"],
                [
                    "CHECK_PERMISSIONS" => 'N',
                    'UF_CRM_TASK' => ['CO_' . $companyId],
                    "!REAL_STATUS" => 5,
                    "RESPONSIBLE_ID" => $fromUser,
                ]
            );
            while ($arTask = $rsTasks->fetch()) {
                \SB\Handler\Tasks::$autoClose = true;
                $oTask = \CTaskItem::getInstance($arTask['ID'], 1);
                try {
                    $oTask->complete();
                } catch (TasksException $e) {

                }
                \SB\Handler\Tasks::$autoClose = false;
            }

            // меняем постановщика в задачах
            $rsTasks = \CTasks::GetList(
                ["ID" => "DESC"],
                [
                    "CHECK_PERMISSIONS" => 'N',
                    'UF_CRM_TASK' => ['CO_' . $companyId],
                    "!REAL_STATUS" => 5,
                    "CREATED_BY" => $fromUser,
                ]
            );
            while ($arTask = $rsTasks->fetch()) {
                $obTask = new \CTasks(false);
                $obTask->Update($arTask['ID'], ['CREATED_BY' => $toUser], array('USER_ID' => 1));
            }

            // переназначем задания БП
            $documentType = array(
                "crm",
                "CCrmDocumentCompany",
                "COMPANY"
            );
            $documentId = array(
                "crm",
                "CCrmDocumentCompany",
                'COMPANY_' . $companyId
            );
            $wflows = \CBPDocument::GetDocumentStates($documentType, $documentId);
            foreach ($wflows as $key => $value) {
                if ($value['STATE_NAME'] == 'InProgress') {
                    $dbTask = \CBPTaskService::GetList(
                        ["ID" => "DESC"],
                        ["WORKFLOW_ID" => $value['ID'], "USER_ID" => $fromUser, 'STATUS' => \CBPTaskStatus::Running],
                        false,
                        false,
                        [
                            "ID",
                            "WORKFLOW_ID",
                            "ACTIVITY",
                            "ACTIVITY_NAME",
                            "MODIFIED",
                            "OVERDUE_DATE",
                            "NAME",
                            "DESCRIPTION",
                            "PARAMETERS",
                            'IS_INLINE',
                            'STATUS',
                            'USER_STATUS',
                            'DOCUMENT_NAME',
                            'DELEGATION_TYPE'
                        ]
                    );
                    while ($arTask = $dbTask->GetNext()) {
                        $errors = [];
                        \CBPDocument::delegateTasks(
                            $fromUser,
                            $toUser,
                            $arTask['ID'],
                            $errors,
                            array(\CBPTaskDelegationType::AllEmployees)
                        );
                    }
                }
            }

            // поиск сделок и делегирование
            $dbres = \CCrmDeal::GetList(
                array('ID' => 'DESC'),
                array(
                    'CHECK_PERMISSIONS' => 'N',
                    '!STAGE_ID' => [
                        'WON',
                        'LOSE',
                        'C1:WON',
                        'C1:LOSE',
                        'C2:WON',
                        'C2:LOSE',
                    ],
                    'COMPANY_ID' => $companyId,
                )
            );
            while ($arDeal = $dbres->Fetch()) {
                \SB\Deals::delegate($arDeal['ID'], $toUser, $arDeal["ASSIGNED_BY_ID"]);
            }

            // поиск лидов и делегирование
            $dbres = \CCrmLead::GetList(
                array('ID' => 'DESC'),
                array(
                    'CHECK_PERMISSIONS' => 'N',
                    'COMPANY_ID' => $companyId,
                    '!STATUS_ID' => [
                        'CONVERTED',
                        'JUNK',
                    ],
                )
            );
            while ($arLead = $dbres->Fetch()) {
                \SB\Leads::delegate($arLead['ID'], $toUser, $arLead["ASSIGNED_BY_ID"]);
            }

            // поиск контактов и делегирование
            $rsContactsIds = $DB->Query('SELECT * FROM b_crm_contact_company  WHERE COMPANY_ID=' . intval($companyId));
            $contactsIds = [];
            while ($arContactId = $rsContactsIds->fetch()) {
                $contactsIds[] = $arContactId['CONTACT_ID'];
            }
            if ($contactsIds) {
                $dbres = \CCrmContact::GetList(
                    array('ID' => 'DESC'),
                    array(
                        'ID' => $contactsIds,
                        'CHECK_PERMISSIONS' => 'N',
                    )
                );
                while ($arContact = $dbres->Fetch()) {
                    \SB\Contacts::delegate($arContact['ID'], $toUser, $arContact["ASSIGNED_BY_ID"]);
                }
            }

            // подменяем ответсвенного в дочерних компаниях
            $isParent = false;
            $rsContact = \CCrmCompany::GetList(
                $arOrder = array('ID' => 'DESC'),
                $arFilter = array(
                    'UF_PARENT_COMPANY' => $arCompany['ID'],
                    'CHECK_PERMISSIONS' => 'N',
                ),
                $arSelect = array()
            );
            while ($arChildCompany = $rsContact->Fetch()) {
                $company = new \CCrmCompany(false);
                $fields = ['ASSIGNED_BY_ID' => $arCompany['ASSIGNED_BY_ID']];
                $company->Update($arChildCompany['ID'], $fields);
            }
            if ($isParent) {
                if (\CModule::IncludeModule('im')) {
                    $arMessageFields = array(
                        "TO_USER_ID" => $USER->GetID(),
                        "FROM_USER_ID" => 0,
                        "NOTIFY_TYPE" => IM_NOTIFY_SYSTEM,
                        "NOTIFY_MODULE" => "im",
                        "NOTIFY_TAG" => "",
                        "NOTIFY_EVENT" => "default",
                        "NOTIFY_MESSAGE" => 'Компания "' . $arCompany['TITLE'] . '" является родительской. В дочерних компаниях также сменен ответсвенный.',
                    );
                    \CIMNotify::Add($arMessageFields);
                }
            }
        }
    }

    public static function checkFields($arCompany, $entityName = 'Компания')
    {
        global $DB;

        $arNeedFeelFields = [];

        if (empty($arCompany['TITLE'])) {
            $arNeedFeelFields[] = $entityName . ': Название';
        }

        if (empty($arCompany['UF_CRM_AMO_372665'])) {
            $arNeedFeelFields[] = $entityName . ': Тип отношений';
        }

        if (empty($arCompany['INN']) && $arCompany['PRESET_ID'] != 3) {
            $arNeedFeelFields[] = $entityName . ': ИНН';
        }

        if (empty($arCompany['KPP']) && $arCompany['PRESET_ID'] == 1) {
            $arNeedFeelFields[] = $entityName . ': КПП';
        }

        if (empty($arCompany['EMAIL']) && empty($arCompany['PHONE'])) {
            $arNeedFeelFields[] = $entityName . ': E-mail или телефон';
        }

        $rsContactsIds = $DB->Query('SELECT * FROM b_crm_contact_company  WHERE COMPANY_ID=' . intval($arCompany['ID']));
        if (!$rsContactsIds->fetch()) {
            $arNeedFeelFields[] = $entityName . ': Контакт';
        }

        return $arNeedFeelFields;
    }

    public static function getCompanyABCFields($companyId = false, $revenue = false)
    {
        if ($companyId && $revenue === false) {
            $revenue = floatval(self::getCompanyData($companyId)['REVENUE']);
        }
        $revenue = floatval($revenue);

        $uf_abc_prop_code = \SB\Defines::COMPANY_PROP_ABC_CATEGORY_UF_CODE; //свойство компании "Категория A/B/C"

        $hasChild = false;
        $groupRevenue = 0;
        if ($companyId) {
            $rsChildCompany = \CCrmCompany::GetList(
                $arOrder = array('ID' => 'DESC'),
                $arFilter = array(
                    'UF_PARENT_COMPANY' => $companyId,
                    'CHECK_PERMISSIONS' => 'N',
                ),
                $arSelect = array('ID', 'REVENUE')
            );
            while ($arChildCompany = $rsChildCompany->Fetch()) {
                if ($arChildCompany['ID'] == $companyId) {
                    continue;
                }
                $hasChild = true;
                $groupRevenue += floatval($arChildCompany['REVENUE']);
            }
            if ($hasChild) {
                $groupRevenue += $revenue;
            }
        }

        return [
            'REVENUE' => $revenue,
            'UF_GROUP_REVENUE' => $groupRevenue,
            $uf_abc_prop_code => $hasChild ? [self::getCompanyABCcategoryEnumId($groupRevenue)] : [self::getCompanyABCcategoryEnumId($revenue)],
        ];
    }

    public static function getCompanyABCcategoryEnumId($revenue = false)
    {
        if (!$revenue) return false;

        $uf_prop = \SB\Defines::COMPANY_PROP_ABC_CATEGORY_UF_CODE; //свойство компании "Категория A/B/C"

        $category_limit_B = floatval(\COption::GetOptionString('grain.customsettings', 'abc_analysis_b_limit')) * pow(10, 9);//млрд
        $category_limit_C = floatval(\COption::GetOptionString('grain.customsettings', 'abc_analysis_c_limit')) * pow(10, 9);//млрд

        if ($revenue >= $category_limit_B) {
            return \SB\Defines::getUfListValueEnumIdByValue('A', $uf_prop);
        } else if ($revenue >= $category_limit_C && $revenue < $category_limit_B) {
            return \SB\Defines::getUfListValueEnumIdByValue('B', $uf_prop);
        } else {
            return \SB\Defines::getUfListValueEnumIdByValue('C', $uf_prop);
        }
    }
}