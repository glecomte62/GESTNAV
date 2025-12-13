<?php
require_once 'config.php';

// Démarrer la session si pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Récupérer le message personnalisé si fourni
$message = $_GET['message'] ?? 'Cette fonctionnalité est réservée aux administrateurs du club.';
$redirect = $_GET['redirect'] ?? 'index.php';

require 'header.php';
?>

<style>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-20px); }
}

@keyframes shake {
    0%, 100% { transform: rotate(0deg); }
    10%, 30%, 50%, 70%, 90% { transform: rotate(-10deg); }
    20%, 40%, 60%, 80% { transform: rotate(10deg); }
}

.error-container {
    min-height: 70vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    margin: -2rem -2rem 2rem -2rem;
}

.error-card {
    background: white;
    border-radius: 30px;
    padding: 3rem;
    max-width: 600px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
    text-align: center;
    animation: fadeIn 0.6s ease-out;
}

.error-icon {
    width: 150px;
    height: 150px;
    margin: 0 auto 2rem;
    background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: float 3s ease-in-out infinite;
}

.error-icon i {
    font-size: 5rem;
    color: white;
    animation: shake 2s ease-in-out 1;
}

.error-code {
    font-size: 1.5rem;
    font-weight: 800;
    color: #ff6b6b;
    letter-spacing: 2px;
    margin-bottom: 1rem;
}

.error-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: #2c3e50;
    margin-bottom: 1.5rem;
}

.error-message {
    font-size: 1.1rem;
    color: #666;
    margin-bottom: 2rem;
    line-height: 1.6;
}

.error-details {
    background: #f8f9fa;
    border-left: 4px solid #ff6b6b;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    text-align: left;
}

.error-details strong {
    color: #2c3e50;
    display: block;
    margin-bottom: 0.5rem;
}

.error-details p {
    margin: 0;
    color: #666;
    font-size: 0.95rem;
}

.btn-group {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-custom {
    padding: 1rem 2rem;
    border-radius: 50px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary-custom {
    background: linear-gradient(135deg, #003a64, #1e6ba8);
    color: white;
    border: none;
}

.btn-primary-custom:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,58,100,0.3);
    color: white;
}

.btn-secondary-custom {
    background: white;
    color: #003a64;
    border: 2px solid #003a64;
}

.btn-secondary-custom:hover {
    background: #003a64;
    color: white;
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,58,100,0.2);
}

.info-box {
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    border-radius: 15px;
    padding: 1.5rem;
    margin-top: 2rem;
}

.info-box h4 {
    color: #1976d2;
    font-size: 1.1rem;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-box ul {
    text-align: left;
    margin: 0;
    padding-left: 1.5rem;
    color: #555;
}

.info-box li {
    margin-bottom: 0.5rem;
}
</style>

<div class="error-container">
    <div class="error-card">
        <div class="error-icon">
            <i class="bi bi-shield-lock-fill"></i>
        </div>
        
        <div class="error-code">ERREUR 403</div>
        <h1 class="error-title">Accès Refusé</h1>
        
        <p class="error-message">
            <?= htmlspecialchars($message) ?>
        </p>
        
        <div class="error-details">
            <strong><i class="bi bi-info-circle"></i> Information</strong>
            <p>
                Vous devez disposer des droits d'administrateur pour accéder à cette ressource.
                Si vous pensez qu'il s'agit d'une erreur, veuillez contacter un administrateur du club.
            </p>
        </div>
        
        <div class="btn-group">
            <a href="<?= htmlspecialchars($redirect) ?>" class="btn-custom btn-primary-custom">
                <i class="bi bi-arrow-left"></i>
                Retour
            </a>
            <a href="index.php" class="btn-custom btn-secondary-custom">
                <i class="bi bi-house-door"></i>
                Accueil
            </a>
        </div>
        
        <div class="info-box">
            <h4>
                <i class="bi bi-lightbulb"></i>
                Fonctionnalités accessibles :
            </h4>
            <ul>
                <li>Consulter les sorties et événements</li>
                <li>S'inscrire aux activités</li>
                <li>Gérer votre profil</li>
                <li>Voir vos statistiques personnelles</li>
            </ul>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
