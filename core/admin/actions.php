<?php

use Stax\VisibilityLogic\Plugin;

$has_pro = Plugin::instance()->has_pro();

?>

<div class="ste-container ste-mx-auto">
	<div class="ste-flex ste-flex-wrap ste--mx-2 ste--my-4">

		<div class="ste-px-2 ste-py-4 ste-w-full lg:ste-w-3/4 overflow-hidden ste-box-border">
			<div class="ste-bg-white ste-p-6 lg:ste-p-10 ste-rounded ste-shadow-lg">
				<?php do_action( STAX_VISIBILITY_HOOK_PREFIX . $current_slug . '_page_content_before' ); ?>

				<?php do_action( STAX_VISIBILITY_HOOK_PREFIX . $current_slug . '_page_content' ); ?>

				<?php do_action( STAX_VISIBILITY_HOOK_PREFIX . $current_slug . '_page_content_after' ); ?>
			</div>
		</div>

		<div class="ste-px-2 ste-py-4 ste-w-full lg:ste-w-1/4 ste-overflow-hidden ste-box-border">
			<?php if ( ! $has_pro ) : ?>
				<div class="ste-relative ste-bg-gradient-to-r ste-from-blue-500 ste-to-indigo-500 ste-p-6 lg:ste-p-10 ste-rounded ste-shadow-lg">
					<span class="ste-absolute ste-right-0 ste-bottom-0 ste-animate-bounce ste-mb-4 ste-mr-4 ste-h-30 ste-w-20 ste-z-10 ste-rotate-45">
						<svg version="1.1" id="go-pro-rocket-icon" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 75 75" style="enable-background:new 0 0 75 75;" xml:space="preserve"><style type="text/css">    .st0{fill:#F35858;}    .st1{fill:#DE4E4E;}    .st2{fill:#FF6868;}    .st3{fill:#FD7878;}    .st4{fill:#ECFAFF;}    .st5{fill:#FCFEFF;}    .st6{fill:#68D4F8;}    .st7{fill:#8DDEF9;}</style><polygon class="st0" points="12,39.6 24.1,27.6 36.8,27.6 24.8,39.6 "/><polygon class="st1" points="35.4,50.2 47.4,38.2 47.4,50.9 35.4,63 "/><path class="st2" d="M40.1,49.4c-3.3,2-6.6,3.3-9.4,3.7c-1.5-4-4.7-7.2-8.7-8.7c0.4-2.8,1.6-6.1,3.7-9.4c3.8,0.1,7.4,1.7,10.1,4.3
							C38.5,42.1,40,45.7,40.1,49.4z"/><path class="st3" d="M25.6,34.9c-2,3.3-3.3,6.6-3.7,9.4c1.9,0.7,3.7,1.9,5.3,3.4l8.5-8.5C32.9,36.5,29.3,35,25.6,34.9z"/><path class="st2" d="M46.1,18.5c5.9-1.7,11-1.7,11.5-1.1c0.5,0.5,0.6,5.6-1.1,11.5c-0.7-2.5-2-4.8-3.8-6.6
							C50.8,20.4,48.5,19.1,46.1,18.5z"/><path class="st3" d="M46.1,18.5c5.9-1.7,11-1.7,11.5-1.1l-4.9,4.9C50.8,20.4,48.5,19.1,46.1,18.5z"/><path class="st4" d="M56.5,28.9c-1.3,4.5-3.7,9.5-7.7,13.6c-2.8,2.8-5.8,5.1-8.7,6.9c-0.1-3.8-1.6-7.4-4.3-10.1
							c-2.8-2.8-6.5-4.3-10.1-4.3c1.7-2.9,4.1-5.9,6.9-8.7c4-4,9.1-6.4,13.6-7.7c2.5,0.7,4.8,2,6.6,3.8C54.6,24.2,55.9,26.5,56.5,28.9z"/><path class="st5" d="M46.1,18.5c-4.5,1.3-9.5,3.7-13.6,7.7c-2.8,2.8-5.1,5.8-6.9,8.7c3.8,0.1,7.4,1.6,10.1,4.3l17-17
							C50.8,20.4,48.5,19.1,46.1,18.5z"/><circle class="st6" cx="46" cy="29" r="3.5"/><path class="st7" d="M43.5,31.5l4.9-4.9c-1.4-1.4-3.6-1.4-4.9,0C42.1,27.9,42.1,30.1,43.5,31.5z"/><path class="st6" d="M25.8,49.2c-7.1-7.1-7.8,7.8-8.5,8.5C18.1,56.9,32.9,56.3,25.8,49.2z"/></svg>
					</span>
					<h2 class="ste-mt-0 ste-text-xl ste-text-white ste-flex ste-items-center">
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
						class="ste-inline-block focus:ste-shadow-none ste-font-bold ste-bg-orange-500 ste-px-6 ste-py-3 ste-text-white hover:ste-text-white ste-no-underline ste-rounded">
						GO PRO
					</a>
				</div>
			<?php else : ?>
				<div class="ste-bg-amber-50 ste-p-6 lg:ste-p-10 ste-rounded ste-shadow-lg">
					<h2 class="ste-mt-0 ste-text-lg ste-flex ste-items-center">
						<span class="dashicons dashicons-heart ste-text-red-500 ste-mr-2"></span>
						PRO version
					</h2>

					<?php if ( class_exists( '\Stax\VisibilityLogicPro\Plugin' ) && \Stax\VisibilityLogicPro\Plugin::instance()->is_license_active() ) : ?>
						Thank you for your purchase and your support!
						<br><br>
						<a href="<?php echo esc_url( admin_url( 'options-general.php?page=stax_wc_visibility_logic_activation' ) ); ?>"
							class="ste-no-underline">
							Manage License Key »
						</a>
					<?php else : ?>
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
			<?php endif; ?>
		</div>


	</div>
</div>
