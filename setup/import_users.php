<?php
require_once 'config.php';
require_once 'auth.php';
require_login();
require_admin();
$debug = isset($_GET['debug']);
if ($debug) {
  ini_set('display_errors', '1');
  ini_set('display_startup_errors', '1');
  error_reporting(E_ALL);
}
$flash = null;
$preview = [];
$headers = [];
$tmpFile = '';
$detectedDelimiter = ',';
$usersColumns = [];
$hasLogin = false;
$hasRole = false;
$hasActif = false;
$hasPasswordHash = false;

// Découverte des colonnes de la table users pour s'adapter au schéma
try {
  $colsStmt = $pdo->query('SHOW COLUMNS FROM users');
  $usersColumns = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
  $hasLogin = in_array('login', $usersColumns, true);
  $hasRole = in_array('role', $usersColumns, true);
  $hasActif = in_array('actif', $usersColumns, true);
  $hasPasswordHash = in_array('password_hash', $usersColumns, true);
} catch (Throwable $e) {
  // en cas d'échec, on garde valeurs par défaut (colonnes potentiellement absentes)
}

function detect_delimiter(string $path): string {
  if (!is_readable($path)) return ',';
  $line = '';
  $h = fopen($path, 'r');
  if ($h) { $line = fgets($h); fclose($h); }
  if ($line === false) return ',';
  // retirer BOM éventuel
  $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
  $cComma = substr_count($line, ',');
  $cSemi  = substr_count($line, ';');
  // Favoriser le séparateur avec plus d'occurrences
  if ($cSemi > $cComma) return ';';
  if ($cComma > 0) return ',';
  if ($cSemi > 0) return ';';
  return ',';
}

function read_csv_preview(string $path, int $max = 10, string $delimiter = ','): array {
  $out = [];
  if (!is_readable($path)) return $out;
  if (($h = fopen($path, 'r')) !== false) {
    $i = 0;
    while (($row = fgetcsv($h, 0, $delimiter)) !== false) {
      $out[] = $row;
      if (++$i >= $max) break;
    }
    fclose($h);
  }
  return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step']) && $_POST['step'] === 'upload') {
        if (!empty($_FILES['csv']['name']) && is_uploaded_file($_FILES['csv']['tmp_name'])) {
            $tmpDir = __DIR__ . '/uploads';
            if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);
            $tmpFile = $tmpDir . '/users_import_' . time() . '_' . mt_rand(1000,9999) . '.csv';
            if (@move_uploaded_file($_FILES['csv']['tmp_name'], $tmpFile)) {
              $detectedDelimiter = detect_delimiter($tmpFile);
              $preview = read_csv_preview($tmpFile, 10, $detectedDelimiter);
                if (!empty($preview)) {
                    $headers = $preview[0];
                }
            } else {
                $flash = ['type' => 'error', 'text' => "Impossible d'enregistrer le fichier."];
            }
        } else {
            $flash = ['type' => 'error', 'text' => 'Aucun fichier CSV sélectionné.'];
        }
    } elseif (isset($_POST['step']) && $_POST['step'] === 'import') {
        $tmpFile = $_POST['tmpFile'] ?? '';
        $map = [
            'nom' => $_POST['map_nom'] ?? '',
            'prenom' => $_POST['map_prenom'] ?? '',
            'email' => $_POST['map_email'] ?? '',
            'login' => $_POST['map_login'] ?? '',
            'password' => $_POST['map_password'] ?? ''
        ];
        $delimiter = $_POST['delimiter'] ?? ',';
        foreach ($map as $k => $v) { $map[$k] = is_numeric($v) ? (int)$v : -1; }
        if (!is_readable($tmpFile)) {
            $flash = ['type' => 'error', 'text' => 'Fichier temporaire introuvable.'];
        } else {
          $inserted = 0; $skipped = 0; $updated = 0;
          try {
            set_time_limit(60);
            $logFile = __DIR__ . '/uploads/import_users.log';
            $log = function($msg) use ($logFile) { @file_put_contents($logFile, '['.date('c')."] " . $msg . "\n", FILE_APPEND); };
            $h = fopen($tmpFile, 'r');
            if ($h === false) { throw new RuntimeException('Impossible d\'ouvrir le fichier import.'); }
            $rowIndex = 0;
            while (($row = fgetcsv($h, 0, $delimiter)) !== false) {
              if ($rowIndex++ === 0) continue; // skip header
              foreach ($row as $i=>$v) { $row[$i] = trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$v)); }
              $nom = ($map['nom'] >= 0 && isset($row[$map['nom']])) ? trim($row[$map['nom']]) : '';
              $prenom = ($map['prenom'] >= 0 && isset($row[$map['prenom']])) ? trim($row[$map['prenom']]) : '';
              $email = ($map['email'] >= 0 && isset($row[$map['email']])) ? trim($row[$map['email']]) : '';
                    $login = ($map['login'] >= 0 && isset($row[$map['login']])) ? trim($row[$map['login']]) : '';
              $password = ($map['password'] >= 0 && isset($row[$map['password']])) ? trim($row[$map['password']]) : '';
                    if ($email === '' && (!$hasLogin || $login === '')) { $skipped++; continue; }
              $password_hash = ($password !== '') ? password_hash($password, PASSWORD_DEFAULT) : password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                    // Recherche d'un utilisateur existant selon colonnes disponibles
                    if ($hasLogin && $login !== '') {
                      $stmt = $pdo->prepare('SELECT id FROM users WHERE (email = ? AND email <> "") OR (login = ? AND login <> "") LIMIT 1');
                      $stmt->execute([$email, $login]);
                    } else {
                      $stmt = $pdo->prepare('SELECT id FROM users WHERE (email = ? AND email <> "") LIMIT 1');
                      $stmt->execute([$email]);
                    }
              $existing = $stmt->fetch(PDO::FETCH_ASSOC);
              if ($existing) {
                        if ($hasLogin) {
                          $up = $pdo->prepare('UPDATE users SET nom=?, prenom=?, email=?, login=? WHERE id=?');
                          $up->execute([$nom, $prenom, $email, $login, (int)$existing['id']]);
                        } else {
                          $up = $pdo->prepare('UPDATE users SET nom=?, prenom=?, email=? WHERE id=?');
                          $up->execute([$nom, $prenom, $email, (int)$existing['id']]);
                        }
                $updated++;
              } else {
                        // Construction dynamique de l'INSERT selon colonnes présentes
                        $cols = ['nom','prenom','email'];
                        $vals = [$nom, $prenom, $email];
                        if ($hasLogin) { $cols[] = 'login'; $vals[] = $login; }
                        if ($hasPasswordHash) { $cols[] = 'password_hash'; $vals[] = $password_hash; }
                        if ($hasActif) { $cols[] = 'actif'; $vals[] = 1; }
                        $placeholders = implode(',', array_fill(0, count($cols), '?'));
                        $ins = $pdo->prepare('INSERT INTO users (' . implode(',', $cols) . ') VALUES (' . $placeholders . ')');
                        $ins->execute($vals);
                $inserted++;
              }
            }
            fclose($h);
            $flash = ['type' => 'success', 'text' => "Import terminé: $inserted ajouté(s), $updated mis à jour, $skipped ignoré(s)."];
            $log("OK: $inserted ajoutés, $updated MAJ, $skipped ignorés");
          } catch (Throwable $e) {
            $flash = ['type' => 'error', 'text' => 'Erreur import: '.$e->getMessage()];
          }
        }
    }
}
?>
<?php require 'header.php'; ?>

<style>
.import-page { max-width: 1000px; margin: 0 auto; padding: 2rem 1rem 3rem; }
.import-header { display:flex; justify-content:space-between; align-items:center; gap:1rem; margin-bottom:1.5rem; padding:1rem 1.25rem; border-radius:1rem; background:linear-gradient(135deg,#004b8d,#00a0c6); color:#fff; }
.import-header h1 { margin:0; font-size:1.4rem; text-transform:uppercase; letter-spacing:.03em; }
.flash { margin:1rem 0; padding:.6rem .8rem; border-radius:.75rem; font-size:.95rem; }
.flash.success { background:#e7f7ec; color:#0a8a0a; }
.flash.error { background:#fde8e8; color:#b02525; }
.card { background:#fff; border-radius:1rem; box-shadow:0 8px 24px rgba(0,0,0,0.08); border:1px solid rgba(0,0,0,0.03); padding:1rem 1.25rem; margin-bottom:1rem; }
.table-sm th, .table-sm td { padding:.4rem .5rem; }
</style>

<div class="import-page">
  <div class="import-header">
    <div>
      <h1>Import des membres</h1>
      <div>Chargez votre CSV puis alignez les colonnes avec la table `users`.</div>
    </div>
    <div>👥</div>
  </div>

  <?php if ($flash): ?>
    <div class="flash <?= $flash['type'] ?>"><?= htmlspecialchars($flash['text']) ?></div>
  <?php endif; ?>

  <?php if (empty($preview)): ?>
    <div class="card">
      <h2 style="margin:0 0 .5rem;">Étape 1 — Charger le fichier CSV</h2>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="step" value="upload">
        <div class="mb-2"><input type="file" name="csv" accept="text/csv,.csv" required></div>
        <button class="btn btn-primary">Prévisualiser</button>
      </form>
    </div>
  <?php else: ?>
    <div class="card">
      <h2 style="margin:0 0 .5rem;">Étape 2 — Associer les colonnes</h2>
      <?php $cols = $headers; if (empty($cols)) { $cols = $preview[0]; } ?>
      <form method="post">
        <input type="hidden" name="step" value="import">
        <input type="hidden" name="tmpFile" value="<?= htmlspecialchars($tmpFile) ?>">
          <input type="hidden" name="delimiter" value="<?= htmlspecialchars($detectedDelimiter) ?>">
        <div class="row g-3">
          <?php
            $options = function($cols){ $html=''; foreach ($cols as $i=>$c){ $label = trim((string)$c) !== '' ? $c : ('Colonne '.($i+1)); $html .= '<option value="'.$i.'">'.htmlspecialchars($label).'</option>'; } return $html; };
          ?>
          <div class="col-md-4">
            <label>Nom</label>
            <select name="map_nom" class="form-select">
              <option value="">— Aucune —</option>
              <?= $options($cols) ?>
            </select>
          </div>
          <div class="col-md-4">
            <label>Prénom</label>
            <select name="map_prenom" class="form-select">
              <option value="">— Aucune —</option>
              <?= $options($cols) ?>
            </select>
          </div>
          <div class="col-md-4">
            <label>Email</label>
            <select name="map_email" class="form-select">
              <option value="">— Aucune —</option>
              <?= $options($cols) ?>
            </select>
          </div>
          <div class="col-md-4">
            <label>Login</label>
            <select name="map_login" class="form-select">
              <option value="">— Aucune —</option>
              <?= $options($cols) ?>
            </select>
          </div>
          <div class="col-md-4">
            <label>Mot de passe</label>
            <select name="map_password" class="form-select">
              <option value="">— Aucune —</option>
              <?= $options($cols) ?>
            </select>
          </div>
        </div>
        <div class="mt-3" style="text-align:right;">
          <button class="btn btn-primary">Importer</button>
        </div>
      </form>
    </div>

    <div class="card">
      <h2 style="margin:0 0 .5rem;">Aperçu du fichier</h2>
      <div style="overflow:auto;">
        <table class="table table-sm table-striped">
          <?php foreach ($preview as $r): ?>
            <tr>
              <?php foreach ($r as $c): ?>
                <td><?= htmlspecialchars((string)$c) ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require 'footer.php'; ?>
