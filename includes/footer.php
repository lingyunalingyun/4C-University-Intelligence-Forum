</main>
<!-- ── 页脚 ── -->
<footer class="site-footer">
  <div class="footer-inner">
    <div class="footer-logo">🎓 <?= SITE_NAME ?></div>
    <div class="footer-links">
      <a href="#">关于我们</a>
      <a href="#">使用规则</a>
      <a href="#">隐私政策</a>
      <a href="#">联系我们</a>
    </div>
    <div class="footer-copy">© 2026 <?= SITE_NAME ?> · 为高校学生打造的智慧交流社区</div>
  </div>
</footer>
<script>
function toggleTheme() {
  var html = document.documentElement;
  var isDark = html.getAttribute('data-theme') === 'dark';
  var next = isDark ? 'light' : 'dark';
  html.setAttribute('data-theme', next);
  localStorage.setItem('theme', next);
  document.getElementById('theme-toggle').textContent = next === 'dark' ? '☀️' : '🌙';
}
// 初始化图标
(function(){
  var btn = document.getElementById('theme-toggle');
  if (btn) btn.textContent = localStorage.getItem('theme') === 'dark' ? '☀️' : '🌙';
})();
</script>
</body>
</html>
