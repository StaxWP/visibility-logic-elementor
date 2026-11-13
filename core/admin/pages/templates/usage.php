<?php
/**
 * Rules tab (3.0 overview): one row per element with its rule in words, warnings, filters and the cache check.
 *
 * @package Stax\VisibilityLogic
 *
 * @var array $index   Rules::index() result.
 * @var array $rows    Rules::overview_rows() result.
 * @var array $filters Current filters.
 * @var array $caches  Cache_Check::detect() result.
 */

use Stax\VisibilityLogic\Rules;
use Stax\VisibilityLogic\Rule_Tree;
use Stax\VisibilityLogic\Settings;

defined( 'ABSPATH' ) || exit;

$stax_vl_docs     = $index['documents'];
$stax_vl_total    = array_sum( wp_list_pluck( $stax_vl_docs, 'count' ) );
$stax_vl_visitor  = 0;
$stax_vl_problems = 0;
$stax_vl_all_rows = Rules::overview_rows( $index );

foreach ( $stax_vl_all_rows as $stax_vl_r ) {
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

$stax_vl_statuses = [
	''         => __( 'Any status', 'visibility-logic-elementor' ),
	'problems' => __( 'Needs attention', 'visibility-logic-elementor' ),
	'expired'  => __( 'Date range ended', 'visibility-logic-elementor' ),
	'future'   => __( 'Starts later', 'visibility-logic-elementor' ),
	'never'    => __( 'Can never be true', 'visibility-logic-elementor' ),
	'disabled' => __( 'Condition turned off', 'visibility-logic-elementor' ),
	'draft'    => __( 'Hidden (draft)', 'visibility-logic-elementor' ),
	'cache'    => __( 'Depends on the visitor', 'visibility-logic-elementor' ),
	'tree'     => __( 'Saved in the 3.0 format', 'visibility-logic-elementor' ),
];
?>
<h2><?php esc_html_e( 'Rules', 'visibility-logic-elementor' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'Every Elementor element and block with visibility rules, with its rule in plain words and what to check. Open an element to change its rule.', 'visibility-logic-elementor' ); ?>
</p>

<?php if ( ! empty( $stax_vl_docs ) ) : ?>
	<ul class="stax-vl-stats">
		<li>
			<?php
			/* translators: 1: number of elements, 2: number of pages. */
			echo esc_html( sprintf( _n( '%1$s element on %2$s page', '%1$s elements on %2$s pages', count( $stax_vl_docs ), 'visibility-logic-elementor' ), number_format_i18n( $stax_vl_total ), number_format_i18n( count( $stax_vl_docs ) ) ) );
			?>
		</li>
		<li class="<?php echo $stax_vl_problems ? 'is-warning' : ''; ?>">
			<?php
			/* translators: %s: number of elements. */
			echo esc_html( sprintf( _n( '%s needs attention', '%s need attention', $stax_vl_problems, 'visibility-logic-elementor' ), number_format_i18n( $stax_vl_problems ) ) );
			?>
		</li>
	</ul>
<?php endif; ?>

<section class="stax-vl-cache" aria-labelledby="stax-vl-cache-title">
	<h3 id="stax-vl-cache-title"><?php esc_html_e( 'Cache check', 'visibility-logic-elementor' ); ?></h3>
	<?php if ( empty( $caches ) ) : ?>
		<p><?php esc_html_e( 'No page cache plugin or host cache was detected. Rules are checked on every page view.', 'visibility-logic-elementor' ); ?></p>
	<?php else : ?>
		<p>
			<?php
			/* translators: %s: number of elements. */
			echo esc_html( sprintf( _n( 'A page cache serves one copy of a page to many visitors. %s element has a rule that depends on the visitor; exclude its page from the cache or keep caching only for guests.', 'A page cache serves one copy of a page to many visitors. %s elements have rules that depend on the visitor; exclude their pages from the cache or keep caching only for guests.', $stax_vl_visitor, 'visibility-logic-elementor' ), number_format_i18n( $stax_vl_visitor ) ) );
			?>
		</p>
		<ul class="stax-vl-cache-list">
			<?php foreach ( $caches as $stax_vl_cache ) : ?>
				<li><strong><?php echo esc_html( $stax_vl_cache['name'] ); ?></strong>: <?php echo esc_html( $stax_vl_cache['help'] ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>

<?php if ( empty( $stax_vl_docs ) ) : ?>
	<p><?php esc_html_e( 'No element uses visibility rules yet. Open an element in Elementor (Visibility tab) or a block (Visibility rules panel) to add one.', 'visibility-logic-elementor' ); ?></p>
<?php else : ?>
	<form method="get" class="stax-vl-filters" action="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>">
		<input type="hidden" name="page" value="<?php echo esc_attr( Settings::RULES_PAGE ); ?>">
		<label for="stax-vl-f-editor"><?php esc_html_e( 'Editor', 'visibility-logic-elementor' ); ?></label>
		<select id="stax-vl-f-editor" name="editor">
			<option value=""><?php esc_html_e( 'Both editors', 'visibility-logic-elementor' ); ?></option>
			<option value="elementor" <?php selected( $filters['editor'], 'elementor' ); ?>><?php esc_html_e( 'Elementor', 'visibility-logic-elementor' ); ?></option>
			<option value="blocks" <?php selected( $filters['editor'], 'blocks' ); ?>><?php esc_html_e( 'Block editor', 'visibility-logic-elementor' ); ?></option>
		</select>
		<label for="stax-vl-f-condition"><?php esc_html_e( 'Condition', 'visibility-logic-elementor' ); ?></label>
		<select id="stax-vl-f-condition" name="condition">
			<option value=""><?php esc_html_e( 'Any condition', 'visibility-logic-elementor' ); ?></option>
			<?php foreach ( $stax_vl_conditions as $stax_vl_slug => $stax_vl_label ) : ?>
				<option value="<?php echo esc_attr( $stax_vl_slug ); ?>" <?php selected( $filters['condition'], $stax_vl_slug ); ?>><?php echo esc_html( $stax_vl_label ); ?></option>
			<?php endforeach; ?>
		</select>
		<label for="stax-vl-f-status"><?php esc_html_e( 'Status', 'visibility-logic-elementor' ); ?></label>
		<select id="stax-vl-f-status" name="status">
			<?php foreach ( $stax_vl_statuses as $stax_vl_value => $stax_vl_label ) : ?>
				<option value="<?php echo esc_attr( $stax_vl_value ); ?>" <?php selected( $filters['status'], $stax_vl_value ); ?>><?php echo esc_html( $stax_vl_label ); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'visibility-logic-elementor' ); ?></button>
		<?php if ( array_filter( $filters ) ) : ?>
			<a href="<?php echo esc_url( Settings::url( 'usage' ) ); ?>"><?php esc_html_e( 'Clear filters', 'visibility-logic-elementor' ); ?></a>
		<?php endif; ?>
	</form>

	<table class="widefat striped stax-vl-usage">
		<caption class="screen-reader-text"><?php esc_html_e( 'Elements with visibility rules', 'visibility-logic-elementor' ); ?></caption>
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Rule', 'visibility-logic-elementor' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Page', 'visibility-logic-elementor' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Element', 'visibility-logic-elementor' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'visibility-logic-elementor' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="4"><?php esc_html_e( 'No element matches these filters.', 'visibility-logic-elementor' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $stax_vl_row ) : ?>
				<?php
				$stax_vl_doc   = $stax_vl_row['doc'];
				$stax_vl_el    = $stax_vl_row['element'];
				$stax_vl_title = '' !== $stax_vl_doc['title'] ? $stax_vl_doc['title'] : __( '(no title)', 'visibility-logic-elementor' );
				$stax_vl_type  = get_post_type_object( $stax_vl_doc['post_type'] );
				$stax_vl_ptype = $stax_vl_type ? $stax_vl_type->labels->singular_name : $stax_vl_doc['post_type'];
				$stax_vl_real  = array_diff( $stax_vl_row['codes'], [ 'cache' ] );
				?>
				<tr>
					<td class="stax-vl-rule">
						<?php echo esc_html( $stax_vl_row['summary'] ); ?>
						<?php if ( $stax_vl_row['stored'] ) : ?>
							<br><span class="stax-vl-muted"><?php esc_html_e( 'Saved in the 3.0 format (groups or 3.0 options)', 'visibility-logic-elementor' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( current_user_can( 'edit_post', $stax_vl_doc['id'] ) ) : ?>
							<a href="<?php echo esc_url( Rules::edit_url( $stax_vl_doc['id'], $stax_vl_row['builder'] ) ); ?>"><?php echo esc_html( $stax_vl_title ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $stax_vl_title ); ?>
						<?php endif; ?>
						<br><span class="stax-vl-muted"><?php echo esc_html( $stax_vl_ptype . ' · ' . ( 'blocks' === $stax_vl_row['builder'] ? __( 'Block editor', 'visibility-logic-elementor' ) : __( 'Elementor', 'visibility-logic-elementor' ) ) ); ?></span>
					</td>
					<td><code><?php echo esc_html( $stax_vl_el['type'] ); ?></code> <span class="stax-vl-muted">#<?php echo esc_html( $stax_vl_el['id'] ); ?></span></td>
					<td>
						<?php if ( ! $stax_vl_real && ! in_array( 'cache', $stax_vl_row['codes'], true ) ) : ?>
							<span class="stax-vl-ok"><?php esc_html_e( 'OK', 'visibility-logic-elementor' ); ?></span>
						<?php else : ?>
							<ul class="stax-vl-warnings">
								<?php foreach ( $stax_vl_row['warnings'] as $stax_vl_i => $stax_vl_text ) : ?>
									<li class="stax-vl-w-<?php echo esc_attr( $stax_vl_row['codes'][ $stax_vl_i ] ); ?>"><?php echo esc_html( $stax_vl_text ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php if ( $index['truncated'] ) : ?>
		<p class="description"><?php echo esc_html( sprintf( /* translators: %d: number. */ __( 'Showing the newest %d documents.', 'visibility-logic-elementor' ), Rules::MAX_DOCUMENTS ) ); ?></p>
	<?php endif; ?>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="stax-vl-rescan">
	<input type="hidden" name="action" value="stax_visibility_rescan">
	<?php wp_nonce_field( 'stax_visibility_rescan' ); ?>
	<p>
		<?php
		/* translators: %s: human time difference. */
		echo esc_html( sprintf( __( 'Scanned %s ago.', 'visibility-logic-elementor' ), human_time_diff( (int) $index['generated'] ) ) );
		?>
		<button type="submit" class="button button-secondary"><?php esc_html_e( 'Scan again', 'visibility-logic-elementor' ); ?></button>
	</p>
</form>
