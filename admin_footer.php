<?php
/** قالب پایین پنل مدیریت */
?>
    </div><!-- admin-page -->

    <footer class="admin-foot no-print">
      <span>© <?= e(settings('site_name')) ?> | پنل مدیریت نسخه <?= fa_num(APP_VERSION) ?></span>
      <span>آخرین ورود: <?= jdate(current_user()['last_login_at'] ?? null, true) ?></span>
    </footer>
  </div><!-- admin-content -->
</div><!-- admin-shell -->

<script src="app.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
