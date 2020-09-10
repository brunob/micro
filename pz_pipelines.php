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
	$taches_generales['pz'] = 60*60;
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
function resa_gis_modele_parametres_autorises($flux){
	$flux[] = 'id_zcollection';
	return $flux;
}