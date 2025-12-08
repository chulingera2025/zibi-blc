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
});
