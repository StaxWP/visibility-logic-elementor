<div class="ste-container ste-mx-auto">
	<div class="ste-bg-white ste-p-10 ste-rounded ste-shadow-xl">
		<?php do_action( STAX_VISIBILITY_HOOK_PREFIX . $current_slug . '_page_content_before' ); ?>

		<?php do_action( STAX_VISIBILITY_HOOK_PREFIX . $current_slug . '_page_content' ); ?>

		<?php do_action( STAX_VISIBILITY_HOOK_PREFIX . $current_slug . '_page_content_after' ); ?>
	</div>
</div>
