<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

/**
 * Tâche périodique de récupération des données depuis les différentes APIs
 *
 * @param array $taches_generales
 * @return array
 */
function pz_taches_generales_cron($taches_generales) {
	//$taches_generales['pz_doi'] = 60*60;
	$taches_generales['pz_sc'] = 60*60;
	return $taches_generales;
}

/**
 * Insertion dans le pipeline gis_modele_parametres_autorises (GIS)
 * 
 * Permettre de passer le paramètre type_auteur au modèle GIS
 * 
 * @param array $flux Le contexte d'environnement du pipeline
 * @return array $flux Le contexte d'environnement modifié
 */
function pz_gis_modele_parametres_autorises($flux){
	$flux[] = 'collection';
	return $flux;
}

/**
 * Insertion dans le pipeline pre_boucle (SPIP)
 * 
 * Forcer le critère {tout} sur les boucles rubriques
 * 
 * @param Boucle $boucle Description de la boucle
 * @return Boucle $boucle Description de la boucle
 */
function pz_pre_boucle($boucle){
	if ($boucle->type_requete == 'rubriques' AND !isset($boucle->modificateur['criteres']['statut']))
		$boucle->modificateur['criteres']['statut'] = true;
	return $boucle;
}