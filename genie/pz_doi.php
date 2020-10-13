<?php

if (!defined('_ECRIRE_INC_VERSION')) return;

include_spip('inc/distant');

/**
 * Cron de mise à jour des zitems
 *
 * @param int $t Date de dernier passage
 * @return int
 **/
function genie_pz_doi_dist($t) {
	// nombre de zitems traite par iteration
	$nb_items = _PZ_GENIE_NB_ITEMS;
	if ($items_list = sql_select('*', 'spip_zitems', "doi != '' and augmented = 'non' and id_parent = '0'", '', 'date_ajout', '0,'.intval($nb_items+1))) {
		while ($nb_items-- and $zitem = sql_fetch($items_list)) {
			spip_log('traitement cron du zitem '. $zitem['id_zitem'], 'pz');
			if (pz_get_metadata($zitem['id_zitem'])) {
				pz_locate_doi($zitem['id_zitem']);
			}
		}
		if ($row = sql_fetch($items_list)) {
			return 0-$t; // il y a encore des zitems à traiter
		}
	}
	return 0;
}


/**
 * Mettre à jour les informations détaillées d'un zitem depuis les APIs crossref & scopus
 *
 * @param int $id_zitem
 * @return string/bool le nom de l'institution, false si aucun résultat
 **/
function pz_get_metadata($id_zitem) {
	if ($doi = sql_getfetsel('doi', 'spip_zitems', 'id_zitem=' . sql_quote($id_zitem))) {
		$candidates = array();

		// récupérer l'affiliation du premier auteur de la publication en utilisant plusieurs APIs dans l'ordre DOI, scopus (zotero dans les notes ?)
		$email = $GLOBALS['meta']['email_webmaster'];
		$doi_endpoint = 'https://api.crossref.org/works/';
		$doi_url = $doi_endpoint . $doi . '?mailto=' . $email;
		// peut-être utiliser copie_locale() ?
		$doi_res = recuperer_url_cache($doi_url);
		if ($doi_res['status'] == 200 and $doi_res['length']) {
			spip_log('récupération des données crossref pour le zitem ' . $id_zitem, 'pz');
			$doi_data = json_decode($doi_res['page'], true);
			if (isset($doi_data['message']['author'][0]['affiliation'][0]['name']) and strlen($doi_data['message']['author'][0]['affiliation'][0]['name']) > 0) {
				// données par très révélante d'après mes test, l'adresse est souvent bien trop complète pour permettre un geocoding correct
				// mais ça peut servir de fallback si scopus n'a rien en stock
				$candidates['name'] = $doi_data['message']['author'][0]['affiliation'][0]['name'];
			}
		} else {
			spip_log('erreur lors de la récupération des données crossref pour le zitem ' . $id_zitem, 'pz');
		}

		$scopus_endpoint = 'https://api.elsevier.com/content/search/scopus';
		$scopus_url = $scopus_endpoint . '?apiKey=' . _PZ_SCOPUS_KEY . '&httpAccept=application/json&query=DOI(' . $doi . ')';
		$scopus_res = recuperer_url_cache($scopus_url);
		if ($scopus_res['status'] == 200 and $scopus_res['length']) {
			spip_log('récupération des données scopus pour le zitem ' . $id_zitem, 'pz');
			$scopus_data = json_decode($scopus_res['page'], true);
			if (isset($scopus_data['search-results']['entry'][0]['affiliation'][0]['affilname']) and strlen($scopus_data['search-results']['entry'][0]['affiliation'][0]['affilname']) > 0) {
				$candidates['name'] = $scopus_data['search-results']['entry'][0]['affiliation'][0]['affilname'];
				$candidates['city'] = $scopus_data['search-results']['entry'][0]['affiliation'][0]['affiliation-city'];
				$candidates['country'] = $scopus_data['search-results']['entry'][0]['affiliation'][0]['affiliation-country'];
			}
		} else {
			spip_log('erreur lors de la récupération des données scopus pour le zitem ' . $id_zitem, 'pz');
		}

		// stocker les données renvoyées par les APIs
		sql_updateq(
			'spip_zitems',
			array(
				'crossref_data' => $doi_res['page'],
				'scopus_data'   => $scopus_res['page'],
				'institute'     => $candidates['name'],
				'city'          => $candidates['city'],
				'country'       => $candidates['country'],
				'augmented'     => 'oui'
			),
			'id_zitem=' . sql_quote($id_zitem)
		);

		return $candidates['name'] ?? false;
	} else {
		return false;
	}
}

/**
 * Localiser un zitem à partir de son DOI depuis les APIs wikidata & nominatim
 *
 * @param int $id_zitem
 * @return bool true si localisé, false sinon
 **/
function pz_locate_doi($id_zitem) {
	include_spip('pz_fonctions');
	$zitem = sql_fetsel('institute, city, country, lat, lon', 'spip_zitems', 'id_zitem='.sql_quote($id_zitem));
	// localiser la structure (université, etc) en utilisant plusieurs APIs dans l'ordre wikidata, nominatim (OSM) (geonames en plus ?)
	// process wikidata pompé sur PUMA https://github.com/OllyButters/puma/blob/master/source/add/geocode.py#L79
	$location = pz_locate_wikidata($zitem['institute'], $location, 1);

	if (!isset($location['lat']) or $location['source'] == 2) {
		$nominatim_query = $zitem['institute'];
		if (isset($zitem['city'])) {
			$nominatim_query .= ', ' . $zitem['city'];
		}
		if (isset($zitem['country'])) {
			$nominatim_query .= ', ' . $zitem['country'];
		}

		$location = pz_locate_osm($nominatim_query, $location, 3);
		
		if (!isset($location['lat']) and isset($zitem['city']) and isset($zitem['country'])) {
			// plan B, si l'adresse n'est pas trouvé, chercher simplement sur la ville
			$nominatim_query = $zitem['city'] . ', ' . $zitem['country'];
			$location = pz_locate_osm($nominatim_query, $location, 4);
		}
	}

	if ($location['lat'] and ($location['lat'] != $zitem['lat'] and $location['lon'] != $zitem['lon'])) {
		sql_updateq('spip_zitems',
			array(
				'location_source' => $location['source'],
				'lat' => $location['lat'],
				'lon' => $location['lon'],
			),
			'id_zitem='. sql_quote($id_zitem)
		);
		return true;
	} else {
		return false;
	}
}
