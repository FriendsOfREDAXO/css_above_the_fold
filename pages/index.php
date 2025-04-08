<?php

/**
 * CSS Above The Fold AddOn
 * Hauptseite im Backend
 */

echo \rex_view::title($this->i18n('title'));

// Include der entsprechenden Subpage
include \rex_be_controller::getCurrentPageObject()->getSubPath();
