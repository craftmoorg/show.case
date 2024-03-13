<?php
use FB\Rowenmetric\Main\Settings;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
Loc::loadMessages(__FILE__);

$request = Application::getInstance()->getContext()->getRequest();
$moduleId = htmlspecialchars($request['mid'] != '' ? $request['mid'] : $request['id']);
Loader::includeModule($moduleId);

$iblocksList = Settings::getIblocks();
$aTabs = [
    [
        'DIV'     => 'edit_1',
        'TAB'     => Loc::getMessage('TAB_NAME_1'),
        'TITLE'   => Loc::getMessage('TAB_TITLE_1'),
        'OPTIONS' => [
            Loc::getMessage('TAB_SECTION_1'),
            [
                'IBLOCK_CATALOG_ID',
                Loc::getMessage('OPTION_NAME_1'),
                null,
                [
                    'selectbox',
                    $iblocksList
                ],
            ],
            [
                'IBLOCK_OFFER_ID',
                Loc::getMessage('OPTION_NAME_2'),
                null,
                [
                    'selectbox',
                    $iblocksList
                ]
            ],
        ]
    ]
];


$tabControl = new CAdminTabControl(
    'tabControl',
    $aTabs
);
$tabControl->begin();
?>
<form action="<?=$APPLICATION->getCurPage(); ?>?mid=<?=$moduleId;?>&lang=<?=LANGUAGE_ID;?>" method="post">
    <?= bitrix_sessid_post(); ?>
    <?
    foreach ($aTabs as $aTab) {
        if ($aTab['OPTIONS']) {
            $tabControl->beginNextTab();
            __AdmSettingsDrawList($moduleId, $aTab['OPTIONS']);
        }
    }
    $tabControl->buttons();
    ?>
    <input type="submit" name="apply" value="Сохранить" class="adm-btn-save" />
    <input type="submit" name="default" value="Сбросить" />
</form>
<?
$tabControl->end();

if ($request->isPost() && check_bitrix_sessid()) {
    $requestValues = $request->getPostList()->getValues();
    foreach ($aTabs as $aTab) {
        foreach ($aTab['OPTIONS'] as $arOption) {
            if (!is_array($arOption)) {
                continue;
            }
            if ($arOption['note']) {
                continue;
            }
            if ($request['apply']) {
                $optionValue = $request->getPost($arOption[0]);
                if ($optionValue === 'default') {
                    $optionValue = null;
                }
                Option::set($moduleId, $arOption[0], $optionValue);
            }
            elseif ($request['default']) {
                Option::set($moduleId, $arOption[0], $arOption[2]);
            }
        }
    }
    LocalRedirect($APPLICATION->getCurPage().'?mid='.$moduleId.'&lang='.LANGUAGE_ID);
}
?>