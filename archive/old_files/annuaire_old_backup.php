<?php
require_once 'config.php';
require_once 'auth.php';
require_login();

// Récupérer tous les membres actifs
$stmt = $pdo->prepare("SELECT id, prenom, nom, email, telephone, qualification, photo_path, photo_metadata FROM users WHERE actif = 1 ORDER BY nom, prenom");
$stmt->execute();
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtre de recherche (côté client avec JS, mais on prépare les données)
$memberCount = count($members);
?>

<?php include 'header.php'; ?>

<style>
.annuaire-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem 3rem;
}

.annuaire-header {
    margin-bottom: 2rem;
}

.annuaire-title {
    font-size: 2rem;
    font-weight: 700;
    color: #333;
    margin: 0 0 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.annuaire-subtitle {
    color: #666;
    font-size: 0.95rem;
    margin: 0;
}

.search-box {
    background: #f9f9f9;
    padding: 1.5rem;
    border-radius: 0.75rem;
    margin-bottom: 2rem;
    border: 2px solid transparent;
    transition: border-color 0.2s;
}

.search-box:focus-within {
    border-color: #004b8d;
    background: #fff;
}

.search-input {
    width: 100%;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    border: 1px solid #ddd;
    border-radius: 0.5rem;
    transition: all 0.2s;
}

.search-input::placeholder {
    color: #999;
}

.search-input:focus {
    outline: none;
    border-color: #004b8d;
    box-shadow: 0 0 0 3px rgba(0, 75, 141, 0.1);
}

.member-count {
    color: #999;
    font-size: 0.85rem;
    margin-top: 0.5rem;
}

.members-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

@media (max-width: 1024px) {
    .members-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .members-grid {
        grid-template-columns: 1fr;
    }
}

.member-card {
    background: #fff;
    border-radius: 0.75rem;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.03);
    display: flex;
    flex-direction: row;
    height: 170px;
}

.member-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
}

.member-photo-section {
    flex: 0 0 170px;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.member-photo-container {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid #fff;
    background: #f0f0f0;
    position: relative;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.member-photo-container img {
    position: absolute;
    width: 100%;
    height: 100%;
    object-fit: cover;
    top: 0;
    left: 0;
}

.member-content {
    flex: 1;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    justify-content: space-between;
}

.member-header {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.member-name {
    font-size: 1rem;
    font-weight: 700;
    color: #333;
    line-height: 1.2;
}

.member-firstname {
    font-size: 0.85rem;
    color: #666;
    font-weight: 500;
}

.member-badge {
    display: inline-flex;
    padding: 0.35rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.8rem;
    font-weight: 700;
    width: fit-content;
    letter-spacing: 0.3px;
}

.badge-pilot {
    background: #dbeafe;
    color: #1e40af;
}

.badge-student {
    background: #fef3c7;
    color: #92400e;
}

.member-contact {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.contact-link {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.8rem;
    color: #666;
    text-decoration: none;
    transition: color 0.2s;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.contact-link:hover {
    color: #004b8d;
}

.contact-link i {
    flex-shrink: 0;
    color: #004b8d;
    font-size: 0.85rem;
}

.no-results {
    text-align: center;
    padding: 3rem 1rem;
    color: #999;
}

.no-results-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.no-results-text {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
}

.no-results-hint {
    font-size: 0.9rem;
    color: #bbb;
}

.hidden {
    display: none;
}

.members-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

@media (max-width: 1024px) {
    .members-grid {
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    }
}

@media (max-width: 768px) {
    .members-grid {
        grid-template-columns: 1fr;
    }
}

.member-card {
    background: #fff;
    border-radius: 0.75rem;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.03);
    display: flex;
    flex-direction: row;
}

.member-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.12);
}

.member-photo-section {
    flex: 0 0 140px;
    background: linear-gradient(135deg, #f0f0f0, #e8e8e8);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.member-photo-container {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid #fff;
    background: #f0f0f0;
    position: relative;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.member-photo-container img {
    position: absolute;
    width: 100%;
    height: 100%;
    object-fit: cover;
    top: 0;
    left: 0;
}

.member-content {
    flex: 1;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    justify-content: space-between;
}

.member-header {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.member-name {
    font-size: 1rem;
    font-weight: 700;
    color: #333;
    line-height: 1.2;
}

.member-firstname {
    font-size: 0.85rem;
    color: #666;
    font-weight: 500;
}

.member-badge {
    display: inline-flex;
    padding: 0.35rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.8rem;
    font-weight: 700;
    width: fit-content;
    letter-spacing: 0.3px;
}

.badge-pilot {
    background: #dbeafe;
    color: #1e40af;
}

.badge-student {
    background: #fef3c7;
    color: #92400e;
}

.member-contact {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.contact-link {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.8rem;
    color: #666;
    text-decoration: none;
    transition: color 0.2s;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.contact-link:hover {
    color: #004b8d;
}

.contact-link i {
    flex-shrink: 0;
    color: #004b8d;
    font-size: 0.85rem;
}

.no-results {
    text-align: center;
    padding: 3rem 1rem;
    color: #999;
}

.no-results-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.no-results-text {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
}

.no-results-hint {
    font-size: 0.9rem;
    color: #bbb;
}

.hidden {
    display: none;
}
</style>

<div class="annuaire-wrapper">
    <div class="annuaire-header">
        <h1 class="annuaire-title">
            <i class="bi bi-people-fill"></i>
            Annuaire
        </h1>
        <p class="annuaire-subtitle">Club LFQJ — Répertoire des membres</p>
    </div>

    <div class="search-box">
        <input 
            type="text" 
            id="searchInput" 
            class="search-input" 
            placeholder="🔍 Rechercher par nom, prénom ou qualification..."
            autocomplete="off"
        >
        <div class="member-count">
            <span id="resultCount"><?= $memberCount ?></span> membre<?= $memberCount > 1 ? 's' : '' ?> trouvé<?= $memberCount > 1 ? 's' : '' ?>
        </div>
    </div>

    <div class="members-grid" id="membersGrid">
        <?php foreach ($members as $member): ?>
            <?php
                $memberId = (int)$member['id'];
                $firstName = htmlspecialchars($member['prenom'] ?? '');
                $lastName = htmlspecialchars($member['nom'] ?? '');
                $email = htmlspecialchars($member['email'] ?? '');
                $phone = htmlspecialchars($member['telephone'] ?? '');
                $qualification = htmlspecialchars($member['qualification'] ?? '');
                
                // Photo avec offsets
                $photoPath = '/assets/img/avatar-placeholder.svg';
                $offsetX = 0;
                $offsetY = 0;
                
                if (!empty($member['photo_path'])) {
                    $photoPath = $member['photo_path'];
                } else {
                    $fsBase = __DIR__ . '/uploads/members';
                    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
                        $fs = $fsBase . '/member_' . $memberId . '.' . $ext;
                        if (@file_exists($fs)) {
                            $photoPath = '/uploads/members/member_' . $memberId . '.' . $ext;
                            break;
                        }
                    }
                }
                
                if (!empty($member['photo_metadata'])) {
                    $meta = json_decode($member['photo_metadata'], true);
                    $offsetX = $meta['offsetX'] ?? 0;
                    $offsetY = $meta['offsetY'] ?? 0;
                }
                
                // Texte de recherche pour filtrage
                $searchText = strtolower($firstName . ' ' . $lastName . ' ' . $qualification . ' ' . $email . ' ' . $phone);
            ?>
            <div class="member-card" data-search="<?= htmlspecialchars($searchText) ?>">
                <div class="member-photo-section">
                    <div class="member-photo-container">
                        <img src="<?= htmlspecialchars($photoPath) ?>" alt="<?= $firstName ?> <?= $lastName ?>" style="transform: translate(<?= $offsetX ?>px, <?= $offsetY ?>px);">
                    </div>
                </div>
                
                <div class="member-content">
                    <div class="member-header">
                        <div>
                            <div class="member-name"><?= $lastName ?></div>
                            <div class="member-firstname"><?= $firstName ?></div>
                        </div>
                        
                        <?php if (!empty($qualification)): ?>
                            <span class="member-badge <?= $qualification === 'Pilote' ? 'badge-pilot' : 'badge-student' ?>">
                                <?= $qualification ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="member-contact">
                        <?php if (!empty($email)): ?>
                            <a href="mailto:<?= htmlspecialchars($email) ?>" class="contact-link" title="<?= htmlspecialchars($email) ?>">
                                <i class="bi bi-envelope-fill"></i>
                                <span><?= htmlspecialchars($email) ?></span>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($phone)): ?>
                            <a href="tel:<?= htmlspecialchars(str_replace(' ', '', $phone)) ?>" class="contact-link" title="<?= htmlspecialchars($phone) ?>">
                                <i class="bi bi-telephone-fill"></i>
                                <span><?= htmlspecialchars($phone) ?></span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($members)): ?>
        <div class="no-results">
            <div class="no-results-icon">👥</div>
            <div class="no-results-text">Aucun membre trouvé</div>
            <div class="no-results-hint">L'annuaire est actuellement vide</div>
        </div>
    <?php endif; ?>
</div>

<script>
const searchInput = document.getElementById('searchInput');
const membersGrid = document.getElementById('membersGrid');
const resultCount = document.getElementById('resultCount');
const cards = Array.from(document.querySelectorAll('.member-card'));

function updateSearch() {
    const query = searchInput.value.toLowerCase().trim();
    let visibleCount = 0;

    cards.forEach(card => {
        const searchText = card.dataset.search || '';
        const matches = query === '' || searchText.includes(query);
        
        if (matches) {
            card.classList.remove('hidden');
            visibleCount++;
        } else {
            card.classList.add('hidden');
        }
    });

    resultCount.textContent = visibleCount;
    
    // Afficher message "aucun résultat"
    const noResults = membersGrid.parentElement.querySelector('.no-results');
    if (visibleCount === 0 && cards.length > 0) {
        if (!noResults) {
            const div = document.createElement('div');
            div.className = 'no-results';
            div.innerHTML = `
                <div class="no-results-icon">🔍</div>
                <div class="no-results-text">Aucun résultat</div>
                <div class="no-results-hint">Essayez un autre terme de recherche</div>
            `;
            membersGrid.parentElement.appendChild(div);
        }
    } else if (noResults) {
        noResults.remove();
    }
}

searchInput.addEventListener('input', updateSearch);

// Focus initial
window.addEventListener('load', () => {
    searchInput.focus();
});
</script>

<?php require 'footer.php'; ?>
