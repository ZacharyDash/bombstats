<?php

require 'vendor/autoload.php';

use Telegram\Bot\Api;
use Telegram\Bot\FileUpload\InputFile;

$telegram = new Api('688653375:AAGPI0J_ghelUDMUvgU7QxNuNd2EaR2Q4FY');

$updates = $telegram->getWebhookUpdates();
$chat_id =  $updates->getMessage()->getChat()->getId();

$db  = new PDO('sqlite:db.sqlite') or die("cannot open the database");
$stmt = $db->prepare("SELECT * FROM telegram_users WHERE chat_id = ?");
$stmt->execute([$chat_id]);
$result = $stmt->fetchAll();
// if new chat - register
if(count($result) == 0){
	$stmt = $db->prepare("INSERT INTO telegram_users VALUES (?)");
	$stmt->bindParam(1, $id);
	$id = $chat_id;
	if($stmt->execute()){
		$telegram->sendPhoto([
	        'chat_id' => $chat_id,
	        'photo' => InputFile::create('http://stats.bomblytics.com/bot/welcome.jpg', 'welcome.jpg'),
	        'caption' => 'I will now start sending you hourly ' . hex2bin('f09f92a3') . ' updates.'
    	]);
	}
}
