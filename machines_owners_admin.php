<?php
require_once 'config.php';
require_once 'auth.php';
require_login();
require_admin();
require_once 'utils/activity_log.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$flash = null;
$hasOwnersTable = false;
try {
  $chk = $pdo->query("SHOW TABLES LIKE 'machines_owners'");
  if ($chk && $chk->fetch()) { $hasOwnersTable = true; }
} catch (Throwable $e) { $hasOwnersTable = false; }

// Créer machine + lier propriétaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_and_link') {
        $nom = trim($_POST['nom'] ?? '');
        $immat = trim($_POST['immatriculation'] ?? '');
        $ownerUserId = (int)($_POST['owner_user_id'] ?? 0);
        if ($nom !== '' && $ownerUserId > 0) {
            try {
                // Créer machine si inexistante
                $stmt = $pdo->prepare('SELECT id FROM machines WHERE nom = ? AND immatriculation = ? LIMIT 1');
                $stmt->execute([$nom, $immat]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $machineId = (int)$row['id'];
                } else {
                    $pdo->prepare('INSERT INTO machines (nom, immatriculation) VALUES (?, ?)')->execute([$nom, $immat]);
                    $machineId = (int)$pdo->lastInsertId();
                }

                // Lier propriétaire
                if ($hasOwnersTable) {
                  try {
                    $stmt = $pdo->prepare('SELECT 1 FROM machines_owners WHERE machine_id = ? AND user_id = ? LIMIT 1');
                    $stmt->execute([$machineId, $ownerUserId]);
                    if (!$stmt->fetch()) {
                      $pdo->prepare('INSERT INTO machines_owners (machine_id, user_id) VALUES (?, ?)')->execute([$machineId, $ownerUserId]);
                    }
                  } catch (Throwable $e) {
                    // ignorer
                  }
                } else {
                  $flash = ['type' => 'warning', 'text' => "La table machines_owners est absente. Exécutez la migration 'migrate_machines_owners.php'."];
                }

                gn_log_current_user_operation($pdo, 'machines_owners_create_link', json_encode(['machine_id' => $machineId, 'user_id' => $ownerUserId]));
                $flash = ['type' => 'success', 'text' => 'Machine créée/liée au membre.'];
            } catch (Throwable $e) {
                $flash = ['type' => 'danger', 'text' => 'Erreur: ' . $e->getMessage()];
            }
        } else {
            $flash = ['type' => 'warning', 'text' => 'Nom de machine et propriétaire requis.'];
        }
    } elseif ($action === 'link_owner') {
      // Lier un propriétaire à une machine existante
      $machineId = (int)($_POST['machine_id'] ?? 0);
      $ownerUserId = (int)($_POST['owner_user_id'] ?? 0);
      if ($machineId > 0 && $ownerUserId > 0) {
        if ($hasOwnersTable) {
          try {
            $stmt = $pdo->prepare('SELECT 1 FROM machines_owners WHERE machine_id = ? AND user_id = ? LIMIT 1');
            $stmt->execute([$machineId, $ownerUserId]);
            if (!$stmt->fetch()) {
              $pdo->prepare('INSERT INTO machines_owners (machine_id, user_id) VALUES (?, ?)')->execute([$machineId, $ownerUserId]);
            }
            gn_log_current_user_operation($pdo, 'machines_owners_link_owner', json_encode(['machine_id' => $machineId, 'user_id' => $ownerUserId]));
            $flash = ['type' => 'success', 'text' => 'Propriétaire lié à la machine.'];
          } catch (Throwable $e) {
            $flash = ['type' => 'danger', 'text' => 'Erreur: ' . $e->getMessage()];
          }
        } else {
          $flash = ['type' => 'warning', 'text' => "La table machines_owners est absente. Exécutez la migration 'migrate_machines_owners.php'."];
        }
      } else {
        $flash = ['type' => 'warning', 'text' => 'Sélectionner une machine et un membre.'];
      }
    } elseif ($action === 'unlink') {
        $machineId = (int)($_POST['machine_id'] ?? 0);
        $ownerUserId = (int)($_POST['owner_user_id'] ?? 0);
        if ($machineId > 0 && $ownerUserId > 0) {
        if ($hasOwnersTable) {
          try {
            $pdo->prepare('DELETE FROM machines_owners WHERE machine_id = ? AND user_id = ?')->execute([$machineId, $ownerUserId]);
            gn_log_current_user_operation($pdo, 'machines_owners_unlink', json_encode(['machine_id' => $machineId, 'user_id' => $ownerUserId]));
            $flash = ['type' => 'success', 'text' => 'Lien supprimé.'];
          } catch (Throwable $e) {
            $flash = ['type' => 'danger', 'text' => 'Erreur: ' . $e->getMessage()];
          }
        } else {
          $flash = ['type' => 'warning', 'text' => "La table machines_owners est absente. Exécutez la migration 'migrate_machines_owners.php'."];
        }
        }
    }
}

// Données pour affichage
$machines = [];
try {
    $stmt = $pdo->query('SELECT id, nom, immatriculation FROM machines ORDER BY nom');
    $machines = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $machines = []; }

$users = [];
try {
    $stmt = $pdo->query('SELECT id, nom, prenom FROM users ORDER BY nom, prenom');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $users = []; }

$links = [];
try {
  if ($hasOwnersTable) {
    $stmt = $pdo->query('SELECT mo.machine_id, mo.user_id, m.nom AS machine_nom, m.immatriculation, u.nom, u.prenom
               FROM machines_owners mo
               JOIN machines m ON m.id = mo.machine_id
               JOIN users u ON u.id = mo.user_id
               ORDER BY m.nom, u.nom, u.prenom');
    $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $links = [];
  }
} catch (Throwable $e) { $links = []; }

require 'header.php';
?>
<div class="container">
  <h2>Gestion des machines propriétaires</h2>
  <?php if (!$hasOwnersTable): ?>
    <div class="alert alert-warning">La table <code>machines_owners</code> est absente. Veuillez exécuter la migration <code>migrate_machines_owners.php</code> pour activer la gestion des propriétaires.</div>
  <?php endif; ?>
  <?php if (is_admin()): ?>
    <details class="mb-3">
      <summary>Diagnostic session (admin)</summary>
      <div class="alert alert-secondary mt-2" style="font-size:.9rem;">
        <div><strong>is_logged_in:</strong> <?= is_logged_in() ? 'oui' : 'non' ?></div>
        <div><strong>is_admin:</strong> <?= is_admin() ? 'oui' : 'non' ?></div>
        <div><strong>user_id:</strong> <?= htmlspecialchars((string)($_SESSION['user_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
        <div><strong>email:</strong> <?= htmlspecialchars((string)($_SESSION['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
        <div><strong>cookie params:</strong>
          <?php
            $p = session_get_cookie_params();
            echo htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');
          ?>
        </div>
        <div><strong>PHPSESSID present:</strong> <?= isset($_COOKIE['PHPSESSID']) ? 'oui' : 'non' ?></div>
      </div>
    </details>
  <?php endif; ?>
  <?php if (!empty($flash)): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($flash['text'], ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <div class="card mb-3">
    <h3 class="card-title">Créer une machine et lier à un membre</h3>
    <form method="post" class="row g-2 align-items-end">
      <input type="hidden" name="action" value="create_and_link" />
      <div class="col-md-4">
        <label class="form-label" for="nom">Nom de la machine</label>
        <input type="text" name="nom" id="nom" class="form-control" required />
      </div>
      <div class="col-md-3">
        <label class="form-label" for="immatriculation">Immatriculation</label>
        <input type="text" name="immatriculation" id="immatriculation" class="form-control" />
      </div>
      <div class="col-md-5">
        <label class="form-label" for="owner_user_id">Propriétaire</label>
        <select name="owner_user_id" id="owner_user_id" class="form-select" required>
          <option value="">Sélectionner…</option>
          <?php foreach ($users as $u): $label = trim(($u['prenom']??'').' '.($u['nom']??'')); ?>
            <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12">
        <button type="submit" class="btn btn-success">Créer et lier</button>
      </div>
    </form>
  </div>

  <div class="card mb-3">
    <h3 class="card-title">Machines existantes</h3>
    <?php if (!$machines): ?>
      <p>Aucune machine enregistrée.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-striped">
          <thead>
            <tr>
              <th>#</th>
              <th>Nom</th>
              <th>Immatriculation</th>
              <th>Propriétaires (compte)</th>
              <th>Lier un propriétaire</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($machines as $m): ?>
              <?php
              $ownerCount = 0;
              $ownersList = [];
              try {
                  $stmtC = $pdo->prepare('SELECT mo.user_id, u.prenom, u.nom FROM machines_owners mo JOIN users u ON u.id = mo.user_id WHERE mo.machine_id = ? ORDER BY u.nom, u.prenom');
                  $stmtC->execute([ (int)$m['id'] ]);
                  $ownersList = $stmtC->fetchAll(PDO::FETCH_ASSOC);
                  $ownerCount = count($ownersList);
              } catch (Throwable $e) { $ownerCount = 0; }
              ?>
              <tr>
                <td><?= (int)$m['id'] ?></td>
                <td><?= htmlspecialchars($m['nom'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($m['immatriculation'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <?= (int)$ownerCount ?>
                  <?php if ($ownersList): ?>
                    <div style="font-size:.9em; color:#555;">
                      <?php foreach ($ownersList as $ow): $lbl = trim(($ow['prenom']??'').' '.($ow['nom']??'')); ?>
                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></span>
                        <form method="post" class="d-inline" onsubmit="return confirm('Supprimer ce lien propriétaire ?');">
                          <input type="hidden" name="action" value="unlink" />
                          <input type="hidden" name="machine_id" value="<?= (int)$m['id'] ?>" />
                          <input type="hidden" name="owner_user_id" value="<?= (int)$ow['user_id'] ?>" />
                          <button type="submit" class="btn btn-outline-danger btn-sm">Retirer</button>
                        </form>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <form method="post" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="action" value="link_owner" />
                    <input type="hidden" name="machine_id" value="<?= (int)$m['id'] ?>" />
                    <select name="owner_user_id" class="form-select form-select-sm" style="min-width:220px;">
                      <option value="">Sélectionner un membre…</option>
                      <?php foreach ($users as $u): $label = trim(($u['prenom']??'').' '.($u['nom']??'')); ?>
                        <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Lier</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3 class="card-title">Liens existants (machines ⇄ membres)</h3>
    <?php if (!$links): ?>
      <p>Aucun lien pour le moment.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-striped">
          <thead>
            <tr>
              <th>Machine</th>
              <th>Immatriculation</th>
              <th>Membre</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($links as $lk): ?>
              <tr>
                <td><?= htmlspecialchars($lk['machine_nom'] ?? '') ?></td>
                <td><?= htmlspecialchars($lk['immatriculation'] ?? '') ?></td>
                <td><?= htmlspecialchars(trim(($lk['prenom']??'').' '.($lk['nom']??''))) ?></td>
                <td>
                  <form method="post" onsubmit="return confirm('Supprimer ce lien propriétaire ?');" class="d-inline">
                    <input type="hidden" name="action" value="unlink" />
                    <input type="hidden" name="machine_id" value="<?= (int)$lk['machine_id'] ?>" />
                    <input type="hidden" name="owner_user_id" value="<?= (int)$lk['user_id'] ?>" />
                    <button type="submit" class="btn btn-outline-danger btn-sm">Supprimer le lien</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require 'footer.php'; ?>
