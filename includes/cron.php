<?php
/**
 * 处理 WP-Cron 定时任务。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 更新 Cron 调度。
 * 在设置保存时触发。
 */
function zibi_blc_update_cron_schedule( $old_value, $value, $option ) {
	$enable_cron = isset( $value['enable_cron'] ) ? $value['enable_cron'] : 0;
	$frequency = isset( $value['cron_frequency'] ) ? $value['cron_frequency'] : 'hourly';

	// 清除旧的调度
	$timestamp = wp_next_scheduled( 'zibi_blc_cron_event' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'zibi_blc_cron_event' );
	}

	// 如果开启，则重新调度
	if ( $enable_cron ) {
		wp_schedule_event( time(), $frequency, 'zibi_blc_cron_event' );
	}
}

/**
 * Cron 任务处理函数：批量检测链接 (自动巡检)。
 * 采用滚动更新策略：每次只检测最久未检测的一批。
 */
function zibi_blc_process_batch() {
	$options = get_option( 'zibi_blc_settings' );
	$meta_key = isset( $options['zibi_link_meta_key'] ) ? $options['zibi_link_meta_key'] : '';
	$batch_size = isset( $options['cron_batch_size'] ) ? intval( $options['cron_batch_size'] ) : 50;

	if ( empty( $meta_key ) ) {
		return;
	}

	// 查询最久未检测的文章
	$args = array(
		'post_type'      => 'post',
		'posts_per_page' => $batch_size,
		'post_status'    => 'publish',
		'meta_query'     => array(
			array(
				'key'     => $meta_key,
				'compare' => 'EXISTS',
			),
		),
		'orderby'        => 'meta_value_num',
		'meta_key'       => '_zibi_link_last_checked',
		'order'          => 'ASC',
	);

	$query = new WP_Query( $args );

	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id = get_the_ID();
			
			// 使用新函数获取链接
			$link = zibi_blc_get_target_link( $post_id );

			if ( ! empty( $link ) ) {
				$result = zibi_blc_perform_check( $link );

				update_post_meta( $post_id, '_zibi_link_status', $result['status'] );
				update_post_meta( $post_id, '_zibi_link_code', $result['code'] );
				update_post_meta( $post_id, '_zibi_link_last_checked', time() );
			}
		}
		wp_reset_postdata();
	}
}
add_action( 'zibi_blc_cron_event', 'zibi_blc_process_batch' );

/**
 * 后台手动检测的处理函数。
 * 由 zibi_blc_background_process_event 触发。
 */
function zibi_blc_handle_background_process() {
	$process_data = get_transient( 'zibi_blc_background_processing' );

	if ( ! $process_data ) {
		return; // 没有正在进行的任务
	}

	$options = get_option( 'zibi_blc_settings' );
	$meta_key = isset( $options['zibi_link_meta_key'] ) ? $options['zibi_link_meta_key'] : '';
	
	// 每次后台处理 20 个，避免超时
	$batch_size = 20; 
	$paged = $process_data['paged'];
	$total = $process_data['total'];
	$processed = $process_data['processed'];

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

	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id = get_the_ID();
			
			// 使用新函数获取链接
			$link = zibi_blc_get_target_link( $post_id );

			if ( ! empty( $link ) ) {
				$result = zibi_blc_perform_check( $link );

				update_post_meta( $post_id, '_zibi_link_status', $result['status'] );
				update_post_meta( $post_id, '_zibi_link_code', $result['code'] );
				update_post_meta( $post_id, '_zibi_link_last_checked', time() );
			}
			$processed++;

			// 实时更新进度 (每检测一个就更新一次 transient，以便前端能看到 +1 的效果)
			$process_data['processed'] = $processed;
			set_transient( 'zibi_blc_background_processing', $process_data, HOUR_IN_SECONDS );
		}
		wp_reset_postdata();
	}

	// 本批次完成，准备下一页
	$process_data['paged'] = $paged + 1;
	set_transient( 'zibi_blc_background_processing', $process_data, HOUR_IN_SECONDS );

	// 检查是否完成
	if ( $processed < $total && $query->have_posts() ) {
		// 还有下一页，调度下一次立即执行 (或者几秒后)
		// 使用 wp_schedule_single_event 链式调用
		wp_schedule_single_event( time() + 5, 'zibi_blc_background_process_event' );
	} else {
		// 完成，删除 transient
		delete_transient( 'zibi_blc_background_processing' );
	}
}
add_action( 'zibi_blc_background_process_event', 'zibi_blc_handle_background_process' );
