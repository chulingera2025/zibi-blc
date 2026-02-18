<?php
/**
 * 用户信息导出页面：导出非管理员用户邮箱和名称。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zibi_blc_add_export_email_menu() {
	add_submenu_page(
		'zibi-link-checker-status',
		'用户导出',
		'用户导出',
		'manage_options',
		'zibi-link-checker-export-email',
		'zibi_blc_export_email_page'
	);
}
add_action( 'admin_menu', 'zibi_blc_add_export_email_menu' );

/**
 * 处理导出下载请求，需在 send_headers 阶段拦截以避免输出缓冲问题。
 */
function zibi_blc_handle_export_email_download() {
	if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'zibi_blc_download_emails' ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '权限不足' );
	}

	check_admin_referer( 'zibi_blc_export_email' );

	$users = zibi_blc_get_non_admin_users();

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="users-' . date( 'Y-m-d' ) . '.csv"' );
	header( 'Cache-Control: no-cache, no-store, must-revalidate' );

	$output = fopen( 'php://output', 'w' );
	// BOM 标记，确保 Excel 正确识别 UTF-8
	fwrite( $output, "\xEF\xBB\xBF" );
	fputcsv( $output, array( 'email', 'name' ) );

	foreach ( $users as $user ) {
		fputcsv( $output, array( $user['email'], $user['name'] ) );
	}

	fclose( $output );
	exit;
}
add_action( 'admin_init', 'zibi_blc_handle_export_email_download' );

function zibi_blc_export_email_page() {
	$users        = zibi_blc_get_non_admin_users();
	$download_url = wp_nonce_url(
		admin_url( 'admin.php?action=zibi_blc_download_emails' ),
		'zibi_blc_export_email'
	);
	?>
	<div class="wrap">
		<h1>用户导出</h1>
		<div class="notice notice-info">
			<p>导出除管理员（Administrator）以外的所有用户邮箱和名称，CSV 格式。</p>
		</div>

		<div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); max-width: 800px;">
			<p>当前符合条件的用户数：<strong><?php echo count( $users ); ?></strong></p>

			<?php if ( count( $users ) > 0 ) : ?>
				<p>
					<a href="<?php echo esc_url( $download_url ); ?>" class="button button-primary">下载 CSV 文件</a>
				</p>

				<h3>预览（前 20 条）</h3>
				<textarea readonly rows="12" style="width:100%; font-family:monospace;"><?php
					echo esc_textarea( "email, name" );
					$preview = array_slice( $users, 0, 20 );
					foreach ( $preview as $user ) {
						echo esc_textarea( "\n" . $user['email'] . ', ' . $user['name'] );
					}
					if ( count( $users ) > 20 ) {
						echo "\n... 共 " . count( $users ) . ' 条';
					}
				?></textarea>
			<?php else : ?>
				<p>没有符合条件的用户。</p>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * 获取所有非管理员用户的邮箱和名称。
 *
 * @return array [ ['email' => '', 'name' => ''], ... ]
 */
function zibi_blc_get_non_admin_users() {
	$users  = get_users( array(
		'role__not_in' => array( 'administrator' ),
	) );
	$result = array();
	foreach ( $users as $user ) {
		if ( ! empty( $user->user_email ) ) {
			$result[] = array(
				'email' => $user->user_email,
				'name'  => $user->display_name,
			);
		}
	}
	return $result;
}
