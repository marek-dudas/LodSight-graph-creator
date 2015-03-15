<?php 

	include 'settings.php';
	
	$conn = mysqli_connect($hostname, $username, $passwd, $dbname);
	if (!$conn) {
		die("Connection failed: " . mysqli_connect_error());
	}
	
	$sql = "SELECT * FROM Summary JOIN (SELECT COUNT(PathID) AS Count, SumID, MAX(Frequency) AS MaxFreq FROM Path WHERE Frequency > 0 GROUP BY SumID) Path USING (SumID) WHERE DATE(StartedAt) > '$showFromDate'";
	
	$result = $conn->query($sql);
?>

	<html>
		<head>
			<style type="text/css">
				th    {color: black; background-color: #eed; font-weight: bold;}
				table {
				    border-collapse: collapse;
				    font: 12px Helvetica Neue, Helvetica, Arial, sans-serif;
				}
				
				table, th, td {
				    border: 1px solid black;
				}
			</style>
		</head>
	
		<table>
			<tr>
				<th>
					Dataset</th>
				<th>
					Endpoint</th>
				<th>
					Paths Found</th>
				<th>
					Timestamp</th>
			</tr>
		
<?php 
	
	while($row = $result->fetch_assoc()) {
		
		$pathcount = $row['Count'];
		$dataset = $row['Dataset'];
		$endpoint = $row['Endpoint'];
		$timestamp = $row['StartedAt'];
		$maxFreq = $row['MaxFreq'];
		
		if($pathcount > $pathCountLimit) $minfreq = $maxFreq * $limitedDetailPercentage * 0.01;
		else $minfreq = 1;
		
		$url = $visualizerLocation."?sumid=".$row['SumID']."&minfreq=$minfreq";
		
		if($dataset == "") $dataset = "all available"; 
			echo "<tr> <td><a target=\"_parent\" href=\"$url\">$dataset</a></td> <td>$endpoint</td> <td>$pathcount</td> <td>$timestamp</td> </tr>";
	};

?>
	</table>
	</html>