<?php
/**
 * @package    TheLoom.Module.CuterWeblinks
 *
 * @author     Stefan <schulz@the-loom.de>
 * @copyright  Copyright (C) 2023 The Loom / Stefan Schulz
 * @license    GNU General Public License version 3 or later; see LICENSE.txt
 * @link       https://www.the-loom.de
 */

use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

defined('_JEXEC') or die;

/** @var array $links */
/** @var Registry $params */

?>
<?php
$prologue = trim($params->get('prologue', ''));
if (!empty($prologue)) :
    ?>
    <div class="prologue">
        <?php echo $prologue; ?>
    </div>
<?php
endif; // prologue
?>
<ul class="cuterweblinks">
    <?php
    $usingGrid = $params->get('image', false) ? ' class="using-grid"' : '';
    $target = $params->get('target', 0) == 1 ? '_blank' : '_top';
    foreach ($links as $link) :
        $id = $link->id;
        $title = $link->title;
        $description = $link->description ?? '';
        $category = $link->category ?? '';
        $hits = $link->hits ?? '';
        $reference = $link->reference;
        $imageUrl = $link->imageUrl;
        ?>
        <li<?php echo $usingGrid; ?>>
            <a class="cuterweblink" href="<?php echo $reference; ?>" target="<?php echo $target; ?>">
                <?php if (!empty($imageUrl)) : ?>
                    <div class="image" style="background-image: url(<?php echo $imageUrl; ?>)"></div>
                <?php endif; ?>
                <div class="content">
                    <?php if (is_numeric($hits)) : ?>
                        <?php $hitKey = intval($hits) == 1 ? 'MOD_CUTERWEBLINKS_HIT' : 'MOD_CUTERWEBLINKS_HITS' ?>
                        <div class="hits">
                            <span class="badge bg-info"><?php echo $hits . ' ' . Text::_($hitKey); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($category)) : ?>
                        <div class="category"><?php echo $category; ?></div>
                    <?php endif; ?>
                    <div class="title"><?php echo $title; ?></div>
                    <?php if (!empty($description)) : ?>
                        <div class="description"><?php echo $description; ?></div>
                    <?php endif; ?>
                </div>
            </a>
        </li>
    <?php
    endforeach;
    ?>
</ul>
<?php
$epilogue = trim($params->get('epilogue', ''));
if (!empty($epilogue)) :
    ?>
    <div class="epilogue">
        <?php echo $epilogue; ?>
    </div>
<?php
endif; // epilogue
?>
