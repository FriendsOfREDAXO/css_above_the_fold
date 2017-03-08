<?php

/**
 * This file is part of the CssAboveTheFold package.
 *
 * @author Friends Of REDAXO
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace FriendsOfRedaxo\CssAboveTheFold;


class CssAboveTheFold
{
    protected static $content;
    public static    $inline;

    public static function ext__output_filter($ep)
    {
        self::$content = $ep->getSubject();

        $mobile_detect = new \Mobile_Detect();
        $clang_id      = \rex_clang::getCurrentId();
        $article_id    = \rex_article::getCurrentId();
        $device        = $mobile_detect->isMobile() ? 'mobile' : 'desktop';
        $filename      = $device . '_' . $article_id . '_' . $clang_id . '.css';
        $file          = \rex_path::addonCache('cssabove', $filename);

        return file_exists($file) ? self::getInlineCss($file) : self::getCriticalJS($device, $article_id, $clang_id);
    }

    protected static function getInlineCss($file)
    {
        // CSS-First in den head einbinden
        self::$inline = '<style type="text/css">' . file_get_contents($file) . '</style>';
        $content      = str_replace('</head>', self::$inline . '</head>', self::$content);

        // Stylesheets asynchron machen
        $regex   = '/<link[^>]*rel="stylesheet"[^>]*href="([^"]+)"[^>]*>/smix';
        $content = preg_replace_callback($regex, function ($matches) {
            CssAboveTheFold::$inline .= '
                <noscript><link rel="stylesheet" type="text/css" href="'. $matches[1] .'" /></noscript>
                <script type="text/javascript">'. strtr(\rex_file::getOutput(__DIR__ .'/../assets/js/inline.js'), ['%CSS_URL%' => $matches[1]]) .'</script>
            ';
            return '';
        }, $content);
        return str_replace('</body>', self::$inline . '</body>', $content);
    }

    protected static function getCriticalJS($device, $article_id, $clang_id)
    {
        $css_first_token = self::getRandomString(40);

        \rex::setConfig('cssabove', ['token' => $css_first_token]);

        // JS-Code einbinden um das CSS zu parsen
        self::$inline = '<script>var cssabove = ' . json_encode([
                'data' => [
                    'device'  => $device,
                    'aid'     => $article_id,
                    'lang_id' => $clang_id,
                    'token'   => $css_first_token,
                ],
                'url'  => \rex_url::frontendController() . '?' . html_entity_decode(http_build_query([
                    'rex-api-call' => 'cssabove_api',
                    'method'       => 'saveCss',
                ])),
            ]) . ';</script><script async type="text/javascript" src="' . \rex_url::addonAssets('css_above_the_fold', 'js/front.js?mtime=' . filemtime(\rex_path::addonAssets('css_above_the_fold', 'js/front.js'))) . '"></script>';
        return str_replace('</body>', self::$inline . '</body>', self::$content);
    }

    protected static function getRandomString($intLength = 100)
    {
        $chars     = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $intMin    = 0;
        $intMax    = strlen($chars) - 1;
        $strReturn = '';
        for ($i = 0; $i < $intLength; $i++) {
            $strReturn .= $chars[rand($intMin, $intMax)];
        }
        return $strReturn;
    }
}