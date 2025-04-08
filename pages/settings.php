<?php

/**
 * CSS Above The Fold AddOn
 * Einstellungsseite
 */

use FriendsOfRedaxo\CssAboveTheFold\CssAboveTheFold;
use rex_addon;
use rex_config_form;
use rex_fragment;
use rex_i18n;
use rex_url;
use rex_view;

$addon = rex_addon::get('css_above_the_fold');
$func = rex_request('func', 'string', '');
$file = rex_request('file', 'string', '');

// Cache-Aktionen
if ($func === 'delete_all_cache') {
    // Alle Cache-Dateien löschen
    $count = CssAboveTheFold::deleteAllCacheFiles();
    echo rex_view::success($addon->i18n('cache_deleted_all', $count));
    
} elseif ($func === 'delete_cache' && !empty($file)) {
    // Einzelne Cache-Datei löschen
    if (CssAboveTheFold::deleteCacheFile($file)) {
        echo rex_view::success($addon->i18n('cache_deleted_file', $file));
    } else {
        echo rex_view::error($addon->i18n('cache_delete_error', $file));
    }
}

// Einstellungen
$form = rex_config_form::factory('css_above_the_fold');

// AddOn aktivieren
$field = $form->addSelectField('active');
$field->setLabel($addon->i18n('active'));
$select = $field->getSelect();
$select->setSize(1);
$select->addOption($addon->i18n('yes'), 1);
$select->addOption($addon->i18n('no'), 0);
$field->setNotice($addon->i18n('active_info'));

// CSS asynchron laden
$field = $form->addSelectField('load_css_async');
$field->setLabel($addon->i18n('load_css_async'));
$select = $field->getSelect();
$select->setSize(1);
$select->addOption($addon->i18n('yes'), 1);
$select->addOption($addon->i18n('no'), 0);
$field->setNotice($addon->i18n('load_css_async_info'));

// Debug-Modus
$field = $form->addSelectField('debug');
$field->setLabel('Debug-Modus');
$select = $field->getSelect();
$select->setSize(1);
$select->addOption($addon->i18n('yes'), 1);
$select->addOption($addon->i18n('no'), 0);
$field->setNotice('Aktiviert ausführliche Logging-Informationen für die Fehlersuche.');

// Viewport-Breakpoints
$field = $form->addRawField('<hr><h3>' . $addon->i18n('viewport_settings') . '</h3>');

// Breakpoints als separate Felder
$breakpoints = $addon->getConfig('breakpoints', [
    'xs' => 375,
    'sm' => 640,
    'md' => 768,
    'lg' => 1024,
    'xl' => 1280,
    'xxl' => 1536
]);

foreach ($breakpoints as $name => $value) {
    $field = $form->addTextField('breakpoints[' . $name . ']');
    $field->setLabel($addon->i18n($name));
    $field->setAttribute('type', 'number');
    $field->setValue($value);
}

// Selektoren
$field = $form->addRawField('<hr><h3>' . $addon->i18n('selectors_info') . '</h3>');

// Immer einschließen
$field = $form->addTextAreaField('always_include_selectors');
$field->setLabel($addon->i18n('always_include'));
$field->setNotice($addon->i18n('always_include_info'));

// Nie einschließen
$field = $form->addTextAreaField('never_include_selectors');
$field->setLabel($addon->i18n('never_include'));
$field->setNotice($addon->i18n('never_include_info'));

// Formular ausgeben
$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', $addon->i18n('settings'), false);
$fragment->setVar('body', $form->get(), false);
echo $fragment->parse('core/page/section.php');

// Cache-Verwaltung
$content = '<p>' . $addon->i18n('cache_management_info') . '</p>';
$content .= '<p><a class="btn btn-primary" href="' . rex_url::currentBackendPage(['func' => 'delete_all_cache']) . '">' . $addon->i18n('delete_all_cache') . '</a></p>';

$fragment = new rex_fragment();
$fragment->setVar('class', 'info', false);
$fragment->setVar('title', $addon->i18n('cache_management'), false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');

// Cache-Dateien auflisten
$cacheFiles = CssAboveTheFold::getCacheFiles();

if (!empty($cacheFiles)) {
    $tableContent = '<table class="table table-hover">';
    $tableContent .= '<thead>
        <tr>
            <th>' . $addon->i18n('cache_file') . '</th>
            <th>' . $addon->i18n('cache_viewport') . '</th>
            <th>' . $addon->i18n('cache_article') . '</th>
            <th>' . $addon->i18n('cache_language') . '</th>
            <th>' . $addon->i18n('cache_size') . '</th>
            <th>' . $addon->i18n('cache_date') . '</th>
            <th>' . $addon->i18n('cache_actions') . '</th>
        </tr>
    </thead>';
    
    $tableContent .= '<tbody>';
    
    foreach ($cacheFiles as $file) {
        // Lösch-Link
        $delete_url = rex_url::currentBackendPage(['func' => 'delete_cache', 'file' => $file['filename']]);
        
        $tableContent .= '<tr>
            <td>' . $file['filename'] . '</td>
            <td>' . $file['viewport'] . '</td>
            <td>' . $file['article_name'] . ' [' . $file['article_id'] . ']</td>
            <td>' . $file['clang_name'] . ' [' . $file['clang_id'] . ']</td>
            <td>' . $file['size_formatted'] . '</td>
            <td>' . $file['modified_formatted'] . '</td>
            <td>
                <a href="' . $delete_url . '" class="btn btn-delete btn-xs" data-confirm="' . $addon->i18n('cache_delete_confirm') . '">
                    <i class="rex-icon rex-icon-delete"></i> ' . $addon->i18n('delete') . '
                </a>
            </td>
        </tr>';
    }
    
    $tableContent .= '</tbody>';
    $tableContent .= '</table>';
    
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
