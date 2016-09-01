/**

*/
cssfirst_getAjax = function(){
  var xmlHttp = null;
  try {
    // Mozilla, Opera, Safari sowie Internet Explorer (ab v7)
    xmlHttp = new XMLHttpRequest();
  } catch(e) {
    try {
        // MS Internet Explorer (ab v6)
        xmlHttp  = new ActiveXObject("Microsoft.XMLHTTP");
    } catch(e) {
      try {
        // MS Internet Explorer (ab v5)
        xmlHttp  = new ActiveXObject("Msxml2.XMLHTTP");
      } catch(e) {
        xmlHttp  = null;
      }
    }
  }
  return xmlHttp;
}
if ('' != cssabove_url && '' == location.hash) {
    window.addEventListener('load', function(){
        window.setTimeout( function() {
            var sheets = document.styleSheets;
            var css = criticalCSS(sheets);
            var formData = new FormData();
            formData.append("css", css);
            var ajax = cssfirst_getAjax();
            ajax.open('POST', cssabove_url, true);
            ajax.send(formData);
        }, 2000);
        return false;
    } , false);
}

/*
criticalCSS by @scottjehl. Run this on your CSS, get the styles that are applicable in the viewport (critical). The url arg should be any part of the URL of the stylesheets you'd like to parse. So, 'all.css' or '/css/' would both work.
modified to use all sheets and join by ''
*/
function criticalCSS( sheets ){
		var maxTop = window.innerHeight,
		critical = [];

	function aboveFold( rule ){
		if( !rule.selectorText ){
			return false;
		}
		var selectors = rule.selectorText.split(","),
			criticalSelectors = [];
		if( selectors.length ){
			for( var l in selectors ){
				var elem;
				try {
					// webkit is really strict about standard selectors getting passed-in
					elem = document.querySelector( selectors[ l ] );
				}
				catch(e) {}
				if( elem && elem.offsetTop <= maxTop ){
					criticalSelectors.push( selectors[ l ] );
				}
			}
		}
		if( criticalSelectors.length ){
			return criticalSelectors.join(",") + rule.cssText.match( /\{.+/ );
		}
		else {
			return false;
		}
	}

	for( var i=0; i<sheets.length;i++){

            try {
		var sheet = sheets[ i ],
			href = sheet.href,
			rules = sheet.cssRules,
			valid = true;

		if( 1 ){
			for( var j in rules ){
				var media = rules[ j ].media,
					matchingRules = [];
				if( media ){
					var innerRules = rules[ j ].cssRules;
					for( var k in innerRules ){
						var critCSSText = aboveFold( innerRules[ k ] );
						if( critCSSText ){
							matchingRules.push( critCSSText );
						}
					}
					if( matchingRules.length ){
						matchingRules.unshift( "@media " + media.mediaText + "{" );
						matchingRules.push( "}" );
					}

				}
				else if( !media ){
					var critCSSText = aboveFold( rules[ j ] );
					if( critCSSText ){
						matchingRules.push( critCSSText );
					}
				}
				critical.push( matchingRules.join( "" ) );
			}

		}
            }catch(e){
            }
	}
	return critical.join( "" );
}

