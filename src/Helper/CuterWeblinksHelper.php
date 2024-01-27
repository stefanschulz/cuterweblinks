<?php
/**
 * @package    TheLoom.Module.CuterWeblinks
 *
 * @author     Stefan <schulz@the-loom.de>
 * @copyright  Copyright (C) 2023 The Loom / Stefan Schulz
 * @license    GNU General Public License version 3 or later; see LICENSE.txt
 * @link       https://www.the-loom.de
 */

namespace TheLoom\Module\CuterWeblinks\Site\Helper;

use JHtml;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

defined('_JEXEC') or die;

/**
 * Helper for mod_cuterweblinks
 *
 * @since 1.0
 */
class CuterWeblinksHelper
{

    /**
     * Retrieve list of links from Weblinks.
     *
     * @param Registry $params The module parameters
     * @param CMSApplicationInterface $app The application
     *
     * @return mixed An array of links or {@code null}, if no links were found.
     *
     * @since 1.0
     **/
    public static function getLinks(Registry $params, CMSApplicationInterface $app): mixed
    {
        // Determine required fields and parameters
        $fields = ['weblinks.id', 'weblinks.title', 'weblinks.url'];
        if ($params->get('image', false)) {
            $fields[] = 'weblinks.images';
        }
        if ($params->get('hits', false)) {
            $fields[] = 'weblinks.hits';
        }
        if ($params->get('description', false)) {
            $fields[] = 'weblinks.description';
        }
        $categories = array_unique($params->get('categories', ['all']));
        $listAllCategories = in_array('all', $categories);
        if (!$listAllCategories) {
            $fields[] = 'weblinks.catid';
        }
        $limit = $params->get('count', 5);
        $ordering = $params->get('ordering');
        $direction = $params->get('direction');

        // Setup and run DB query
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select($db->quoteName($fields));
        if ($params->get('category', false)) {
            $query->select($db->quoteName('categories.title', 'category'));
        }
        $query->from($db->quoteName('#__weblinks', 'weblinks'));
        $query->leftJoin(
            $db->quoteName('#__categories', 'categories')
            . ' ON ' . $db->quoteName('categories.id') . ' = ' . $db->quoteName('weblinks.catid')
        );
        $query->where($db->quoteName('weblinks.state') . ' = 1');
        $query->where($db->quoteName('categories.published') . ' = 1');
        if (!$listAllCategories) {
            $query->where($db->quoteName('weblinks.catid') . ' IN (' . implode(',', $categories) . ')');
        }
        if (is_numeric($limit)) {
            $query->setLimit(intval($limit));
        }
        $query->order($db->quoteName('weblinks.' . $ordering) . ' ' . $direction);
        $db->setQuery($query);
        $links = $db->loadObjectList();
        // postprocessing results to clean up title, determine target reference and resolve image url
        if (!empty($links)) {
            foreach ($links as $link) {
                $link->title = strip_tags($link->title);
                $link->reference = Route::_('index.php?option=com_weblinks&task=weblink.go&id=' . $link->id);
                $link->imageUrl = null;
                if (!empty($link->images)) {
                    $imgContainer = json_decode($link->images);
                    $url = null;
                    if (!empty($imgContainer->image_first)) {
                        $url = $imgContainer->image_first;
                    } elseif (!empty($imgContainer->image_second)) {
                        $url = $imgContainer->image_second;
                    }
                    if (!empty($url)) {
                        $joomlaImageStart = strpos($url, '#');
                        if ($joomlaImageStart !== false) {
                            $url = substr($url, 0, $joomlaImageStart);
                        }
                        $link->imageUrl = $url;
                    }
                }
            }
        }
        return $links;
    }

    /**
     * Determine the layout-related stylesheet, if it exists.
     *
     * @param $module string This module.
     * @param $layout string The layout to be applied.
     * @return string|null The resulting style filename or <code>null</code>.
     *
     * @since 1.0
     */
    public static function getStyleFilename(string $module, string $layout = 'default'): ?string
    {
        if (str_contains($layout, ':')) {
            // Get the template and file name from the string
            [, $layout] = explode(':', $layout);
        }
        $stylesheet = JHtml::stylesheet($module . '/' . $layout . '_style.css', ['relative' => true, 'pathOnly' => true]);
        if (empty($stylesheet)) {
            $stylesheet = JHtml::stylesheet($module . '/default_style.css', ['relative' => true, 'pathOnly' => true]);
        }
        if (str_starts_with($stylesheet, '/')) {
            $stylesheet = substr($stylesheet, 1);
        }
        return $stylesheet;
    }

}