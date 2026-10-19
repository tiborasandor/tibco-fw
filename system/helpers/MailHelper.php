<?php
declare(strict_types=1);

namespace system\helpers;

use PHPMailer\PHPMailer\PHPMailer;

/** SMTP mail via PHPMailer, settings: system.mail (no username = no SMTP auth). */
class MailHelper {

    private array $settings;

    public function __construct(\Psr\Container\ContainerInterface $container) {
        $this->settings = $container->get('settings')['system']['mail'] ?? [];
    }

    /**
     * @param array<string,string> $headers Raw extra headers (not for Bcc, use $bcc)
     * @param string[] $cc
     * @param string[] $bcc Not visible to the other recipients
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
