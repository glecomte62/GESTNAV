# Workflows opérationnels

## Sorties
- Création/édition: via `sortie_edit.php` (GET `id` pour édition). Machines liées via `sortie_machines`.
- Inscriptions: `sorties.php` et `sortie_detail.php` listent les inscrits. Confirmation par membres.
- Pré-inscription: `preinscription_sortie.php` (machine/coéquipier/notes). Affichage lecture seule côté admin.
- Affectations: réalisées sur `sortie_detail.php` (admin). Validation déclenche emails et mise à jour des priorités.
- Priorité: après validation, membres inscrits non affectés deviennent prioritaires (badge rouge). La priorité est retirée quand un membre est affecté.
- Changement machine/coéquipier: via actions signées par `action_token`.

## Événements
- CRUD: `evenement_edit.php`, liste `evenements_list.php`.
- Invitations: `evenements_admin.php` envoie des emails aux membres.
- Inscriptions/annulations: `evenement_inscription_detail.php`, avec notifications admin.

## Emails
- Validation des affectations: envoie les confirmations et les notifications aux non-affectés.
- Annulations/suppressions: pas d’envois automatiques.
- Utiliser `mail_helper.php` et gabarits HTML simples.

## Déploiement
- Script principal: `tools/deploy_ftp.sh` (push sélectif de fichiers modifiés).
- Assets Leaflet: inclus localement (`assets/leaflet/...`).
- Vérifier `CHANGELOG.md` et `GESTNAV_VERSION` avant déploiement.

## Badges et UI
- `PRIORITAIRE`: badge rouge avec tooltip; légende sur page détail des sorties.
- États inscriptions/assignations: badges colorés dans listes.
