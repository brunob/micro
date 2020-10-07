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
					$bib .= "\t$key = { $val },\n";
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
