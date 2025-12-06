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
function zibi_blc_inject_status_to_paid_box( $html, $pay_mate, $post_id ) {
	// 如果 HTML 为空，说明没有渲染资源框，直接返回
	if ( empty( $html ) ) {
		return $html;
	}

	// 获取链接状态
	$status = get_post_meta( $post_id, '_zibi_link_status', true );
	$last_checked = get_post_meta( $post_id, '_zibi_link_last_checked', true );

	// 如果没有检测记录，不显示
	if ( empty( $status ) ) {
		return $html;
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

	// 构造注入的 HTML 片段
	// 使用 Zibi 主题风格的容器
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

	// 将注入内容添加到 HTML 结尾 (在闭合 div 之前，或者直接追加)
	// zibpay_posts_paid_box 返回的是整个 box 的 HTML。
	// 我们尝试插入到最后一个 </div> 之前，或者直接追加在内部内容之后。
	// 简单起见，我们追加到 box-body 的最后。
	
	// 查找 class="box-body
	// 如果能找到 box-body 的结束位置最好，如果找不到，就直接追加到最后。
	// 由于正则匹配 HTML 风险较大，我们尝试直接追加到 $html 字符串的末尾，但要在最外层 div 闭合之前。
	
	$last_div_pos = strrpos( $html, '</div>' );
	if ( $last_div_pos !== false ) {
		// 插入到最后一个 </div> 之前
		$html = substr_replace( $html, $injection . '</div>', $last_div_pos, 6 );
	} else {
		$html .= $injection;
	}

	return $html;
}
add_filter( 'zibpay_posts_paid_box', 'zibi_blc_inject_status_to_paid_box', 10, 3 );
