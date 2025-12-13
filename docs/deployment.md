# Déploiement

## Pré-requis

## Procédure
1. Valider les changements en local.
2. Mettre à jour `CHANGELOG.md` et `config.php` (`GESTNAV_VERSION`).
3. Lancer:

```sh
zsh -lc "tools/deploy_ftp.sh"
```

4. Vérifier: pages modifiées OK, carte Leaflet fonctionne, assets chargés.

## Assets et dépendances

## Cartes VAC (SIA)

- Bouton VAC: la page `sortie_info.php` propose un bouton vers la recherche SIA (`https://www.sia.aviation-civile.gouv.fr/?q=<OACI>`), robuste face aux changements d’URLs.
- Lien PDF direct (optionnel): si la constante `GESTNAV_SIA_CYCLE_PATH` est définie dans `config.php` (ex: `eAIP_27_NOV_2025`), un lien direct est construit vers:
	- `https://www.sia.aviation-civile.gouv.fr/media/dvd/<GESTNAV_SIA_CYCLE_PATH>/Atlas-VAC/PDF_AIPparSSection/VAC/AD/AD-2.<OACI>.pdf`
- Mise à jour en cas de changement SIA:
	- Rechercher le nouveau nom du cycle eAIP (ex: `eAIP_15_JAN_2027`) sur le site SIA.
	- Mettre à jour `GESTNAV_SIA_CYCLE_PATH` soit via variable d’environnement (préféré), soit dans `config.php`.
		- Environnement: exporter `GESTNAV_SIA_CYCLE_PATH="eAIP_XX_MON_YYYY"` sur l’hébergement.
		- Fallback code: `config.php` utilise la valeur env si présente, sinon le défaut `eAIP_27_NOV_2025`.
	- Vérifier que le pattern d’URL reste identique; sinon, adapter la construction d’URL dans `sortie_info.php` (section carte, bouton VAC PDF).

## Cibles de déploiement

## Notes
