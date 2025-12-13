<?php
require_once 'config.php';
require_once 'auth.php';
require_login();
require_admin();
header('Content-Type: text/plain; charset=UTF-8');

// Usage:
// - /fix_sorties_status_en_etude.php?normalize=1  -> normalise toutes les variantes vers 'en étude' et remplace les vides par 'prévue'
// - /fix_sorties_status_en_etude.php?id=123&to=en%20%C3%A9tude -> force un statut pour une sortie donnée

try {
    if (isset($_GET['id']) && isset($_GET['to'])) {
        $id = (int)$_GET['id'];
        $to = trim((string)$_GET['to']);
        $stmt = $pdo->prepare("UPDATE sorties SET statut = ? WHERE id = ?");
        $stmt->execute([$to, $id]);
        echo "OK: sortie $id mise à jour -> statut='$to'\n";
        exit(0);
    }

    if (isset($_GET['normalize'])) {
        $updated = 0;
        // Variantes connues à normaliser vers 'en étude'
        $variants = [
            'en_etude','en etude','en étude','EN ETUDE','EN ÉTUDE','En etude','En étude'
        ];
        foreach ($variants as $v) {
            $stmt = $pdo->prepare("UPDATE sorties SET statut = 'en étude' WHERE LOWER(REPLACE(statut,'_',' ')) = LOWER(?)");
            $stmt->execute([$v]);
            $updated += $stmt->rowCount();
        }
        // Vides -> 'prévue'
        $stmt = $pdo->prepare("UPDATE sorties SET statut = 'prévue' WHERE statut IS NULL OR statut = ''");
        $stmt->execute();
        $updated += $stmt->rowCount();
        echo "OK: normalisation terminée. $updated enregistrements mis à jour.\n";
        exit(0);
    }

    echo "Aide:\n- /fix_sorties_status_en_etude.php?normalize=1\n- /fix_sorties_status_en_etude.php?id=123&to=en%20%C3%A9tude\n";
} catch (Throwable $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
    exit(1);
}
