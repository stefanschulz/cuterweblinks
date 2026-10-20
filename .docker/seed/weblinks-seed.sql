-- Weblinks seed DATA for the cuterweblinks-dev instance (data only, no schema).
-- Prefix is written as #__ ; import-seed.sh substitutes the live prefix.
-- Requires the com_weblinks tables to already exist (install that package first,
-- see .docker/README.md "Installing the extensions").
--
-- Deliberately crafted (not a production dump) to exercise every feature this
-- module's query/template touch, so future integration tests have a known,
-- reproducible fixture:
--   - 3 categories at different access levels (Public / Registered / Special)
--     plus an unpublished one
--   - a weblink restricted at the *category* level vs. one restricted at the
--     *weblink* level within an otherwise-public category (see 1.5.1's
--     access-level fix)
--   - a long, multi-paragraph HTML description (> 300 plain-text chars) to
--     exercise description_trim (1.6.0)
--   - an empty description (must not show a description block, must not gain
--     a trailing ellipsis if description_trim is set)
--   - a non-default language entry, to exercise the "languages" param
--   - a featured entry (featured=1) - currently unused by the module's query,
--     seeded for whenever a "featured" filter/badge feature lands
--   - varied hits/ordering/created values so ordering=hits|ordering|created
--     all produce a visibly different, checkable sequence

DELETE FROM `#__weblinks`;
DELETE FROM `#__categories` WHERE extension = 'com_weblinks';

-- Categories --------------------------------------------------------------
-- Manually-computed nested set (lft/rgt), all flat top-level siblings under
-- the implicit root (parent_id=1) - correct as long as these three stay the
-- only com_weblinks categories. If more are added later by hand, re-run via
-- Admin > Components > Web Links > Categories > Rebuild instead of guessing.
INSERT INTO `#__categories`
  (asset_id, parent_id, lft, rgt, level, path, extension, title, alias, note, description,
   published, checked_out, checked_out_time, access, params, metadesc, metakey, metadata,
   created_user_id, created_time, modified_user_id, modified_time, hits, language, version)
VALUES
  (0, 1, 1, 2, 1, 'public-category',     'com_weblinks', 'Public Category',     'public-category',     '', 'Visible to everyone.',            1, NULL, NULL, 1, '{}', '', '', '{}', 42, '2026-01-10 09:00:00', 42, '2026-01-10 09:00:00', 0, '*', 1),
  (0, 1, 3, 4, 1, 'registered-category', 'com_weblinks', 'Registered Category', 'registered-category', '', 'Restricted at category level.',  1, NULL, NULL, 2, '{}', '', '', '{}', 42, '2026-01-10 09:00:00', 42, '2026-01-10 09:00:00', 0, '*', 1),
  (0, 1, 5, 6, 1, 'special-category',    'com_weblinks', 'Special Category',    'special-category',    '', 'Restricted at category level.',  1, NULL, NULL, 3, '{}', '', '', '{}', 42, '2026-01-10 09:00:00', 42, '2026-01-10 09:00:00', 0, '*', 1),
  (0, 1, 7, 8, 1, 'unpublished-category', 'com_weblinks', 'Unpublished Category', 'unpublished-category', '', 'Unpublished, so its links must never show.', 0, NULL, NULL, 1, '{}', '', '', '{}', 42, '2026-01-10 09:00:00', 42, '2026-01-10 09:00:00', 0, '*', 1);

SET @catPublic     = (SELECT id FROM `#__categories` WHERE alias = 'public-category'     AND extension = 'com_weblinks');
SET @catRegistered = (SELECT id FROM `#__categories` WHERE alias = 'registered-category' AND extension = 'com_weblinks');
SET @catSpecial    = (SELECT id FROM `#__categories` WHERE alias = 'special-category'    AND extension = 'com_weblinks');
SET @catUnpublished = (SELECT id FROM `#__categories` WHERE alias = 'unpublished-category' AND extension = 'com_weblinks');

-- Weblinks ------------------------------------------------------------------
INSERT INTO `#__weblinks`
  (catid, title, alias, url, description, hits, state, ordering, access, params, language,
   created, created_by, created_by_alias, modified, modified_by, metakey, metadesc, metadata,
   featured, xreference, publish_up, publish_down, version, images)
VALUES
  -- 1: baseline entry with an image reference (path will 404 in a fresh instance -
  --    fine, it still exercises the imageUrl-resolution code path)
  (@catPublic, 'Joomla', 'joomla', 'https://www.joomla.org',
   '<p>The official Joomla project website.</p>',
   42, 1, 1, 1, '{}', '*',
   '2026-01-15 10:00:00', 42, '', '2026-01-15 10:00:00', 42, '', '', '{}',
   0, '', '2026-01-15 10:00:00', NULL, 1,
   '{"image_first":"images/sampledata/cassiopeia/joomla_black.png#joomlaImage://local-images/sampledata/cassiopeia/joomla_black.png?width=300&height=300","image_first_alt":"","image_second":"","image_second_alt":""}'),

  -- 2: long, multi-paragraph description for description_trim testing (plain
  --    text length ~360 chars once tags are stripped and whitespace collapsed)
  (@catPublic, 'Long Description Example', 'long-description-example', 'https://example.com/long',
   '<p>This entry exists specifically to exercise the description_trim parameter introduced in 1.6.0.</p><p>Its description spans multiple HTML paragraphs with formatting, which is exactly the kind of rich text that breaks CSS line-clamping in a fixed-height card layout - the reason description_trim exists in the first place.</p><p>A third paragraph pushes the plain-text length safely past any reasonable trim value you might configure, such as 140 characters.</p>',
   7, 1, 2, 1, '{}', '*',
   '2026-01-16 10:00:00', 42, '', '2026-01-16 10:00:00', 42, '', '', '{}',
   0, '', '2026-01-16 10:00:00', NULL, 1, '{}'),

  -- 3: empty description - must not render a description block, and
  --    description_trim must not append an ellipsis to nothing
  (@catPublic, 'No Description Example', 'no-description-example', 'https://example.com/no-description',
   '',
   0, 1, 3, 1, '{}', '*',
   '2026-01-17 10:00:00', 42, '', '2026-01-17 10:00:00', 42, '', '', '{}',
   0, '', '2026-01-17 10:00:00', NULL, 1, '{}'),

  -- 4: public weblink, but inside a category restricted to Registered - must
  --    disappear for an anonymous visitor even though the weblink's own
  --    access is Public (category access does not get inherited/ignored)
  (@catRegistered, 'Registered Only Link', 'registered-only-link', 'https://example.com/registered',
   '<p>Only visible to Registered users and above, via the category access level.</p>',
   15, 1, 1, 1, '{}', '*',
   '2026-01-18 10:00:00', 42, '', '2026-01-18 10:00:00', 42, '', '', '{}',
   0, '', '2026-01-18 10:00:00', NULL, 1, '{}'),

  -- 5: Special-access weblink inside the otherwise-Public category - must
  --    disappear for anonymous/Registered visitors due to its own access,
  --    independent of the category being Public
  (@catPublic, 'Special Access Link', 'special-access-link', 'https://example.com/special',
   '<p>Only visible to Special access level and above, via the weblink''s own access level.</p>',
   3, 1, 4, 3, '{}', '*',
   '2026-01-19 10:00:00', 42, '', '2026-01-19 10:00:00', 42, '', '', '{}',
   0, '', '2026-01-19 10:00:00', NULL, 1, '{}'),

  -- 6: non-default language, for the "languages" (respect site language) param
  (@catPublic, 'German Only Link', 'german-only-link', 'https://example.com/de',
   '<p>Nur auf Deutsch getaggt - für den Sprachfilter-Test.</p>',
   5, 1, 5, 1, '{}', 'de-DE',
   '2026-01-20 10:00:00', 42, '', '2026-01-20 10:00:00', 42, '', '', '{}',
   0, '', '2026-01-20 10:00:00', NULL, 1, '{}'),

  -- 7: featured flag set (unused by the module today, seeded for a future
  --    "featured" filter/badge) and the highest hit count, for ordering=hits
  (@catPublic, 'Featured Link', 'featured-link', 'https://example.com/featured',
   '<p>Flagged as featured; also has the highest hit count in this fixture.</p>',
   99, 1, 6, 1, '{}', '*',
   '2026-01-21 10:00:00', 42, '', '2026-01-21 10:00:00', 42, '', '', '{}',
   1, '', '2026-01-21 10:00:00', NULL, 1, '{}'),

  -- 8: unpublished (state 0) weblink in an otherwise-Public category - the state filter
  --    must hide it from everyone, including Super Users on the front end
  (@catPublic, 'Unpublished Link', 'unpublished-link', 'https://example.com/unpublished',
   '<p>State 0: must never be listed.</p>',
   1, 0, 7, 1, '{}', '*',
   '2026-01-22 10:00:00', 42, '', '2026-01-22 10:00:00', 42, '', '', '{}',
   0, '', '2026-01-22 10:00:00', NULL, 1, '{}'),

  -- 9: published weblink with Public access inside an unpublished category - the
  --    categories.published filter must hide it
  (@catUnpublished, 'Link In Unpublished Category', 'link-in-unpublished-category', 'https://example.com/in-unpublished-category',
   '<p>Published itself, but its category is not.</p>',
   1, 1, 1, 1, '{}', '*',
   '2026-01-23 10:00:00', 42, '', '2026-01-23 10:00:00', 42, '', '', '{}',
   0, '', '2026-01-23 10:00:00', NULL, 1, '{}');
