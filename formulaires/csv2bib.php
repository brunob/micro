<?php

if (!defined('_ECRIRE_INC_VERSION')) {
	return;
}

function formulaires_csv2bib_charger_dist() {
	return array('bib' => '');
}

function formulaires_csv2bib_verifier_dist() {
	$erreurs = array();

	return $erreurs;
}

function formulaires_csv2bib_traiter_dist() {

	$importer_csv = charger_fonction("importer_csv", "inc");

	if (isset($_FILES) && isset($_FILES['fichier']) && !$_FILES['fichier']['error']) {
		$fichier = $_FILES['fichier']['tmp_name'];
		$csv = $importer_csv($fichier, true, ",", '"', null);
		if (is_array($csv) and count($csv) >= 1) {
			$bib = '';
			foreach ($csv as $item) {
				$bib .= "@conference{RefName,\n";
				foreach ($item as $key => $val) {
					if (in_array($key, array('chapter', 'abstract', 'title', 'keywords', 'speaker', 'author', 'affiliation', 'url', 'inproceedings', 'conference', 'series', 'booktitle', 'publisher', 'editor', 'address', 'month', 'year', 'pages', 'isbn', 'copyright', 'presentationType', 'lat', 'lon'))) {
						if (strlen($val) > 0) {
							// escape { " $ ref http://www.bibtex.org/SpecialSymbols/
							$bib .= "\t${key} = { " . addcslashes($val, '{"$') . " },\n";
						}
					}
				}
				$bib .= "}\n";
			}
		}
		set_request('bib', $bib);
	}

	return array(
		'message_ok' => _T('pz:csv_converted'),
		'editable'   => true,
	);
}
