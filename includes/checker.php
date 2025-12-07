<?php
/**
 * 处理链接检测逻辑 (后台版)。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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

	// 默认逻辑：直接读取 Meta Key
	return get_post_meta( $post_id, $meta_key, true );
}

/**
 * 执行单个 URL 检测的核心函数。
 * 
 * @param string $url 要检测的 URL。
 * @return array 包含 status (valid/invalid) 和 code (HTTP 状态码) 的数组。
 */
function zibi_blc_perform_check( $url ) {
	$is_baidu = ( strpos( $url, 'pan.baidu.com' ) !== false );

	// 百度网盘需要 GET 请求获取内容进行关键词匹配
	// 普通链接使用 HEAD 请求以节省资源
	$method = $is_baidu ? 'GET' : 'HEAD';
	
	$args = array(
		'timeout'     => 10, // 百度网盘响应可能较慢，增加超时
		'redirection' => 5,
		'method'      => $method,
		'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36' // 模拟浏览器 UA
	);

	$response = wp_remote_request( $url, $args );

	$status = 'invalid';
	$code = 'error';

	if ( ! is_wp_error( $response ) ) {
		$response_code = wp_remote_retrieve_response_code( $response );
		$code = $response_code;

		// 基础状态码判断
		if ( $response_code >= 200 && $response_code < 400 ) {
			$status = 'valid';

			// 百度网盘特殊检测逻辑
			if ( $is_baidu ) {
				$body = wp_remote_retrieve_body( $response );
				
				// 常见的百度网盘失效关键词
				$invalid_keywords = array(
					'分享的文件已经被取消了',
					'链接不存在',
					'百度网盘-链接不存在',
					'啊哦，你来晚了',
					'该文件已过期',
					'此链接分享内容可能因为涉及侵权'
				);

				foreach ( $invalid_keywords as $keyword ) {
					if ( mb_strpos( $body, $keyword ) !== false ) {
						$status = 'invalid';
						$code = 'content_invalid'; // 自定义状态码，表示内容失效
						break;
					}
				}
			}
		}
	}

	return array(
		'status' => $status,
		'code'   => $code,
	);
}

/**
 * 处理 AJAX 请求以检测链接并更新数据库 (单个)。
 */
function zibi_blc_admin_check_link() {
	// 验证 Nonce 安全性
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'zibi_blc_admin_nonce' ) ) {
		wp_send_json_error( '安全验证失败' );
	}

	// 获取文章 ID
	$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
	if ( ! $post_id ) {
		wp_send_json_error( '无效的文章 ID' );
	}

	// 检查权限
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( '无权执行此操作' );
	}

	// 使用新函数获取链接
	$link = zibi_blc_get_target_link( $post_id );

	if ( empty( $link ) ) {
		wp_send_json_error( '未找到百度网盘资源链接' );
	}

	// 使用核心函数执行检测
	$result = zibi_blc_perform_check( $link );

	// 更新文章 Meta
	update_post_meta( $post_id, '_zibi_link_status', $result['status'] );
	update_post_meta( $post_id, '_zibi_link_code', $result['code'] );
	update_post_meta( $post_id, '_zibi_link_last_checked', time() );

	// 返回结果给前端更新 UI
	wp_send_json_success( array(
		'status' => $result['status'],
		'code' => $result['code'],
		'last_checked' => wp_date( 'Y-m-d H:i', time() ),
		'message' => ( $result['status'] === 'valid' ) ? '有效' : '失效 (' . $result['code'] . ')'
	) );

	wp_die();
}
add_action( 'wp_ajax_zibi_blc_admin_check_link', 'zibi_blc_admin_check_link' );

/**
 * 启动后台全站检测。
 */
function zibi_blc_start_background_check() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'zibi_blc_admin_nonce' ) ) {
		wp_send_json_error( '安全验证失败' );
	}

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( '无权执行此操作' );
	}

	if ( get_transient( 'zibi_blc_background_processing' ) ) {
		wp_send_json_error( '检测任务已在运行中' );
	}

	// 获取总数
	$options = get_option( 'zibi_blc_settings' );
	$meta_key = isset( $options['zibi_link_meta_key'] ) ? $options['zibi_link_meta_key'] : '';

	if ( empty( $meta_key ) ) {
		wp_send_json_error( '未配置 Meta Key' );
	}

	$args = array(
		'post_type'      => 'post',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'meta_query'     => array(
			array(
				'key'     => $meta_key,
				'compare' => 'EXISTS',
			),
		),
		'fields' => 'ids',
	);

	$query = new WP_Query( $args );
	$total = $query->post_count;

	if ( $total == 0 ) {
		wp_send_json_error( '没有找到需要检测的文章' );
	}

	// 初始化任务数据
	$process_data = array(
		'paged' => 1,
		'total' => $total,
		'processed' => 0,
		'start_time' => time(),
	);
	set_transient( 'zibi_blc_background_processing', $process_data, HOUR_IN_SECONDS );

	// 调度立即执行
	wp_schedule_single_event( time(), 'zibi_blc_background_process_event' );

	wp_send_json_success( array( 'message' => '后台任务已启动' ) );
	wp_die();
}
add_action( 'wp_ajax_zibi_blc_start_background_check', 'zibi_blc_start_background_check' );

/**
 * 获取后台任务状态。
 */
function zibi_blc_get_background_status() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'zibi_blc_admin_nonce' ) ) {
		wp_send_json_error( '安全验证失败' );
	}

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( '无权执行此操作' );
	}

	$process_data = get_transient( 'zibi_blc_background_processing' );

	if ( ! $process_data ) {
		wp_send_json_success( array( 'running' => false ) );
	} else {
		wp_send_json_success( array(
			'running' => true,
			'processed' => $process_data['processed'],
			'total' => $process_data['total'],
		) );
	}

	wp_die();
}
add_action( 'wp_ajax_zibi_blc_get_background_status', 'zibi_blc_get_background_status' );

/**
 * 获取需要检测的文章总数 (用于手动批量检测进度条初始值)。
 */
function zibi_blc_get_total_count() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'zibi_blc_admin_nonce' ) ) {
		wp_send_json_error( '安全验证失败' );
	}

	$options = get_option( 'zibi_blc_settings' );
	$meta_key = isset( $options['zibi_link_meta_key'] ) ? $options['zibi_link_meta_key'] : '';

	if ( empty( $meta_key ) ) {
		wp_send_json_error( '未配置 Meta Key' );
	}

	$args = array(
		'post_type'      => 'post',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'meta_query'     => array(
			array(
				'key'     => $meta_key,
				'compare' => 'EXISTS',
			),
		),
		'fields' => 'ids',
	);

	$query = new WP_Query( $args );
	wp_send_json_success( array( 'total' => $query->post_count ) );
	wp_die();
}
add_action( 'wp_ajax_zibi_blc_get_total_count', 'zibi_blc_get_total_count' );

/**
 * 批量检测逻辑 (手动全站 - 兼容旧版前端递归，虽然现在主要用后台版，但保留以防万一)。
 */
function zibi_blc_manual_batch_check() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'zibi_blc_admin_nonce' ) ) {
		wp_send_json_error( '安全验证失败' );
	}

	$paged = isset( $_POST['paged'] ) ? intval( $_POST['paged'] ) : 1;
	$batch_size = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : 10;
	
	$options = get_option( 'zibi_blc_settings' );
	$meta_key = isset( $options['zibi_link_meta_key'] ) ? $options['zibi_link_meta_key'] : '';

	if ( empty( $meta_key ) ) {
		wp_send_json_error( '未配置 Meta Key' );
	}

	$args = array(
		'post_type'      => 'post',
		'posts_per_page' => $batch_size,
		'paged'          => $paged,
		'post_status'    => 'publish',
		'meta_query'     => array(
			array(
				'key'     => $meta_key,
				'compare' => 'EXISTS',
			),
		),
	);

	$query = new WP_Query( $args );
	$results = array();

	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id = get_the_ID();
			
			// 使用新函数获取链接
			$link = zibi_blc_get_target_link( $post_id );

			if ( ! empty( $link ) ) {
				$check_result = zibi_blc_perform_check( $link );

				update_post_meta( $post_id, '_zibi_link_status', $check_result['status'] );
				update_post_meta( $post_id, '_zibi_link_code', $check_result['code'] );
				update_post_meta( $post_id, '_zibi_link_last_checked', time() );

				$results[] = array(
					'post_id' => $post_id,
					'status' => $check_result['status'],
					'code' => $check_result['code'],
					'last_checked' => wp_date( 'Y-m-d H:i', time() ),
					'message' => ( $check_result['status'] === 'valid' ) ? '有效' : '失效 (' . $check_result['code'] . ')'
				);
			}
		}
		wp_reset_postdata();
	}

	wp_send_json_success( array( 'results' => $results ) );
	wp_die();
}
add_action( 'wp_ajax_zibi_blc_manual_batch_check', 'zibi_blc_manual_batch_check' );
