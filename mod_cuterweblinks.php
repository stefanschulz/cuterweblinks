<?php
/**
 * @package    TheLoom.Module.CuterWeblinks
 *
 * @author     Stefan <schulz@the-loom.de>
 * @copyright  Copyright (C) 2023 The Loom / Stefan Schulz
 * @license    GNU General Public License version 3 or later; see LICENSE.txt
 * @link       https://www.the-loom.de
 */

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\Registry\Registry;
use TheLoom\Module\CuterWeblinks\Site\Helper\CuterWeblinksHelper;

/** @var CMSApplicationInterface $app */
/** @var Registry $params */
/** @var Joomla\CMS\WebAsset\WebAssetManager $webAssetManager */

$webAssetManager = $app->getDocument()->getWebAssetManager();

$links = CuterWeblinksHelper::getLinks($params, $app);
if (empty($links)) {
    return;
}

$layout = $params->get('layout', 'default');
$styleFilename = CuterWeblinksHelper::getStyleFilename('mod_cuterweblinks', $layout);
$webAssetManager->registerAndUseStyle('mod_cuterweblinks.' . $layout, $styleFilename, ['relative' => true]);

require ModuleHelper::getLayoutPath('mod_cuterweblinks', $layout);
