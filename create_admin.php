<?php
require 'config.php';

$nom = 'Admin';
$prenom = 'Club';
$email = 'admin@clubulm.local';   // à modifier
$password = 'admin123';           // à changer ensuite

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO users (nom, prenom, email, password_hash, role) VALUES (?,?,?,?, 'admin')");
$stmt->execute([$nom, $prenom, $email, $hash]);

echo "Admin créé. Pense à supprimer ce fichier create_admin.php !";
