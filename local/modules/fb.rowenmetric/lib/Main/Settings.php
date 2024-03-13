<?php
namespace FB\Rowenmetric\Main;
use Bitrix\Main\Localization\Loc;
use FB\Rowenmetric\Constants;

class Settings
{
    static function getIblocks(): array
    {
        $iblocskList = ['default'  => ' '];
        $rsIblocks = \CIBlock::GetList(
            ['ID' => 'ASC'],
            [],
            false
        );
        while($arIblocks = $rsIblocks->Fetch()){
            $iblocskList[$arIblocks['ID']] = '[' . $arIblocks['ID'] . '] ' . $arIblocks['NAME'];
        }

        return $iblocskList;
    }
}
