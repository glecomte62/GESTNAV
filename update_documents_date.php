<?php
/**
 * Script de migration pour ajouter le champ document_date
 * À exécuter une seule fois pour mettre à jour la structure de la table documents
 */

require_once 'config.php';
require_once 'auth.php';

// Vérification admin
if (!is_admin()) {
    die("Accès refusé. Seul un administrateur peut exécuter ce script.");
}

echo "<!DOCTYPE html>\n";
echo "<html lang='fr'>\n<head>\n<meta charset='UTF-8'>\n";
echo "<title>Migration - Champ Date Document</title>\n";
echo "<style>body{font-family:Arial,sans-serif;margin:40px;line-height:1.6}";
echo ".success{color:green;font-weight:bold}.error{color:red;font-weight:bold}</style>\n";
echo "</head>\n<body>\n";
echo "<h1>🔧 Migration - Ajout du champ document_date</h1>\n";

try {
    // Vérifier si la colonne existe déjà
    $check = $pdo->query("SELECT document_date FROM documents LIMIT 1");
    echo "<p class='success'>✅ La colonne document_date existe déjà.</p>\n";
} catch (PDOException $e) {
    // La colonne n'existe pas, on l'ajoute
    echo "<p>⏳ Ajout de la colonne document_date...</p>\n";
    
    try {
        $pdo->exec("ALTER TABLE documents ADD COLUMN document_date DATE NULL AFTER uploaded_at");
        echo "<p class='success'>✅ Colonne document_date ajoutée avec succès !</p>\n";
    } catch (PDOException $e) {
        echo "<p class='error'>❌ Erreur lors de l'ajout de la colonne : " . htmlspecialchars($e->getMessage()) . "</p>\n";
        echo "<p>Veuillez vérifier que vous avez les droits nécessaires sur la base de données.</p>\n";
    }
}

// Créer un index sur document_date pour améliorer les performances des recherches par date
try {
    $pdo->exec("CREATE INDEX idx_document_date ON documents(document_date)");
    echo "<p class='success'>✅ Index créé sur document_date.</p>\n";
} catch (PDOException $e) {
    // L'index existe peut-être déjà
    if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
        echo "<p class='success'>✅ Index sur document_date déjà existant.</p>\n";
    } else {
        echo "<p class='error'>⚠️ Impossible de créer l'index : " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
}

echo "<h2>✅ Migration terminée !</h2>\n";
echo "<p>Le système peut maintenant stocker et rechercher des documents par date.</p>\n";
echo "<p><strong>Fonctionnalités ajoutées :</strong></p>\n";
echo "<ul>\n";
echo "<li>📅 Champ date du document (facture, contrat, etc.)</li>\n";
echo "<li>🤖 Détection automatique de la date lors de l'upload</li>\n";
echo "<li>🔍 Recherche et tri par date possible</li>\n";
echo "</ul>\n";
echo "<p><a href='documents_admin.php'>← Retour à la gestion des documents</a></p>\n";
echo "</body>\n</html>";
?>
