<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}
$this->setFrameMode(true);
?>

<div class="presentation">
    <?if($arResult['MUSIC_PATH']):?>
    <div class="presentation__container">
        <div class="presentation__content content">
            <div class="presentation__title">Экскурсия по центральному офису</div>
            <label class="presentation__toggle"">
                <input id="btn-play" class="presentation__toggle-checkbox" type="checkbox"">
                <div class="presentation__toggle-switch"></div>
                <span class="presentation__toggle-label">Гимн Ровен</span>
            </label>
        </div>
    </div>

    <script>
        let btn = document.getElementById('btn-play');
        let isPlaying = false;
        let isEnd = false;
        let music = new Audio(<?=json_encode($arResult['MUSIC_PATH'])?>);
        music.preload = 'auto';
        music.addEventListener('ended', function(){
            isPlaying = false;
            isEnd = true;
            btn.click();
        });
        btn.addEventListener('click', playOrPause);
        function playOrPause() {
            if (!isEnd){
                if(isPlaying) {
                    music.pause();
                }
                else{
                    music.play();
                }
                isPlaying = !isPlaying;
            }
            else {
                isEnd = false;
            }
        }
    </script>
    <?endif;?>

    <?if($arResult['PICTURE_PATH']):?>
    <?$this->addExternalJS($templateFolder."/gsap.min.js");?>
    <div class="presentation-panorma__container">
    <div id="panorama"></div>
    </div>

    <script>
        gsap.set('#panorama', {perspective:1000}); //lower number exagerates the 'spheriness'

        let zoom = 1,
            stageH = gsap.getProperty('#panorama', 'height'),
            mouse = {x:0.5, y:0.5}, // not pixels! these track position as a percentage of stage width/height
            pov = { x:0.5, y:0.5, speed:0.03 },
            auto = true;
        const n = 16, //number of divs
            c = document.getElementById('panorama');
        for (let i=0; i<n; i++){
            let b = document.createElement('div');
            b.classList.add('box');
            c.appendChild(b);
            gsap.set(b, {
                left:'50%',
                top:'50%',
                xPercent:-60,
                yPercent:-50,
                color:'#fff',
                z:1300,
                width:235,
                height:1066,
                scaleX:-1, //flip horizontally
                rotationY:-89+i/n*-360+90,
                transformOrigin:String("50% 50% -590%"), //adjust 3rd percentage to remove space between divs
                backgroundImage: 'url(' + <?=json_encode($arResult['PICTURE_PATH'])?> + ')', // Прописываем путь к изображению
                backgroundPosition:i*-235+'px 0px' //offset should match width
            });
        }
        window.onresize = (e)=>{
            stageH = gsap.getProperty('#panorama', 'height');
            gsap.to('.box', {y:0});
        }
        c.onmousemove = (e)=>{
            auto = false;
            gsap.killTweensOf(mouse);
            mouse.x = e.clientX/window.innerWidth;
            mouse.y = e.clientY/window.innerHeight;
        }
        c.onmouseleave = ()=>{ auto=true; }
        c.onclick = (e)=>{
            gsap.to('.box', {duration:0.4, z:[1300,1500,1700][zoom]});
            zoom++;
            if (zoom==3) zoom=0;
        }
        setAutoX();
        function setAutoX(){
            if (auto) gsap.to(mouse, {duration:5, x:gsap.utils.random(0.45,0.55), ease:'sine.in'});
            gsap.delayedCall(gsap.utils.random(3,5), setAutoX);
        }
        setAutoY();
        function setAutoY(){
            if (auto) gsap.to(mouse, {duration:6, y:gsap.utils.random(0,1), ease:'sine.in'});
            gsap.delayedCall(gsap.utils.random(4,6), setAutoY);
        }
        gsap.ticker.add(()=> {
            pov.x += (mouse.x - pov.x) * pov.speed;
            pov.y += (mouse.y - pov.y) * pov.speed;
            gsap.set('.box', {rotationY:(i)=>-89+i/n*-360+180*pov.x, y:(Math.abs(1000-stageH)/2)-(Math.abs(1000-stageH))*pov.y });
        });
    </script>
    <?endif;?>
</div>
