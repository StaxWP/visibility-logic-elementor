# Visibility Logic 3.0: rule builder progress

Plan: second-brain `Projects/staxwp/docs/2026-11-13-majors-ux-rethink.md` (V-M1 to V-M11) and backlog items B1, B2, B4, B5, B6 from `2026-11-13-vl-vs-dynamic-visibility.md`. Approved 2026-11-13: rule builder in 3.0, Settings > Visibility Logic with Rules first, shared conditions package.

## Storage and backward compatibility

- Old rules are never migrated. The builder reads the 2.x settings as a tree: "All" = one group, "At least one" = one condition per group (`Rule_Tree::legacy_to_tree()`, JS twin `assets/js/rule-map.js`).
- Saving: when the edited rule can be written in the 2.x format (one group, or one condition per group, each condition type once, no draft, no 3.0 option) only the 2.x keys that changed are written; nothing is written when nothing changed.
- Otherwise the tree is stored in `stax_visibility_rules` (blocks: `staxVisibility.rules`) and the 2.x keys become a fail-closed rule: Show, All, User Role = administrator, every other condition switched off. Visibility Logic 2.x (which ignores the tree) then shows the element to administrators only.
- 3.0 options are marked in the registry (`since`, `options_since`), so a rule that uses them is always stored as a tree.
- Evaluation: `Conditions::evaluate()` uses the tree when one is stored, the 2.x evaluator otherwise (unchanged). Each tree condition is evaluated by the existing condition modules with only that condition switched on (`Rule_Tree::check()`), so Pro and add-on conditions work in trees.

## Done (by plan item)

- Shared package `StaxWP/stax-conditions` (private), bundled in `lib/stax-conditions` (loader picks the highest bundled 1.x copy; `bin/bundle.sh` in the package copies it).
- V-M1 summaries: one template language in PHP and JS (shared fixtures), used by the panel, canvas labels, Navigator badges, List View badges, Rules overview, explain trace, abilities data (`summary` in `list-rules` elements).
- V-M2 rule builder + tree + evaluator + mapping both ways (above).
- V-M3 Elementor: custom control in the Visibility tab, classic sections behind "Show the classic settings"; Elementor 4 atomic elements keep the generated controls (new fields included).
- V-M4 block editor: "Visibility rules" panel on the same builder, WordPress `metadata.blockVisibility` shown in the panel, canvas labels, List View badges.
- V-M5 picker: search (labels and keywords), task categories, 6 presets, Pro conditions with a small Pro mark and one sentence + "Learn more" when Pro is missing (locked add-on conditions show their own notice).
- V-M6 Preview as guest / role / specific user (needs `list_users`) / device / date and time in both editors; country with Pro. REST `visibility-logic/v1/explain`, simulation in `Context` only.
- V-M7 why hidden: trace per condition in the editors; front-end admin bar "Visibility: N hidden here" with the reason per element and "View this page as" (nonce-signed).
- V-M8 Rules overview (first tab): one row per element, rule in words, warnings (ended, starts later, never true, condition off, draft, visitor rule on a cached site), filters, cache check (`Cache_Check`). Server-rendered table (DataViews deferred, see below).
- V-M9 field UX: chips for list values (meta keys, capabilities, typed values), "is empty / is not empty" operators, help with the site time zone on yearly ranges; midnight ranges keep the 2.x note.
- V-M11 readme (3.0.0 changelog, upgrade notice), update notice links to Rules; POT regenerated. Docs on staxwp.com and wp.org screenshots: not done (see below).
- B1 yearly period, B2 specific users / capability / all roles, B4 draft, B5 columns (with Pro 2.4 too: free conditions on columns, Pro ones locked there with a one-line reason; evaluated without Pro's missing controls), B6 operators.
- B7 Page condition (free; builder-only, stored as a tree so 2.x fails closed), B3 text fallback (free: Elementor controls only without Pro, blocks too; "When hidden" in the builder offers remove / CSS / message / template (Pro)).
- V-M9 finished: "(next day)" in summaries for time ranges over midnight (`{time_to|after:time_from}`), site time zone and current site time on every date and time field.

- Admin screen redesign (2026-11-17, Gabriel: modern panel like SQ Panel): app header (logo, version, Free/Pro pill, Docs, Support, Get Pro), tabs with icons and a "needs attention" count on Rules (cached index only), Rules tab with stat cards (filter links), cache alert, search, editor switch All / Elementor / Blocks / Pages (`editor=page`), status tags, Edit/View buttons; Conditions tab as grouped cards with switches and search; new General tab (the three general settings, history under "Earlier versions"); one form per tab (`section` field), sticky save bar with unsaved changes; Pro tabs keep their markup inside a card. `Settings::icon()` (inline SVG), `assets/js/settings.js`, asset versions with file time. Not checked: axe on the new screens, the Playwright e2e run (`admin-screens.mjs` selectors updated).

### Dynamic Visibility parity (2026-11-16, plan `2026-11-16-vl-dv-parity-and-importer.md` P1 to P5, P9)

- P5 / B19 developer API: `Conditions::register()` / `stax_visibility_register_condition()` on `stax/visibility/register_conditions` (label, fields, summary, `evaluate( $values, $context )`); stored as trees; conditions without fields get "Is true / Is not true"; exceptions count as not met. Pro registers its own slugs with `pro => true`. Docs: `docs/developer-conditions.md`, sample plugin `docs/examples/vl-sample-condition/`.
- P1 / B11 page-level visibility: `Page_Rules` (post meta `_stax_visibility_page`, mirrored in Elementor page settings `stax_visibility_page`), Elementor page settings section and block editor document panel (`assets/js/page-rules.js`, one builder for both); free = message (REST, feeds, excerpts, noindex, editors exempt with a notice bar, Preview as applies); hooks for Pro: `page_hidden` action, `page_replacement` filter, `page_hidden_actions`, `page_fields`, `page_documents`. Rules overview rows (type `page`).
- P2 / P3 / P4 / P9 Pro parts in Pro `license-client` (Reveal on click, view caps, popups, Preview as language). Free hooks they use: `stax/visibility/element_settings` (extra block panel fields), `stax/visibility/overview_row_actions`, `stax/visibility/preview_languages`; `assets/js/preview-extras.js` (a country or a language alone starts the preview).
- Not checked in a browser yet: the page panel (both editors), the language select, Pro's Reveal section and block fields (logic covered by the WP-CLI suites and Pro's headless `tests/js/reveal.test.cjs`).

## Tests

- `tests/rule-tree-test.php`: equivalence suite (370 rules x 24 visitor contexts = 26,568 old-vs-new checks: tree, stored tree, default true/false, round trip, downgrade), storage decisions, B1/B2/B4/B6, groups, explain, overview, rendering (blocks, Elementor, columns); writes the golden file for the JS twin.
- `tests/js/rule-map.test.cjs` (node): JS mapping = PHP mapping on the golden file.
- `tests/visibility-test.php`, `tests/blocks-test.php`: 2.x suites still green (with and without Pro, and with Pro 2.4.3).
- Summaries and warnings of the real registry are written to the golden file and checked by the JS test with the bundled package (PHP and JS identical).
- E2E (`tests/e2e/`, Playwright + axe-core on a scratch site): `rule-builder.mjs` (Elementor), `blocks-builder.mjs`, `admin-screens.mjs`, `setup.php` (fixtures). `section-scroll.mjs` is from 3.0 RC.

## Remaining

- Elementor 4 atomic panel on the builder (V-L4, when Elementor's V4 API allows custom controls).
- DataViews rules screen (approved build step; 3.0 ships the server-rendered table).
- Docs on staxwp.com and wp.org screenshots (V-M11).
- Decided for later: Dynamic Visibility importer (3.1), DataViews rules screen (3.1). Click-to-reveal, view caps and popups were built in Pro 3.0 (2026-11-16).
