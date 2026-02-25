<?php
declare(strict_types = 1);

// phpcs:disable PSR1.Files.SideEffects
require_once 'symfony_mailer.civix.php';
pathload()->addSearchDir(__DIR__ . '/dist');
pathload()->addNamespace('civicrm-symfony-mailer@6', ['SM6\\']);

// phpcs:enable

use CRM_SymfonyMailer_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function symfony_mailer_civicrm_config(\CRM_Core_Config $config): void {
  _symfony_mailer_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function symfony_mailer_civicrm_install(): void {
  _symfony_mailer_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function symfony_mailer_civicrm_enable(): void {
  _symfony_mailer_civix_civicrm_enable();
}
