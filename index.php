<?php

require_once __DIR__ . '/classes/autoload.php';

if (!file_exists(__DIR__ . '/.env')) {
  die('Un fichier est manquant');
}

session_start();

(new AppController(__DIR__))->run();
