<?php
/**
 * CSS Above The Fold AddOn
 * Einstellungsseite
 */

$addon = rex_addon::get('css_above_the_fold');

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
$field->setLabel($addon->i18n('debug'));
$select = $field->getSelect();
$select->setSize(1);
$select->addOption($addon->i18n('yes'), 1);
$select->addOption($addon->i18n('no'), 0);
$field->setNotice($addon->i18n('debug_info'));

// Wichtige Regeln bewahren
$field = $form->addSelectField('preserve_important_rules');
$field->setLabel($addon->i18n('preserve_important_rules'));
$select = $field->getSelect();
$select->setSize(1);
$select->addOption($addon->i18n('yes'), 1);
$select->addOption($addon->i18n('no'), 0);
$field->setNotice($addon->i18n('preserve_important_rules_info'));

// CSS-Variablen einschließen
$field = $form->addSelectField('include_css_vars');
$field->setLabel($addon->i18n('include_css_vars'));
$select = $field->getSelect();
$select->setSize(1);
$select->addOption($addon->i18n('yes'), 1);
$select->addOption($addon->i18n('no'), 0);
$field->setNotice($addon->i18n('include_css_vars_info'));

// Viewport-Breakpoints
$field = $form->addRawField('<hr><h3>' . $addon->i18n('viewport_settings') . '</h3>');

// Breakpoints als separate Felder
$field = $form->addTextField('breakpoint_xs');
$field->setLabel($addon->i18n('breakpoint_xs'));
$field->setAttribute('type', 'number');

$field = $form->addTextField('breakpoint_sm');
$field->setLabel($addon->i18n('breakpoint_sm'));
$field->setAttribute('type', 'number');

$field = $form->addTextField('breakpoint_md');
$field->setLabel($addon->i18n('breakpoint_md'));
$field->setAttribute('type', 'number');

$field = $form->addTextField('breakpoint_lg');
$field->setLabel($addon->i18n('breakpoint_lg'));
$field->setAttribute('type', 'number');

$field = $form->addTextField('breakpoint_xl');
$field->setLabel($addon->i18n('breakpoint_xl'));
$field->setAttribute('type', 'number');

$field = $form->addTextField('breakpoint_xxl');
$field->setLabel($addon->i18n('breakpoint_xxl'));
$field->setAttribute('type', 'number');

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
