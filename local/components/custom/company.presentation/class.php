<?php
use Bitrix\Main\Loader;
Bitrix\Main\Loader::IncludeModule('iblock');

class CompanyPresenttion extends \CBitrixComponent
{
    public function executeComponent()
    {
        if (!empty($this->arParams['PICTURE_PATH'])){
            $this->arResult['PICTURE_PATH'] = $this->arParams['PICTURE_PATH'];
        }
        if (!empty($this->arParams['MUSIC_PATH'])){
            $this->arResult['MUSIC_PATH'] = $this->arParams['MUSIC_PATH'];
        }
        $this->includeComponentTemplate();
    }
}
