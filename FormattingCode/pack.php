<?php
	$fh = fopen("/home/bk/Documents/DrugPackaging/InputFiles/package.txt", "r") or die("Error on file open");
	$str0 = fgets($fh);

	$prod_ndc_match = "/\d{5}-\d{4}/";
	$package_code_match = "/\d{5}-\d{4}-\d{2}/";
	$date_match = "/\d{4}-\d{2}-\d{2}/";

	// generate array keys by splitting up the fields line from the first line of the file
	$field_keys = explode("\t", trim($str0));

	// the complete final string to put in the file
	$final_string = "";
	$error_string = "";

	while (! feof($fh)) {
		// break the data line into individual elements
		$field_data = explode("\t", trim(fgets($fh)));

		// an array for holding data in format $item_array['field_key'] = field_data[i]
		$item_array = array();

		$line_string = "";

		// keeps count of the number of elements in the line
		$count = 0;

		// load the item_array with the key and value, trim whitespace from each field
		foreach ($field_keys as $value) {
			$item_array[$value] = trim($field_data[$count++]);
		}


		// if a field was empty mark it as \\N, null value for mysql
		foreach ($item_array as $key => $value) {
			if ($value == "") {
				$item_array[$key] = "\\N";
			}
		}

		/* 1.  	standardize the productndc and ndcpackage code in the format #####-#### and #####-####-##
				this file may contain productndc and ndcpackagecode with any of those fields without leading zeros in those fields
				if they are shorter than the indicated format in any of those fields add leading zeros
			2.	standardize the marketing dates for mysql if the date is not null, the file format dates are YYYYMMDD, mysql is YYYY-MM-DD
			3.  standardize N, Y, I, E values to No, Yes, Inactive, Expired
		*/
		foreach ($item_array as $key => $value) {
			if ($key == 'PRODUCTNDC' || $key == 'NDCPACKAGECODE') {
				$ndc_components = explode("-", $value);
				if (count($ndc_components) == 2) {
					if (strlen($ndc_components[0]) == 4) {
						$ndc_components[0] = "0" . $ndc_components[0];
					}
					if (strlen($ndc_components[1]) == 3) {
						$ndc_components[1] = "0" . $ndc_components[1];
					}
					$item_array[$key] = $ndc_components[0] . "-" . $ndc_components[1];
				}
				elseif (count($ndc_components) == 3) {
					if (strlen($ndc_components[0]) == 4) {
						$ndc_components[0] = "0" . $ndc_components[0];
					}
					if (strlen($ndc_components[1]) == 3) {
						$ndc_components[1] = "0" . $ndc_components[1];
					}
					if (strlen($ndc_components[2]) == 1) {
						$ndc_components[2] = "0" . $ndc_components[2];
					}
					$item_array[$key] = $ndc_components[0] . "-" . $ndc_components[1] . "-" . $ndc_components[2];
				}
			}
			if (($key == "STARTMARKETINGDATE" || $key == "ENDMARKETINGDATE") && $value != "\\N") {
				$item_array[$key] = substr($value, 0, 4) . "-" . substr($value, 4, 2) . "-" . substr($value, 6, 2);
			}
			if ($value == "N") {
				$item_array[$key] = "No";
			}
			if ($value == "Y") {
				$item_array[$key] = "Yes";
			}
			if ($value == "I") {
				$item_array[$key] = "Inactive";
			}
			if ($value == "E") {
				$item_array[$key] = "Expired";
			}
		}
		/*

		if (! preg_match($prod_ndc_match, $item_array['PRODUCTNDC'])) {
			$error_string .= ("Error on ndc " . $item_array['PRODUCTNDC'] . "\n");
		}
		if (! preg_match($package_code_match, $item_array['NDCPACKAGECODE'])) {
			$error_string .= ("Error on ndc " . $item_array['PRODUCTNDC'] . "\n");
		}
		if (! preg_match($date_match, $item_array['STARTMARKETINGDATE'])) {
			$error_string .= ("Error on start market date on ndc " . $item_array['NDCPACKAGECODE'] . "\n");
		}
		if ($item_array['ENDMARKETINGDATE'] != "\\N" && !preg_match($date_match, $item_array['ENDMARKETINGDATE'])) {
			$error_string .= ("Error on end market date on ndc " . $item_array['NDCPACKAGECODE'] . "\n");			
		}
		if ($item_array['PACKAGEDESCRIPTION'] == "\N") {
			$error_string = ("Package description was null at " . $item_array['NDCPACKAGECODE']. "\n");
		}
		*/


		// put each item from the itemarray into the final string separated by tabs for mysql, discard the product ID field
		$count = 0;
		foreach ($item_array as $key => $value) {
			if ($key != 'PRODUCTID') {
				$line_string .= $value;
				if ($count++ == count($item_array) - 2) {
					$line_string .= "\n";
				}
				else {
					$line_string .= "\t";
				}
			}
		}
		$final_string .= $line_string;
	}

	fclose($fh);

	$final_string = trim($final_string);
		//$item_array['FULLNDC'] = str_replace("-", "", $item_array['NDCPACKAGECODE']);
		//$item_array['LABELERCODE'] = substr($item_array['FULLNDC'], 0, 5);
	// reset counter to zero


	//$file_name = "/home/bk/Documents/DrugPackaging/OutputFiles/FormattedProducts.txt";
	$file_name = "tempfile.txt";
	file_put_contents($file_name, $final_string);
	//fclose($fh);

	//$fh = fopen("/home/bk/Documents/DrugPackaging/OutputFiles/FormattedProducts.txt", "r") or die("Could not open file");
	$fh = fopen("tempfile.txt", "r") or die("Could not open file");

	/* find duplicate NDCs */

	$ndc_dup_array = array();

	while (! feof($fh)) {
		$items = explode("\t", trim(fgets($fh)));

		if (! isset($ndc_dup_array[$items[1]])) {
			$ndc_dup_array[$items[1]] = 1;
		}
		else {
			$ndc_dup_array[$items[1]]++;
		}
	}


	fclose($fh);

	$final_string = "";

	$only_duplicates = array();

	foreach ($ndc_dup_array as $key => $value) {
		if ($value > 1) {
			$only_duplicates[$key] = $value;
			//$final_string .= ($key . "\n");
		}
	}

	//$final_string = "";



	//$file_name = "/home/bk/Documents/DrugPackaging/OutputFiles/DuplicateNDCs.txt";
	//file_put_contents($file_name, $final_string);


	$final_string = "";

	$fh = fopen("tempfile.txt", "r") or die("Could not open file");

	while (! feof($fh)) {
		$line = fgets($fh);

		$elements = explode("\t", trim($line));

		$duplicate_found = false;

		foreach ($only_duplicates as $key => $value) {
			if ($key == $elements[1] && $value > 1) {
				$duplicate_found = true;
				$only_duplicates[$key]--;
				print('duplicate removed at ');
				print($key);
				print("\n");
			}
		}

		if ($duplicate_found) {
			continue;
		}
		else {
			$final_string .= $line;
		}
	}

	fclose($fh);

	$file_name = "/home/bk/Documents/DrugPackaging/OutputFiles/FormattedProducts.txt";

	file_put_contents($file_name, $final_string);

?>