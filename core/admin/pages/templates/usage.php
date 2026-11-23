<?php
/**
 * Rules tab (3.0 overview): one row per element with its rule in words, warnings, filters and the cache check.
 *
 * @package Stax\VisibilityLogic
 *
 * @var array $index   Rules::index() result.
 * @var array $rows    Rows of this page (Rules::overview_rows(), paged).
 * @var int   $matched Rows matching the filters, all pages.
 * @var array $paging  page, pages, per_page.
 * @var array $filters Current filters.
 * @var array $caches  Cache_Check::detect() result.
 */

use Stax\VisibilityLogic\Rule_Tree;
use Stax\VisibilityLogic\Rules;
use Stax\VisibilityLogic\Settings;

defined( 'ABSPATH' ) || exit;

$stax_vl_docs     = $index['documents'];
$stax_vl_total    = array_sum( wp_list_pluck( $stax_vl_docs, 'count' ) );
$stax_vl_visitor  = 0;
$stax_vl_problems = 0;

foreach ( Rules::overview_rows( $index ) as $stax_vl_r ) {
	if ( in_array( 'cache', $stax_vl_r['codes'], true ) ) {
		++$stax_vl_visitor;
	}
	if ( array_diff( $stax_vl_r['codes'], [ 'cache', 'draft' ] ) ) {
		++$stax_vl_problems;
	}
}

$stax_vl_conditions = [];
foreach ( $stax_vl_docs as $stax_vl_doc ) {
	foreach ( $stax_vl_doc['conditions'] as $stax_vl_c ) {
		$stax_vl_conditions[ $stax_vl_c ] = Rules::condition_label( $stax_vl_c );
	}
}
asort( $stax_vl_conditions );

$stax_vl_statuses = array_merge(
	[
		''         => __( 'Any status', 'visibility-logic-elementor' ),
		'problems' => __( 'Needs attention', 'visibility-logic-elementor' ),
	],
	Rule_Tree::warning_tags(),
	[ 'tree' => __( 'Saved in the 3.0 format', 'visibility-logic-elementor' ) ]
);

$stax_vl_editors = [
	''          => __( 'All', 'visibility-logic-elementor' ),
	'elementor' => __( 'Elementor', 'visibility-logic-elementor' ),
	'blocks'    => __( 'Blocks', 'visibility-logic-elementor' ),
	'page'      => __( 'Pages', 'visibility-logic-elementor' ),
	'menus'     => __( 'Menus', 'visibility-logic-elementor' ),
	'widgets'   => __( 'Widgets', 'visibility-logic-elementor' ),
];

// Tag style and icon of each warning code.
$stax_vl_tags = [
	'expired'    => [ 'error', 'clock' ],
	'never'      => [ 'error', 'alert' ],
	'disabled'   => [ 'error', 'alert' ],
	'locked'     => [ 'warn', 'lock' ],
	'incomplete' => [ 'warn', 'alert' ],
	'future'     => [ 'warn', 'clock' ],
	'draft'      => [ 'muted', 'eye' ],
	'cache'      => [ 'info', 'user' ],
];

// Short tag per warning code (the sentences go under the rule text).
$stax_vl_tag_names = Rule_Tree::warning_tags();

/**
 * URL of the Rules tab with the given filters (others kept).
 *
 * @param array $changes Filters to set ('' removes one).
 */
$stax_vl_filter_url = static function ( array $changes ) use ( $filters ) {
	$args = array_filter( array_merge( $filters, $changes ), 'strlen' );

	return add_query_arg( $args, Settings::url( 'usage' ) );
};

$stax_vl_stats = [
	[ 'stack', '', $stax_vl_total, _n( 'Element with rules', 'Elements with rules', $stax_vl_total, 'visibility-logic-elementor' ), [ 'status' => '' ] ],
	[ 'file', 'muted', count( $stax_vl_docs ), _n( 'Page', 'Pages', count( $stax_vl_docs ), 'visibility-logic-elementor' ), null ],
	[ 'alert', 'warn', $stax_vl_problems, _n( 'Needs attention', 'Need attention', $stax_vl_problems, 'visibility-logic-elementor' ), [ 'status' => 'problems' ] ],
	[ 'user', 'info', $stax_vl_visitor, __( 'Cache risk', 'visibility-logic-elementor' ), [ 'status' => 'cache' ] ],
];
?>
<div class="stax-vl-title">
	<div>
		<h2><?php esc_html_e( 'Rules', 'visibility-logic-elementor' ); ?></h2>
		<p><?php esc_html_e( 'Every element, block and page with a visibility rule, in plain words. Open one to change its rule.', 'visibility-logic-elementor' ); ?></p>
	</div>
</div>

<?php if ( ! empty( $stax_vl_docs ) ) : ?>
	<ul class="stax-vl-stats">
		<?php foreach ( $stax_vl_stats as $stax_vl_stat ) : ?>
			<li>
				<?php
				$stax_vl_tag    = $stax_vl_stat[4] ? 'a' : 'div';
				$stax_vl_active = $stax_vl_stat[4] && '' !== $stax_vl_stat[4]['status'] && $filters['status'] === $stax_vl_stat[4]['status'];
				?>
				<<?php echo esc_html( $stax_vl_tag ); ?> class="stax-vl-card stax-vl-stat<?php echo $stax_vl_active ? ' is-active' : ''; ?>"<?php echo $stax_vl_stat[4] ? ' href="' . esc_url( $stax_vl_filter_url( $stax_vl_stat[4] ) ) . '"' : ''; ?>>
					<span class="stax-vl-stat__icon<?php echo $stax_vl_stat[1] ? ' stax-vl-stat__icon--' . esc_attr( $stax_vl_stat[1] ) : ''; ?>"><?php Settings::icon( $stax_vl_stat[0] ); ?></span>
					<span>
						<span class="stax-vl-stat__num"><?php echo esc_html( number_format_i18n( $stax_vl_stat[2] ) ); ?></span>
						<span class="stax-vl-stat__label"><?php echo esc_html( $stax_vl_stat[3] ); ?></span>
					</span>
				</<?php echo esc_html( $stax_vl_tag ); ?>>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>

<?php if ( empty( $caches ) ) : ?>
	<section class="stax-vl-alert stax-vl-alert--ok" aria-labelledby="stax-vl-cache-title">
		<?php Settings::icon( 'db' ); ?>
		<div>
			<h3 id="stax-vl-cache-title"><?php esc_html_e( 'No page cache detected', 'visibility-logic-elementor' ); ?></h3>
			<p><?php esc_html_e( 'Rules are checked on every page view.', 'visibility-logic-elementor' ); ?></p>
		</div>
	</section>
<?php else : ?>
	<section class="stax-vl-alert" aria-labelledby="stax-vl-cache-title">
		<?php Settings::icon( 'db' ); ?>
		<div>
			<h3 id="stax-vl-cache-title">
				<?php
				/* translators: %s: cache plugin or host names. */
				echo esc_html( sprintf( __( 'Page cache detected: %s', 'visibility-logic-elementor' ), implode( ', ', wp_list_pluck( $caches, 'name' ) ) ) );
				?>
			</h3>
			<p>
				<?php
				/* translators: %s: number of elements. */
				echo esc_html( sprintf( _n( 'A page cache serves one copy of a page to many visitors. %s element has a rule that depends on the visitor; exclude its page from the cache or keep caching only for guests.', 'A page cache serves one copy of a page to many visitors. %s elements have rules that depend on the visitor; exclude their pages from the cache or keep caching only for guests.', $stax_vl_visitor, 'visibility-logic-elementor' ), number_format_i18n( $stax_vl_visitor ) ) );
				?>
			</p>
			<ul>
				<?php foreach ( $caches as $stax_vl_cache ) : ?>
					<li><strong><?php echo esc_html( $stax_vl_cache['name'] ); ?></strong>: <?php echo esc_html( $stax_vl_cache['help'] ); ?></li>
				<?php endforeach; ?>
			</ul>
			<?php if ( $stax_vl_visitor ) : ?>
				<div class="stax-vl-alert__actions">
					<a class="stax-vl-btn stax-vl-btn--sm" href="<?php echo esc_url( $stax_vl_filter_url( [ 'status' => 'cache' ] ) ); ?>">
						<?php
						/* translators: %s: number of elements. */
						echo esc_html( sprintf( _n( 'Show this element', 'Show these %s elements', $stax_vl_visitor, 'visibility-logic-elementor' ), number_format_i18n( $stax_vl_visitor ) ) );
						?>
					</a>
				</div>
			<?php endif; ?>
		</div>
	</section>
<?php endif; ?>

<div class="stax-vl-card">
	<?php if ( empty( $stax_vl_docs ) ) : ?>
		<div class="stax-vl-empty">
			<?php Settings::icon( 'eye' ); ?>
			<p><?php esc_html_e( 'No element uses visibility rules yet. Open an element in Elementor (Visibility tab) or a block (Visibility rules panel) to add one.', 'visibility-logic-elementor' ); ?></p>
		</div>
	<?php else : ?>
		<form method="get" class="stax-vl-filters" action="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>" data-stax-vl-autosubmit>
			<input type="hidden" name="page" value="<?php echo esc_attr( Settings::RULES_PAGE ); ?>">
			<?php if ( '' !== $filters['editor'] ) : ?>
				<input type="hidden" name="editor" value="<?php echo esc_attr( $filters['editor'] ); ?>">
			<?php endif; ?>
			<label class="stax-vl-search">
				<?php Settings::icon( 'search' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Search the rules on this screen', 'visibility-logic-elementor' ); ?></span>
				<input type="search" name="q" value="<?php echo esc_attr( $filters['q'] ); ?>" data-stax-vl-search="#stax-vl-rules" data-stax-vl-submit placeholder="<?php esc_attr_e( 'Search elements, pages or rules', 'visibility-logic-elementor' ); ?>">
			</label>
			<nav class="stax-vl-seg" aria-label="<?php esc_attr_e( 'Editor', 'visibility-logic-elementor' ); ?>">
				<?php foreach ( $stax_vl_editors as $stax_vl_value => $stax_vl_label ) : ?>
					<?php $stax_vl_on = $filters['editor'] === $stax_vl_value; ?>
					<a href="<?php echo esc_url( $stax_vl_filter_url( [ 'editor' => $stax_vl_value ] ) ); ?>" class="<?php echo $stax_vl_on ? 'is-active' : ''; ?>"<?php echo $stax_vl_on ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $stax_vl_label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<label for="stax-vl-f-condition" class="screen-reader-text"><?php esc_html_e( 'Condition', 'visibility-logic-elementor' ); ?></label>
			<select id="stax-vl-f-condition" name="condition">
				<option value=""><?php esc_html_e( 'Any condition', 'visibility-logic-elementor' ); ?></option>
				<?php foreach ( $stax_vl_conditions as $stax_vl_slug => $stax_vl_label ) : ?>
					<option value="<?php echo esc_attr( $stax_vl_slug ); ?>" <?php selected( $filters['condition'], $stax_vl_slug ); ?>><?php echo esc_html( $stax_vl_label ); ?></option>
				<?php endforeach; ?>
			</select>
			<label for="stax-vl-f-status" class="screen-reader-text"><?php esc_html_e( 'Status', 'visibility-logic-elementor' ); ?></label>
			<select id="stax-vl-f-status" name="status">
				<?php foreach ( $stax_vl_statuses as $stax_vl_value => $stax_vl_label ) : ?>
					<option value="<?php echo esc_attr( $stax_vl_value ); ?>" <?php selected( $filters['status'], $stax_vl_value ); ?>><?php echo esc_html( $stax_vl_label ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="stax-vl-btn stax-vl-btn--sm stax-vl-js-hide"><?php esc_html_e( 'Filter', 'visibility-logic-elementor' ); ?></button>
			<?php if ( array_filter( $filters ) ) : ?>
				<a class="stax-vl-btn stax-vl-btn--ghost stax-vl-btn--sm" href="<?php echo esc_url( Settings::url( 'usage' ) ); ?>"><?php esc_html_e( 'Clear filters', 'visibility-logic-elementor' ); ?></a>
			<?php endif; ?>
		</form>

		<table class="stax-vl-table" id="stax-vl-rules">
			<caption class="screen-reader-text"><?php esc_html_e( 'Elements with visibility rules', 'visibility-logic-elementor' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Element', 'visibility-logic-elementor' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Rule', 'visibility-logic-elementor' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'visibility-logic-elementor' ); ?></th>
					<th scope="col" class="stax-vl-col-actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'visibility-logic-elementor' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="4" class="stax-vl-empty"><?php esc_html_e( 'No element matches these filters.', 'visibility-logic-elementor' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $stax_vl_row ) : ?>
					<?php
					$stax_vl_doc   = $stax_vl_row['doc'];
					$stax_vl_el    = $stax_vl_row['element'];
					$stax_vl_title = '' !== $stax_vl_doc['title'] ? $stax_vl_doc['title'] : __( '(no title)', 'visibility-logic-elementor' );
					$stax_vl_area  = ! empty( $stax_vl_doc['area'] ); // Menu item or classic widget (Area_Rules).
					$stax_vl_type  = $stax_vl_area ? null : get_post_type_object( $stax_vl_doc['post_type'] );
					$stax_vl_ptype = $stax_vl_area ? $stax_vl_doc['kind_label'] : ( $stax_vl_type ? $stax_vl_type->labels->singular_name : $stax_vl_doc['post_type'] );
					$stax_vl_page  = 'page' === $stax_vl_el['type'];
					$stax_vl_block = 'blocks' === $stax_vl_row['builder'];
					$stax_vl_can   = $stax_vl_area ? current_user_can( 'edit_theme_options' ) : current_user_can( 'edit_post', $stax_vl_doc['id'] );
					$stax_vl_view  = ! $stax_vl_area && 'publish' === $stax_vl_doc['status'] ? get_permalink( $stax_vl_doc['id'] ) : '';

					if ( $stax_vl_area ) {
						$stax_vl_name = $stax_vl_el['name'];
						$stax_vl_icon = 'menus' === $stax_vl_row['builder'] ? 'list' : 'box';
					} elseif ( $stax_vl_page ) {
						/* translators: %s: post type name, such as Page. */
						$stax_vl_name = sprintf( __( 'Whole %s', 'visibility-logic-elementor' ), strtolower( $stax_vl_ptype ) );
						$stax_vl_icon = 'file';
					} else {
						$stax_vl_name = ucwords( str_replace( [ 'core/', '-', '_', '/' ], [ '', ' ', ' ', ' ' ], $stax_vl_el['type'] ) );
						$stax_vl_icon = $stax_vl_block ? 'blocks' : ( in_array( $stax_vl_el['type'], [ 'container', 'section', 'column' ], true ) ? 'stack' : 'box' );
					}
					?>
					<tr>
						<td>
							<div class="stax-vl-el">
								<span class="stax-vl-el__icon"><?php Settings::icon( $stax_vl_icon ); ?></span>
								<div>
									<div class="stax-vl-el__name">
										<?php echo esc_html( $stax_vl_name ); ?>
										<?php if ( ! $stax_vl_page && ! $stax_vl_area ) : ?>
											<span class="stax-vl-muted">#<?php echo esc_html( $stax_vl_el['id'] ); ?></span>
										<?php endif; ?>
									</div>
									<div class="stax-vl-el__page">
										<?php if ( $stax_vl_can ) : ?>
											<a href="<?php echo esc_url( Rules::edit_url( $stax_vl_doc['id'], $stax_vl_row['builder'] ) ); ?>"><?php echo esc_html( $stax_vl_title ); ?></a>
										<?php else : ?>
											<?php echo esc_html( $stax_vl_title ); ?>
										<?php endif; ?>
										&middot; <?php echo esc_html( $stax_vl_area ? $stax_vl_ptype : $stax_vl_ptype . ' · ' . ( $stax_vl_block ? __( 'Block editor', 'visibility-logic-elementor' ) : __( 'Elementor', 'visibility-logic-elementor' ) ) ); ?>
									</div>
								</div>
							</div>
						</td>
						<td class="stax-vl-rule">
							<?php echo esc_html( $stax_vl_row['summary'] ); ?>
							<?php
							$stax_vl_notes = array_unique( array_diff_key( $stax_vl_row['warnings'], array_keys( $stax_vl_row['codes'], 'draft', true ) ) );
							?>
							<?php if ( $stax_vl_notes ) : ?>
								<ul class="stax-vl-rule__notes">
									<?php foreach ( $stax_vl_notes as $stax_vl_text ) : ?>
										<li><?php echo esc_html( $stax_vl_text ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
							<?php if ( $stax_vl_row['stored'] && ! $stax_vl_page && ! $stax_vl_area ) : ?>
								<br><span class="stax-vl-muted"><?php esc_html_e( 'Saved in the 3.0 format (groups or 3.0 options)', 'visibility-logic-elementor' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<ul class="stax-vl-tags">
								<?php if ( ! $stax_vl_row['codes'] ) : ?>
									<li><span class="stax-vl-tag stax-vl-tag--ok"><?php Settings::icon( 'check' ); ?><?php esc_html_e( 'OK', 'visibility-logic-elementor' ); ?></span></li>
								<?php endif; ?>
								<?php foreach ( array_intersect_key( $stax_vl_tag_names, array_flip( $stax_vl_row['codes'] ) ) as $stax_vl_code => $stax_vl_text ) : ?>
									<?php $stax_vl_t = isset( $stax_vl_tags[ $stax_vl_code ] ) ? $stax_vl_tags[ $stax_vl_code ] : [ 'warn', 'alert' ]; ?>
									<li><span class="stax-vl-tag stax-vl-tag--<?php echo esc_attr( $stax_vl_t[0] ); ?>"><?php Settings::icon( $stax_vl_t[1] ); ?><?php echo esc_html( $stax_vl_text ); ?></span></li>
								<?php endforeach; ?>
							</ul>
						</td>
						<td class="stax-vl-col-actions">
							<div class="stax-vl-actions">
								<?php if ( $stax_vl_can ) : ?>
									<a class="stax-vl-btn stax-vl-btn--ghost stax-vl-btn--icon" href="<?php echo esc_url( Rules::edit_url( $stax_vl_doc['id'], $stax_vl_row['builder'] ) ); ?>">
										<?php Settings::icon( 'edit' ); ?>
										<span class="screen-reader-text">
											<?php
											/* translators: %s: page title. */
											echo esc_html( sprintf( __( 'Edit %s', 'visibility-logic-elementor' ), $stax_vl_title ) );
											?>
										</span>
									</a>
								<?php endif; ?>
								<?php if ( $stax_vl_view ) : ?>
									<a class="stax-vl-btn stax-vl-btn--ghost stax-vl-btn--icon" href="<?php echo esc_url( $stax_vl_view ); ?>" target="_blank" rel="noopener">
										<?php Settings::icon( 'ext' ); ?>
										<span class="screen-reader-text">
											<?php
											/* translators: %s: page title. */
											echo esc_html( sprintf( __( 'View %s (opens in a new tab)', 'visibility-logic-elementor' ), $stax_vl_title ) );
											?>
										</span>
									</a>
								<?php endif; ?>
								<?php
								/**
								 * Extra actions of a Rules overview row (Visibility Logic Pro: reset view counts).
								 *
								 * @param array $row Rules::overview_rows() row.
								 */
								do_action( 'stax/visibility/overview_row_actions', $stax_vl_row ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- shares the 2.x hook namespace.
								?>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if ( $paging['pages'] > 1 ) : ?>
		<nav class="stax-vl-paging" aria-label="<?php esc_attr_e( 'Rules pages', 'visibility-logic-elementor' ); ?>">
			<?php if ( $paging['page'] > 1 ) : ?>
				<a class="stax-vl-btn stax-vl-btn--sm" href="<?php echo esc_url( $stax_vl_filter_url( [ 'paged' => (string) ( $paging['page'] - 1 ) ] ) ); ?>"><?php esc_html_e( 'Previous', 'visibility-logic-elementor' ); ?></a>
			<?php endif; ?>
			<span>
				<?php
				/* translators: 1: current page, 2: number of pages. */
				echo esc_html( sprintf( __( 'Page %1$s of %2$s', 'visibility-logic-elementor' ), number_format_i18n( $paging['page'] ), number_format_i18n( $paging['pages'] ) ) );
				?>
			</span>
			<?php if ( $paging['page'] < $paging['pages'] ) : ?>
				<a class="stax-vl-btn stax-vl-btn--sm" href="<?php echo esc_url( $stax_vl_filter_url( [ 'paged' => (string) ( $paging['page'] + 1 ) ] ) ); ?>"><?php esc_html_e( 'Next', 'visibility-logic-elementor' ); ?></a>
			<?php endif; ?>
		</nav>
	<?php endif; ?>

	<div class="stax-vl-foot">
		<span>
			<?php
			$stax_vl_from = $matched ? ( $paging['page'] - 1 ) * $paging['per_page'] + 1 : 0;
			/* translators: 1: first row shown, 2: last row shown, 3: number of matching rows. */
			echo esc_html( sprintf( __( 'Showing %1$s to %2$s of %3$s', 'visibility-logic-elementor' ), number_format_i18n( $stax_vl_from ), number_format_i18n( $stax_vl_from ? $stax_vl_from + count( $rows ) - 1 : 0 ), number_format_i18n( $matched ) ) );
			if ( $index['truncated'] ) {
				echo ' &middot; ';
				/* translators: %d: number. */
				echo esc_html( sprintf( __( 'newest %d documents only', 'visibility-logic-elementor' ), Rules::MAX_DOCUMENTS ) );
			}
			?>
		</span>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="stax_visibility_rescan">
			<?php wp_nonce_field( 'stax_visibility_rescan' ); ?>
			<span>
				<?php
				/* translators: %s: human time difference. */
				echo esc_html( sprintf( __( 'Scanned %s ago.', 'visibility-logic-elementor' ), human_time_diff( (int) $index['generated'] ) ) );
				?>
			</span>
			<button type="submit" class="stax-vl-btn stax-vl-btn--sm"><?php Settings::icon( 'refresh' ); ?><?php esc_html_e( 'Scan again', 'visibility-logic-elementor' ); ?></button>
		</form>
	</div>
</div>
