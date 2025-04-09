<?php
/**
 * CSS Above The Fold API
 * 
 * Verarbeitet die API-Anfragen für das AddOn
 */
class rex_api_css_above_the_fold extends rex_api_function
{
    /** @var bool API im Frontend verfügbar machen */
    protected $published = true;
    
    /**
     * Führt die API-Funktion aus
     * 
     * @throws rex_api_exception Bei Fehlern
     */
    public function execute()
    {
        // API-Output-Buffer leeren, um unerwünschte Ausgaben zu vermeiden
        rex_response::cleanOutputBuffers();
        
        // Prüfen, ob es ein POST-Request ist
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendErrorResponse('Ungültige Anfragemethode. Nur POST ist erlaubt.', 405);
            exit;
        }

        $method = rex_post('method', 'string', 'saveCss');
        
        if ($method === 'saveCss') {
            $this->saveCss();
        } elseif ($method === 'deleteCss') {
            $this->deleteCss();
        } elseif ($method === 'clearCache') {
            $this->clearCache();
        } else {
            $this->sendErrorResponse('Unbekannte Methode: ' . $method, 400);
            exit;
        }
    }

    /**
     * Speichert das Critical CSS
     */
    private function saveCss()
    {
        $addon = rex_addon::get('css_above_the_fold');
        $token = rex_post('token', 'string', '');
        $css = rex_post('css', 'string', '');
        $viewport = rex_post('viewport', 'string', 'xl');
        $article_id = rex_post('article_id', 'int', 0);
        $clang_id = rex_post('clang_id', 'int', 0);
        $extraction_time = rex_post('extraction_time', 'int', 0);
        
        $tokenKey = 'token_' . $viewport . '_' . $article_id . '_' . $clang_id;
        $expectedToken = $addon->getConfig($tokenKey, null);

        if (empty($token) || $token !== $expectedToken) {
            $this->sendErrorResponse($addon->i18n('api_error_token'), 403);
            exit;
        }
        
        // Benutzten Token entfernen
        $addon->removeConfig($tokenKey);

        if ($article_id <= 0 || $clang_id <= 0 || empty($viewport)) {
            $this->sendErrorResponse('Fehlende Parameter (article_id, clang_id oder viewport)', 400);
            exit;
        }
        
        $trimmedCss = trim($css);
        if (empty($trimmedCss) || strlen($trimmedCss) < 10) {
            $this->sendErrorResponse($addon->i18n('api_error_no_css'), 400);
            exit;
        }
        
        // Pfad zur Cache-Datei
        $file = $this->getCacheFile($viewport, $article_id, $clang_id);
        
        // CSS in die Cache-Datei schreiben
        if (rex_file::put($file, $css) === false) {
            $this->sendErrorResponse($addon->i18n('api_error_save'), 500);
            exit;
        }
        
        // Statistik speichern (wenn gewünscht)
        if ($extraction_time > 0) {
            $statsKey = 'stats_' . $viewport . '_' . $article_id . '_' . $clang_id;
            $addon->setConfig($statsKey, [
                'extraction_time' => $extraction_time,
                'size' => strlen($css),
                'date' => time()
            ]);
        }
        
        // Erfolgreiche Antwort senden
        $this->sendSuccessResponse([
            'file' => basename($file),
            'message' => 'Critical CSS erfolgreich gespeichert.',
            'size' => strlen($css),
            'extraction_time' => $extraction_time
        ]);
        exit;
    }

    /**
     * Löscht das Critical CSS
     */
    private function deleteCss()
    {
        $addon = rex_addon::get('css_above_the_fold');
        $token = rex_post('token', 'string', '');
        $viewport = rex_post('viewport', 'string', '');
        $article_id = rex_post('article_id', 'int', 0);
        $clang_id = rex_post('clang_id', 'int', 0);
        
        // Token validieren
        $tokenKey = 'delete_token';
        $expectedToken = $addon->getConfig($tokenKey, null);
        
        if (empty($token) || $token !== $expectedToken) {
            $this->sendErrorResponse('Ungültiger Token für das Löschen.', 403);
            exit;
        }
        
        // Benutzten Token entfernen
        $addon->removeConfig($tokenKey);
        
        if ($article_id <= 0 || $clang_id <= 0 || empty($viewport)) {
            $this->sendErrorResponse('Fehlende erforderliche Parameter für das Löschen.', 400);
            exit;
        }
        
        $file = $this->getCacheFile($viewport, $article_id, $clang_id);
        
        if (!file_exists($file)) {
            $this->sendErrorResponse('CSS-Datei existiert nicht.', 404);
            exit;
        }
        
        if (!rex_file::delete($file)) {
            $this->sendErrorResponse('Fehler beim Löschen der CSS-Datei.', 500);
            exit;
        }
        
        // Statistik ebenfalls löschen
        $statsKey = 'stats_' . $viewport . '_' . $article_id . '_' . $clang_id;
        $addon->removeConfig($statsKey);
        
        // Erfolgreiche Antwort senden
        $this->sendSuccessResponse([
            'message' => 'Critical CSS erfolgreich gelöscht.'
        ]);
        exit;
    }
    
    /**
     * Löscht den gesamten Cache
     */
    private function clearCache()
    {
        $addon = rex_addon::get('css_above_the_fold');
        $token = rex_post('token', 'string', '');
        
        // Token validieren
        $tokenKey = 'clear_cache_token';
        $expectedToken = $addon->getConfig($tokenKey, null);
        
        if (empty($token) || $token !== $expectedToken) {
            $this->sendErrorResponse('Ungültiger Token für das Löschen des Caches.', 403);
            exit;
        }
        
        // Benutzten Token entfernen
        $addon->removeConfig($tokenKey);
        
        $cacheDir = rex_path::addonCache('css_above_the_fold');
        $files = glob($cacheDir . '*.css');
        $count = 0;
        
        if (is_array($files)) {
            foreach ($files as $file) {
                if (rex_file::delete($file)) {
                    $count++;
                }
            }
        }
        
        // Erfolgreiche Antwort senden
        $this->sendSuccessResponse([
            'count' => $count,
            'message' => "Cache erfolgreich gelöscht. {$count} Dateien wurden entfernt."
        ]);
        exit;
    }
    
    /**
     * Sendet eine Erfolgsantwort
     * 
     * @param array $data Die zu sendenden Daten
     */
    private function sendSuccessResponse(array $data)
    {
        $response = array_merge(['status' => 'success'], $data);
        rex_response::setHeader('Content-Type', 'application/json');
        echo json_encode($response);
    }
    
    /**
     * Sendet eine Fehlerantwort
     * 
     * @param string $message Die Fehlermeldung
     * @param int $statusCode Der HTTP-Statuscode
     */
    private function sendErrorResponse($message, $statusCode = 400)
    {
        rex_response::setStatus($statusCode);
        rex_response::setHeader('Content-Type', 'application/json');
        echo json_encode([
            'status' => 'error',
            'message' => $message
        ]);
    }
    
    /**
     * Liefert den Pfad zur Cache-Datei für das Critical CSS
     * 
     * @param string $viewport Der Viewport
     * @param int $article_id Die Artikel-ID
     * @param int $clang_id Die Sprach-ID
     * @return string Pfad zur Cache-Datei
     */
    private function getCacheFile($viewport, $article_id, $clang_id)
    {
        // Viewport-Namen bereinigen, um Directory Traversal oder ungültige Zeichen zu verhindern
        $viewport = preg_replace('/[^a-zA-Z0-9_-]/', '', $viewport);
        
        // IDs als Integers sicherstellen
        $article_id = (int) $article_id;
        $clang_id = (int) $clang_id;
        
        return rex_path::addonCache('css_above_the_fold', $viewport . '_' . $article_id . '_' . $clang_id . '.css');
    }
}
