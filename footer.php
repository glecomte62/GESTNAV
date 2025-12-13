</div> <!-- /.gn-wrapper -->

<footer class="gn-footer">
    <img src="/assets/img/logo.jpg" alt="Logo" height="48"
         style="border-radius: 6px; margin-bottom: .5rem; opacity: .95;">
    <div>
        GESTNAV – v<?= htmlspecialchars(function_exists('gestnav_version') ? gestnav_version() : '1.0') ?>
        — <?= htmlspecialchars(function_exists('gestnav_build_date') ? gestnav_build_date() : '') ?>
    </div>
    <span>Outil interne de gestion des sorties & machines</span>
    <div style="margin-top:.25rem; font-size:.9rem; opacity:.9;">
        Crédits: <strong>Guillaume Lecomte</strong>
        – <a href="https://www.linkedin.com/in/guillaume-lecomte-frbe" target="_blank" rel="noopener">LinkedIn</a>
        · <a href="CHANGELOG.md" target="_blank" rel="noopener">Changelog</a>
        <?php if (function_exists('is_admin') && is_admin()): ?>
            · <a href="docs/index.md" target="_blank" rel="noopener">Docs</a>
        <?php endif; ?>
        · <a href="PROMO_EMAIL.md" target="_blank" rel="noopener" class="text-white-50 text-decoration-none">Promo Email</a>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
