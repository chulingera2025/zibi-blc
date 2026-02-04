<?php
/**
 * 分类管理页面：筛选免费/积分文章并批量添加到指定分类。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 注册分类管理子菜单。
 */
function zibi_blc_add_category_menu() {
	add_submenu_page(
		'zibi-link-checker-status',
		'分类管理',
		'分类管理',
		'manage_options',
		'zibi-link-checker-category',
		'zibi_blc_category_page'
	);
}
add_action( 'admin_menu', 'zibi_blc_add_category_menu' );

/**
 * 渲染分类管理页面。
 */
function zibi_blc_category_page() {
	$categories = get_categories( array(
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	) );
	?>
	<div class="wrap">
		<h1>分类管理</h1>
		<div class="notice notice-info">
			<p>
				<strong>功能说明：</strong> 筛选免费或积分购买的文章，批量添加到指定分类。<br>
				<strong>付费类型判断：</strong>
				<code>pay_type = 'no' 或空</code> 为免费文章；
				<code>pay_modo = 'points'</code> 为积分购买文章。
			</p>
		</div>

		<div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); max-width: 800px;">
			<table class="form-table">
				<tr>
					<th scope="row"><label for="zibi_filter_type">筛选类型</label></th>
					<td>
						<select id="zibi_filter_type" name="zibi_filter_type">
							<option value="both">免费 + 积分</option>
							<option value="free">仅免费</option>
							<option value="points">仅积分</option>
						</select>
						<p class="description">选择要筛选的文章付费类型。</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="zibi_target_category">目标分类</label></th>
					<td>
						<select id="zibi_target_category" name="zibi_target_category">
							<option value="">-- 请选择 --</option>
							<?php foreach ( $categories as $cat ) : ?>
								<option value="<?php echo esc_attr( $cat->term_id ); ?>">
									<?php echo esc_html( $cat->name ); ?> (<?php echo intval( $cat->count ); ?> 篇)
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">将筛选出的文章添加到此分类。</p>
					</td>
				</tr>
				<tr>
					<th scope="row">排除选项</th>
					<td>
						<label>
							<input type="checkbox" id="zibi_exclude_existing" name="zibi_exclude_existing" value="1" checked>
							排除已在目标分类中的文章
						</label>
						<p class="description">勾选后，已属于目标分类的文章将不会出现在结果中。</p>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="button" id="zibi-btn-preview-category" class="button button-primary">预览筛选结果</button>
			</p>
		</div>

		<div id="zibi-category-result" style="margin-top: 20px; display: none;">
			<h2>筛选结果 <span id="zibi-category-count" style="font-size: 14px; color: #666;"></span></h2>
			<div style="max-height: 500px; overflow-y: auto;">
				<table class="widefat fixed striped">
					<thead>
						<tr>
							<th style="width: 5%;"><input type="checkbox" id="zibi-select-all" checked></th>
							<th style="width: 35%;">文章标题</th>
							<th style="width: 20%;">付费类型</th>
							<th style="width: 40%;">当前分类</th>
						</tr>
					</thead>
					<tbody id="zibi-category-tbody">
					</tbody>
				</table>
			</div>
			<p class="submit">
				<button type="button" id="zibi-btn-add-category" class="button button-primary button-large">批量添加到分类</button>
			</p>
		</div>
	</div>

	<script>
	jQuery(document).ready(function($) {
		var previewPosts = [];

		// 全选/取消全选
		$('#zibi-select-all').on('change', function() {
			var checked = $(this).is(':checked');
			$('#zibi-category-tbody input[type="checkbox"]').prop('checked', checked);
		});

		// 预览筛选结果
		$('#zibi-btn-preview-category').on('click', function() {
			var filterType = $('#zibi_filter_type').val();
			var targetCategory = $('#zibi_target_category').val();
			var excludeExisting = $('#zibi_exclude_existing').is(':checked') ? 1 : 0;

			if (!targetCategory) {
				alert('请选择目标分类');
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true).text('筛选中...');

			$.ajax({
				url: zibi_blc_vars.ajax_url,
				type: 'POST',
				data: {
					action: 'zibi_blc_preview_category',
					filter_type: filterType,
					target_category: targetCategory,
					exclude_existing: excludeExisting,
					nonce: zibi_blc_vars.nonce
				},
				success: function(response) {
					if (response.success) {
						previewPosts = response.data;
						renderCategoryTable(previewPosts);
						$('#zibi-category-result').show();
					} else {
						alert('错误: ' + response.data);
					}
				},
				error: function() {
					alert('网络错误');
				},
				complete: function() {
					$btn.prop('disabled', false).text('预览筛选结果');
				}
			});
		});

		function renderCategoryTable(posts) {
			var html = '';
			if (posts.length === 0) {
				html = '<tr><td colspan="4" style="text-align:center;">没有符合条件的文章</td></tr>';
				$('#zibi-btn-add-category').prop('disabled', true);
			} else {
				posts.forEach(function(post, index) {
					html += '<tr>';
					html += '<td><input type="checkbox" class="zibi-post-checkbox" data-id="' + post.id + '" checked></td>';
					html += '<td><a href="' + post.edit_url + '" target="_blank">' + post.title + '</a></td>';
					html += '<td>' + post.pay_type_label + '</td>';
					html += '<td>' + post.categories + '</td>';
					html += '</tr>';
				});
				$('#zibi-btn-add-category').prop('disabled', false);
			}
			$('#zibi-category-tbody').html(html);
			$('#zibi-category-count').text('(共 ' + posts.length + ' 篇文章)');
			$('#zibi-select-all').prop('checked', true);
		}

		// 批量添加到分类
		$('#zibi-btn-add-category').on('click', function() {
			var targetCategory = $('#zibi_target_category').val();
			var postIds = [];

			$('#zibi-category-tbody input.zibi-post-checkbox:checked').each(function() {
				postIds.push($(this).data('id'));
			});

			if (postIds.length === 0) {
				alert('请至少选择一篇文章');
				return;
			}

			if (!confirm('确定要将 ' + postIds.length + ' 篇文章添加到所选分类吗？')) {
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true).text('正在添加...');

			$.ajax({
				url: zibi_blc_vars.ajax_url,
				type: 'POST',
				data: {
					action: 'zibi_blc_add_to_category',
					post_ids: JSON.stringify(postIds),
					category_id: targetCategory,
					nonce: zibi_blc_vars.nonce
				},
				success: function(response) {
					if (response.success) {
						alert('成功添加 ' + response.data.success_count + ' 篇文章到分类！');
						// 重新预览刷新列表
						$('#zibi-btn-preview-category').click();
					} else {
						alert('错误: ' + response.data);
					}
				},
				error: function() {
					alert('网络错误');
				},
				complete: function() {
					$btn.prop('disabled', false).text('批量添加到分类');
				}
			});
		});
	});
	</script>
	<?php
}

/**
 * AJAX: 预览符合条件的文章。
 */
function zibi_blc_preview_category_ajax() {
	check_ajax_referer( 'zibi_blc_admin_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( '权限不足' );
	}

	$filter_type      = isset( $_POST['filter_type'] ) ? sanitize_text_field( $_POST['filter_type'] ) : 'both';
	$target_category  = isset( $_POST['target_category'] ) ? absint( $_POST['target_category'] ) : 0;
	$exclude_existing = isset( $_POST['exclude_existing'] ) ? absint( $_POST['exclude_existing'] ) : 0;

	if ( $target_category <= 0 ) {
		wp_send_json_error( '请选择有效的目标分类' );
	}

	$results = zibi_blc_filter_posts_by_pay_type( $filter_type, $exclude_existing ? $target_category : 0 );

	wp_send_json_success( $results );
}
add_action( 'wp_ajax_zibi_blc_preview_category', 'zibi_blc_preview_category_ajax' );

/**
 * AJAX: 批量添加文章到分类。
 */
function zibi_blc_add_to_category_ajax() {
	check_ajax_referer( 'zibi_blc_admin_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( '权限不足' );
	}

	$post_ids    = isset( $_POST['post_ids'] ) ? json_decode( stripslashes( $_POST['post_ids'] ), true ) : array();
	$category_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;

	if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
		wp_send_json_error( '未选择文章' );
	}

	if ( $category_id <= 0 ) {
		wp_send_json_error( '无效的分类 ID' );
	}

	$term = get_term( $category_id, 'category' );
	if ( ! $term || is_wp_error( $term ) ) {
		wp_send_json_error( '分类不存在' );
	}

	$success_count = zibi_blc_add_posts_to_category( $post_ids, $category_id );

	wp_send_json_success( array( 'success_count' => $success_count ) );
}
add_action( 'wp_ajax_zibi_blc_add_to_category', 'zibi_blc_add_to_category_ajax' );

/**
 * 根据付费类型筛选文章。
 *
 * @param string $filter_type 筛选类型：'free', 'points', 'both'
 * @param int    $exclude_category 排除的分类 ID，0 表示不排除
 * @return array 符合条件的文章列表
 */
function zibi_blc_filter_posts_by_pay_type( $filter_type, $exclude_category = 0 ) {
	$args = array(
		'post_type'      => 'post',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'meta_key'       => 'posts_zibpay',
	);

	if ( $exclude_category > 0 ) {
		$args['category__not_in'] = array( $exclude_category );
	}

	$query   = new WP_Query( $args );
	$results = array();

	foreach ( $query->posts as $post ) {
		$pay_meta = get_post_meta( $post->ID, 'posts_zibpay', true );

		if ( ! is_array( $pay_meta ) ) {
			continue;
		}

		$pay_type = isset( $pay_meta['pay_type'] ) ? $pay_meta['pay_type'] : '';
		$pay_modo = isset( $pay_meta['pay_modo'] ) ? $pay_meta['pay_modo'] : '';

		$is_free   = empty( $pay_type ) || $pay_type === 'no';
		$is_points = $pay_modo === 'points';

		$match = false;
		if ( $filter_type === 'free' && $is_free ) {
			$match = true;
		} elseif ( $filter_type === 'points' && $is_points ) {
			$match = true;
		} elseif ( $filter_type === 'both' && ( $is_free || $is_points ) ) {
			$match = true;
		}

		if ( $match ) {
			$post_categories = get_the_category( $post->ID );
			$cat_names       = array();
			foreach ( $post_categories as $cat ) {
				$cat_names[] = $cat->name;
			}

			$pay_type_label = $is_points ? '积分购买' : '免费';

			$results[] = array(
				'id'             => $post->ID,
				'title'          => esc_html( $post->post_title ),
				'edit_url'       => esc_url( get_edit_post_link( $post->ID, 'raw' ) ),
				'pay_type_label' => $pay_type_label,
				'categories'     => esc_html( implode( ', ', $cat_names ) ?: '无分类' ),
			);
		}
	}

	wp_reset_postdata();

	return $results;
}

/**
 * 批量将文章添加到指定分类。
 *
 * @param array $post_ids    文章 ID 数组
 * @param int   $category_id 目标分类 ID
 * @return int 成功添加的数量
 */
function zibi_blc_add_posts_to_category( $post_ids, $category_id ) {
	$success = 0;

	foreach ( $post_ids as $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			continue;
		}

		// append = true，保留现有分类
		$result = wp_set_post_terms( $post_id, array( $category_id ), 'category', true );

		if ( ! is_wp_error( $result ) ) {
			$success++;
		}
	}

	return $success;
}
