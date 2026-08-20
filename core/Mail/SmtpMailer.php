<?php

declare(strict_types=1);

namespace Core\Mail;

final class SmtpMailer
{
    /** @var resource|null */
    private $socket = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly string $password,
        private readonly string $encryption = 'tls'
    ) {
    }

    public function send(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $subject,
        string $htmlBody,
        ?string $textBody = null
    ): void {
        $this->connect();
        $hostname = gethostname() ?: 'localhost';

        try {
            $this->expect($this->command('EHLO ' . $hostname), 250);

            if ($this->encryption === 'tls') {
                $this->expect($this->command('STARTTLS'), 220);
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('No fue posible iniciar TLS con el servidor SMTP.');
                }
                $this->expect($this->command('EHLO ' . $hostname), 250);
            }

            if ($this->username !== '') {
                $this->expect($this->command('AUTH LOGIN'), 334);
                $this->expect($this->command(base64_encode($this->username)), 334);
                $this->expect($this->command(base64_encode($this->password)), 235);
            }

            $this->expect($this->command('MAIL FROM:<' . $fromEmail . '>'), 250);
            $this->expect($this->command('RCPT TO:<' . $toEmail . '>'), 250);
            $this->expect($this->command('DATA'), 354);

            $encodedSubject = $this->encodeHeader($subject);
            $encodedFrom = $this->encodeHeader($fromName) . ' <' . $fromEmail . '>';
            $textBody = $textBody ?? strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
            $boundary = 'b_' . bin2hex(random_bytes(8));

            $message = implode("\r\n", [
                'From: ' . $encodedFrom,
                'To: <' . $toEmail . '>',
                'Subject: ' . $encodedSubject,
                'MIME-Version: 1.0',
                'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
                '',
                '--' . $boundary,
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
                '',
                $textBody,
                '',
                '--' . $boundary,
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
                '',
                $htmlBody,
                '',
                '--' . $boundary . '--',
                '',
            ]);

            $this->write($message . "\r\n.");
            $this->expect($this->read(), 250);
            $this->command('QUIT');
        } finally {
            $this->disconnect();
        }
    }

    private function connect(): void
    {
        $target = $this->host . ':' . $this->port;
        $socket = @stream_socket_client(
            'tcp://' . $target,
            $errno,
            $errstr,
            20,
            STREAM_CLIENT_CONNECT
        );

        if (!is_resource($socket)) {
            throw new \RuntimeException('No fue posible conectar al servidor SMTP (' . $target . '): ' . $errstr);
        }

        stream_set_timeout($socket, 20);
        $this->socket = $socket;
        $this->expect($this->read(), 220);
    }

    private function disconnect(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }

        $this->socket = null;
    }

    private function command(string $command): string
    {
        $this->write($command);

        return $this->read();
    }

    private function write(string $payload): void
    {
        if (!is_resource($this->socket)) {
            throw new \RuntimeException('Conexión SMTP no disponible.');
        }

        $written = fwrite($this->socket, $payload . "\r\n");
        if ($written === false) {
            throw new \RuntimeException('No fue posible escribir en la conexión SMTP.');
        }
    }

    private function read(): string
    {
        if (!is_resource($this->socket)) {
            throw new \RuntimeException('Conexión SMTP no disponible.');
        }

        $response = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        if ($response === '') {
            throw new \RuntimeException('El servidor SMTP no respondió.');
        }

        return $response;
    }

    private function expect(string $response, int $code): void
    {
        if (!str_starts_with(trim($response), (string) $code)) {
            throw new \RuntimeException('Respuesta SMTP inesperada: ' . trim($response));
        }
    }

    private function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }

        return $value;
    }
}
