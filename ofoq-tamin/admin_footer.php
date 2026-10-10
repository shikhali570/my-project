    </main><!-- admin-page -->

    <footer class="admin-foot no-print">
      <span>© <?= e(settings('site_name')) ?> <span class="admin-foot__divider">·</span> نسخه <?= fa_text(APP_VERSION) ?></span>
      <span>آخرین ورود مدیر: <?= jdate(current_user()['last_login_at'] ?? null, true) ?></span>
    </footer>
  </div><!-- admin-content -->
</div><!-- admin-shell -->

<script src="app.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
