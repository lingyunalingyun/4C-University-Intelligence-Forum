<?php
/*
 * includes/footer.php — 全局页面底部
 * 功能：关闭 admin/普通页面的布局容器，输出版权信息和页脚 HTML。
 */
if (!empty($in_admin)): ?>
  </main><!-- /.admin-content -->
</div><!-- /.admin-layout -->
<?php else: ?>
</main>
<?php endif; ?>
<!-- ── 页脚 ── -->
<footer class="site-footer" <?= !empty($in_admin) ? 'style="display:none"' : '' ?>>
  <div class="footer-inner">
    <div class="footer-logo"><?= SITE_NAME ?></div>
    <div class="footer-links">
      <a href="<?= $base ?>pages/about.php">关于我们</a>
      <a href="<?= $base ?>pages/terms.php">使用规则</a>
      <a href="<?= $base ?>pages/privacy.php">隐私政策</a>
      <a href="<?= $base ?>pages/contact.php">联系我们</a>
    </div>
    <div class="footer-copy">© 2026 <?= SITE_NAME ?> · 为高校学生打造的智慧交流社区</div>
  </div>
</footer>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>
if (typeof lucide !== 'undefined') lucide.createIcons();
function toggleTheme() {
  var html = document.documentElement;
  var isDark = html.getAttribute('data-theme') === 'dark';
  var next = isDark ? 'light' : 'dark';
  html.setAttribute('data-theme', next);
  localStorage.setItem('theme', next);
  document.getElementById('theme-toggle').textContent = next === 'dark' ? '亮' : '暗';
}
(function(){
  var btn = document.getElementById('theme-toggle');
  if (btn) btn.textContent = localStorage.getItem('theme') === 'dark' ? '亮' : '暗';
})();

// 汉堡抽屉
(function() {
  var btn      = document.getElementById('menu-btn');
  var drawer   = document.getElementById('nav-drawer');
  var backdrop = document.getElementById('nav-backdrop');
  if (!btn) return;
  function openDrawer()  { btn.classList.add('open'); drawer.classList.add('open'); backdrop.classList.add('open'); }
  function closeDrawer() { btn.classList.remove('open'); drawer.classList.remove('open'); backdrop.classList.remove('open'); }
  btn.addEventListener('click', function() { drawer.classList.contains('open') ? closeDrawer() : openDrawer(); });
  backdrop.addEventListener('click', closeDrawer);
  drawer.querySelectorAll('a').forEach(function(a) { a.addEventListener('click', closeDrawer); });
})();

// 翻书转页动画
(function() {
  var veil = document.getElementById('flip-veil');
  document.querySelectorAll('a[href]').forEach(function(a) {
    a.addEventListener('click', function(e) {
      var href = this.getAttribute('href');
      if (!href || href === '#' || href.startsWith('javascript') || href.startsWith('mailto') || this.target === '_blank') return;
      e.preventDefault();
      document.body.style.animation = 'flipOut .55s cubic-bezier(.4,0,.2,1) both';
      if (veil) {
        veil.style.background = 'linear-gradient(to left, rgba(23,19,12,.1) 0%, transparent 35%)';
        veil.style.animation = 'none';
        veil.offsetHeight;
        veil.style.animation = 'veilIn .55s cubic-bezier(.4,0,.2,1) both';
      }
      setTimeout(function() { location.href = href; }, 550);
    });
  });
})();
</script>
</body>
</html>
