<?php

spl_autoload_register(static function (string $className): void {
  $filePath = __DIR__ . '/' . $className . '.php';

  if (is_file($filePath)) {
    require_once $filePath;
  }
});