<?php
if (!defined('GESTNAV_LOADED')) {
    define('GESTNAV_LOADED', true);
    require_once 'config.php';
    require_once 'auth.php';
}

require_login();

// Charge les dépendances de manière sécurisée
if (file_exists(__DIR__ . '/mail_helper.php') && !function_exists('gestnav_send_mail')) {
    require_once 'mail_helper.php';
}

if (file_exists(__DIR__ . '/utils/proposal_email_notifier.php') && !class_exists('ProposalEmailNotifier')) {
    require_once 'utils/proposal_email_notifier.php';
}

$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $month = trim($_POST['month'] ?? '');
    
    // Gérer aérodromes et bases ULM
    $aerodrome_raw = trim($_POST['aerodrome_id'] ?? '');
    $aerodrome_id = null;
    $ulm_base_id = null;
    
    if (!empty($aerodrome_raw)) {
        if (strpos($aerodrome_raw, 'ulm_') === 0) {
            // Base ULM: extraire l'ID
            $ulm_base_id = (int)substr($aerodrome_raw, 4);
        } else {
            // Aérodrome classique
            $aerodrome_id = (int)$aerodrome_raw;
        }
    }
    
    $restaurant = trim($_POST['restaurant_choice'] ?? '');
    $restaurant_details = trim($_POST['restaurant_details'] ?? '');
    $activity = trim($_POST['activity_details'] ?? '');
    
    if (empty($titre)) {
        $flash = ['type' => 'error', 'text' => 'Le titre est obligatoire.'];
    } elseif (empty($_FILES['photo']['name'])) {
        $flash = ['type' => 'error', 'text' => 'La photo est obligatoire.'];
    } else {
        try {
            $photo_filename = null;
            
            if (!empty($_FILES['photo']['name'])) {
                $maxFileSize = 10 * 1024 * 1024;
                if ($_FILES['photo']['size'] > $maxFileSize) {
                    $flash = ['type' => 'error', 'text' => 'Photo trop volumineuse (max 10MB).'];
                } else {
                    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
                    if (!in_array($_FILES['photo']['type'], $allowedMimes)) {
                        $flash = ['type' => 'error', 'text' => 'Format photo non autorise.'];
                    } else {
                        $uploadsDir = __DIR__ . '/uploads/proposals';
                        if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0755, true);
                        
                        $ext = 'jpg';
                        if ($_FILES['photo']['type'] === 'image/png') $ext = 'png';
                        if ($_FILES['photo']['type'] === 'image/webp') $ext = 'webp';
                        
                        $filename = 'proposal_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                        $photoPath = $uploadsDir . '/' . $filename;
                        
                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $photoPath)) {
                            $photo_filename = $filename;
                        } else {
                            $flash = ['type' => 'error', 'text' => 'Erreur lors de l upload de la photo.'];
                        }
                    }
                }
            }
            
            if (!$flash) {
                $stmt = $pdo->prepare("INSERT INTO sortie_proposals (user_id, titre, description, month_proposed, aerodrome_id, ulm_base_id, restaurant_choice, restaurant_details, activity_details, photo_filename, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente')");
                $stmt->execute([$_SESSION['user_id'], $titre, $description, $month, $aerodrome_id, $ulm_base_id, $restaurant, $restaurant_details, $activity, $photo_filename]);
                
                $proposalId = $pdo->lastInsertId();
                
                // Send notifications if the class is available
                if (class_exists('ProposalEmailNotifier')) {
                    try {
                        $notifier = new ProposalEmailNotifier($pdo);
                        $notifier->notifyNewProposal($proposalId);
                    } catch (Exception $e) {
                        error_log("Erreur notification: " . $e->getMessage());
                    }
                }
                
                $flash = ['type' => 'success', 'text' => 'Sortie proposee! Les administrateurs examineront votre proposition.'];
            }
        } catch (Exception $e) {
            $flash = ['type' => 'error', 'text' => 'Erreur: ' . $e->getMessage()];
        }
    }
}

require 'header.php';
?>

<style>
.propose-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem 1rem 3rem;
}

.propose-header {
    background: linear-gradient(135deg, #004b8d 0%, #0066c0 100%);
    color: #ffffff;
    padding: 3rem 2rem;
    border-radius: 0.75rem;
    margin-bottom: 2rem;
    text-align: center;
}

.propose-header h1 {
    margin: 0 0 0.5rem;
    font-size: 2rem;
}

.propose-header p {
    margin: 0;
    opacity: 0.95;
}

.propose-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    padding: 2rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.section-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #004b8d;
    margin: 2rem 0 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #e5e7eb;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #1a1a1a;
}

.form-group input,
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    font-family: inherit;
    font-size: 0.95rem;
}

.form-group textarea {
    min-height: 120px;
    resize: vertical;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: #0066c0;
    box-shadow: 0 0 0 3px rgba(0, 102, 192, 0.1);
}

.form-group small {
    display: block;
    margin-top: 0.25rem;
    color: #6b7280;
    font-size: 0.85rem;
}

.form-group.required label:after {
    content: ' *';
    color: #ef4444;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.button-group {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    justify-content: flex-end;
}

.btn-submit {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #ffffff;
    padding: 0.75rem 2rem;
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-cancel {
    background: #f3f4f6;
    color: #1a1a1a;
    padding: 0.75rem 2rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.3s ease;
    display: inline-block;
}

.btn-cancel:hover {
    background: #e5e7eb;
}

.flash {
    padding: 1rem;
    border-radius: 0.75rem;
    margin-bottom: 1.5rem;
    font-weight: 500;
}

.flash.success {
    background: #d1fae5;
    color: #065f46;
    border-left: 4px solid #10b981;
}

.flash.error {
    background: #fee2e2;
    color: #7f1d1d;
    border-left: 4px solid #ef4444;
}

@media (max-width: 768px) {
    .propose-header h1 {
        font-size: 1.5rem;
    }
    
    .button-group {
        flex-direction: column;
    }
    
    .button-group button,
    .button-group a {
        width: 100%;
    }
}
</style>

<div class="propose-container">
    <div class="propose-header">
        <h1>Proposer une Sortie</h1>
        <p>Partagez vos idees de sorties avec le club!</p>
    </div>

    <?php if ($flash): ?>
        <div class="flash <?= $flash['type'] ?>"><?= htmlspecialchars($flash['text']) ?></div>
    <?php endif; ?>

    <div class="propose-card">
        <form method="post" enctype="multipart/form-data">
            <div class="section-title">Informations Principales</div>
            
            <div class="form-group required">
                <label>Titre de la sortie</label>
                <input type="text" name="titre" required placeholder="Ex: Vol sur le Mont-Blanc">
                <small>Soyez descriptif et concis</small>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" placeholder="Decrivez votre idee de sortie en detail..."></textarea>
                <small>Contexte, points d interet, duree estimee, etc.</small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Mois propose</label>
                    <select name="month">
                        <option value="">-- Selectionner un mois --</option>
                        <option value="janvier">Janvier</option>
                        <option value="fevrier">Fevrier</option>
                        <option value="mars">Mars</option>
                        <option value="avril">Avril</option>
                        <option value="mai">Mai</option>
                        <option value="juin">Juin</option>
                        <option value="juillet">Juillet</option>
                        <option value="aout">Aout</option>
                        <option value="septembre">Septembre</option>
                        <option value="octobre">Octobre</option>
                        <option value="novembre">Novembre</option>
                        <option value="decembre">Decembre</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Lieu (Aérodromes et Bases ULM)</label>
                    <select name="aerodrome_id">
                        <option value="">-- Sélectionner une destination --</option>
                        <?php 
                        try {
                            // Récupérer les aérodromes
                            $aeros = $pdo->query("SELECT id, oaci, nom, 'aerodrome' as type FROM aerodromes_fr ORDER BY nom")->fetchAll();
                            
                            // Récupérer les bases ULM
                            $ulm_bases = [];
                            try {
                                $ulm_bases = $pdo->query("SELECT id, oaci, nom, 'ulm' as type FROM ulm_bases_fr ORDER BY nom")->fetchAll();
                            } catch (Exception $e) {
                                // Table ulm_bases_fr n'existe peut-être pas encore
                            }
                            
                            // Fusionner et afficher par catégorie
                            if (!empty($aeros)) {
                                echo '<optgroup label="🛩️ Aérodromes">';
                                foreach ($aeros as $aero) {
                                    echo '<option value="' . htmlspecialchars($aero['id']) . '">' . htmlspecialchars($aero['oaci'] . ' - ' . $aero['nom']) . '</option>';
                                }
                                echo '</optgroup>';
                            }
                            
                            if (!empty($ulm_bases)) {
                                echo '<optgroup label="🪂 Bases ULM">';
                                foreach ($ulm_bases as $ulm) {
                                    echo '<option value="ulm_' . htmlspecialchars($ulm['id']) . '">' . htmlspecialchars($ulm['oaci'] . ' - ' . $ulm['nom']) . '</option>';
                                }
                                echo '</optgroup>';
                            }
                        } catch (Exception $e) {
                            error_log("Erreur chargement destinations: " . $e->getMessage());
                        }
                        ?>
                    </select>
                    <small>Destination principale (aérodrome ou base ULM)</small>
                </div>
            </div>

            <div class="section-title">Restauration et Activites</div>

            <div class="form-group">
                <label>Choix de restaurant</label>
                <input type="text" name="restaurant_choice" placeholder="Ex: Restaurant de l Aerodromes">
                <small>Ou manger sur place?</small>
            </div>

            <div class="form-group">
                <label>Details restaurant</label>
                <textarea name="restaurant_details" placeholder="Menu recommande, specialites, prix estime..." style="min-height: 80px;"></textarea>
            </div>

            <div class="form-group">
                <label>Activite ou visite sur place</label>
                <textarea name="activity_details" placeholder="Ex: Visite du musee de l aviation, randonnee, etc." style="min-height: 80px;"></textarea>
            </div>

            <div class="section-title">Photo Illustration</div>

            <div class="form-group">
                <label>Photo (obligatoire) *</label>
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
                <small>JPG, PNG ou WebP (max 10MB) - Photo du lieu ou de l'aérodrome</small>
            </div>

            <div class="button-group">
                <a href="sortie_proposals_list.php" class="btn-cancel">Annuler</a>
                <button type="submit" class="btn-submit">Soumettre la proposition</button>
            </div>
        </form>
    </div>
</div>

<?php require 'footer.php'; ?>
