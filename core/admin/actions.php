<?php

use Stax\VisibilityLogic\Plugin;

$has_pro       = Plugin::instance()->has_pro();
$options_class = 'lg:ste-w-3/4';

?>

<div class="ste-container ste-mx-auto">
    <div class="ste-flex ste-flex-wrap ste--mx-2">

        <div class="ste-my-2 ste-px-2 ste-w-full overflow-hidden ste-box-border <?php echo esc_attr( $options_class ); ?>">
            <div class="ste-bg-white ste-p-6 lg:ste-p-10 ste-rounded ste-shadow-lg">
				<?php do_action( STAX_VISIBILITY_HOOK_PREFIX . $current_slug . '_page_content_before' ); ?>

				<?php do_action( STAX_VISIBILITY_HOOK_PREFIX . $current_slug . '_page_content' ); ?>

				<?php do_action( STAX_VISIBILITY_HOOK_PREFIX . $current_slug . '_page_content_after' ); ?>
            </div>
        </div>

        <div class="ste-my-2 ste-px-2 ste-w-full lg:ste-w-1/4 ste-overflow-hidden ste-box-border">
			<?php if ( ! $has_pro ) : ?>
                <div class="ste-bg-blue-500 ste-p-6 lg:ste-p-10 ste-rounded ste-shadow-lg">
                    <div class="sqp_box">

                        <h2 class="ste-mt-0 ste-text-lg ste-text-white">
                            <span class="dashicons dashicons-book ste-mr-2"></span>
                            Get more with PRO
                        </h2>

                        <ul class="ste-m-0 ste-p-0 ste-mb-4 ste-text-white">
                            <li>- Get full access to all visibility options.</li>
                            <li>- Create complex visibility logic to fit your needs.</li>
                            <li>- Custom Elementor template as fallback for hidden elements.</li>
                            <li>- And many more...</li>
                        </ul>
                        <a href="https://staxwp.com/go/visibility-logic" target="_blank"
                           class="focus:ste-shadow-none ste-text-white hover:ste-text-white">
                            Go PRO »
                        </a>

                    </div>
                </div>
			<?php else: ?>
                <div class="ste-bg-blue-50 ste-p-6 lg:ste-p-10 ste-rounded ste-shadow-lg">
                    <div class="sqp_box">

                        <h2 class="ste-mt-0 ste-text-lg">
                            <span class="dashicons dashicons-heart ste-text-red-500 ste-text-2xl ste-mr-2"></span>
                            PRO version
                        </h2>

						<?php if ( class_exists( '\Stax\VisibilityLogicPro\Plugin' ) && \Stax\VisibilityLogicPro\Plugin::instance()->is_license_active() ): ?>
                            Thank you for your purchase and your support!
                            <br><br>
                            <a href="<?php echo esc_url( admin_url( 'options-general.php?page=stax_wc_visibility_logic_activation' ) ); ?>"
                               class="ste-no-underline">
                                Manage License Key »
                            </a>
						<?php else: ?>
                            <strong>Licence is not activated yet!</strong>
                            <br>
                            <a href="<?php echo esc_url( admin_url( 'options-general.php?page=stax_wc_visibility_logic_activation' ) ); ?>"
                               class="ste-no-underline">
                                Add your License Key »
                            </a>

						<?php endif; ?>
                        <br>
                        <br>
                        <a href="https://my.staxwp.com/" target="_blank" class="ste-no-underline">
                            My account & Support site »
                        </a>



                    </div>
                </div>


			<?php endif; ?>
        </div>


    </div>
</div>
