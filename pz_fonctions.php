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
 *     string source : source de données du résultat
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
	if ($doi_res) {
		$doi_data = json_decode($doi_res['page'], true);
		if (isset($doi_data['message']['author'][0]['affiliation']) and strlen($doi_data['message']['author'][0]['affiliation']) > 0) {
			$candidates['name'] = $doi_data['message']['author'][0]['affiliation'];
		}
	}

	$scopus_endpoint = 'https://api.elsevier.com/content/search/scopus';
	$scopus_url = $scopus_endpoint . '?apiKey=' . _PZ_SCOPUS_KEY . '&httpAccept=application/json&query=DOI(' . $zitem['doi'] . ')';
	$scopus_res = recuperer_url_cache($scopus_url);
	if ($scopus_res) {
		$scopus_data = json_decode($scopus_res['page'], true);
		if (isset($scopus_data['search-results']['entry'][0]['affiliation'][0]['affilname']) and strlen($scopus_data['search-results']['entry'][0]['affiliation'][0]['affilname']) > 0) {
			$candidates['name'] = $scopus_data['search-results']['entry'][0]['affiliation'][0]['affilname'];
			$candidates['city'] = $scopus_data['search-results']['entry'][0]['affiliation'][0]['affiliation-city'];
			$candidates['country'] = $scopus_data['search-results']['entry'][0]['affiliation'][0]['affiliation-country'];
		}
	}

	// localiser la structure (université, etc) en utilisant plusieurs APIs dans l'ordre wikidata, nominatim (OSM) (geonames en plus ?)
	// process wikidata pompé sur PUMA https://github.com/OllyButters/puma/blob/master/source/add/geocode.py#L79
	$wikidata_endpoint = 'https://query.wikidata.org/sparql?format=json';
	$wikidata_query = '
	SELECT ?item ?itemLabel ?country ?countryLabel ?mainTown ?mainTownLabel ?mainLon ?mainLat ?hqTownLabel ?hqLon ?hqLat
	WHERE {
		?item rdfs:label "' . $candidates['name'] . '"@en.
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
	$wikidata_url = parametre_url($wikidata_endpoint, 'query', $wikidata_query, '&');
	$wikidata_res = recuperer_url_cache($wikidata_url);
	if ($wikidata_res) {
		$wikidata_data = json_decode($wikidata_res['page'], true);
		if (isset($wikidata_data['results']['bindings'][0]['countryLabel']['value'])) {
			$location['country'] = $wikidata_data['results']['bindings'][0]['countryLabel']['value'];
		}
		if (isset($wikidata_data['results']['bindings'][0]['mainTownLabel']['value'])) {
			$location['city'] = $wikidata_data['results']['bindings'][0]['mainTownLabel']['value'];
			$location['lat'] = $wikidata_data['results']['bindings'][0]['mainLat']['value'];
			$location['lon'] = $wikidata_data['results']['bindings'][0]['mainLon']['value'];
			$location['source'] = 'wikidata main town';
		} elseif (isset($wikidata_data['results']['bindings'][0]['hqTownLabel']['value'])) {
			$location['city'] = $wikidata_data['results']['bindings'][0]['hqTownLabel']['value'];
			$location['lat'] = $wikidata_data['results']['bindings'][0]['hqLat']['value'];
			$location['lon'] = $wikidata_data['results']['bindings'][0]['hqLon']['value'];
			$location['source'] = 'wikidata head quarter';
		}
	}

	if (!isset($location['lat']) or $location['source'] == 'wikidata head quarter') {
		$nominatim_endpoint = 'https://nominatim.openstreetmap.org/search/?format=json&addressdetails=1&limit=1&q=';
		$nominatim_query = $candidates['name'];
		if (isset($candidates['city'])) {
			$nominatim_query .= ', ' . $candidates['city'];
		}
		if (isset($candidates['country'])) {
			$nominatim_query .= ', ' . $candidates['country'];
		}
		$nominatim_url = $nominatim_endpoint . urlencode($nominatim_query);
		$nominatim_res = recuperer_url_cache($nominatim_url);
		if ($nominatim_res) {
			$nominatim_data = json_decode($nominatim_res['page'], true);
			if ($nominatim_data[0]['lat']) {
				$location['country'] = $candidates['country'];
				$location['city'] = $candidates['city'];
				$location['lat'] = $nominatim_data[0]['lat'];
				$location['lon'] = $nominatim_data[0]['lon'];
				$location['source'] = 'nominatim institute';
			}
		}
		if (!isset($location['lat']) and isset($candidates['city']) and isset($candidates['country'])) {
			// plan B, si l'adresse n'est pas trouvé, chercher simplement sur la ville ?
			$nominatim_query = $candidates['city'] . ', ' . $candidates['country'];
			$nominatim_url = $nominatim_endpoint . urlencode($nominatim_query);
			$nominatim_res = recuperer_url_cache($nominatim_url);
			if ($nominatim_res) {
				$nominatim_data = json_decode($nominatim_res['page'], true);
				if ($nominatim_data[0]['lat']) {
					$location['lat'] = $nominatim_data[0]['lat'];
					$location['lon'] = $nominatim_data[0]['lon'];
					$location['source'] = 'nominatim city';
				}
			}
		}
	}

	if ($location['lat']) {
		return $location;
	} else {
		return false;
	}
}