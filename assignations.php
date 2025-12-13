<?php
require 'header.php';
require_login();

$sortie_id = (int)($_GET['sortie_id'] ?? 0);
if (!$sortie_id) {
    echo "<p>Sortie invalide.</p>";
    require 'footer.php';
    exit;
}

// Récup sortie (avec titre)
$stmt = $pdo->prepare("
    SELECT s.*, u.nom AS admin_nom, u.prenom AS admin_prenom 
    FROM sorties s 
    JOIN users u ON u.id = s.created_by
    WHERE s.id = ?
");
$stmt->execute([$sortie_id]);
$sortie = $stmt->fetch();
if (!$sortie) {
    echo "<p>Sortie introuvable.</p>";
    require 'footer.php';
    exit;
}

// Récup machines de la sortie
$stmt = $pdo->prepare("
    SELECT sm.id AS sortie_machine_id, m.*
    FROM sortie_machines sm
    JOIN machines m ON m.id = sm.machine_id
    WHERE sm.sortie_id = ?
");
$stmt->execute([$sortie_id]);
$machines = $stmt->fetchAll();

// Membres actifs
$membres = $pdo->query("SELECT id, nom, prenom FROM users WHERE actif = 1 ORDER BY nom, prenom")->fetchAll();

$error = '';

// Traitement affectation (admin)
if (is_admin() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $sortie_machine_id = (int)$_POST['sortie_machine_id'];
    $user_id = (int)$_POST['user_id'];
    $role_onboard = $_POST['role_onboard'] ?? 'pilote';

    // Vérifier le nombre d'affectations existantes pour cette machine/sortie
    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM sortie_assignations WHERE sortie_machine_id = ?");
    $stmt->execute([$sortie_machine_id]);
    $count = (int)$stmt->fetch()['c'];

    if ($count >= 2) {
        $error = "Maximum 2 utilisateurs déjà affectés sur cette machine pour cette sortie.";
    } else {
        // Vérifier si déjà affecté
        $stmt = $pdo->prepare("SELECT id FROM sortie_assignations WHERE sortie_machine_id=? AND user_id=?");
        $stmt->execute([$sortie_machine_id, $user_id]);
        if ($stmt->fetch()) {
            $error = "Cet utilisateur est déjà affecté sur cette machine.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO sortie_assignations (sortie_machine_id, user_id, role_onboard) VALUES (?,?,?)");
            $stmt->execute([$sortie_machine_id, $user_id, $role_onboard]);
        }
    }
}

// Suppression affectation
if (is_admin() && isset($_GET['delete_assign'])) {
    $stmt = $pdo->prepare("DELETE FROM sortie_assignations WHERE id=?");
    $stmt->execute([$_GET['delete_assign']]);
    header("Location: assignations.php?sortie_id=$sortie_id");
    exit;
}

// Récup affectations par machine
$stmt = $pdo->prepare("
    SELECT sa.*, u.nom, u.prenom, sm.machine_id, m.nom AS machine_nom, m.immatriculation
    FROM sortie_assignations sa
    JOIN users u ON u.id = sa.user_id
    JOIN sortie_machines sm ON sm.id = sa.sortie_machine_id
    JOIN machines m ON m.id = sm.machine_id
    WHERE sm.sortie_id = ?
");
$stmt->execute([$sortie_id]);
$assignations = $stmt->fetchAll();

// Regrouper affectations par sortie_machine_id
$assign_par_machine = [];
foreach ($assignations as $a) {
    $assign_par_machine[$a['sortie_machine_id']][] = $a;
}
?>

<div class="gn-page-header">
    <div>
        <h1 class="gn-page-title">
            <i class="bi bi-person-lines-fill"></i>
            Affectations – <?= htmlspecialchars($sortie['titre']) ?>
        </h1>
        <p class="gn-page-subtitle">
            Association des pilotes et passagers aux machines pour cette sortie.
        </p>
    </div>
    <div>
        <a href="sorties.php" class="gn-btn gn-btn-outline">
            <i class="bi bi-arrow-left"></i> Retour aux sorties
        </a>
    </div>
</div>

<div class="gn-card mb-3">
    <div class="gn-card-header">
        <div class="gn-card-title">
            <i class="bi bi-info-circle"></i> Informations sur la sortie
        </div>
    </div>
    <div class="row g-3">
        <div class="col-md-4">
            <strong>Nom</strong><br>
            <?= htmlspecialchars($sortie['titre']) ?>
        </div>
        <div class="col-md-4">
            <strong>Date & heure</strong><br>
            <?= htmlspecialchars($sortie['date_sortie']) ?>
        </div>
        <div class="col-md-4">
            <strong>Statut</strong><br>
            <?= htmlspecialchars($sortie['statut']) ?>
        </div>
    </div>
    <div class="row g-3 mt-2">
        <div class="col-md-8">
            <strong>Description</strong><br>
            <?= $sortie['description'] ? htmlspecialchars($sortie['description']) : '<span class="gn-card-subtitle">Aucune description</span>' ?>
        </div>
        <div class="col-md-4">
            <strong>Créée par</strong><br>
            <?= htmlspecialchars($sortie['admin_prenom'] . ' ' . $sortie['admin_nom']) ?>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-warning py-2">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if (!$machines): ?>
    <div class="gn-card">
        <p class="gn-card-subtitle mb-0">
            Aucune machine n’a été associée à cette sortie. Retournez dans le module Sorties pour en ajouter.
        </p>
    </div>
<?php else: ?>

    <?php foreach ($machines as $m): ?>
        <?php $liste = $assign_par_machine[$m['sortie_machine_id']] ?? []; ?>
        <div class="gn-card mb-3">
            <div class="gn-card-header">
                <div>
                    <div class="gn-card-title">
                        <i class="bi bi-airplane-engines"></i>
                        <?= htmlspecialchars($m['nom']) ?> (<?= htmlspecialchars($m['immatriculation']) ?>)
                    </div>
                    <div class="gn-card-subtitle">
                        Maximum 2 personnes par machine pour cette sortie.
                    </div>
                </div>
                <div class="gn-card-subtitle">
                    Occupation : <?= count($liste) ?>/2
                </div>
            </div>

            <h6 class="mb-2">Affectations actuelles</h6>
            <?php if (!$liste): ?>
                <p class="gn-card-subtitle">Aucune affectation pour le moment.</p>
            <?php else: ?>
                <table class="gn-table mb-3">
                    <thead>
                    <tr>
                        <th>Membre</th>
                        <th>Rôle</th>
                        <?php if (is_admin()): ?>
                            <th style="width:120px;">Actions</th>
                        <?php endif; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($liste as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['prenom'] . ' ' . $a['nom']) ?></td>
                            <td><?= htmlspecialchars($a['role_onboard']) ?></td>
                            <?php if (is_admin()): ?>
                                <td>
                                    <a href="assignations.php?sortie_id=<?= $sortie_id ?>&delete_assign=<?= $a['id'] ?>"
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Retirer cette affectation ?');">
                                        <i class="bi bi-x-circle"></i> Retirer
                                    </a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if (is_admin()): ?>
                <?php if (count($liste) >= 2): ?>
                    <p class="gn-card-subtitle mb-0">
                        <i class="bi bi-exclamation-triangle"></i> Limite de 2 affectations atteinte pour cette machine.
                    </p>
                <?php else: ?>
                    <h6 class="mb-2">Ajouter une affectation</h6>
                    <form method="post">
                        <input type="hidden" name="sortie_machine_id" value="<?= $m['sortie_machine_id'] ?>">

                        <div class="gn-form-grid">
                            <div class="gn-form-group">
                                <label>Membre</label>
                                <select name="user_id" required>
                                    <?php foreach ($membres as $u): ?>
                                        <option value="<?= $u['id'] ?>">
                                            <?= htmlspecialchars($u['nom'] . ' ' . $u['prenom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="gn-form-group">
                                <label>Rôle</label>
                                <select name="role_onboard">
                                    <option value="pilote">Pilote</option>
                                    <option value="passager">Passager</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="gn-btn gn-btn-primary">
                            <i class="bi bi-person-plus"></i> Affecter
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

<?php endif; ?>

<?php require 'footer.php'; ?>
