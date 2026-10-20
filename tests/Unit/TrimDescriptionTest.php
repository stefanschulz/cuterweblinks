<?php

/**
 * @package    TheLoom.Module.CuterWeblinks
 *
 * @copyright  Copyright (C) 2026 The Loom / Stefan Schulz. All rights reserved.
 * @license    GNU General Public License version 3 or later; see LICENSE.txt
 * @link       https://www.the-loom.de
 */

namespace TheLoom\Module\CuterWeblinks\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheLoom\Module\CuterWeblinks\Site\Helper\CuterWeblinksHelper;

/**
 * description_trim: HTML description -> plain text of at most N characters.
 */
class TrimDescriptionTest extends TestCase
{
    /**
     * @return array<string, array{string, int, string}>
     */
    public static function cases(): array
    {
        return [
            // 1.6.1: strip_tags() alone glued "end.</p><p>Next" into "end.Next"
            'paragraphs are separated by a space' => ['<p>First.</p><p>Second.</p>', 100, 'First. Second.'],
            'br is a word boundary'              => ['one<br>two<br/>three', 100, 'one two three'],
            'list items are separated'           => ['<ul><li>one</li><li>two</li></ul>', 100, 'one two'],
            'headings and divs'                  => ['<h2>Title</h2><div>Body</div>', 100, 'Title Body'],
            'block tag with attributes'          => ['<p class="x">Hi</p>   <div>there</div>', 100, 'Hi there'],
            'uppercase tags'                     => ['<P>A</P><P>B</P>', 100, 'A B'],
            // inline tags must not split words
            'inline tags keep the word whole'    => ['Joom<b>la</b> rocks', 100, 'Joomla rocks'],
            'link text stays attached'           => ['see <a href="/x">this</a>, please', 100, 'see this, please'],
            // length handling
            'shorter than the limit: no ellipsis' => ['<p>short</p>', 100, 'short'],
            'exactly the limit: no ellipsis'      => ['abcde', 5, 'abcde'],
            'empty input stays empty'             => ['', 10, ''],
            'only tags stays empty'               => ['<p> </p><br>', 10, ''],
            'cut at the last whole word'          => ['alpha beta gamma delta', 12, 'alpha beta…'],
            // Pinned, not endorsed: the cut looks for the last space *inside* the first N characters, so a
            // word that ends exactly at the limit is dropped too ("alpha beta" is 10 chars, yet becomes "alpha").
            // That is what the spec asks for (never cut mid-word); changing it to keep such a word is a
            // deliberate behaviour change and should change this expectation with it.
            'word ending exactly at the limit is dropped' => ['alpha beta gamma', 10, 'alpha…'],
            'single long word is cut hard'        => ['abcdefghijkl', 5, 'abcde…'],
            'whitespace is collapsed first'       => ["alpha \n\t  beta", 100, 'alpha beta'],
            // multibyte: limits count characters, not bytes
            'umlauts count as one character'      => ['äöü äöü äöü', 8, 'äöü äöü…'],
            'multibyte text is not cut in half'   => ['Grüße aus Köln am Rhein', 11, 'Grüße aus…'],
        ];
    }

    #[DataProvider('cases')]
    public function testTrim(string $html, int $limit, string $expected): void
    {
        $this->assertSame($expected, CuterWeblinksHelper::trimDescription($html, $limit));
    }

    public function testResultIsNeverLongerThanLimitPlusEllipsis(): void
    {
        $html = '<p>' . str_repeat('lorem ipsum dolor ', 40) . '</p>';

        foreach ([1, 7, 20, 64, 140, 500] as $limit) {
            $result = CuterWeblinksHelper::trimDescription($html, $limit);

            $this->assertLessThanOrEqual($limit + 1, mb_strlen($result), "limit $limit");
            $this->assertStringNotContainsString('<', $result);
        }
    }
}
