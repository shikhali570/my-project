  </section><!-- panel-main -->
</div><!-- panel-layout -->
</div><!-- page-wrapper -->

<footer class="panel-footer no-print">
  <div class="container panel-footer__inner">
    <span>© <?= e(settings('site_name')) ?> <span>·</span> کارپوشه خریدار سازمانی</span>
    <span>پشتیبانی <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string)settings('phone'))) ?>"><?= e(settings('phone')) ?></a> <span>·</span> <a href="mailto:<?= e(settings('email')) ?>"><?= e(settings('email')) ?></a></span>
  </div>
</footer>

<script src="app.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
