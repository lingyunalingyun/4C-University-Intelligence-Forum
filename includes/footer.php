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
(function(){
  var wrap = document.getElementById('nav-avatar-wrap');
  var drop = document.getElementById('nav-dropdown');
  if (!wrap || !drop) return;
  wrap.addEventListener('click', function(e){
    e.stopPropagation();
    drop.classList.toggle('open');
  });
  document.addEventListener('click', function(){
    drop.classList.remove('open');
  });
})();
</script>
</body>
</html>
