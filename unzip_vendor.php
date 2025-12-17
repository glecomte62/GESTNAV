<?php
/**
 * Décompression du fichier vendor.zip
 */

$zipFile = __DIR__ . '/vendor.zip';

if (!file_exists($zipFile)) {
    die("❌ vendor.zip introuvable");
}

echo "<h1>📦 Installation de la librairie PDF Parser</h1>";
echo "<p>✅ vendor.zip trouvé (" . round(filesize($zipFile)/1024, 2) . " KB)</p>";

// Supprimer l'ancien dossier vendor
if (is_dir(__DIR__ . '/vendor')) {
    echo "<p>🗑️ Suppression de l'ancien dossier vendor...</p>";
    function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        rrmdir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
    rrmdir(__DIR__ . '/vendor');
    echo "<p>✅ Ancien dossier supprimé</p>";
}

// Décompresser avec ZipArchive
if (!class_exists('ZipArchive')) {
    die("❌ Extension ZipArchive non disponible sur ce serveur");
}

$zip = new ZipArchive;
if ($zip->open($zipFile) === true) {
    echo "<p>📂 Décompression en cours...</p>";
    $zip->extractTo(__DIR__);
    $zip->close();
    echo "<p>✅ Décompression réussie !</p>";
    
    // Supprimer le zip
    unlink($zipFile);
    echo "<p>🗑️ Fichier zip supprimé</p>";
    
    // Vérifier l'installation
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        echo "<p>✅ vendor/autoload.php trouvé</p>";
        
        require_once __DIR__ . '/vendor/autoload.php';
        
        if (class_exists('Smalot\PdfParser\Parser')) {
            echo "<p style='color: green; font-size: 1.2em; font-weight: bold;'>✅ Smalot\\PdfParser\\Parser disponible !</p>";
            echo "<p>🎉 <strong>Installation réussie !</strong></p>";
            echo "<p>👉 Tu peux maintenant retourner sur <a href='test_extraction.php' style='color: blue; text-decoration: underline;'>test_extraction.php</a> pour tester l'extraction</p>";
        } else {
            echo "<p>❌ Classe Smalot\\PdfParser\\Parser introuvable</p>";
        }
    } else {
        echo "<p>❌ vendor/autoload.php introuvable après décompression</p>";
    }
} else {
    echo "<p>❌ Impossible d'ouvrir le fichier zip</p>";
}
