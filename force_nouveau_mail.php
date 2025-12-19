<?php
session_start();
// Vider complètement la session pour forcer la régénération
unset($_SESSION['email_draft']);
session_destroy();
echo "✅ Session vidée - Un nouveau mail sera généré\n";
echo "👉 Allez sur: https://gestnav.clubulmevasion.fr/envoyer_email.php\n";
echo "👉 Cliquez sur 'Envoyer les nouveautés'\n";
echo "👉 Le mail sera ultra-simplifié\n";
