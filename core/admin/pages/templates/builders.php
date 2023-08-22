<?php

$item_class = stax_vle_is_pro() ? 'xl:ste-w-1/4' : '';

?>

<h2 class="ste-my-0 ste-leading-none ste-text-2xl ste-text-gray-900 ste-font-bold ste-tracking-wide">
	<?php esc_html_e( 'Builders', 'visibility-logic-elementor' ); ?>
</h2>

<div class="ste-text-sm ste-text-gray-700 ste-mt-2">
	<?php esc_html_e( 'Choose for which builders do you want to enable Visibility Logic.', 'visibility-logic-elementor' ); ?>
</div>

<div class="ste-mt-5">
	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST">
		<div class="ste-flex ste-flex-wrap ste--mx-2">
			<?php foreach ( $builders as $slug => $builder ) : ?>
				<div class="ste-my-2 ste-w-full md:ste-w-1/2 lg:ste-w-1/3 <?php echo esc_attr( $item_class ); ?>">
					<div class="ste-mx-2">
						<label for="<?php echo esc_attr( 'builder-label-' . $slug ); ?>" class="ste-relative ste-block ste-rounded ste-bg-gradient-to-r ste-from-ash-300 ste-to-ash-200 ste-p-4">
							<div class="ste-flex ste-justify-between ste-items-center">
								<span class="ste-font-medium ste-text-gray-600 ste-text-sm">
									<?php echo $builder['name']; ?>
								</span>
									<?php if ( isset( $builder['soon'] ) && $builder['soon'] ) : ?>
										<div class="ste-leading-none">
											<span class="ste-absolute ste-top-0 ste-right-0 ste-text-[10px] ste-uppercase ste-bg-slate-200 ste-text-slate-500 ste-px-1 ste-py-0.5 ste-rounded ste-tracking-wide">
												<?php esc_html_e( 'Coming Soon' ); ?>
											</span>
										</div>
									<?php else : ?>
										<div class="ste-relative ste-leading-none">
											<input type="checkbox" name="<?php echo esc_attr( $slug ); ?>"
												id="builder-label-<?php echo $slug; ?>" class="ste-toggle-input" <?php checked( $builder['status'] ); ?>>
											<div class="ste-toggle-line ste-w-5 ste-h-2 ste-bg-ash-600 ste-rounded-full ste-shadow-inner"></div>
											<div class="ste-toggle-dot ste-absolute ste-w-4 ste-h-4 ste-bg-white ste-rounded-full ste-shadow ste-inset-y-0 ste-left-0"></div>
										</div>
									<?php endif; ?>
							</div>
							<?php if ( isset( $builder['description'] ) && $builder['description'] ) : ?>
								<div class="ste-text-xs ste-mt-1">
									<?php echo $builder['description']; ?>
								</div>
							<?php endif; ?>
							<?php if ( isset( $builder['require'] ) && isset( $builder['require']['text'] ) && isset( $builder['require']['text']['plugins'] ) ) : ?>
								<div class="ste-text-xs ste-mt-2">
									<?php echo $builder['require']['text']['plugins']; ?>
								</div>
							<?php endif; ?>
						</label>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		
		<input type="hidden" name="action" value="stax_visibility_builders_activation">
		<?php wp_nonce_field( 'stax_visibility_builders_activation' ); ?>

		<div class="ste-mt-5">
			<button type="submit"
					class="ste-bg-gradient-to-r ste-from-green-500 ste-to-green-400 ste-text-md ste-text-white ste-py-3 ste-px-6 ste-rounded ste-border-0 ste-shadow-xl hover:ste-shadow-lg ste-cursor-pointer">
				<span class="ste-flex ste-items-center">
					<svg class="ste-fill-current" xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"><path d="M20.285 2l-11.285 11.567-5.286-5.011-3.714 3.716 9 8.728 15-15.285z"/></svg>
					<span class="ste-leading-none ste-font-bold ste-ml-2 ste-uppercase"><?php esc_html_e( 'Save', 'visibility-logic-elementor' ); ?></span>
				</span>
			</button>
		</div>
	</form>
</div>
