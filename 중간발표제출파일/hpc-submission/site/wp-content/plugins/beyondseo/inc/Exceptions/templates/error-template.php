<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<div class="rc-error-container">
    <header class="rc-error-header">
        <h1><?php echo esc_html($title ?? ''); ?></h1>
    </header>
    <section class="rc-error-body">
        <p><?php echo esc_html($description ?? ''); ?></p>
		<?php if (!empty($reasons)): ?>
            <ul>
				<?php foreach ($reasons as $beyondseo_reason): ?>
                <li><?php echo esc_html($beyondseo_reason); ?></li>
				<?php endforeach; ?>
            </ul>
		<?php endif; ?>
    </section>
	<?php if (!empty($showFooter)): ?>
        <footer class="rc-error-footer">
            <p><?php echo wp_kses_post($footer ?? ''); ?></p>
        </footer>
	<?php endif; ?>
</div>
