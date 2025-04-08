<?php

namespace FriendsOfRedaxo\CssAboveTheFold;

/**
 * CSS Above The Fold API
 * 
 * Verarbeitet die API-Anfragen für das AddOn
 */
class Api extends \rex_api_function
{
    /** @var bool API im Frontend verfügbar machen */
    protected $published = true;
    
    /** @var array Antwort-Array */
    protected array $response = [];
    
    /** @var bool Erfolgsstatus */
    protected bool $success = true;

    /**
     * Führt die API-Funktion aus
     * 
     * @throws \rex_api_exception Bei Fehlern
     */
    public function execute(): \rex_api_result
    {
        // API-Output-Buffer leeren, um unerwünschte Ausgaben zu vermeiden
        \rex_response::cleanOutputBuffers();
        
        // Prüfen, ob es ein POST-Request ist
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new \rex_api_exception('Ungültige Anfragemethode. Nur POST ist erlaubt.');
        }

        $method = \rex_post('method', 'string', '');
        $internalMethod = '__' . $method;

        if (!$method || !method_exists($this, $internalMethod)) {
            $this->logError(E_WARNING, "CSS Above The Fold API: Methode '{$method}' existiert nicht oder ist nicht aufrufbar.");
            throw new \rex_api_exception("Methode '{$method}' existiert nicht.");
        }
        
        try {
            // Methode ausführen
            $this->$internalMethod();
        } catch (\rex_api_exception $apiEx) {
            // API-spezifische Exceptions weiterwerfen
            throw $apiEx;
        } catch (\Exception $ex) {
            // Allgemeine Exceptions abfangen, protokollieren und eine generische rex_api_exception werfen
            $this->logError(E_WARNING, "CSS Above The Fold API: {$ex->getMessage()}", $ex->getFile(), $ex->getLine());
            throw new \rex_api_exception('Ein interner Serverfehler ist aufgetreten.', $ex);
        }
        
        // Header setzen, bevor Ausgabe gesendet wird
        \rex_response::setHeader('Content-Type', 'application/json; charset=utf-8');
        
        // Bei erfolgreichem Abschluss ein JSON-Ergebnis zurückgeben und beenden
        return new \rex_api_result($this->success, $this->response);
    }

    /**
     * Speichert das Critical CSS
     * Interne Methode mit Präfix __
     * 
     * @throws \rex_api_exception Bei Fehlern
     */
    private function __saveCss(): void
    {
        $addon = \rex_addon::get('css_above_the_fold');
        $token = \rex_post('token', 'string', '');
        $css = \rex_post('css', 'string', '');
        $viewport = \rex_post('viewport', 'string', 'xl');
        $article_id = \rex_post('article_id', 'int', 0);
        $clang_id = \rex_post('clang_id', 'int', 0);
        
        $tokenKey = 'token_' . $viewport . '_' . $article_id . '_' . $clang_id;
        $expectedToken = $addon->getConfig($tokenKey, null);

        if (empty($token)) {
             $this->success = false;
             throw new \rex_api_exception('Token fehlt.');
        }
        
        if (null === $expectedToken) {
            $this->success = false;
            $this->logError(E_WARNING, "CSS Above The Fold API: Erwarteter Token '{$tokenKey}' nicht in der Konfiguration gefunden.");
            throw new \rex_api_exception('Token ist ungültig oder abgelaufen.');
        }
        
        if (!hash_equals((string)$expectedToken, $token)) {
             $this->success = false;
             $this->logError(E_WARNING, "CSS Above The Fold API: Ungültiger Token für '{$tokenKey}' bereitgestellt.");
             throw new \rex_api_exception('Token ist ungültig.');
        }

        // Benutzten Token entfernen
        $addon->removeConfig($tokenKey);

        if ($article_id <= 0) {
            $this->success = false;
            throw new \rex_api_exception('Artikel-ID nicht gesetzt oder ungültig.');
        }
        
        if ($clang_id <= 0) {
            $this->success = false;
            throw new \rex_api_exception('Sprach-ID nicht gesetzt oder ungültig.');
        }
        
        if (empty($viewport)) {
             $this->success = false;
             throw new \rex_api_exception('Viewport nicht gesetzt.');
        }
        
        $trimmedCss = trim($css);
        if (empty($trimmedCss)) {
             $this->success = false;
             throw new \rex_api_exception('CSS-Inhalt ist leer.');
        }
        
        if (strlen($trimmedCss) < 10 || (!str_contains($trimmedCss, '{') && !str_contains($trimmedCss, '}'))) {
             $this->success = false;
             $this->logError(E_WARNING, "CSS Above The Fold API: Potenziell ungültiges CSS für {$tokenKey} empfangen: " . substr($trimmedCss, 0, 100));
             throw new \rex_api_exception('CSS-Inhalt scheint ungültig zu sein.');
        }
        
        $file = CssAboveTheFold::getCacheFile($viewport, $article_id, $clang_id);
        
        if (false === \rex_file::put($file, $css)) {
             $this->success = false;
             $this->logError(E_ERROR, "CSS Above The Fold API: Fehler beim Schreiben der CSS-Datei: {$file}");
             throw new \rex_api_exception('Fehler beim Speichern der CSS-Datei.');
        }
        
        // Erfolgreiche Antwort befüllen
        $this->success = true;
        $this->response['status'] = 'success';
        $this->response['file'] = basename($file);
        $this->response['message'] = 'Critical CSS erfolgreich gespeichert.';
    }

    /**
     * Löscht das Critical CSS
     * Interne Methode mit Präfix __
     * 
     * @throws \rex_api_exception Bei Fehlern
     */
    private function __deleteCss(): void
    {
        $addon = \rex_addon::get('css_above_the_fold');
        $token = \rex_post('token', 'string', '');
        $viewport = \rex_post('viewport', 'string', '');
        $article_id = \rex_post('article_id', 'int', 0);
        $clang_id = \rex_post('clang_id', 'int', 0);
        
        // Token validieren (falls implementiert)
        $tokenKey = 'delete_token_' . $viewport . '_' . $article_id . '_' . $clang_id;
        $expectedToken = $addon->getConfig($tokenKey, null);
        
        if (empty($token) || null === $expectedToken || !hash_equals((string)$expectedToken, $token)) {
            $this->success = false;
            throw new \rex_api_exception('Ungültiger Token für das Löschen.');
        }
        
        // Benutzten Token entfernen
        $addon->removeConfig($tokenKey);
        
        if ($article_id <= 0 || $clang_id <= 0 || empty($viewport)) {
            $this->success = false;
            throw new \rex_api_exception('Fehlende erforderliche Parameter für das Löschen.');
        }
        
        $file = CssAboveTheFold::getCacheFile($viewport, $article_id, $clang_id);
        
        if (!file_exists($file)) {
            $this->success = false;
            throw new \rex_api_exception('CSS-Datei existiert nicht.');
        }
        
        if (!CssAboveTheFold::deleteCacheFile(basename($file))) {
            $this->success = false;
            $this->logError(E_WARNING, "CSS Above The Fold API: Fehler beim Löschen der CSS-Datei: {$file}");
            throw new \rex_api_exception('Fehler beim Löschen der CSS-Datei.');
        }
        
        // Erfolgreiche Antwort befüllen
        $this->success = true;
        $this->response['status'] = 'success';
        $this->response['message'] = 'Critical CSS erfolgreich gelöscht.';
    }
    
    /**
     * Löscht den gesamten Cache
     * Interne Methode mit Präfix __
     * 
     * @throws \rex_api_exception Bei Fehlern
     */
    private function __clearCache(): void
    {
        $addon = \rex_addon::get('css_above_the_fold');
        $token = \rex_post('token', 'string', '');
        
        // Token validieren
        $tokenKey = 'clear_cache_token';
        $expectedToken = $addon->getConfig($tokenKey, null);
        
        if (empty($token) || null === $expectedToken || !hash_equals((string)$expectedToken, $token)) {
            $this->success = false;
            throw new \rex_api_exception('Ungültiger Token für das Löschen des Caches.');
        }
        
        // Benutzten Token entfernen
        $addon->removeConfig($tokenKey);
        
        $count = CssAboveTheFold::deleteAllCacheFiles();
        
        // Erfolgreiche Antwort befüllen
        $this->success = true;
        $this->response['status'] = 'success';
        $this->response['count'] = $count;
        $this->response['message'] = "Cache erfolgreich gelöscht. {$count} Dateien wurden entfernt.";
    }
    
    /**
     * Standard-Funktion, wenn keine Methode angegeben oder nicht gefunden wurde
     */
    protected function __default(): void
    {
         $this->success = false;
         throw new \rex_api_exception('Keine gültige API-Methode angegeben.');
    }
    
    /**
     * Hilfsmethode zum Protokollieren von Fehlern
     * 
     * @param int $level Fehlerlevel (E_ERROR, E_WARNING, etc.)
     * @param string $message Fehlermeldung
     * @param string $file Datei, in der der Fehler aufgetreten ist (optional)
     * @param int $line Zeile, in der der Fehler aufgetreten ist (optional)
     */
    private function logError(int $level, string $message, string $file = __FILE__, int $line = __LINE__): void
    {
        \rex_logger::logError($level, $message, $file, $line);
    }
}
