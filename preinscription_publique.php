<?php
// Page publique de pré-inscription au club (sans authentification requise)
require_once 'config.php';

$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des champs
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $adresse_ligne1 = trim($_POST['adresse_ligne1'] ?? '');
    $adresse_ligne2 = trim($_POST['adresse_ligne2'] ?? '');
    $code_postal = trim($_POST['code_postal'] ?? '');
    $ville = trim($_POST['ville'] ?? '');
    $pays = trim($_POST['pays'] ?? 'France');
    $telephone = trim($_POST['telephone'] ?? '');
    $gsm = trim($_POST['gsm'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $date_naissance = trim($_POST['date_naissance'] ?? '');
    $profession = trim($_POST['profession'] ?? '');
    $contact_urgence_nom = trim($_POST['contact_urgence_nom'] ?? '');
    $contact_urgence_tel = trim($_POST['contact_urgence_tel'] ?? '');
    $contact_urgence_email = trim($_POST['contact_urgence_email'] ?? '');
    $presentation = trim($_POST['presentation'] ?? '');
    $est_pilote = isset($_POST['est_pilote']) && $_POST['est_pilote'] === 'oui' ? 1 : 0;
    $numero_licence = trim($_POST['numero_licence'] ?? '');
    
    // Validation
    if (!$nom) $errors[] = "Le nom est obligatoire";
    if (!$prenom) $errors[] = "Le prénom est obligatoire";
    if (!$adresse_ligne1) $errors[] = "L'adresse est obligatoire";
    if (!$code_postal) $errors[] = "Le code postal est obligatoire";
    if (!$ville) $errors[] = "La ville est obligatoire";
    if (!$gsm) $errors[] = "Le GSM est obligatoire";
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "L'email est invalide";
    if (!$date_naissance) $errors[] = "La date de naissance est obligatoire";
    if (!$profession) $errors[] = "La profession est obligatoire";
    if (!$contact_urgence_nom) $errors[] = "Le contact d'urgence est obligatoire";
    if (!$contact_urgence_tel) $errors[] = "Le téléphone du contact d'urgence est obligatoire";
    if (!$contact_urgence_email || !filter_var($contact_urgence_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'email du contact d'urgence est invalide";
    }
    if (!$presentation) $errors[] = "La présentation est obligatoire";
    if ($est_pilote && !$numero_licence) $errors[] = "Le numéro de licence est obligatoire pour les pilotes";
    
    // Vérifier la photo
    $photo_filename = null;
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = "La photo est obligatoire";
    } elseif ($_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['photo'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = "Format de photo invalide (JPG, PNG uniquement)";
        } elseif ($file['size'] > $max_size) {
            $errors[] = "La photo est trop volumineuse (max 5MB)";
        } else {
            $upload_dir = __DIR__ . '/uploads/preinscriptions/';
            @mkdir($upload_dir, 0755, true);
            
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $photo_filename = uniqid('preinsc_') . '.' . $ext;
            $target_path = $upload_dir . $photo_filename;
            
            if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                $errors[] = "Erreur lors de l'upload de la photo";
                $photo_filename = null;
            }
        }
    }
    
    // Vérifier que l'email n'existe pas déjà
    if (!$errors) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Un compte existe déjà avec cet email";
        }
        
        $stmt = $pdo->prepare("SELECT id FROM preinscriptions WHERE email = ? AND statut = 'en_attente'");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Une demande de pré-inscription est déjà en cours avec cet email";
        }
    }
    
    // Insertion
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO preinscriptions (
                    nom, prenom, adresse_ligne1, adresse_ligne2, code_postal, ville, pays,
                    telephone, gsm, email, date_naissance, profession,
                    contact_urgence_nom, contact_urgence_tel, contact_urgence_email,
                    photo_filename, presentation, est_pilote, numero_licence, statut
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente')
            ");
            
            $stmt->execute([
                $nom, $prenom, $adresse_ligne1, $adresse_ligne2, $code_postal, $ville, $pays,
                $telephone, $gsm, $email, $date_naissance, $profession,
                $contact_urgence_nom, $contact_urgence_tel, $contact_urgence_email,
                $photo_filename, $presentation, $est_pilote, $numero_licence
            ]);
            
            $success = true;
            
            // Charger le helper d'envoi d'emails
            require_once __DIR__ . '/mail_helper.php';
            
            // Envoyer un email de confirmation au candidat
            $subject = "Pré-inscription au Club ULM Evasion - Confirmation de réception";
            $message = "
                <html>
                <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <div style='background: linear-gradient(135deg, #004b8d, #00a0c6); color: white; padding: 2rem; text-align: center; border-radius: 10px 10px 0 0;'>
                        <h1 style='margin: 0;'>✅ Demande reçue !</h1>
                    </div>
                    <div style='padding: 2rem; background: white; border: 1px solid #e0e0e0; border-top: none;'>
                        <p>Bonjour <strong>$prenom $nom</strong>,</p>
                        <p>Nous avons bien reçu votre demande de pré-inscription au <strong>Club ULM Evasion</strong>.</p>
                        <p>Votre dossier est actuellement en cours d'étude par notre comité. Nous reviendrons vers vous très prochainement.</p>
                        <p>Merci de votre intérêt pour notre club !</p>
                    </div>
                    <div style='padding: 1rem; text-align: center; background: #f9f9f9; border-radius: 0 0 10px 10px;'>
                        <p style='margin: 0; color: #666; font-size: 0.85rem;'>
                            <strong>Club ULM Evasion</strong><br>
                            GESTNAV v" . GESTNAV_VERSION . "
                        </p>
                    </div>
                </body>
                </html>
            ";
            gestnav_send_mail($pdo, $email, $subject, $message);
            
            // Notifier info@clubulmevasion.fr
            $notifSubject = "🆕 Nouvelle pré-inscription - $prenom $nom";
            $notifMessage = "
                <html>
                <body style='font-family: Arial, sans-serif; max-width: 700px; margin: 0 auto;'>
                    <div style='background: linear-gradient(135deg, #004b8d, #00a0c6); color: white; padding: 2rem; border-radius: 10px 10px 0 0;'>
                        <h1 style='margin: 0; font-size: 1.5rem;'>🆕 Nouvelle demande de pré-inscription</h1>
                    </div>
                    <div style='padding: 2rem; background: white; border: 1px solid #e0e0e0;'>
                        <p style='font-size: 1.1rem; color: #004b8d;'>Une nouvelle personne souhaite rejoindre le club :</p>
                        
                        <div style='background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin: 1.5rem 0;'>
                            <h3 style='margin-top: 0; color: #004b8d; border-bottom: 2px solid #00a0c6; padding-bottom: 0.5rem;'>👤 Informations personnelles</h3>
                            <table style='width: 100%; border-collapse: collapse;'>
                                <tr><td style='padding: 0.5rem 0; font-weight: 600; width: 180px;'>Nom :</td><td>$nom</td></tr>
                                <tr><td style='padding: 0.5rem 0; font-weight: 600;'>Prénom :</td><td>$prenom</td></tr>
                                <tr><td style='padding: 0.5rem 0; font-weight: 600;'>Email :</td><td><a href='mailto:$email'>$email</a></td></tr>
                                <tr><td style='padding: 0.5rem 0; font-weight: 600;'>Téléphone :</td><td>$telephone</td></tr>
                                <tr><td style='padding: 0.5rem 0; font-weight: 600;'>GSM :</td><td><strong>$gsm</strong></td></tr>
                                <tr><td style='padding: 0.5rem 0; font-weight: 600;'>Date de naissance :</td><td>$date_naissance</td></tr>
                                <tr><td style='padding: 0.5rem 0; font-weight: 600;'>Profession :</td><td>$profession</td></tr>
                                <tr><td style='padding: 0.5rem 0; font-weight: 600;'>Ville :</td><td>$code_postal $ville ($pays)</td></tr>
                            </table>
                        </div>
                        
                        <div style='background: #fff3cd; padding: 1.5rem; border-radius: 8px; margin: 1.5rem 0; border-left: 4px solid #ffc107;'>
                            <h3 style='margin-top: 0; color: #856404;'>✈️ Expérience</h3>
                            <p style='margin: 0; font-size: 1.05rem;'><strong>Pilote :</strong> " . ($est_pilote ? "✅ Oui (licence <strong>$numero_licence</strong>)" : "❌ Non") . "</p>
                        </div>
                        
                        <div style='background: #e7f3ff; padding: 1.5rem; border-radius: 8px; margin: 1.5rem 0;'>
                            <h3 style='margin-top: 0; color: #004b8d;'>💬 Présentation</h3>
                            <p style='margin: 0; line-height: 1.6;'>" . nl2br(htmlspecialchars($presentation)) . "</p>
                        </div>
                        
                        <div style='background: #d1ecf1; padding: 1.5rem; border-radius: 8px; margin: 1.5rem 0; border-left: 4px solid #0c5460;'>
                            <h3 style='margin-top: 0; color: #0c5460;'>🆘 Contact d'urgence</h3>
                            <table style='width: 100%; border-collapse: collapse;'>
                                <tr><td style='padding: 0.3rem 0; font-weight: 600; width: 100px;'>Nom :</td><td>$contact_urgence_nom</td></tr>
                                <tr><td style='padding: 0.3rem 0; font-weight: 600;'>Tél :</td><td>$contact_urgence_tel</td></tr>
                                <tr><td style='padding: 0.3rem 0; font-weight: 600;'>Email :</td><td>$contact_urgence_email</td></tr>
                            </table>
                        </div>
                        
                        <div style='text-align: center; margin-top: 2rem;'>
                            <a href='https://gestnav.clubulmevasion.fr/preinscriptions_admin.php' 
                               style='background: linear-gradient(135deg, #004b8d, #00a0c6); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: 600; font-size: 1.05rem;'>
                                🔍 Accéder à l'interface de validation
                            </a>
                        </div>
                    </div>
                    <div style='padding: 1rem; text-align: center; background: #f9f9f9; border-radius: 0 0 10px 10px;'>
                        <p style='margin: 0; color: #666; font-size: 0.85rem;'>GESTNAV v" . GESTNAV_VERSION . " - Club ULM Evasion</p>
                    </div>
                </body>
                </html>
            ";
            $resultInfo = gestnav_send_mail($pdo, 'info@clubulmevasion.fr', $notifSubject, $notifMessage);
            
            if (!$resultInfo['success']) {
                error_log("Erreur envoi email info@clubulmevasion.fr: " . $resultInfo['error']);
            }
            
            // Notifier les admins
            $stmtAdmins = $pdo->query("SELECT email, prenom, nom FROM users WHERE role = 'admin' AND email IS NOT NULL AND email != ''");
            $admins = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);
            foreach ($admins as $admin) {
                $adminSubject = "🆕 Nouvelle pré-inscription - $prenom $nom";
                $adminMessage = "
                    <html>
                    <body style='font-family: Arial, sans-serif;'>
                        <div style='background: linear-gradient(135deg, #004b8d, #00a0c6); color: white; padding: 1.5rem; border-radius: 8px 8px 0 0;'>
                            <h2 style='margin: 0;'>🆕 Nouvelle pré-inscription</h2>
                        </div>
                        <div style='padding: 1.5rem; background: white; border: 1px solid #e0e0e0; border-top: none;'>
                            <p>Bonjour <strong>{$admin['prenom']}</strong>,</p>
                            <p>Une nouvelle demande de pré-inscription a été soumise :</p>
                            <ul style='line-height: 1.8;'>
                                <li><strong>Nom :</strong> $prenom $nom</li>
                                <li><strong>Email :</strong> <a href='mailto:$email'>$email</a></li>
                                <li><strong>Téléphone :</strong> $gsm</li>
                                <li><strong>Ville :</strong> $ville</li>
                                <li><strong>Pilote :</strong> " . ($est_pilote ? "✅ Oui (licence $numero_licence)" : "❌ Non") . "</li>
                            </ul>
                            <p style='text-align: center; margin-top: 2rem;'>
                                <a href='https://gestnav.clubulmevasion.fr/preinscriptions_admin.php' 
                                   style='background: #004b8d; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: 600;'>
                                    🔍 Voir le dossier complet
                                </a>
                            </p>
                        </div>
                        <div style='padding: 1rem; text-align: center; background: #f9f9f9; border-radius: 0 0 8px 8px;'>
                            <p style='margin: 0; color: #666; font-size: 0.8rem;'>GESTNAV - Club ULM Evasion</p>
                        </div>
                    </body>
                    </html>
                ";
                $resultAdmin = gestnav_send_mail($pdo, $admin['email'], $adminSubject, $adminMessage);
                
                if (!$resultAdmin['success']) {
                    error_log("Erreur envoi email admin {$admin['email']}: " . $resultAdmin['error']);
                }
            }
            
        } catch (Exception $e) {
            $errors[] = "Erreur lors de l'enregistrement : " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Pré-inscription - Club ULM Evasion</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #004b8d;
            --secondary: #00a0c6;
        }
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        .preinsc-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .preinsc-card {
            background: white;
            border-radius: 1.25rem;
            padding: 2rem;
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
            margin-bottom: 2rem;
        }
        .preinsc-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 2rem;
            border-radius: 1.25rem;
            text-align: center;
            margin-bottom: 2rem;
            box-shadow: 0 8px 20px rgba(0,75,141,0.3);
        }
        .preinsc-header h1 {
            margin: 0;
            font-size: 1.75rem;
            letter-spacing: 0.02em;
        }
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        .form-control, .form-select {
            border-radius: 0.75rem;
            border: 1px solid #d1d5db;
            padding: 0.75rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(0,160,198,0.1);
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 0.75rem;
            padding: 0.875rem 2rem;
            font-weight: 600;
            width: 100%;
        }
        .btn-primary:hover {
            filter: brightness(1.1);
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary);
            margin: 2rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--secondary);
        }
        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 1.5rem;
            border-radius: 0.75rem;
            border-left: 4px solid #10b981;
        }
        .alert-danger {
            border-radius: 0.75rem;
            border-left: 4px solid #dc3545;
        }
        .required::after {
            content: " *";
            color: #dc3545;
        }
    </style>
</head>
<body>

<div class="preinsc-container">
    <?php if ($success): ?>
        <div class="preinsc-card">
            <div class="success-message">
                <h2 style="margin: 0 0 1rem;">✅ Demande envoyée avec succès !</h2>
                <p style="margin: 0;">Merci pour votre pré-inscription au <strong>Club ULM Evasion</strong>.</p>
                <p style="margin: 0.5rem 0 0;">Un email de confirmation vous a été envoyé. Notre comité va étudier votre dossier et vous recontactera très prochainement.</p>
            </div>
        </div>
    <?php else: ?>
        
        <div class="preinsc-header">
            <h1>🛩️ Pré-inscription Club ULM Evasion</h1>
            <p style="margin: 0.5rem 0 0; opacity: 0.95;">Rejoignez notre communauté de passionnés</p>
        </div>

        <div class="preinsc-card">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <strong>Erreurs :</strong>
                    <ul style="margin: 0.5rem 0 0; padding-left: 1.25rem;">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                
                <div class="section-title">📋 Informations personnelles</div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Nom</label>
                        <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Prénom</label>
                        <input type="text" name="prenom" class="form-control" value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label required">Adresse (ligne 1)</label>
                    <input type="text" name="adresse_ligne1" class="form-control" value="<?= htmlspecialchars($_POST['adresse_ligne1'] ?? '') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Complément d'adresse (ligne 2)</label>
                    <input type="text" name="adresse_ligne2" class="form-control" value="<?= htmlspecialchars($_POST['adresse_ligne2'] ?? '') ?>">
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label required">Code postal</label>
                        <input type="text" name="code_postal" class="form-control" value="<?= htmlspecialchars($_POST['code_postal'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="form-label required">Ville</label>
                        <input type="text" name="ville" class="form-control" value="<?= htmlspecialchars($_POST['ville'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label required">Pays</label>
                        <input type="text" name="pays" class="form-control" value="<?= htmlspecialchars($_POST['pays'] ?? 'France') ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Téléphone fixe</label>
                        <input type="tel" name="telephone" class="form-control" value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>" placeholder="Optionnel">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Téléphone mobile (GSM)</label>
                        <input type="tel" name="gsm" class="form-control" value="<?= htmlspecialchars($_POST['gsm'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Date de naissance</label>
                        <input type="date" name="date_naissance" class="form-control" value="<?= htmlspecialchars($_POST['date_naissance'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label required">Profession</label>
                    <input type="text" name="profession" class="form-control" value="<?= htmlspecialchars($_POST['profession'] ?? '') ?>" required>
                </div>

                <div class="section-title">🚨 Contact d'urgence</div>

                <div class="mb-3">
                    <label class="form-label required">Nom de la personne à prévenir</label>
                    <input type="text" name="contact_urgence_nom" class="form-control" value="<?= htmlspecialchars($_POST['contact_urgence_nom'] ?? '') ?>" required>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Téléphone du contact</label>
                        <input type="tel" name="contact_urgence_tel" class="form-control" value="<?= htmlspecialchars($_POST['contact_urgence_tel'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Email du contact</label>
                        <input type="email" name="contact_urgence_email" class="form-control" value="<?= htmlspecialchars($_POST['contact_urgence_email'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="section-title">📷 Photo & Présentation</div>

                <div class="mb-3">
                    <label class="form-label required">Photo d'identité</label>
                    <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/jpg" required>
                    <small class="text-muted">Format JPG ou PNG, max 5MB</small>
                </div>

                <div class="mb-3">
                    <label class="form-label required">Parlez-nous de vous</label>
                    <textarea name="presentation" class="form-control" rows="5" required placeholder="Pourquoi souhaitez-vous rejoindre le club ? Quelles sont vos motivations ?"><?= htmlspecialchars($_POST['presentation'] ?? '') ?></textarea>
                </div>

                <div class="section-title">✈️ Expérience de pilotage</div>

                <div class="mb-3">
                    <label class="form-label required">Êtes-vous déjà pilote ?</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="est_pilote" id="pilote_oui" value="oui" <?= (isset($_POST['est_pilote']) && $_POST['est_pilote'] === 'oui') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="pilote_oui">Oui</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="est_pilote" id="pilote_non" value="non" <?= (!isset($_POST['est_pilote']) || $_POST['est_pilote'] === 'non') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="pilote_non">Non</label>
                    </div>
                </div>

                <div class="mb-3" id="licence_group" style="display: none;">
                    <label class="form-label">Numéro de licence</label>
                    <input type="text" name="numero_licence" class="form-control" value="<?= htmlspecialchars($_POST['numero_licence'] ?? '') ?>" placeholder="Ex: ULM123456">
                </div>

                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">📤 Envoyer ma demande</button>
                </div>
            </form>
        </div>

        <p style="text-align: center; color: #666; font-size: 0.85rem;">
            * Tous les champs sont obligatoires
        </p>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const piloteOui = document.getElementById('pilote_oui');
    const piloteNon = document.getElementById('pilote_non');
    const licenceGroup = document.getElementById('licence_group');
    
    function toggleLicence() {
        if (piloteOui.checked) {
            licenceGroup.style.display = 'block';
            licenceGroup.querySelector('input').required = true;
        } else {
            licenceGroup.style.display = 'none';
            licenceGroup.querySelector('input').required = false;
        }
    }
    
    piloteOui.addEventListener('change', toggleLicence);
    piloteNon.addEventListener('change', toggleLicence);
    toggleLicence();
});
</script>

</body>
</html>
