<?php 

	include 'settings.php';
	
	$minfreq = 1;
	
	$conn = mysqli_connect($hostname, $username, $passwd, $dbname);
	if (!$conn) {
		die("Connection failed: " . mysqli_connect_error());
	}
	
	$sql = "SELECT * FROM Summary JOIN (SELECT COUNT(PathID) AS Count, SumID FROM Path WHERE Frequency > 0 GROUP BY SumID) Path USING (SumID)";
	
	$result = $conn->query($sql);
?>

	<html>
		<head>
			<style type="text/css">
				th    {color: black; background-color: #eed; font-weight: bold;}
				table {
				    border-collapse: collapse;
				    font-family: Helvetica;
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
		$url = $visualizerLocation."?sumid=".$row['SumID']."&minfreq=$minfreq";
		$pathcount = $row['Count'];
		$dataset = $row['Dataset'];
		$endpoint = $row['Endpoint'];
		$timestamp = $row['StartedAt'];
		if($dataset != "") echo "<tr> <td><a target=\"_parent\" href=\"$url\">$dataset</a></td> <td>$endpoint</td> <td>$pathcount</td> <td>$timestamp</td> </tr>";
	};

?>
	</table>
	</html>