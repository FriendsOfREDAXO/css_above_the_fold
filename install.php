<?php
/**
 * CSS Above The Fold AddOn
 * Installationsroutine
 */

// Cache-Verzeichnis im addonCache erstellen
$cacheDir = rex_path::addonCache('css_above_the_fold');
rex_dir::create($cacheDir);

// JavaScript-Verzeichnis erstellen, falls noch nicht vorhanden
$jsDir = rex_path::addon('css_above_the_fold', 'assets/js');
rex_dir::create($jsDir);

// Critical-Extractor JavaScript-Datei erstellen
$jsFile = rex_path::addon('css_above_the_fold', 'assets/js/critical-extractor.js');
$jsContent = <<<'EOD'
/**
 * CSS Above The Fold - Critical CSS Extractor
 * 
 * Extrahiert das für den sichtbaren Bereich einer Webseite 
 * benötigte CSS und sendet es an den Server, um es für zukünftige 
 * Besuche zwischenzuspeichern.
 */
(function() {
    // DOMContentLoaded-Event abwarten
    document.addEventListener("DOMContentLoaded", function() {
        // requestIdleCallback verwenden, wenn verfügbar, um die Auswirkungen zu minimieren
        var executeExtraction = function() {
            try {
                var criticalCssExtractor = {
                    // Konfiguration
                    config: {
                        // Diese Werte werden bei der Einbindung ersetzt
                        viewport: '{{VIEWPORT}}',
                        article_id: {{ARTICLE_ID}},
                        clang_id: {{CLANG_ID}},
                        token: '{{TOKEN}}',
                        apiUrl: '{{API_URL}}',
                        viewportHeight: window.innerHeight,
                        viewportWidth: window.innerWidth,
                        includeCssVars: {{INCLUDE_CSS_VARS}},
                        preserveImportantRules: {{PRESERVE_IMPORTANT_RULES}},
                        alwaysIncludeSelectors: '{{ALWAYS_INCLUDE}}'.split(/\r?\n/).filter(Boolean),
                        neverIncludeSelectors: '{{NEVER_INCLUDE}}'.split(/\r?\n/).filter(Boolean)
                    },
                    
                    // Cache für verarbeitete Elemente
                    processedSelectors: new Set(),
                    processedRules: new Set(),
                    extractionStartTime: Date.now(),
                    extractedBytes: 0,
                    
                    // Debug-Modus
                    debugMode: false,
                    
                    /**
                     * Initialisierung
                     */
                    init: function() {
                        if (this.debugMode) {
                            console.log('[CriticalCSS] Extraction started for viewport: ' + this.config.viewport);
                            console.log('[CriticalCSS] Viewport dimensions: ' + this.config.viewportWidth + 'x' + this.config.viewportHeight);
                        }
                        
                        this.extractCriticalCss();
                    },
                    
                    /**
                     * Hauptfunktion zur Extraktion des Critical CSS
                     */
                    extractCriticalCss: function() {
                        var styles = "";
                        var viewportHeight = this.config.viewportHeight;
                        var viewportWidth = this.config.viewportWidth;

                        try {
                            // Standardmäßig CSS-Variablen einschließen, wenn konfiguriert
                            if (this.config.includeCssVars) {
                                styles += this.extractCssVariables();
                            }
                            
                            // Alle Elemente im DOM auswählen
                            var elements = document.querySelectorAll("*");
                            var visibleElements = [];
                            
                            // Sichtbare Elemente finden
                            for (var i = 0; i < elements.length; i++) {
                                var element = elements[i];
                                if (this.isElementInViewport(element, viewportHeight, viewportWidth)) {
                                    visibleElements.push(element);
                                }
                            }
                            
                            if (this.debugMode) {
                                console.log('[CriticalCSS] Found ' + visibleElements.length + ' visible elements');
                            }

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

                            // Selektoren für sichtbare Elemente extrahieren
                            for (var i = 0; i < visibleElements.length; i++) {
                                var element = visibleElements[i];
                                var selectorInfo = this.generateSelector(element);
                                
                                if (selectorInfo && selectorInfo.selector) {
                                    var selector = selectorInfo.selector;
                                    
                                    // Prüfen, ob der Selektor übersprungen werden soll
                                    var shouldSkip = this.config.neverIncludeSelectors.some(neverSelector => 
                                        selector.includes(neverSelector)
                                    );
                                    
                                    if (shouldSkip) continue;
                                    
                                    // CSS für diesen Selektor extrahieren
                                    if (!this.processedSelectors.has(selector)) {
                                        styles += this.extractSelectorStyles(selector);
                                        this.processedSelectors.add(selector);
                                    }
                                    
                                    // Eltern-Selektor verarbeiten, falls vorhanden
                                    if (selectorInfo.parentSelector && !this.processedSelectors.has(selectorInfo.parentSelector)) {
                                        styles += this.extractSelectorStyles(selectorInfo.parentSelector);
                                        this.processedSelectors.add(selectorInfo.parentSelector);
                                    }
                                }
                            }
                            
                            // Wenn Styles gefunden wurden, an den Server senden
                            if (styles.trim() !== "") {
                                // Grundlegende Bereinigung: Leere Regeln entfernen
                                styles = styles.replace(/@[^\{]+\{\s*\}/gm, '').replace(/[^\{]+\{\s*\}/gm, '');
                                
                                this.extractedBytes = styles.length;
                                
                                if (this.debugMode) {
                                    console.log('[CriticalCSS] Extracted ' + styles.length + ' bytes of CSS');
                                    console.log('[CriticalCSS] Extraction took ' + (Date.now() - this.extractionStartTime) + 'ms');
                                }
                                
                                this.sendCriticalCss(styles.trim());
                            } else {
                                if (this.debugMode) {
                                    console.log("[CriticalCSS] No styles extracted");
                                }
                            }
                        } catch (e) {
                            console.error("[CriticalCSS] Error during extraction:", e);
                        }
                    },
                    
                    /**
                     * Extrahiert alle CSS-Variablen aus :root und html-Selektoren
                     */
                    extractCssVariables: function() {
                        var cssVars = "";
                        var rootSelectors = [":root", "html", "body"];
                        
                        rootSelectors.forEach(rootSelector => {
                            if (!this.processedSelectors.has(rootSelector)) {
                                var selectorStyles = this.extractSelectorStyles(rootSelector);
                                if (selectorStyles) {
                                    // Nur CSS-Variablen behalten
                                    if (selectorStyles.includes("--")) {
                                        cssVars += selectorStyles;
                                        this.processedSelectors.add(rootSelector);
                                    }
                                }
                            }
                        });
                        
                        return cssVars;
                    },
                    
                    /**
                     * Prüft, ob ein Element im sichtbaren Bereich liegt
                     */
                    isElementInViewport: function(element, viewportHeight, viewportWidth) {
                        if (!(element instanceof Element)) return false;

                        // Bestimmte Tags überspringen
                        var tagName = element.tagName;
                        if (['SCRIPT', 'STYLE', 'META', 'LINK', 'TITLE', 'NOSCRIPT'].includes(tagName) || 
                            element.id === 'critical-css-generator' || 
                            element.id === 'critical-css') {
                            return false;
                        }

                        var rect = element.getBoundingClientRect();
                        
                        // Element muss Dimensionen haben
                        if (rect.width <= 0 || rect.height <= 0) return false;

                        // Element muss den Viewport schneiden
                        var intersects = (
                            rect.bottom > 0 &&
                            rect.right > 0 &&
                            rect.top < viewportHeight &&
                            rect.left < viewportWidth
                        );

                        if (!intersects) return false;

                        // Berechnete Stile prüfen
                        try {
                            var style = window.getComputedStyle(element);
                            if (style.display === 'none' || 
                                style.visibility === 'hidden' || 
                                parseFloat(style.opacity) === 0) {
                                return false;
                            }
                            
                            // Clip-Path prüfen
                            if (style.clipPath && 
                                (style.clipPath === 'inset(100%)' || 
                                 style.clipPath === 'circle(0px)' || 
                                 style.clipPath === 'polygon(0px 0px, 0px 0px, 0px 0px)')) {
                                return false;
                            }
                            
                            // Legacy clip-Eigenschaft prüfen
                            if (style.clip && 
                                style.clip.startsWith('rect') && 
                                style.clip.match(/[1-9]/) === null) {
                                return false;
                            }
                        } catch (e) {
                            if (this.debugMode) {
                                console.warn('[CriticalCSS] Error getting computed style:', e);
                            }
                        }
                        
                        return true;
                    },
                    
                    /**
                     * Generiert einen CSS-Selektor für ein Element
                     */
                    generateSelector: function(element, depth = 0) {
                        const MAX_DEPTH = 5;
                        
                        if (!element || 
                            !(element instanceof Element) || 
                            element === document.documentElement || 
                            element === document.body || 
                            depth > MAX_DEPTH) {
                            return null;
                        }
                        
                        var selector = element.tagName.toLowerCase();
                        var parentSelector = null;
                        
                        // ID-basierter Selektor
                        if (element.id) {
                            var escapedId = element.id.replace(/(:|\\.|\[|\]|,|=)/g, "\\\\$1");
                            var idSelector = "#" + escapedId;
                            
                            try {
                                if (document.querySelectorAll(idSelector).length === 1) {
                                    selector = element.tagName.toLowerCase() + idSelector;
                                    return { selector: selector, parentSelector: null };
                                }
                            } catch(e) { /* Ungültigen Selektor ignorieren */ }
                        }
                        
                        // Klassen-basierter Selektor
                        var classNameString = "";
                        
                        try {
                            if (element instanceof SVGElement && element.className instanceof SVGAnimatedString) {
                                classNameString = element.className.baseVal;
                            } else if (typeof element.className === 'string') {
                                classNameString = element.className;
                            }
                        } catch (e) { /* Ignorieren */ }
                        
                        if (classNameString) {
                            var classes = classNameString.trim().split(/\s+/).filter(Boolean);
                            
                            if (classes.length > 0) {
                                classes.sort();
                                selector += "." + classes.map(cls => cls.replace(/(:|\\.|\[|\]|,|=)/g, "\\\\$1")).join('.');
                            }
                        }

                        // Eindeutigkeit prüfen
                        var isUnique = false;
                        
                        try {
                            isUnique = document.querySelectorAll(selector).length === 1;
                        } catch (e) { /* Ungültigen Selektor ignorieren */ }

                        if (!isUnique && element.parentElement) {
                            var parentInfo = this.generateSelector(element.parentElement, depth + 1);
                            
                            if (parentInfo && parentInfo.selector) {
                                parentSelector = parentInfo.selector;
                            }
                        }
                        
                        return { selector: selector, parentSelector: parentSelector };
                    },
                    
                    /**
                     * Extrahiert CSS-Regeln für einen bestimmten Selektor
                     */
                    extractSelectorStyles: function(selector) {
                        var styles = "";
                        var styleSheets = document.styleSheets;
                        
                        // Prüfen, ob der Selektor gültig ist
                        try {
                            document.querySelector(selector);
                        } catch (e) {
                            if (this.debugMode) {
                                console.warn("[CriticalCSS] Invalid selector:", selector, e);
                            }
                            return "";
                        }

                        try {
                            // Alle Stylesheets durchlaufen
                            for (var i = 0; i < styleSheets.length; i++) {
                                var sheet = styleSheets[i];
                                
                                // Deaktivierte oder spezielle Stylesheets überspringen
                                if (sheet.disabled || 
                                    !sheet.ownerNode || 
                                    sheet.ownerNode.id === 'critical-css' || 
                                    sheet.ownerNode.id === 'critical-css-generator') {
                                    continue;
                                }

                                var rules;
                                
                                try {
                                    rules = sheet.cssRules || sheet.rules;
                                    if (!rules) continue;
                                } catch (e) {
                                    // CORS-Fehler bei externen Stylesheets ignorieren
                                    if (e.name !== 'SecurityError') {
                                        if (this.debugMode) {
                                            console.warn("[CriticalCSS] Could not access rules:", e);
                                        }
                                    }
                                    continue;
                                }
                                
                                // Alle Regeln durchlaufen
                                for (var j = 0; j < rules.length; j++) {
                                    var rule = rules[j];
                                    var ruleText = "";

                                    try {
                                        // CSSStyleRule (normale CSS-Regeln)
                                        if (rule.type === CSSRule.STYLE_RULE) {
                                            var ruleSelector = rule.selectorText;
                                            
                                            if (ruleSelector && this.doesRuleMatchSelector(ruleSelector, selector)) {
                                                // Bei preserveImportantRules prüfen wir auf !important
                                                if (this.config.preserveImportantRules) {
                                                    var containsImportant = rule.cssText.includes("!important");
                                                    if (containsImportant) {
                                                        ruleText = rule.cssText;
                                                    } else {
                                                        ruleText = rule.cssText;
                                                    }
                                                } else {
                                                    ruleText = rule.cssText;
                                                }
                                            }
                                        } 
                                        // CSSMediaRule (Media Queries)
                                        else if (rule.type === CSSRule.MEDIA_RULE) {
                                            var mediaCondition = rule.conditionText || rule.media.mediaText;
                                            
                                            if (window.matchMedia(mediaCondition).matches) {
                                                var mediaStyles = "";
                                                var mediaRules = rule.cssRules || rule.rules;
                                                
                                                if (mediaRules) {
                                                    for (var k = 0; k < mediaRules.length; k++) {
                                                        var mediaRule = mediaRules[k];
                                                        
                                                        if (mediaRule.type === CSSRule.STYLE_RULE && 
                                                            mediaRule.selectorText && 
                                                            this.doesRuleMatchSelector(mediaRule.selectorText, selector)) {
                                                            mediaStyles += mediaRule.cssText + "\n";
                                                        }
                                                    }
                                                }
                                                
                                                if (mediaStyles) {
                                                    ruleText = "@media " + mediaCondition + " {\n" + mediaStyles + "}\n";
                                                }
                                            }
                                        }
                                        // CSSSupportsRule (@supports)
                                        else if (rule.type === CSSRule.SUPPORTS_RULE) {
                                            var supportsCondition = rule.conditionText;
                                            
                                            if (CSS.supports(supportsCondition)) {
                                                var supportsStyles = "";
                                                var supportsRules = rule.cssRules || rule.rules;
                                                
                                                if (supportsRules) {
                                                    for (var m = 0; m < supportsRules.length; m++) {
                                                        var supportsRule = supportsRules[m];
                                                        
                                                        if (supportsRule.type === CSSRule.STYLE_RULE && 
                                                            supportsRule.selectorText && 
                                                            this.doesRuleMatchSelector(supportsRule.selectorText, selector)) {
                                                            supportsStyles += supportsRule.cssText + "\n";
                                                        }
                                                    }
                                                }
                                                
                                                if (supportsStyles) {
                                                    ruleText = "@supports " + supportsCondition + " {\n" + supportsStyles + "}\n";
                                                }
                                            }
                                        }

                                        // Regel zu den Styles hinzufügen, wenn noch nicht vorhanden
                                        if (ruleText && !this.processedRules.has(ruleText)) {
                                            styles += ruleText + "\n";
                                            this.processedRules.add(ruleText);
                                        }
                                    } catch(ruleError) {
                                        if (this.debugMode) {
                                            console.warn('[CriticalCSS] Error processing rule:', ruleError);
                                        }
                                    }
                                }
                            }
                        } catch (e) {
                            console.error("[CriticalCSS] Error extracting styles:", e);
                        }
                        
                        return styles;
                    },
                    
                    /**
                     * Prüft, ob ein Regel-Selektor mit einem Element-Selektor übereinstimmt
                     */
                    doesRuleMatchSelector: function(ruleSelectorText, elementSelector) {
                        // Multi-Selektoren aufspalten
                        var ruleSelectors = ruleSelectorText.split(',').map(s => s.trim());
                        
                        // Prüfung auf exakte Übereinstimmung oder Teil-Selektor-Übereinstimmung
                        for (var i = 0; i < ruleSelectors.length; i++) {
                            var ruleSelector = ruleSelectors[i];
                            
                            // Exakte Übereinstimmung
                            if (ruleSelector === elementSelector) {
                                return true;
                            }
                            
                            // Klassen-/ID-basierte Prüfung
                            if (elementSelector.includes('.') || elementSelector.includes('#')) {
                                // Einfache Teilstring-Prüfung (kann verbessert werden)
                                if (ruleSelector.includes(elementSelector) || 
                                    elementSelector.includes(ruleSelector)) {
                                    return true;
                                }
                            } else {
                                // Tag-basierte Prüfung (z.B. "div")
                                var elTag = elementSelector.split(/[.#\[]/)[0];
                                if (elTag && ruleSelector.startsWith(elTag)) {
                                    return true;
                                }
                            }
                        }
                        
                        return false;
                    },
                    
                    /**
                     * Sendet das extrahierte CSS an den Server
                     */
                    sendCriticalCss: function(css) {
                        if (!this.config.apiUrl) {
                            console.error('[CriticalCSS] API URL not configured');
                            return;
                        }
                        
                        var xhr = new XMLHttpRequest();
                        xhr.open("POST", this.config.apiUrl, true);
                        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                        
                        xhr.onerror = function() {
                            console.error("[CriticalCSS] Network error");
                        };
                        
                        xhr.onload = function() {
                            if (xhr.status >= 200 && xhr.status < 300) {
                                if (this.debugMode) {
                                    console.log("[CriticalCSS] CSS sent successfully");
                                }
                            } else {
                                console.error("[CriticalCSS] Server error:", xhr.status, xhr.responseText);
                            }
                        }.bind(this);
                        
                        xhr.ontimeout = function() {
                            console.error('[CriticalCSS] Request timed out');
                        };
                        
                        xhr.timeout = 15000; // 15 Sekunden Timeout
                        
                        var data = "viewport=" + encodeURIComponent(this.config.viewport) + 
                                  "&article_id=" + encodeURIComponent(this.config.article_id) + 
                                  "&clang_id=" + encodeURIComponent(this.config.clang_id) + 
                                  "&token=" + encodeURIComponent(this.config.token) + 
                                  "&extraction_time=" + encodeURIComponent(Date.now() - this.extractionStartTime) +
                                  "&css=" + encodeURIComponent(css);
                        
                        try {
                            xhr.send(data);
                        } catch (e) {
                            console.error("[CriticalCSS] Error sending data:", e);
                        }
                    }
                };
                
                // Extraktor starten
                criticalCssExtractor.init();
                
            } catch (e) {
                console.error("[CriticalCSS] Initialization error:", e);
            }
        };
        
        // requestIdleCallback verwenden, wenn verfügbar
        if (window.requestIdleCallback) {
            window.requestIdleCallback(executeExtraction, { timeout: 2000 });
        } else {
            setTimeout(executeExtraction, 500);
        }
    });
})();
EOD;

// JavaScript-Datei schreiben
rex_file::put($jsFile, $jsContent);

// Erfolgsmeldung
return true;
