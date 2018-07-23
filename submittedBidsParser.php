<?php

$cerl = error_reporting ();
error_reporting (E_ERROR | E_PARSE | E_NOTICE);


$servername = "*****";
$username = "******";
$password = "*****";
$dbname = "*****";
$_DATADIR_ = "/var/data/sirva/SubmittedBids/";
$_DONEDIR_ = "/var/data/sirva/SubmittedBidsDone/";

$dir = new DirectoryIterator($_DATADIR_); //Grab all the bids
foreach ($dir as $fileinfo) {
    if ($fileinfo->isDot()){
    	continue;
    }

	l("Working on file: ".$fileinfo->getFilename()."\n");
	$file = file_get_contents($_DATADIR_.$fileinfo->getFilename());
	$file = preg_replace("/\n/", "|", $file);
	$file = preg_replace("/[^\x20-\x7f]/", "", $file);
	$file = preg_replace("/\|/", "\n", $file);
	$file = str_replace("&nbsp;", " ", $file);
	$doc = new DOMDocument();
	$doc->loadHTML($file);
	$forms = $doc->getElementsByTagName("form");
	$bidRequestID = NULL;
	$altBidID = NULL;
	foreach ($forms as $key => $form) {
		//echo "\n******\n\n\n\n******\n";
		$bid = [];
		$propType = $form->getElementsByTagName("input"); //Go through all the input fields first.
		$flag = false;
		foreach ($propType as $key => $node) {
			$name = $node->getAttribute("name")."\n";
			switch ($name) {
				case "propertyTypeLevel\n":
					if($node->getAttribute("checked") != ""){
						$bid['propertyLevel'] = $node->getAttribute("value");
					}
					break;
				case "communityName\n":
					$bid['propertyName'] = $node->getAttribute("value");
					break;
				case "communityAddress\n":
					$bid['propertyAddress'] = $node->getAttribute("value");
					break;
				case "communityCity\n":
					$bid['city'] = $node->getAttribute("value");
					break;
				case "communinityState\n":
					$bid['state'] = $node->getAttribute("value");
					break;
				case "communinityZipcode\n":
					$bid['zip'] = $node->getAttribute("value");
					break;
				case "bidUnitSize\n":
					$bid['unitSize'] = $node->getAttribute("value");
					break;
				case "aptSquareFeet\n":
					$bid['aptSize'] = $node->getAttribute("value");
					break;
				case "rateFrequency\n":
					if($node->getAttribute("checked") != ""){
						$bid['rateUOM'] = $node->getAttribute("value");
					}
					break;
				case "aptSquareFeet\n":
					$bid['aptSize'] = $node->getAttribute("value");
					break;
				case "rate\n":
					$bid['rateAmount'] = $node->getAttribute("value");
					break;
				case "taxRate\n":
					$bid['taxRate'] = $node->getAttribute("value");
					break;
				case "dateAvailable\n":
					$bid['dateAvailable'] = date("Y-m-d",strtotime($node->getAttribute("value")));
					break;
				case "minimumTerm\n":
					$bid['minLeaseTerm'] = $node->getAttribute("value");
					break;
				case "longTermRental\n":
					$bid['longTermDiscount'] = $node->getAttribute("value");
					break;
				case "highSpeedInternet\n":
					if($node->getAttribute("checked") != ""){
						$bid['internet'] = $node->getAttribute("value");
					}
					break;
				case "phoneServiceIncluded\n":
					if($node->getAttribute("checked") != ""){
						$bid['phone'] = $node->getAttribute("value");
					}
					break;
				case "cableIncluded\n":
					if($node->getAttribute("checked") != ""){
						$bid['cable'] = $node->getAttribute("value");
					}
					break;
				case "washerDryer\n":
					if($node->getAttribute("checked") != ""){
						$bid['laundry'] = $node->getAttribute("value");
					}
					break;
				case "maidService\n":
					if($node->getAttribute("checked") != ""){
						$bid['maid'] = $node->getAttribute("value");
					}
					break;
				case "parkingIncluded\n":
					if($node->getAttribute("checked") != ""){
						$bid['parking'] = $node->getAttribute("value");
					}
					break;
				case "parkingFeeIncluded\n":
					if($node->getAttribute("checked") != ""){
						$bid['parkingFee'] = $node->getAttribute("value");
					}
					break;
				case "parkingFee\n":
					$bid['parkingFeeAmt'] = $node->getAttribute("value");
					break;

				case "petFee\n":
					$bid['petFee'] = $node->getAttribute("value");
					break;

				case "petDeposit\n":
					$bid['petDeposit'] = $node->getAttribute("value");
					break;

				case "bidRequestID\n":
					//altBidID cannot be used to find active requests matching it
					$altBidID = intval($node->getAttribute("value"));
					if(!$bidRequestID){
						$bidRequestID = intval($node->getAttribute("value"));
					}
					break;

				case "cbrID\n":
					$bid['cbrID'] = $node->getAttribute("value");
					break;
				case "webLink\n":
					$bid['propertyWebLink'] = $node->getAttribute("value");
					break;

				case "otherFees\n":
					if(!$flag){
						$bid['apxDistance'] = $node->getAttribute("value");
						$flag = true; //we've seen it already
					}
					else{
						$bid['otherFees'] = $node->getAttribute("value");
					}
					break;
				case "sfMonth\n":
					$bid['sfMonth'] = $node->getAttribute("value");
					break;
				case "clientID\n":
					$bid['clientID'] = $node->getAttribute("value");
					break;
				default:
					break;
			}
		};
		$propType = $form->getElementsByTagName("textarea"); //Go through the text areas next
		foreach ($propType as $key => $node) {
			$name = $node->getAttribute("name")."\n";
			switch ($name) {
				case "petPolicy\n":
					$bid['petPolicy'] = $node->textContent;
					break;
				case "comments\n":
					$bid['comments'] = addslashes($node->textContent);
					break;
				default:
					break;
			}
		}

		$bid['reservationID']  = $doc->getElementById("reservationNumber")->textContent;
		$bid['clientID'] = ($doc->getElementById("clientID"))?$doc->getElementById("clientID")->textContent:"";
		$bid['specialRequests'] = addslashes($doc->getElementById("specialRequests")->textContent);
		// foreach ($bid as $key => $value) {
		// 	echo $key." :: ".$value."\n";
		// }
		$keys = implode(",", array_keys($bid));
		$values = [];
		foreach ($bid as $key => $value) {
			switch ($key) {
				case "bidRequestID":
					$bid[$key] = intval($value);
					break;
				case "cbrID":
					$bid[$key] = intval($value);
					break;
				case "reservationID":
					$bid[$key] = intval($value);
					break;
				case "zip":
					$bid[$key] = intval($value);
					break;
				case "aptSize":
					$bid[$key] = intval($value);
					break;
				case "rateAmout":
					$bid[$key] = floatval($value);
					break;
				case "parkingFeeAmt":
					$bid[$key] = floatval($value);
					break;
				case "petFee":
					$bid[$key] = floatval($value);
					break;
				case "petDeposit":
					$bid[$key] = floatval($value);
					break;
				default:
					$bid[$key] = "\"".$value."\"";
					break;
			}
		}

		$values = implode(",", $bid);

		$sql = "INSERT IGNORE INTO SubmittedBids ($keys,bidRequestID,altBidID,bidhash,idHash) VALUES ($values, '$bidRequestID','$altBidID', sha2(concat('$altBidID',cbrID, clientID, reservationID, specialRequests),0),sha2(concat(bidRequestID,cbrID, clientID, reservationID, specialRequests),0))";
		// echo($sql.PHP_EOL);
		//continue;
		$conn = new mysqli($servername, $username, $password, $dbname);
		if ($conn->connect_error) {
		    die("Connection failed: " . $conn->connect_error);
		} 
		if ($conn->query($sql)) {
		    echo "Insert Successful! ".$conn->affected_rows." rows updated\n";
		    rename($_DATADIR_.$fileinfo->getFilename(), $_DONEDIR_.$fileinfo->getFilename());
			}
		else {
		    	echo "Error: " . $sql . "\n" . $conn->error;
		}
		$conn->close();
	}
	echo PHP_EOL;
}
function l($message){
  echo basename(__FILE__, '.php') . " - " . date('m/d/y H:i:s') . " - " . $message . "\n"; 
}
error_reporting ($cerl);
?>