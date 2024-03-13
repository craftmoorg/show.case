<?php
use Bitrix\Main\Loader;
Loader::IncludeModule('iblock');

class CompanyBooklet extends \CBitrixComponent
{
    private function prepareResultData()
    {
        if (!empty($this->arParams['IBLOCK_CODE']) && !empty($this->arParams['IBLOCK_ELEMENT'])){
            $rsElements = \CIBlockElement::GetList(
                [
                    'id' => 'ASC',
                ],
                [
                    'IBLOCK_CODE' => $this->arParams['IBLOCK_CODE'],
                    'ACTIVE' => 'Y',
                    'CODE' => $this->arParams['IBLOCK_ELEMENT'],
                ],
                false,
                false,
                [
                    'PREVIEW_TEXT',
                    'PROPERTY_BOOKLET_PHOTO_1',
                    'PROPERTY_BOOKLET_PHOTO_2',
                    'PROPERTY_BOOKLET_PHOTO_3',
                    'PROPERTY_BOOKLET',
                ]
            );
            if($element = $rsElements->Fetch())
            {
                $this->arResult['BOOKLET_DATA'] = [
                    'TEXT' => $element['PREVIEW_TEXT'],
                    'PHOTO_1' => CFile::GetPath($element['PROPERTY_BOOKLET_PHOTO_1_VALUE']),
                    'PHOTO_2' => CFile::GetPath($element['PROPERTY_BOOKLET_PHOTO_2_VALUE']),
                    'PHOTO_3' => CFile::GetPath($element['PROPERTY_BOOKLET_PHOTO_3_VALUE']),
                    'FILE' => CFile::GetPath($element['PROPERTY_BOOKLET_VALUE']),
                    'FILE_NAME' => CFile::GetByID($element['PROPERTY_BOOKLET_VALUE'])->Fetch()['ORIGINAL_NAME'],
                ];
            }
        }
    }
    
    public function executeComponent()
    {
        $this->prepareResultData();
        $this->includeComponentTemplate();
    }
}
