<?php
// $host = "165.132.121.124"; // 자신의 mysql
$host = "127.0.0.1";
$DB_name = "master"; // 데이터베이스 이름
$user = "root"; // 기본 사용자.
$password = "0000"; // apm 기본 암호

$sensor_field_cnt = 7;
$actuator_field_cnt = 3;
$sensor_field_name = array(
	'초음파',
	'장애물 감지',
	'습도',
	'온도',
	'열',
	'조도',
	'가스'
); //센서 필드명 집합
$actuator_field_name = array(
	'Fan',
	'Servo',
	'Buzzer'
); //액추에이터 필드명 집합

$conn = mysqli_connect($host, $user, $password, $DB_name);

if (!$conn) { die("DB Connection Error: " .mysqli_error($conn)); }

// else
if($_POST['table_name'] == 'sensorlist'){
	$sql = "select IP,S_Ultrasonic,S_IR,S_Humidity,S_Temperature,S_Heatindex,S_Light,S_Gas from SensorList";
}
else{
	$sql = "select IP,A_Fan,A_Servo,A_Buzzer from ActuatorList";
}

$result = mysqli_query($conn, $sql) or die("Error in Selecting " . mysqli_error($conn));

while ($row = mysqli_fetch_array($result)) {
	echo "<li class='ui-widget-content ui-corner-tr ui-state-default'>";
	echo "<h5 class='ui-widget-header'>IP: " . $row['IP'] . "</h5>";
	echo "<table class='table table-hover table-condensed table-bordered'><tbody>";
	if($_POST['table_name'] == 'sensorlist'){
		for ($i = 1; $i <= $sensor_field_cnt; $i++) {	//sensor part
			if ($row[$i] != NULL) {
				echo "<tr><td>" . $sensor_field_name[$i-1] . "</td><td>" . $row[$i] . "</td></tr>";
			}	//if
		}	//for
	}	//if
	else{	//table_name == actuatorlist
		for($j = 1; $j <= $actuator_field_cnt; $j++){	//actuator part
			if($row[$j] != NULL){
				echo "<tr><td>" . $actuator_field_name[$j-1] . "</td><td>" . $row[$j] . "</td></tr>";
			}	//if
		}	//for
	}
	echo "</tbody></table></li>";
}	//while

mysqli_close($conn);
?>