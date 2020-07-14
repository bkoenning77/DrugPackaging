<?php
	$fh = fopen("/home/bk/Documents/DrugPackaging/InputFiles/product.txt", "r") or die("file could not be opened");
	$encoding = "UTF-8";
	$str0 = fgets($fh);
	$keystrings = explode("\t", $str0);
	$initial_date_match = "/\d{6}/";
	$final_string = "";
	$pharm_class_regexes_abbrevs = array(
		'MoA' => ["/\\[MoA\\]/", "[MoA]"],
		'CS' => ["/\\[CS\\]/", "[CS]"],
		'PE' => ["/\\[PE\\]/", "[PE]"],
		'EPC' => ["/\\[EPC\\]/", "[EPC]"],
		'Chemical/Ingredient' => ["/\\[Chemical\/Ingredient\\]/", "[Chemical/Ingredient]"]
	);

	$micro_changes = array(
		'ug' => ["/ug/","\u{00B5}g"],
		'umol' => ["/umol/", "\u{00B5}mol"]
	);

	$dates_to_change = ['STARTMARKETINGDATE', 'ENDMARKETINGDATE', 'LISTING_RECORD_CERTIFIED_THROUGH'];

	$final_regexes = array( 
		'STARTMARKETINGDATE' => "/\d{4}-\d{2}-\d{2}/",
		'ENDMARKETINGDATE' => "/\d{4}-\d{2}-\d{2}/",
		'PRODUCTNDC' => "/\d{5}-\d{4}/",
		'LISTING_RECORD_CERTIFIED_THROUGH' => "/\d{4}-\d{2}-\d{2}/"
	);
	$pharm_classes_array = array(
		'PE' => array(), 'MoA' => array(), 'CS' => array(), 'EPC' => array(), 'Chemical/Ingredient' => array()
	);
	$pharm_classes_names = array('PE' => "PhysiologicEffect", 'CS' => "ChemicalStructure", 'MoA' => "MechanismOfAction",
		'EPC' => "EstablishedPharmaceuticalClass", 'Chemical/Ingredient' => "ChemicalIngredient"
	);
	$field_array = array(
		'PRODUCTTYPENAME' => array(),
		'PROPRIETARYNAMESUFFIX' => array(),
		'DOSAGEFORMNAME' => array(),
		'ROUTENAME' => array(),
		'MARKETINGCATEGORYNAME' => array(),
		'ACTIVE_INGRED_UNIT' => array(),
		//'PHARM_CLASSES' => array(),
		'DEASCHEDULE' => array(),
		'NDC_EXCLUDE_FLAG' => array(),
	);
	$longest_lengths = array( 
		'PRODUCTNDC' => 0, 'PRODUCTTYPENAME' => 0, 'PROPRIETARYNAME' => 0, 'PROPRIETARYNAMESUFFIX' => 0,
		'NONPROPRIETARYNAME' => 0, 'DOSAGEFORMNAME' => 0, 'ROUTENAME' => 0, 'STARTMARKETINGDATE' => 0,
		'ENDMARKETINGDATE' => 0,
		'MARKETINGCATEGORYNAME' => 0,
		'APPLICATIONNUMBER' => 0,
		'LABELERNAME' => 0,
		'SUBSTANCENAME' => 0,
		'ACTIVE_NUMERATOR_STRENGTH' => 0,
		'ACTIVE_INGRED_UNIT' => 0,
		'PHARM_CLASSES' => 0,
		'DEASCHEDULE' => 0,
		'NDC_EXCLUDE_FLAG' => 0,
		'LISTING_RECORD_CERTIFIED_THROUGH' => 0
	);

	$labeler_codes = array();
	$errors = array();

	for ($i = 0; $i < count($keystrings); $i++) {
		$keystrings[$i] = trim($keystrings[$i]);
	}

	while (! feof($fh)) {
		$line = fgets($fh);
		$output_line = "";
		$items = array();
		$elements = explode("\t", $line);

		$count = 0;
		foreach($keystrings as $value) {
			$items[$value] = trim($elements[$count++]);
			if ($items[$value] == "") {
				$items[$value] = "\\N";
			}
		}

		$items['NDC_EXCLUDE_FLAG'] = changeFlag($items['NDC_EXCLUDE_FLAG']);
		foreach($dates_to_change as $date_field) {
			$items[$date_field] = changeDate($items[$date_field]);
		}

		foreach($items as $key => $value) {
			$items[$key] = utf8_encode($value);
		}
		foreach($micro_changes as $key => $micro_arr) {
			if (preg_match($micro_arr[0], $items['ACTIVE_INGRED_UNIT'])) {
				$items['ACTIVE_INGRED_UNIT'] = str_replace($key, $micro_arr[1], $items['ACTIVE_INGRED_UNIT']); 
			}

		}

		if (! preg_match($final_regexes['PRODUCTNDC'], $items['PRODUCTNDC'])) {
			$items['PRODUCTNDC'] = changeNDC($items['PRODUCTNDC']);
		}

		logPharmClasses($items['PHARM_CLASSES'], $pharm_classes_array, $pharm_class_regexes_abbrevs);

		if (isset($labeler_codes[substr($items['PRODUCTNDC'], 0, 5)]) && $labeler_codes[substr($items['PRODUCTNDC'], 0, 5)] != $items['LABELERNAME']) {
			array_push($errors, "Labeler code was already set, but found a different labeler at ndc " . $items['PRODUCTNDC']);
		}
		else {
			$labeler_codes[substr($items['PRODUCTNDC'], 0, 5)] = $items['LABELERNAME'];
		}

		foreach($field_array as $key => $value) {
			$split_elements = explode(";", $items[$key]);
			for ($i = 0; $i < count($split_elements); $i++) {
				$split_elements[$i] = trim($split_elements[$i]);
			}
			foreach($split_elements as $split_value) {
				if (isset($field_array[$key][$split_value])) {
					$field_array[$key][$split_value]++;
				}
				else {
					$field_array[$key][$split_value] = 1;
				}
			}
		}

		foreach($longest_lengths as $key => $value) {
			if ($value < strlen($items[$key])) {
				$longest_lengths[$key] = strlen($items[$key]);
			}
		}

		$count = 0;
		foreach($items as $key => $val) {
			if ($key != 'PRODUCTID') {
				if ($count < count($items) - 1) {
					$output_line .= ($val . "\t");
				}
				else {
					$output_line .= ($val . "\n");
				}
			}
			$count++;
		}
		$final_string .= $output_line;
	}

	fclose($fh);

	/* put the final updated contents into the final file */
	$file_name = "/home/bk/Documents/DrugPackaging/OutputFiles/FinalDrugFile.txt";
	file_put_contents($file_name, trim($final_string));

	/* load the longest field lengths into a file */
	$file_name = "/home/bk/Documents/DrugPackaging/OutputFiles/LongestFieldWidths.txt";
	$longest_lengths_string = "";
	foreach($longest_lengths as $key => $value) {
		$longest_lengths_string .= ($key . " => " . $value . "\n");
	}
	file_put_contents($file_name, $longest_lengths_string);

	$file_name = "/home/bk/Documents/DrugPackaging/OutputFiles/LabelerCodes.txt";
	$labeler_string = "";
	$count = 0;
	foreach($labeler_codes as $key => $value) {
		if ($count++ < count($labeler_codes) - 1) $labeler_string .= ($key . "\t" . $value . "\n");
		else $labeler_string .= ($key . "\t" . $value);
	}
	file_put_contents($file_name, $labeler_string);

	foreach($pharm_classes_array as $key => $value) {
		$newline_separated_filename = ("/home/bk/Documents/DrugPackaging/OutputFiles/" . $pharm_classes_names[$key] . ".txt");
		$tab_separated_filename = ("/home/bk/Documents/DrugPackaging/OutputFiles/" . $pharm_classes_names[$key] . "Tabbed.txt");
		$comma_separated_filename = ("/home/bk/Documents/DrugPackaging/OutputFiles/" .$pharm_classes_names[$key] . "CSV.txt");
		$newline_string = "";
		$tabbed_string = "";
		$csv_string = "";
		$count = 0;
		foreach($value as $keyX => $valueX) {
			if ($count++ < count($value) - 1) {
				$newline_string .= ($keyX . "\n");
				$tabbed_string .= ($keyX . "\t");
				$csv_string .= ($keyX . ", ");
			}
			else {
				$newline_string .= $keyX;
				$tabbed_string .= $keyX;
				$csv_string .= $keyX;
			}
		}
		file_put_contents($newline_separated_filename, $newline_string);
		file_put_contents($tab_separated_filename, $tabbed_string);
		file_put_contents($comma_separated_filename, $csv_string);
	}

	foreach($field_array as $key => $value) {
		$newline_separated_filename = ("/home/bk/Documents/DrugPackaging/OutputFiles/" . $key . ".txt");
		$count = 0;
		$newline_separated_string = "";
		foreach($value as $keyX => $valueX) {
			if ($count++ < count($value)) {
				$newline_separated_string .= ($keyX . "\n");
			}
			else {
				$newline_separated_string .= $keyX;
			}
		}
		file_put_contents($newline_separated_filename, $newline_separated_string);
	}
	function checkForNull($value, $ndc) {
		if ($value == "\\N") {
			return "Null value found at " . $ndc;
		}
		else return false;
	}
	function changeDate($YYYYMMDD) {
		if ($YYYYMMDD == "\\N") return $YYYYMMDD;
		else return substr($YYYYMMDD, 0, 4) . "-" . substr($YYYYMMDD, 4, 2) . "-" . substr($YYYYMMDD, 6, 2);
	}

	function changeNDC($ndc) {
		$ndcsplit = explode("-", $ndc);
		if (strlen($ndcsplit[0]) == 4) $ndcsplit[0] = "0" . $ndcsplit[0];
		if (strlen($ndcsplit[1]) == 3) $ndcsplit[1] = "0" . $ndcsplit[1];
		return $ndcsplit[0] . "-" . $ndcsplit[1];
	}

	function logPharmClasses($drug_pharm_classes, &$pharm_classes_array, &$pharm_class_regexes_abbrevs) {
		if ($drug_pharm_classes == "\\N") return;
		$pharm_elements = explode(",", $drug_pharm_classes);
		for ($i = 0; $i < count($pharm_elements); $i++) {
			$pharm_elements[$i] = trim($pharm_elements[$i]);
		}

		foreach($pharm_elements as $valuePharm) {
			foreach($pharm_class_regexes_abbrevs as $key => $valueRA) {
				if (preg_match($valueRA[0], $valuePharm)) {
					$element = trim(str_replace($valueRA[1], "", $valuePharm));
					if (isset($pharm_classes_array[$key][$element])) {
						$pharm_classes_array[$key][$element]++;
					}
					else {
						$pharm_classes_array[$key][$element] = 1;
					}
				}

			}
		}
		return;
	}

	function changeFlag($flag) {
		if ($flag == "N") return "No";
		elseif ($flag == "Y") return "Yes";
		elseif ($flag == "E") return "Expired";
		elseif ($flag == "I") return "Inactive";
	}
?>
