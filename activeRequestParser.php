<?php

$cerl = error_reporting ();
error_reporting (E_ERROR | E_PARSE | E_NOTICE);

$_DATADIR_ = '/var/data/sirva/ActiveRequests/';
$_DONEDIR_ = '/var/data/sirva/ActiveRequestsDone/';

$servername = "*****";
$username = "*****";
$password = "*****";
$dbname = "*****";
$FLAG = 1;
$kill = 1;
l();
//Grab all leads currently active in the DB.
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
//Grab all leads status=1;
$getActiveRequestsInDB = "SELECT reservationNum FROM ActiveRequests WHERE status = 1";
$i=0;
$activeRequestsInDB = [];
$res = $conn->query($getActiveRequestsInDB);
if ($res->num_rows > 0) {
	echo "Retrieved Rows: " . $res->num_rows . "\n";
	while($row = $res->fetch_assoc()){
	  $activeRequestsInDB[$i++] = $row["reservationNum"];
	}
}
else {
	$FLAG = 0;
	echo "0 results for status = 1 in DB \n";
}
var_dump($activeRequestsInDB);
echo PHP_EOL;
$dir = new DirectoryIterator($_DATADIR_); //Grab all the bids
//Check to see if the scraper actually ran
foreach ($dir as $key => $fileinfo) {
	if($fileinfo->isDot())
		continue;
	if($fileinfo->getFilename() == "_A_"){
		echo "Verified Scraper has run - continuing\n";
		$kill = 0;
		unlink($_DATADIR_.$fileinfo->getFilename());
		break;
	}
}
if($kill){
	echo "Error: Scraper did not run -- aborting...";
	exit();
}
foreach ($dir as $fileinfo) {
    if ($fileinfo->isDot()){
    	continue;
    }
    echo PHP_EOL."Working on file: ".$fileinfo->getFilename()."\n";
	$file = file_get_contents($_DATADIR_.$fileinfo->getFilename());
	$file = preg_replace("/\n/", "|", $file);
	$file = preg_replace("/[^\x20-\x7f]/", "", $file);
	$file = preg_replace("/\|/", "\n", $file);
	$file = str_replace("&nbsp;", " ", $file);
	$doc = new DOMDocument();
	$doc->loadHTML($file);

	$leadData = [];

	$tableHDR = $doc->getElementsByTagName("table")[1]; //Header information
	$tableDET = $doc->getElementsByTagName("table")[2]; //Detail information
	
	//Work on Header first. Grab all the cells.
	$hdrCell = $tableHDR->getElementsByTagName("tr")[1]->getElementsByTagName("td"); 
	foreach ($hdrCell as $key => $cell) {
		$leadData[] = $cell->textContent;
	}
	//Details come next
	$detCell = $tableDET->getElementsByTagName("tr")[1]->getElementsByTagName("td"); 
	foreach ($detCell as $key => $cell) {
		$leadData[] = addslashes($cell->textContent);
	}

	//We have the reservation number, lets check if it's already in the DB.
	$index = compareToDB($leadData[1]);
	if($index != -1){ //this means we've already logged this active request
		unset($activeRequestsInDB[$index]);
		echo "Found Reservation number: ".$leadData[1]." in the DB already".PHP_EOL;
		rename($_DATADIR_.$fileinfo->getFilename(), $_DONEDIR_.$fileinfo->getFilename());
		continue;
	}

	/*Now get the metadata:
		-clientID
		-cbrID
		-bidRequestID
	*/
	$inputs = $doc->getElementsByTagName("form")[$doc->getElementsByTagName("form")->length-1]->getElementsByTagName("input");
	foreach ($inputs as $key => $input) {
			$name = $input->getAttribute("name");
			switch ($name) {
				case 'cbrID':
					$metaData["cbrID"] = $input->getAttribute("value");
					break;
				case 'bidRequestID':
					$metaData["bidRequestID"] = $input->getAttribute("value");
					break;
				default:
					break;
			}//end switch
		}//end foreach
	//In order to get the clientID, we need to find the form that has the same bidRequestID
	$allInputs = $doc->getElementsByTagName("input");
	foreach ($allInputs as $key => $input) {
		if($input->getAttribute("name") == "bidRequestID" && $input->getAttribute("value") == $metaData["bidRequestID"]){
			$parent = $input->parentNode->getElementsByTagName("input");
			foreach ($parent as $key => $cell) {
				if($cell->getAttribute("name") == "clientID"){
					$metaData["clientID"] = $cell->getAttribute("value");
					break;
				}//end if
			}// end foreach
			break;
		}//end if
	}
	$values = "'".implode("','", $metaData)."','".implode("','", $leadData)."'";
	$sql = "INSERT IGNORE INTO ActiveRequests (bidRequestID, cbrID, clientID, unitSize ,reservationNum, arrivalDate, minimumStay, numOccupants, desiredLocation, pets, typeWeightBreed, specialRequests, hash) VALUES ($values, sha2(concat(bidRequestID,cbrID, clientID, reservationNum, specialRequests),0))";
	if ($conn->connect_error) {
	    die("Connection failed: " . $conn->connect_error);
	} 
	if ($conn->query($sql)) {
	    echo "Insert Successful! ".$conn->affected_rows." rows updated".PHP_EOL;
	    rename($_DATADIR_.$fileinfo->getFilename(), $_DONEDIR_.$fileinfo->getFilename());
		}
	else {
	    echo "Error: " . $sql . "\n" . $conn->error;
	}
}//end dir foreach

var_dump($activeRequestsInDB);

//Close all the reservation ID's still in the activeRequestsInDB array.
foreach ($activeRequestsInDB as $key => $row) {
	$sql = "UPDATE ActiveRequests SET close_timestamp = FROM_UNIXTIME(UNIX_TIMESTAMP()), status = 0 WHERE reservationNum = '$row' ";
	echo "Setting reservationNum $row to closed\n";
	if ($conn->query($sql)) {
	    echo "Update Successful! ".$conn->affected_rows." rows updated".PHP_EOL;
		}
	else {
	    echo "Error: " . $sql . "\n" . $conn->error;
	}
}

function compareToDB($resID){
	if(!$GLOBALS['FLAG']){
		return -1;
	}
	$activeRequestsInDB = $GLOBALS['activeRequestsInDB'];
	foreach ($activeRequestsInDB as $key => $row) {
		if($row == $resID){
			return $key;
		}
		else{
			continue;
		}
	}
	return -1;
}

$conn->close();
error_reporting ($cerl);
function l($message=""){
  echo basename(__FILE__, '.php') . " - " . date('m/d/y H:i:s') . " - " . $message . "\n"; 
}
?>