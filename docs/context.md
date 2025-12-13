# Contexte du projet GESTNAV

## Objectifs
- Gérer les sorties ULM du club (création, édition, inscriptions, affectations).
- Gérer les machines (flotte club et propriétaires) et leurs liens aux membres.
- Gérer les événements club (non-vol), inscriptions, et communications.
- Centraliser les workflows opérationnels (emails, validations, priorités, promotions).

## Périmètre fonctionnel
- Sorties: création/édition, inscription membres, pré-inscription (préférences), affectations machines/équipages, photos, carte destination.
- Événements: CRUD, invitations, inscriptions, statistiques.
- Machines: gestion, propriétaires, exclusions temporaires pour une sortie.
- Déploiement: scripts FTP standardisés.

## Terminologie
- Sortie: activité de vol planifiée.
- Événement: activité club hors vol.
- Inscription: participation d’un membre à une sortie/événement.
- Affectation: attribution d’un membre à une machine pour une sortie.
- Prioritaire: membre inscrit aux deux sorties, non affecté lors de la première; badge affiché.
- Pré-inscription: préférences machine/coéquipier renseignées avant affectations.

## Pages clés
- `sorties.php`: liste des sorties, actions et badges.
- `sortie_detail.php`: détail d’une sortie, cartes, photos, inscriptions et affectations.
- `sortie_edit.php`: création/édition des sorties.
- `preinscription_sortie.php`: saisie des préférences.
- `inscriptions_admin.php`: gestion des inscriptions (admin).
- `machines.php`, `aerodromes_admin.php`: gestion des ressources.
- `evenements_*`: gestion et inscriptions aux événements.

## Données et tables importantes
- `sortie_*`: sorties, inscriptions, affectations, machines, photos, exclusions, pré-inscriptions, invités.
- `machines`, `machines_owners`: parc et propriétaires.
- `evenements`, `evenement_inscriptions`: événements et inscriptions.
- `sortie_priorites`: états de priorité des membres (badge et logique post-validation).
- Logs: `logs_connexions`, `logs_operations`.

## Règles clés
- Emails d’annulation/suppression: non envoyés; emails lors de validation affectations seulement.
- Priorité: activée pour inscrits non affectés; affichée sur détail et liste; tooltip explicatif.
- Auto-association: liaison machines propriétaires aux sorties avec exclusions.
