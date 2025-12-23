<?php
/**
 * 批量导入更新页面。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zibi_blc_add_import_menu() {
	add_submenu_page(
		'zibi-link-checker-status',
		'批量导入链接',
		'批量导入',
		'manage_options',
		'zibi-link-checker-import',
		'zibi_blc_import_page'
	);
}
add_action( 'admin_menu', 'zibi_blc_add_import_menu' );

function zibi_blc_import_page() {
	?>
	<div class="wrap">
		<h1>批量导入更新链接 (基于标题匹配)</h1>
		<div class="notice notice-info">
			<p>
				<strong>功能说明：</strong> 上传 CSV 文件，系统将自动根据“名称”列与已发布的文章标题进行相似度匹配（>80%）。匹配成功后，将用表格中的链接替换文章的原有链接。<br>
				<strong>注意：</strong> 请先备份数据库！仅支持 UTF-8 编码的 CSV 文件。
			</p>
		</div>

		<div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); max-width: 800px;">
			<table class="form-table">
				<tr>
					<th scope="row"><label for="zibi_csv_file">选择 CSV 文件</label></th>
					<td>
						<input type="file" id="zibi_csv_file" name="zibi_csv_file" accept=".csv">
						<p class="description">文件必须包含列名表头。</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="zibi_col_title">名称/标题 列名</label></th>
					<td>
						<input type="text" id="zibi_col_title" name="zibi_col_title" value="名称" class="regular-text">
						<p class="description">CSV 中对应文章标题的列头名称。</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="zibi_col_link">链接 列名</label></th>
					<td>
						<input type="text" id="zibi_col_link" name="zibi_col_link" value="分享链接" class="regular-text">
						<p class="description">CSV 中对应新资源链接的列头名称。</p>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="button" id="zibi-btn-preview" class="button button-primary">第一步：上传预览</button>
			</p>
		</div>

		<div id="zibi-import-result" style="margin-top: 20px; display: none;">
			<h2>匹配预览 <span id="zibi-match-count" style="font-size: 14px; color: #666;"></span></h2>
			<div style="max-height: 500px; overflow-y: auto;">
				<table class="widefat fixed striped">
					<thead>
						<tr>
							<th style="width: 25%;">CSV 名称</th>
							<th style="width: 25%;">匹配文章标题</th>
							<th style="width: 10%;">相似度</th>
							<th style="width: 30%;">新链接</th>
							<th style="width: 10%;">状态</th>
						</tr>
					</thead>
					<tbody id="zibi-import-tbody">
						<!-- 动态插入 -->
					</tbody>
				</table>
			</div>
			<p class="submit">
				<button type="button" id="zibi-btn-run-import" class="button button-primary button-large">第二步：确认更新</button>
			</p>
		</div>
	</div>

	<script>
	jQuery(document).ready(function($) {
		var previewData = []; // 存储预览结果，用于提交

		// 预览
		$('#zibi-btn-preview').on('click', function() {
			var fileInput = document.getElementById('zibi_csv_file');
			var file = fileInput.files[0];
			if (!file) {
				alert('请先选择文件');
				return;
			}

			var formData = new FormData();
			formData.append('action', 'zibi_blc_preview_import');
			formData.append('csv_file', file);
			formData.append('col_title', $('#zibi_col_title').val());
			formData.append('col_link', $('#zibi_col_link').val());
			formData.append('nonce', zibi_blc_vars.nonce);

			var $btn = $(this);
			$btn.prop('disabled', true).text('分析中...');

			$.ajax({
				url: zibi_blc_vars.ajax_url,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(response) {
					if (response.success) {
						var list = response.data;
						previewData = list; // 保存数据
						renderTable(list);
						$('#zibi-import-result').show();
					} else {
						alert('错误: ' + response.data);
					}
				},
				error: function() {
					alert('网络错误');
				},
				complete: function() {
					$btn.prop('disabled', false).text('第一步：上传预览');
				}
			});
		});

		function renderTable(list) {
			var html = '';
			var validCount = 0;
			var skipCount = 0;

			list.forEach(function(item) {
				var statusClass = '';
				var statusText = '';
				
				if (item.match_level === 'high') {
					statusClass = 'color:green;font-weight:bold;';
					statusText = '已匹配 (ID:' + item.post_id + ')';
					validCount++;
				} else if (item.match_level === 'same') {
					statusClass = 'color:#666;font-style:italic;';
					statusText = '链接一致，无需更新';
					skipCount++;
				} else {
					statusClass = 'color:orange;';
					statusText = '未找到';
				}

				html += '<tr>';
				html += '<td>' + item.csv_title + '</td>';
				html += '<td>' + (item.post_title || '-') + '</td>';
				html += '<td style="' + statusClass + '">' + item.similarity + '%</td>';
				html += '<td>' + item.new_link + '</td>';
				html += '<td>' + statusText + '</td>';
				html += '</tr>';
			});
			$('#zibi-import-tbody').html(html);
			$('#zibi-match-count').text('(共 ' + list.length + ' 行，需更新 ' + validCount + ' 行，跳过 ' + skipCount + ' 行)');
			
			if (validCount === 0) {
				$('#zibi-btn-run-import').prop('disabled', true);
			} else {
				$('#zibi-btn-run-import').prop('disabled', false);
			}
		}

		// 执行导入
		$('#zibi-btn-run-import').on('click', function() {
			if (!confirm('确定要根据匹配结果批量更新链接吗？此操作不可撤销。')) {
				return;
			}

			// 只提交需要更新的 (high)
			var matches = previewData.filter(function(item) {
				return item.match_level === 'high';
			});

			if (matches.length === 0) {
				alert('没有需要更新的数据');
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true).text('正在更新...');
			
			// 简单的批量提交，如果是非常大量的数据，可能需要分批。这里假设只是几十上百条。
			$.ajax({
				url: zibi_blc_vars.ajax_url,
				type: 'POST',
				data: {
					action: 'zibi_blc_run_import',
					matches: JSON.stringify(matches),
					nonce: zibi_blc_vars.nonce
				},
				success: function(response) {
					if (response.success) {
						alert('导入成功! 更新了 ' + response.data.updated_count + ' 个链接。');
						location.reload();
					} else {
						alert('导入失败: ' + response.data);
					}
				},
				error: function() {
					alert('网络错误');
				},
				complete: function() {
					$btn.prop('disabled', false).text('第二步：确认更新');
				}
			});
		});
	});
	</script>
	<?php
}
