<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use RuntimeException;
use Throwable;

final class DirectSmtpTransport
{
    /**
     * @param array{host:string,port:int,encryption:string,username:string,password:string,password_configured:bool,timeout:int} $smtp
     * @return array{success:bool,error_code:?string}
     */
    public function send(
        string $recipient,
        string $subject,
        string $body,
        array $smtp,
        string $fromName,
        string $fromAddress
    ): array {
        $stream = null;
        try {
            $recipient = trim($recipient);
            $fromAddress = trim($fromAddress);
            if (! EmailSettings::validEmail($recipient)) {
                return ['success' => false, 'error_code' => 'recipient_email_unavailable'];
            }
            if (! EmailSettings::validEmail($fromAddress)) {
                return ['success' => false, 'error_code' => 'sender_email_invalid'];
            }

            $host = trim((string) ($smtp['host'] ?? ''));
            $port = (int) ($smtp['port'] ?? 0);
            $encryption = (string) ($smtp['encryption'] ?? 'tls');
            $username = (string) ($smtp['username'] ?? '');
            $password = (string) ($smtp['password'] ?? '');
            $timeout = (int) ($smtp['timeout'] ?? 15);
            if ($host === '' || $port <= 0) {
                return ['success' => false, 'error_code' => 'smtp_not_configured'];
            }

            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'peer_name' => $host,
                    'allow_self_signed' => false,
                    'SNI_enabled' => true,
                ],
            ]);
            $scheme = $encryption === 'ssl' ? 'ssl' : 'tcp';
            $remote = sprintf('%s://%s:%d', $scheme, $host, $port);
            $errno = 0;
            $errstr = '';
            $stream = @stream_socket_client(
                $remote,
                $errno,
                $errstr,
                max(3, min(60, $timeout)),
                STREAM_CLIENT_CONNECT,
                $context
            );
            unset($errno, $errstr);
            if (! is_resource($stream)) {
                throw new RuntimeException('smtp_connect_failed');
            }
            stream_set_timeout($stream, max(3, min(60, $timeout)));
            $this->expect($stream, [220], 'smtp_banner_failed');

            $helo = $this->heloName();
            $this->command($stream, 'EHLO ' . $helo, [250], 'smtp_ehlo_failed');

            if ($encryption === 'tls') {
                $this->command($stream, 'STARTTLS', [220], 'smtp_starttls_failed');
                $crypto = @stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($crypto !== true) {
                    throw new RuntimeException('smtp_tls_failed');
                }
                $this->command($stream, 'EHLO ' . $helo, [250], 'smtp_ehlo_after_tls_failed');
            }

            if ($username !== '' || $password !== '') {
                if ($username === '' || $password === '') {
                    throw new RuntimeException('smtp_auth_credentials_incomplete');
                }
                $this->command($stream, 'AUTH LOGIN', [334], 'smtp_auth_not_supported');
                $this->command($stream, base64_encode($username), [334], 'smtp_auth_username_failed');
                $this->command($stream, base64_encode($password), [235], 'smtp_auth_failed');
            }

            $this->command($stream, 'MAIL FROM:<' . $fromAddress . '>', [250], 'smtp_mail_from_failed');
            $this->command($stream, 'RCPT TO:<' . $recipient . '>', [250, 251], 'smtp_recipient_rejected');
            $this->command($stream, 'DATA', [354], 'smtp_data_rejected');
            $this->write($stream, $this->message($recipient, $subject, $body, $fromName, $fromAddress) . "\r\n.\r\n");
            $this->expect($stream, [250], 'smtp_message_rejected');

            try {
                $this->command($stream, 'QUIT', [221], 'smtp_quit_failed');
            } catch (Throwable) {
                // Delivery already completed; QUIT failure does not make the sent message fail.
            }
            fclose($stream);
            return ['success' => true, 'error_code' => null];
        } catch (Throwable $error) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            $code = $error->getMessage();
            if (! preg_match('/^smtp_[a-z0-9_]+$/', $code)) {
                $code = 'smtp_transport_failed';
            }
            return ['success' => false, 'error_code' => $code];
        }
    }

    /** @param resource $stream @param list<int> $expected */
    private function command($stream, string $command, array $expected, string $errorCode): void
    {
        $this->write($stream, $command . "\r\n");
        $this->expect($stream, $expected, $errorCode);
    }

    /** @param resource $stream @param list<int> $expected */
    private function expect($stream, array $expected, string $errorCode): void
    {
        $response = $this->readResponse($stream);
        $code = (int) substr($response, 0, 3);
        if (! in_array($code, $expected, true)) {
            throw new RuntimeException($errorCode);
        }
    }

    /** @param resource $stream */
    private function readResponse($stream): string
    {
        $response = '';
        while (! feof($stream)) {
            $line = fgets($stream, 4096);
            if ($line === false) {
                break;
            }
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] !== '-') {
                break;
            }
        }
        $meta = stream_get_meta_data($stream);
        if (! empty($meta['timed_out'])) {
            throw new RuntimeException('smtp_timeout');
        }
        if ($response === '' || ! preg_match('/^[0-9]{3}/', $response)) {
            throw new RuntimeException('smtp_invalid_response');
        }
        return $response;
    }

    /** @param resource $stream */
    private function write($stream, string $payload): void
    {
        $length = strlen($payload);
        $written = 0;
        while ($written < $length) {
            $chunk = fwrite($stream, substr($payload, $written));
            if ($chunk === false || $chunk === 0) {
                throw new RuntimeException('smtp_write_failed');
            }
            $written += $chunk;
        }
    }

    private function message(string $recipient, string $subject, string $body, string $fromName, string $fromAddress): string
    {
        $fromName = trim(preg_replace('/[\r\n]+/', ' ', $fromName) ?? 'Safe Contracts');
        $subject = trim(preg_replace('/[\r\n]+/', ' ', $subject) ?? '');
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $body = str_replace("\n", "\r\n", $body);
        $body = preg_replace('/(?m)^\./', '..', $body) ?? $body;
        $encodedFromName = $this->encodeHeader($fromName !== '' ? $fromName : 'Safe Contracts');
        $encodedSubject = $this->encodeHeader($subject);
        $messageIdHost = preg_replace('/[^a-z0-9.-]/i', '', $this->heloName()) ?: 'localhost';
        $messageId = '<' . bin2hex(random_bytes(12)) . '@' . $messageIdHost . '>';

        $headers = [
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'Message-ID: ' . $messageId,
            'From: ' . $encodedFromName . ' <' . $fromAddress . '>',
            'To: <' . $recipient . '>',
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        return implode("\r\n", $headers) . "\r\n\r\n" . rtrim($body, "\r\n");
    }

    private function encodeHeader(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (preg_match('/^[\x20-\x7E]+$/', $value)) {
            return $value;
        }
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function heloName(): string
    {
        $name = function_exists('gethostname') ? (string) gethostname() : 'localhost';
        $name = strtolower(trim($name));
        if ($name === '' || ! preg_match('/^[a-z0-9.-]+$/', $name)) {
            return 'localhost';
        }
        return $name;
    }
}
