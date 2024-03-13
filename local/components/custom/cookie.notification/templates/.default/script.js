$(document).ready(function () {
    function checkCookies(){
        let cookieDate = localStorage.getItem('rowenCookieDate');
        let cookieNotification = document.querySelector('.cookie-notification__container');
        let cookieBtn = document.getElementById('cookie-notification__accept');

        if(!cookieDate || (+cookieDate + 31536000000) < Date.now()){
            cookieNotification.style.opacity = '1';
            cookieNotification.style.transform = 'translateX(-50%) scaleY(1)';
        }
        else{
            cookieNotification.style.display = 'none';
        }

        cookieBtn.addEventListener('click', function(){
            localStorage.setItem( 'rowenCookieDate', Date.now() );
            cookieNotification.style.transition = '200ms ease';
            cookieNotification.style.transform = 'translateX(-50%) scaleY(0)';
            setTimeout(function () {cookieNotification.style.display = 'none';}, 300);
        })
    }
    checkCookies();
});