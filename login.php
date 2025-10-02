<?php
$login = $array['LOGIN'];
$password = $array['PASSWORD'];

if (!$_SESSION["maps"]) {
  $inputLogin = $_POST["login"] ?? '';
  $inputPassword = $_POST["password"] ?? '';
  
  if ($inputLogin === $login && $inputPassword === $password) {
      $_SESSION["maps"] = true;
      header('Location: /');
      exit;
  } else if ($inputLogin || $inputPassword) {
      $error = "Identifiant ou mot de passe incorrect";
  }
  
  include __DIR__ . '/inc/head.php';
  include __DIR__ . '/inc/login.php';
  include __DIR__ . '/inc/end.php';
  die();
}
$maps = [];