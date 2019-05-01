<?php

new SQLite3('db.sqlite');

$db  = new PDO('sqlite:db.sqlite') or die("cannot open the database");
$query = 'CREATE TABLE IF NOT EXISTS telegram_users (
		  chat_id BIGINT
		)';
$db->query($query);

$query = 'CREATE TABLE IF NOT EXISTS tokens_history (
		  total_supply INT,
		  created DATETIME 
		);';
$db->query($query);	

$query = 'CREATE TABLE IF NOT EXISTS daily_stats (
		  tokens_burned INT,
		  day DATETIME 
		);';
$db->query($query);	




