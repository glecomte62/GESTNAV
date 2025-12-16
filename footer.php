</div> <!-- /.gn-wrapper -->

<?php
// Récupérer les infos du club
$clubName = defined('CLUB_NAME') ? CLUB_NAME : (function_exists('get_club_setting') ? get_club_setting('club_name', 'Club ULM') : 'Club ULM');
$clubCity = defined('CLUB_CITY') ? CLUB_CITY : (function_exists('get_club_setting') ? get_club_setting('club_city', '') : '');
$clubWebsite = defined('CLUB_WEBSITE') ? CLUB_WEBSITE : (function_exists('get_club_setting') ? get_club_setting('club_website', '') : '');
$clubEmail = defined('CLUB_EMAIL_FROM') ? CLUB_EMAIL_FROM : (function_exists('get_club_setting') ? get_club_setting('club_email_from', '') : '');
$clubPhone = defined('CLUB_PHONE') ? CLUB_PHONE : (function_exists('get_club_setting') ? get_club_setting('club_phone', '') : '');
$logoPath = defined('CLUB_LOGO_PATH') ? CLUB_LOGO_PATH : (function_exists('get_club_setting') ? get_club_setting('club_logo_path', 'assets/img/logo.png') : 'assets/img/logo.png');
$logoHeight = defined('CLUB_LOGO_HEIGHT') ? CLUB_LOGO_HEIGHT : (function_exists('get_club_setting') ? get_club_setting('club_logo_height', 48) : 48);
$version = GESTNAV_VERSION;
?>

<footer class="gn-footer" style="background: linear-gradient(90deg, #003a64 0%, #0a548b 100%); padding: 2rem 1rem; border-top: 3px solid #00a0c6;">
    <div class="container">
        <div class="row align-items-center">
            <!-- Logo et nom du club -->
            <div class="col-md-4 text-center text-md-start mb-3 mb-md-0">
                <img src="/<?= htmlspecialchars($logoPath) ?>" 
                     alt="<?= htmlspecialchars($clubName) ?>" 
                     height="<?= $logoHeight ?>"
                     style="border-radius: 6px; margin-bottom: .5rem; box-shadow: 0 3px 8px rgba(0,0,0,0.3);">
                <div class="text-white fw-semibold" style="font-size: 1.1rem;">
                    <?= htmlspecialchars($clubName) ?>
                </div>
                <?php if ($clubCity): ?>
                    <div class="text-white-50" style="font-size: 0.9rem;">
                        <i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($clubCity) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Infos centrales -->
            <div class="col-md-4 text-center mb-3 mb-md-0">
                <div class="text-white mb-2" style="font-size: 1rem;">
                    <i class="bi bi-airplane me-2"></i><strong>GESTNAV</strong> <span class="badge bg-primary">v<?= htmlspecialchars($version) ?></span>
                </div>
                <div class="text-white-50" style="font-size: 0.85rem;">
                    Gestion des sorties & machines
                </div>
                <?php if ($clubWebsite): ?>
                    <div class="mt-2">
                        <a href="<?= htmlspecialchars($clubWebsite) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-light">
                            <i class="bi bi-globe me-1"></i>Site du club
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Liens et crédits -->
            <div class="col-md-4 text-center text-md-end">
                <?php if ($clubEmail): ?>
                    <div class="mb-2">
                        <a href="mailto:<?= htmlspecialchars($clubEmail) ?>" class="text-white-50 text-decoration-none" style="font-size: 0.9rem;">
                            <i class="bi bi-envelope-fill me-1"></i><?= htmlspecialchars($clubEmail) ?>
                        </a>
                    </div>
                <?php endif; ?>
                
                <?php if ($clubPhone): ?>
                    <div class="mb-2">
                        <a href="tel:<?= htmlspecialchars(str_replace(' ', '', $clubPhone)) ?>" class="text-white-50 text-decoration-none" style="font-size: 0.9rem;">
                            <i class="bi bi-telephone-fill me-1"></i><?= htmlspecialchars($clubPhone) ?>
                        </a>
                    </div>
                <?php endif; ?>
                
                <div class="text-white-50" style="font-size: 0.85rem;">
                    <a href="changelog.php" class="text-white-50 text-decoration-none">
                        <i class="bi bi-clock-history me-1"></i>Changelog
                    </a>
                    <?php if (function_exists('is_admin') && is_admin()): ?>
                        <span class="mx-2">·</span>
                        <a href="config_generale.php" class="text-white-50 text-decoration-none">
                            <i class="bi bi-sliders me-1"></i>Config
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="mt-2 text-white-50" style="font-size: 0.75rem;">
                    Développé par <strong>Guillaume Lecomte</strong>
                    <br>
                    <a href="https://www.linkedin.com/in/guillaume-lecomte-frbe" target="_blank" rel="noopener" class="text-white-50">
                        <i class="bi bi-linkedin"></i> LinkedIn
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Barre de copyright -->
        <div class="row mt-3 pt-3" style="border-top: 1px solid rgba(255,255,255,0.1);">
            <div class="col-12 text-center text-white-50" style="font-size: 0.75rem;">
                © <?= date('Y') ?> <?= htmlspecialchars($clubName) ?> · Tous droits réservés · GESTNAV v<?= htmlspecialchars($version) ?>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
