<?php

/**
 * This file is part of the CssAboveTheFold package.
 *
 * @author Friends Of REDAXO
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace FriendsOfRedaxo\CssAboveTheFold;

if (!\rex::isBackend()) {
    \rex_extension::register('OUTPUT_FILTER', ['\FriendsOfRedaxo\CssAboveTheFold\CssAboveTheFold', 'ext__output_filter'], \rex_extension::LATE);
}