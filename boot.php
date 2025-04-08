<?php

/**
 * CSS Above The Fold AddOn
 * Boot-Datei wird beim REDAXO-Start geladen
 */

// Debug-Konstante für Entwicklung definieren
// Kann im config.yml auf true gesetzt werden, um Debug-Meldungen zu aktivieren
if (!defined('CSS_ABOVE_THE_FOLD_DEBUG')) {
    define('CSS_ABOVE_THE_FOLD_DEBUG', $this->getConfig('debug', false));
}

// Namespaces importieren
use FriendsOfRedaxo\CssAboveTheFold\CssAboveTheFold;
use rex;
use rex_extension;
use rex_extension_point;

// Output Filter nur im Frontend und nicht im Debug-Modus registrieren
if (rex::isFrontend() && !rex::isDebugMode()) {
    rex_extension::register('OUTPUT_FILTER', function(rex_extension_point $ep) {
        // Prüfen, ob das AddOn aktiviert ist
        if (!$this->getConfig('active', true)) {
            return $ep->getSubject();
        }
        
        return CssAboveTheFold::outputFilter($ep);
    }, rex_extension::LATE);
}

// Im Backend Assets registrieren
if (rex::isBackend() && rex::getUser()) {
    // Hier könnten bei Bedarf Backend-Assets registriert werden
}
