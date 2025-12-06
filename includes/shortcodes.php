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

	// 查询包含该 Meta Key 的文章
	$args = array(
		'post_type'      => 'post',
		'posts_per_page' => $atts['posts_per_page'],
		'paged'          => $paged,
		'meta_query'     => array(
			array(
				'key'     => $meta_key,
				'compare' => 'EXISTS',
			),
		),
	);

	$query = new WP_Query( $args );

	if ( ! $query->have_posts() ) {
		return '<div class="zibi-alert info">暂无包含资源链接的文章。</div>';
	}

	ob_start();
	?>
	<div class="zibi-link-list-container">
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
