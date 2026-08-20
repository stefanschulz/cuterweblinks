<?php
/**
 * @package    TheLoom.Module.CuterWeblinks
 *
 * @author     Stefan <schulz@the-loom.de>
 * @copyright  Copyright (C) 2026 The Loom / Stefan Schulz
 * @license    GNU General Public License version 3 or later; see LICENSE.txt
 * @link       https://www.the-loom.de
 */

namespace TheLoom\Module\CuterWeblinks\Site\Field;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

defined('_JEXEC') or die;

/**
 * Renders a warning in the module edit form if the Weblinks component
 * (com_weblinks) is not installed or not enabled. Renders nothing otherwise.
 *
 * @since 1.6.0
 */
class WeblinksstatusField extends FormField
{
    /**
     * @var string
     * @since 1.6.0
     */
    protected $type = 'Weblinksstatus';

    /**
     * @return string
     *
     * @since 1.6.0
     */
    protected function getInput(): string
    {
        if (ComponentHelper::isEnabled('com_weblinks')) {
            return '';
        }

        return '<div class="alert alert-warning">' . Text::_('MOD_CUTERWEBLINKS_FIELD_WEBLINKSSTATUS_WARNING') . '</div>';
    }

    /**
     * @return string
     *
     * @since 1.6.0
     */
    protected function getLabel(): string
    {
        return '';
    }
}
