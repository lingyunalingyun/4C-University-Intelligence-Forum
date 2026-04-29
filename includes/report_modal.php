<?php
/*
 * includes/report_modal.php — 举报弹窗共享组件
 * 功能：可举报帖子(type=post)或用户(type=user)，原因单选+补充说明，
 *       通过 openReportModal(type, targetId) 触发，AJAX 提交到 actions/report_submit.php。
 * 权限：需登录（JS层判断，服务端再校验）
 * 引入方：pages/post.php、pages/profile.php（均在 /pages/ 目录下）
 */
?>
<div id="report-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9500;align-items:center;justify-content:center;">
  <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--r-lg);
              padding:24px 26px;width:420px;max-width:94vw;max-height:88vh;overflow-y:auto;
              box-shadow:var(--shadow-md);">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
      <span style="font-size:15px;font-weight:700;color:var(--danger)">🚩 举报</span>
      <button onclick="closeReportModal()"
              style="background:none;border:none;color:var(--txt-3);font-size:18px;cursor:pointer;padding:0;line-height:1;">✕</button>
    </div>

    <p style="font-size:12px;color:var(--txt-2);margin:0 0 12px;">请选择举报原因（必选）</p>

    <!-- 帖子举报原因 -->
    <div id="report-reasons-post" style="display:none;border:1px solid var(--border);border-radius:var(--r);overflow:hidden;">
      <?php
      $post_reasons = [
          '侵犯知识产权', '色情低俗', '违规广告引流',
          '涉政敏感', '散布谣言', '涉嫌诈骗',
          '人身攻击/引战', '垃圾信息', '其他',
      ];
      foreach ($post_reasons as $i => $r): ?>
      <label style="display:flex;align-items:center;gap:10px;padding:9px 13px;cursor:pointer;
                    <?= $i < count($post_reasons)-1 ? 'border-bottom:1px solid var(--border);' : '' ?>">
        <input type="radio" name="report_reason" value="<?= h($r) ?>" style="accent-color:var(--danger);flex-shrink:0;">
        <span style="font-size:13px;color:var(--txt)"><?= h($r) ?></span>
      </label>
      <?php endforeach; ?>
    </div>

    <!-- 用户举报原因 -->
    <div id="report-reasons-user" style="display:none;border:1px solid var(--border);border-radius:var(--r);overflow:hidden;">
      <?php
      $user_reasons = [
          '个人信息违规', '色情低俗', '发布不实信息',
          '人身攻击', '赌博诈骗', '违规引流', '其他',
      ];
      foreach ($user_reasons as $i => $r): ?>
      <label style="display:flex;align-items:center;gap:10px;padding:9px 13px;cursor:pointer;
                    <?= $i < count($user_reasons)-1 ? 'border-bottom:1px solid var(--border);' : '' ?>">
        <input type="radio" name="report_reason" value="<?= h($r) ?>" style="accent-color:var(--danger);flex-shrink:0;">
        <span style="font-size:13px;color:var(--txt)"><?= h($r) ?></span>
      </label>
      <?php endforeach; ?>
    </div>

    <!-- 补充说明 -->
    <div style="margin-top:14px;">
      <label style="font-size:12px;color:var(--txt-2);display:block;margin-bottom:5px;">补充说明（选填）</label>
      <textarea id="report-detail" maxlength="200" placeholder="可以补充描述问题详情…"
                style="width:100%;box-sizing:border-box;background:var(--bg-2);border:1px solid var(--border);
                       color:var(--txt);padding:8px 10px;border-radius:var(--r);font-size:13px;
                       font-family:inherit;resize:vertical;min-height:68px;outline:none;"></textarea>
    </div>

    <div id="report-msg" style="font-size:12px;margin-top:8px;min-height:16px;"></div>

    <div style="display:flex;gap:10px;margin-top:14px;">
      <button onclick="submitReport()"
              style="flex:1;padding:9px;border-radius:var(--r);border:none;cursor:pointer;
                     font-size:13px;font-weight:700;background:var(--danger);color:#fff;font-family:inherit;">
        提交举报
      </button>
      <button onclick="closeReportModal()"
              style="flex:1;padding:9px;border-radius:var(--r);border:1px solid var(--border);
                     background:transparent;color:var(--txt-2);cursor:pointer;font-size:13px;font-family:inherit;">
        取消
      </button>
    </div>
  </div>
</div>

<script>
var _reportType = '', _reportTargetId = 0;
function openReportModal(type, targetId) {
    _reportType = type;
    _reportTargetId = targetId;
    document.getElementById('report-reasons-post').style.display = type === 'post' ? 'block' : 'none';
    document.getElementById('report-reasons-user').style.display = type === 'user' ? 'block' : 'none';
    document.querySelectorAll('input[name="report_reason"]').forEach(function(r){ r.checked = false; });
    document.getElementById('report-detail').value = '';
    document.getElementById('report-msg').textContent = '';
    document.getElementById('report-modal').style.display = 'flex';
}
function closeReportModal() {
    document.getElementById('report-modal').style.display = 'none';
}
function submitReport() {
    var reason = document.querySelector('input[name="report_reason"]:checked');
    if (!reason) {
        var msg = document.getElementById('report-msg');
        msg.textContent = '请选择举报原因';
        msg.style.color = 'var(--danger)';
        return;
    }
    var detail = document.getElementById('report-detail').value.trim();
    var fd = new FormData();
    fd.append('type', _reportType);
    fd.append('target_id', _reportTargetId);
    fd.append('reason', reason.value);
    fd.append('detail', detail);
    fetch('../actions/report_submit.php', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(d) {
            var msg = document.getElementById('report-msg');
            msg.textContent = d.msg || (d.ok ? '举报已提交' : '提交失败');
            msg.style.color = d.ok ? 'var(--success)' : 'var(--danger)';
            if (d.ok) setTimeout(closeReportModal, 1400);
        });
}
document.getElementById('report-modal').addEventListener('click', function(e) {
    if (e.target === this) closeReportModal();
});
</script>
