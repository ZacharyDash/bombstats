<?php
// only cron script - protection for direct run  
if(isset($_SERVER['REMOTE_ADDR']))die('Permission denied.');

// composer install...
require 'vendor/autoload.php';

use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;

/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/

if(!function_exists('curl_version'))die('This script requires cURL extension to be loaded...');
 
/**
  Settings
  **/ 

// ethersacan settings
$etherscanApiURL 	   = 'https://api.etherscan.io/api?';
$etherscanApiKey 	   = 'SM6JD1A44SYMK7KP3K6QMVMPS2PCSR9AZ9';
$etherscanTokenAddress = '0x1C95b093d6C236d3EF7c796fE33f9CC6b8606714';
$originalTokenAmount   = 1000000;

// twitter settings
$twitterSettings = [
	'oauth_access_token' => "YOUR_TWITTER_CREDENTIALS",
    'oauth_access_token_secret' => "YOUR_TWITTER_CREDENTIALS",
    'consumer_key' => "YOUR_TWITTER_CREDENTIALS",
    'consumer_secret' => "YOUR_TWITTER_CREDENTIALS"
]; // put your twitter credentials in this array

$twitterAPI = new TwitterAPIExchange($twitterSettings);
$twitterAPIUrl = 'https://api.twitter.com/1.1/statuses/update.json';

// telegram bot API
$telegram = new Api('688653375:AAGPI0J_ghelUDMUvgU7QxNuNd2EaR2Q4FY');

// get data from etherscan
$queryParams = [
	'module' => 'stats',
	'action' => 'tokensupply',
	'contractaddress' => $etherscanTokenAddress,
	'apikey' => $etherscanApiKey
];
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $etherscanApiURL . http_build_query($queryParams));
curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
$return  = curl_exec($curl);
curl_close($curl);
$result = json_decode($return);
// if get correct answer prepare data and send tweet
if ($result->status == '1'){ 
	$tokensupply = trim($result->result);
	$burntOutput = $originalTokenAmount - $tokensupply;

	// get last query
	$lastQuery = getLastTokenSupply();
    $lastSupply = $lastQuery['total_supply'];

	if(strlen($lastSupply) > 0) {

		$diff = $lastSupply - $tokensupply;
		// if there is difference with last value
		if($diff !== 0) {
			$avg = getDailyAVG();
			$finalBomb = getFinalBomb($tokensupply, $avg);
			//put new value in db
			insertTokenSupply($tokensupply);

			$twitterMessage = prepareMessageForTwitter($lastQuery, $tokensupply, $burntOutput, $avg, $finalBomb);
			$shareTwitterMessage = prepareMessageForTwitter($lastQuery, $tokensupply, $burntOutput, $avg, $finalBomb, false);
			// send to twitter
		
			$twitterResponse = $twitterAPI->buildOauth($twitterAPIUrl, 'POST')
	    		 ->setPostfields(['status' => $twitterMessage])
	    		 ->performRequest();

	    	$twitterResult = json_decode($twitterResponse);

	    	if(isset($twitterResult->id)){
	    		$telegramChats = getAllTelegramChats();
				$telegramMessage = prepareMessageForTelegram($lastQuery, $tokensupply, $burntOutput, $avg, $finalBomb);
				$share_link1 = 'https://twitter.com/intent/tweet?text='. urlencode($shareTwitterMessage) .'&hashtags=cryptocurrency';
				$share_link2 = 'https://ddex.io/trade/BOMB-WETH?referralCode=7PLE';
				$k = new Keyboard();
				$keyboard = $k::make()
	    					->inline()
	    					->row(
						        $k::inlineButton(['text' => hex2bin('f09f9189') . ' Tweet it! ', 'url' => $share_link1]),
						        $k::inlineButton(['text' => 'BUY/SELL ' . hex2bin('f09f92a3'), 'url' => $share_link2])
	    					);

				if(count($telegramChats > 0)){
		    		foreach ($telegramChats as $chat) {
		    			try{
		    				$telegram->sendMessage([
						        'chat_id' => $chat['chat_id'],
						        'text' => $telegramMessage,
						        'reply_markup' => $keyboard
							]);
		    			} catch(Telegram\Bot\Exceptions\TelegramResponseException $e){
							$errorData = $e->getResponseData();
							if ($errorData['ok'] === false) {
							   // delete user who blocked this bot
						       deleteUser($chat['chat_id']);
						    }
						}
		    		}
		    	}
	    	}
		}
	}
}

function prepareMessageForTwitter($lastInHistory, $total, $burned, $avg, $lastBomb, $hashtags_row = true)
{
	$diff = $lastInHistory['total_supply'] - $total;

	$emoticon_boom = html_entity_decode('&#x1f4a5;', 0, 'UTF-8');
	$emoticon_fire = html_entity_decode('&#x1f525;', 0, 'UTF-8');
	$emoticon_bomb = html_entity_decode('&#x1f4a3;', 0, 'UTF-8'); 
	$emoticon_skull = html_entity_decode('&#x1f480;', 0, 'UTF-8'); 
	$emoticon_upwards = html_entity_decode('&#x1F4C8;', 0, 'UTF-8'); 
	$emoticon_downwards = html_entity_decode('&#x1F4C9;', 0, 'UTF-8'); 
	$emoticon_rocket = html_entity_decode('&#x1f680;', 0, 'UTF-8'); 
	$emoticon_money = html_entity_decode('&#x1f4b8;', 0, 'UTF-8');
	$emoticon_bank = html_entity_decode('&#x1f3e6;', 0, 'UTF-8');
	$space = html_entity_decode('&#x0020;', 0, 'UTF-8');
	// shuffle hashtag string 
	$hashtags = explode(' ', '#cryptocurrency #crypto');
	shuffle($hashtags);

	$message = $emoticon_boom . " BOOM!! " . $emoticon_boom . $space . $diff . ' $BOMB have been destroyed in the last hour ' . $emoticon_fire . "\r\n\r\n";
	$message .= $emoticon_bomb . " Remaining: " . number_format($total) . ' $BOMB' . "\r\n";
	$message .= $emoticon_fire . " Burned: " . number_format($burned) . ' | Rate: ' . $avg . "/d \r\n";
	$message .= $emoticon_skull . " Last " . '$BOMB' . " expected: ". $lastBomb ."\r\n";

	$price = getPricing();

	if($price) {

        $market = $total * $price['price_usd'];
        if($market < 1000000) {
            $market_price_postfix = 'K';
            $market_price = round($market / 1000);
        } else {
            $market_price_postfix = 'M';
            $market_price = round($market / 1000000, 2);
        }

        //$marketcap_percentage = round((($market_price - $lastInHistory['marketcap']) / $lastInHistory['marketcap']) * 100, 2);
        $change = $price['24h_change'];
        //$change_percentage = round((($change - $lastInHistory['24h_value']) / $lastInHistory['24h_value']) * 100, 2);

		switch ($change) {
			case ($change > 0 && $change < 15):
					$em = $emoticon_upwards;
					$price_change = '+' . $price['24h_change'];
				break;
			case ($change > 15):
					$em = $emoticon_rocket;
					$price_change = '+' . $price['24h_change'];
				break;
			case ($change < 0):
					$em = $emoticon_downwards;
					$price_change = $price['24h_change'];
				break;	
			default:
					$em = $emoticon_downwards;
					$price_change = '+' . $price['24h_change'];
				break;
		}

        $message .= $emoticon_bank . " Market Cap: $" . $market_price . $market_price_postfix . " (". $marketcap_percentage ."%)\r\n";
        if($price['24h_volume']){
            $message .= $emoticon_money . " Volume: $" . round(($price['24h_volume'] / 1000), 1) . "K (". $change_percentage ."%)\r\n";
        }

		$message .= $em . " Price: " . '$' . $price['price_usd'] . " (". $price_change ."%|24h)\r\n\r\n";
	}

	$message .= "@bombtoken";
	if($hashtags_row){
		$message .= ' ' . implode(' ', $hashtags);
	}

	return $message;
}

function prepareMessageForTelegram($lastInHistory, $total, $burned, $avg, $lastBomb)
{
	$diff = $lastInHistory['total_supply'] - $total;

	$emoji = [
		'boom' => hex2bin('f09f92a5'),
		'bomb' => hex2bin('f09f92a3'),
        'money_wings' => hex2bin('F09F92B8'),
        'bank' => hex2bin('F09F8FA6'),
		'fire' => hex2bin('f09f94a5'),
		'skull' => hex2bin('f09f9280'),
		'rocket' => hex2bin('f09f9a80'),
		'downwards' => hex2bin('f09f9389'),
		'upwards' => hex2bin('f09f9388')
	];

	$message = $emoji['boom'] . " BOOM!! " . $emoji['boom'] . " ";
	$message .= $diff . ' $BOMB have been destroyed in the last hour ' . "\r\n\r\n";
	$message .= $emoji['bomb'] . " Remaining: " . number_format($total) . ' $BOMB' . "\r\n";
	$message .= $emoji['fire'] . " Burned: " . number_format($burned) . ' | Rate: ' . $avg . "/d \r\n";
	$message .= $emoji['skull'] . " Last " . '$BOMB' . " expected: ". $lastBomb ."\r\n";

    $price = getPricing();

	if($price) {

        $market = $total * $price['price_usd'];
        if($market < 1000000) {
            $market_price_postfix = 'K';
            $market_price = round($market / 1000);
        } else {
            $market_price_postfix = 'M';
            $market_price = round($market / 1000000, 2);
        }

        //$marketcap_percentage = round((($market_price - $lastInHistory['marketcap']) / $lastInHistory['marketcap']) * 100, 2);
		$change = $price['24h_change'];
		//$change_percentage = round((($change - $lastInHistory['24h_value']) / $lastInHistory['24h_value']) * 100, 2);

		switch ($change) {
			case ($change > 0 && $change < 15):
					$em = $emoji['upwards'];
					$price_change = '+' . $price['24h_change'];
				break;
			case ($change > 15):
					$em = $emoji['rocket'];
					$price_change = '+' . $price['24h_change'];
				break;
			case ($change < 0):
					$em = $emoji['downwards'];
					$price_change = $price['24h_change'];
				break;	
			default:
					$em = $emoji['downwards'];
					$price_change = '+' . $price['24h_change'];
				break;
		}

        $message .= $emoji['bank'] . " Market Cap: $" . $market_price . $market_price_postfix . " (". $marketcap_percentage ."%)\r\n";
		if($price['24h_volume']){
            $message .= $emoji['money_wings'] . " Volume: $" . round(($price['24h_volume'] / 1000), 1) . "K (". $change_percentage ."%)\r\n";
        }
		$message .= $em . " Price: " . '$' . $price['price_usd'] . " (". $price_change ."%|24h)\r\n";
	}

	return $message;
} 



function getAllTelegramChats()
{
	$db  = new PDO('sqlite:'.__DIR__.'/db.sqlite') or die("cannot open the database");
	$stmt = $db->prepare("SELECT * FROM telegram_users");
	$stmt->execute();
	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function insertTokenSupply($tokenSupply)
{
	$price = getPricing();

	if($price) {
        $market = $tokenSupply * $price['price_usd'];
        if($market < 1000000) {
            $marketcap = round($market / 1000);
        } else {
            $marketcap = round($market / 1000000, 2);
        }
    } else {
	    $marketcap = 0;
    }

    $volume = $price['24h_volume'];

    $db  = new PDO('sqlite:'.__DIR__.'/db.sqlite') or die("cannot open the database");
	$stmt = $db->prepare("INSERT INTO tokens_history VALUES (?, ?, ?, ?)");
	$stmt->bindParam(1, $value);
	$stmt->bindParam(2, $date);
	$stmt->bindParam(3, $t_volume);
	$stmt->bindParam(4, $t_markecap);
	$value = $tokenSupply;
	$date = date('Y-m-d H:i:s');
	$t_volume = $volume;
	$t_markecap = $marketcap;
	return $stmt->execute();
}

function getLastTokenSupply()
{
    $db  = new PDO('sqlite:'.__DIR__.'/db.sqlite') or die("cannot open the database");
    $stmt = $db->prepare("SELECT * FROM 'tokens_history' ORDER BY datetime(created) DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result;
}

function getDailyAVG()
{
	$db  = new PDO('sqlite:'.__DIR__.'/db.sqlite') or die("cannot open the database");
	$stmt = $db->prepare("SELECT AVG(tokens_burned) FROM (SELECT tokens_burned FROM 'daily_stats' ORDER BY date(day) DESC LIMIT 3) daily_stats");
	$stmt->execute();
	$result = $stmt->fetchColumn();
	return ($result && $result > 0) ? round($result) : '0';
}

function getFinalBomb($total, $daily_avg)
{
	$days_left = round((int)$total / (int)$daily_avg);
	return date('Y', strtotime("+$days_left days"));
}

function getPricing(){

	$url = 'https://api.coingecko.com/api/v3/coins/bomb/';
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$data = curl_exec($ch);
	curl_close($ch);
	$response = json_decode($data);

	if(isset($response->market_data)){
		$price = round($response->market_data->current_price->usd, 2);
		$change = round($response->market_data->price_change_percentage_24h, 2);
		$volume = round($response->market_data->total_volume->usd, 2);
		return ['price_usd' => $price, '24h_change' => $change, '24h_volume' => $volume];
	}

	return false;
}

function deleteUser($id)
{
	$db  = new PDO('sqlite:'.__DIR__.'/db.sqlite') or die("cannot open the database");
	$stmt = $db->prepare("DELETE FROM telegram_users WHERE chat_id = ?");
	$stmt->execute([$id]);
}














