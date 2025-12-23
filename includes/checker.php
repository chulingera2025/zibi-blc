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
 * @param int $max_retries 最大重试次数 (默认 2 次，共检测 3 次)。
 * @return array 包含 status (valid/invalid) 和 code (HTTP 状态码) 的数组。
 */
function zibi_blc_perform_check( $url, $max_retries = 2 ) {
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

	$attempt = 0;
	$status = 'invalid';
	$code = 'error';

	while ( $attempt <= $max_retries ) {
		$response = wp_remote_request( $url, $args );

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

		// 如果检测成功 (valid)，直接跳出循环
		if ( $status === 'valid' ) {
			break;
		}

		// 如果失败且还有重试机会，等待 1 秒后重试
		if ( $attempt < $max_retries ) {
			sleep( 1 );
		}

		$attempt++;
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

/**
 * 处理链接更新请求 (AJAX)。
 */
function zibi_blc_update_link() {
	// 验证 Nonce
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'zibi_blc_admin_nonce' ) ) {
		wp_send_json_error( '安全验证失败' );
	}

	// 验证权限
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( '无权执行此操作' );
	}

	$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
	$new_link = isset( $_POST['new_link'] ) ? trim( $_POST['new_link'] ) : '';

	if ( ! $post_id || empty( $new_link ) ) {
		wp_send_json_error( '参数错误' );
	}

	// 更新链接
	if ( zibi_blc_update_target_link( $post_id, $new_link ) ) {
		// 更新成功后，立即进行一次检测
		$result = zibi_blc_perform_check( $new_link );

		update_post_meta( $post_id, '_zibi_link_status', $result['status'] );
		update_post_meta( $post_id, '_zibi_link_code', $result['code'] );
		update_post_meta( $post_id, '_zibi_link_last_checked', time() );

		wp_send_json_success( array(
			'message' => '链接已更新并重新检测',
			'new_link' => $new_link,
			'status' => $result['status'],
			'code' => $result['code'],
			'last_checked' => wp_date( 'Y-m-d H:i', time() )
		) );
	} else {
		wp_send_json_error( '更新失败，未找到原百度网盘链接或保存出错' );
	}
}
add_action( 'wp_ajax_zibi_blc_update_link', 'zibi_blc_update_link' );
/**
 * 处理导入预览 (AJAX)。
 */
function zibi_blc_preview_import() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'zibi_blc_admin_nonce' ) ) {
		wp_send_json_error( '安全验证失败' );
	}
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( '无权执行此操作' );
	}
	if ( empty( $_FILES['csv_file'] ) ) {
		wp_send_json_error( '未上传文件' );
	}

	$col_title = isset( $_POST['col_title'] ) ? trim( $_POST['col_title'] ) : '名称';
	$col_link = isset( $_POST['col_link'] ) ? trim( $_POST['col_link'] ) : '分享链接';

	$file = $_FILES['csv_file']['tmp_name'];
	if ( ! is_readable( $file ) ) {
		wp_send_json_error( '无法读取文件' );
	}

	// 1. 获取所有文章标题
	// 注意：如果文章数非常多 (几万篇)，这里可能耗内存。
	// 这里假设是数千篇以内。
	$all_posts = get_posts( array(
		'post_type' => 'post',
		'post_status' => 'publish',
		'numberposts' => -1,
		'fields' => 'ids' // 只取 ID，循环里取标题
	) );
	
	$post_map = array();
	foreach ( $all_posts as $pid ) {
		$post_map[$pid] = get_the_title( $pid );
	}

	// 2. 解析 CSV (新增：自动编码检测与转换)
	$raw_content = file_get_contents( $file );
	
	// 检测编码 (优先检测 GBK/GB2312，因为 Excel 导出 CSV 默认常为 ANSI)
	$encoding = mb_detect_encoding( $raw_content, array( 'ASCII', 'UTF-8', 'GBK', 'GB2312', 'CP936' ), true );
	
	if ( $encoding !== 'UTF-8' ) {
		$raw_content = mb_convert_encoding( $raw_content, 'UTF-8', $encoding );
	}
	
	// 按行分割
	$lines = preg_split( '/\r\n|\r|\n/', $raw_content );
	$rows = array();
	foreach ( $lines as $line ) {
		if ( ! empty( trim( $line ) ) ) {
			$rows[] = str_getcsv( $line );
		}
	}

	if ( empty( $rows ) ) {
		wp_send_json_error( 'CSV 文件为空或解析失败' );
	}
	
	$header = array_shift( $rows ); // 假设第一行是表头
	// 简单查找列索引
	$title_idx = -1;
	$link_idx = -1;

	// 处理 BOM 头
	if ( isset($header[0]) ) {
		$header[0] = str_replace( "\xEF\xBB\xBF", '', $header[0] ); 
	}

	foreach ( $header as $i => $h ) {
		$h = trim($h);
		if ( $h === $col_title ) $title_idx = $i;
		if ( $h === $col_link ) $link_idx = $i;
	}

	if ( $title_idx === -1 || $link_idx === -1 ) {
		wp_send_json_error( '找不到指定的列名：' . $col_title . ' 或 ' . $col_link . '。请检查 CSV 表头。' );
	}

	$results = array();

	foreach ( $rows as $row ) {
		if ( count( $row ) <= max( $title_idx, $link_idx ) ) continue;

		$csv_title = trim( $row[$title_idx] );
		$csv_link = trim( $row[$link_idx] );

		if ( empty( $csv_title ) || empty( $csv_link ) ) continue;

		// 3. 匹配算法
		$best_match_id = 0;
		$best_percent = 0;

		// 预处理：移除所有特殊字符 (标点、符号、空格)，只保留字母、数字、汉字等
		// Regex: /[^\p{L}\p{N}]/u (排除所有 非字母 非数字 的字符)
		$clean_csv = preg_replace( '/[^\p{L}\p{N}]/u', '', $csv_title );
		
		foreach ( $post_map as $pid => $ptitle ) {
			$clean_post = preg_replace( '/[^\p{L}\p{N}]/u', '', $ptitle );
			
			// 如果清洗后为空，则回退到原始字符串比较 (极端情况)
			$t1 = empty( $clean_csv ) ? $csv_title : $clean_csv;
			$t2 = empty( $clean_post ) ? $ptitle : $clean_post;
			
			$percent = 0;
			
			// 1. 计算文本相似度 (基于清洗后的字符串)
			similar_text( $t1, $t2, $percent );
			
			// 2. 包含匹配优化
			// 只有当字符串长度 > 4 时才启用
			if ( mb_strlen( $t2 ) > 4 && mb_strlen( $t1 ) > 4 ) {
				// 如果清洗后的标题互相包含
				if ( mb_stripos( $t1, $t2 ) !== false || mb_stripos( $t2, $t1 ) !== false ) {
					$percent = max( $percent, 95.0 );
				}
			}

			if ( $percent > $best_percent ) {
				$best_percent = $percent;
				$best_match_id = $pid;
			}
		}

		$item = array(
			'csv_title' => $csv_title,
			'new_link' => $csv_link,
			'post_id' => 0,
			'post_title' => '',
			'similarity' => round( $best_percent, 1 ),
			'match_level' => 'low'
		);

		if ( $best_percent >= 80 ) {
			$item['post_id'] = $best_match_id;
			$item['post_title'] = $post_map[$best_match_id];
			
			// 检查链接是否一致
			$current_link = zibi_blc_get_target_link( $best_match_id );
			if ( ! empty( $current_link ) && trim( $current_link ) === $csv_link ) {
				$item['match_level'] = 'same'; // 链接相同
			} else {
				$item['match_level'] = 'high';
			}
		}

		$results[] = $item;
	}

	wp_send_json_success( $results );
}
add_action( 'wp_ajax_zibi_blc_preview_import', 'zibi_blc_preview_import' );

/**
 * 执行批量更新 (AJAX)。
 */
function zibi_blc_run_import() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'zibi_blc_admin_nonce' ) ) {
		wp_send_json_error( '安全验证失败' );
	}
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( '无权执行此操作' );
	}
	
	$matches_json = isset( $_POST['matches'] ) ? stripslashes( $_POST['matches'] ) : '';
	$matches = json_decode( $matches_json, true );

	if ( empty( $matches ) || ! is_array( $matches ) ) {
		wp_send_json_error( '数据无效' );
	}

	$updated_count = 0;

	foreach ( $matches as $item ) {
		if ( ! empty( $item['post_id'] ) && ! empty( $item['new_link'] ) ) {
			$success = zibi_blc_update_target_link( $item['post_id'], $item['new_link'] );
			if ( $success ) {
				// 立即检测，状态更新
				$check = zibi_blc_perform_check( $item['new_link'] );
				update_post_meta( $item['post_id'], '_zibi_link_status', $check['status'] );
				update_post_meta( $item['post_id'], '_zibi_link_code', $check['code'] );
				update_post_meta( $item['post_id'], '_zibi_link_last_checked', time() );
				
				$updated_count++;
			}
		}
	}

	wp_send_json_success( array( 'updated_count' => $updated_count ) );
}
add_action( 'wp_ajax_zibi_blc_run_import', 'zibi_blc_run_import' );

/**
 * 搜索文章 (AJAX，用于手动匹配)。
 */
function zibi_blc_search_posts() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'zibi_blc_admin_nonce' ) ) {
		wp_send_json_error( '安全验证失败' );
	}
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( '无权执行此操作' );
	}

	$keyword = isset( $_POST['keyword'] ) ? trim( $_POST['keyword'] ) : '';
	
	if ( empty( $keyword ) ) {
		wp_send_json_success( array() );
	}

	$posts = get_posts( array(
		'post_type' => 'post',
		'post_status' => 'publish',
		's' => $keyword,
		'posts_per_page' => 20,
		'orderby' => 'relevance'
	) );

	$results = array();
	foreach ( $posts as $post ) {
		$results[] = array(
			'id' => $post->ID,
			'title' => $post->post_title
		);
	}

	wp_send_json_success( $results );
}
add_action( 'wp_ajax_zibi_blc_search_posts', 'zibi_blc_search_posts' );
