(function () {
    var counter = 0,
        catfHandle = window.setInterval(function () {
            counter++;
            if (typeof jQuery != 'undefined') {
                window.clearInterval(catfHandle);

                (function ($) {
                    if (typeof cssabove != 'undefined') {
                        $(document).ready(function () {
                            if (location.hash == '') {
                                var sheets = document.styleSheets;
                                criticalCSS(sheets, cssabove.data);

                                $.ajax(cssabove.url, {
                                    method: 'POST',
                                    dataType: 'json',
                                    data: cssabove.data
                                });
                            }
                        });

                        /*
                         criticalCSS by @scottjehl. Run this on your CSS, get the styles that are applicable in the viewport (critical). The url arg should be any part of the URL of the stylesheets you'd like to parse. So, 'all.css' or '/css/' would both work.
                         modified to use all sheets and join by ''
                         */
                        function criticalCSS(sheets, data) {
                            var maxTop = data.maxTop || data.device == 'mobile' ? 768 : 1200;
                            var critical = [];

                            function aboveFold(rule) {
                                var result = '';
                                if (rule.selectorText) {
                                    var selectors = rule.selectorText.split(","),
                                        criticalSelectors = [];
                                    if (selectors.length) {
                                        for (var l in selectors) {
                                            var selector = selectors[l].replace('::before', '').replace('::after', '').replace(':before', '').replace(':after', ''),
                                                elem = $(selector);

                                            if (elem.length && elem.offset().top <= maxTop) {
                                                criticalSelectors.push(selectors[l]);
                                            }
                                        }
                                    }
                                    if (criticalSelectors.length) {
                                        result = criticalSelectors.join(",") + rule.cssText.match(/\{.+/);
                                    }
                                }
                                return result;
                            }

                            for (var i = 0; i < sheets.length; i++) {
                                try {
                                    var sheet = sheets[i],
                                        rules = sheet.cssRules;

                                    for (var j in rules) {
                                        var media = rules[j].media,
                                            matchingRules = [];
                                        if (media) {
                                            var innerRules = rules[j].cssRules;
                                            for (var k in innerRules) {
                                                matchingRules.push(aboveFold(innerRules[k]));
                                            }
                                            if (matchingRules.length) {
                                                matchingRules.unshift("@media " + media.mediaText + "{");
                                                matchingRules.push("}");
                                            }
                                        }
                                        else if (!media) {
                                            matchingRules.push(aboveFold(rules[j]));
                                        }
                                        critical.push(matchingRules.join(''));
                                    }
                                } catch (e) {
                                }
                            }
                            data.css = critical.join('');
                        }
                    }
                })(jQuery);
            }
            else if (counter >= 15) {
                console.warn('jQuery not found! It is required in order to work correctly');
                window.clearInterval(catfHandle);
            }
        }, 200);
})();