<?php
/**
 * CSS Above The Fold AddOn
 * Hauptseite im Backend
 */

echo rex_view::title($this->i18n('css_above_the_fold_title'));

// Include der entsprechenden Subpage
rex_be_controller::includeCurrentPageSubPath();
