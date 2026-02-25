<?php
declare(strict_types = 1);

namespace Civi\SymfonyMailer;

use Civi\FlexMailer\FlexMailerTask;
use CRM_SymfonyMailer_ExtensionUtil as E;
use SM6\Symfony\Component\Mailer\Mailer;
use SM6\Symfony\Component\Mailer\MailerInterface;
use SM6\Symfony\Component\Mailer\Transport;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Use Symfony Mailer as a backend to send jobs for FlexMailer.
 *
 * @service symfony_mailer.sender
 */
class SymfonySender extends BasicSender implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
      'civi.flexmailer.send' => ['onSend', 100],
    ];
  }

  protected ?MailerInterface $mailer = NULL;

  public function onAbort(): void {
    unset($this->mailer);
  }

  /**
   * @param \Civi\FlexMailer\FlexMailerTask $task
   *
   * @return mixed
   */
  public function sendMessage(FlexMailerTask $task): mixed {
    $mailer = $this->getSymfonyMailer();
    $email = SymfonyMailerUtil::convertMailParamsToEmail($task->getMailParams());

    try {
      $mailer->send($email);
      return static::createOutcomeOk();
    }
    catch (\Throwable $e) {
      // FIXME: This pattern is probably wrong for PHPMailer. Sketch based on PEAR Mail.
      $smtpCode = $smtpMessage = '';
      if (preg_match('/ \(code: (.+), response: /', $e->getMessage(), $matches)) {
        $smtpCode = $matches[1];
        $smtpMessage = $matches[2];
      }

      return static::createOutcomeError($e, $smtpCode, $smtpMessage);
    }
  }

  public function getSymfonyMailer(): MailerInterface {
    if ($this->mailer === NULL) {
      $transport = Transport::fromDsn(_symfony_mailer_dsn());
      $this->mailer = new Mailer($transport);
    }
    return $this->mailer;
  }

}
