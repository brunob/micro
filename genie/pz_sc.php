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
	if ($items_list = sql_select('*', 'spip_zitems', "doi = '' and augmented = 'non' and id_parent = '0'", '', 'date_ajout', '0,'.intval($nb_items+1))) {
		while ($nb_items-- and $zitem = sql_fetch($items_list)) {
			spip_log('traitement cron du zitem '. $zitem['id_zitem'], 'pz');
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
	$zitem = sql_fetsel('affiliation, lat, lon', 'spip_zitems', 'id_zitem='.sql_quote($id_zitem));
	// localiser la structure (université, etc) en utilisant plusieurs APIs dans l'ordre wikidata, nominatim & photon (OSM)
	// process wikidata pompé sur PUMA https://github.com/OllyButters/puma/blob/master/source/add/geocode.py#L79
	$location = pz_locate_wikidata($zitem['affiliation'], $location, 1);

	if (!isset($location['lat']) or $location['source'] == 2) {
		$location = pz_locate_osm($zitem['affiliation'], $location, 3);
		if (!isset($location['lat'])) {
			$location = pz_locate_photon($zitem['affiliation'], $location, 3);
		}
	}

	if (!isset($location['lat'])) {
		$name = rtrim(preg_replace('/ \(.*\)/', '', $zitem['affiliation']));
		$location = pz_locate_wikidata($name, $location, 4);

		if (!isset($location['lat']) or $location['source'] == 5) {
			$location = pz_locate_osm($name, $location, 6);
			if (!isset($location['lat'])) {
				$location = pz_locate_photon($name, $location, 6);
			}
		}

		if (!isset($location['lat'])) {
			preg_match('/\((.*)\)/', $zitem['affiliation'], $regs);
			$location = pz_locate_osm($regs[1], $location, 7);
			if (!isset($location['lat'])) {
				$location = pz_locate_photon($regs[1], $location, 6);
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
				'augmented'     => 'oui'
			),
			'id_zitem='. sql_quote($id_zitem)
		);
		return true;
	} else {
		return false;
	}
}
