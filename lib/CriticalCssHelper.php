<?php
/**
 * CSS Above The Fold AddOn
 * Helper-Klasse mit statischen Hilfsmethoden
 */
class CriticalCssHelper
{
    /**
     * Ermittelt den Viewport basierend auf der Bildschirmbreite
     * Dies ist eine serverseitige Schätzung basierend auf dem User-Agent
     * 
     * @return string Viewport-Bezeichner (z.B. 'xs', 'md', 'xl')
     */
    public static function detectViewport()
    {
        // Standardmäßig Desktop-Viewport annehmen
        $viewport = 'xl';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Einfache Erkennung für Mobilgeräte
        if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $userAgent)) {
            $viewport = 'xs';
        } 
        // Einfache Erkennung für Tablets
        elseif (preg_match('/tablet|ipad|playbook|silk/i', $userAgent)) {
            $viewport = 'md';
        }
        
        return $viewport;
    }

    /**
     * Liefert den Pfad zur Cache-Datei für das Critical CSS
     * 
     * @param string $viewport Der Viewport
     * @param int $article_id Die Artikel-ID
     * @param int $clang_id Die Sprach-ID
     * @return string Pfad zur Cache-Datei
     */
    public static function getCacheFile($viewport, $article_id, $clang_id)
    {
        // Viewport-Namen bereinigen
        $viewport = preg_replace('/[^a-zA-Z0-9_-]/', '', $viewport);
        
        // IDs als Integers sicherstellen
        $article_id = (int) $article_id;
        $clang_id = (int) $clang_id;
        
        return rex_path::addonCache('css_above_the_fold', $viewport . '_' . $article_id . '_' . $clang_id . '.css');
    }

    /**
     * Verarbeitet den Output, wenn eine Cache-Datei existiert.
     * Bindet das gespeicherte Critical CSS inline ein und lädt Original-CSS optional asynchron.
     * 
     * @param string $cacheFile Pfad zur Critical CSS Cache-Datei
     * @param string $content HTML-Content
     * @return string Modifizierter HTML-Content
     */
    public static function processWithInlineCss($cacheFile, $content)
    {
        $addon = rex_addon::get('css_above_the_fold');
        $criticalCssContent = file_get_contents($cacheFile);

        // Leeren Cache-Inhalt prüfen
        if (empty(trim($criticalCssContent))) {
            return $content;
        }
        
        // Critical CSS in den head einbinden
        $headEndTag = '</head>';
        $pos = stripos($content, $headEndTag);
        
        if ($pos !== false) {
            $inlineCssBlock = '<style id="critical-css">' . $criticalCssContent . '</style>';
            $content = substr_replace($content, $inlineCssBlock . $headEndTag, $pos, strlen($headEndTag));
        } else {
            // Fallback: An den Anfang des body anhängen, wenn </head> nicht gefunden wurde
            $bodyStartTag = '<body';
            $posBody = stripos($content, $bodyStartTag);
            
            if ($posBody !== false) {
                // Ende des body-Tags finden
                $bodyTagEndPos = strpos($content, '>', $posBody);
                if ($bodyTagEndPos !== false) {
                    $inlineCssBlock = '<style id="critical-css">' . $criticalCssContent . '</style>';
                    $content = substr_replace($content, $inlineCssBlock, $bodyTagEndPos + 1, 0);
                }
            }
            
            if (CSS_ABOVE_THE_FOLD_DEBUG) {
                rex_logger::factory()->info('CSS Above The Fold: Closing </head> tag nicht gefunden. Critical CSS wurde möglicherweise nicht korrekt eingefügt.');
            }
        }

        // Nur wenn die async-Option aktiviert ist, CSS asynchron laden
        if ($addon->getConfig('load_css_async', true)) {
            // Regex um verlinkte Stylesheets zu finden (verbesserte Robustheit)
            $regex = '/<link(?=[^>]*\srel=[\'"]stylesheet[\'"])(?![^>]*\smedia=[\'"]print[\'"])[^>]*\shref=[\'"]([^\'"]+)[\'"][^>]*>/is';
            
            $asyncCssBlock = '';
            $content = preg_replace_callback($regex, function ($matches) use (&$asyncCssBlock) {
                $cssUrl = $matches[1];
                
                // Code für das asynchrone Laden dieses Stylesheets erstellen
                $asyncCssBlock .= self::getAsyncCssCode($cssUrl);
                
                // Originales <link>-Tag entfernen
                return ''; 
            }, $content);
            
            // Async-CSS-Skript am Ende des Body einfügen
            if (!empty($asyncCssBlock)) {
                $bodyEndTag = '</body>';
                $pos = strripos($content, $bodyEndTag);
                
                if ($pos !== false) {
                    $content = substr_replace($content, $asyncCssBlock . $bodyEndTag, $pos, strlen($bodyEndTag));
                } else {
                    // Fallback wenn </body> nicht gefunden wird
                    $content .= $asyncCssBlock;
                    if (CSS_ABOVE_THE_FOLD_DEBUG) {
                        rex_logger::factory()->info('CSS Above The Fold: Closing </body> tag nicht gefunden. Async CSS wurde ans Ende angehängt.');
                    }
                }
            }
        }
        
        return $content;
    }

    /**
     * Erzeugt den HTML-Code zum asynchronen Laden von CSS
     * 
     * @param string $cssUrl URL zur CSS-Datei
     * @return string HTML-Code für das asynchrone Laden
     */
    public static function getAsyncCssCode($cssUrl)
    {
        // URL für HTML-Attribute richtig kodieren
        $safeCssUrl = htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8');
        
        // Empfohlenes Pattern für asynchrones CSS-Loading verwenden
        return PHP_EOL . '<link rel="preload" href="' . $safeCssUrl . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . PHP_EOL . 
               '<noscript><link rel="stylesheet" href="' . $safeCssUrl . '"></noscript>' . PHP_EOL;
    }

    /**
     * Verarbeitet den Output, wenn keine Cache-Datei existiert.
     * Bindet JavaScript ein, um das Critical CSS zu generieren und zu senden.
     * 
     * @param string $viewport Der aktuelle Viewport
     * @param int $article_id Die Artikel-ID
     * @param int $clang_id Die Sprach-ID
     * @param string $content HTML-Content
     * @return string Modifizierter HTML-Content
     */
    public static function processWithCriticalJs($viewport, $article_id, $clang_id, $content)
    {
        // Sicherheits-Token generieren
        $token = self::generateToken(40);
        $tokenKey = 'token_' . $viewport . '_' . $article_id . '_' . $clang_id;
        
        // Token temporär speichern
        rex_addon::get('css_above_the_fold')->setConfig($tokenKey, $token);
        
        // JavaScript zur Generierung des Critical CSS einbinden
        $criticalJs = self::getCriticalJsCode($viewport, $article_id, $clang_id, $token);
        
        // Vor dem schließenden </body>-Tag einfügen
        $bodyEndTag = '</body>';
        $pos = strripos($content, $bodyEndTag);
        
        if ($pos !== false) {
            return substr_replace($content, $criticalJs . $bodyEndTag, $pos, strlen($bodyEndTag));
        } else {
            // Fallback wenn </body> nicht gefunden wird
            if (CSS_ABOVE_THE_FOLD_DEBUG) {
                rex_logger::factory()->info('CSS Above The Fold: Closing </body> tag nicht gefunden. Critical CSS Generator ans Ende angehängt.');
            }
            return $content . $criticalJs;
        }
    }

    /**
     * Generiert einen zufälligen Token
     * 
     * @param int $length Gewünschte Länge des Hex-Tokens (sollte gerade sein)
     * @return string Der generierte Token
     */
    public static function generateToken($length = 40)
    {
        // Sicherstellen, dass die Länge gerade ist für bin2hex
        $length = max(20, (int)($length / 2) * 2); 
        
        try {
            // Kryptografisch sichere Zufallsbytes verwenden
            return bin2hex(random_bytes($length / 2));
        } catch (\Exception $e) {
            // Fallback für Umgebungen, in denen random_bytes fehlschlagen könnte
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $token = '';
            $max = strlen($chars) - 1;
            
            for ($i = 0; $i < $length; $i++) {
                $token .= $chars[mt_rand(0, $max)];
            }
            
            return $token;
        }
    }

    /**
     * Erzeugt das JavaScript für die Erkennung des Critical CSS
     * 
     * @param string $viewport Der Viewport
     * @param int $article_id Die Artikel-ID
     * @param int $clang_id Die Sprach-ID
     * @param string $token Sicherheits-Token
     * @return string JavaScript-Code
     */
    public static function getCriticalJsCode($viewport, $article_id, $clang_id, $token)
    {
        $addon = rex_addon::get('css_above_the_fold');
        
        // API-URL für das Frontend korrekt generieren
        $apiUrl = rex_url::frontendController(['rex-api-call' => 'css_above_the_fold', 'method' => 'saveCss']);
        
        // Breakpoints abrufen
        $breakpoints = [
            'xs' => $addon->getConfig('breakpoint_xs', 375),
            'sm' => $addon->getConfig('breakpoint_sm', 640),
            'md' => $addon->getConfig('breakpoint_md', 768),
            'lg' => $addon->getConfig('breakpoint_lg', 1024),
            'xl' => $addon->getConfig('breakpoint_xl', 1280),
            'xxl' => $addon->getConfig('breakpoint_xxl', 1536)
        ];
        
        // Selektoren, die immer/nie eingeschlossen werden sollen
        $alwaysIncludeSelectors = $addon->getConfig('always_include_selectors', '');
        $neverIncludeSelectors = $addon->getConfig('never_include_selectors', '');
        
        // CSS-Variablen automatisch einschließen?
        $includeCssVars = $addon->getConfig('include_css_vars', true) ? 'true' : 'false';
        
        // Wichtige Regeln bewahren?
        $preserveImportantRules = $addon->getConfig('preserve_important_rules', true) ? 'true' : 'false';
        
        // Das JavaScript für die Critical CSS-Extraktion abrufen
        $jsFile = rex_path::addon('css_above_the_fold', 'assets/js/critical-extractor.js');
        
        if (!file_exists($jsFile)) {
            if (CSS_ABOVE_THE_FOLD_DEBUG) {
                rex_logger::factory()->error('CSS Above The Fold: JS-Datei nicht gefunden: ' . $jsFile);
            }
            return '<!-- Critical CSS Extractor JS not found -->';
        }
        
        $js = file_get_contents($jsFile);
        
        // Parameter in das JavaScript einsetzen
        $js = str_replace([
            '{{VIEWPORT}}',
            '{{ARTICLE_ID}}',
            '{{CLANG_ID}}',
            '{{TOKEN}}',
            '{{API_URL}}',
            '{{ALWAYS_INCLUDE}}',
            '{{NEVER_INCLUDE}}',
            '{{INCLUDE_CSS_VARS}}',
            '{{PRESERVE_IMPORTANT_RULES}}'
        ], [
            $viewport,
            $article_id,
            $clang_id,
            $token,
            $apiUrl,
            htmlspecialchars($alwaysIncludeSelectors, ENT_QUOTES),
            htmlspecialchars($neverIncludeSelectors, ENT_QUOTES),
            $includeCssVars,
            $preserveImportantRules
        ], $js);
        
        return '<script id="critical-css-generator">' . $js . '</script>';
    }
}
