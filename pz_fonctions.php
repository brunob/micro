<?php

if (!defined('_ECRIRE_INC_VERSION')) return;

/**
 * Renvoie les infos géographiques de l'institution liée à une publication
 *
 * @param string $id_zitem ID zotero de la publication
 * @return array|bool
 *   false si echec
 *   array sinon :
 *     float lat : latitude
 *     float lon : longitude
 *     string country : pays
 *     string city : ville
 *     int source : source de données du résultat
 *       1 : wikidata main town
 *       2 : wikidata head quarter
 *       3 : nominatim institute
 *       4 : nominatim city
 *     array doi données renvoyées par l'API DOI
 *     array scopus données renvoyées par l'API scopus 
 **/
function pz_locate_zitem($id_zitem) {
	$zitem = sql_fetsel('*', 'spip_zitems', 'id_zitem='.sql_quote($id_zitem));
	$candidates = array();
	$location = array();

	// récupérer l'affiliation du premier auteur de la publication en utilisant plusieurs APIs dans l'ordre DOI, scopus (zotero dans les notes ?)
	$email = $GLOBALS['meta']['email_webmaster'];
	$doi_endpoint = 'https://api.crossref.org/works/';
	$doi_url = $doi_endpoint . $zitem['doi'] . '?mailto=' . $email;
	// peut-être utiliser copie_locale() ?
	$doi_res = recuperer_url_cache($doi_url);
	if ($doi_res['status'] == 200 and $doi_res['length']) {
		$location['doi'] = $doi_data = json_decode($doi_res['page'], true);
		if (isset($doi_data['message']['author'][0]['affiliation'][0]['name']) and strlen($doi_data['message']['author'][0]['affiliation'][0]['name']) > 0) {
			// données par très révélante d'après mes test, l'adresse est souvent bien trop complète pour permettre un geocoding correct
			// mais ça peut servir de fallback si scopus n'a rien en stock
			$candidates['name'] = $doi_data['message']['author'][0]['affiliation'][0]['name'];
		}
	}

	$scopus_endpoint = 'https://api.elsevier.com/content/search/scopus';
	$scopus_url = $scopus_endpoint . '?apiKey=' . _PZ_SCOPUS_KEY . '&httpAccept=application/json&query=DOI(' . $zitem['doi'] . ')';
	$scopus_res = recuperer_url_cache($scopus_url);
	if ($scopus_res['status'] == 200 and $scopus_res['length']) {
		$location['scopus'] = $scopus_data = json_decode($scopus_res['page'], true);
		if (isset($scopus_data['search-results']['entry'][0]['affiliation'][0]['affilname']) and strlen($scopus_data['search-results']['entry'][0]['affiliation'][0]['affilname']) > 0) {
			$candidates['name'] = $scopus_data['search-results']['entry'][0]['affiliation'][0]['affilname'];
			$candidates['city'] = $scopus_data['search-results']['entry'][0]['affiliation'][0]['affiliation-city'];
			$candidates['country'] = $scopus_data['search-results']['entry'][0]['affiliation'][0]['affiliation-country'];
		}
	}

	// localiser la structure (université, etc) en utilisant plusieurs APIs dans l'ordre wikidata, nominatim (OSM) (geonames en plus ?)
	// process wikidata pompé sur PUMA https://github.com/OllyButters/puma/blob/master/source/add/geocode.py#L79
	$location = pz_locate_wikidata($candidates['name'], $location, 1);

	if (!isset($location['lat']) or $location['source'] == '2') {
		$nominatim_query = $candidates['name'];
		if (isset($candidates['city'])) {
			$nominatim_query .= ', ' . $candidates['city'];
		}
		if (isset($candidates['country'])) {
			$nominatim_query .= ', ' . $candidates['country'];
		}

		$location = pz_locate_osm($nominatim_query, $location, 3);

		if (!isset($location['lat']) and isset($candidates['city']) and isset($candidates['country'])) {
			// plan B, si l'adresse n'est pas trouvé, chercher simplement sur la ville
			$nominatim_query = $candidates['city'] . ', ' . $candidates['country'];
			$location = pz_locate_osm($nominatim_query, $location, 4);
		}
	}

	if ($location['lat']) {
		return $location;
	} else {
		return false;
	}
}

/**
 * Renvoie les infos géographiques d'une institution
 *
 * @param string $affiliation adresse de l'institution
 * @return array|bool
 *   false si echec
 *   array sinon :
 *     float lat : latitude
 *     float lon : longitude
 *     string country : pays
 *     string city : ville
 *     int source : source de données du résultat
 *       1 : wikidata main town
 *       2 : wikidata head quarter
 *       3 : nominatim institute
 *       4 : wikidata main town short
 *       5 : wikidata head quarter short
 *       6 : nominatim institute short
 *       7 : nominatim address
 *       8 : manual
 **/
function pz_locate_affiliation($affiliation) {
	$location = array();

	$location = pz_locate_wikidata($affiliation, $location, 1);

	if (!isset($location['lat']) or $location['source'] == 2) {
		$location = pz_locate_osm($affiliation, $location, 3);
		if (!isset($location['lat'])) {
			$location = pz_locate_photon($affiliation, $location, 3);
		}
	}

	if (!isset($location['lat'])) {
		$name = rtrim(preg_replace('/ \(.*\)/', '', $affiliation));
		$location = pz_locate_wikidata($name, $location, 4);

		if (!isset($location['lat']) or $location['source'] == 5) {
			$location = pz_locate_osm($name, $location, 6);
			if (!isset($location['lat'])) {
				$location = pz_locate_photon($name, $location, 6);
			}
		}

		if (!isset($location['lat'])) {
			preg_match('/\((.*)\)/', $affiliation, $regs);
			$location = pz_locate_osm($regs[1], $location, 7);
			if (!isset($location['lat'])) {
				$location = pz_locate_photon($regs[1], $location, 6);
			}
		}
	}

	if ($location['lat']) {
		return $location;
	} else {
		return false;
	}
}


/**
 * Localise un élément en utilisant l'API wikidata
 *
 * @param string $search
 * @param array $location
 * @param int $precision
 * @param integer $id_zitem
 * @return array
 *     float lat : latitude
 *     float lon : longitude
 *     string country : pays
 *     string city : ville
 *     int source : source de données du résultat
 */
function pz_locate_wikidata($search, $location, $precision, $id_zitem = 0) {
	$endpoint = 'https://query.wikidata.org/sparql?format=json';
	$query = '
	SELECT ?item ?itemLabel ?country ?countryLabel ?mainTown ?mainTownLabel ?mainLon ?mainLat ?hqTownLabel ?hqLon ?hqLat
	WHERE {
		?item rdfs:label "' . $search . '"@en.
		?item wdt:P17 ?country
		OPTIONAL
		{
			?item wdt:P131 ?mainTown
		}
		OPTIONAL
		{
			?item p:P625 ?mainLocation.
			?mainLocation psv:P625 ?mainCoordinateNode.
			?mainCoordinateNode wikibase:geoLongitude ?mainLon.
			?mainCoordinateNode wikibase:geoLatitude ?mainLat.
		}
		OPTIONAL
		{
			?item wdt:P159 ?hqTown.
		}
		OPTIONAL
		{
			?item wdt:P159 ?hq2.
			?hq2 p:P625 ?hq3.
			?hq3 psv:P625 ?hq4.
			?hq4 wikibase:geoLongitude ?hqLon.
			?hq4 wikibase:geoLatitude ?hqLat.
		}
		SERVICE wikibase:label { bd:serviceParam wikibase:language "en". }
	}';
	$url = parametre_url($endpoint, 'query', $query, '&');
	$res = recuperer_url_cache($url);
	if ($res['status'] == 200 and $res['length']) {
		spip_log('récupération des données wikidata pour le zitem '. $id_zitem, 'pz');
		$data = json_decode($res['page'], true);
		if (isset($data['results']['bindings'][0]['countryLabel']['value'])) {
			$location['country'] = $data['results']['bindings'][0]['countryLabel']['value'];
		}
		if (isset($data['results']['bindings'][0]['mainTownLabel']['value'])) {
			$location['city'] = $data['results']['bindings'][0]['mainTownLabel']['value'];
			$location['lat'] = $data['results']['bindings'][0]['mainLat']['value'];
			$location['lon'] = $data['results']['bindings'][0]['mainLon']['value'];
			$location['source'] = $precision;
		} elseif (isset($data['results']['bindings'][0]['hqTownLabel']['value'])) {
			$location['city'] = $data['results']['bindings'][0]['hqTownLabel']['value'];
			$location['lat'] = $data['results']['bindings'][0]['hqLat']['value'];
			$location['lon'] = $data['results']['bindings'][0]['hqLon']['value'];
			$location['source'] = $precision + 1;
		}
	} else {
		spip_log('erreur lors de la récupération des données wikidata pour le zitem '. $id_zitem, 'pz');
	}
	return $location;
}

/**
 * Localise un élément en utilisant l'API nominatim
 *
 * @param string $search
 * @param array $location
 * @param int $precision
 * @param integer $id_zitem
 * @return array
 *     float lat : latitude
 *     float lon : longitude
 *     string country : pays
 *     string city : ville
 *     int source : source de données du résultat
 */
function pz_locate_osm($search, $location, $precision, $id_zitem = 0) {
	$endpoint = 'https://nominatim.openstreetmap.org/search/?format=json&addressdetails=1&limit=1';
	$url = parametre_url($endpoint, 'q', $search, '&');
	$res = recuperer_url_cache($url);
	if ($res['status'] == 200 and $res['length']) {
		spip_log('récupération des données nominatim pour le zitem '. $id_zitem . ' précision ' . $precision, 'pz');
		$data = json_decode($res['page'], true);
		if ($data[0]['lat']) {
			$location['country'] = $data[0]['address']['country'];
			$location['city'] = $data[0]['address']['city'];
			$location['lat'] = $data[0]['lat'];
			$location['lon'] = $data[0]['lon'];
			$location['source'] = $precision;
		}
	} else {
		spip_log('erreur lors de la récupération des données nominatim pour le zitem '. $id_zitem . ' précision ' . $precision, 'pz');
	}
	return $location;
}

/**
 * Localise un élément en utilisant l'API photon
 *
 * @param string $search
 * @param array $location
 * @param int $precision
 * @param integer $id_zitem
 * @return array
 *     float lat : latitude
 *     float lon : longitude
 *     string country : pays
 *     string city : ville
 *     int source : source de données du résultat
 */
function pz_locate_photon($search, $location, $precision, $id_zitem = 0) {
	$endpoint = 'https://photon.komoot.io/api/?limit=1';
	$url = parametre_url($endpoint, 'q', $search, '&');
	$res = recuperer_url_cache($url);
	if ($res['status'] == 200 and $res['length']) {
		spip_log('récupération des données photon pour le zitem '. $id_zitem . ' précision ' . $precision, 'pz');
		$data = json_decode($res['page'], true);
		if ($data['features'][0]['geometry']['coordinates'][0]) {
			$location['country'] = $data['features'][0]['properties']['country'];
			$location['city'] = $data['features'][0]['properties']['city'];
			$location['lat'] = $data['features'][0]['geometry']['coordinates'][1];
			$location['lon'] = $data['features'][0]['geometry']['coordinates'][0];
			$location['source'] = $precision;
		}
	} else {
		spip_log('erreur lors de la récupération des données photon pour le zitem '. $id_zitem . ' précision ' . $precision, 'pz');
	}
	return $location;
}

/**
 * Extrait une valeur depuis le champ extras d'un zitem
 *
 * @param string $text
 * @param string $extra
 * @return string|bool
 *   le texte de l'extra
 *   false si pas d'extra de ce nom
 */
function pz_extra($text, $extra) {
	if (preg_match('/^'. $extra .': (.*)\n?/m', $text, $matches)) {
		return $matches[1];
	} else {
		return false;
	}
}

/**
 * Surligne des mots dans un texte
 *
 * @param string $text
 * @param string $search
 * @return string|bool
 *   le texte de l'affiliation
 *   false si pas d'affiliation
 */
function pz_surligner($text, $search) {
	if (strlen($search)) {
		$text = preg_replace('@('.$search.')@u', '<span class="spip_surligne">${1}</span>', $text);
	}
	return $text;
}
