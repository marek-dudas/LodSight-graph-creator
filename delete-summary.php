<?php 
	$sumID = 37541850;

	include 'settings.php';
	
	$minfreq = 1;
	
	$conn = mysqli_connect($hostname, $username, $passwd, $dbname);
	if (!$conn) {
		die("Connection failed: " . mysqli_connect_error());
	}
	
	$sql = "DELETE FROM PathTriplet WHERE PathID IN (SELECT PathID FROM Path WHERE SumID = $sumID)";
	
	$result = $conn->query($sql);
	
	echo "$result";
	echo "<br>";
	
	$sql = "DELETE FROM Path WHERE SumID = $sumID";
	
	$result = $conn->query($sql);
	
	echo "$result";
	echo "<br>";
	
	$sql = "DELETE FROM SetTriplet WHERE SetID IN (SELECT SetID FROM CSet WHERE SumID = $sumID)";
	
	$result = $conn->query($sql);
	
	echo "$result";
	echo "<br>";
	
	$sql = "DELETE FROM SetPredicate WHERE SetID IN (SELECT SetID FROM CSet WHERE SumID = $sumID)";
	
	$result = $conn->query($sql);
	
	echo "$result";
	echo "<br>";
	
	$sql = "DELETE FROM CSet WHERE SumID = $sumID)";
	
	$result = $conn->query($sql);
	
	echo "$result";
	echo "<br>";	

	$sql = "DELETE FROM Summary WHERE SumID = $sumID)";
	
	$result = $conn->query($sql);
	
	echo "$result";
	echo "<br>";
?>