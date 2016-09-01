<?php 
include_once __DIR__.'/vendor/Mobile_Detect/Mobile_Detect.php';
if (!rex::isBackend()) {
    if (
        isset($_GET['clid'])
        && isset($_GET['aid'])
        && isset($_GET['device'])
        && isset($_GET['cssabove_token'])
        && isset($_POST['css'])
    ){
        @session_start();
        if (isset($_SESSION['cssabove_token']) && 
            $_SESSION['cssabove_token'] == $_GET['cssabove_token']
        ){
            if (in_array($_GET['device'], array('mobile', 'desktop'))){
                $device = $_GET['device'];
            } else {
                $device = 'fraud'.date('YmdHis');
            }
            $clang_id = intval($_GET['clid']);
            $article_id = intval($_GET['aid']);
            $file = \rex_path::addonCache('cssabove', $device.'_'.$article_id.'_'.$clang_id.'.css');
            $dir = \rex_path::addonCache('cssabove', '');
            if(!is_dir($dir)){
                mkdir($dir, 0777, true);
            }
            if (!file_exists($file)) {
                file_put_contents($file, $_POST['css']);
            }
            die();
        } 
        die();

    }
    rex_extension::register('OUTPUT_FILTER', function(rex_extension_point $ep){
        $mobile_detect = new Mobile_Detect();
        $device = $mobile_detect->isMobile() ? 'mobile' : 'desktop';
        $article_id = rex_article::getCurrentId();
        $clang_id   = rex_clang::getCurrentId();
        $css_first_token = cssabove_getRandomString(40);

        $file = \rex_path::addonCache('cssabove', $device.'_'.$article_id.'_'.$clang_id.'.css');
        if (!file_exists($file)) {
            // JS-Code einbinden um das CSS zu parsen
            $url = rex_getUrl(null, null, array(
                'cssabove_token' => $css_first_token,
                'device' => $device,
                'aid' =>$article_id,
                'clid' => $clang_id,
            ), '&');
            $code = '<script type="text/javascript">var cssabove_url = \''.$url.'\';</script>';
            $code.= '<script type="text/javascript" src="'.rex_url::assets('addons/css_above_the_fold/js/front.js').'"></script>';
            @session_start();
            $_SESSION['cssabove_token'] = $css_first_token;
            return str_replace('</head>', $code.'</head>', $ep->getSubject());
        } else {
            // CSS-First in den head einbinden
            $code = '<style type="text/css">'.
                file_get_contents($file).
                '</style>';
            $content = str_replace('</head>', $code.'</head>', $ep->getSubject());
            // Verschiebe alle Stylesheets nach dem </html> Tag
            global $css_sammlung;
            $css_sammlung = array();
            $regex = '/<link[^>]*rel="stylesheet"[^>]*href="[^"]+"[^>]*>/smix';
            $content = preg_replace_callback($regex, function($matches) {
                global $css_sammlung;
                $css_sammlung[] = $matches[0];
                return '';
            }, $content);
            $content = str_replace('</html>', '</html>'.implode('',$css_sammlung), $content);
            return $content;
        }
    });
}


function cssabove_getRandomString($intLength=100) { 
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $intMin = 0;
    $intMax = strlen($chars)-1;
    $strReturn = '';
    for ($i=0;$i<$intLength;$i++) {
         $strReturn .= $chars[rand($intMin,$intMax)];
    }
    return $strReturn;
}


