<?php
// Vider le cache OpCache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ Cache OpCache vidé avec succès !<br>";
} else {
    echo "ℹ️ OpCache n'est pas activé ou opcache_reset() n'est pas disponible.<br>";
}

// Vider aussi le cache APC si présent
if (function_exists('apc_clear_cache')) {
    apc_clear_cache();
    echo "✅ Cache APC vidé avec succès !<br>";
}

echo "<br><a href='sortie_detail.php?sortie_id=25'>→ Retour à la sortie</a>";
?>
