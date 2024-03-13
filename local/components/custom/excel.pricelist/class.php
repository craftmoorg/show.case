<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true){
    die();
}

use Bitrix\Main\Loader;
Loader::includeModule("iblock");
Loader::includeModule("catalog");

class BitExcelPricelist extends \CBitrixComponent
{
    private static $iblockCode = 'catalog';
    private static $sectionCode = '';

    private static $recommendUserPriceCodes = '';
    private static $basePriceCode = 'BASE';
    private static $marketsMaxPriceCode = 'MARKETS_MAX_PRICE';
    private static $tradePriceSmallCode = 'ОПТ мелкий';
    private static $tradePriceBigCode = 'ОПТ крупный';
    private static $tradePriceDealerCode = 'ОПТ_Диллер';
    private static $recommendUserPriceId;
    private static $basePriceId = '';
    private static $marketsMaxPriceId = '';
    private static $tradePriceSmallId = '';
    private static $tradePriceBigId = '';
    private static $tradePriceDealerId = '';

    private static $products = [];
    private static $sections = [];
    private static $sectionName = 'ДТРД';

    private static $uploadDir = '/upload/unloading_price_lists/';

    /**
     * Подготавливаем необходимые переменные для формирования запроса по товарам
     * @author Craftmoorg
     */
    private function prepareData(): void
    {
        self::$sectionCode = $this->arParams['SECTION_CODE'];
        if ($this->arParams['IS_INDIVIDUAL_PRICE'] == 'Y'){
            global $USER;
            $rsUser = \CUser::GetByID($USER->GetID());
            $arUser = $rsUser->Fetch();
            if (trim($arUser['UF_PRICE_XML_ID'])) {
                $dbPriceType = \CCatalogGroup::GetList(
                    array("SORT" => "ASC"),
                    array("XML_ID" => trim($arUser['UF_PRICE_XML_ID']))
                );
                if ($arPriceType = $dbPriceType->Fetch()) {
                    self::$recommendUserPriceId[$arPriceType['NAME']] = $arPriceType['ID'];
                }
            }
        }
        else{
            $priceFilter = [self::$basePriceCode, self::$tradePriceSmallCode, self::$tradePriceBigCode];
            $res = CCatalogGroup::GetListEx(
                [],
                ['=NAME' => $priceFilter],
                false,
                false,
                []
            );
            while ($group = $res->Fetch()){
                (self::$basePriceCode == $group['NAME']
                    ? self::$recommendUserPriceId[self::$basePriceCode] = $group['ID'] : '');
                (self::$tradePriceSmallCode == $group['NAME']
                    ? self::$recommendUserPriceId[self::$tradePriceSmallCode] = $group['ID'] : '');
                (self::$tradePriceBigCode == $group['NAME']
                    ? self::$recommendUserPriceId[self::$tradePriceBigCode] = $group['ID'] : '');
            }
        }
    }


    /**
     * Получаем товары со всеми подразделами из текущего раздела
     * @author Craftmoorg
     */
    public function getProducts(): void
    {
        $arFilter = array(
            'IBLOCK_CODE' => self::$iblockCode,
            'ACTIVE' => 'Y',
            'GLOBAL_ACTIVE' => 'Y',
            'ELEMENT_SUBSECTIONS' => 'Y',
            //'DEPTH_LEVEL' => 1,
            'CNT_ACTIVE' => 'Y',
        );
        if (self::$sectionCode) {
            $arFilter['CODE'] = self::$sectionCode;
        } else {
            $arFilter['DEPTH_LEVEL'] = 1;
        }

        $rsSection = \CIBlockSection::GetList(array('LEFT_MARGIN' => 'asc'), $arFilter, true);
        $arSections = array();
        while ($arSect = $rsSection->fetch()) {
            if (
                $arSect['ELEMENT_CNT'] > 0
            ) {
                $row = array();
                $row["ID"] = $arSect["ID"];
                $row["NAME"] = $arSect["NAME"];
                $row["DEPTH_LEVEL"] = $arSect["DEPTH_LEVEL"];
                $arSections[$arSect["ID"]] = $row;
                $this->addSubsections($arSect["ID"], $arSections);
                if (self::$sectionCode) {
                    self::$sectionName = $arSect["NAME"] . ' - ДТРД';
                }
            }
        }
        
        $arSelect = [
            'ID',
            'IBLOCK_ID',
            'NAME',
            'PROPERTY_ARTICLE',
            'CATALOG_QUANTITY',
            'IBLOCK_SECTION_ID',
            'DETAIL_PAGE_URL',
            'CATALOG_AVAILABLE',
        ];
        foreach (self::$recommendUserPriceId as $key => $recommendPriceId) {
            $arSelect[] = 'CATALOG_GROUP_'.$recommendPriceId;
        }

        $arFilter = [
            'IBLOCK_CODE' => self::$iblockCode,
            'ACTIVE' => 'Y',
            'INCLUDE_SUBSECTIONS' => 'Y',
        ];

        if (self::$sectionCode) {
            $arFilter['SECTION_CODE'] = self::$sectionCode;
        }

        $productIds = [];
        $productList = [];

        $rsElements = \CIBlockElement::GetList(
            [
                'NAME' => 'ASC',
            ],
            $arFilter,
            false,
            false,
            $arSelect
        );
        while($element = $rsElements->GetNext())
        {
            $iblockId = $element['IBLOCK_ID'];
            $productIds[] = $element['ID'];
            $quantity = (
                $element['CATALOG_AVAILABLE'] == 'Y'
                && $element['CATALOG_QUANTITY'] == 0
                || $element['CATALOG_AVAILABLE'] != 'Y'
                    ? 'Ожидается поступление' : $element['CATALOG_QUANTITY']
            );

            $productList[$element['ID']] = [
                'ID' => $element['ID'],
                'NAME' => $element['~NAME'],
                'IBLOCK_SECTION_ID' => $element['IBLOCK_SECTION_ID'],
                'BAR_CODE' => '',
                'ARTICLE' => $element['PROPERTY_ARTICLE_VALUE'],
                'DETAIL_PAGE_URL' => 'https://'.SITE_SERVER_NAME.$element['DETAIL_PAGE_URL'],
                'QUANTITY_VALUE' => $quantity,
            ];
            if ($this->arParams['IS_INDIVIDUAL_PRICE'] == 'Y'){
                foreach (self::$recommendUserPriceId as $tradeId){
                    $productList[$element['ID']]['TRADE_PRICE'] = $element['CATALOG_PRICE_'.$tradeId];
                    break;
                }
            }
            else{
                foreach (self::$recommendUserPriceId as $key => $tradeId){
                    if ($key == self::$basePriceCode){
                        $productList[$element['ID']]['BASE_PRICE'] = $element['CATALOG_PRICE_'.$tradeId];
                    }
                    if ($key == self::$tradePriceSmallCode){
                        $productList[$element['ID']]['SMALL_PRICE'] = $element['CATALOG_PRICE_'.$tradeId];
                    }
                    if ($key == self::$tradePriceBigCode){
                        $productList[$element['ID']]['BIG_PRICE'] = $element['CATALOG_PRICE_'.$tradeId];
                    }
                }
            }
        }

        $propertyCml2TraitsID = \CIBlockProperty::GetByID("CML2_TRAITS", false, self::$iblockCode)->Fetch()['ID'];
        $rsPropertyValues = \CIBlockElement::GetPropertyValues($iblockId, ['ID' => $productIds], true, ['ID' => $propertyCml2TraitsID]);
        while ($arPropertyValue = $rsPropertyValues->Fetch()){
            foreach ($arPropertyValue['DESCRIPTION'][$propertyCml2TraitsID] as $key => $value){
                if ($value == 'Штрихкод'){
                    $productList[$arPropertyValue['IBLOCK_ELEMENT_ID']]['BAR_CODE'] = str_replace(',', ','.PHP_EOL, $arPropertyValue[$propertyCml2TraitsID][$key]);
                }
            }
        }

        $arMeasure = \Bitrix\Catalog\ProductTable::getCurrentRatioWithMeasure($productIds);
        foreach ($arMeasure as $key => $value) {
            $productList[$key]['MEASURE'] = $value['MEASURE']['SYMBOL_RUS'];
        }

        $products = [];
        foreach ($productList as $id => $product) {
            $products[$product['IBLOCK_SECTION_ID']][$id] = $product;
        }
        self::$products = $products;
        self::$sections = $arSections;
    }

    private function addSubsections($sectionId, &$arSections)
    {
        $rsSections2 = \CIBlockSection::GetList(
            array('LEFT_MARGIN' => 'asc'),
            array(
                'IBLOCK_CODE' => self::$iblockCode,
                'ACTIVE' => 'Y',
                'GLOBAL_ACTIVE' => 'Y',
                'ELEMENT_SUBSECTIONS' => 'Y',
                'CNT_ACTIVE' => 'Y',
                'SECTION_ID' => $sectionId,
            ),
            true
        );
        while ($arSect = $rsSections2->fetch()) {
            if ($arSect['ELEMENT_CNT'] > 0) {
                $row = array();
                $row["ID"] = $arSect["ID"];
                $row["NAME"] = $arSect["NAME"];
                $row["DEPTH_LEVEL"] = $arSect["DEPTH_LEVEL"];
                $arSections[$arSect["ID"]] = $row;
                $this->addSubsections($arSect["ID"], $arSections);
            }
        }
    }

    private function setRowLevel($active_sheet, $rowNum, $level)
    {
        for ($i = 1; $i <= $level; $i++) {
            $active_sheet->getRowDimension($rowNum)
                ->setOutlineLevel($i - 1)
                ->setVisible(true)
                ->setCollapsed(false);
        }
    }

    /**
     * Формируем Индивидуальный прайс-дист в Excel таблицу
     * @author Craftmoorg
     */
    public function makeIndividualPriceList()
    {
        include_once $_SERVER['DOCUMENT_ROOT'].'/local/assets/php_excel/PHPExcel.php';

        $path = $_SERVER['DOCUMENT_ROOT'].self::$uploadDir;
        $fileName = 'Прайс-лист '. self::$sectionName . '.xls';
        mkdir($path, 0777, true);

        $obExcel = new PHPExcel();

        $style_cell_centered = array(
            'font'=>array(
                'name' => 'Calibri',
                'size' => 10
            ),
            'alignment' => array(
                'horizontal' => PHPExcel_STYLE_ALIGNMENT::HORIZONTAL_LEFT,
                'vertical' => PHPExcel_STYLE_ALIGNMENT::VERTICAL_CENTER,
            ),
            'borders'=>array(
                'outline' => array(
                    'style'=>PHPExcel_Style_Border::BORDER_THIN
                ),
                'allborders'=>array(
                    'style'=>PHPExcel_Style_Border::BORDER_THIN,
                    'color' => array(
                        'rgb'=>'000000'
                    )
                )
            )
        );
        $style_cell_section = array(
            'font'=>array(
                'bold' => true,
                'color' => array('rgb' => 'FFFFFF'),
                'name' => 'Calibri',
                'size' => 10
            ),
            'alignment' => array(
                'horizontal' => PHPExcel_STYLE_ALIGNMENT::HORIZONTAL_LEFT,
                'vertical' => PHPExcel_STYLE_ALIGNMENT::VERTICAL_CENTER,
            ),
            'borders'=>array(
                //внешняя рамка
                'outline' => array(
                    'style'=>PHPExcel_Style_Border::BORDER_THIN
                ),
                'allborders'=>array(
                    'style'=>PHPExcel_Style_Border::BORDER_THIN,
                    'color' => array(
                        'rgb'=>'000000'
                    )
                )
            ),
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => 'D62A32')
            )
        );

        $obExcel->setActiveSheetIndex(0);
        $obExcel->getActiveSheet()->setTitle('Прайс-лист');
        $obExcel->getActiveSheet()->getDefaultRowDimension()->setRowHeight(-1);
        $obExcel->getDefaultStyle()->getFont()->setSize(11)->setName('Calibri');
        $obExcel->getActiveSheet()->getColumnDimension('A')->setWidth(17);
        $obExcel->getActiveSheet()->getColumnDimension('B')->setWidth(17);
        $obExcel->getActiveSheet()->getColumnDimension('C')->setWidth(70);
        $obExcel->getActiveSheet()->getColumnDimension('D')->setWidth(15);
        $obExcel->getActiveSheet()->getColumnDimension('E')->setWidth(15);
        $obExcel->getActiveSheet()->getColumnDimension('F')->setWidth(27);
        $obExcel->getActiveSheet()->getColumnDimension('G')->setWidth(17);
        $obExcel->getActiveSheet()->getRowDimension('1')->setRowHeight(70);
        $obExcel->getActiveSheet()->getStyle('C1:G1')->getFont()->setBold(true);
        $obExcel->getActiveSheet()->getStyle('A2:G2')->getFont()->setBold(true);
        $obExcel->getActiveSheet()->getStyle('A1:G2')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER)->setWrapText(true);
        $obExcel->getActiveSheet()->getStyle('A1:G1')->applyFromArray(
            array(
                'borders' => array(
                    'allborders' => array(
                        'style' => PHPExcel_Style_Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    )
                )
            ));
        $obExcel->getActiveSheet()->getStyle('A2:G2')->applyFromArray(
            array(
                'borders' => array(
                    'allborders' => array(
                        'style' => PHPExcel_Style_Border::BORDER_THIN,
                        'color' => ['rgb' => 'FFFFFF']
                    )
                )
            ));
        $obExcel->getActiveSheet()->getStyle('G2')->applyFromArray(
            array(
                'borders' => array(
                    'right' => array(
                        'style' => PHPExcel_Style_Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    )
                )
            ));
        $obExcel->getActiveSheet()->getStyle('A2:G2')->applyFromArray(
            array(
                'font'  => array(
                    'color' => array('rgb' => 'FFFFFF'),
                ),
                'fill' => array(
                    'type' => PHPExcel_Style_Fill::FILL_SOLID,
                    'color' => array('rgb' => 'D62A32')
                )
            )
        );
        $obExcel->getActiveSheet()->getStyle('B1')->applyFromArray(
            array(
                'font' => array(
                    'color' => array(
                        'rgb' => '0000FF'
                    ),
                    'underline' => 'single'
                )
            )
        );
        $obExcel->getActiveSheet()->freezePane('A3');


        $objDrawing = new PHPExcel_Worksheet_Drawing();
        $objDrawing->setName('DTRD');
        $objDrawing->setDescription('Logo');
        $objDrawing->setPath($_SERVER['DOCUMENT_ROOT'].$this->getPath().'/img/dtrd_logo_2.png');
        $objDrawing->setOffsetX(20);
        $objDrawing->setOffsetY(10);
        $objDrawing->setCoordinates('A1');
        $objDrawing->setWidth(100);
        $objDrawing->setHeight(80);
        $objDrawing->setWorksheet($obExcel->getActiveSheet());  //save

        $obExcel->getActiveSheet()
            ->setCellValue('A2', 'Артикул')
            ->setCellValue('B2', 'EAN')
            ->setCellValue('C2', 'Название')
            ->setCellValue('D2', 'Цена НДС')
            ->setCellValue('E2', 'Единица измерения')
            ->setCellValue('F2', 'Наличие')
            ->setCellValue('G2', 'Переход на сайт')
            ->setCellValue('C1', 'Адрес: Московская область, г.Балашиха, Носовинское шоссе, вл. 253')
            ->mergeCells('D1:E1')
            ->setCellValue('D1', 'Режим работы:'.PHP_EOL.'Пн-Пт 9:00-17:00')
            ->setCellValue('F1', 'Телефон:'.PHP_EOL.'+7 926 701 27 85')
            ->setCellValue('G1', 'EMAIL:'.PHP_EOL.'opt@dtrd.ru')
        ;

        $obExcel->getActiveSheet()->setCellValue('B1', 'https://'.SITE_SERVER_NAME);
        $obExcel->getActiveSheet()->getCell('B1')->getHyperlink()->setUrl('https://'.SITE_SERVER_NAME);


        $rowNum = 3;
        $active_sheet = $obExcel->getActiveSheet();

        $depthLevelDiff = false;
        foreach (self::$sections as $sectionId => $arSection) {
            if ($depthLevelDiff === false) {
                $depthLevelDiff = $arSection['DEPTH_LEVEL'] - 1;
            }
            self::$sections[$sectionId]['DEPTH_LEVEL'] = self::$sections[$sectionId]['DEPTH_LEVEL'] - $depthLevelDiff;
        }

        foreach (self::$sections as $sectionId => $arSection) {
            if ($arSection['DEPTH_LEVEL'] <= 2) {
                $active_sheet->mergeCells('A' . $rowNum . ':G' . $rowNum);
                $active_sheet->setCellValue('A' . $rowNum, str_repeat(" . ", $arSection['DEPTH_LEVEL'] - 1) . $arSection['NAME']);
                $active_sheet->getStyle('A' . $rowNum . ':G' . $rowNum)->applyFromArray($style_cell_section);

                $this->setRowLevel($active_sheet, $rowNum, $arSection['DEPTH_LEVEL']);

                $rowNum++;
            }
            if (self::$products[$arSection['ID']]) {
                foreach (self::$products[$arSection['ID']] as $productId => $arProduct) {
                    $active_sheet->setCellValueExplicit('A' . $rowNum, $arProduct['ARTICLE'], PHPExcel_Cell_DataType::TYPE_STRING);
                    $active_sheet->setCellValueExplicit('B' . $rowNum, $arProduct['BAR_CODE'], PHPExcel_Cell_DataType::TYPE_STRING);
                    $active_sheet->setCellValue('C' . $rowNum,  $arProduct['NAME']);
                    $active_sheet->setCellValue('D' . $rowNum, $arProduct['TRADE_PRICE']);
                    $active_sheet->setCellValue('E' . $rowNum, $arProduct['MEASURE']);
                    $active_sheet->setCellValue('F' . $rowNum, $arProduct['QUANTITY_VALUE']);
                    $active_sheet->setCellValue('G' . $rowNum, 'В позицию на сайте');
                    $active_sheet->getCell('G' . $rowNum)->getHyperlink()->setUrl($arProduct['DETAIL_PAGE_URL']);
                    $this->setRowLevel($active_sheet, $rowNum, ($arSection['DEPTH_LEVEL'] + 1 <= 3 ? $arSection['DEPTH_LEVEL'] + 1 : 3));
                    $active_sheet->getStyle('G' . $rowNum)->applyFromArray(
                        array(
                            'font' => array(
                                'color' => array(
                                    'rgb' => '0000FF'
                                ),
                                'underline' => 'single'
                            )
                        )
                    );
                    $active_sheet->getStyle('A' . $rowNum . ':G' . $rowNum)->applyFromArray($style_cell_centered);

                    $rowNum++;
                }
            }
        }

        $active_sheet->getStyle('A3:F'.$rowNum)->getAlignment()->setWrapText(true);


        $obWriter = PHPExcel_IOFactory::createWriter($obExcel, 'Excel5');

        header('Content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        $obWriter->save('php://output');
    }

    /**
     * Формируем Индивидуальный прайс-лист в Excel таблицу
     * @author Craftmoorg
     */
    public function makeMainPriceList()
    {
        include_once $_SERVER['DOCUMENT_ROOT'].'/local/assets/php_excel/PHPExcel.php';

        $path = $_SERVER['DOCUMENT_ROOT'].self::$uploadDir;
        $fileName = 'Прайс-лист '. self::$sectionName . '.xls';
        mkdir($path, 0777, true);

        $obExcel = new PHPExcel();

        $style_cell_centered = array(
            'font'=>array(
                'name' => 'Calibri',
                'size' => 10
            ),
            'alignment' => array(
                'horizontal' => PHPExcel_STYLE_ALIGNMENT::HORIZONTAL_LEFT,
                'vertical' => PHPExcel_STYLE_ALIGNMENT::VERTICAL_CENTER,
            ),
            'borders'=>array(
                'outline' => array(
                    'style'=>PHPExcel_Style_Border::BORDER_THIN
                ),
                'allborders'=>array(
                    'style'=>PHPExcel_Style_Border::BORDER_THIN,
                    'color' => array(
                        'rgb'=>'000000'
                    )
                )
            )
        );
        $style_cell_section = array(
            'font'=>array(
                'bold' => true,
                'color' => array('rgb' => 'FFFFFF'),
                'name' => 'Calibri',
                'size' => 10
            ),
            'alignment' => array(
                'horizontal' => PHPExcel_STYLE_ALIGNMENT::HORIZONTAL_LEFT,
                'vertical' => PHPExcel_STYLE_ALIGNMENT::VERTICAL_CENTER,
            ),
            'borders'=>array(
                'outline' => array(
                    'style'=>PHPExcel_Style_Border::BORDER_THIN
                ),
                'allborders'=>array(
                    'style'=>PHPExcel_Style_Border::BORDER_THIN,
                    'color' => array(
                        'rgb'=>'000000'
                    )
                )
            ),
            'fill' => array(
                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('rgb' => 'D62A32')
            )
        );

        $obExcel->setActiveSheetIndex(0);
        $obExcel->getActiveSheet()->setTitle('Прайс-лист');
        $obExcel->getActiveSheet()->getDefaultRowDimension()->setRowHeight(-1);
        $obExcel->getDefaultStyle()->getFont()->setSize(11)->setName('Calibri');
        $obExcel->getActiveSheet()->getColumnDimension('A')->setWidth(17);
        $obExcel->getActiveSheet()->getColumnDimension('B')->setWidth(17);
        $obExcel->getActiveSheet()->getColumnDimension('C')->setWidth(70);
        $obExcel->getActiveSheet()->getColumnDimension('D')->setWidth(15);
        $obExcel->getActiveSheet()->getColumnDimension('E')->setWidth(15);
        $obExcel->getActiveSheet()->getColumnDimension('F')->setWidth(27);
        $obExcel->getActiveSheet()->getColumnDimension('G')->setWidth(17);
        $obExcel->getActiveSheet()->getColumnDimension('H')->setWidth(17);
        $obExcel->getActiveSheet()->getColumnDimension('I')->setWidth(16);
        $obExcel->getActiveSheet()->getRowDimension('1')->setRowHeight(70);
        $obExcel->getActiveSheet()->getStyle('C1:I1')->getFont()->setBold(true);
        $obExcel->getActiveSheet()->getStyle('A2:I2')->getFont()->setBold(true);
        $obExcel->getActiveSheet()->getStyle('A1:I2')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER)->setWrapText(true);
        $obExcel->getActiveSheet()->getStyle('A1:I1')->applyFromArray(
            array(
                'borders' => array(
                    'allborders' => array(
                        'style' => PHPExcel_Style_Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    )
                )
            ));
        $obExcel->getActiveSheet()->getStyle('A2:I2')->applyFromArray(
            array(
                'borders' => array(
                    'allborders' => array(
                        'style' => PHPExcel_Style_Border::BORDER_THIN,
                        'color' => ['rgb' => 'FFFFFF']
                    )
                )
            ));
        $obExcel->getActiveSheet()->getStyle('I2')->applyFromArray(
            array(
                'borders' => array(
                    'right' => array(
                        'style' => PHPExcel_Style_Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    )
                )
            ));
        $obExcel->getActiveSheet()->getStyle('A2:I2')->applyFromArray(
            array(
                'font'  => array(
                    'color' => array('rgb' => 'FFFFFF'),
                ),
                'fill' => array(
                    'type' => PHPExcel_Style_Fill::FILL_SOLID,
                    'color' => array('rgb' => 'D62A32')
                )
            )
        );
        $obExcel->getActiveSheet()->getStyle('B1')->applyFromArray(
            array(
                'font' => array(
                    'color' => array(
                        'rgb' => '0000FF'
                    ),
                    'underline' => 'single'
                )
            )
        );
        $obExcel->getActiveSheet()->freezePane('A3');

        $objDrawing = new PHPExcel_Worksheet_Drawing();
        $objDrawing->setName('DTRD');
        $objDrawing->setDescription('Logo');
        $objDrawing->setPath($_SERVER['DOCUMENT_ROOT'].$this->getPath().'/img/dtrd_logo_2.png');
        $objDrawing->setOffsetX(20);
        $objDrawing->setOffsetY(10);
        $objDrawing->setCoordinates('A1');
        $objDrawing->setWidth(100);
        $objDrawing->setHeight(80);
        $objDrawing->setWorksheet($obExcel->getActiveSheet());  //save

        $obExcel->getActiveSheet()
            ->setCellValue('A2', 'Артикул')
            ->setCellValue('B2', 'EAN')
            ->setCellValue('C2', 'Название')
            ->setCellValue('D2', 'Единица измерения')
            ->setCellValue('E2', 'Наличие')
            ->setCellValue('F2', 'Базовый ОПТ'.PHP_EOL.'руб. с НДС'.PHP_EOL.'от 20 до 50 т.')
            ->setCellValue('G2', 'Мелкий ОПТ'.PHP_EOL.'руб. с НДС'.PHP_EOL.'от 50 до 150 т.')
            ->setCellValue('H2', 'Крупный ОПТ'.PHP_EOL.'руб. с НДС'.PHP_EOL.'от 150 до 250 т.')
            ->setCellValue('I2', 'Переход на сайт')
            ->setCellValue('C1', 'Адрес: Московская область, г.Балашиха, Носовинское шоссе, вл. 253')
            ->mergeCells('D1:E1')
            ->setCellValue('D1', 'Режим работы:'.PHP_EOL.'Пн-Пт 9:00-17:00')
            ->mergeCells('F1:G1')
            ->setCellValue('F1', 'Телефон:'.PHP_EOL.'+7 926 701 27 85')
            ->setCellValue('H1', 'EMAIL:'.PHP_EOL.'opt@dtrd.ru')
        ;

        $obExcel->getActiveSheet()->setCellValue('B1', 'https://'.SITE_SERVER_NAME);
        $obExcel->getActiveSheet()->getCell('B1')->getHyperlink()->setUrl('https://'.SITE_SERVER_NAME);


        $rowNum = 3;
        $active_sheet = $obExcel->getActiveSheet();

        $depthLevelDiff = false;
        foreach (self::$sections as $sectionId => $arSection) {
            if ($depthLevelDiff === false) {
                $depthLevelDiff = $arSection['DEPTH_LEVEL'] - 1;
            }
            self::$sections[$sectionId]['DEPTH_LEVEL'] = self::$sections[$sectionId]['DEPTH_LEVEL'] - $depthLevelDiff;
        }

        foreach (self::$sections as $sectionId => $arSection) {
            if ($arSection['DEPTH_LEVEL'] <= 2) {
                $active_sheet->mergeCells('A' . $rowNum . ':I' . $rowNum);
                $active_sheet->setCellValue('A' . $rowNum, str_repeat(" . ", $arSection['DEPTH_LEVEL'] - 1) . $arSection['NAME']);
                $active_sheet->getStyle('A' . $rowNum . ':I' . $rowNum)->applyFromArray($style_cell_section);

                $this->setRowLevel($active_sheet, $rowNum, $arSection['DEPTH_LEVEL']);

                $rowNum++;
            }
            if (self::$products[$arSection['ID']]) {
                foreach (self::$products[$arSection['ID']] as $productId => $arProduct) {
                    $active_sheet->setCellValueExplicit('A' . $rowNum, $arProduct['ARTICLE'], PHPExcel_Cell_DataType::TYPE_STRING);
                    $active_sheet->setCellValueExplicit('B' . $rowNum, $arProduct['BAR_CODE'], PHPExcel_Cell_DataType::TYPE_STRING);
                    $active_sheet->setCellValue('C' . $rowNum,  $arProduct['NAME']);
                    $active_sheet->setCellValue('D' . $rowNum, $arProduct['MEASURE']);
                    $active_sheet->setCellValue('E' . $rowNum, $arProduct['QUANTITY_VALUE']);
                    $active_sheet->setCellValue('F' . $rowNum, $arProduct['BASE_PRICE']);
                    $active_sheet->setCellValue('G' . $rowNum, $arProduct['SMALL_PRICE']);
                    $active_sheet->setCellValue('H' . $rowNum, $arProduct['BIG_PRICE']);
                    $active_sheet->setCellValue('I' . $rowNum, 'В позицию на сайте');
                    $active_sheet->getCell('I' . $rowNum)->getHyperlink()->setUrl($arProduct['DETAIL_PAGE_URL']);
                    $this->setRowLevel($active_sheet, $rowNum, ($arSection['DEPTH_LEVEL'] + 1 <= 3 ? $arSection['DEPTH_LEVEL'] + 1 : 3));
                    $active_sheet->getStyle('I' . $rowNum)->applyFromArray(
                        array(
                            'font' => array(
                                'color' => array(
                                    'rgb' => '0000FF'
                                ),
                                'underline' => 'single'
                            )
                        )
                    );
                    $active_sheet->getStyle('A' . $rowNum . ':I' . $rowNum)->applyFromArray($style_cell_centered);

                    $rowNum++;
                }
            }
        }

        $active_sheet->getStyle('A3:I'.$rowNum)->getAlignment()->setWrapText(true);


        $obWriter = PHPExcel_IOFactory::createWriter($obExcel, 'Excel5');

        header('Content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        $obWriter->save('php://output');
    }

    public function executeComponent()
    {
        if ($this->arParams['IS_AUTHORIZED']) {
            $this->prepareData();
            $this->getProducts();
            if ($this->arParams['IS_INDIVIDUAL_PRICE'] == 'Y'){
                $this->makeIndividualPriceList();
            }
            else{
                $this->makeMainPriceList();
            }
        } else {
            LocalRedirect('/catalog/');
        }
    }

}

