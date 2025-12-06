<?php
/**
 * 处理短代码逻辑。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 短代码: [zibi_link_status]
 * 显示当前文章的链接状态（读取数据库缓存）。
 */
function zibi_blc_shortcode_status() {
	$post_id = get_the_ID();
	$status = get_post_meta( $post_id, '_zibi_link_status', true );
	$code = get_post_meta( $post_id, '_zibi_link_code', true );
	$last_checked = get_post_meta( $post_id, '_zibi_link_last_checked', true );

	if ( empty( $status ) ) {
		return '<span class="zibi-link-status pending">待检测</span>';
	}

	$class = 'zibi-link-status ' . esc_attr( $status );
	$text = ( $status === 'valid' ) ? '链接有效' : '链接失效 (' . esc_html( $code ) . ')';
	
	// 可选：显示最后检测时间
	// $date = date( 'Y-m-d H:i', $last_checked );
	// $text .= ' <small>(' . $date . ')</small>';

	return '<span class="' . $class . '">' . $text . '</span>';
}
add_shortcode( 'zibi_link_status', 'zibi_blc_shortcode_status' );

/**
 * 短代码: [zibi_link_status_list]
 * 显示全站资源链接状态列表。
 */
function zibi_blc_shortcode_list( $atts ) {
	$atts = shortcode_atts( array(
		'posts_per_page' => 20,
	), $atts );

	$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

	// 获取配置的 Meta Key
	$options = get_option( 'zibi_blc_settings' );
	$meta_key = isset( $options['zibi_link_meta_key'] ) ? $options['zibi_link_meta_key'] : '';

	if ( empty( $meta_key ) ) {
		return '<div class="zibi-alert error">请先在后台配置资源链接 Meta Key。</div>';
	}

	// 获取搜索关键词
	$search_query = isset( $_GET['zibi_search'] ) ? sanitize_text_field( $_GET['zibi_search'] ) : '';

	// 查询包含该 Meta Key 的文章
	$args = array(
		'post_type'      => 'post',
		'posts_per_page' => $atts['posts_per_page'],
		'paged'          => $paged,
		's'              => $search_query, // 搜索功能
		'meta_query'     => array(
			'relation' => 'AND',
			array(
				'key'     => $meta_key,
				'compare' => 'EXISTS',
			),
			// 确保有状态字段，以便排序 (可选，如果想把未检测的放最后)
			array(
				'key'     => '_zibi_link_status',
				'compare' => 'EXISTS',
			),
		),
		// 排序：失效的 (invalid) 在前，有效的 (valid) 在后。
		// 字母顺序：invalid < valid，所以用 ASC。
		'orderby'        => 'meta_value',
		'meta_key'       => '_zibi_link_status',
		'order'          => 'ASC',
	);

	$query = new WP_Query( $args );

	ob_start();
	?>
	<div class="zibi-link-list-container">
		<!-- 搜索框 -->
		<form method="get" action="" class="zibi-search-form" style="margin-bottom: 20px;">
			<input type="text" name="zibi_search" placeholder="搜索文章标题..." value="<?php echo esc_attr( $search_query ); ?>" style="padding: 8px; width: 200px;">
			<button type="submit" class="button" style="padding: 8px 15px; cursor: pointer;">搜索</button>
			<?php if ( $search_query ) : ?>
				<a href="<?php echo remove_query_arg( 'zibi_search' ); ?>" style="margin-left: 10px;">清除搜索</a>
			<?php endif; ?>
		</form>

	<?php
	if ( ! $query->have_posts() ) {
		echo '<div class="zibi-alert info">没有找到相关文章。</div></div>';
		wp_reset_postdata();
		return ob_get_clean();
	}
	?>

		<table class="zibi-link-table">
			<thead>
				<tr>
					<th>文章标题</th>
					<th>状态</th>
					<th>最后检测</th>
				</tr>
			</thead>
			<tbody>
				<?php while ( $query->have_posts() ) : $query->the_post(); 
					$post_id = get_the_ID();
					$status = get_post_meta( $post_id, '_zibi_link_status', true );
					$code = get_post_meta( $post_id, '_zibi_link_code', true );
					$last_checked = get_post_meta( $post_id, '_zibi_link_last_checked', true );
					
					$status_class = $status ? $status : 'pending';
					$status_text = '待检测';
					if ( $status === 'valid' ) {
						$status_text = '有效';
					} elseif ( $status === 'invalid' ) {
						$status_text = '失效 (' . $code . ')';
					}
				?>
				<tr>
					<td><a href="<?php the_permalink(); ?>" target="_blank"><?php the_title(); ?></a></td>
					<td><span class="zibi-status-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_text ); ?></span></td>
					<td><?php echo $last_checked ? wp_date( 'Y-m-d', $last_checked ) : '-'; ?></td>
				</tr>
				<?php endwhile; ?>
			</tbody>
		</table>

		<div class="zibi-pagination">
			<?php
			echo paginate_links( array(
				'total' => $query->max_num_pages,
				'current' => $paged,
				'prev_text' => '&laquo; 上一页',
				'next_text' => '下一页 &raquo;',
			) );
			?>
		</div>
	</div>
	<?php
	wp_reset_postdata();
	return ob_get_clean();
}
add_shortcode( 'zibi_link_status_list', 'zibi_blc_shortcode_list' );
