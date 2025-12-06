<?php
/**
 * 处理前端集成逻辑 (自动注入到 Zibi 主题)。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 将链接状态注入到 Zibi 主题的已付费/已购买资源框中。
 * 挂钩: zibpay_posts_paid_box
 *
 * @param string $html    原始 HTML 内容。
 * @param array  $pay_mate 支付相关 Meta 数据。
 * @param int    $post_id  文章 ID。
 * @return string 修改后的 HTML 内容。
 */
/**
 * 使用 JavaScript 将链接状态注入到 Zibi 主题的资源框中。
 * 由于 Zibi 主题的 PHP 钩子是预过滤钩子，无法直接修改输出，因此采用 JS 注入。
 */
function zibi_blc_frontend_status_script() {
	if ( ! is_singular( 'post' ) ) {
		return;
	}

	$post_id = get_the_ID();
	
	// 获取链接状态
	$status = get_post_meta( $post_id, '_zibi_link_status', true );
	$last_checked = get_post_meta( $post_id, '_zibi_link_last_checked', true );

	// 如果没有检测记录，不显示
	if ( empty( $status ) ) {
		return;
	}

	// 构建状态 HTML
	$status_label = '';
	$status_class = '';
	$icon = '';

	if ( $status === 'valid' ) {
		$status_label = '资源链接有效';
		$status_class = 'zibi-blc-valid';
		$icon = '<i class="fa fa-check-circle" aria-hidden="true"></i>';
	} else {
		$status_label = '资源链接可能失效';
		$status_class = 'zibi-blc-invalid';
		$icon = '<i class="fa fa-exclamation-circle" aria-hidden="true"></i>';
	}

	$date_str = $last_checked ? wp_date( 'Y-m-d H:i', $last_checked ) : '未检测';

	$injection = sprintf(
		'<div class="zibi-blc-status-box %s">' .
		'<div class="status-left">' .
		'<span class="status-icon">%s</span> ' .
		'<span class="status-text"><strong>%s</strong></span>' .
		'</div>' .
		'<span class="status-date">检测时间: %s</span>' .
		'</div>',
		esc_attr( $status_class ),
		$icon,
		esc_html( $status_label ),
		esc_html( $date_str )
	);

	// 输出 JS
	?>
	<script>
	jQuery(document).ready(function($) {
		var statusHtml = <?php echo json_encode( $injection ); ?>;
		var $box = $('.zib-widget.pay-box .box-body');
		
		// 避免重复添加
		if ($box.length && $box.find('.zibi-blc-status-box').length === 0) {
			$box.append(statusHtml);
		}
	});
	</script>
	<?php
}
add_action( 'wp_footer', 'zibi_blc_frontend_status_script' );
