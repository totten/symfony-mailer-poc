<?php

// Return the current version number for this library.

// For this civicrm-symfony-mailer@7.X.X.phar, we will copy the version-number from the eponymous symfony/mailer and append the hour.

$installed = json_decode(file_get_contents(
  __DIR__ . '/vendor/composer/installed.json'
), TRUE);

foreach ($installed['packages'] ?? [] as $package) {
  if ($package['name'] === 'symfony/mailer') {
     return $package['version_normalized'] . '.' . gmdate('YmdH');
  }
}

throw new \Exception("Failed to determine library version.");
