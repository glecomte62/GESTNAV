<?php
/**
 * Script temporaire pour vider le cache OPcache
 * À exécuter une fois puis supprimer
 */

// Vider OPcache si activé
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ OPcache vidé avec succès\n";
} else {
    echo "ℹ️ OPcache non activé\n";
}

// Vider le cache de réalpath
if (function_exists('clearstatcache')) {
    clearstatcache(true);
    echo "✅ Cache stat vidé\n";
}

echo "\n🔄 Recharge la page maintenant !\n";
echo "\n⚠️ Pense à supprimer ce fichier après utilisation pour des raisons de sécurité.\n";
