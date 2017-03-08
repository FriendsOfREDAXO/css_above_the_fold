(function () {
    var counter = 0,
        catfHandle = window.setInterval(function () {
            counter++;
            if (typeof jQuery != 'undefined') {
                window.clearInterval(catfHandle);

                (function ($) {
                    var css = document.createElement("link");
                    css.rel = "stylesheet";
                    css.href = "%CSS_URL%?bbb=1";
                    css.type = "text/css";
                    $('body').append(css);
                })(jQuery);
            }
            else if (counter >= 15) {
                console.warn('jQuery not found! It is required in order to work correctly');
                window.clearInterval(catfHandle);
            }
        }, 200);
})();