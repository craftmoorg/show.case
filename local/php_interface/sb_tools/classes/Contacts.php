<?php

namespace SB;

class Contacts
{
    public static function getContactData($contactId)
    {
        \CModule::IncludeModule('crm');

        $rsContact = \CCrmContact::GetList(
            $arOrder = array('ID' => 'DESC'),
            $arFilter = array(
                'ID' => $contactId,
                'CHECK_PERMISSIONS' => 'N',
            ),
            $arSelect = array()
        );

        if ($arContact = $rsContact->Fetch()) {
            $arContact['AR_EMAIL'] = [];
            $arContact['AR_PHONE'] = [];

            // Получаем телефон
            $fmResult = \CCrmFieldMulti::GetList(
                array('ID' => 'asc'),
                array(
                    'ENTITY_ID' => \CCrmOwnerType::ResolveName(\CCrmOwnerType::Contact),
                    'ELEMENT_ID' => $arContact['ID'],
                    'TYPE_ID' => 'PHONE',
                )
            );
            while ($arFm = $fmResult->fetch()) {
                if ($arFm['TYPE_ID'] == 'PHONE') {
                    if (!$arContact['PHONE']) {
                        $arContact['PHONE'] = preg_replace('/\D/ui', '', $arFm['VALUE']);
                    }
                    $arContact['AR_PHONE'][] = preg_replace('/\D/ui', '', $arFm['VALUE']);
                }
            }

            // Получаем Email
            $fmResult = \CCrmFieldMulti::GetList(
                array('ID' => 'asc'),
                array(
                    'ENTITY_ID' => \CCrmOwnerType::ResolveName(\CCrmOwnerType::Contact),
                    'ELEMENT_ID' => $arContact['ID'],
                    'TYPE_ID' => 'EMAIL',
                )
            );
            while ($arFm = $fmResult->fetch()) {
                if ($arFm['TYPE_ID'] == 'EMAIL') {
                    if (!$arContact['EMAIL']) {
                        $arContact['EMAIL'] = $arFm['VALUE'];
                    }
                    $arContact['AR_EMAIL'][] = $arFm['VALUE'];
                }
            }

            $arContact['COMPANY_IDs'] = \Bitrix\Crm\Binding\ContactCompanyTable::getContactCompanyIDs($arContact['ID']);
        }

        if (!$arContact['COMPANY_ID'] && $arContact['COMPANY_IDs']) {
            $arContact['COMPANY_ID'] = reset($arContact['COMPANY_IDs']);
        }

        return $arContact;
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
                    $IDs[] = $id;
                }
            }
            $arFilter['!ID'] = $IDs;
        }

        $obEntity = \CCrmContact::GetListEx(
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
            $checker = new \Bitrix\Crm\Integrity\ContactDuplicateChecker();
            $adapter = \Bitrix\Crm\EntityAdapterFactory::create($fields, \CCrmOwnerType::Contact);
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
                    if ($entityTypeID == \CCrmOwnerType::Contact && !in_array($entity->getEntityID(), $excludeID)) {
                        $duplicateIds[] = $entity->getEntityID();
                    }
                }
            }
            if ($duplicateIds) {
                $obEntity = \CCrmContact::GetListEx(
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

    public static function delegate($contactId, $toUser, $fromUser)
    {
        \Bitrix\Main\Loader::includeModule('crm');
        \Bitrix\Main\Loader::includeModule('tasks');

        $contactId = intval($contactId);
        $toUser = intval($toUser);
        $fromUser = intval(str_replace('user_', '', $fromUser));
        $arContact = self::getContactData(intval($contactId));
        if ($arContact) {
            if (!$fromUser) {
                return;
            }

            // меняем ответсвенного в лиде
            if ($arContact['ASSIGNED_BY_ID'] == $fromUser) {
                $contact = new \CCrmContact(false);
                $fields = ['ASSIGNED_BY_ID' => $toUser];
                $contact->Update($contactId, $fields);
            }

            // меняем ответсвенного в задачах
            $rsTasks = \CTasks::GetList(
                ["ID" => "DESC"],
                [
                    "CHECK_PERMISSIONS" => 'N',
                    'UF_CRM_TASK' => ['C_' . $contactId],
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
                    'UF_CRM_TASK' => ['C_' . $contactId],
                    "!REAL_STATUS" => 5,
                    "CREATED_BY" => $fromUser,
                ]
            );
            while ($arTask = $rsTasks->fetch()) {
                $obTask = new \CTasks(false);
                $obTask->Update($arTask['ID'], ['CREATED_BY' => $toUser], array('USER_ID' => 1));
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
                    'CONTACT_ID' => $contactId,
                )
            );
            while ($arDeal = $dbres->Fetch()) {
                if (!$arDeal['COMPANY_ID']) {
                    \SB\Deals::delegate($arDeal['ID'], $toUser, $fromUser);
                }
            }

            // поиск лидов и делегирование
            $dbres = \CCrmLead::GetList(
                array('ID' => 'DESC'),
                array(
                    'CHECK_PERMISSIONS' => 'N',
                    'CONTACT_ID' => $contactId,
                    '!STATUS_ID' => [
                        'CONVERTED',
                        'JUNK',
                    ],
                )
            );
            while ($arLead = $dbres->Fetch()) {
                if (!$arLead['COMPANY_ID']) {
                    \SB\Leads::delegate($arLead['ID'], $toUser, $fromUser);
                }
            }
        }
    }
}