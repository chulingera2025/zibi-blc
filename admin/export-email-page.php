<?php
/**
 * 用户邮箱导出页面：导出非管理员用户邮箱。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zibi_blc_add_export_email_menu() {
	add_submenu_page(
		'zibi-link-checker-status',
		'邮箱导出',
		'邮箱导出',
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

	$emails = zibi_blc_get_non_admin_emails();

	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="user-emails-' . date( 'Y-m-d' ) . '.txt"' );
	header( 'Cache-Control: no-cache, no-store, must-revalidate' );

	echo implode( "\n", $emails );
	exit;
}
add_action( 'admin_init', 'zibi_blc_handle_export_email_download' );

function zibi_blc_export_email_page() {
	$emails      = zibi_blc_get_non_admin_emails();
	$download_url = wp_nonce_url(
		admin_url( 'admin.php?action=zibi_blc_download_emails' ),
		'zibi_blc_export_email'
	);
	?>
	<div class="wrap">
		<h1>邮箱导出</h1>
		<div class="notice notice-info">
			<p>导出除管理员（Administrator）以外的所有用户邮箱，每行一个。</p>
		</div>

		<div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); max-width: 800px;">
			<p>当前符合条件的用户数：<strong><?php echo count( $emails ); ?></strong></p>

			<?php if ( count( $emails ) > 0 ) : ?>
				<p>
					<a href="<?php echo esc_url( $download_url ); ?>" class="button button-primary">下载 TXT 文件</a>
				</p>

				<h3>预览（前 20 条）</h3>
				<textarea readonly rows="12" style="width:100%; font-family:monospace;"><?php
					$preview = array_slice( $emails, 0, 20 );
					echo esc_textarea( implode( "\n", $preview ) );
					if ( count( $emails ) > 20 ) {
						echo "\n... 共 " . count( $emails ) . ' 条';
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
 * 获取所有非管理员用户的邮箱。
 *
 * @return array 邮箱列表
 */
function zibi_blc_get_non_admin_emails() {
	$users  = get_users( array(
		'role__not_in' => array( 'administrator' ),
		'fields'       => array( 'user_email' ),
	) );
	$emails = array();
	foreach ( $users as $user ) {
		if ( ! empty( $user->user_email ) ) {
			$emails[] = $user->user_email;
		}
	}
	return $emails;
}
