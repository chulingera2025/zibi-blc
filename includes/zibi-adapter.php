<?php
/**
 * Zibi 主题适配器。
 * 处理与 Zibi 主题相关的特定逻辑，如 Meta Key 解析。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 解析 Zibi Pay Meta 数据以提取百度网盘链接。
 * 
 * @param array|string $pay_mate Zibi Pay Meta 数据。
 * @return string|false 找到的链接或 false。
 */
function zibi_blc_parse_zibpay_meta( $pay_mate ) {
	if ( empty( $pay_mate ) || empty( $pay_mate['pay_download'] ) ) {
		return false;
	}

	$downloads = $pay_mate['pay_download'];
	$links = array();

	// 兼容新版数组格式
	if ( is_array( $downloads ) ) {
		foreach ( $downloads as $item ) {
			if ( ! empty( $item['link'] ) ) {
				$links[] = trim( $item['link'] );
			}
		}
	} 
	// 兼容旧版字符串格式 (链接|名称|更多|样式 换行分隔)
	else {
		$lines = preg_split( '/\r\n|\r|\n/', $downloads );
		foreach ( $lines as $line ) {
			$parts = explode( '|', $line );
			if ( ! empty( $parts[0] ) ) {
				$links[] = trim( $parts[0] );
			}
		}
	}

	// 过滤百度网盘链接
	foreach ( $links as $link ) {
		if ( strpos( $link, 'pan.baidu.com' ) !== false ) {
			return $link; // 返回找到的第一个百度网盘链接
		}
	}

	return false;
}

/**
 * 获取目标链接 (适配 Zibi 主题，仅提取百度网盘)。
 * 
 * @param int $post_id 文章 ID。
 * @return string|false 找到的百度网盘链接，未找到返回 false。
 */
function zibi_blc_get_target_link( $post_id ) {
	$options = get_option( 'zibi_blc_settings' );
	$meta_key = isset( $options['zibi_link_meta_key'] ) ? $options['zibi_link_meta_key'] : '';

	if ( empty( $meta_key ) ) {
		return false;
	}

	// 特殊适配：如果是 posts_zibpay，则进行复杂解析
	if ( $meta_key === 'posts_zibpay' ) {
		$pay_mate = get_post_meta( $post_id, 'posts_zibpay', true );
		return zibi_blc_parse_zibpay_meta( $pay_mate );
	}

	// 默认逻辑：直接读取 Meta Key
	return get_post_meta( $post_id, $meta_key, true );
}
