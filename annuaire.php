<?php
require_once 'config.php';
require_once 'auth.php';
require_login();

// Vérifier quelles colonnes existent
$colsStmt = $pdo->query('SHOW COLUMNS FROM users');
$existingCols = $colsStmt ? $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
$hasEmportPassager = in_array('emport_passager', $existingCols);
$hasQualifRadioIfr = in_array('qualification_radio_ifr', $existingCols);

// Construire la requête dynamiquement
$selectCols = "id, prenom, nom, email, telephone, qualification, photo_path, photo_metadata";
if ($hasEmportPassager) $selectCols .= ", emport_passager";
if ($hasQualifRadioIfr) $selectCols .= ", qualification_radio_ifr";

// Récupérer tous les membres actifs avec leurs machines
try {
    $stmt = $pdo->prepare("SELECT $selectCols FROM users WHERE actif = 1 ORDER BY nom, prenom");
    $stmt->execute();
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erreur annuaire.php - Récupération membres: " . $e->getMessage());
    $members = [];
}

// Récupérer les machines pour tous les membres
$memberMachines = [];
$allMachines = [];
try {
    $stmt = $pdo->query("
        SELECT um.user_id, m.id, m.nom, m.immatriculation 
        FROM user_machines um 
        JOIN machines m ON um.machine_id = m.id 
        WHERE m.actif = 1 
        ORDER BY m.nom
    ");
    if ($stmt) {
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $uid = (int)$row['user_id'];
            if (!isset($memberMachines[$uid])) {
                $memberMachines[$uid] = [];
            }
            $memberMachines[$uid][] = $row;
            
            // Collecter machines uniques
            if (!isset($allMachines[$row['id']])) {
                $allMachines[$row['id']] = [
                    'id' => $row['id'],
                    'nom' => $row['nom'],
                    'immatriculation' => $row['immatriculation']
                ];
            }
        }
    }
} catch (Exception $e) {
    error_log("Erreur annuaire.php - Machines: " . $e->getMessage());
}

$memberCount = count($members);

// Pré-calculer les chemins des photos pour éviter les boucles file_exists dans la boucle
$photoCache = [];
$uploadsDir = __DIR__ . '/uploads/members';
try {
    foreach ($members as $member) {
        $memberId = (int)$member['id'];
        $photoPath = $member['photo_path'] ?? null;
        
        if (!empty($photoPath) && file_exists(__DIR__ . '/' . $photoPath)) {
            $photoCache[$memberId] = $photoPath;
        } else {
            $found = false;
            foreach (['webp', 'jpg', 'jpeg', 'png'] as $ext) {
                $fs = $uploadsDir . '/member_' . $memberId . '.' . $ext;
                if (file_exists($fs)) {
                    $photoCache[$memberId] = '/uploads/members/member_' . $memberId . '.' . $ext;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $photoCache[$memberId] = '/assets/img/avatar-placeholder.svg';
            }
        }
    }
} catch (Exception $e) {
    error_log("Erreur annuaire.php - Photo cache: " . $e->getMessage());
}

include 'header.php';
?>

<style>
.annuaire-wrapper {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 1rem 3rem;
}

.annuaire-header {
    margin-bottom: 2rem;
}

.annuaire-title {
    font-size: 2rem;
    font-weight: 700;
    color: #004b8d;
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
    background: linear-gradient(135deg, #f0f7ff 0%, #f5f0ff 100%);
    padding: 1.5rem;
    border-radius: 0.75rem;
    margin-bottom: 2rem;
    border: 2px solid #e0e7ff;
    transition: all 0.2s;
}

.search-box:focus-within {
    border-color: #004b8d;
    background: linear-gradient(135deg, #ffffff 0%, #f5f0ff 100%);
    box-shadow: 0 4px 12px rgba(0, 75, 141, 0.1);
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

.filter-section {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e0e7ff;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #333;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.filter-btn {
    padding: 0.5rem 1rem;
    border: 1.5px solid #d0d7e2;
    background: #fff;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    color: #666;
    white-space: nowrap;
}

.filter-btn:hover {
    border-color: #004b8d;
    background: #f0f7ff;
    color: #004b8d;
}

.filter-btn.active {
    background: #004b8d;
    color: #fff;
    border-color: #004b8d;
    box-shadow: 0 4px 12px rgba(0, 75, 141, 0.3);
}

.filter-btn.active:hover {
    filter: brightness(1.1);
}

.filter-checkbox {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    cursor: pointer;
    padding: 0.4rem 0.8rem;
    border-radius: 0.5rem;
    transition: all 0.2s;
    user-select: none;
}

.filter-checkbox:hover {
    background: #f0f7ff;
}

.filter-checkbox input[type="checkbox"] {
    cursor: pointer;
    width: 18px;
    height: 18px;
    accent-color: #004b8d;
}

.filter-checkbox label {
    cursor: pointer;
    font-size: 0.85rem;
    margin: 0;
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
        grid-template-columns: 1fr !important;
    }
    
    .member-card {
        display: flex !important;
        flex-direction: column !important;
        height: auto !important;
        grid-template-columns: unset !important;
        background: #ffffff !important;
        border-radius: 0.75rem !important;
        border: 1px solid #e8ecf1 !important;
    }
    
    .member-photo-section {
        width: 100% !important;
        height: 250px !important;
        padding: 0 !important;
        background: linear-gradient(135deg, #004b8d 0%, #0066c0 100%) !important;
        order: -1 !important;
        border-radius: 0.75rem 0.75rem 0 0 !important;
        flex: unset !important;
    }
    
    .member-photo-container {
        width: 140px !important;
        height: 140px !important;
    }
    
    .member-content {
        padding: 1.2rem !important;
        background: #ffffff !important;
        width: 100% !important;
        order: 1 !important;
        border-radius: 0 0 0.75rem 0.75rem !important;
        flex: unset !important;
        height: auto !important;
    }
    
    .member-name {
        font-size: 1.1rem !important;
    }
    
    .member-firstname {
        font-size: 0.85rem !important;
    }
    
    .member-badge {
        font-size: 0.75rem !important;
        padding: 0.3rem 0.6rem !important;
    }
    
    .contact-link {
        font-size: 0.75rem !important;
        gap: 0.3rem !important;
    }
    
    .member-header {
        gap: 0.4rem !important;
    }
    
    .member-contact {
        gap: 0.3rem !important;
    }
}

.member-card {
    border-radius: 0.5rem;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    border: none;
    display: grid;
    grid-template-columns: auto 1fr;
    height: auto;
    min-height: 190px;
    position: relative;
}

.member-card:nth-child(1n) {
    background: #ffffff;
}

.member-card:nth-child(1n) .member-content {
    background: linear-gradient(90deg, rgba(0, 75, 141, 0.12) 0%, rgba(0, 75, 141, 0.05) 50%, #ffffff 100%);
}

.member-card:nth-child(2n) {
    background: #ffffff;
}

.member-card:nth-child(2n) .member-content {
    background: linear-gradient(90deg, rgba(8, 145, 178, 0.12) 0%, rgba(8, 145, 178, 0.05) 50%, #ffffff 100%);
}

.member-card:nth-child(3n) {
    background: #ffffff;
}

.member-card:nth-child(3n) .member-content {
    background: linear-gradient(90deg, rgba(99, 102, 241, 0.12) 0%, rgba(99, 102, 241, 0.05) 50%, #ffffff 100%);
}

.member-card:nth-child(4n) {
    background: #ffffff;
}

.member-card:nth-child(4n) .member-content {
    background: linear-gradient(90deg, rgba(5, 150, 105, 0.12) 0%, rgba(5, 150, 105, 0.05) 50%, #ffffff 100%);
}

.member-card:nth-child(5n) {
    background: #ffffff;
}

.member-card:nth-child(5n) .member-content {
    background: linear-gradient(90deg, rgba(217, 119, 6, 0.12) 0%, rgba(217, 119, 6, 0.05) 50%, #ffffff 100%);
}

.member-card:nth-child(6n) {
    background: #ffffff;
}

.member-card:nth-child(6n) .member-content {
    background: linear-gradient(90deg, rgba(220, 38, 38, 0.12) 0%, rgba(220, 38, 38, 0.05) 50%, #ffffff 100%);
}

.member-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0, 75, 141, 0.15);
    border-color: #004b8d;
}

.member-photo-section {
    background: linear-gradient(135deg, #004b8d 0%, #0066c0 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
    padding: 0.3rem;
    width: 230px;
    min-width: 230px;
    height: 100%;
    min-height: 190px;
}

.member-photo-container {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    overflow: hidden;
    border: 4px solid #fff;
    background: #f0f0f0;
    position: relative;
    box-shadow: 0 8px 20px rgba(0,0,0,0.2);
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
    background: linear-gradient(90deg, rgba(0, 75, 141, 0.12) 0%, rgba(0, 75, 141, 0.06) 50%, #ffffff 100%);
    width: 100%;
    min-height: 190px;
}

.member-header {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.member-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1a1a1a;
    line-height: 1.1;
    letter-spacing: -0.3px;
}

.member-firstname {
    font-size: 0.85rem;
    color: #555;
    font-weight: 500;
}

.member-badge {
    display: inline-flex;
    padding: 0.3rem 0.6rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 700;
    width: fit-content;
}

.badge-pilot {
    background: linear-gradient(135deg, #004b8d 0%, #0066c0 100%);
    color: #fff;
    box-shadow: 0 4px 12px rgba(0, 75, 141, 0.3);
}

.badge-student {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #fff;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.qualifications-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.3rem;
}

.badge-qualification {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.25rem 0.6rem;
    border-radius: 9999px;
    font-size: 0.7rem;
    font-weight: 700;
    background: #10b981;
    color: #fff;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.badge-qualification.radio-ifr {
    background: #f59e0b;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
}

.badge-machine {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.25rem 0.6rem;
    border-radius: 9999px;
    font-size: 0.7rem;
    font-weight: 700;
    background: #06b6d4;
    color: #fff;
    box-shadow: 0 2px 8px rgba(6, 182, 212, 0.3);
}

.member-contact {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.contact-link {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.75rem;
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

.hidden {
    display: none !important;
}

@media (max-width: 768px) {
    .search-box {
        padding: 1rem;
    }
    
    .search-input {
        padding: 0.8rem;
        font-size: 1rem;
    }
    
    .member-count {
        font-size: 0.8rem;
        margin-top: 0.5rem;
    }
}

/* Couleurs de fond alternées pour chaque carte */
.member-card:nth-child(1n) .member-photo-section {
    background: linear-gradient(135deg, #004b8d 0%, #0066c0 100%);
}

.member-card:nth-child(2n) .member-photo-section {
    background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%);
}

.member-card:nth-child(3n) .member-photo-section {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
}

.member-card:nth-child(4n) .member-photo-section {
    background: linear-gradient(135deg, #059669 0%, #10b981 100%);
}

.member-card:nth-child(5n) .member-photo-section {
    background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
}

.member-card:nth-child(6n) .member-photo-section {
    background: linear-gradient(135deg, #dc2626 0%, #f87171 100%);
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
        <div class="filter-section">
            <div class="filter-group">
                <span class="filter-label">Statut pilote :</span>
                <div class="filter-buttons">
                    <button class="filter-btn" data-filter-type="status" data-filter-value="pilot" title="Afficher les pilotes confirmés">
                        ✈️ Pilote confirmé
                    </button>
                    <button class="filter-btn" data-filter-type="status" data-filter-value="student" title="Afficher les élèves pilotes">
                        📚 Élève pilote
                    </button>
                </div>
            </div>
            
            <div class="filter-group">
                <span class="filter-label">Qualifications supplémentaires :</span>
                <div class="filter-buttons">
                    <label class="filter-checkbox">
                        <input type="checkbox" data-filter-type="qualification" data-filter-value="emport" />
                        <span>👥 Emport passager</span>
                    </label>
                    <label class="filter-checkbox">
                        <input type="checkbox" data-filter-type="qualification" data-filter-value="radio" />
                        <span>📡 Radio IFR</span>
                    </label>
                </div>
            </div>
            
            <?php if (!empty($allMachines)): ?>
            <div class="filter-group">
                <span class="filter-label">Machines lâchées :</span>
                <div class="filter-buttons">
                    <?php foreach ($allMachines as $machine): ?>
                    <label class="filter-checkbox">
                        <input type="checkbox" data-filter-type="machine" data-filter-value="<?= $machine['id'] ?>" />
                        <span>✈️ <?= htmlspecialchars($machine['nom']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
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
                $emport_passager = $hasEmportPassager ? (int)($member['emport_passager'] ?? 0) : 0;
                $qualification_radio_ifr = $hasQualifRadioIfr ? (int)($member['qualification_radio_ifr'] ?? 0) : 0;
                
                // Utiliser le cache pré-calculé
                $photoPath = $photoCache[$memberId] ?? '/assets/img/avatar-placeholder.svg';
                $offsetX = 0;
                $offsetY = 0;
                
                if (!empty($member['photo_metadata'])) {
                    $meta = json_decode($member['photo_metadata'], true);
                    $offsetX = $meta['offsetX'] ?? 0;
                    $offsetY = $meta['offsetY'] ?? 0;
                }
                
                $searchText = strtolower($firstName . ' ' . $lastName . ' ' . $qualification . ' ' . $email . ' ' . $phone);
                
                // Attributs de filtrage - normaliser la qualification
                $filterAttrs = '';
                $filters = [];
                $qualLower = mb_strtolower(trim($qualification));
                
                // Déterminer le type de pilote
                if (strpos($qualLower, 'élève') !== false || strpos($qualLower, 'eleve') !== false) {
                    $filters[] = 'student';
                } elseif (strpos($qualLower, 'pilote') !== false) {
                    $filters[] = 'pilot';
                }
                
                if ($emport_passager) $filters[] = 'emport';
                if ($qualification_radio_ifr) $filters[] = 'radio';
                
                // Ajouter les machines aux filtres
                $memberMachineIds = [];
                if (isset($memberMachines[$memberId])) {
                    foreach ($memberMachines[$memberId] as $machine) {
                        $filters[] = 'machine-' . $machine['id'];
                        $memberMachineIds[] = $machine['id'];
                    }
                }
            ?>
            <div class="member-card" data-search="<?= htmlspecialchars($searchText) ?>" data-filters="<?= htmlspecialchars(implode(' ', $filters)) ?>" data-pilot="<?= in_array('pilot', $filters) ? '1' : '0' ?>" data-student="<?= in_array('student', $filters) ? '1' : '0' ?>" data-emport="<?= $emport_passager ?>" data-radio="<?= $qualification_radio_ifr ?>" data-machines="<?= htmlspecialchars(implode(' ', $memberMachineIds)) ?>">
                <div class="member-photo-section">
                    <div class="member-photo-container">
                        <img src="<?= htmlspecialchars($photoPath) ?>" alt="<?= $firstName ?> <?= $lastName ?>" loading="lazy" style="transform: translate(<?= $offsetX ?>px, <?= $offsetY ?>px);">
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
                        
                        <?php 
                        $hasMachines = isset($memberMachines[$memberId]) && !empty($memberMachines[$memberId]);
                        $hasQualifications = $emport_passager || $qualification_radio_ifr;
                        if ($hasQualifications || $hasMachines): 
                        ?>
                            <div class="qualifications-badges">
                                <?php if ($emport_passager): ?>
                                    <span class="badge-qualification" title="Emport Passager">
                                        <i class="bi bi-people"></i> Emport
                                    </span>
                                <?php endif; ?>
                                <?php if ($qualification_radio_ifr): ?>
                                    <span class="badge-qualification radio-ifr" title="Qualification Radio IFR">
                                        <i class="bi bi-broadcast"></i> Radio IFR
                                    </span>
                                <?php endif; ?>
                                <?php if ($hasMachines): ?>
                                    <?php foreach ($memberMachines[$memberId] as $machine): ?>
                                        <span class="badge-machine" title="Lâché sur <?= htmlspecialchars($machine['nom']) ?> (<?= htmlspecialchars($machine['immatriculation']) ?>)">
                                            <i class="bi bi-airplane"></i> <?= htmlspecialchars($machine['nom']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
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
        </div>
    <?php endif; ?>
</div>

<script>
const searchInput = document.getElementById('searchInput');
const membersGrid = document.getElementById('membersGrid');
const resultCount = document.getElementById('resultCount');
const statusFilters = document.querySelectorAll('[data-filter-type="status"]');
const qualificationFilters = document.querySelectorAll('[data-filter-type="qualification"]');
const machineFilters = document.querySelectorAll('[data-filter-type="machine"]');
const cards = Array.from(document.querySelectorAll('.member-card'));

let activeStatusFilter = null;
let activeQualifications = new Set();
let activeMachines = new Set();

function updateSearch() {
    const query = searchInput.value.toLowerCase().trim();
    let visibleCount = 0;

    cards.forEach(card => {
        const searchText = card.dataset.search || '';
        const searchMatches = query === '' || searchText.includes(query);
        
        // Logique de filtrage :
        // 1. Si un filtre de statut est actif ET aucune qualification n'est cochée :
        //    afficher seulement ce statut (peu importe ses qualifications bonus)
        // 2. Si des qualifications sont cochées ET un statut est actif :
        //    afficher ceux avec ce statut ET au moins une qualification
        // 3. Si des qualifications sont cochées SANS statut :
        //    afficher ceux avec au moins une qualification (peu importe le statut)
        // 4. Si des machines sont cochées :
        //    afficher ceux lâchés sur au moins une machine sélectionnée
        
        let statusMatches = true;
        let qualificationMatches = true;
        let machineMatches = true;
        
        // Filtrage par machines
        if (activeMachines.size > 0) {
            const cardMachines = card.dataset.machines ? card.dataset.machines.split(' ').filter(m => m) : [];
            machineMatches = Array.from(activeMachines).some(machineId => 
                cardMachines.includes(machineId.toString())
            );
        }
        
        if (activeStatusFilter && activeQualifications.size === 0) {
            // Cas 1: Filtrer par statut seul
            if (activeStatusFilter === 'pilot') {
                statusMatches = card.dataset.pilot === '1';
            } else if (activeStatusFilter === 'student') {
                statusMatches = card.dataset.student === '1';
            }
        } else if (activeQualifications.size > 0) {
            // Cas 2 ou 3: Des qualifications sont sélectionnées
            qualificationMatches = Array.from(activeQualifications).some(qual => {
                if (qual === 'emport') return card.dataset.emport === '1';
                if (qual === 'radio') return card.dataset.radio === '1';
                return false;
            });
            
            // Si un statut est aussi sélectionné, vérifier le statut
            if (activeStatusFilter) {
                if (activeStatusFilter === 'pilot') {
                    statusMatches = card.dataset.pilot === '1';
                } else if (activeStatusFilter === 'student') {
                    statusMatches = card.dataset.student === '1';
                }
            }
        }
        
        const matches = searchMatches && statusMatches && qualificationMatches && machineMatches;
        
        if (matches) {
            card.classList.remove('hidden');
            visibleCount++;
        } else {
            card.classList.add('hidden');
        }
    });

    resultCount.textContent = visibleCount;
}

// Gestion des filtres de statut (radio buttons)
statusFilters.forEach(btn => {
    btn.addEventListener('click', () => {
        const filterValue = btn.dataset.filterValue;
        
        if (activeStatusFilter === filterValue) {
            // Désactiver si on clique sur le même
            activeStatusFilter = null;
            btn.classList.remove('active');
        } else {
            // Désactiver les autres et activer celui-ci
            statusFilters.forEach(b => b.classList.remove('active'));
            activeStatusFilter = filterValue;
            btn.classList.add('active');
        }
        
        updateSearch();
    });
});

// Gestion des filtres de qualifications (checkboxes)
qualificationFilters.forEach(checkbox => {
    checkbox.addEventListener('change', () => {
        const filterValue = checkbox.dataset.filterValue;
        
        if (checkbox.checked) {
            activeQualifications.add(filterValue);
        } else {
            activeQualifications.delete(filterValue);
        }
        
        updateSearch();
    });
});

// Gestion des filtres de machines (checkboxes)
machineFilters.forEach(checkbox => {
    checkbox.addEventListener('change', () => {
        const filterValue = checkbox.dataset.filterValue;
        
        if (checkbox.checked) {
            activeMachines.add(filterValue);
        } else {
            activeMachines.delete(filterValue);
        }
        
        updateSearch();
    });
});

searchInput.addEventListener('input', updateSearch);
</script>

<?php require 'footer.php'; ?>
