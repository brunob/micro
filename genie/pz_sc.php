<?php

if (!defined('_ECRIRE_INC_VERSION')) return;

include_spip('inc/distant');

/**
 * Cron de mise à jour des zitems
 *
 * @param int $t Date de dernier passage
 * @return int
 **/
function genie_pz_sc_dist($t) {
	// nombre de zitems traite par iteration
	$nb_items = _PZ_GENIE_NB_ITEMS;
	if ($items_list = sql_select('*', 'spip_zitems', "doi = '' and extras like '%tex.affiliation%' and augmented = 'non' and id_parent = '0'", '', 'date_ajout DESC', '0,'.intval($nb_items+1))) {
		while ($nb_items-- and $zitem = sql_fetch($items_list)) {
			spip_log('traitement cron sc du zitem '. $zitem['id_zitem'], 'pz');
			pz_locate_sc($zitem['id_zitem']);
		}
		if ($row = sql_fetch($items_list)) {
			return 0-$t; // il y a encore des zitems à traiter
		}
	}
	return 0;
}

/**
 * Localiser un zitem à partir de son extra affiliation depuis les APIs wikidata & OSM
 *
 * @param int $id_zitem
 * @return bool true si localisé, false sinon
 **/
function pz_locate_sc($id_zitem) {
	include_spip('pz_fonctions');

	$zitem = sql_fetsel('extras, lat, lon', 'spip_zitems', 'id_zitem='.sql_quote($id_zitem));
	$location = array();

	// récupérer l'affiliation depuis les extras, sinon ne rien faire (ça ne devrait pas arriver cf la requêtes sql dans genie_pz_sc_dist)
	if (preg_match('/^tex.affiliation: (.*)\n/m', $zitem['extras'], $matches)) {
		$affiliation = $matches[1];
	} else {
		return false;
	}

	// localiser la structure (université, etc) en utilisant plusieurs APIs dans l'ordre wikidata, nominatim & photon (OSM)
	// process wikidata pompé sur PUMA https://github.com/OllyButters/puma/blob/master/source/add/geocode.py#L79
	$location = pz_locate_wikidata($affiliation, $location, 1, $id_zitem);

	if (!isset($location['lat']) or $location['source'] == 2) {
		$location = pz_locate_osm($affiliation, $location, 3, $id_zitem);
		if (!isset($location['lat'])) {
			$location = pz_locate_photon($affiliation, $location, 3, $id_zitem);
		}
	}

	if (!isset($location['lat'])) {
		$name = rtrim(preg_replace('/ \(.*\)/', '', $affiliation));
		$location = pz_locate_wikidata($name, $location, 4, $id_zitem);

		if (!isset($location['lat']) or $location['source'] == 5) {
			$location = pz_locate_osm($name, $location, 6, $id_zitem);
			if (!isset($location['lat'])) {
				$location = pz_locate_photon($name, $location, 6, $id_zitem);
			}
		}

		if (!isset($location['lat'])) {
			preg_match('/\((.*)\)/', $affiliation, $regs);
			$location = pz_locate_osm($regs[1], $location, 7, $id_zitem);
			if (!isset($location['lat'])) {
				$location = pz_locate_photon($regs[1], $location, 6, $id_zitem);
			}
		}
	}

	if ($location['lat'] and ($location['lat'] != $zitem['lat'] and $location['lon'] != $zitem['lon'])) {
		sql_updateq('spip_zitems',
			array(
				'location_source' => $location['source'],
				'lat' => $location['lat'],
				'lon' => $location['lon'],
				'city'          => $location['city'],
				'country'       => $location['country'],
				'institute'     => $affiliation,
				'augmented'     => 'oui'
			),
			'id_zitem='. sql_quote($id_zitem)
		);
		return true;
	} else {
		return false;
	}
}
