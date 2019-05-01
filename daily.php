<?php
// only cron script - protection for direct run  
if(isset($_SERVER['REMOTE_ADDR']))die('Permission denied.');

$yesterday = date("Y-m-d", time() - 86400);
$db  = new PDO('sqlite:'.__DIR__.'/db.sqlite') or die("cannot open the database");

$stmt = $db->prepare("SELECT max(total_supply)-min(total_supply) FROM 'tokens_history' WHERE DATE(created)='" . $yesterday . "'");
$stmt->execute();
$dayly_burned = $stmt->fetchColumn();

//save stats for yesterday

$stmt = $db->prepare("INSERT INTO daily_stats VALUES (?, ?)");
$stmt->bindParam(1, $burned);
$stmt->bindParam(2, $day);
$burned = $dayly_burned;
$day = $yesterday;

if($stmt->execute()){
	echo 'success';
}


