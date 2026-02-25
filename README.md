# symfony_mailer (proof of concept)

Replaces the delivery backend for CiviCRM mailings -- switching from PEAR Mail to Symfony Mailer.  In theory, Symfony Mailer is
updated more frequently and may have more compatibility fixes.

This implementation was written circa Feb 2026 to help assess utility and feasibility.  Assume that it is incomplete.
(Remove this message if the status changes.)

_This is an [extension for CiviCRM](https://docs.civicrm.org/sysadmin/en/latest/customize/extensions/), licensed under [AGPL-3.0](LICENSE.txt)._

## Getting Started

* Install the extension
* In `civicrm.settings.php`, create a "DSN" string, e.g.

    ```php
    define('CIVICRM_SYMFONY_MAILER_DSN', 'native://default');
    define('CIVICRM_SYMFONY_MAILER_DSN', 'smtp://localhost:25');
    define('CIVICRM_SYMFONY_MAILER_DSN', 'smtp://user:pass@smtp.example.com:25');
    define('CIVICRM_SYMFONY_MAILER_DSN', 'smtp://user:pass@smtp.example.com?peer_fingerprint=6A1CF3B08D175A284C30BC10DE19162307C7286E');
    define('CIVICRM_SYMFONY_MAILER_DSN', 'smtp://user:pass@smtp.example.com?verify_peer=0');
    define('CIVICRM_SYMFONY_MAILER_DSN', 'smtps://smtp.example.com?local_domain=example.org');
    define('CIVICRM_SYMFONY_MAILER_DSN', 'sendmail://default');
    define('CIVICRM_SYMFONY_MAILER_DSN', 'sendmail://default?command=/usr/sbin/sendmail%20-oi%20-t');
    define('CIVICRM_SYMFONY_MAILER_DSN', 'roundrobin(postmark+api://ID@default sendgrid+smtp://KEY@default)');
    ```

    For more DSN options, see https://symfony.com/doc/6.4/mailer.html

NOTE: If you do not configure `CIVICRM_SYMFONY_MAILER_DSN`, then the system will continue using PEAR Mail.

## Included transports

In Symfony Mailer, there are built-in transports (such as SMTP and Sendmail) and contributed transports (such as Amazon
SES or Sendgrid).  None of this has been tested.  But I've included a few in case someone wants to try them.

* `symfony/google-mailer`
* `symfony/amazon-mailer`
* `symfony/sendgrid-mailer`
* `symfony/mailgun-mailer`
* `symfony/mailjet-mailer`

## Known Issues

* Only implements FlexMailer support (CiviMail/Mosaico).
* Does not currently support other use-cases (e.g. transactional-emails). These other use-cases
  may have direct references to PEAR Mail. To convert them, we would need an adapter that
  allows PEAR Mail-consumers to delegate to Symfony Mailer. (There's a rough draft in the similar [phpmailer-poc](https://github.com/totten/phpmailer-poc).)
* The DSN requires extra setup. You could read `mailing_backend` and set this automatically.
* Need to detect SMTP error codes. (Without this, delivery tracking may be inaccurate.)
