<?php
/**
 * Script pour décompresser vendor.tar.gz sur le serveur
 */

$archive = __DIR__ . '/vendor.tar.gz';

if (!file_exists($archive)) {
    die("❌ vendor.tar.gz introuvable");
}

echo "📦 Décompression de vendor.tar.gz...\n";

// Supprimer l'ancien dossier vendor s'il existe
if (is_dir(__DIR__ . '/vendor')) {
    echo "🗑️ Suppression de l'ancien dossier vendor...\n";
    exec('rm -rf ' . escapeshellarg(__DIR__ . '/vendor'));
}

// Décompresser
exec('tar -xzf ' . escapeshellarg($archive) . ' -C ' . escapeshellarg(__DIR__), $output, $return);

if ($return === 0) {
    echo "✅ Décompression réussie !\n";
    echo "✅ Dossier vendor créé\n";
    
    // Vérifier autoload.php
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        echo "✅ autoload.php trouvé\n";
        require_once __DIR__ . '/vendor/autoload.php';
        
        // Tester la classe
        if (class_exists('Smalot\PdfParser\Parser')) {
            echo "✅ Smalot\\PdfParser\\Parser disponible !\n";
        } else {
            echo "❌ Classe Smalot\\PdfParser\\Parser introuvable\n";
        }
    } else {
        echo "❌ autoload.php introuvable\n";
    }
    
    // Supprimer l'archive
    unlink($archive);
    echo "🗑️ Archive supprimée\n";
    
} else {
    echo "❌ Erreur lors de la décompression\n";
    echo "Sortie: " . implode("\n", $output) . "\n";
}
