<?php
/**
 * 处理链接检测逻辑 (后台版)。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
		'timeout'     => 10, 
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
 * 文章发布/更新时自动检测链接。
 */
function zibi_blc_auto_check_on_publish( $post_id ) {
	// 如果是自动保存或修订版本，不处理
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	// 仅处理 post 类型
	if ( get_post_type( $post_id ) !== 'post' ) {
		return;
	}

	// 仅处理已发布状态
	if ( get_post_status( $post_id ) !== 'publish' ) {
		return;
	}

	// 获取资源链接
	$link = zibi_blc_get_target_link( $post_id );

	// 如果数据库中没有，尝试从 $_POST 中获取 (针对新发布文章，Meta 可能尚未写入数据库)
	if ( empty( $link ) && isset( $_POST['posts_zibpay'] ) ) {
		$link = zibi_blc_parse_zibpay_meta( $_POST['posts_zibpay'] );
	}

	// 如果没有链接，直接忽略
	if ( empty( $link ) ) {
		return;
	}

	// 执行检测
	$result = zibi_blc_perform_check( $link );

	// 更新 Meta
	update_post_meta( $post_id, '_zibi_link_status', $result['status'] );
	update_post_meta( $post_id, '_zibi_link_code', $result['code'] );
	update_post_meta( $post_id, '_zibi_link_last_checked', time() );
}
add_action( 'save_post', 'zibi_blc_auto_check_on_publish', 999 );
