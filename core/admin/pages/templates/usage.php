<?php
/**
 * Rules in use tab.
 *
 * @package Stax\VisibilityLogic
 *
 * @var array $index Rules::index() result.
 */

use Stax\VisibilityLogic\Rules;

defined( 'ABSPATH' ) || exit;

$stax_vl_docs = $index['documents'];
?>
<h2><?php esc_html_e( 'Rules in use', 'visibility-logic-elementor' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'Pages, posts and Elementor templates with at least one element that has Visibility Logic turned on. Use it to review rules before a launch or after a plugin update.', 'visibility-logic-elementor' ); ?>
</p>

<?php if ( empty( $stax_vl_docs ) ) : ?>
	<p><?php esc_html_e( 'No element uses visibility rules yet.', 'visibility-logic-elementor' ); ?></p>
<?php else : ?>
	<table class="widefat striped stax-vl-usage">
		<caption class="screen-reader-text"><?php esc_html_e( 'Documents with visibility rules', 'visibility-logic-elementor' ); ?></caption>
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Title', 'visibility-logic-elementor' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Type', 'visibility-logic-elementor' ); ?></th>
				<th scope="col" class="num"><?php esc_html_e( 'Elements', 'visibility-logic-elementor' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Conditions', 'visibility-logic-elementor' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $stax_vl_docs as $stax_vl_doc ) : ?>
				<?php
				$stax_vl_type  = get_post_type_object( $stax_vl_doc['post_type'] );
				$stax_vl_label = $stax_vl_type ? $stax_vl_type->labels->singular_name : $stax_vl_doc['post_type'];
				if ( '' !== $stax_vl_doc['template'] && 'elementor_library' === $stax_vl_doc['post_type'] ) {
					$stax_vl_label .= ' (' . $stax_vl_doc['template'] . ')';
				}
				$stax_vl_title = '' !== $stax_vl_doc['title'] ? $stax_vl_doc['title'] : __( '(no title)', 'visibility-logic-elementor' );
				?>
				<tr>
					<td>
						<?php if ( current_user_can( 'edit_post', $stax_vl_doc['id'] ) ) : ?>
							<strong><a href="<?php echo esc_url( Rules::edit_url( $stax_vl_doc['id'] ) ); ?>"><?php echo esc_html( $stax_vl_title ); ?></a></strong>
						<?php else : ?>
							<strong><?php echo esc_html( $stax_vl_title ); ?></strong>
						<?php endif; ?>
						<?php if ( 'publish' !== $stax_vl_doc['status'] ) : ?>
							<?php $stax_vl_status = get_post_status_object( $stax_vl_doc['status'] ); ?>
							<span class="post-state">(<?php echo esc_html( $stax_vl_status ? $stax_vl_status->label : $stax_vl_doc['status'] ); ?>)</span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $stax_vl_label ); ?></td>
					<td class="num">
						<?php echo esc_html( number_format_i18n( $stax_vl_doc['count'] ) ); ?>
						<?php if ( $stax_vl_doc['legacy'] ) : ?>
							<br><span class="stax-vl-muted">
								<?php
								/* translators: %s: number of elements. */
								echo esc_html( sprintf( _n( '%s with 1.x settings', '%s with 1.x settings', $stax_vl_doc['legacy'], 'visibility-logic-elementor' ), number_format_i18n( $stax_vl_doc['legacy'] ) ) );
								?>
							</span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( implode( ', ', array_map( [ Rules::class, 'condition_label' ], $stax_vl_doc['conditions'] ) ) ); ?></td>
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
