<?php

/**
 * @package    TheLoom.Module.CuterWeblinks
 *
 * @copyright  Copyright (C) 2026 The Loom / Stefan Schulz. All rights reserved.
 * @license    GNU General Public License version 3 or later; see LICENSE.txt
 * @link       https://www.the-loom.de
 */

// The files under test guard themselves with `defined('_JEXEC') or die`, like every file in a
// Joomla extension.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or \define('_JEXEC', 1);

// Integration tests: .docker/test.sh sets JOOMLA_ROOT and boots the real Joomla framework of
// the test stack, so the query runs against the real database. Without it (composer run test on
// the host) only the Unit suite is meant to run and the Integration tests skip themselves.
$joomlaRoot = getenv('JOOMLA_ROOT');

if ($joomlaRoot && is_file($joomlaRoot . '/includes/framework.php')) {
    \define('JPATH_BASE', $joomlaRoot);

    // Under CLI Uri::root() and friends need something to work with.
    $_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST'] ?? 'cuterweblinks.localhost';
    $_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';

    require_once JPATH_BASE . '/includes/defines.php';
    require_once JPATH_BASE . '/includes/framework.php';
}

// Composer's autoloader goes last on purpose: it registers itself first in the autoload chain, so
// the module classes come from this working tree - not from a (possibly older) copy that is
// installed in the Joomla instance.
require_once __DIR__ . '/../vendor/autoload.php';
// phpcs:enable PSR1.Files.SideEffects
