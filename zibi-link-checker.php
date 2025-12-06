<?php
/**
 * Plugin Name:       Zibi Link Checker
 * Plugin URI:        https://github.com/your-repo/zibi-link-checker
 * Description:       自用专为 Zibi 主题设计的付费资源链接有效性检测插件。支持后台批量检测、自动定时巡检及前台状态展示。
 * Version:           2.5.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:           chulingera2025
 * Author URI:        https://github.com/chulingera2025
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       zibi-link-checker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // 如果直接访问文件，则退出。
}

// 定义插件常量
define( 'ZIBI_BLC_VERSION', '2.5.0' );
define( 'ZIBI_BLC_PATH', plugin_dir_path( __FILE__ ) );
define( 'ZIBI_BLC_URL', plugin_dir_url( __FILE__ ) );

// 引入必要文件
require_once ZIBI_BLC_PATH . 'admin/settings.php';
require_once ZIBI_BLC_PATH . 'admin/checker-page.php'; // 新增：后台检测页面
require_once ZIBI_BLC_PATH . 'includes/checker.php';
require_once ZIBI_BLC_PATH . 'includes/shortcodes.php'; // 新增：短代码逻辑
require_once ZIBI_BLC_PATH . 'includes/cron.php';       // 新增：定时任务逻辑
require_once ZIBI_BLC_PATH . 'includes/frontend-integration.php'; // 新增：前端自动注入

/**
 * 插件停用时清除定时任务。
 */
function zibi_blc_deactivation() {
	$timestamp = wp_next_scheduled( 'zibi_blc_cron_event' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'zibi_blc_cron_event' );
	}
}
register_deactivation_hook( __FILE__, 'zibi_blc_deactivation' );

/**
 * 加载前端样式。
 */
function zibi_blc_enqueue_scripts() {
	if ( is_singular() || is_page() ) {
		wp_enqueue_style( 'zibi-blc-style', ZIBI_BLC_URL . 'assets/css/style.css', array(), ZIBI_BLC_VERSION );
	}
}
add_action( 'wp_enqueue_scripts', 'zibi_blc_enqueue_scripts' );

/**
 * 加载后台脚本 (用于批量检测)。
 */
function zibi_blc_admin_enqueue_scripts( $hook ) {
	if ( 'toplevel_page_zibi-link-checker-status' !== $hook ) {
		return;
	}

	wp_enqueue_script( 'zibi-blc-admin-script', ZIBI_BLC_URL . 'assets/js/admin.js', array( 'jquery' ), ZIBI_BLC_VERSION, true );
	wp_localize_script( 'zibi-blc-admin-script', 'zibi_blc_vars', array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'zibi_blc_admin_nonce' ),
	) );
}
add_action( 'admin_enqueue_scripts', 'zibi_blc_admin_enqueue_scripts' );
