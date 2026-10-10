-- Publishes two mod_cuterweblinks instances for the cuterweblinks-dev instance,
-- assigned to all pages (menuid 0) in the "sidebar-right" position used by the
-- default Cassiopeia template.
--
-- Requires mod_cuterweblinks to already be installed (see .docker/README.md).
-- Re-running this script replaces both instances (safe to re-import after a
-- `down -v` + re-seed).
--
--   "Cuter Weblinks - Defaults"      no params set at all -> exercises every
--                                    manifest default, incl. the 1.5.1 fix for
--                                    missing ordering/direction and the 1.6.0
--                                    description_trim default (0 = untouched
--                                    raw HTML). This is the regression guard:
--                                    its output must never change when adding
--                                    a new param with a sensible default.
--
--   "Cuter Weblinks - Full Featured" every display option on, description_trim
--                                    set to 140, respects site language -
--                                    exercises the "everything visible" path,
--                                    including the new 1.6.0 trimming.

DELETE FROM `#__modules_menu` WHERE moduleid IN (
  SELECT id FROM `#__modules` WHERE module = 'mod_cuterweblinks'
);
DELETE FROM `#__modules` WHERE module = 'mod_cuterweblinks';

INSERT INTO `#__modules`
  (title, note, content, ordering, position, checked_out, checked_out_time, published,
   module, access, showtitle, params, client_id, language, publish_up, publish_down)
VALUES
  ('Cuter Weblinks - Defaults', '', '', 1, 'sidebar-right', NULL, NULL, 1,
   'mod_cuterweblinks', 1, 1, '{}', 0, '*', NULL, NULL),
  ('Cuter Weblinks - Full Featured', '', '', 2, 'sidebar-right', NULL, NULL, 1,
   'mod_cuterweblinks', 1, 1,
   '{"categories":["all"],"count":"10","ordering":"title","direction":"ASC","image":"1","description":"1","description_trim":"140","category":"1","hits":"1","target":"1","languages":"1"}',
   0, '*', NULL, NULL);

INSERT INTO `#__modules_menu` (moduleid, menuid)
SELECT id, 0 FROM `#__modules` WHERE module = 'mod_cuterweblinks';
