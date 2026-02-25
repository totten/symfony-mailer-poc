<?php
declare(strict_types = 1);

namespace Civi\SymfonyMailer;

use CRM_SymfonyMailer_ExtensionUtil as E;
use SM6\Symfony\Component\Mime\Address;
use SM6\Symfony\Component\Mime\Email;

class SymfonyMailerUtil {

  public static function convertMailParamsToEmail(array $mailParams): Email {
    // The general assumption is that key-value pairs in $mailParams should
    // pass through as email headers, but there are several special-cases
    // (e.g. 'toName', 'toEmail', 'text', 'html', 'attachments', 'headers').

    $mail = new Email();

    // 1. Consolidate: 'toName' and 'toEmail' should be 'To'.
    $mail->to(new Address($mailParams['toEmail'], $mailParams['toName'] ?? ''));
    unset($mailParams['toName']);
    unset($mailParams['toEmail']);

    // 2. Apply the other fields.
    foreach ($mailParams as $key => $value) {
      if (empty($value)) {
        continue;
      }

      switch ($key) {
        case 'text':
          $mail->text($mailParams['text']);
          break;

        case 'html':
          $mail->html($mailParams['html']);
          break;

        case 'attachments':
          foreach ($mailParams['attachments'] as $fileID => $attach) {
            $mail->attachFromPath($attach['fullPath'], $attach['cleanName'], $attach['mime_type']);
          }
          break;

        case 'headers':
          static::applyHeaders($mail, $value);
          break;

        default:
          static::applyHeaders($mail, [$key => $value]);
          break;
      }
    }

    $mail->getHeaders()->addHeader('X-CiviMail-Engine', 'SymfonyMailer'); // REVERT
    return $mail;
  }

  public static function applyHeaders(Email $mail, array $headers): void {
    foreach ($headers as $name => $value) {
      switch (strtolower($name)) {
        case 'mime-version':
          // Ignore
          break;

        case 'from':
          $mail->from($value);
          break;

        case 'to':
          $mail->to($value);
          break;

        case 'cc':
          $mail->cc($value);
          break;

        case 'bcc':
          $mail->bcc($value);
          break;

        case 'subject':
          $mail->subject($value);
          break;

        default:
          $mail->getHeaders()->addHeader($name, $value);
          break;
      }
    }
  }

}
