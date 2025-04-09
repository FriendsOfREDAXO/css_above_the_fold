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
    
    // API-URL für das Frontend korrekt generieren - WICHTIG: Keine HTML-Entitäten verwenden
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
    // WICHTIG: Wir verwenden json_encode für korrekte JavaScript-Syntax bei Strings
    $js = str_replace([
        '\'{{VIEWPORT}}\'',
        '{{ARTICLE_ID}}',
        '{{CLANG_ID}}',
        '\'{{TOKEN}}\'',
        '\'{{API_URL}}\'',
        '\'{{ALWAYS_INCLUDE}}\'',
        '\'{{NEVER_INCLUDE}}\'',
        '{{INCLUDE_CSS_VARS}}',
        '{{PRESERVE_IMPORTANT_RULES}}'
    ], [
        json_encode($viewport),
        (int)$article_id,
        (int)$clang_id,
        json_encode($token),
        json_encode($apiUrl),
        json_encode($alwaysIncludeSelectors),
        json_encode($neverIncludeSelectors),
        $includeCssVars,
        $preserveImportantRules
    ], $js);
    
    return '<script id="critical-css-generator">' . $js . '</script>';
}
