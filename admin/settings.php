<?php
/**
 * 处理插件设置页面。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 注册设置菜单。
 */
function zibi_blc_add_admin_menu() {
	add_options_page(
		'Zibi 链接检测设置',
		'Zibi 链接检测',
		'manage_options',
		'zibi-link-checker',
		'zibi_blc_options_page'
	);
}
add_action( 'admin_menu', 'zibi_blc_add_admin_menu' );

/**
 * 注册设置项。
 */
function zibi_blc_settings_init() {
	register_setting( 'zibi_blc_plugin', 'zibi_blc_settings', array(
		'sanitize_callback' => 'zibi_blc_sanitize_settings'
	) );

	add_settings_section(
		'zibi_blc_plugin_section',
		'常规设置',
		'zibi_blc_settings_section_callback',
		'zibi_blc_plugin'
	);

	add_settings_field(
		'zibi_link_meta_key',
		'资源链接 Meta Key',
		'zibi_link_meta_key_render',
		'zibi_blc_plugin',
		'zibi_blc_plugin_section'
	);
	add_settings_section(
		'zibi_blc_cron_section',
		'自动检测设置',
		'zibi_blc_cron_section_callback',
		'zibi_blc_plugin'
	);

	add_settings_field(
		'enable_cron',
		'开启自动检测',
		'zibi_blc_enable_cron_render',
		'zibi_blc_plugin',
		'zibi_blc_cron_section'
	);

	add_settings_field(
		'cron_frequency',
		'检测频率',
		'zibi_blc_cron_frequency_render',
		'zibi_blc_plugin',
		'zibi_blc_cron_section'
	);

	add_settings_field(
		'cron_batch_size',
		'单次检测数量',
		'zibi_blc_cron_batch_size_render',
		'zibi_blc_plugin',
		'zibi_blc_cron_section'
	);
}
add_action( 'admin_init', 'zibi_blc_settings_init' );

/**
 * 设置部分回调函数。
 */
function zibi_blc_settings_section_callback() {
	echo '请配置用于存储资源链接的自定义字段名称（Meta Key）。';
}

function zibi_blc_cron_section_callback() {
	echo '配置后台自动检测任务。建议根据服务器性能调整单次检测数量。';
}

/**
 * 渲染 Meta Key 输入框。
 */
function zibi_link_meta_key_render() {
	$options = get_option( 'zibi_blc_settings' );
	$value = isset( $options['zibi_link_meta_key'] ) ? $options['zibi_link_meta_key'] : 'posts_zibpay';
	?>
	<input type='text' name='zibi_blc_settings[zibi_link_meta_key]' value='<?php echo esc_attr( $value ); ?>' class="regular-text">
	<p class="description">例如: <code>down_url</code> 或 <code>zibi_resources</code>。如果不确定，请咨询主题作者。</p>
	<?php
}

/**
 * 渲染开启自动检测复选框。
 */
function zibi_blc_enable_cron_render() {
	$options = get_option( 'zibi_blc_settings' );
	$value = isset( $options['enable_cron'] ) ? $options['enable_cron'] : 0;
	?>
	<input type='checkbox' name='zibi_blc_settings[enable_cron]' value='1' <?php checked( 1, $value ); ?>>
	<p class="description">开启后，将通过 WP-Cron 在后台自动检测链接。</p>
	<?php
}

/**
 * 渲染检测频率下拉框。
 */
function zibi_blc_cron_frequency_render() {
	$options = get_option( 'zibi_blc_settings' );
	$value = isset( $options['cron_frequency'] ) ? $options['cron_frequency'] : 'hourly';
	?>
	<select name='zibi_blc_settings[cron_frequency]'>
		<option value='hourly' <?php selected( 'hourly', $value ); ?>>每小时</option>
		<option value='twicedaily' <?php selected( 'twicedaily', $value ); ?>>每天两次</option>
		<option value='daily' <?php selected( 'daily', $value ); ?>>每天一次</option>
	</select>
	<?php
}

/**
 * 渲染单次检测数量输入框。
 */
function zibi_blc_cron_batch_size_render() {
	$options = get_option( 'zibi_blc_settings' );
	$value = isset( $options['cron_batch_size'] ) ? $options['cron_batch_size'] : 50;
	?>
	<input type='number' name='zibi_blc_settings[cron_batch_size]' value='<?php echo esc_attr( $value ); ?>' min="1" max="100" class="small-text">
	<p class="description">每次任务运行时检测的链接数量。建议 20-50。</p>
	<?php
}

/**
 * 净化设置输入。
 */
function zibi_blc_sanitize_settings( $input ) {
	$new_input = array();
	if( isset( $input['zibi_link_meta_key'] ) ) {
		$new_input['zibi_link_meta_key'] = sanitize_text_field( $input['zibi_link_meta_key'] );
	}
	if( isset( $input['enable_cron'] ) ) {
		$new_input['enable_cron'] = absint( $input['enable_cron'] );
	}
	if( isset( $input['cron_frequency'] ) ) {
		$new_input['cron_frequency'] = sanitize_text_field( $input['cron_frequency'] );
	}
	if( isset( $input['cron_batch_size'] ) ) {
		$new_input['cron_batch_size'] = absint( $input['cron_batch_size'] );
	}
	
	// 触发 Cron 调度更新
	// 这里不能直接调用 schedule 函数，因为此时设置还没保存到数据库。
	// 我们添加一个 transient 或 action 来在设置保存后更新 schedule。
	add_action( 'update_option_zibi_blc_settings', 'zibi_blc_update_cron_schedule', 10, 3 );
	
	return $new_input;
}

/**
 * 渲染选项页面 HTML。
 */
function zibi_blc_options_page() {
	?>
	<div class="wrap">
		<h1>Zibi 链接检测设置</h1>
		<form action='options.php' method='post'>
			<?php
			settings_fields( 'zibi_blc_plugin' );
			do_settings_sections( 'zibi_blc_plugin' );
			submit_button();
			?>
		</form>

		<hr>

		<h2>短代码使用说明</h2>
		<p>您可以在文章、页面或小工具中使用以下短代码：</p>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th>短代码</th>
					<th>描述</th>
					<th>示例</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><code>[zibi_link_status]</code></td>
					<td>显示当前文章的资源链接状态（有效/失效）。通常用于文章内容中。</td>
					<td><code>[zibi_link_status]</code></td>
				</tr>
				<tr>
					<td><code>[zibi_link_status_list]</code></td>
					<td>显示全站所有资源链接的状态列表，支持分页。建议在新建的页面中使用。</td>
					<td><code>[zibi_link_status_list posts_per_page="20"]</code></td>
				</tr>
			</tbody>
		</table>
		<p class="description">注意：对于 Zibi 主题，插件会自动在资源下载区域显示状态，无需手动添加 <code>[zibi_link_status]</code>。</p>
	</div>
	<?php
}
