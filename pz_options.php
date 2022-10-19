<?php 

if (!defined('_ECRIRE_INC_VERSION')) return;

## valeurs modifiables dans mes_options
if (!defined('_PZ_GENIE_NB_ITEMS')) {
	define('_PZ_GENIE_NB_ITEMS', 50);
}

## ID de la collection plastic_pollution_papers
if (!defined('_PZ_ID_PPP')) {
	define('_PZ_ID_PPP', 'XI85GUTQ');
}

## ID de la collection pour l'édition en cours
if (!defined('_PZ_ID_CURRENT')) {
	define('_PZ_ID_CURRENT', 'HT23N3EM');
}

define('_DELAI_RECUPERER_URL_CACHE', 3600 * 30);

$GLOBALS['z_blocs'] = array(
	'content',
	'extra',
	'head',
	'head_js',
	'header',
	'footer',
	'breadcrumb',
);

/**
 * Surcharge de zotspip pour le forcer à travailler sur une seule collection
 *
 * @param object $t
 * @return int
 *   le texte de l'affiliation
 *   false si pas d'affiliation
 */
function genie_maj_zotspip($t) {
	include_spip('inc/zotspip');
	return zotspip_maj(false, _PZ_ID_CURRENT);
}