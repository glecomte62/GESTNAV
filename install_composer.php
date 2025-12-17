<?php
/**
 * Installation automatique de Composer et smalot/pdfparser
 */

// Définir les variables d'environnement nécessaires
putenv('HOME=' . __DIR__);
putenv('COMPOSER_HOME=' . __DIR__ . '/.composer');

echo "<h1>📦 Installation de la librairie PDF Parser</h1>";

// Vérifier si composer.phar existe
if (!file_exists(__DIR__ . '/composer.phar')) {
    echo "<p>📥 Téléchargement de Composer...</p>";
    
    $composerSetup = file_get_contents('https://getcomposer.org/installer');
    if ($composerSetup === false) {
        die("❌ Impossible de télécharger Composer");
    }
    
    file_put_contents(__DIR__ . '/composer-setup.php', $composerSetup);
    
    // Exécuter l'installation de Composer en ligne de commande
    $php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
    $cmd = $php . ' ' . escapeshellarg(__DIR__ . '/composer-setup.php') . ' 2>&1';
    
    echo "<p>Commande: <code>$cmd</code></p>";
    echo "<pre>";
    system($cmd, $return);
    echo "</pre>";
    
    unlink(__DIR__ . '/composer-setup.php');
    
    if (!file_exists(__DIR__ . '/composer.phar')) {
        die("❌ Échec de l'installation de Composer");
    }
    echo "<p>✅ Composer installé !</p>";
} else {
    echo "<p>✅ Composer déjà installé</p>";
}

// Installer smalot/pdfparser
echo "<p>📦 Installation de smalot/pdfparser...</p>";

if (!file_exists(__DIR__ . '/composer.json')) {
    echo "<p>❌ composer.json introuvable. Upload-le depuis ton PC.</p>";
    echo "<p>Contenu à créer dans composer.json :</p>";
    echo "<pre>{
    \"require\": {
        \"smalot/pdfparser\": \"^2.12\"
    }
}</pre>";
    die();
}

// Exécuter composer install
$php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
$cmd = $php . ' ' . escapeshellarg(__DIR__ . '/composer.phar') . ' install --no-dev --optimize-autoloader 2>&1';

echo "<p>Commande: <code>$cmd</code></p>";
echo "<pre>";
system($cmd, $return);
echo "</pre>";

if ($return === 0) {
    echo "<p>✅ Installation réussie !</p>";
    
    // Vérifier
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        echo "<p>✅ vendor/autoload.php trouvé</p>";
        
        require_once __DIR__ . '/vendor/autoload.php';
        
        if (class_exists('Smalot\PdfParser\Parser')) {
            echo "<p>✅ <strong>Smalot\\PdfParser\\Parser disponible !</strong></p>";
            echo "<p>🎉 Tu peux maintenant retourner sur <a href='test_extraction.php'>test_extraction.php</a></p>";
        } else {
            echo "<p>❌ Classe introuvable</p>";
        }
    } else {
        echo "<p>❌ vendor/autoload.php introuvable</p>";
    }
} else {
    echo "<p>❌ Erreur lors de l'installation (code: $return)</p>";
}
