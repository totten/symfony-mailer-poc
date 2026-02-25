<?php
declare(strict_types = 1);
namespace Civi\SymfonyMailer;

use Civi\FlexMailer\Event\SendBatchEvent;
use Civi\FlexMailer\FlexMailerTask;
use Civi\FlexMailer\Listener\IsActiveTrait;
use CRM_SymfonyMailer_ExtensionUtil as E;
use Civi\Core\Service\AutoService;

/**
 * Basic listener for the 'civi.flexmailer.send'. This assumes that the
 * message-sending API will send one message at a time.
 *
 * This will detect exceptions (per-message) and track mailing status accordingly.
 */
abstract class BasicSender extends AutoService {

  public static function createOutcomeOk(): array {
    return ['status' => 'ok'];
  }

  public static function createOutcomeError(?\Throwable $e, $smtpCode = NULL, $smtpMessage = NULL): array {
    return ['status' => 'error', 'exception' => $e, 'smtpCode' => $smtpCode, 'smtpMessage' => $smtpMessage];
  }

  use IsActiveTrait;

  const BULK_MAIL_INSERT_COUNT = 10;

  /**
   * Send the message for a single task.
   *
   * @param \Civi\FlexMailer\FlexMailerTask $task
   * @return mixed
   *   This should return success (`createOutcomeOk()`) or an error (`createOutcomeError()`)
   */
  abstract public function sendMessage(FlexMailerTask $task): mixed;

  /**
   * Do setup when starting the batch
   *
   * Example: $this->mailer = \Civi::service('pear_mail');
   */
  public function onStart(): void {
  }

  /**
   * Do tear-down after repeated failures.
   *
   * Example: $mailer->disconnect();
   *
   * @return void
   */
  public function onAbort(): void {
  }

  public function onSend(SendBatchEvent $e) {
    static $smtpConnectionErrors = 0;

    if (!$this->isActive() || !_symfony_mailer_dsn()) {
      return;
    }

    $e->stopPropagation();

    $job = $e->getJob();
    $mailing = $e->getMailing();
    $job_date = \CRM_Utils_Date::isoToMysql($job->scheduled_date);
    $this->onStart();

    $targetParams = $deliveredParams = [];
    $count = 0;
    $retryBatch = FALSE;

    foreach ($e->getTasks() as $key => $task) {
      /** @var \Civi\FlexMailer\FlexMailerTask $task */
      if (!$task->hasContent()) {
        continue;
      }

      $params = $task->getMailParams();
      if (isset($params['abortMailSend']) && $params['abortMailSend']) {
        continue;
      }

      $outcome = $this->sendMessage($task);

      if ($outcome['status'] !== 'ok') {
        if ($this->isTemporaryError($outcome)) {
          // lets log this message and code
          $errorMessage = $outcome['exception']->getMessage();
          $code = $outcome['exception']->getCode();
          \CRM_Core_Error::debug_log_message("SMTP Socket Error or failed to set sender error. Message: $errorMessage, Code: $code");

          // these are socket write errors which most likely means smtp connection errors
          // lets skip them and reconnect.
          $smtpConnectionErrors++;
          if ($smtpConnectionErrors <= 5) {
            $this->onAbort();
            $retryBatch = TRUE;
            continue;
          }

          // seems like we have too many of them in a row, we should
          // write stuff to disk and abort the cron job
          $job->writeToDB($deliveredParams, $targetParams, $mailing, $job_date);

          \CRM_Core_Error::debug_log_message("Too many SMTP Socket Errors. Exiting");
          \CRM_Utils_System::civiExit();
        }
        else {
          $this->recordBounce($job, $task, $exception->getMessage());
        }
      }
      else {
        // Register the delivery event.
        $deliveredParams[] = $task->getEventQueueId();
        $targetParams[] = $task->getContactId();

        $count++;
        if ($count % self::BULK_MAIL_INSERT_COUNT == 0) {
          $job->writeToDB($deliveredParams, $targetParams, $mailing, $job_date);
          $count = 0;

          // hack to stop mailing job at run time, CRM-4246.
          // to avoid making too many DB calls for this rare case
          // lets do it when we snapshot
          $status = \CRM_Core_DAO::getFieldValue(
            'CRM_Mailing_DAO_MailingJob',
            $job->id,
            'status',
            'id',
            TRUE
          );

          if ($status != 'Running') {
            $e->setCompleted(FALSE);
            return;
          }
        }
      }

      unset($exception);

      // seems like a successful delivery or bounce, lets decrement error count
      // only if we have smtp connection errors
      if ($smtpConnectionErrors > 0) {
        $smtpConnectionErrors--;
      }

      // If we have enabled the Throttle option, this is the time to enforce it.
      $mailThrottleTime = \CRM_Core_Config::singleton()->mailThrottleTime;
      if (!empty($mailThrottleTime)) {
        usleep((int) $mailThrottleTime);
      }
    }

    $completed = $job->writeToDB(
      $deliveredParams,
      $targetParams,
      $mailing,
      $job_date
    );
    if ($retryBatch) {
      $completed = FALSE;
    }
    $e->setCompleted($completed);
  }

  /**
   * Determine if an SMTP error is temporary or permanent.
   *
   * @param array $outcome
   * @return bool
   *   TRUE - Temporary/retriable error
   *   FALSE - Permanent/non-retriable error
   */
  protected function isTemporaryError(array $outcome): bool {
    $userMessage = $outcome['exception']->getMessage();
    $smtpCode = $outcome['smtpCode'] ?? NULL;
    $smtpMessage = $outcome['smtpMessage'] ?? NULL;

    if (str_contains($userMessage, 'Failed to write to socket')) {
      return TRUE;
    }

    // Register 5xx SMTP response code (permanent failure) as bounce.
    if (isset($smtpCode[0]) && $smtpCode[0] === '5') {
      return FALSE;
    }

    // Consider SMTP Erorr 450, class 4.1.2 "Domain not found", as permanent failures if the corresponding setting is enabled
    if ($smtpCode === '450' && \Civi::settings()->get('smtp_450_is_permanent')) {
      if ($smtpMessage === '4.1.2') {
        return FALSE;
      }
    }

    if (str_contains($userMessage, 'Failed to set sender')) {
      return TRUE;
    }

    if (str_contains($userMessage, 'Failed to add recipient')) {
      return TRUE;
    }

    if (str_contains($userMessage, 'Failed to send data')) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * @param \CRM_Mailing_BAO_MailingJob $job
   * @param \Civi\FlexMailer\FlexMailerTask $task
   * @param string $errorMessage
   */
  protected function recordBounce($job, $task, $errorMessage) {
    $params = [
      'event_queue_id' => $task->getEventQueueId(),
      'job_id' => $job->id,
      'hash' => $task->getHash(),
    ];
    $params = array_merge($params,
      \CRM_Mailing_BAO_BouncePattern::match($errorMessage)
    );
    \CRM_Mailing_Event_BAO_MailingEventBounce::recordBounce($params);
  }

}
