# Visibility Logic 3.0: rule builder progress

Plan: second-brain `Projects/staxwp/docs/2026-11-13-majors-ux-rethink.md` (V-M1 to V-M11) and backlog B1, B2, B4, B5, B6 from `2026-11-13-vl-vs-dynamic-visibility.md`. Approved 2026-11-13 (rule builder in 3.0, Settings > Visibility Logic with Rules first, shared conditions package).

## Done

- Shared package `StaxWP/stax-conditions` bundled in `lib/stax-conditions` (loader picks the highest bundled copy).
- Engine: `Rule_Tree` (registry for the builder, tree storage, 2.x mapping both ways, fail-closed downgrade keys, evaluation through the existing condition modules, summaries, warnings), `Context` (visitor and time, simulation for Preview as), `Cache_Check`.
- B1 yearly recurring period, B2 specific users / capability / all selected roles, B4 draft (tree), B5 columns (also with Pro 2.5), B6 operators (User Meta, ACF) through the shared library; 2.x operators unchanged.
- Tests: `tests/rule-tree-test.php` (equivalence suite: 370 rules x 24 visitor contexts, 26,568 checks; round trip; downgrade; 3.0 additions), `tests/js/rule-map.test.cjs` (JS twin vs PHP golden file).

## Next

See the sections below as they are completed.
