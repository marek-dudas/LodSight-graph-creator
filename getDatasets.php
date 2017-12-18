<?php

include 'settings.php';

$conn = mysqli_connect($hostname, $username, $passwd, $dbname);
if (!$conn) {
	die("Connection failed: " . mysqli_connect_error());
}

$sql = "SELECT * FROM Summary JOIN (SELECT COUNT(PathID) AS Count, SumID, MAX(Frequency) AS MaxFreq FROM Path WHERE Frequency > 0 GROUP BY SumID) Path USING (SumID) WHERE DATE(StartedAt) > '$showFromDate'";

$result = $conn->query($sql);
$minfreqTotal = 1;
$return = array();
while ($row = $result->fetch_assoc()) {

	$pathcount = $row['Count'];
	$dataset = $row['Dataset'];
	$endpoint = $row['Endpoint'];
	$timestamp = $row['StartedAt'];
	$maxFreq = $row['MaxFreq'];

	if ($pathcount > $pathCountLimit) {
		$minfreqTotal = $minfreq = $maxFreq * $limitedDetailPercentage * 0.01;
	} else $minfreq = 1;

	$url = $visualizerLocation . "?sumid=" . $row['SumID'] . "&minfreq=$minfreq&maxfreq=" . PHP_INT_MAX;

	$dataset = $dataset == "" ? "all available" : $dataset;
	$return[] = array(
		'sumId' => $row["SumID"],
		'url' => $url,
		'dataset' => $dataset,
		'endpoint' => $endpoint,
		'pathCount' => $pathcount,
		'selected' => false
	);
}

$returnData = array(
	'minFreq' => $minfreq,
	'maxFreq' => PHP_INT_MAX,
	'data' => $return
);

echo json_encode($returnData);
