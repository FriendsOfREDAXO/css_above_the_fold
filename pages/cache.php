<?php
/**
 * CSS Above The Fold AddOn
 * Cache-Verwaltungsseite
 */

$addon = rex_addon::get('css_above_the_fold');
$func = rex_request('func', 'string', '');
$file = rex_request('file', 'string', '');

// Cache-Aktionen
if ($func === 'delete_all_cache') {
    // Alle Cache-Dateien löschen
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
    
    echo rex_view::success($addon->i18n('cache_deleted_all', $count));
    
} elseif ($func === 'delete_cache' && !empty($file)) {
    // Einzelne Cache-Datei löschen
    $cacheFile = rex_path::addonCache('css_above_the_fold', $file);
    
    // Prüfen, ob die Datei existiert und im richtigen Verzeichnis liegt
    if (strpos(realpath($cacheFile), realpath(rex_path::addonCache('css_above_the_fold'))) === 0 && file_exists($cacheFile)) {
        if (rex_file::delete($cacheFile)) {
            echo rex_view::success($addon->i18n('cache_deleted_file', $file));
        } else {
            echo rex_view::error($addon->i18n('cache_delete_error', $file));
        }
    } else {
        echo rex_view::error($addon->i18n('cache_delete_error', $file));
    }
} elseif ($func === 'warm_cache') {
    // Cache aufwärmen (Basis-Implementierung - lädt nur die Startseite)
    $token = bin2hex(random_bytes(16));
    
    echo rex_view::info('
        <div class="progress">
            <div class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
        </div>
        <div id="warm-status">Bereite Cache-Warming vor...</div>
        <script>
            (function() {
                const viewports = ["xs", "sm", "md", "lg", "xl", "xxl"];
                const progress = document.querySelector(".progress-bar");
                const status = document.getElementById("warm-status");
                let current = 0;
                
                function warmViewport(viewport) {
                    status.textContent = "Warme Cache für Viewport: " + viewport;
                    
                    const iframe = document.createElement("iframe");
                    iframe.style.width = viewport === "xs" ? "375px" : 
                                         viewport === "sm" ? "640px" : 
                                         viewport === "md" ? "768px" : 
                                         viewport === "lg" ? "1024px" : 
                                         viewport === "xl" ? "1280px" : "1536px";
                    iframe.style.height = "800px";
                    iframe.style.position = "absolute";
                    iframe.style.opacity = "0.01";
                    iframe.style.pointerEvents = "none";
                    
                    // Cache-Busting mit Token
                    iframe.src = "' . rex_url::frontendController() . '?cache_token=' . $token . '&viewport=" + viewport;
                    
                    document.body.appendChild(iframe);
                    
                    setTimeout(function() {
                        document.body.removeChild(iframe);
                        current++;
                        const percent = Math.round((current / viewports.length) * 100);
                        progress.style.width = percent + "%";
                        progress.setAttribute("aria-valuenow", percent);
                        progress.textContent = percent + "%";
                        
                        if (current < viewports.length) {
                            warmViewport(viewports[current]);
                        } else {
                            status.textContent = "Cache-Warming abgeschlossen!";
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        }
                    }, 5000);  // 5 Sekunden pro Viewport
                }
                
                // Starten
                setTimeout(function() {
                    warmViewport(viewports[0]);
                }, 1000);
            })();
        </script>
    ');
}

// Cache-Verwaltung
$content = '<p>' . $addon->i18n('cache_management_info') . '</p>';
$content .= '<p>
    <a class="btn btn-danger" href="' . rex_url::currentBackendPage(['func' => 'delete_all_cache']) . '">' . $addon->i18n('delete_all_cache') . '</a>
    <a class="btn btn-primary" href="' . rex_url::currentBackendPage(['func' => 'warm_cache']) . '">' . $addon->i18n('cache_warm') . '</a>
</p>';

$fragment = new rex_fragment();
$fragment->setVar('class', 'info', false);
$fragment->setVar('title', $addon->i18n('cache_management'), false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

// Cache-Dateien auflisten
$cacheDir = rex_path::addonCache('css_above_the_fold');
$files = glob($cacheDir . '*.css');
$cacheFiles = [];

if (is_array($files)) {
    foreach ($files as $file) {
        $filename = basename($file);
        $parts = explode('_', $filename);
        
        $viewport = $parts[0] ?? '';
        $article_id = isset($parts[1]) ? (int)$parts[1] : 0;
        $clang_id = isset($parts[2]) ? (int)str_replace('.css', '', $parts[2]) : 0;
        
        // Artikel- und Sprachinformationen abrufen
        $article = rex_article::get($article_id);
        $article_name = $article ? $article->getName() : $addon->i18n('unknown_article');
        
        $clang = rex_clang::get($clang_id);
        $clang_name = $clang ? $clang->getName() : $addon->i18n('unknown_language');
        
        // Dateigröße und Datum
        $size = filesize($file);
        $size_formatted = $size < 1024 ? $size . ' B' : round($size / 1024, 2) . ' KB';
        
        $modified = filemtime($file);
        
        $cacheFiles[] = [
            'filename' => $filename,
            'viewport' => $viewport,
            'article_id' => $article_id,
            'article_name' => $article_name,
            'clang_id' => $clang_id,
            'clang_name' => $clang_name,
            'size' => $size,
            'size_formatted' => $size_formatted,
            'modified' => $modified,
            'modified_formatted' => date('Y-m-d H:i:s', $modified)
        ];
    }
}

if (!empty($cacheFiles)) {
    $tableContent = '<table class="table table-hover">
        <thead>
            <tr>
                <th>' . $addon->i18n('cache_file') . '</th>
                <th>' . $addon->i18n('cache_viewport') . '</th>
                <th>' . $addon->i18n('cache_article') . '</th>
                <th>' . $addon->i18n('cache_language') . '</th>
                <th>' . $addon->i18n('cache_size') . '</th>
                <th>' . $addon->i18n('cache_date') . '</th>
                <th>' . $addon->i18n('cache_actions') . '</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($cacheFiles as $file) {
        // Lösch-Link
        $delete_url = rex_url::currentBackendPage(['func' => 'delete_cache', 'file' => $file['filename']]);
        
        $tableContent .= '<tr>
            <td>' . rex_escape($file['filename']) . '</td>
            <td>' . rex_escape($file['viewport']) . '</td>
            <td>' . rex_escape($file['article_name']) . ' [' . $file['article_id'] . ']</td>
            <td>' . rex_escape($file['clang_name']) . ' [' . $file['clang_id'] . ']</td>
            <td>' . rex_escape($file['size_formatted']) . '</td>
            <td>' . rex_escape($file['modified_formatted']) . '</td>
            <td>
                <a href="' . $delete_url . '" 
                   class="btn btn-delete btn-xs" 
                   data-confirm="' . $addon->i18n('cache_delete_confirm') . '">
                    <i class="rex-icon rex-icon-delete"></i> ' . $addon->i18n('delete') . '
                </a>
            </td>
        </tr>';
    }
    
    $tableContent .= '</tbody></table>';
    
    $fragment = new rex_fragment();
    $fragment->setVar('title', $addon->i18n('cache_files') . ' (' . count($cacheFiles) . ')', false);
    $fragment->setVar('body', $tableContent, false);
    echo $fragment->parse('core/page/section.php');
} else {
    // Keine Cache-Dateien vorhanden
    $fragment = new rex_fragment();
    $fragment->setVar('class', 'info', false);
    $fragment->setVar('title', $addon->i18n('cache_files'), false);
    $fragment->setVar('body', '<p>' . $addon->i18n('no_cache_files') . '</p>', false);
    echo $fragment->parse('core/page/section.php');
}

// Leistungs-Indikatoren und Statistiken anzeigen, wenn vorhanden
$statsEntries = [];
foreach ($addon->getConfig() as $key => $value) {
    if (strpos($key, 'stats_') === 0 && is_array($value)) {
        $statsEntries[] = $value;
    }
}

if (!empty($statsEntries)) {
    $statsContent = '<div class="row">';
    
    // Durchschnittliche Extraktionszeit
    $totalTime = 0;
    $count = count($statsEntries);
    foreach ($statsEntries as $entry) {
        $totalTime += $entry['extraction_time'] ?? 0;
    }
    $avgTime = $count > 0 ? round($totalTime / $count) : 0;
    
    $statsContent .= '<div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-heading">' . $addon->i18n('extraction_time') . '</div>
            <div class="panel-body">
                <h3>' . $avgTime . ' ms</h3>
                <p>' . $addon->i18n('extraction_time_info') . '</p>
            </div>
        </div>
    </div>';
    
    // Letzte Extraktion
    $lastExtraction = 0;
    foreach ($statsEntries as $entry) {
        if (($entry['date'] ?? 0) > $lastExtraction) {
            $lastExtraction = $entry['date'];
        }
    }
    
    $statsContent .= '<div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-heading">' . $addon->i18n('last_extraction') . '</div>
            <div class="panel-body">
                <h3>' . ($lastExtraction > 0 ? date('Y-m-d H:i', $lastExtraction) : '-') . '</h3>
            </div>
        </div>
    </div>';
    
    // Durchschnittliche Größe
    $totalSize = 0;
    foreach ($statsEntries as $entry) {
        $totalSize += $entry['size'] ?? 0;
    }
    $avgSize = $count > 0 ? round($totalSize / $count / 1024, 1) : 0;
    
    $statsContent .= '<div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-heading">' . $addon->i18n('css_size_critical') . '</div>
            <div class="panel-body">
                <h3>' . $avgSize . ' KB</h3>
            </div>
        </div>
    </div>';
    
    $statsContent .= '</div>';
    
    $fragment = new rex_fragment();
    $fragment->setVar('title', $addon->i18n('performance_stats'), false);
    $fragment->setVar('body', $statsContent, false);
    echo $fragment->parse('core/page/section.php');
}
