<?php 

if (!defined('_ECRIRE_INC_VERSION')) return;

## valeurs modifiables dans mes_options
if (!defined('_PZ_GENIE_NB_ITEMS')) {
	define('_PZ_GENIE_NB_ITEMS', 50);
}

define('_DELAI_RECUPERER_URL_CACHE', 3600 * 30);
define('_PZ_SCOPUS_KEY', '63e059beee265eda0bdbafe5df5606ef');

$GLOBALS['z_blocs'] = array(
	'content',
	'extra',
	'head',
	'head_js',
	'header',
	'footer',
	'breadcrumb',
);
