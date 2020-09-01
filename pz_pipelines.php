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