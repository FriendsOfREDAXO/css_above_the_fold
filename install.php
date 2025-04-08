<?php

/**
 * CSS Above The Fold AddOn
 * Installationsroutine
 */

// Benötigte Namespaces
use rex_dir;
use rex_path;

// Cache-Verzeichnis im addonCache erstellen
$cacheDir = rex_path::addonCache('css_above_the_fold');
rex_dir::create($cacheDir);

// Erfolgsmeldung
return true;
