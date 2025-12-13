# Checklists opérationnelles

## Mise en production
- [ ] Mettre à jour `CHANGELOG.md` et `GESTNAV_VERSION`/`GESTNAV_BUILD_DATE` dans `config.php`.
- [ ] Vérifier les pages modifiées (sorties, détail, événements, admin).
- [ ] Tester Leaflet (carte + icônes locales) et fallback OSM.
- [ ] Lancer le déploiement: `tools/deploy_ftp.sh`.
- [ ] Valider affichage version en footer.

## Validation des affectations (sorties)
- [ ] Vérifier les inscriptions et pré-inscriptions (machines/coéquipier).
- [ ] Assigner équipages et machines; gérer exclusions si nécessaire.
- [ ] Valider les affectations (déclenche emails de confirmation).
- [ ] Contrôler la notification aux non‑affectés et l’activation des priorités.
- [ ] Regarder la liste des inscrits: badges et légende "PRIORITAIRE" corrects.

## Audit des données
- [ ] Tables clés présentes: `sortie_*`, `machines_*`, `evenements_*`, logs.
- [ ] Colonnes attendues détectées via `SHOW TABLES/COLUMNS` quand applicable.
- [ ] Tokens d’action présents pour liens sensibles (annulation/changement).
- [ ] Aéronefs propriétaires correctement auto‑associés (hors exclusions).
- [ ] Emails configurés et testés via `test_mail.php` si besoin.
