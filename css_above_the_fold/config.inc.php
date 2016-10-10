<?php
/** 
 * Addon: 	by Adrian Kühnis
 * @author      Kollerdirect	
 */


$mypage = 'css_above_the_fold'; // only for this file

$REX['ADDON']['page'][$mypage] = $mypage;
$REX['ADDON']['name'][$mypage] = 'Css above the fold';
$REX['ADDON']['version'][$mypage] = '1.0.0';
$REX['ADDON']['author'][$mypage] = 'Adrian Kühnis, Kollerdirect AG';
$REX['ADDON']['path'][$mypage] = $REX['INCLUDE_PATH'].'/addons/'.$mypage;

include_once __DIR__.'/vendor/Mobile_Detect/Mobile_Detect.php';
if ('84.74.56.154' == $_SERVER['REMOTE_ADDR']) {
}
if (!$REX['REDAXO'] ) {
    ini_set('display_errors',1);
    error_reporting(E_ALL ^ E_NOTICE ^ E_STRICT);
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
            //$file = \rex_path::addonCache('cssabove', $device.'_'.$article_id.'_'.$clang_id.'.css');
            //$dir = \rex_path::addonCache('cssabove', '');
            $dir = $REX['INCLUDE_PATH'].'/generated/addons/css_above_the_fold';
            $file = $dir.'/'. $device.'_'.$article_id.'_'.$clang_id.'.css';
            if(!is_dir($dir)){
                mkdir($dir, 0777, true);
            }
            if (!file_exists($file)) {
                file_put_contents($file, stripslashes($_POST['css']));
            }
            die();
        } 
        die();

    }

    rex_register_extension('OUTPUT_FILTER', function($params){
        global $REX;
        $mobile_detect = new Mobile_Detect();
        $device = $mobile_detect->isMobile() ? 'mobile' : 'desktop';
        $article_id = $REX['ARTICLE_ID'];
        $clang_id   = $REX['CUR_CLANG'];
        $css_first_token = cssabove_getRandomString(40);
        $dir = $REX['INCLUDE_PATH'].'/generated/addons/css_above_the_fold';
        $file = $dir.'/'. $device.'_'.$article_id.'_'.$clang_id.'.css';
        //$file = \rex_path::addonCache('cssabove', $device.'_'.$article_id.'_'.$clang_id.'.css');
        if (!file_exists($file)) {
            // JS-Code einbinden um das CSS zu parsen
            $url = rex_getUrl(null, null, array(
                'cssabove_token' => $css_first_token,
                'device' => $device,
                'aid' =>$article_id,
                'clid' => $clang_id,
            ), '&');
            $code = '<script type="text/javascript">';
            $code.= 'var cssabove_url = \''.$url.'\';';
            $code.= 'var css_above_the_fold_device = \''.$device.'\';';
            $code.= '</script>';
            $code.= '<script type="text/javascript" src="/files/addons/css_above_the_fold/js/front.js"></script>';
            @session_start();
            $_SESSION['cssabove_token'] = $css_first_token;
            return str_replace('</head>', $code.'</head>', $params['subject']);
        } else {
            // CSS-First in den head einbinden
            $code = '<style type="text/css">'.
                file_get_contents($file).
                '</style>';
            $content =  str_replace('</head>', $code.'</head>', $params['subject']);
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
    }, REX_EXTENSION_LATE);
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
