<?php

namespace FriendsOfRedaxo\CssAboveTheFold;

/**
 * CSS Above The Fold Hauptklasse
 * 
 * Verwaltet das Critical CSS für den sichtbaren Bereich einer Webseite
 */
class CssAboveTheFold
{
    /** @var string Enthält den HTML-Content der Seite */
    protected static string $content = '';
    
    /** @var string Enthält das Inline-CSS für den sichtbaren Bereich */
    protected static string $inlineCssBlock = '';
    
    /** @var string Enthält das Skript zum asynchronen Laden des restlichen CSS */
    protected static string $asyncCssBlock = '';
    
    /** @var array Cache für Breakpoint-Konfiguration */
    protected static array $breakpointsCache = [];

    /**
     * Verarbeitet den Output der Seite und fügt das Critical CSS ein
     * 
     * @param \rex_extension_point $ep Der REDAXO Extension Point
     * @return string Modifizierter HTML-Output
     */
    public static function outputFilter(\rex_extension_point $ep): string
    {
        // AddOn-Instanz holen
        $addon = \rex_addon::get('css_above_the_fold');
        
        // Prüfen, ob das AddOn aktiviert ist - redundant aber sicherer
        if (!$addon->getConfig('active', true)) {
            return $ep->getSubject();
        }

        // AJAX-Requests ausschließen
        if (\rex_request::isXmlHttpRequest()) {
            return $ep->getSubject();
        }
        
        self::$content = $ep->getSubject();
        
        // Statische Variablen für die aktuelle Anfrage zurücksetzen
        self::$inlineCssBlock = '';
        self::$asyncCssBlock = '';

        // Aktuelle Artikel- und Sprach-ID ermitteln
        $article_id = \rex_article::getCurrentId();
        $clang_id = \rex_clang::getCurrentId();

        // Wenn keine gültige Artikel- oder Sprachkontext vorhanden
        if ($article_id <= 0 || $clang_id <= 0) {
            self::logDebug('Konnte Artikel oder Sprache nicht ermitteln.');
            return self::$content; 
        }
        
        // Viewport ermitteln (serverseitige Schätzung)
        $viewport = self::detectViewport();
        
        // Cache-Datei für das aktuelle Critical CSS
        $cacheFile = self::getCacheFile($viewport, $article_id, $clang_id);
        
        // Wenn Cache existiert, lesbar ist und Inhalt hat -> CSS inline einbinden
        if (is_readable($cacheFile) && filesize($cacheFile) > 0) {
            return self::processWithInlineCss($cacheFile);
        } 
        // Sonst -> JavaScript zur Generierung einbinden
        else {
            return self::processWithCriticalJs($viewport, $article_id, $clang_id);
        }
    }
    
    /**
     * Verarbeitet den Output, wenn eine Cache-Datei existiert.
     * Bindet das gespeicherte Critical CSS inline ein und lädt Original-CSS optional asynchron.
     * 
     * @param string $cacheFile Pfad zur Critical CSS Cache-Datei
     * @return string Modifizierter HTML-Content
     */
    protected static function processWithInlineCss(string $cacheFile): string
    {
        $addon = \rex_addon::get('css_above_the_fold');
        $criticalCssContent = file_get_contents($cacheFile);

        // Leeren Cache-Inhalt prüfen - sollte nicht vorkommen wegen filesize-Check
        if (empty(trim($criticalCssContent))) {
            return self::$content;
        }
        
        // Critical CSS in den head einbinden
        // Sicherstellen, dass der Ersatz nur einmal erfolgt und unabhängig von Groß-/Kleinschreibung ist
        $headEndTag = '</head>';
        $pos = stripos(self::$content, $headEndTag);
        
        if ($pos !== false) {
            self::$inlineCssBlock = '<style id="critical-css">' . $criticalCssContent . '</style>';
            $content = substr_replace(self::$content, self::$inlineCssBlock . $headEndTag, $pos, strlen($headEndTag));
        } else {
            // Fallback: An den Anfang des body anhängen, wenn </head> nicht gefunden wurde
            $bodyStartTag = '<body';
            $posBody = stripos(self::$content, $bodyStartTag);
            
            if ($posBody !== false) {
                // Ende des body-Tags finden
                $bodyTagEndPos = strpos(self::$content, '>', $posBody);
                if ($bodyTagEndPos !== false) {
                    self::$inlineCssBlock = '<style id="critical-css">' . $criticalCssContent . '</style>';
                    $content = substr_replace(self::$content, self::$inlineCssBlock, $bodyTagEndPos + 1, 0);
                } else {
                    $content = self::$content; // Konnte nicht injizieren
                }
            } else {
                $content = self::$content; // Konnte nicht injizieren
            }
            
            self::logDebug('Closing </head> tag nicht gefunden. Critical CSS wurde möglicherweise nicht korrekt eingefügt.');
        }

        // Nur wenn die async-Option aktiviert ist, CSS asynchron laden
        if ($addon->getConfig('load_css_async', true)) {
            // Regex um verlinkte Stylesheets zu finden (verbesserte Robustheit)
            // Berücksichtigt verschiedene Attributreihenfolgen und Anführungszeichen, ignoriert Links mit media="print"
            $regex = '/<link(?=[^>]*\srel=[\'"]stylesheet[\'"])(?![^>]*\smedia=[\'"]print[\'"])[^>]*\shref=[\'"]([^\'"]+)[\'"][^>]*>/is';
            
            $content = preg_replace_callback($regex, function ($matches) {
                $cssUrl = $matches[1];
                
                // Code für das asynchrone Laden dieses Stylesheets erstellen
                CssAboveTheFold::$asyncCssBlock .= self::getAsyncCssCode($cssUrl);
                
                // Originales <link>-Tag entfernen
                return ''; 
            }, $content);
            
            // Async-CSS-Skript am Ende des Body einfügen
            if (!empty(self::$asyncCssBlock)) {
                $bodyEndTag = '</body>';
                $pos = strripos($content, $bodyEndTag); // Letztes Vorkommen finden, unabhängig von Groß-/Kleinschreibung
                
                if ($pos !== false) {
                    $content = substr_replace($content, self::$asyncCssBlock . $bodyEndTag, $pos, strlen($bodyEndTag));
                } else {
                    // Fallback wenn </body> nicht gefunden wird
                    $content .= self::$asyncCssBlock;
                    self::logDebug('Closing </body> tag nicht gefunden. Async CSS wurde ans Ende angehängt.');
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
    protected static function getAsyncCssCode(string $cssUrl): string
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
     * @return string Modifizierter HTML-Content
     */
    protected static function processWithCriticalJs(string $viewport, int $article_id, int $clang_id): string
    {
        // Sicherheits-Token generieren
        $token = self::generateToken(40);
        $tokenKey = 'token_' . $viewport . '_' . $article_id . '_' . $clang_id;
        
        // Token temporär speichern
        \rex_addon::get('css_above_the_fold')->setConfig($tokenKey, $token);
        
        // JavaScript zur Generierung des Critical CSS einbinden
        $criticalJs = self::getCriticalJsCode($viewport, $article_id, $clang_id, $token);
        
        // Vor dem schließenden </body>-Tag einfügen
        $bodyEndTag = '</body>';
        $pos = strripos(self::$content, $bodyEndTag); // Letztes Vorkommen finden, unabhängig von Groß-/Kleinschreibung
        
        if ($pos !== false) {
            return substr_replace(self::$content, $criticalJs . $bodyEndTag, $pos, strlen($bodyEndTag));
        } else {
            // Fallback wenn </body> nicht gefunden wird
            self::logDebug('Closing </body> tag nicht gefunden. Critical CSS Generator ans Ende angehängt.');
            return self::$content . $criticalJs;
        }
    }
    
    /**
     * Generiert einen zufälligen Token
     * 
     * @param int $length Gewünschte Länge des Hex-Tokens (sollte gerade sein)
     * @return string Der generierte Token
     */
    protected static function generateToken(int $length = 40): string
    {
        // Sicherstellen, dass die Länge gerade ist für bin2hex
        $length = max(20, (int)($length / 2) * 2); 
        
        try {
            // Kryptografisch sichere Zufallsbytes verwenden
            return bin2hex(random_bytes($length / 2));
        } catch (\Exception $e) {
            // Fallback für Umgebungen, in denen random_bytes fehlschlagen oder nicht verfügbar ist
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
     * Ermittelt den Viewport basierend auf der Bildschirmbreite
     * Dies ist eine serverseitige Schätzung basierend auf dem User-Agent
     * 
     * @return string Viewport-Bezeichner (z.B. 'xs', 'md', 'xl')
     */
    protected static function detectViewport(): string
    {
        // Standardmäßig Desktop-Viewport annehmen
        $viewport = 'xl';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Einfache Erkennung für Mobilgeräte (kann ungenau sein)
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
     * Liefert die Breakpoint-Konfiguration aus dem AddOn
     * 
     * @return array Breakpoint-Konfiguration
     */
    public static function getBreakpoints(): array
    {
        if (empty(self::$breakpointsCache)) {
            $addon = \rex_addon::get('css_above_the_fold');
            self::$breakpointsCache = $addon->getConfig('breakpoints', [
                'xs' => 375,
                'sm' => 640,
                'md' => 768,
                'lg' => 1024,
                'xl' => 1280,
                'xxl' => 1536
            ]);
        }
        
        return self::$breakpointsCache;
    }
    
    /**
     * Liefert den Pfad zur Cache-Datei für das Critical CSS
     * 
     * @param string $viewport Der Viewport
     * @param int $article_id Die Artikel-ID
     * @param int $clang_id Die Sprach-ID
     * @return string Pfad zur Cache-Datei
     */
    public static function getCacheFile(string $viewport, int $article_id, int $clang_id): string
    {
        // Viewport-Namen bereinigen, um Directory Traversal oder ungültige Zeichen zu verhindern
        $viewport = preg_replace('/[^a-zA-Z0-9_-]/', '', $viewport);
        
        // IDs als Integers sicherstellen
        $article_id = (int) $article_id;
        $clang_id = (int) $clang_id;
        
        return \rex_path::addonCache('css_above_the_fold', $viewport . '_' . $article_id . '_' . $clang_id . '.css');
    }
    
    /**
     * Liste aller verfügbaren Cache-Dateien zurückgeben
     * 
     * @return array Liste mit Informationen zu allen Cache-Dateien
     */
    public static function getCacheFiles(): array
    {
        $cacheDir = \rex_path::addonCache('css_above_the_fold');
        $files = glob($cacheDir . '*.css');
        $result = [];
        
        if (is_array($files)) {
            foreach ($files as $file) {
                $filename = basename($file);
                $parts = explode('_', $filename);
                
                $viewport = $parts[0] ?? '';
                $article_id = isset($parts[1]) ? (int)$parts[1] : 0;
                $clang_id = isset($parts[2]) ? (int)str_replace('.css', '', $parts[2]) : 0;
                
                // Artikel- und Sprachinformationen abrufen
                $article = \rex_article::get($article_id);
                $article_name = $article ? $article->getName() : 'Unknown Article';
                
                $clang = \rex_clang::get($clang_id);
                $clang_name = $clang ? $clang->getName() : 'Unknown Language';
                
                // Dateigröße und Datum
                $size = filesize($file);
                $size_formatted = $size < 1024 ? $size . ' B' : round($size / 1024, 2) . ' KB';
                
                $modified = filemtime($file);
                
                $result[] = [
                    'filename' => $filename,
                    'viewport' => $viewport,
                    'article_id' => $article_id,
                    'article_name' => $article_name,
                    'clang_id' => $clang_id,
                    'clang_name' => $clang_name,
                    'size' => $size,
                    'size_formatted' => $size_formatted,
                    'modified' => $modified,
                    'modified_formatted' => date('Y-m-d H:i:s', $modified),
                    'path' => $file
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Löscht eine Cache-Datei
     * 
     * @param string $filename Der Dateiname (ohne Pfad)
     * @return bool True bei Erfolg, sonst false
     */
    public static function deleteCacheFile(string $filename): bool
    {
        // Dateinamen validieren, um Directory Traversal zu verhindern
        if (preg_match('/[^a-zA-Z0-9_.-]/', $filename)) {
            return false;
        }
        
        $cacheFile = \rex_path::addonCache('css_above_the_fold', $filename);
        
        if (file_exists($cacheFile) && is_file($cacheFile)) {
            return unlink($cacheFile);
        }
        
        return false;
    }
    
    /**
     * Löscht alle Cache-Dateien
     * 
     * @return int Anzahl der gelöschten Dateien
     */
    public static function deleteAllCacheFiles(): int
    {
        $cacheDir = \rex_path::addonCache('css_above_the_fold');
        $count = 0;
        
        $files = glob($cacheDir . '*.css');
        if (is_array($files)) {
            foreach ($files as $file) {
                if (is_file($file) && unlink($file)) {
                    $count++;
                }
            }
        }
        
        return $count;
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
    private static function getCriticalJsCode(string $viewport, int $article_id, int $clang_id, string $token): string
    {
        // API-URL für das Frontend korrekt generieren
        $apiUrl = \rex_url::frontendController(['rex-api-call' => 'css_above_the_fold', 'method' => 'saveCss']);
        
        // Breakpoints für den Viewport-Detektor abrufen
        $breakpoints = self::getBreakpoints();
        $viewportHeight = $breakpoints[$viewport] ?? 1000;
        
        // Selektoren, die immer/nie eingeschlossen werden sollen
        $addon = \rex_addon::get('css_above_the_fold');
        $alwaysIncludeSelectors = $addon->getConfig('always_include_selectors', '');
        $neverIncludeSelectors = $addon->getConfig('never_include_selectors', '');
        
        // NOWDOC für JavaScript-Code verwenden, um Probleme mit Quotes zu vermeiden
        return <<<JS
<script id="critical-css-generator">
document.addEventListener("DOMContentLoaded", function() {
    // requestIdleCallback verwenden, wenn verfügbar, um die Auswirkungen zu minimieren,
    // sonst auf setTimeout zurückgreifen
    var runExtraction = function() {
        try {
            var criticalCssExtractor = {
                config: {
                    viewport: '{$viewport}',
                    article_id: {$article_id},
                    clang_id: {$clang_id},
                    token: '{$token}',
                    apiUrl: '{$apiUrl}',
                    viewportHeight: window.innerHeight,
                    viewportWidth: window.innerWidth,
                    alwaysIncludeSelectors: '{$alwaysIncludeSelectors}'.split(/\\r?\\n/).filter(Boolean),
                    neverIncludeSelectors: '{$neverIncludeSelectors}'.split(/\\r?\\n/).filter(Boolean)
                },
                
                processedSelectors: new Set(), // Verfolgte bereits verarbeitete Selektoren
                processedRules: new Set(),     // Verfolgte bereits hinzugefügte CSS-Regeln
                debugMode: false,              // Debug-Modus-Einstellung

                init: function() {
                    if (this.debugMode) console.log('[CriticalCSS] Starting extraction for viewport ' + this.config.viewport + '...');
                    this.extractCriticalCss();
                },
                
                extractCriticalCss: function() {
                    var styles = "";
                    var viewportHeight = this.config.viewportHeight;
                    var viewportWidth = this.config.viewportWidth;

                    try {
                        var elements = document.querySelectorAll("*");
                        var visibleElements = [];
                        
                        for (var i = 0; i < elements.length; i++) {
                            var element = elements[i];
                            // Verbesserten Viewport-Check verwenden
                            if (this.isElementInViewport(element, viewportHeight, viewportWidth)) {
                                visibleElements.push(element);
                            }
                        }
                        
                        if (this.debugMode) console.log('[CriticalCSS] Found ' + visibleElements.length + ' elements potentially in viewport.');

                        // Selektoren verarbeiten, die immer eingeschlossen werden sollen
                        this.config.alwaysIncludeSelectors.forEach(selector => {
                            if (selector && !this.processedSelectors.has(selector)) {
                                var selectorStyles = this.extractSelectorStyles(selector);
                                if (selectorStyles) {
                                    styles += selectorStyles;
                                    this.processedSelectors.add(selector);
                                }
                            }
                        });

                        for (var i = 0; i < visibleElements.length; i++) {
                            var element = visibleElements[i];
                            // Einen Selektor für das Element und seine Eltern generieren, wenn für Spezifität erforderlich
                            var selectorInfo = this.generateSelector(element); 
                            
                            if (selectorInfo && selectorInfo.selector) {
                                var selector = selectorInfo.selector;
                                
                                // Prüfen, ob der Selektor in der "Nie einschließen"-Liste steht
                                var shouldSkip = this.config.neverIncludeSelectors.some(neverSelector => 
                                    selector.includes(neverSelector)
                                );
                                
                                if (shouldSkip) {
                                    continue;
                                }
                                
                                // Nur verarbeiten, wenn ein Selektor generiert wurde und nicht zuvor verarbeitet wurde
                                if (!this.processedSelectors.has(selector)) {
                                    styles += this.extractSelectorStyles(selector);
                                    this.processedSelectors.add(selector); 
                                }
                                // Wenn auch ein Elternselektor für die Eindeutigkeit generiert wurde, diesen ebenfalls verarbeiten
                                if (selectorInfo.parentSelector && !this.processedSelectors.has(selectorInfo.parentSelector)) {
                                     styles += this.extractSelectorStyles(selectorInfo.parentSelector);
                                     this.processedSelectors.add(selectorInfo.parentSelector);
                                }
                            }
                        }
                        
                        if (styles.trim() !== "") {
                            // Grundlegende Bereinigung: Leere Regeln entfernen (könnte ausgefeilter sein)
                            styles = styles.replace(/@[^\\{]+\\{\\s*\\}/gm, '').replace(/[^\\{]+\\{\\s*\\}/gm, '');
                            if (this.debugMode) console.log('[CriticalCSS] Extracted ' + styles.length + ' bytes. Sending to server.');
                            this.sendCriticalCss(styles.trim());
                        } else {
                             if (this.debugMode) console.log("[CriticalCSS] No styles extracted for visible elements.");
                        }
                    } catch (e) {
                        console.error("[CriticalCSS] Error during extraction:", e);
                    }
                },
                
                isElementInViewport: function(element, viewportHeight, viewportWidth) {
                    if (!(element instanceof Element)) { return false; } // Sicherstellen, dass es ein Element ist

                    // Script/style/meta/link/title/noscript-Tags und den Generator selbst überspringen
                    var tagName = element.tagName;
                    if (['SCRIPT', 'STYLE', 'META', 'LINK', 'TITLE', 'NOSCRIPT'].includes(tagName) || element.id === 'critical-css-generator') { return false; }

                    var rect = element.getBoundingClientRect();
                    
                    // Element muss Abmessungen haben, um sichtbar zu sein
                    if (rect.width <= 0 || rect.height <= 0) { return false; }

                    // Element muss das Viewport-Rechteck schneiden
                    var intersects = (
                        rect.bottom > 0 &&                   // Untere Kante unterhalb der Viewport-Oberkante
                        rect.right > 0 &&                    // Rechte Kante rechts von der Viewport-Linken
                        rect.top < viewportHeight &&         // Obere Kante oberhalb der Viewport-Unterkante
                        rect.left < viewportWidth            // Linke Kante links von der Viewport-Rechten
                    );

                    if (!intersects) return false;

                    // Grundlegende Prüfung des berechneten visibility-Stils
                    try {
                        var style = window.getComputedStyle(element);
                        if (style.display === 'none' || style.visibility === 'hidden' || parseFloat(style.opacity) === 0) {
                            return false;
                        }
                        // Prüfen, ob clip-path das Element vollständig verbirgt
                        if (style.clipPath && (style.clipPath === 'inset(100%)' || style.clipPath === 'circle(0px)' || style.clipPath === 'polygon(0px 0px, 0px 0px, 0px 0px)')) {
                             return false;
                        }
                        // Legacy clip-Eigenschaft prüfen
                        if (style.clip && style.clip.startsWith('rect') && style.clip.match(/[1-9]/) === null) {
                             return false;
                        }
                    } catch (e) {
                         if (this.debugMode) console.warn('[CriticalCSS] Could not get computed style for element:', element, e);
                         // Sichtbar annehmen, wenn Style-Check fehlschlägt
                    }
                    
                    return true; // Alle Prüfungen bestanden
                },
                
                generateSelector: function(element, depth = 0) {
                    // Maximale Tiefe, um Endlosschleifen oder übermäßig lange Selektoren zu verhindern
                    const MAX_DEPTH = 5; 
                    if (!element || !(element instanceof Element) || element === document.documentElement || element === document.body || depth > MAX_DEPTH) {
                        return null;
                    }
                    
                    var selector = element.tagName.toLowerCase();
                    var parentSelector = null;
                    
                    // 1. ID bevorzugen, wenn eindeutig und gültig
                    if (element.id) {
                         var escapedId = element.id.replace(/(:|\\.|\[|\\]|,|=)/g, "\\\\$1");
                         var idSelector = "#" + escapedId;
                         try {
                             if (document.querySelectorAll(idSelector).length === 1) {
                                selector = element.tagName.toLowerCase() + idSelector; // Etwas spezifischer machen
                                return { selector: selector, parentSelector: null }; 
                             }
                         } catch(e) { /* Ungültigen Selektor ignorieren */ }
                    }
                    
                    // 2. Klassen verwenden, wenn verfügbar
                    var classNameString = "";
                    try {
                        if (element instanceof SVGElement && element.className instanceof SVGAnimatedString) {
                            classNameString = element.className.baseVal;
                        } else if (typeof element.className === 'string') {
                            classNameString = element.className;
                        }
                    } catch (e) { /* Ignorieren */ }
                    
                    if (classNameString) {
                        var classes = classNameString.trim().split(/\\s+/).filter(Boolean); // Leere Strings herausfiltern
                        if (classes.length > 0) {
                             classes.sort(); 
                             selector += "." + classes.map(cls => cls.replace(/(:|\\.|\[|\\]|,|=)/g, "\\\\$1")).join('.');
                        }
                    }

                    // 3. Eindeutigkeit prüfen - Bei Nicht-Eindeutigkeit versuchen, Elternkontext hinzuzufügen
                    var isUnique = false;
                    try {
                         isUnique = document.querySelectorAll(selector).length === 1;
                    } catch (e) { /* Ungültigen Selektor ignorieren */ }

                    if (!isUnique && element.parentElement) {
                         var parentInfo = this.generateSelector(element.parentElement, depth + 1);
                         if (parentInfo && parentInfo.selector) {
                              parentSelector = parentInfo.selector; // Elternselektor separat speichern
                              // Wir benötigen möglicherweise nicht immer den kombinierten Selektor, wenn der Elternteil selbst verarbeitet wird
                              // selector = parentInfo.selector + " > " + selector; 
                         }
                    }
                    
                    return { selector: selector, parentSelector: parentSelector };
                },
                
                extractSelectorStyles: function(selector) {
                    var styles = "";
                    var styleSheets = document.styleSheets;
                    
                    // Grundlegende Prüfung, ob der Selektor potenziell gültig ist, bevor alle Regeln durchlaufen werden
                    try {
                         document.querySelector(selector); // Wirft Fehler bei ungültiger Syntax
                    } catch (e) {
                         if (this.debugMode) console.warn("[CriticalCSS] Skipping extraction for invalid selector:", selector, e);
                         return ""; 
                    }

                    try {
                        for (var i = 0; i < styleSheets.length; i++) {
                            var sheet = styleSheets[i];
                            
                            if (sheet.disabled || !sheet.ownerNode || sheet.ownerNode.id === 'critical-css' || sheet.ownerNode.id === 'critical-css-generator') continue; 

                            var rules;
                            try {
                                rules = sheet.cssRules || sheet.rules;
                                if (!rules) continue; 
                            } catch (e) {
                                if (e.name !== 'SecurityError') { 
                                     if (this.debugMode) console.warn("[CriticalCSS] Could not access rules for stylesheet:", sheet.href, e.message);
                                }
                                continue; 
                            }
                            
                            for (var j = 0; j < rules.length; j++) {
                                var rule = rules[j];
                                var ruleText = ""; 

                                try { 
                                    if (rule.type === 1 /* CSSStyleRule */) {
                                        var ruleSelector = rule.selectorText;
                                        if (ruleSelector && this.doesRuleMatchSelector(ruleSelector, selector)) {
                                            ruleText = rule.cssText;
                                        }
                                    } else if (rule.type === 4 /* CSSMediaRule */) {
                                        var mediaCondition = rule.conditionText || rule.media.mediaText;
                                        if (window.matchMedia(mediaCondition).matches) {
                                            var mediaStyles = "";
                                            var mediaRules = rule.cssRules || rule.rules;
                                            if (mediaRules) {
                                                 for (var k = 0; k < mediaRules.length; k++) {
                                                    var mediaRule = mediaRules[k];
                                                    if (mediaRule.type === 1 && mediaRule.selectorText && this.doesRuleMatchSelector(mediaRule.selectorText, selector)) {
                                                         mediaStyles += mediaRule.cssText + "\\n";
                                                    }
                                                }
                                            }
                                            if (mediaStyles) {
                                                ruleText = "@media " + mediaCondition + " {\\n" + mediaStyles + "}\\n";
                                            }
                                        }
                                    } else if (rule.type === 3 /* CSSImportRule */) {
                                        // Import-Regeln überspringen, da wir direkt auf die Stylesheets zugreifen
                                        continue;
                                    } else if (rule.type === 5 /* CSSFontFaceRule */) {
                                        // Font-Face-Regeln haben wir bereits, wenn wir die Datei später vollständig laden
                                        continue;
                                    } else if (rule.type === 6 /* CSSPageRule */) {
                                        // Page-Regeln beeinflussen nicht den sichtbaren Bereich
                                        continue;
                                    } else if (rule.type === 7 /* CSSKeyframesRule */) {
                                        // Keyframes werden in unserem Fall nicht benötigt
                                        continue;
                                    } else if (rule.type === 8 /* CSSKeyframeRule */) {
                                        // Keyframes-Regeln werden in unserem Fall nicht benötigt
                                        continue;
                                    } else if (rule.type === 12 /* CSSSupportsRule */) {
                                        var supportsCondition = rule.conditionText;
                                        if (CSS.supports(supportsCondition)) {
                                            var supportsStyles = "";
                                            var supportsRules = rule.cssRules || rule.rules;
                                            if (supportsRules) {
                                                 for (var m = 0; m < supportsRules.length; m++) {
                                                    var supportsRule = supportsRules[m];
                                                    if (supportsRule.type === 1 && supportsRule.selectorText && this.doesRuleMatchSelector(supportsRule.selectorText, selector)) {
                                                         supportsStyles += supportsRule.cssText + "\\n";
                                                    }
                                                }
                                            }
                                            if (supportsStyles) {
                                                ruleText = "@supports " + supportsCondition + " {\\n" + supportsStyles + "}\\n";
                                            }
                                        }
                                    }

                                    // Eindeutigen Regeltext zu den Styles hinzufügen
                                    if (ruleText && !this.processedRules.has(ruleText)) {
                                        styles += ruleText + "\\n";
                                        this.processedRules.add(ruleText);
                                    }
                                } catch(ruleError) {
                                     if (this.debugMode) console.warn('[CriticalCSS] Error processing a specific CSS rule:', rule, ruleError);
                                }
                            } // Ende der Regeln-Schleife
                        } // Ende der Stylesheets-Schleife
                    } catch (e) {
                        console.error("[CriticalCSS] Error processing styles for selector " + selector + ":", e);
                    }
                    
                    return styles;
                },

                // Hilfsmethode zu prüfen, ob der selectorText einer Regel möglicherweise mit dem Selektor des Elements übereinstimmt
                doesRuleMatchSelector: function(ruleSelectorText, elementSelector) {
                     // Einfache Prüfung zuerst: Wenn der Element-Selektor ein Substring ist, könnte er übereinstimmen.
                     // Dies ist schnell, kann aber False Positives haben (z.B. ".button" stimmt mit ".button-active" überein).
                     if (!ruleSelectorText.includes(elementSelector.split(/#|\\./)[0])) { // Schnelle Prüfung des Tag-Namens
                          return false;
                     }
                    
                     // Robuster (aber langsamer): Die Regel-Selektoren aufteilen und auf exakte Übereinstimmung prüfen.
                     var ruleSelectors = ruleSelectorText.split(',').map(s => s.trim());
                     if (ruleSelectors.includes(elementSelector)) {
                          return true;
                     }

                     // Einfachheitshalber nehmen wir an, dass, wenn der elementSelector ein Teil von ruleSelectorText ist,
                     // eine potenzielle Übereinstimmung besteht.
                     return ruleSelectorText.includes(elementSelector);
                },
                
                sendCriticalCss: function(css) {
                    if (!this.config.apiUrl) {
                        console.error('[CriticalCSS] API URL is not configured.');
                        return;
                    }
                    var xhr = new XMLHttpRequest();
                    xhr.open("POST", this.config.apiUrl, true); 
                    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                    
                    xhr.onerror = function() {
                        console.error("[CriticalCSS] Network error sending data to server.");
                    };
                    xhr.onload = function() {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            if (this.debugMode) console.log("[CriticalCSS] Data sent successfully. Status:", xhr.status);
                        } else {
                            console.error("[CriticalCSS] Server responded with status:", xhr.status, xhr.responseText);
                        }
                    };
                    xhr.ontimeout = function () {
                         console.error('[CriticalCSS] Request timed out.');
                    };
                    xhr.timeout = 15000; // Timeout setzen (15 Sekunden)

                    var data = "method=saveCss" + 
                              "&viewport=" + encodeURIComponent(this.config.viewport) + 
                              "&article_id=" + encodeURIComponent(this.config.article_id) + 
                              "&clang_id=" + encodeURIComponent(this.config.clang_id) + 
                              "&token=" + encodeURIComponent(this.config.token) + 
                              "&css=" + encodeURIComponent(css); 
                    
                    try {
                         xhr.send(data);
                    } catch (e) {
                         console.error("[CriticalCSS] Error sending XHR request:", e);
                    }
                }
            }; // Ende des criticalCssExtractor-Objekts
            
            criticalCssExtractor.init();

        } catch (e) {
            console.error("[CriticalCSS] Error initializing script:", e);
        }
    }; // Ende der runExtraction-Funktion

    if (window.requestIdleCallback) {
        window.requestIdleCallback(runExtraction, { timeout: 2000 }); // Idle-Callback mit 2s-Timeout verwenden
    } else {
        setTimeout(runExtraction, 500); // Fallback auf setTimeout
    }
});
</script>
JS; // Ende des NOWDOC
    }
    
    /**
     * Öffentliche Methode zum manuellen Anstoßen des asynchronen Ladens von CSS-Dateien
     * Kann in Templates/Modulen verwendet werden, wenn das AddOn nicht automatisch 
     * alle Stylesheets verarbeiten soll.
     * 
     * @param string $cssUrl URL zur CSS-Datei
     * @return string HTML-Code zum asynchronen Laden
     */
    public static function loadCssAsync(string $cssUrl): string
    {
        // URL für HTML-Attribute richtig kodieren
        $safeCssUrl = htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8');
        
        // Empfohlenes Pattern für asynchrones CSS-Loading verwenden
        return '<link rel="preload" href="' . $safeCssUrl . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . PHP_EOL . 
               '<noscript><link rel="stylesheet" href="' . $safeCssUrl . '"></noscript>';
    }
    
    /**
     * Loggt Debug-Informationen, wenn DEBUG aktiviert ist
     * 
     * @param string $message Die Debug-Nachricht
     * @param array $context Zusätzlicher Kontext (optional)
     */
    protected static function logDebug(string $message, array $context = []): void
    {
        if (defined('CSS_ABOVE_THE_FOLD_DEBUG') && CSS_ABOVE_THE_FOLD_DEBUG) {
            \rex_logger::logger('CSS Above The Fold Debug: ' . $message, $context);
        }
    }
}
