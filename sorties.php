<?php
require_once 'config.php';
require_once 'auth.php';
require_login();
require_once 'mail_helper.php'; // pour gestnav_send_mail()

// Détecter présence éventuelle de la colonne destination_id
$hasDestinationId = false;
$hasUlmBaseId = false;
try {
    $cols = $pdo->query('SHOW COLUMNS FROM sorties')->fetchAll(PDO::FETCH_COLUMN, 0);
    $hasDestinationId = in_array('destination_id', $cols, true);
    $hasUlmBaseId = in_array('ulm_base_id', $cols, true);
} catch (Throwable $e) {}

// ----- SUPPRESSION D'UNE SORTIE (ADMIN) -----
if (is_admin() && isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->beginTransaction();

        // Supprimer les affectations liées via sortie_machines
        $stmtSmIds = $pdo->prepare("SELECT id FROM sortie_machines WHERE sortie_id = ?");
        $stmtSmIds->execute([$id]);
        $smIds = $stmtSmIds->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($smIds)) {
            // Supprimer les assignations liées
            $in = implode(',', array_fill(0, count($smIds), '?'));
            $stmtDelSa = $pdo->prepare("DELETE FROM sortie_assignations WHERE sortie_machine_id IN ($in)");
            $stmtDelSa->execute($smIds);
        }

        // Supprimer les liaisons machines
        $stmtDelSm = $pdo->prepare("DELETE FROM sortie_machines WHERE sortie_id = ?");
        $stmtDelSm->execute([$id]);

        // Supprimer les inscriptions
        try {
            $stmtDelIns = $pdo->prepare("DELETE FROM sortie_inscriptions WHERE sortie_id = ?");
            $stmtDelIns->execute([$id]);
        } catch (Throwable $e) { /* table optionnelle */ }

        // Supprimer les photos et fichiers
        try {
            $stmtPhotos = $pdo->prepare("SELECT filename FROM sortie_photos WHERE sortie_id = ?");
            $stmtPhotos->execute([$id]);
            $photos = $stmtPhotos->fetchAll(PDO::FETCH_COLUMN);
            foreach ($photos as $pf) {
                $path = __DIR__ . '/uploads/sorties/' . $pf;
                if (is_file($path)) { @unlink($path); }
            }
            $stmtDelPhotos = $pdo->prepare("DELETE FROM sortie_photos WHERE sortie_id = ?");
            $stmtDelPhotos->execute([$id]);
        } catch (Throwable $e) { /* table optionnelle */ }

        // Supprimer la sortie
        $stmt = $pdo->prepare("DELETE FROM sorties WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();
        header('Location: sorties.php?deleted=1');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        header('Location: sorties.php?deleted=0&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// ----- ENVOI DU MAIL À TOUS LES UTILISATEURS POUR UNE SORTIE (ADMIN) -----
if (is_admin() && isset($_GET['notify'])) {
    $sortie_id = (int)$_GET['notify'];

    $stmt = $pdo->prepare("
        SELECT s.*,
               GROUP_CONCAT(DISTINCT m.nom SEPARATOR ', ') AS machines
        FROM sorties s
        LEFT JOIN sortie_machines sm ON sm.sortie_id = s.id
        LEFT JOIN machines m ON m.id = sm.machine_id
        WHERE s.id = ?
        GROUP BY s.id
    ");
    $stmt->execute([$sortie_id]);
    $sortie = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sortie) {
        // Destination pour enrichir le mail
        $dest_label = '';
        if ($hasDestinationId && !empty($sortie['destination_id'])) {
            try {
                $stmtDest = $pdo->prepare('SELECT oaci, nom FROM aerodromes_fr WHERE id = ?');
                $stmtDest->execute([(int)$sortie['destination_id']]);
                if ($rowDest = $stmtDest->fetch(PDO::FETCH_ASSOC)) {
                    $oaci = $rowDest['oaci'] ?? '';
                    $nomd = $rowDest['nom'] ?? '';
                    $dest_label = trim(($oaci ? ($oaci.' – ') : '').$nomd);
                }
            } catch (Throwable $e) { /* ignore si table absente */ }
        }
        $stmtU = $pdo->query("
            SELECT id, nom, prenom, email
            FROM users
            WHERE actif = 1
              AND email IS NOT NULL
              AND email <> ''
        ");
        $users = $stmtU->fetchAll(PDO::FETCH_ASSOC);

        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $baseUrl = $scheme . $host . $baseDir;

        $inscriptionUrl = $baseUrl . '/preinscription_sortie.php?sortie_id=' . $sortie_id;

        $sent   = 0;
        $failed = 0;

        foreach ($users as $u) {
            $nom_complet = trim($u['prenom'] . ' ' . $u['nom']);
            $to          = [$u['email'] => $nom_complet];

            $subject = 'Nouvelle sortie ULM : ' . $sortie['titre'];

            $date_str = htmlspecialchars($sortie['date_sortie'] ?? '', ENT_QUOTES, 'UTF-8');
            $titre    = htmlspecialchars($sortie['titre'] ?? '', ENT_QUOTES, 'UTF-8');
            $desc     = htmlspecialchars($sortie['description'] ?? '', ENT_QUOTES, 'UTF-8');
            $machines = htmlspecialchars($sortie['machines'] ?? 'À définir', ENT_QUOTES, 'UTF-8');
            $nom_html = htmlspecialchars($nom_complet, ENT_QUOTES, 'UTF-8');
            $url_html = htmlspecialchars($inscriptionUrl, ENT_QUOTES, 'UTF-8');

            $html = '
                <p>Bonjour ' . $nom_html . ',</p>
                <p>Une nouvelle sortie ULM vient d\'être créée :</p>
                <ul>
                    <li><strong>Nom :</strong> ' . $titre . '</li>
                    <li><strong>Date / heure :</strong> ' . $date_str . '</li>
                    ' . (!empty($dest_label) ? ('<li><strong>Destination :</strong> ' . htmlspecialchars($dest_label, ENT_QUOTES, 'UTF-8') . '</li>') : '') . '
                    <li><strong>Description :</strong> ' . $desc . '</li>
                    <li><strong>Machines prévues :</strong> ' . $machines . '</li>
                </ul>
                <p style="margin: 1.2em 0;">
                    <a href="' . $url_html . '" 
                       style="display:inline-block;padding:10px 18px;border-radius:4px;
                              background-color:#004b8d;color:#ffffff;text-decoration:none;">
                        Indiquer mes préférences (pré-inscription)
                    </a>
                </p>
                <p style="color:#444;">Sur la page, vous pourrez indiquer une <strong>préférence de machine</strong> et/ou de <strong>coéquipier</strong>. 
                Il s\'agit d\'une <strong>pré-inscription</strong> : la validation définitive sera effectuée par un administrateur en fonction des expériences et des élèves pilotes à intégrer.</p>
                <p style="color:#444;"><em>Note organisation&nbsp;:</em> le club vise <strong>2 sorties par mois</strong>. Les membres inscrits aux deux sorties qui n\'ont pas pu participer à la première sont <strong>prioritaires sur la seconde</strong>, sous réserve de s\'y être inscrits.</p>
                <p>Tu devras être connecté à GestNav avec ton compte pour enregistrer tes préférences.</p>
                <p>À bientôt,<br>Le club ULM</p>
            ';

                $text = "Une nouvelle sortie ULM vient d'être créée : " . $sortie['titre'] .
                    "\nDate/heure : " . $sortie['date_sortie'] .
                    (!empty($dest_label) ? ("\nDestination : " . $dest_label) : '') .
                    "\nConnecte-toi à GestNav pour indiquer tes préférences (pré-inscription).\nLa validation finale sera effectuée par un administrateur." .
                    "\n\nNote organisation : le club vise 2 sorties par mois. Les membres inscrits aux deux sorties qui n'ont pas pu participer à la première sont prioritaires sur la seconde, s'ils s'y sont inscrits.";

            $result = gestnav_send_mail($pdo, $to, $subject, $html, $text);

            if ($result['success']) {
                $sent++;
            } else {
                $failed++;
            }
        }

        header('Location: sorties.php?notified=1&sent=' . $sent . '&failed=' . $failed . '&focus=' . $sortie_id);
        exit;
    } else {
        header('Location: sorties.php?notified=0');
        exit;
    }
}

// Inclure le header (après traitements pour permettre les redirections sans page blanche)
require 'header.php';

// ----- LISTE DES SORTIES -----
$sql = "
SELECT s.*,
       GROUP_CONCAT(DISTINCT m.nom SEPARATOR ', ') AS machines,
       COUNT(sa.id) AS nb_assignations
FROM sorties s
LEFT JOIN sortie_machines sm       ON sm.sortie_id = s.id
LEFT JOIN machines m               ON m.id = sm.machine_id
LEFT JOIN sortie_assignations sa   ON sa.sortie_machine_id = sm.id
GROUP BY s.id
ORDER BY s.date_sortie DESC
";
$whereClause = '';
if (!is_admin()) {
    // Non-admin: n'afficher que les statuts publiés connus
    $whereClause = "WHERE LOWER(REPLACE(s.statut,'_',' ')) IN ('prévue','prevue','terminée','terminee','annulée','annulee')";
}
$sql = "SELECT s.*,\n       GROUP_CONCAT(DISTINCT m.nom SEPARATOR ', ') AS machines,\n       COUNT(sa.id) AS nb_assignations,\n       (SELECT sp.filename FROM sortie_photos sp WHERE sp.sortie_id = s.id ORDER BY sp.created_at DESC LIMIT 1) AS photo_filename"
    . ($hasDestinationId ? ", ad.oaci AS dest_oaci, ad.nom AS dest_nom" : "")
    . ($hasUlmBaseId ? ", ub.oaci AS ulm_oaci, ub.nom AS ulm_nom" : "")
    . "\nFROM sorties s\n"
    . ($hasDestinationId ? "LEFT JOIN aerodromes_fr ad ON ad.id = s.destination_id\n" : "")
    . ($hasUlmBaseId ? "LEFT JOIN ulm_bases_fr ub ON ub.id = s.ulm_base_id\n" : "")
    . "LEFT JOIN sortie_machines sm ON sm.sortie_id = s.id\nLEFT JOIN machines m ON m.id = sm.machine_id\nLEFT JOIN sortie_assignations sa ON sa.sortie_machine_id = sm.id\n"
    . ($whereClause ? ($whereClause . "\n") : "")
    . "GROUP BY s.id\nORDER BY s.date_sortie DESC";
$sorties = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
// Filtre de sécurité côté PHP: masque les sorties "En étude" pour non-admins
if (!is_admin() && $sorties) {
    $allowed = ['prévue','prevue','terminée','terminee','annulée','annulee'];
    $sorties = array_values(array_filter($sorties, function($s) use ($allowed){
        $status = isset($s['statut']) ? (string)$s['statut'] : '';
        $norm = strtolower(trim(str_replace('_', ' ', $status)));
        return in_array($norm, $allowed, true);
    }));
}
$focus_id = isset($_GET['focus']) ? (int)$_GET['focus'] : 0;
?>

<style>
    .sorties-page {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem 1rem 3rem;
    }
    .sorties-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 2rem;
        padding: 1.5rem 1.75rem;
        border-radius: 1.25rem;
        background: linear-gradient(135deg, #004b8d, #00a0c6);
        color: #fff;
        box-shadow: 0 12px 30px rgba(0,0,0,0.25);
    }
    .sorties-header h1 {
        font-size: 1.6rem;
        margin: 0;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }
    .sorties-header p {
        margin: 0.25rem 0 0;
        opacity: 0.9;
        font-size: 0.95rem;
    }
    .sorties-header-icon {
        font-size: 2.4rem;
        opacity: 0.9;
    }
    .card {
        background: #ffffff;
        border-radius: 1.25rem;
        padding: 1.75rem 1.5rem;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(0, 0, 0, 0.03);
        margin-bottom: 1.5rem;
    }
    .flash-message {
        margin-bottom: 1rem;
        font-size: 0.9rem;
        padding: 0.6rem 0.8rem;
        border-radius: 999px;
        background: #e7f7ec;
        color: #0a8a0a;
    }
    .flash-message.error {
        background: #fde8e8;
        color: #b02525;
    }

    .sorties-list-wrapper .sorties-list-title {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        margin-bottom: 0.75rem;
        gap: .75rem;
    }
    .sorties-list-title h2 {
        margin: 0;
        font-size: 1.05rem;
    }
    .sorties-list-title span {
        font-size: 0.8rem;
        color: #666;
    }

    .sorties-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .sortie-card {
        border-radius: 1rem;
        border: 1px solid #e4e9f2;
        padding: 0.75rem 0.9rem;
        display: grid;
        grid-template-columns: 160px minmax(0, 1.4fr) minmax(0, 1fr);
        gap: 0.5rem 1rem;
        font-size: 0.9rem;
    }
    .sorties-row-focus {
        box-shadow: 0 0 0 2px #00a0c6 inset;
        background: #f0fbff;
    }
    @media (max-width: 900px) {
        .sortie-card {
            grid-template-columns: 1fr;
        }
    }
    .sortie-thumb {
        width: 100%;
        border-radius: 0.75rem;
        background: #f2f6fc;
        overflow: hidden;
    }
    .sortie-thumb img { width:100%; height:100%; object-fit: cover; aspect-ratio: 16/9; display:block; }
    .sortie-main {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
    }
    .sortie-header-line {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.35rem 0.6rem;
    }
    .sortie-date {
        font-size: 0.85rem;
        color: #555;
    }
    .sortie-title {
        font-weight: 600;
    }
    .sortie-description {
        font-size: 0.85rem;
        color: #555;
    }
    .sortie-meta {
        font-size: 0.85rem;
        color: #444;
        display: flex;
        flex-wrap: wrap;
        gap: 0.25rem 0.8rem;
    }
    .sortie-meta span {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }
    .sortie-meta-label {
        font-weight: 600;
    }
    .sortie-side {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 0.5rem;
    }
    .sortie-stats {
        font-size: 0.85rem;
        color: #555;
        display: flex;
        flex-wrap: wrap;
        gap: 0.25rem 0.8rem;
    }
    .badge-status {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.6rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .badge-status.prevue {
        background: rgba(0, 150, 0, 0.1);
        color: #0a8a0a;
    }
    .badge-status.terminee {
        background: rgba(0, 120, 180, 0.1);
        color: #005b8a;
    }
    .badge-status.annulee {
        background: rgba(200, 0, 0, 0.06);
        color: #b02525;
    }
    .badge-status.etude {
        background: rgba(156, 39, 176, 0.10);
        color: #6a1b9a;
        border: 1px solid rgba(156, 39, 176, 0.25);
    }
    .sortie-side .sorties-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
    }
    .sorties-actions a {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.8rem;
        text-decoration: none;
        padding: 0.25rem 0.6rem;
        border-radius: 999px;
        border: 1px solid #d0d7e2;
        white-space: nowrap;
    }
    .sorties-actions a.assign {
        border-color: rgba(0, 75, 141, 0.3);
    }
    .sorties-actions a.edit {
        border-color: rgba(120, 120, 120, 0.4);
    }
    .sorties-actions a.delete {
        border-color: rgba(176, 37, 37, 0.4);
    }
    .sorties-actions a.mail {
        border-color: rgba(0, 160, 198, 0.7);
    }
    .sorties-actions a.broadcast {
        background: #0a8a0a;
        color: #ffffff;
        border-color: #0a8a0a;
    }
    .btn-primary-gestnav {
        border: none;
        border-radius: 999px;
        padding: 0.55rem 1.3rem;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        background: linear-gradient(135deg, #004b8d, #00a0c6);
        color: #fff;
        box-shadow: 0 8px 16px rgba(0, 75, 141, 0.35);
        transition: transform 0.1s ease, box-shadow 0.1s ease, filter 0.1s ease;
    }
    .btn-primary-gestnav:hover {
        filter: brightness(1.05);
        transform: translateY(-1px);
        box-shadow: 0 10px 20px rgba(0, 75, 141, 0.4);
    }
</style>

<div class="sorties-page">
    <div class="sorties-header">
        <div>
            <h1>Gestion des sorties</h1>
            <p>Consultez les sorties, gérez les affectations et prévenez les membres par e-mail.</p>
        </div>
        <div class="sorties-header-icon">📅</div>
    </div>

    <?php if (isset($_GET['created'])): ?>
        <div class="flash-message">
            ✅ Sortie créée. Vous pouvez maintenant envoyer un e-mail aux membres pour les prévenir.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div class="flash-message">
            ✅ Sortie mise à jour avec succès.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <?php if ($_GET['deleted'] == '1'): ?>
            <div class="flash-message">✅ Sortie supprimée.</div>
        <?php else: ?>
            <div class="flash-message error">❌ Suppression impossible: <?= htmlspecialchars($_GET['error'] ?? 'Erreur inconnue') ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (isset($_GET['notified'])): ?>
        <?php if ($_GET['notified'] == '1'): ?>
            <?php
                $sent   = isset($_GET['sent'])   ? (int)$_GET['sent']   : 0;
                $failed = isset($_GET['failed']) ? (int)$_GET['failed'] : 0;
            ?>
            <div class="flash-message">
                ✉️ Mail envoyé : <?= $sent ?> utilisateur(s) informé(s)
                <?php if ($failed > 0): ?>
                    – ⚠️ <?= $failed ?> échec(s) (vérifier les adresses).
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="flash-message error">
                ❌ Impossible d'envoyer le mail : sortie introuvable.
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (is_admin()): ?>
        <div style="text-align:right;margin-bottom:1rem;">
            <button class="btn-primary-gestnav" onclick="window.location.href='sortie_edit.php'">
                + Nouvelle sortie
            </button>
        </div>
    <?php endif; ?>

    <div class="card sorties-list-wrapper">
        <div class="sorties-list-title">
            <h2>Liste des sorties</h2>
            <span><?= count($sorties) ?> sortie(s) planifiée(s)</span>
        </div>

        <?php if ($sorties): ?>
            <div class="sorties-list">
                <?php foreach ($sorties as $s): ?>
                    <?php
                        $status_raw = isset($s['statut']) ? trim((string)$s['statut']) : '';
                        $norm_status = trim(str_replace('_', ' ', $status_raw));
                        $norm_status = function_exists('mb_strtolower') ? mb_strtolower($norm_status, 'UTF-8') : strtolower($norm_status);
                        $norm_no_accent = strtr($norm_status, [
                            'é' => 'e','è' => 'e','ê' => 'e','ë' => 'e',
                            'à' => 'a','â' => 'a',
                            'î' => 'i','ï' => 'i',
                            'ô' => 'o','ö' => 'o',
                            'û' => 'u','ü' => 'u',
                            'ç' => 'c'
                        ]);
                        $is_study = (
                            $norm_status === 'en étude' || $norm_no_accent === 'en etude' || strpos($norm_no_accent, 'etude') !== false
                        );
                        $class_status = 'prevue';
                        if ($norm_no_accent === 'terminee') $class_status = 'terminee';
                        if ($norm_no_accent === 'annulee')  $class_status = 'annulee';
                        if ($is_study) $class_status = 'etude';

                        $label_status = $is_study ? 'En étude' : (
                            ($norm_status !== '') ? ucfirst($norm_status) : (($status_raw !== '') ? ucfirst($status_raw) : 'Prévue')
                        );

                        $row_class = ($focus_id === (int)$s['id']) ? 'sorties-row-focus' : '';
                    ?>
                    <div id="sortie-<?= $s['id'] ?>" class="sortie-card <?= $row_class ?>">
                        <div class="sortie-thumb">
                            <?php if (!empty($s['photo_filename'])): ?>
                                <img src="uploads/sorties/<?= htmlspecialchars($s['photo_filename']) ?>" alt="Photo sortie">
                            <?php else: ?>
                                <img src="assets/img/Ulm.jpg" alt="Illustration ULM">
                            <?php endif; ?>
                        </div>
                        <div class="sortie-main">
                            <div class="sortie-header-line">
                                <span class="sortie-date">
                                    <?php if (!empty($s['is_multi_day']) && !empty($s['date_fin'])): ?>
                                        <?= htmlspecialchars('Du ' . date('d/m/Y', strtotime($s['date_sortie'])) . ' au ' . date('d/m/Y', strtotime($s['date_fin']))) ?>
                                    <?php else: ?>
                                        <?= htmlspecialchars($s['date_sortie']) ?>
                                    <?php endif; ?>
                                </span>
                                <span class="sortie-title">
                                    <?= htmlspecialchars($s['titre']) ?>
                                </span>
                                <span class="badge-status <?= $class_status ?>" <?php if (is_admin()) { echo 'title="statut: '.htmlspecialchars($status_raw).' | norm: '.htmlspecialchars($norm_status).' | noaccent: '.htmlspecialchars($norm_no_accent).'"'; } ?>>
                                    <?= htmlspecialchars($label_status) ?>
                                </span>
                            </div>
                            <?php if (!empty($s['description'])): ?>
                                <div class="sortie-description">
                                    <?= htmlspecialchars($s['description']) ?>
                                </div>
                            <?php endif; ?>
                            <div class="sortie-meta">
                                <span>
                                    <span class="sortie-meta-label">Machines :</span>
                                    <?= htmlspecialchars($s['machines']) ?: '—' ?>
                                </span>
                                <?php if (!empty($s['repas_prevu'])): ?>
                                <span style="display:inline-flex;align-items:center;padding:0.18rem 0.55rem;border:1px solid rgba(10,138,10,0.25);border-radius:999px;font-size:0.7rem;background:rgba(10,138,10,0.08);color:#0a8a0a;">
                                    🍽️ Repas prévu
                                </span>
                                <?php endif; ?>
                                <?php
                                    // Priorité: base ULM si présente, sinon aérodrome
                                    $display_dest = false;
                                    $dest_icon = '';
                                    $dest_text = '';
                                    if ($hasUlmBaseId && (!empty($s['ulm_oaci']) || !empty($s['ulm_nom']))) {
                                        $display_dest = true;
                                        $dest_icon = '🪂';
                                        $dest_text = ($s['ulm_oaci'] ? ($s['ulm_oaci'].' – ') : '') . ($s['ulm_nom'] ?? '');
                                    } elseif ($hasDestinationId && (!empty($s['dest_oaci']) || !empty($s['dest_nom']))) {
                                        $display_dest = true;
                                        $dest_icon = '🛩️';
                                        $dest_text = ($s['dest_oaci'] ? ($s['dest_oaci'].' – ') : '') . ($s['dest_nom'] ?? '');
                                    }
                                ?>
                                <?php if ($display_dest): ?>
                                <span style="display:inline-flex;align-items:center;padding:0.18rem 0.55rem;border:1px solid rgba(0,75,141,0.25);border-radius:999px;font-size:0.7rem;background:rgba(0,75,141,0.08);color:#004b8d;">
                                    <?= $dest_icon ?> <?= htmlspecialchars($dest_text) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($s['repas_details'])): ?>
                                <div class="sortie-description">
                                    <span class="sortie-meta-label">Repas :</span>
                                    <?= htmlspecialchars(substr($s['repas_details'], 0, 120)) ?><?= strlen($s['repas_details']) > 120 ? '...' : '' ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="sortie-side">
                            <div class="sortie-stats">
                                <span>
                                    <span class="sortie-meta-label">Affectations :</span>
                                    <?= (int)$s['nb_assignations'] ?>
                                </span>
                            </div>
                            <div class="sorties-actions">
                                <?php
                                    // Badge de priorité pour l'utilisateur courant (s'il est prioritaire)
                                    $priorityBadgeHtml = '';
                                    if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
                                        try {
                                            $stP = $pdo->prepare('SELECT active FROM sortie_priorites WHERE user_id = ?');
                                            $stP->execute([ (int)$_SESSION['user_id'] ]);
                                            $isPriorityUser = (bool)($stP->fetchColumn() ?? 0);
                                            if ($isPriorityUser) {
                                                $priorityBadgeHtml = '<span class="badge-status annulee" style="background:#fde8e8;color:#b00020;border:1px solid #f5b5b5;" title="Vous êtes prioritaire sur la prochaine sortie">PRIORITAIRE</span>';
                                            }
                                        } catch (Throwable $e) { /* no-op */ }
                                    }
                                ?>
                                <?php if (is_admin()): ?>
                                <a href="sortie_detail.php?sortie_id=<?= $s['id'] ?>" class="assign">
                                Détail / Affectations
                                </a>
                                <?php endif; ?>


                                <?php if (is_admin()): ?>
                                    <a href="sortie_edit.php?id=<?= $s['id'] ?>" class="edit">
                                        Éditer
                                    </a>
                                    <a href="sorties.php?notify=<?= $s['id'] ?>" class="mail broadcast"
                                       onclick="return confirm('Envoyer un e-mail à tous les utilisateurs pour cette sortie ?');">
                                        Diffuser la sortie
                                    </a>
                                    <a href="#" class="mail" onclick="openEmailModal(<?= $s['id'] ?>, <?= htmlspecialchars(json_encode($s['titre']), ENT_QUOTES, 'UTF-8') ?>); return false;">
                                        Mail inscrits
                                    </a>
                                    <a href="sorties.php?delete=<?= $s['id'] ?>"
                                       class="delete"
                                       onclick="return confirm('Supprimer cette sortie ?');">
                                        Supprimer
                                    </a>
                                <?php else: ?>
                                    <a href="inscription_sortie.php?sortie_id=<?= $s['id'] ?>" class="mail">
                                        Je m’inscris
                                    </a>
                                    <?= $priorityBadgeHtml ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>Aucune sortie enregistrée.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Email Inscrits -->
<div class="modal fade" id="emailModal" tabindex="-1" aria-labelledby="emailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="emailModalLabel">Envoyer un email aux inscrits</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p><strong>Sujet :</strong> <span id="emailSubject"></span></p>
        <div class="mb-3">
          <label for="emailMessage" class="form-label">Message</label>
          <textarea class="form-control" id="emailMessage" rows="8" placeholder="Saisir le message à envoyer aux membres inscrits..."></textarea>
        </div>
        <div id="emailAlert" style="display:none;" class="alert" role="alert"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-primary" id="sendEmailBtn">Envoyer</button>
      </div>
    </div>
  </div>
</div>

<script>
let currentSortieId = 0;
let currentSortieTitre = '';

function openEmailModal(sortieId, sortieTitre) {
    currentSortieId = sortieId;
    currentSortieTitre = sortieTitre;
    document.getElementById('emailSubject').textContent = 'CONCERNE - ' + sortieTitre + ' - IMPORTANT';
    document.getElementById('emailMessage').value = '';
    document.getElementById('emailAlert').style.display = 'none';
    var modal = new bootstrap.Modal(document.getElementById('emailModal'));
    modal.show();
}

document.getElementById('sendEmailBtn').addEventListener('click', function() {
    const message = document.getElementById('emailMessage').value.trim();
    const alertDiv = document.getElementById('emailAlert');
    const btn = this;
    
    if (message === '') {
        alertDiv.className = 'alert alert-danger';
        alertDiv.textContent = 'Le message ne peut pas être vide.';
        alertDiv.style.display = 'block';
        return;
    }
    
    btn.disabled = true;
    btn.textContent = 'Envoi en cours...';
    alertDiv.style.display = 'none';
    
    fetch('action_email_sortie.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'sortie_id=' + encodeURIComponent(currentSortieId) + '&message=' + encodeURIComponent(message)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alertDiv.className = 'alert alert-success';
            let msg = 'Email envoyé à ' + data.sent + ' membre(s).';
            if (data.failed > 0) {
                msg += ' Échec pour ' + data.failed + ' membre(s).';
            }
            // Construire la liste des destinataires si fournie
            if (Array.isArray(data.recipients) && data.recipients.length > 0) {
                msg += '\nDestinataires :';
                msg += '<ul style="margin-top:0.5rem;">';
                data.recipients.forEach(function(r){
                    const name = r.nom || '';
                    const email = r.email || '';
                    msg += '<li>' + name.replace(/</g,'&lt;').replace(/>/g,'&gt;') + ' &lt;' + email.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '&gt;</li>';
                });
                msg += '</ul>';
            }
            alertDiv.innerHTML = msg;
            alertDiv.style.display = 'block';
            document.getElementById('emailMessage').value = '';
        } else {
            alertDiv.className = 'alert alert-danger';
            alertDiv.textContent = 'Erreur : ' + (data.error || 'Erreur inconnue.');
            alertDiv.style.display = 'block';
        }
    })
    .catch(error => {
        alertDiv.className = 'alert alert-danger';
        alertDiv.textContent = 'Erreur réseau : ' + error.message;
        alertDiv.style.display = 'block';
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Envoyer';
    });
});
</script>

<?php require 'footer.php'; ?>
