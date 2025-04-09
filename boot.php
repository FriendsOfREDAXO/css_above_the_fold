<?php
/**
 * CSS Above The Fold AddOn
 * Boot-Datei wird beim REDAXO-Start geladen
 */

// Debug-Konstante für Entwicklung definieren
if (!defined('CSS_ABOVE_THE_FOLD_DEBUG')) {
    define('CSS_ABOVE_THE_FOLD_DEBUG', $this->getConfig('debug', false));
}

// Wenn es eine Cache-Warming-Anfrage ist, den Viewport erzwingen
if (rex::isFrontend() && rex_request('cache_warm', 'int', 0) === 1) {
    $token = rex_request('token', 'string', '');
    $expectedToken = $this->getConfig('cache_warm_token', '');
    
    // Sicherheits-Check: Nur mit gültigem Token
    if (!empty($token) && $token === $expectedToken) {
        // Viewport aus der Anfrage nehmen
        $viewport = rex_request('viewport', 'string', '');
        
        if (!empty($viewport)) {
            // Session-Variable setzen für den erzwungenen Viewport
            rex_set_session('forced_viewport', $viewport);
            
            // Debug-Information
            if (CSS_ABOVE_THE_FOLD_DEBUG) {
                rex_logger::factory()->info('CSS Above The Fold: Cache-Warming für Viewport ' . $viewport);
            }
        }
    }
}

// Output Filter nur im Frontend und nicht im Debug-Modus registrieren
if (rex::isFrontend() && !rex::isDebugMode()) {
    rex_extension::register('OUTPUT_FILTER', function(rex_extension_point $ep) {
        // Prüfen, ob das AddOn aktiviert ist
        if (!$this->getConfig('active', true)) {
            return $ep->getSubject();
        }
        
        $content = $ep->getSubject();
        
        // AJAX-Requests ausschließen
        if (rex_request::isXmlHttpRequest()) {
            return $content;
        }
        
        // Aktuelle Artikel- und Sprach-ID ermitteln
        $article_id = rex_article::getCurrentId();
        $clang_id = rex_clang::getCurrentId();
        
        // Wenn keine gültige Artikel- oder Sprachkontext vorhanden
        if ($article_id <= 0 || $clang_id <= 0) {
            if (CSS_ABOVE_THE_FOLD_DEBUG) {
                rex_logger::factory()->info('CSS Above The Fold: Konnte Artikel oder Sprache nicht ermitteln.');
            }
            return $content;
        }
        
        // Helper-Klasse verwenden
        try {
            // Viewport ermitteln (serverseitige Schätzung oder aus Session für Cache-Warming)
            $forcedViewport = rex_session('forced_viewport', 'string', '');
            $viewport = !empty($forcedViewport) ? $forcedViewport : CriticalCssHelper::detectViewport();
            
            // Bei Cache-Warming die Session-Variable zurücksetzen
            if (!empty($forcedViewport)) {
                rex_set_session('forced_viewport', '');
            }
            
            // Cache-Datei für das aktuelle Critical CSS
            $cacheFile = CriticalCssHelper::getCacheFile($viewport, $article_id, $clang_id);
            
            // Wenn Cache existiert, lesbar ist und Inhalt hat -> CSS inline einbinden
            if (is_readable($cacheFile) && filesize($cacheFile) > 0) {
                return CriticalCssHelper::processWithInlineCss($cacheFile, $content);
            } 
            // Sonst -> JavaScript zur Generierung einbinden
            else {
                return CriticalCssHelper::processWithCriticalJs($viewport, $article_id, $clang_id, $content);
            }
        } catch (Exception $e) {
            if (CSS_ABOVE_THE_FOLD_DEBUG) {
                rex_logger::factory()->error('CSS Above The Fold: Fehler: ' . $e->getMessage());
            }
            return $content;
        }
    }, rex_extension::LATE);
}
