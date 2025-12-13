<?php
require_once 'config.php';
require_once 'auth.php';
require_login();

// Empêcher tout cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Si un ID est passé en paramètre (admin édite un membre), l'utiliser
// Sinon utiliser l'ID de la session (utilisateur édite son propre profil)
$target_user_id = isset($_GET['id']) ? (int)$_GET['id'] : ($_SESSION['user_id'] ?? null);
if (!$target_user_id) { header('Location: login.php'); exit; }

// Vérifier les droits : soit c'est son propre profil, soit il est admin
if ($target_user_id != $_SESSION['user_id'] && !is_admin()) {
    header('Location: acces_refuse.php?message=' . urlencode('Vous ne pouvez modifier que votre propre photo') . '&redirect=account.php');
    exit;
}

$stmt = $pdo->prepare('SELECT photo_path, photo_metadata FROM users WHERE id = ?');
$stmt->execute([$target_user_id]);
$target_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$target_user || empty($target_user['photo_path'])) {
    header('Location: account.php');
    exit;
}

// Récupérer les offsets existants
$offsetX = 0;
$offsetY = 0;
if (!empty($target_user['photo_metadata'])) {
    $meta = json_decode($target_user['photo_metadata'], true);
    $offsetX = $meta['offsetX'] ?? 0;
    $offsetY = $meta['offsetY'] ?? 0;
}

// Gestion POST pour sauvegarder le recadrage
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer l'ID depuis le POST (priorité) ou la session
    $save_user_id = isset($_POST['id']) ? (int)$_POST['id'] : ($_SESSION['user_id'] ?? null);
    
    // Vérifier les droits
    if ($save_user_id != $_SESSION['user_id'] && !is_admin()) {
        header('Location: acces_refuse.php?message=' . urlencode('Vous ne pouvez modifier que votre propre photo') . '&redirect=account.php');
        exit;
    }
    
    $offsetX = (int)($_POST['offsetX'] ?? 0);
    $offsetY = (int)($_POST['offsetY'] ?? 0);
    
    $metadata = json_encode(['offsetX' => $offsetX, 'offsetY' => $offsetY]);
    $pdo->prepare('UPDATE users SET photo_metadata = ? WHERE id = ?')->execute([$metadata, $save_user_id]);
    
    // Redirection avec paramètre optionnel
    $redirectTo = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : 'account.php';
    header('Location: ' . $redirectTo . '?photo=saved');
    exit;
}

require 'header.php';
?>

<style>
.crop-container { 
    max-width: 600px; 
    margin: 2rem auto; 
    padding: 1rem; 
}

.crop-header {
    text-align: center;
    margin-bottom: 2rem;
}

.crop-header h2 {
    margin: 0 0 0.5rem;
    font-size: 1.4rem;
}

.crop-header p {
    color: #666;
    margin: 0;
    font-size: 0.95rem;
}

.crop-canvas {
    position: relative;
    width: 280px;
    height: 280px;
    margin: 2rem auto;
    border-radius: 50%;
    overflow: hidden;
    background: #f5f5f5;
    border: 4px solid #ddd;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    cursor: grab;
}

.crop-canvas.dragging {
    cursor: grabbing;
}

.crop-canvas img {
    position: absolute;
    width: 100%;
    height: 100%;
    object-fit: cover;
    user-select: none;
}

.crop-hint {
    text-align: center;
    color: #999;
    font-size: 0.85rem;
    margin-top: 1rem;
}

.controls-section {
    background: #f9f9f9;
    padding: 1.5rem;
    border-radius: 0.75rem;
    margin: 2rem 0;
}

.control-group {
    margin-bottom: 1.25rem;
}

.control-group:last-child {
    margin-bottom: 0;
}

.control-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
    color: #333;
}

.slider-row {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.slider-row input[type="range"] {
    flex: 1;
    height: 6px;
    border-radius: 3px;
    background: #ddd;
    outline: none;
    -webkit-appearance: none;
}

.slider-row input[type="range"]::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #004b8d;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.slider-row input[type="range"]::-moz-range-thumb {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #004b8d;
    cursor: pointer;
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.slider-value {
    min-width: 60px;
    text-align: right;
    font-weight: 700;
    color: #004b8d;
    font-size: 1rem;
}

.button-group {
    display: flex;
    gap: 0.75rem;
    margin-top: 1.5rem;
}

.button-group button {
    flex: 1;
    padding: 0.75rem;
    font-weight: 600;
    border-radius: 0.5rem;
    border: none;
    cursor: pointer;
    font-size: 0.95rem;
    transition: all 0.2s;
}

.btn-reset {
    background: #e8e8e8;
    color: #333;
}

.btn-reset:hover {
    background: #d0d0d0;
}

.btn-save {
    background: #004b8d;
    color: #fff;
}

.btn-save:hover {
    background: #003563;
}

.back-link {
    text-align: center;
    margin-top: 1.5rem;
}

.back-link a {
    color: #666;
    text-decoration: none;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    transition: color 0.2s;
}

.back-link a:hover {
    color: #004b8d;
}
</style>

<div class="crop-container">
    <div class="crop-header">
        <h2>📸 Centrer votre photo</h2>
        <p>Glissez votre photo pour la centrer dans le cercle</p>
    </div>

    <div class="crop-canvas" id="cropCanvas">
        <img id="photoImg" src="<?= htmlspecialchars($target_user['photo_path']) ?>?nocache=<?= microtime(true) ?>" alt="Photo à recadrer">
    </div>
    <div class="crop-hint">Glissez la photo pour l'ajuster</div>

    <div class="controls-section">
        <form method="post" id="cropForm">
            <input type="hidden" name="id" value="<?= $target_user_id ?>">
            <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_GET['redirect_to'] ?? 'account.php') ?>">
            
            <div class="control-group">
                <label>Position horizontale</label>
                <div class="slider-row">
                    <input type="range" id="offsetX" name="offsetX" min="-80" max="80" value="<?= $offsetX ?>" step="1">
                    <span class="slider-value"><span id="offsetXValue"><?= $offsetX ?></span>px</span>
                </div>
            </div>

            <div class="control-group">
                <label>Position verticale</label>
                <div class="slider-row">
                    <input type="range" id="offsetY" name="offsetY" min="-80" max="80" value="<?= $offsetY ?>" step="1">
                    <span class="slider-value"><span id="offsetYValue"><?= $offsetY ?></span>px</span>
                </div>
            </div>

            <div class="button-group">
                <button type="button" class="btn-reset" onclick="resetPosition()">↻ Réinitialiser</button>
                <button type="submit" class="btn-save">✓ Enregistrer</button>
            </div>
        </form>
    </div>

    <div class="back-link">
        <a href="account.php">← Retour au compte</a>
    </div>
</div>

<script>
const cropCanvas = document.getElementById('cropCanvas');
const photoImg = document.getElementById('photoImg');
const offsetXInput = document.getElementById('offsetX');
const offsetYInput = document.getElementById('offsetY');
const offsetXValue = document.getElementById('offsetXValue');
const offsetYValue = document.getElementById('offsetYValue');

let isDragging = false;
let startX = 0;
let startY = 0;
let currentX = parseInt(offsetXInput.value);
let currentY = parseInt(offsetYInput.value);

function updatePreview() {
    currentX = parseInt(offsetXInput.value);
    currentY = parseInt(offsetYInput.value);
    photoImg.style.transform = `translate(calc(-50% + ${currentX}px), calc(-50% + ${currentY}px))`;
    photoImg.style.top = '50%';
    photoImg.style.left = '50%';
    offsetXValue.textContent = currentX;
    offsetYValue.textContent = currentY;
}

function resetPosition() {
    offsetXInput.value = 0;
    offsetYInput.value = 0;
    updatePreview();
}

// Drag pour ajuster la photo
cropCanvas.addEventListener('mousedown', (e) => {
    isDragging = true;
    startX = e.clientX;
    startY = e.clientY;
    cropCanvas.classList.add('dragging');
    e.preventDefault();
});

document.addEventListener('mousemove', (e) => {
    if (!isDragging) return;
    
    const deltaX = e.clientX - startX;
    const deltaY = e.clientY - startY;
    
    offsetXInput.value = Math.max(-80, Math.min(80, currentX + deltaX / 2));
    offsetYInput.value = Math.max(-80, Math.min(80, currentY + deltaY / 2));
    updatePreview();
});

document.addEventListener('mouseup', () => {
    isDragging = false;
    cropCanvas.classList.remove('dragging');
    currentX = parseInt(offsetXInput.value);
    currentY = parseInt(offsetYInput.value);
});

// Sliders
offsetXInput.addEventListener('input', updatePreview);
offsetYInput.addEventListener('input', updatePreview);

// Initialiser
updatePreview();
</script>

<?php require 'footer.php'; ?>
