<?php
require_once 'config.php';
require_once 'auth.php';
require_login();
require_admin();

// Déterminer le type: sorties ou événements
$type = isset($_GET['type']) && $_GET['type'] === 'evenement' ? 'evenement' : 'sortie';

$flash = null;

// Handlers POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_sortie_inscription') {
        $sortie_id = (int)($_POST['sortie_id'] ?? 0);
        $user_id   = (int)($_POST['user_id'] ?? 0);
        if ($sortie_id > 0 && $user_id > 0) {
            try {
                // éviter doublon
                $check = $pdo->prepare("SELECT id FROM sortie_inscriptions WHERE sortie_id=? AND user_id=? LIMIT 1");
                $check->execute([$sortie_id, $user_id]);
                if ($check->fetch()) {
                    $flash = ['type' => 'warning', 'text' => "L'utilisateur est déjà inscrit à cette sortie."];
                } else {
                    $token = bin2hex(random_bytes(32));
                    $ins = $pdo->prepare("INSERT INTO sortie_inscriptions (sortie_id, user_id, action_token) VALUES (?,?,?)");
                    $ins->execute([$sortie_id, $user_id, $token]);
                    $flash = ['type' => 'success', 'text' => 'Inscription ajoutée.'];
                }
            } catch (Throwable $e) {
                $flash = ['type' => 'error', 'text' => 'Erreur ajout: ' . $e->getMessage()];
            }
        }
        $type = 'sortie';
        $_GET['sortie_id'] = (string)$sortie_id;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'remove_sortie_inscription') {
        $sortie_id = (int)($_POST['sortie_id'] ?? 0);
        $user_id   = (int)($_POST['user_id'] ?? 0);
        if ($sortie_id > 0 && $user_id > 0) {
            try {
                // Libérer affectations
                $q = $pdo->prepare("DELETE sa FROM sortie_assignations sa JOIN sortie_machines sm ON sm.id=sa.sortie_machine_id WHERE sm.sortie_id=? AND sa.user_id=?");
                $q->execute([$sortie_id, $user_id]);
                // Supprimer les pré-inscriptions (préférences)
                try {
                    $delPre = $pdo->prepare("DELETE FROM sortie_preinscriptions WHERE sortie_id=? AND user_id=?");
                    $delPre->execute([$sortie_id, $user_id]);
                } catch (Throwable $e) {
                    // Table peut ne pas exister
                }
                // Supprimer l'inscription
                $del = $pdo->prepare("DELETE FROM sortie_inscriptions WHERE sortie_id=? AND user_id=?");
                $del->execute([$sortie_id, $user_id]);
                // Ne pas déclencher de mails automatiques ici: pas de promotion ni notification.
                $flash = ['type' => 'success', 'text' => "Inscription supprimée."];
            } catch (Throwable $e) {
                $flash = ['type' => 'error', 'text' => 'Erreur suppression: ' . $e->getMessage()];
            }
        }
        $type = 'sortie';
        $_GET['sortie_id'] = (string)$sortie_id;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_event_inscriptions') {
        $evenement_id = (int)($_POST['evenement_id'] ?? 0);
        if ($evenement_id > 0) {
            try {
                foreach (($_POST['ins'] ?? []) as $id => $row) {
                    $id = (int)$id;
                    $statut = $row['statut'] ?? 'en_attente';
                    $nb_acc = max(0, (int)($row['nb_accompagnants'] ?? 0));
                    $upd = $pdo->prepare("UPDATE evenement_inscriptions SET statut=?, nb_accompagnants=? WHERE id=? AND evenement_id=?");
                    $upd->execute([$statut, $nb_acc, $id, $evenement_id]);
                }
                $flash = ['type' => 'success', 'text' => 'Inscriptions événement mises à jour.'];
            } catch (Throwable $e) {
                $flash = ['type' => 'error', 'text' => 'Erreur mise à jour: ' . $e->getMessage()];
            }
        }
        $type = 'evenement';
        $_GET['evenement_id'] = (string)$evenement_id;
    }
}

// Données communes
// Sorties récentes et à venir (pour choix)
$sorties = $pdo->query("SELECT id, titre, date_sortie FROM sorties ORDER BY date_sortie DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
// Événements récents et à venir
$events = $pdo->query("SELECT id, titre, date_evenement FROM evenements ORDER BY date_evenement DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);

// Détails selon type
$selected_sortie = isset($_GET['sortie_id']) ? (int)$_GET['sortie_id'] : 0;
$selected_event  = isset($_GET['evenement_id']) ? (int)$_GET['evenement_id'] : 0;

// Inscriptions de sortie
$inscrits_sortie = [];
if ($type === 'sortie' && $selected_sortie > 0) {
    $stmt = $pdo->prepare("SELECT si.*, u.prenom, u.nom, u.email FROM sortie_inscriptions si JOIN users u ON u.id=si.user_id WHERE si.sortie_id=? ORDER BY si.id ASC");
    $stmt->execute([$selected_sortie]);
    $inscrits_sortie = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Inscriptions événement
$inscrits_evt = [];
if ($type === 'evenement' && $selected_event > 0) {
    $stmt = $pdo->prepare("SELECT ei.*, u.prenom, u.nom, u.email FROM evenement_inscriptions ei JOIN users u ON u.id=ei.user_id WHERE ei.evenement_id=? ORDER BY ei.id ASC");
    $stmt->execute([$selected_event]);
    $inscrits_evt = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require 'header.php';
?>

<div class="container" style="max-width:1100px; padding:2rem 1rem 3rem;">
  <div class="d-flex align-items-center justify-content-between mb-3 p-3" style="border-radius:1rem;background:linear-gradient(135deg,#004b8d,#00a0c6);color:#fff;">
    <div>
      <h1 style="margin:0;font-size:1.4rem;">Gestion des inscrits</h1>
      <div style="opacity:.9;">Admin: sorties et événements</div>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type']==='error'?'danger':($flash['type']==='warning'?'warning':'success') ?>"><?= htmlspecialchars($flash['text']) ?></div>
  <?php endif; ?>

  <ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= $type==='sortie'?'active':'' ?>" href="inscriptions_admin.php?type=sortie">Sorties</a></li>
    <li class="nav-item"><a class="nav-link <?= $type==='evenement'?'active':'' ?>" href="inscriptions_admin.php?type=evenement">Événements</a></li>
  </ul>

  <?php if ($type === 'sortie'): ?>
    <div class="card p-3 mb-3">
      <form method="get" class="row g-2 align-items-end">
        <input type="hidden" name="type" value="sortie">
        <div class="col-md-8">
          <label class="form-label">Sortie</label>
          <select name="sortie_id" class="form-select" required>
            <option value="">— Choisir une sortie —</option>
            <?php foreach ($sorties as $s): ?>
              <option value="<?= (int)$s['id'] ?>" <?= $selected_sortie===(int)$s['id']?'selected':'' ?>>
                <?= htmlspecialchars(($s['date_sortie'] ?? '') . ' — ' . ($s['titre'] ?? 'Sortie')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <button class="btn btn-primary w-100">Charger</button>
        </div>
      </form>
    </div>

    <?php if ($selected_sortie > 0): ?>
      <div class="card p-3 mb-3">
        <h5 class="mb-2">Inscrits (<?= count($inscrits_sortie) ?>)</h5>
        <?php if (empty($inscrits_sortie)): ?>
          <p class="text-muted">Aucun inscrit pour cette sortie.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead><tr><th>Nom</th><th>Email</th><th>Inscrit le</th><th style="width:1%"></th></tr></thead>
              <tbody>
                <?php foreach ($inscrits_sortie as $i): ?>
                  <tr>
                    <td><?= htmlspecialchars(trim(($i['prenom']??'') . ' ' . ($i['nom']??''))) ?></td>
                    <td><?= htmlspecialchars($i['email'] ?? '') ?></td>
                    <td><?= htmlspecialchars($i['created_at'] ?? '') ?></td>
                    <td>
                      <form method="post" onsubmit="return confirm('Supprimer cette inscription ?');">
                        <input type="hidden" name="action" value="remove_sortie_inscription">
                        <input type="hidden" name="sortie_id" value="<?= $selected_sortie ?>">
                        <input type="hidden" name="user_id" value="<?= (int)$i['user_id'] ?>">
                        <button class="btn btn-sm btn-outline-danger">Supprimer</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <div class="card p-3">
        <h5 class="mb-2">Ajouter une inscription</h5>
        <?php 
          // Liste des utilisateurs non inscrits
          $users = $pdo->prepare("SELECT id, prenom, nom, email FROM users WHERE id NOT IN (SELECT user_id FROM sortie_inscriptions WHERE sortie_id=?) ORDER BY nom, prenom LIMIT 500");
          $users->execute([$selected_sortie]);
          $candidates = $users->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <?php if (empty($candidates)): ?>
          <p class="text-muted">Tous les membres sont déjà inscrits ou aucun membre disponible.</p>
        <?php else: ?>
          <form method="post" class="row g-2 align-items-end">
            <input type="hidden" name="action" value="add_sortie_inscription">
            <input type="hidden" name="sortie_id" value="<?= $selected_sortie ?>">
            <div class="col-md-8">
              <label class="form-label">Membre</label>
              <select name="user_id" class="form-select" required>
                <option value="">— Choisir un membre —</option>
                <?php foreach ($candidates as $u): ?>
                  <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars(trim(($u['nom']??'') . ' ' . ($u['prenom']??'')) . ' — ' . ($u['email']??'')) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <button class="btn btn-primary w-100">Ajouter</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="card p-3 mb-3">
      <form method="get" class="row g-2 align-items-end">
        <input type="hidden" name="type" value="evenement">
        <div class="col-md-8">
          <label class="form-label">Événement</label>
          <select name="evenement_id" class="form-select" required>
            <option value="">— Choisir un événement —</option>
            <?php foreach ($events as $e): ?>
              <option value="<?= (int)$e['id'] ?>" <?= $selected_event===(int)$e['id']?'selected':'' ?>>
                <?= htmlspecialchars(($e['date_evenement'] ?? '') . ' — ' . ($e['titre'] ?? 'Événement')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <button class="btn btn-primary w-100">Charger</button>
        </div>
      </form>
    </div>

    <?php if ($selected_event > 0): ?>
      <style>
        .evt-table thead th { font-weight:600; font-size:.9rem; color:#004b8d; border-bottom:2px solid #e6ebf2; }
        .evt-table tbody tr { transition: background .15s ease; }
        .evt-table tbody tr:hover { background: #f8fbff; }
        .evt-name { display:flex; align-items:center; gap:.6rem; }
        .evt-avatar { width:28px; height:28px; border-radius:50%; background:#eaf3ff; color:#0b5cab; display:inline-flex; align-items:center; justify-content:center; font-weight:700; }
        .badge-pill { display:inline-flex; align-items:center; padding:.2rem .55rem; border-radius:999px; font-size:.75rem; font-weight:600; border:1px solid #d0d7e2; }
        .badge-wait { background:#fff7e6; color:#9a6700; border-color:#f1c76e; }
        .badge-conf { background:#e7f7ec; color:#0a8a0a; border-color:#cfe7d4; }
        .badge-cancel { background:#fde8e8; color:#b00020; border-color:#f5b5b5; }
      </style>
      <div class="card p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="mb-0">Inscriptions (<?= count($inscrits_evt) ?>)</h5>
          <span class="badge-pill" style="background:#eaf3ff;color:#0b5cab;">Événement #<?= (int)$selected_event ?></span>
        </div>
        <?php if (empty($inscrits_evt)): ?>
          <p class="text-muted">Aucune inscription pour cet événement.</p>
        <?php else: ?>
          <form method="post">
            <input type="hidden" name="action" value="update_event_inscriptions">
            <input type="hidden" name="evenement_id" value="<?= $selected_event ?>">
            <div class="table-responsive">
              <table class="table table-sm align-middle evt-table">
                <thead><tr><th>Inscrit</th><th>Email</th><th>Statut</th><th>Accompagnants</th></tr></thead>
                <tbody>
                  <?php foreach ($inscrits_evt as $i): ?>
                    <tr>
                      <td>
                        <?php $initials = strtoupper(substr((string)($i['prenom']??''),0,1).substr((string)($i['nom']??''),0,1)); ?>
                        <div class="evt-name">
                          <span class="evt-avatar" title="Membre"><?= htmlspecialchars($initials) ?></span>
                          <span><?= htmlspecialchars(trim(($i['prenom']??'') . ' ' . ($i['nom']??''))) ?></span>
                        </div>
                      </td>
                      <td><?= htmlspecialchars($i['email'] ?? '') ?></td>
                      <td>
                        <?php $stat = $i['statut'] ?? 'en_attente'; ?>
                        <div class="d-flex align-items-center gap-2">
                          <select name="ins[<?= (int)$i['id'] ?>][statut]" class="form-select form-select-sm" style="max-width:160px;">
                            <option value="en_attente" <?= $stat==='en_attente'?'selected':'' ?>>En attente</option>
                            <option value="confirmée" <?= $stat==='confirmée'?'selected':'' ?>>Confirmée</option>
                            <option value="annulée" <?= $stat==='annulée'?'selected':'' ?>>Annulée</option>
                          </select>
                          <?php if ($stat==='confirmée'): ?>
                            <span class="badge-pill badge-conf">Confirmée</span>
                          <?php elseif ($stat==='annulée'): ?>
                            <span class="badge-pill badge-cancel">Annulée</span>
                          <?php else: ?>
                            <span class="badge-pill badge-wait">En attente</span>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td>
                        <input type="number" min="0" name="ins[<?= (int)$i['id'] ?>][nb_accompagnants]" value="<?= (int)($i['nb_accompagnants'] ?? 0) ?>" class="form-control form-control-sm" style="width:90px;">
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="text-end mt-2">
              <button class="btn btn-primary">Enregistrer</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php require 'footer.php'; ?>
