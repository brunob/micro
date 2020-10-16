<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

include_spip('inc/actions');
include_spip('inc/editer');

function formulaires_locate_zitem_charger_dist($id_zitem, $retour = '') {
	$valeurs = array();
	$valeurs = sql_fetsel('id_zitem, lat, lon, extras','spip_zitems','id_zitem='. sql_quote($id_zitem));
	$valeurs['zoom'] = 9;
	$valeurs['editable'] = true;
	return $valeurs;
}

function formulaires_locate_zitem_verifier_dist($id_zitem, $retour = '') {
	$erreurs = array();
	if (!_request('lat')) {
		$erreurs['lat'] = _T('info_obligatoire');
	}
	if (!_request('lon')) {
		$erreurs['lon'] = _T('info_obligatoire');
	}
	return $erreurs;
}

function formulaires_locate_zitem_traiter_dist($id_zitem, $retour = '') {
	$res = array();
	sql_updateq('spip_zitems',
		array(
			'location_source' => 8,
			'lat' => _request('lat'),
			'lon' => _request('lon'),
		),
		'id_zitem='. sql_quote($id_zitem)
	);
	include_spip('inc/invalideur');
	suivre_invalideur(1);
	if ($retour) {
		$res['redirect'] = $retour;
	}
	return $res;
}
