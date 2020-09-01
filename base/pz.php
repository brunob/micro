<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function pz_declarer_champs_extras($champs = array()){
	// options de base
	$options = array(
		'obligatoire' => false,
		'rechercher' => false,
		'sql' => "text DEFAULT '' NOT NULL",
		'traitements' => '_TRAITEMENT_TYPO',
	);

	$champs['spip_zitems']['augmented'] = array(
		'saisie' => 'input',
		'options' => array_merge($options,array(
			'nom' => 'augmented',
			'label' => 'augmented',
			'sql' => 'varchar(3) DEFAULT "non"'
		))
	);
	$champs['spip_zitems']['crossref_data'] = array(
		'saisie' => 'textarea',
		'options' => array_merge($options,array(
			'nom' => 'crossref_data',
			'label' => 'crossref_data',
			'sql' => 'mediumtext DEFAULT "" NOT NULL'
		))
	);
	$champs['spip_zitems']['scopus_data'] = array(
		'saisie' => 'textarea',
		'options' => array_merge($options,array(
			'nom' => 'scopus_data',
			'label' => 'scopus_data',
			'sql' => 'mediumtext DEFAULT "" NOT NULL'
		))
	);
	$champs['spip_zitems']['institute'] = array(
		'saisie' => 'input',
		'options' => array_merge($options,array(
			'nom' => 'institute',
			'label' => 'institute',
			'traitements' => '_TRAITEMENT_TYPO'
		))
	);
	$champs['spip_zitems']['city'] = array(
		'saisie' => 'input',
		'options' => array_merge($options,array(
			'nom' => 'city',
			'label' => 'city',
			'traitements' => '_TRAITEMENT_TYPO'
		))
	);
	$champs['spip_zitems']['country'] = array(
		'saisie' => 'input',
		'options' => array_merge($options,array(
			'nom' => 'country',
			'label' => 'country',
			'traitements' => '_TRAITEMENT_TYPO'
		))
	);
	$champs['spip_zitems']['location_source'] = array(
		'saisie' => 'input',
		'options' => array_merge($options,array(
			'nom' => 'location_source',
			'label' => 'location_source',
			'sql' => 'tinyint(1) DEFAULT 0 NOT NULL'
		))
	);
	$champs['spip_zitems']['lat'] = array(
		'saisie' => 'input',
		'options' => array_merge($options,array(
			'nom' => 'lat',
			'label' => 'lat',
			'sql' => 'double NULL NULL',
		))
	);
	$champs['spip_zitems']['lon'] = array(
		'saisie' => 'input',
		'options' => array_merge($options,array(
			'nom' => 'lon',
			'label' => 'lon',
			'sql' => 'double NULL NULL',
		))
	);

	return $champs;
}
