<?php
require_once 'config.php';
require_once 'auth.php';

// Pas besoin d'être connecté pour voir la présentation
require 'header.php';
?>

<style>
.about-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 2rem 1rem 3rem;
}

.about-header {
    text-align: center;
    margin-bottom: 3rem;
    padding-bottom: 2rem;
    border-bottom: 3px solid #004b8d;
}

.about-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: #004b8d;
    margin: 0 0 0.5rem;
}

.about-subtitle {
    color: #666;
    font-size: 1rem;
    margin: 0;
}

.author-section {
    background: linear-gradient(135deg, #004b8d 0%, #0066c0 100%);
    color: #ffffff;
    padding: 3rem 2rem;
    border-radius: 0.75rem;
    margin-bottom: 3rem;
    box-shadow: 0 4px 16px rgba(0, 75, 141, 0.2);
    text-align: center;
    display: flex;
    align-items: center;
    gap: 3rem;
}

.author-photo {
    flex-shrink: 0;
}

.author-photo img {
    width: 200px;
    height: 200px;
    border-radius: 50%;
    border: 4px solid rgba(255,255,255,0.3);
    object-fit: cover;
}

.author-text {
    flex: 1;
    text-align: left;
}

.author-name {
    font-size: 2rem;
    font-weight: 700;
    margin: 0 0 1rem;
}

.author-role {
    font-size: 1.2rem;
    margin: 0 0 0.5rem;
    opacity: 0.95;
}

.author-info {
    font-size: 0.95rem;
    line-height: 1.8;
    margin: 0;
    opacity: 0.9;
}

.about-card {
    background: #ffffff;
    border: 1px solid #e8ecf1;
    border-radius: 0.75rem;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.about-card-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #004b8d;
    margin: 0 0 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.about-content {
    color: #555;
    line-height: 1.8;
    margin: 0;
}

.thanks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.thanks-person {
    background: linear-gradient(135deg, #f5f7fa 0%, #ffffff 100%);
    border-left: 4px solid #10b981;
    padding: 1.5rem;
    border-radius: 0.5rem;
    transition: all 0.3s ease;
}

.thanks-person:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.thanks-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1a1a1a;
    margin: 0;
}

.thanks-role {
    font-size: 0.9rem;
    color: #999;
    margin: 0.25rem 0 0;
}

.app-features {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1.5rem;
}

.feature {
    background: #f9f9f9;
    padding: 1rem;
    border-radius: 0.5rem;
    text-align: center;
}

.feature-icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.feature-name {
    font-size: 0.95rem;
    font-weight: 600;
    color: #333;
    margin: 0;
}

.btn-changelog {
    display: inline-block;
    background: linear-gradient(135deg, #004b8d 0%, #0066c0 100%);
    color: #ffffff;
    padding: 0.75rem 2rem;
    border-radius: 0.5rem;
    text-decoration: none;
    font-weight: 600;
    margin-top: 1rem;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    font-size: 0.95rem;
}

.btn-changelog:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 75, 141, 0.3);
    text-decoration: none;
    color: #ffffff;
}

.contact-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.contact-item {
    background: #f9f9f9;
    padding: 1.5rem;
    border-radius: 0.5rem;
    border-left: 4px solid #0066c0;
}

.contact-item-icon {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.contact-item-label {
    font-size: 0.85rem;
    color: #999;
    text-transform: uppercase;
    font-weight: 600;
    margin: 0;
}

.contact-item-value {
    font-size: 1rem;
    font-weight: 600;
    color: #1a1a1a;
    margin: 0.5rem 0 0;
}

.contact-item-value a {
    color: #0066c0;
    text-decoration: none;
}

.contact-item-value a:hover {
    text-decoration: underline;
}

@media (max-width: 768px) {
    .about-container {
        padding: 1rem;
    }
    
    .about-title {
        font-size: 2rem;
    }
    
    .author-section {
        padding: 2rem 1.5rem;
        flex-direction: column;
        text-align: center;
    }
    
    .author-text {
        text-align: center;
    }
    
    .author-photo img {
        width: 150px;
        height: 150px;
    }
    
    .author-name {
        font-size: 1.5rem;
    }
    
    .author-role {
        font-size: 1rem;
    }
    
    .about-card {
        padding: 1.5rem;
    }
}
</style>

<div class="about-container">
    <div class="about-header">
        <h1 class="about-title">À propos</h1>
        <p class="about-subtitle">GESTNAV ULM - Espace membres du Club ULM Evasion</p>
    </div>

    <!-- Section Auteur -->
    <div class="author-section">
        <div class="author-photo">
            <img src="assets/img/guillaume.jpeg" alt="Guillaume LECOMTE" loading="lazy" decoding="async">
        </div>
        <div class="author-text">
            <h2 class="author-name">Guillaume LECOMTE</h2>
            <div class="author-role">👨‍💻 Informaticien • 🛩️ Pilote</div>
            <p class="author-info">
                Entré au club en 2022 et passionné par les ULM et l'aviation.<br>
                Développeur de cette application avec cœur et motivation.<br>
                <br>
                <em>"Créer une plateforme qui rapproche les membres et facilite la gestion du club."</em>
            </p>
        </div>
    </div>

    <!-- Section À propos de l'app -->
    <div class="about-card">
        <h3 class="about-card-title">
            ✈️ À propos de GESTNAV ULM
        </h3>
        <div style="display: flex; gap: 2rem; margin-bottom: 1.5rem; align-items: flex-start;">
            <img src="assets/img/club.jpeg" alt="Club ULM Evasion" loading="lazy" decoding="async" style="width: 200px; height: 150px; border-radius: 0.5rem; object-fit: cover; flex-shrink: 0;">
            <p class="about-content" style="margin: 0;">
                GESTNAV ULM est une plateforme de gestion développée pour le Club ULM Evasion basé à LFQJ.
                L'application permet aux membres de consulter les sorties, s'inscrire aux événements, 
                voir l'annuaire des membres avec leurs coordonnées et profils, et bien d'autres fonctionnalités 
                pour améliorer la vie du club.
            </p>
        </div>

        <h4 style="font-size: 1.1rem; font-weight: 700; color: #004b8d; margin-top: 1.5rem; margin-bottom: 1rem;">
            🎯 Fonctionnalités principales
        </h4>
        <div class="app-features">
            <div class="feature">
                <div class="feature-icon">📅</div>
                <p class="feature-name">Gestion des sorties</p>
            </div>
            <div class="feature">
                <div class="feature-icon">👥</div>
                <p class="feature-name">Annuaire des membres</p>
            </div>
            <div class="feature">
                <div class="feature-icon">✍️</div>
                <p class="feature-name">Inscriptions en ligne</p>
            </div>
            <div class="feature">
                <div class="feature-icon">🗺️</div>
                <p class="feature-name">Cartes interactives</p>
            </div>
            <div class="feature">
                <div class="feature-icon">📱</div>
                <p class="feature-name">Responsive design</p>
            </div>
            <div class="feature">
                <div class="feature-icon">🔔</div>
                <p class="feature-name">Notifications</p>
            </div>
        </div>

        <div style="text-align: center; margin-top: 2rem;">
            <a href="changelog.php" class="btn-changelog">📋 Voir l'historique des versions</a>
        </div>
    </div>

    <!-- Section Remerciements -->
    <div class="about-card">
        <h3 class="about-card-title">
            🙏 Remerciements
        </h3>
        <p class="about-content">
            Un grand merci aux personnes qui ont contribué au développement et à l'amélioration de cette plateforme :
        </p>

        <div class="thanks-grid">
            <div class="thanks-person">
                <p class="thanks-name">Julien CHANET</p>
                <p class="thanks-role">Contributeur & Conseil</p>
            </div>
            <div class="thanks-person">
                <p class="thanks-name">Frédéric DUMONT</p>
                <p class="thanks-role">Contributeur & Conseil</p>
            </div>
            <div class="thanks-person">
                <p class="thanks-name">Alain DEPRAETER</p>
                <p class="thanks-role">Contributeur & Conseil</p>
            </div>
            <div class="thanks-person">
                <p class="thanks-name">Jean-Luc LALUYE</p>
                <p class="thanks-role">Contributeur & Conseil</p>
            </div>
        </div>
    </div>

    <!-- Section Contact -->
    <div class="about-card">
        <h3 class="about-card-title">
            💬 Feedback & Suggestions
        </h3>
        <p class="about-content">
            Vous avez des suggestions d'amélioration, des bugs à signaler ou des fonctionnalités à proposer ?<br>
            N'hésitez pas à contacter Guillaume directement pour tout feedback.
            Votre avis nous aide à améliorer continuellement la plateforme !
        </p>

        <div class="contact-info">
            <div class="contact-item">
                <div class="contact-item-icon">📱</div>
                <p class="contact-item-label">Téléphone</p>
                <p class="contact-item-value"><a href="tel:+33646365629">+33 6 46 36 56 29</a></p>
            </div>
            <div class="contact-item">
                <div class="contact-item-icon">📧</div>
                <p class="contact-item-label">Email</p>
                <p class="contact-item-value"><a href="mailto:lecomteguillaume@outlook.com">lecomteguillaume@outlook.com</a></p>
            </div>
        </div>
    </div>

</div>

<?php require 'footer.php'; ?>
