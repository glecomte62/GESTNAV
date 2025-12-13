<?php
/**
 * Email helper for sortie_proposals notifications
 */

if (!function_exists('sendMail')) {
    require_once __DIR__ . '/../mail_helper.php';
}

class ProposalEmailNotifier {
    private $pdo;
    private $adminEmail = 'info@clubulmevasion.fr';
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    public function notifyNewProposal($proposalId) {
        try {
            $stmt = $this->pdo->prepare("SELECT sp.*, u.prenom, u.nom, u.email, a.oaci, a.nom as aero_nom FROM sortie_proposals sp JOIN users u ON sp.user_id = u.id LEFT JOIN aerodromes_fr a ON sp.aerodrome_id = a.id WHERE sp.id = ?");
            $stmt->execute([$proposalId]);
            $proposal = $stmt->fetch();
            
            if (!$proposal) return false;
            
            $adminSubject = "Nouvelle sortie proposee: {$proposal['titre']}";
            $adminBody = "Une nouvelle sortie a ete proposee par " . htmlspecialchars($proposal['prenom'] . ' ' . $proposal['nom']) . "\n\n"
                . "TITRE: " . htmlspecialchars($proposal['titre']) . "\n"
                . "MOIS: " . htmlspecialchars($proposal['month_proposed']) . "\n"
                . "LIEU: " . htmlspecialchars($proposal['aero_nom'] ?? 'Non specifie') . "\n\n"
                . "Description: " . substr(htmlspecialchars($proposal['description']), 0, 200) . "...\n\n"
                . "Lien admin: " . $this->getAdminLink($proposalId) . "\n\n"
                . "---\n"
                . "Proposal ID: {$proposalId}\n"
                . "Date: " . date('d/m/Y H:i');
            
            gestnav_send_mail($this->pdo, $this->adminEmail, $adminSubject, $adminBody);
            
            $memberSubject = "Votre sortie a ete proposee avec succes!";
            $memberBody = "Bonjour " . htmlspecialchars($proposal['prenom']) . ",\n\n"
                . "Nous avons bien recu votre proposition: " . htmlspecialchars($proposal['titre']) . "\n\n"
                . "Les administrateurs examineront votre proposition sous peu.\n"
                . "Consulter: " . $this->getProposalLink($proposalId) . "\n\n"
                . "---\nClub ULM Evasion";
            
            gestnav_send_mail($this->pdo, $proposal['email'], $memberSubject, $memberBody);
            
            return true;
        } catch (Exception $e) {
            error_log("Error notifying new proposal: " . $e->getMessage());
            return false;
        }
    }
    
    public function notifyStatusChange($proposalId, $newStatus, $adminNotes = '') {
        try {
            $stmt = $this->pdo->prepare("SELECT sp.*, u.prenom, u.email FROM sortie_proposals sp JOIN users u ON sp.user_id = u.id WHERE sp.id = ?");
            $stmt->execute([$proposalId]);
            $proposal = $stmt->fetch();
            
            if (!$proposal) return false;
            
            $statusLabels = [
                'en_attente' => 'En attente',
                'accepte' => 'Acceptee',
                'en_preparation' => 'En preparation',
                'validee' => 'Validee et ouvertes aux inscriptions!',
                'rejetee' => 'Rejetee'
            ];
            
            $statusMessages = [
                'accepte' => "Votre proposition a ete acceptee! Les administrateurs vont maintenant creer la sortie officielle.",
                'en_preparation' => "La sortie est en cours de preparation. Nous affinerons les details.",
                'validee' => "Super! Votre sortie a ete validee et les membres peuvent maintenant s inscrire!",
                'rejetee' => "Malheureusement, votre proposition n a pas ete retenue. "
            ];
            
            $statusLabel = $statusLabels[$newStatus] ?? $newStatus;
            $message = $statusMessages[$newStatus] ?? '';
            
            $subject = "Sortie proposee: Statut change a '" . htmlspecialchars($statusLabel) . "'";
            
            $body = "Bonjour " . htmlspecialchars($proposal['prenom']) . ",\n\n"
                . "Le statut de votre proposition a change:\n\n"
                . "NOUVEAU STATUT: " . htmlspecialchars($statusLabel) . "\n"
                . ($message ? "\n" . $message . "\n" : "")
                . ($adminNotes ? "\nNotes: " . htmlspecialchars($adminNotes) . "\n" : "")
                . "\nConsulter: " . $this->getProposalLink($proposalId) . "\n\n"
                . "---\nClub ULM Evasion\n" . date('d/m/Y H:i');
            
            return gestnav_send_mail($this->pdo, $proposal['email'], $subject, $body);
            
        } catch (Exception $e) {
            error_log("Error notifying status change: " . $e->getMessage());
            return false;
        }
    }
    
    private function getAdminLink($proposalId) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http';
        return "{$protocol}://{$host}/sortie_proposals_admin.php?id={$proposalId}";
    }
    
    private function getProposalLink($proposalId) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http';
        return "{$protocol}://{$host}/sortie_proposal_detail.php?id={$proposalId}";
    }
}
?>
