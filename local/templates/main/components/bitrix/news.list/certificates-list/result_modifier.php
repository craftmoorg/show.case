<?
if (!empty($arResult['ITEMS'])){
    $arResult['TABS'] = [];
    foreach ($arResult['ITEMS'] as &$item){
        if ($item['PROPERTIES']['CERT_INSTITUTE']['VALUE']){
            if (!in_array($item['PROPERTIES']['CERT_INSTITUTE']['VALUE'], $arResult['TABS'])){
                $arResult['TABS'][] = $item['PROPERTIES']['CERT_INSTITUTE']['VALUE'];
            }
        }
        if ($item['PROPERTIES']['CERT_FILE']['VALUE']){
            $fileInfo = \CFile::GetByID($item['PROPERTIES']['CERT_FILE']['VALUE'])->Fetch();
            $item['PROPERTIES']['CERT_FILE']['SRC'] = $fileInfo['SRC'];
            $item['PROPERTIES']['CERT_FILE']['ORIGINAL_NAME'] = $fileInfo['ORIGINAL_NAME'];
        }
    }
    unset($item);
}