<?php
/**
 * 后台检测页面逻辑。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 注册管理菜单。
 */
function zibi_blc_add_checker_menu() {
	add_menu_page(
		'Zibi 链接状态',
		'链接状态',
		'manage_options',
		'zibi-link-checker-status',
		'zibi_blc_status_page',
		'dashicons-admin-links',
		60
	);
}
add_action( 'admin_menu', 'zibi_blc_add_checker_menu' );

/**
 * 渲染状态页面。
 */
function zibi_blc_status_page() {
	// 获取配置的 Meta Key
	$options = get_option( 'zibi_blc_settings' );
	$meta_key = isset( $options['zibi_link_meta_key'] ) ? $options['zibi_link_meta_key'] : '';

	if ( empty( $meta_key ) ) {
		echo '<div class="notice notice-error"><p>请先在 <a href="' . admin_url('options-general.php?page=zibi-link-checker') . '">设置页面</a> 配置资源链接 Meta Key。</p></div>';
		return;
	}

	// 简单的分页逻辑
	$paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
	$posts_per_page = 20;

	$args = array(
		'post_type'      => 'post',
		'posts_per_page' => $posts_per_page,
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
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline">资源链接状态</h1>
		<button id="zibi-blc-check-all-site" class="page-title-action">检测全站所有链接</button>
		<hr class="wp-header-end">

		<div id="zibi-progress-wrap" style="<?php echo get_transient( 'zibi_blc_background_processing' ) ? '' : 'display:none;'; ?> margin: 20px 0; background: #fff; padding: 15px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
			<p><strong>正在后台检测全站链接...</strong> <span id="zibi-progress-text">请稍候...</span></p>
			<div style="background: #f0f0f1; border-radius: 3px; height: 20px; overflow: hidden;">
				<div id="zibi-progress-bar" style="background: #2271b1; width: 0%; height: 100%; transition: width 0.3s;"></div>
			</div>
			<p class="description" style="margin-top:5px;">您可以关闭此页面，检测将在后台继续运行。</p>
		</div>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col" class="manage-column column-title">文章标题</th>
					<th scope="col" class="manage-column column-link">资源链接</th>
					<th scope="col" class="manage-column column-status">状态</th>
					<th scope="col" class="manage-column column-date">最后检测</th>
					<th scope="col" class="manage-column column-action">操作</th>
				</tr>
			</thead>
			<tbody id="the-list">
				<?php if ( $query->have_posts() ) : ?>
					<?php while ( $query->have_posts() ) : $query->the_post(); 
						$post_id = get_the_ID();
						$link = zibi_blc_get_target_link( $post_id );
						$status = get_post_meta( $post_id, '_zibi_link_status', true );
						$code = get_post_meta( $post_id, '_zibi_link_code', true );
						$last_checked = get_post_meta( $post_id, '_zibi_link_last_checked', true );

						$status_label = '<span class="dashicons dashicons-minus"></span> 待检测';
						$row_class = '';
						if ( $status === 'valid' ) {
							$status_label = '<span class="dashicons dashicons-yes" style="color:green"></span> 有效';
						} elseif ( $status === 'invalid' ) {
							$status_label = '<span class="dashicons dashicons-no" style="color:red"></span> 失效 (' . $code . ')';
							$row_class = 'zibi-invalid-row';
						}
					?>
					<tr class="<?php echo $row_class; ?>" data-post-id="<?php echo $post_id; ?>">
						<td class="title column-title">
							<strong><a href="<?php echo get_edit_post_link(); ?>"><?php the_title(); ?></a></strong>
						</td>
						<td class="link column-link">
							<code class="zibi-link-text"><?php echo esc_html( wp_trim_words( $link, 5, '...' ) ); ?></code>
						</td>
						<td class="status column-status">
							<span class="zibi-status-display"><?php echo $status_label; ?></span>
						</td>
						<td class="date column-date">
							<span class="zibi-date-display"><?php echo $last_checked ? wp_date( 'Y-m-d H:i', $last_checked ) : '-'; ?></span>
						</td>
						<td class="action column-action">
							<button type="button" class="button zibi-edit-link-btn" data-id="<?php echo $post_id; ?>" data-link="<?php echo esc_attr($link); ?>">修改链接</button>
						</td>
					</tr>
					<?php endwhile; ?>
				<?php else : ?>
					<tr><td colspan="5">没有找到包含资源链接的文章。</td></tr>
				<?php endif; ?>
			</tbody>
		</table>

		<?php
		// 分页链接
		$big = 999999999;
		echo '<div class="tablenav bottom"><div class="tablenav-pages">';
		echo paginate_links( array(
			'base' => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
			'format' => '?paged=%#%',
			'current' => max( 1, get_query_var('paged') ),
			'total' => $query->max_num_pages
		) );
		echo '</div></div>';
		wp_reset_postdata();
		?>
	</div>
	<?php
}
