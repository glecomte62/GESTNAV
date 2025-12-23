<?php
require_once 'config.php';
require_once 'auth.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>GESTNAV – Club ULM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Icônes -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- Styles GestNav (cache-busting) -->
    <link rel="stylesheet" href="/assets/css/gestnav.css?v=desk-20251203-v2">
</head>
<body>

<nav class="navbar navbar-expand-lg gn-navbar">
    <div class="container-fluid px-3 px-lg-4">
        <a class="navbar-brand text-white d-flex align-items-center gap-2" href="index.php">
            <img src="/assets/img/logo.jpg" alt="Logo club ULM" height="42"
                 style="border-radius: 6px; box-shadow: 0 3px 8px rgba(0,0,0,0.3);">

            <div class="d-flex flex-column lh-1">
                <span class="fw-bold text-uppercase" style="font-size: 1.05rem;">
                    GESTNAV ULM
                </span>
                <span style="font-size: .8rem; opacity: .85;">LFQJ – Espace membres</span>
            </div>
        </a>

        <button class="navbar-toggler text-white border-0" type="button"
                data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Basculer la navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <?php if (is_logged_in()): ?>
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 me-3">
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>"
                           href="index.php">
                            <i class="bi bi-speedometer2 me-1"></i> Tableau de bord
                        </a>
                    </li>
                    <?php if (is_admin()): ?>
                        <?php $cur = basename($_SERVER['PHP_SELF']); $adminActive = in_array($cur, ['machines.php','membres.php','evenements_admin.php','logs_connexions.php','logs_operations.php','logs_affectations.php','machines_owners_admin.php','inscriptions_admin.php','aerodromes_admin.php','envoyer_email.php','historique_emails.php','sondages_admin.php','sondages_detail.php','config_mail.php','config_generale.php','preinscriptions_admin.php']) ? 'active' : ''; ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?= $adminActive ?>" href="#" id="adminMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-gear-fill me-1"></i> Administration
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminMenu">
                                <li>
                                    <a class="dropdown-item <?= $cur === 'machines.php' ? 'active' : '' ?>" href="machines.php">
                                        <i class="bi bi-airplane-fill me-1"></i> Machines
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?= $cur === 'aerodromes_admin.php' ? 'active' : '' ?>" href="aerodromes_admin.php">
                                        <i class="bi bi-geo-alt-fill me-1"></i> Aérodromes
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?= $cur === 'membres.php' ? 'active' : '' ?>" href="membres.php">
                                        <i class="bi bi-people-fill me-1"></i> Membres
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?= $cur === 'preinscriptions_admin.php' ? 'active' : '' ?>" href="preinscriptions_admin.php">
                                        <i class="bi bi-person-plus-fill me-1"></i> Pré-inscriptions
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item <?= $cur === 'evenements_admin.php' ? 'active' : '' ?>" href="evenements_admin.php">
                                        <i class="bi bi-tools me-1"></i> Gestion événements
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?= $cur === 'sortie_proposals_admin.php' ? 'active' : '' ?>" href="sortie_proposals_admin.php">
                                        <i class="bi bi-lightbulb-fill me-1"></i> Propositions sorties
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?= $cur === 'machines_owners_admin.php' ? 'active' : '' ?>" href="machines_owners_admin.php">
                                        <i class="bi bi-person-badge me-1"></i> Machines propriétaires
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?= $cur === 'inscriptions_admin.php' ? 'active' : '' ?>" href="inscriptions_admin.php">
                                        <i class="bi bi-people me-1"></i> Gestion des inscrits
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item <?= $cur === 'envoyer_email.php' ? 'active' : '' ?>" href="envoyer_email.php">
                                        <i class="bi bi-envelope-fill me-1"></i> Envoi d'emails
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?= $cur === 'historique_emails.php' ? 'active' : '' ?>" href="historique_emails.php">
                                        <i class="bi bi-mailbox me-1"></i> Historique emails
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?= $cur === 'sondages_admin.php' || $cur === 'sondages_detail.php' ? 'active' : '' ?>" href="sondages_admin.php">
                                        <i class="bi bi-graph-up me-1"></i> Gestion sondages
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item <?= $cur === 'logs_connexions.php' ? 'active' : '' ?>" href="logs_connexions.php">
                                        <i class="bi bi-shield-lock me-1"></i> Logs connexions
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?= $cur === 'logs_operations.php' ? 'active' : '' ?>" href="logs_operations.php">
                                        <i class="bi bi-clipboard-check me-1"></i> Logs opérations
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?= $cur === 'logs_affectations.php' ? 'active' : '' ?>" href="logs_affectations.php">
                                        <i class="bi bi-list-check me-1"></i> Logs affectations
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item <?= $cur === 'config_generale.php' ? 'active' : '' ?>" href="config_generale.php">
                                        <i class="bi bi-sliders me-1"></i> Configuration générale
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?= $cur === 'config_mail.php' ? 'active' : '' ?>" href="config_mail.php">
                                        <i class="bi bi-envelope-at me-1"></i> Configuration email/SMTP
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'sorties.php' ? 'active' : '' ?>"
                           href="sorties.php">
                            <i class="bi bi-calendar-event me-1"></i> Sorties
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'mes_sorties.php' ? 'active' : '' ?>"
                           href="mes_sorties.php">
                            <i class="bi bi-calendar-check me-1"></i> Mes sorties
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'sortie_proposals_list.php' ? 'active' : '' ?>"
                           href="sortie_proposals_list.php">
                            <i class="bi bi-lightbulb me-1"></i> Propositions de sorties
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'evenements_list.php' ? 'active' : '' ?>"
                           href="evenements_list.php">
                            <i class="bi bi-calendar2-event me-1"></i> Événements
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'sondages.php' ? 'active' : '' ?>"
                           href="sondages.php">
                            <i class="bi bi-graph-up me-1"></i> Sondages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'annuaire.php' ? 'active' : '' ?>"
                           href="annuaire.php">
                            <i class="bi bi-people-fill me-1"></i> Annuaire
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'stats.php' ? 'active' : '' ?>"
                           href="stats.php">
                            <i class="bi bi-graph-up-arrow me-1"></i> Statistiques
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'about.php' ? 'active' : '' ?>"
                           href="about.php">
                            <i class="bi bi-info-circle me-1"></i> À propos
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-2">
                    <a href="account.php" class="d-flex align-items-center gap-2 text-white text-decoration-none gn-user-link">
                        <?php 
                            $userId = $_SESSION['user_id'] ?? null;
                            $userPhoto = '/assets/img/avatar-placeholder.svg';
                            $offsetX = 0;
                            $offsetY = 0;
                            
                            if ($userId) {
                                try {
                                    $stmt = $pdo->prepare('SELECT photo_path, photo_metadata FROM users WHERE id = ?');
                                    $stmt->execute([$userId]);
                                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                                    
                                    if ($user && !empty($user['photo_path'])) {
                                        $userPhoto = $user['photo_path'];
                                        if (!empty($user['photo_metadata'])) {
                                            $meta = json_decode($user['photo_metadata'], true);
                                            $offsetX = $meta['offsetX'] ?? 0;
                                            $offsetY = $meta['offsetY'] ?? 0;
                                        }
                                    }
                                } catch (Throwable $e) {}
                            }
                        ?>
                        <div style="width:40px; height:40px; border-radius:50%; overflow:hidden; border:2px solid rgba(255,255,255,0.3); flex-shrink:0; background:#f0f0f0; display:flex; align-items:center; justify-content:center; position:relative;">
                            <img src="<?= htmlspecialchars($userPhoto) ?>" alt="Photo profil" style="width:100%; height:100%; object-fit:cover; position:absolute; top:0; left:0; transform:translate(<?= $offsetX ?>px, <?= $offsetY ?>px);">
                        </div>
                        <div style="display:flex; flex-direction:column; gap:0; line-height:1.2;">
                            <span style="font-size:.9rem; font-weight:600;"><?= htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']) ?></span>
                            <?php if (is_admin()): ?>
                                <span class="badge bg-warning text-dark" style="font-size:.7rem; width:fit-content;">ADMIN</span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <a href="logout.php" class="text-white-50 text-decoration-none" title="Déconnexion">
                        <i class="bi bi-box-arrow-right" style="font-size:1.2rem;"></i>
                    </a>
                </div>
            <?php else: ?>
                <div class="ms-auto">
                    <a href="login.php" class="gn-btn gn-btn-primary">
                        <i class="bi bi-box-arrow-in-right"></i> Connexion
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="gn-wrapper">
