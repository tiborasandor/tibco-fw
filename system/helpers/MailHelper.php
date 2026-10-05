<?php
declare(strict_types=1);

namespace system\helpers;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sends e-mail over SMTP with PHPMailer. Connection and sender come from
 * settings.php (system.mail): host, port, username/password (optional -
 * without a username no SMTP auth is attempted, e.g. for an internal relay),
 * secure ('tls'/'ssl'/null), from_email, from_name.
 *
 * On failure PHPMailer's own \PHPMailer\PHPMailer\Exception is thrown - the
 * caller catches and logs it.
 */
class MailHelper {

    private array $settings;

    public function __construct(\Psr\Container\ContainerInterface $container) {
        $this->settings = $container->get('settings')['system']['mail'] ?? [];
    }

    /**
     * @param array<string,string> $headers Extra headers (e.g. List-Unsubscribe) - spam
     *   filters read a lot of signals from headers like these, not only from the body.
     *   Raw headers only: a "Bcc" put here would be visible to every recipient, use $bcc.
     * @param string[] $cc  CC addresses (real SMTP-level CC, shown in the Cc header).
     * @param string[] $bcc BCC addresses (real SMTP-level BCC: added only as RCPT TO,
     *   they don't appear in any header, the other recipients can't see them).
     */
    public function send(
        string $to,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        array $headers = [],
        array $cc = [],
        array $bcc = []
    ): bool {
        if (empty($this->settings['from_email'])) {
            throw new \RuntimeException('MailHelper: system.mail.from_email is not set in settings.php');
        }

        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = $this->settings['host'] ?? 'localhost';
        $mail->Port = (int) ($this->settings['port'] ?? 25);
        $mail->CharSet = 'UTF-8';

        if (!empty($this->settings['username'])) {
            $mail->SMTPAuth = true;
            $mail->Username = $this->settings['username'];
            $mail->Password = $this->settings['password'] ?? '';
        } else {
            $mail->SMTPAuth = false;
        }

        if (!empty($this->settings['secure'])) {
            $mail->SMTPSecure = $this->settings['secure'];
        } else {
            $mail->SMTPAutoTLS = false;
        }

        $mail->setFrom($this->settings['from_email'], $this->settings['from_name'] ?? '');
        $mail->addAddress($to);
        foreach ($cc as $address) {
            $mail->addCC($address);
        }
        foreach ($bcc as $address) {
            $mail->addBCC($address);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody ?: strip_tags($htmlBody);

        foreach ($headers as $name => $value) {
            $mail->addCustomHeader($name, $value);
        }

        return $mail->send();
    }

}
?>
