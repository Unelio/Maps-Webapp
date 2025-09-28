<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Maps</title>
<link rel="icon" type="image/png" href="favicon.png"> 
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
<meta http-equiv="Content-Security-Policy" content="
  default-src 'self';
  script-src 'self' 'unsafe-inline';
  style-src 'self' 'unsafe-inline';
  img-src 'self' data:;
  frame-src 'self';
">

<link rel="manifest" href="manifest.json">

<link rel="icon" sizes="512x512" href="favicon.png">
<meta name="mobile-web-app-capable" content="yes">
<meta name="application-name" content="Maps">

<meta name="apple-mobile-web-app-title" content="Maps">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="#fff">
<link rel="apple-touch-icon" sizes="512x512" href="favicon.png">

<meta name="msapplication-tooltip" content="Maps">
<meta name="msapplication-TileColor" content="#fff">
<meta name="msapplication-TileImage" content="favicon.png">

<link rel="stylesheet" type="text/css" href="fonts/fontello.css">

<?php
echo '<link rel="stylesheet" href="css/styles.css?t='.time().'">'."\n";
?>

<script>
if(typeof navigator.serviceWorker !== 'undefined'){
  navigator.serviceWorker.register('sw.js')
}
</script>
</head>