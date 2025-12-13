<?php
/**
 * Photo Cache Helper
 * Optimise le chargement des photos en cache local
 */

class PhotoCache {
    private static $cache = [];
    private static $uploads_dir = __DIR__ . '/../uploads/members';
    
    /**
     * Récupère le chemin optimisé d'une photo de membre
     * @param int $memberId
     * @param string $photoPath Chemin depuis la BD (optionnel)
     * @return string Chemin de la photo ou avatar par défaut
     */
    public static function getMemberPhotoPath($memberId, $photoPath = null) {
        $cacheKey = 'member_' . $memberId;
        
        // Retourner depuis le cache si disponible
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }
        
        // Si un chemin est fourni et le fichier existe, l'utiliser
        if (!empty($photoPath) && file_exists(__DIR__ . '/../' . $photoPath)) {
            self::$cache[$cacheKey] = $photoPath;
            return $photoPath;
        }
        
        // Chercher dans le dossier uploads/members/
        $extensions = ['webp', 'jpg', 'jpeg', 'png'];
        foreach ($extensions as $ext) {
            $filePath = self::$uploads_dir . '/member_' . $memberId . '.' . $ext;
            if (file_exists($filePath)) {
                $urlPath = '/uploads/members/member_' . $memberId . '.' . $ext;
                self::$cache[$cacheKey] = $urlPath;
                return $urlPath;
            }
        }
        
        // Avatar par défaut
        self::$cache[$cacheKey] = '/assets/img/avatar-placeholder.svg';
        return '/assets/img/avatar-placeholder.svg';
    }
    
    /**
     * Pré-charge les photos pour une liste de membres (optimisation)
     * @param array $memberIds
     */
    public static function preloadPhotos($memberIds) {
        foreach ($memberIds as $id) {
            if (!isset(self::$cache['member_' . $id])) {
                $extensions = ['webp', 'jpg', 'jpeg', 'png'];
                foreach ($extensions as $ext) {
                    $filePath = self::$uploads_dir . '/member_' . $id . '.' . $ext;
                    if (file_exists($filePath)) {
                        self::$cache['member_' . $id] = '/uploads/members/member_' . $id . '.' . $ext;
                        break;
                    }
                }
                if (!isset(self::$cache['member_' . $id])) {
                    self::$cache['member_' . $id] = '/assets/img/avatar-placeholder.svg';
                }
            }
        }
    }
    
    /**
     * Vide le cache
     */
    public static function clearCache() {
        self::$cache = [];
    }
}
?>
