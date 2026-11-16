# Custom conditions in code (developer API, since 3.0)

Visibility Logic 3.0 has a supported API to add a condition from a plugin or a theme. A registered condition shows everywhere the built-in ones do, with no editor code:

- the rule builder in Elementor (Visibility tab) and the block editor ("Visibility rules" panel): picker, search, categories, groups;
- Elementor 4 elements (generated controls);
- summaries, canvas and Navigator labels, List View badges, the Rules overview;
- Preview as, "why hidden" in the editors and the admin bar.

There is no PHP typed in the editor (no `eval`): the condition is code you ship and review like any other plugin code.

## Register

```php
add_action( 'stax/visibility/register_conditions', function () {
	stax_visibility_register_condition( 'acme_level', [
		'label'    => 'ACME membership level',
		'category' => 'who',          // who | when | device | page | shop | where | other
		'keywords' => [ 'membership', 'plan' ],
		'visitor'  => true,           // depends on the visitor: cache warning in the Rules overview
		'fields'   => [
			[
				'key'     => 'acme_level_in',
				'type'    => 'multiselect',
				'label'   => 'Level is',
				'options' => [ 'gold' => 'Gold', 'silver' => 'Silver' ],
			],
		],
		'summary'  => 'ACME level is[ {acme_level_in|or}]',
		'short'    => '{acme_level_in}',
		'evaluate' => function ( array $values, array $context ) {
			return in_array( acme_current_level(), (array) $values['acme_level_in'], true );
		},
	] );
} );
```

`\Stax\VisibilityLogic\Conditions::register()` is the same call. Check `function_exists( 'stax_visibility_register_condition' )` when your code may run with Visibility Logic 2.x or without it.

### Arguments

| Key | Required | Meaning |
|---|---|---|
| `label` | yes | Name in the picker and the default summary. |
| `evaluate` | yes | `function( array $values, array $context ): ?bool`. |
| `fields` | no | Settings of the condition (see below). Without fields the condition gets an "Is true / Is not true" select. |
| `category` | no | Picker category, `other` by default. |
| `keywords` | no | Extra words for the picker search. |
| `summary`, `short` | no | Summary templates: `{field_key}`, `{field_key\|or}`, `{field_key\|and}`, `{field_key\|lower}`, optional parts in `[ ]`. Also `[ 'field' => key, 'cases' => [ value => template, '*' => template ] ]`. |
| `visitor` | no | `true` when the answer depends on the visitor (cache warning). |
| `since` | no | Defaults to `3.0.0`. |

### Fields

Field keys are setting names; start them with your slug so they never clash. Types: `select`, `multiselect`, `text`, `textarea`, `number`, `switch`, `date` (`Y-m-d H:i`), `time` (`HH:mm`), `list` (typed values, stored as an array), `users`, `posts` (ids picked through a search), `repeater` (rows of `fields`). Optional keys: `default`, `placeholder`, `help`, `show_if` (`[ 'other_key' => [ values ] ]`), `required`.

### Evaluate

- `$values`: the condition's field values by key. Switches are `true`/`false`, lists and multiselects are arrays, everything else is a string.
- `$context`: `id` and `name` of the element, `kind` (`element` for Elementor, `block`, `atomic` for Elementor 4).
- Return `true` (met), `false` (not met) or `null` (cannot apply here, for example your plugin's data is missing: the condition is skipped, like a condition of an inactive plugin).
- An exception counts as "not met" (and is logged when `WP_DEBUG` is on).
- Read the visitor through `\Stax\VisibilityLogic\Context` (`user_id()`, `user()`, `roles()`, `can()`, `timestamp()`, `local( $format )`, `device()`, `browser()`) instead of `get_current_user_id()` and `time()`, so "Preview as" works for your condition too.
- The answer is computed on the server for every request. Cached pages keep the answer of the first visitor; mark visitor conditions with `visitor => true` so the Rules overview warns about it.

## Storage and Visibility Logic 2.x

Rules that use a registered condition are stored as rule trees (`stax_visibility_rules`). Visibility Logic 2.x does not know the condition, so after a downgrade those elements are shown to administrators only (fail closed), never to everyone.

## Older API (still supported)

The 2.x hooks keep working: register fields with the `stax/visibility/condition_fields` filter and answer through `stax/visibility/apply_conditions`. `register()` uses them under the hood; prefer it for new code.

## Example

`docs/examples/vl-sample-condition/vl-sample-condition.php` is a small plugin with two conditions (one with settings, one without). Copy it to `wp-content/plugins/` to try it.
