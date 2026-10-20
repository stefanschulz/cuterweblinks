<?php

/**
 * @package    TheLoom.Module.CuterWeblinks
 *
 * @copyright  Copyright (C) 2026 The Loom / Stefan Schulz. All rights reserved.
 * @license    GNU General Public License version 3 or later; see LICENSE.txt
 * @link       https://www.the-loom.de
 */

namespace TheLoom\Module\CuterWeblinks\Tests\Integration;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheLoom\Module\CuterWeblinks\Site\Helper\CuterWeblinksHelper;

/**
 * CuterWeblinksHelper::getLinks() against the real database of the .docker/ test stack.
 *
 * Deliberately no database mock: what has to be right here is the SQL - access levels, language,
 * ordering, category filter - and a mock would only assert that the query looks the way it was
 * written, not that it returns the right rows. Run with .docker/test.sh.
 *
 * The expectations are the seed fixture in .docker/seed/weblinks-seed.sql (re-import it with
 * .docker/import-seed.sh if a test fails because someone has been editing the data). Rows are
 * identified by title, never by id, since ids differ between installs.
 *
 *   category      access  links
 *   Public        1       Joomla, Long Description Example, No Description Example, German Only Link (de-DE),
 *                         Featured Link (99 hits), Special Access Link (link-level access 3)
 *   Registered    2       Registered Only Link
 *   Special       3       (none)
 *   Unpublished   1       Link In Unpublished Category (the category is unpublished)
 *   (Public)              also: Unpublished Link (state 0)
 */
class LinksTest extends TestCase
{
    /** View levels per visitor type, as User::getAuthorisedViewLevels() would return them. */
    private const GUEST      = [1, 5];
    private const REGISTERED = [1, 2];
    private const SPECIAL    = [1, 2, 3];

    private const PUBLIC_LINKS = ['Featured Link', 'German Only Link', 'Joomla', 'Long Description Example', 'No Description Example'];

    protected function setUp(): void
    {
        if (!\defined('JPATH_BASE') || !class_exists(Factory::class)) {
            $this->markTestSkipped('Needs the Joomla install of the test stack - run .docker/test.sh.');
        }

        // Route::_() builds the link through the site router, which would need a running SiteApplication
        // (the container cannot create one under CLI). A pass-through stand-in, put in the router cache
        // Route keeps per client, is enough: what is checked here is the internal URL the helper hands over.
        $cache = new \ReflectionProperty(Route::class, '_router');
        $cache->setValue(null, ['site' => new class {
            public function build(string $url): Uri
            {
                return new Uri($url);
            }
        }]);
    }

    /**
     * @param int[] $levels
     */
    private function app(array $levels = self::GUEST, string $language = 'en-GB'): CMSApplicationInterface
    {
        $user = $this->createStub(User::class);
        $user->method('getAuthorisedViewLevels')->willReturn($levels);

        $lang = $this->createStub(Language::class);
        $lang->method('getTag')->willReturn($language);

        $app = $this->createStub(CMSApplicationInterface::class);
        $app->method('getName')->willReturn('site');
        $app->method('getIdentity')->willReturn($user);
        $app->method('getLanguage')->willReturn($lang);

        return $app;
    }

    /**
     * @param array<string, mixed> $params
     * @param int[]                $levels
     *
     * @return object[]
     */
    private function links(array $params = [], array $levels = self::GUEST, string $language = 'en-GB'): array
    {
        $params += ['count' => 50];
        $app = $this->app($levels, $language);
        // Route::_() asks the global application for its client name
        Factory::$application = $app;
        $links = CuterWeblinksHelper::getLinks(new Registry($params), $app);

        $this->assertIsArray($links, 'getLinks() returned null - did the query fail?');

        return $links;
    }

    /**
     * @param array<string, mixed> $params
     * @param int[]                $levels
     *
     * @return string[]
     */
    private function titles(array $params = [], array $levels = self::GUEST, string $language = 'en-GB'): array
    {
        return array_map(static fn ($link) => $link->title, $this->links($params, $levels, $language));
    }

    private function link(string $title, array $params = [], array $levels = self::SPECIAL): object
    {
        foreach ($this->links($params, $levels) as $link) {
            if ($link->title === $title) {
                return $link;
            }
        }

        $this->fail("No link titled '$title' in the result - seed data changed?");
    }

    private function categoryId(string $title): int
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_weblinks'))
            ->where($db->quoteName('title') . ' = ' . $db->quote($title));

        return (int) $db->setQuery($query)->loadResult();
    }

    // -- access levels (1.5.1) ---------------------------------------------------------------------------------

    public function testGuestSeesPublicLinksOnly(): void
    {
        $this->assertSame(self::PUBLIC_LINKS, $this->titles());
    }

    public function testRegisteredAlsoSeesLinksInRegisteredCategory(): void
    {
        $this->assertSame(
            ['Featured Link', 'German Only Link', 'Joomla', 'Long Description Example', 'No Description Example', 'Registered Only Link'],
            $this->titles([], self::REGISTERED)
        );
    }

    public function testSpecialAlsoSeesLinkRestrictedOnLinkLevel(): void
    {
        $this->assertSame(
            [
                'Featured Link', 'German Only Link', 'Joomla', 'Long Description Example', 'No Description Example',
                'Registered Only Link', 'Special Access Link',
            ],
            $this->titles([], self::SPECIAL)
        );
    }

    public function testCategoryAccessIsCheckedIndependentlyOfLinkAccess(): void
    {
        // "Registered Only Link" is public itself; only its category is restricted.
        $this->assertNotContains('Registered Only Link', $this->titles());
        // "Special Access Link" sits in a public category; only the link is restricted.
        $this->assertNotContains('Special Access Link', $this->titles([], self::REGISTERED));
    }

    public function testUnpublishedLinksAndCategoriesAreNeverListed(): void
    {
        // even for a visitor who is allowed to see every access level
        $titles = $this->titles([], self::SPECIAL);

        $this->assertNotContains('Unpublished Link', $titles);
        $this->assertNotContains('Link In Unpublished Category', $titles);
        $this->assertSame(
            [],
            $this->titles(['categories' => [(string) $this->categoryId('Unpublished Category')]], self::SPECIAL)
        );
    }

    // -- filters -----------------------------------------------------------------------------------------------

    public function testLanguageFilterOffShowsEveryLanguage(): void
    {
        $this->assertContains('German Only Link', $this->titles(['languages' => 0]));
    }

    #[DataProvider('languageCases')]
    public function testLanguageFilterOn(string $siteLanguage, bool $germanLinkVisible): void
    {
        $titles = $this->titles(['languages' => 1], self::GUEST, $siteLanguage);

        $this->assertSame($germanLinkVisible, \in_array('German Only Link', $titles, true));
        // "*" links are always shown
        $this->assertContains('Joomla', $titles);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function languageCases(): array
    {
        return [
            'site language en-GB hides the de-DE link' => ['en-GB', false],
            'site language de-DE shows the de-DE link' => ['de-DE', true],
        ];
    }

    public function testCategoryFilter(): void
    {
        $this->assertSame(
            self::PUBLIC_LINKS,
            $this->titles(['categories' => [(string) $this->categoryId('Public Category')]])
        );
        // Registered category exists, but a guest may not see anything in it
        $this->assertSame([], $this->titles(['categories' => [(string) $this->categoryId('Registered Category')]]));
        $this->assertSame(
            ['Registered Only Link'],
            $this->titles(['categories' => [(string) $this->categoryId('Registered Category')]], self::REGISTERED)
        );
    }

    public function testAllCategoriesIsTheDefault(): void
    {
        $this->assertSame($this->titles(), $this->titles(['categories' => ['all']]));
    }

    public function testCountLimitsTheResult(): void
    {
        $this->assertCount(2, $this->links(['count' => 2]));
        $this->assertCount(1, $this->links(['count' => 1]));
    }

    // -- ordering (1.5.1: defaults when the params are missing) -----------------------------------------------

    public function testOrderingDefaultsToTitleAscendingWhenParamsAreMissing(): void
    {
        // Module rows created outside the admin form have no ordering/direction. 1.5.0 built
        // "ORDER BY weblinks." from that and took the whole page down with SQL error 1054.
        $this->assertSame(self::PUBLIC_LINKS, $this->titles([]));
    }

    public function testOrderByTitleDescending(): void
    {
        $this->assertSame(array_reverse(self::PUBLIC_LINKS), $this->titles(['ordering' => 'title', 'direction' => 'DESC']));
    }

    public function testOrderByHits(): void
    {
        $this->assertSame(
            ['Featured Link', 'Joomla', 'Long Description Example', 'German Only Link', 'No Description Example'],
            $this->titles(['ordering' => 'hits', 'direction' => 'DESC'])
        );
        $this->assertSame(
            ['No Description Example', 'German Only Link', 'Long Description Example', 'Joomla', 'Featured Link'],
            $this->titles(['ordering' => 'hits', 'direction' => 'ASC'])
        );
    }

    public function testOrderByCreatedDescending(): void
    {
        $this->assertSame(
            ['Featured Link', 'German Only Link', 'No Description Example', 'Long Description Example', 'Joomla'],
            $this->titles(['ordering' => 'created', 'direction' => 'DESC'])
        );
    }

    public function testOrderByManualOrdering(): void
    {
        $this->assertSame(
            ['Joomla', 'Long Description Example', 'No Description Example', 'German Only Link', 'Featured Link'],
            $this->titles(['ordering' => 'ordering', 'direction' => 'ASC'])
        );
    }

    // -- field selection and postprocessing --------------------------------------------------------------------

    public function testOptionalFieldsAreOnlySelectedWhenRequested(): void
    {
        $link = $this->links([])[0];

        $this->assertObjectNotHasProperty('description', $link);
        $this->assertObjectNotHasProperty('hits', $link);
        $this->assertObjectNotHasProperty('category', $link);

        $link = $this->links(['description' => 1, 'hits' => 1, 'category' => 1])[0];

        $this->assertObjectHasProperty('description', $link);
        $this->assertObjectHasProperty('hits', $link);
        $this->assertSame('Public Category', $link->category);
    }

    public function testReferenceGoesThroughTheWeblinksComponent(): void
    {
        $link = $this->link('Joomla');

        $this->assertStringContainsString('task=weblink.go', $link->reference);
        $this->assertStringContainsString('id=' . $link->id, $link->reference);
    }

    public function testImageUrlIsResolvedAndJoomlaSuffixRemoved(): void
    {
        $this->assertSame(
            'images/sampledata/cassiopeia/joomla_black.png',
            $this->link('Joomla', ['image' => 1])->imageUrl
        );
        // no images column selected, or no image stored: null, never an error
        $this->assertNull($this->link('Joomla')->imageUrl);
        $this->assertNull($this->link('Featured Link', ['image' => 1])->imageUrl);
    }

    // -- description_trim (1.6.0 / 1.6.1) ----------------------------------------------------------------------

    public function testDescriptionIsRawHtmlWithoutTrim(): void
    {
        // Backward compatibility: every installation from before 1.6.0 has no description_trim.
        $description = $this->link('Long Description Example', ['description' => 1])->description;

        $this->assertStringStartsWith('<p>', $description);
        $this->assertStringContainsString('</p><p>', $description);
        $this->assertSame($description, $this->link('Long Description Example', ['description' => 1, 'description_trim' => 0])->description);
    }

    public function testTrimmedDescriptionIsPlainTextCutAtAWordBoundary(): void
    {
        $description = $this->link('Long Description Example', ['description' => 1, 'description_trim' => 140])->description;

        $this->assertStringEndsWith('…', $description);
        $this->assertLessThanOrEqual(141, mb_strlen($description));
        $this->assertStringNotContainsString('<', $description);
        // 1.6.1: the paragraph break must not glue "1.6.0." and "Its" together
        $this->assertStringContainsString('1.6.0. Its', $description);
        $this->assertDoesNotMatchRegularExpression('/\s…$/u', $description);
    }

    public function testShortAndEmptyDescriptionsGetNoEllipsis(): void
    {
        $params = ['description' => 1, 'description_trim' => 140];

        $this->assertSame('The official Joomla project website.', $this->link('Joomla', $params)->description);
        $this->assertSame('', $this->link('No Description Example', $params)->description);
    }

    // -- Weblinks missing or broken (1.5.1) --------------------------------------------------------------------

    public function testBrokenWeblinksTableYieldsNullInsteadOfAnException(): void
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $table = $db->replacePrefix('#__weblinks');
        $moved = $table . '_cwl_test';

        $db->setQuery('RENAME TABLE ' . $db->quoteName($table) . ' TO ' . $db->quoteName($moved))->execute();

        try {
            $links = CuterWeblinksHelper::getLinks(new Registry(['count' => 5]), $this->app());
        } finally {
            // never leave the test stack without its weblinks table
            $db->setQuery('RENAME TABLE ' . $db->quoteName($moved) . ' TO ' . $db->quoteName($table))->execute();
        }

        $this->assertNull($links);
        $this->assertSame(self::PUBLIC_LINKS, $this->titles(), 'table is back, module works again');
    }
}
