<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}
$this->setFrameMode(true);
?>

<?if(!empty($arResult['ITEMS'])):?>
<div class="main">
    <div class="main__container">
        <h2 class="content-block__main-title"><span>Сертификаты</span><a href="<?=$arResult['LIST_PAGE_URL']?>">Смотреть все</a></h2>
        <div class="certificates">
            <div class="certificates__container">
                <div class="swiper">
                    <div class="swiper-wrapper">
                        <?foreach ($arResult['ITEMS'] as $certifitace):?>
                            <div class="swiper-slide">
                                <div class="certificates__element">
                                    <a class="certificates__download-btn"
                                       href="<?=$certifitace['PROPERTIES']['CERT_FILE']['SRC']?>"
                                       download="<?=$certifitace['PROPERTIES']['CERT_FILE']['ORIGINAL_NAME']?>"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20px" height="20px" viewBox="0 0 24 24" fill="none">
                                            <path d="M8 11L12 15M12 15L16 11M12 15V3M7 4.51555C4.58803 6.13007 3 8.87958 3 12C3 16.9706 7.02944 21 12 21C16.9706 21 21 16.9706 21 12C21 8.87958 19.412 6.13007 17 4.51555" stroke="var(--main-text-color-v4)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </a>
                                    <div class="certificates__element-title"><?=$certifitace['NAME']?></div>
                                    <a href="#">
                                        <div class="certificates__img-wrapper">
                                            <img src="<?=$certifitace['PREVIEW_PICTURE']['SRC']?>" alt="<?=$certifitace['NAME']?>">
                                        </div>
                                    </a>
                                    <?if(!empty($certifitace['PROPERTIES']['CERT_LINK']['VALUE'])):?>
                                        <div class="certificates__link-wrapper">
                                            <a href="<?=$certifitace['PROPERTIES']['CERT_LINK']['VALUE']?>" target="_blank">проверить на <?=$certifitace['PROPERTIES']['CERT_INSTITUTE']['VALUE']?></a>
                                        </div>
                                    <?endif;?>
                                </div>
                            </div>
                        <?endforeach;?>
                    </div>
                </div>
                <div class="swiper-sub-wrapper">
                    <div class="swiper-pagination"></div>
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-button-next"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const swiper = new Swiper('.swiper', {
        direction: 'horizontal',
        loop: true,
        autoplay: {
            delay: 5000,
        },
        speed: 1000,
        slidesPerView: 3,
        slidesPerGroup: 3,
        spaceBetween: 40,
        breakpoints: {
            0: {
                slidesPerView: 1,
                slidesPerGroup: 1,
                speed: 400,
            },
            800: {
                slidesPerView: 2,
                slidesPerGroup: 2,
                spaceBetween: 20,
                speed: 600,
            },
            1025: {
                slidesPerView: 3,
                slidesPerGroup: 3,
                speed: 1000,
            }
        },
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
            dynamicBullets: true
        },
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },
    });
</script>
<?endif;?>