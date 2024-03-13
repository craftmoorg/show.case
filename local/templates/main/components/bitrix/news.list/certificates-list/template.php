<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}
$this->setFrameMode(true);
?>

<?if(!empty($arResult['ITEMS'])):?>
<div class="certificates-list">
    <?if(!empty($arResult['TABS'])):?>
        <div class="certificates-list__tabs-container">
            <?foreach ($arResult['TABS'] as $tab):?>
                <div class="certificates-list__tab" data-type-id="<?=str_replace('.', '-', $tab)?>"><?=$tab?></div>
            <?endforeach;?>
        </div>
    <?endif;?>
    <div class="certificates-list__container">
        <?foreach ($arResult['ITEMS'] as $certifitace):?>
            <div class="certificates-list__element cert-element-<?=str_replace('.', '-', $certifitace['PROPERTIES']['CERT_INSTITUTE']['VALUE'])?>">
                <a class="certificates-list__download-btn" 
                   href="<?=$certifitace['PROPERTIES']['CERT_FILE']['SRC']?>"
                   download="<?=$certifitace['PROPERTIES']['CERT_FILE']['ORIGINAL_NAME']?>"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="20px" height="20px" viewBox="0 0 24 24" fill="none">
                        <path d="M8 11L12 15M12 15L16 11M12 15V3M7 4.51555C4.58803 6.13007 3 8.87958 3 12C3 16.9706 7.02944 21 12 21C16.9706 21 21 16.9706 21 12C21 8.87958 19.412 6.13007 17 4.51555" stroke="var(--main-text-color-v4)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
                <div class="certificates-list__element-title"><?=$certifitace['NAME']?></div>
                <a class="cert-fancybox"
                   href="<?=$certifitace['PREVIEW_PICTURE']['SRC']?>"
                   data-fancybox="gallery-<?=$certifitace['PROPERTIES']['CERT_INSTITUTE']['VALUE']?>"
                   data-caption="<?=$certifitace['NAME']?>"
                   data-height="1100"
                >
                    <div class="certificates-list__img-wrapper">
                        <img src="<?=$certifitace['PREVIEW_PICTURE']['SRC']?>" alt="<?=$certifitace['NAME']?>">
                    </div>
                </a>
                <?if(!empty($certifitace['PROPERTIES']['CERT_LINK']['VALUE'])):?>
                    <div class="certificates-list__link-wrapper">
                        <a href="<?=$certifitace['PROPERTIES']['CERT_LINK']['VALUE']?>" target="_blank">проверить на <?=$certifitace['PROPERTIES']['CERT_INSTITUTE']['VALUE']?></a>
                    </div>
                <?endif;?>
            </div>
        <?endforeach;?>
    </div>
</div>

    <script>
        $(document).ready(function (){
            $('.certificates-list__tab').click(function (){
                $(this).toggleClass('active');
                if ($('.certificates-list__tab.active').length > 0){
                    $('.certificates-list__container').addClass('filter');
                }
                else{
                    $('.certificates-list__container').removeClass('filter');
                }
                $('.cert-element-' + $(this).attr('data-type-id')).toggleClass('active');
            });
        });

        Fancybox.bind('.cert-fancybox', {
            Toolbar: {
                display: {
                    left: [
                        "zoomIn",
                        "zoomOut",
                    ],
                    middle: [
                        "prev",
                        "infobar",
                        "next"
                    ],
                    right: [
                        "slideshow",
                        "download",
                        "close",
                    ],
                }
            }
        });
    </script>
<?endif;?>