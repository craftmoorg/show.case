<?php

namespace SB;

use Bitrix\Main\Numerator\Numerator;

class Deals
{
    /**
     * Возвращает массив данных по сделке
     * @param $dealId
     * @return array
     */
    public static function getDealData($dealId)
    {
        \CModule::IncludeModule('crm');

        $arDealData = \CCrmDeal::GetById($dealId, false);

        $dbres = \CCrmDeal::GetList(
            array('ID' => 'DESC'),
            array(
                'ID' => $dealId,
                'CHECK_PERMISSIONS' => 'N',
            )
        );
        if ($arDeal = $dbres->Fetch()) {
            // Получаем данные по контакту
            if ($arDealData) {
                $arDeal = array_merge($arDealData, $arDeal);
            }
            $arDeal['CONTACT'] = array();
            if ($arDeal['CONTACT_ID']) {
                $arDeal['CONTACT'] = Contacts::getContactData($arDeal['CONTACT_ID']);
            }
        }

        return $arDeal;
    }

    /**
     * ВОзвращает массыив со стадиями сделки
     * @param int $categoryId
     * @return array
     */
    public static function getStagesList($categoryId = 0)
    {
        \CModule::IncludeModule('crm');

        $stages = array();
        $allStages = \Bitrix\Crm\Category\DealCategory::getStageList($categoryId);
        foreach ($allStages as $stageID => $stageTitle) {
            $stages[$stageID] = $stageTitle;
        }
        return $stages;
    }

    /**
     * Делегирование сделки другому пользователю
     * @param $dealId
     * @param $toUser
     * @param $fromUser
     */
    public static function delegate($dealId, $toUser, $fromUser)
    {
        \Bitrix\Main\Loader::includeModule('crm');
        \Bitrix\Main\Loader::includeModule('tasks');

        $dealId = intval($dealId);
        $toUser = intval($toUser);
        $fromUser = intval(str_replace('user_', '', $fromUser));
        $arDeal = self::getDealData(intval($dealId));
        if ($arDeal) {
            if (!$fromUser) {
                return;
            }

            // меняем ответсвенного в сделке
            $deal = new \CCrmDeal(false);
            $fields = [];
            if ($arDeal['ASSIGNED_BY_ID'] == $fromUser) {
                $fields['ASSIGNED_BY_ID'] = $toUser;
            }
            if ($fields) {
                $deal->Update($dealId, $fields);
            }

            // переназначем задания БП
            $documentType = array(
                "crm",
                "CCrmDocumentDeal",
                "DEAL"
            );
            $documentId = array(
                "crm",
                "CCrmDocumentDeal",
                'DEAL_' . $dealId
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

            // меняем ответсвенного в задачах
            $rsTasks = \CTasks::GetList(
                ["ID" => "DESC"],
                [
                    "CHECK_PERMISSIONS" => 'N',
                    'UF_CRM_TASK' => ['D_' . $dealId],
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
                    'UF_CRM_TASK' => ['D_' . $dealId],
                    "!REAL_STATUS" => 5,
                    "CREATED_BY" => $fromUser,
                ]
            );
            while ($arTask = $rsTasks->fetch()) {
                $obTask = new \CTasks(false);
                $obTask->Update($arTask['ID'], ['CREATED_BY' => $toUser], array('USER_ID' => 1));
            }
        }
    }
}