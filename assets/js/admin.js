jQuery(document).ready(function ($) {
    // 检查页面加载时是否已经在运行
    if ($('#zibi-progress-wrap').is(':visible')) {
        $('#zibi-blc-check-all-site').prop('disabled', true).text('后台检测中...');
        pollStatus();
    }

    // 启动后台全站检测
    $('#zibi-blc-check-all-site').on('click', function () {
        if (!confirm('确定要启动后台全站检测吗？任务将在后台运行。')) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('正在启动...');

        $.ajax({
            url: zibi_blc_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'zibi_blc_start_background_check',
                nonce: zibi_blc_vars.nonce
            },
            success: function (response) {
                if (response.success) {
                    $('#zibi-progress-wrap').slideDown();
                    pollStatus();
                } else {
                    alert('启动失败: ' + response.data);
                    $btn.prop('disabled', false).text('检测全站所有链接');
                }
            },
            error: function () {
                alert('网络请求失败');
                $btn.prop('disabled', false).text('检测全站所有链接');
            }
        });
    });

    function pollStatus() {
        $.ajax({
            url: zibi_blc_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'zibi_blc_get_background_status',
                nonce: zibi_blc_vars.nonce
            },
            success: function (response) {
                if (response.success) {
                    var data = response.data;
                    if (data.running) {
                        var processed = data.processed;
                        var total = data.total;
                        var percent = Math.min(100, Math.round((processed / total) * 100));

                        $('#zibi-progress-bar').css('width', percent + '%');
                        $('#zibi-progress-text').text(percent + '% (' + processed + '/' + total + ')');

                        // 继续轮询 (加快频率以显示实时进度)
                        setTimeout(pollStatus, 1000);
                    } else {
                        // 任务结束
                        $('#zibi-progress-bar').css('width', '100%');
                        $('#zibi-progress-text').text('检测完成！');
                        setTimeout(function () {
                            alert('后台检测已完成！');
                            location.reload(); // 刷新以显示最新状态
                        }, 1000);
                    }
                }
            },
            error: function () {
                // 网络错误，稍后重试
                setTimeout(pollStatus, 5000);
            }
        });
    }
    // 修改链接
    $(document).on('click', '.zibi-edit-link-btn', function () {
        var $btn = $(this);
        var postId = $btn.data('id');
        var currentLink = $btn.data('link');
        var $row = $btn.closest('tr');

        var newLink = prompt('请输入新的百度网盘链接:', currentLink);

        if (newLink === null || newLink === currentLink) {
            return; // 取消或未修改
        }

        if (!newLink) {
            alert('链接不能为空');
            return;
        }

        $btn.prop('disabled', true).text('更新中...');

        $.ajax({
            url: zibi_blc_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'zibi_blc_update_link',
                post_id: postId,
                new_link: newLink,
                nonce: zibi_blc_vars.nonce
            },
            success: function (response) {
                if (response.success) {
                    var data = response.data;
                    alert('更新成功！');

                    // 更新 UI
                    $row.find('.zibi-link-text').text(data.new_link);
                    $btn.data('link', data.new_link); // 更新按钮上的 data-link

                    var color = (data.status === 'valid') ? 'green' : 'red';
                    var icon = (data.status === 'valid') ? 'yes' : 'no';
                    $row.find('.zibi-status-display').html('<span class="dashicons dashicons-' + icon + '" style="color:' + color + '"></span> ' + (data.status === 'valid' ? '有效' : '失效 (' + data.code + ')'));
                    $row.find('.zibi-date-display').text(data.last_checked);

                    if (data.status === 'invalid') {
                        $row.addClass('zibi-invalid-row');
                    } else {
                        $row.removeClass('zibi-invalid-row');
                    }
                } else {
                    alert('更新失败: ' + response.data);
                }
            },
            error: function () {
                alert('网络请求失败');
            },
            complete: function () {
                $btn.prop('disabled', false).text('修改链接');
            }
        });
    });
});
