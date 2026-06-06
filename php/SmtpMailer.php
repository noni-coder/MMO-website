<?php
/**
 * ENSEMBLE MMO — Classe SMTP légère
 * Envoi d'e-mails via SMTP authentifié sans dépendance externe.
 * Compatible OVH, Ionos, Gmail, etc.
 */

class SmtpMailer
{
    private $host;
    private $port;
    private $secure;
    private $username;
    private $password;
    private $socket;
    private $log = [];

    public function __construct(string $host, int $port, string $secure, string $username, string $password)
    {
        $this->host     = $host;
        $this->port     = $port;
        $this->secure   = $secure;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Envoie un e-mail
     */
    public function send(string $fromEmail, string $fromName, string $toEmail, string $toName, string $subject, string $bodyText, string $bodyHtml = ''): bool
    {
        try {
            $this->connect();
            $this->ehlo();
            $this->authenticate();

            // MAIL FROM
            $this->sendCommand("MAIL FROM:<{$fromEmail}>", 250);

            // RCPT TO
            $this->sendCommand("RCPT TO:<{$toEmail}>", 250);

            // DATA
            $this->sendCommand("DATA", 354);

            // Construire le message
            $boundary = md5(uniqid(time()));
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>\r\n";
            $headers .= "To: =?UTF-8?B?" . base64_encode($toName) . "?= <{$toEmail}>\r\n";
            $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            $headers .= "Message-ID: <" . md5(uniqid()) . "@" . parse_url($this->host, PHP_URL_HOST) . ">\r\n";
            $headers .= "X-Mailer: Ensemble-MMO/1.0\r\n";

            if (!empty($bodyHtml)) {
                $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
                $body  = "--{$boundary}\r\n";
                $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $body .= chunk_split(base64_encode($bodyText)) . "\r\n";
                $body .= "--{$boundary}\r\n";
                $body .= "Content-Type: text/html; charset=UTF-8\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $body .= chunk_split(base64_encode($bodyHtml)) . "\r\n";
                $body .= "--{$boundary}--\r\n";
            } else {
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $headers .= "Content-Transfer-Encoding: base64\r\n";
                $body = chunk_split(base64_encode($bodyText));
            }

            // Protéger les points en début de ligne
            $message = $headers . "\r\n" . $body;
            $message = str_replace("\r\n.\r\n", "\r\n..\r\n", $message);

            $this->sendRaw($message);
            $this->sendCommand("\r\n.", 250);

            // QUIT
            $this->sendCommand("QUIT", 221);
            $this->disconnect();

            return true;
        } catch (\Exception $e) {
            $this->log[] = "ERROR: " . $e->getMessage();
            $this->disconnect();
            return false;
        }
    }

    private function connect(): void
    {
        $prefix = ($this->secure === 'ssl') ? 'ssl://' : '';
        $this->socket = @fsockopen(
            $prefix . $this->host,
            $this->port,
            $errno,
            $errstr,
            15
        );

        if (!$this->socket) {
            throw new \Exception("Connexion SMTP impossible: {$errstr} ({$errno})");
        }

        stream_set_timeout($this->socket, 15);
        $this->readResponse(220);
    }

    private function ehlo(): void
    {
        $hostname = gethostname() ?: 'localhost';
        $this->sendCommand("EHLO {$hostname}", 250);

        // STARTTLS si nécessaire
        if ($this->secure === 'tls') {
            $this->sendCommand("STARTTLS", 220);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \Exception("STARTTLS échoué");
            }
            $this->sendCommand("EHLO {$hostname}", 250);
        }
    }

    private function authenticate(): void
    {
        $this->sendCommand("AUTH LOGIN", 334);
        $this->sendCommand(base64_encode($this->username), 334);
        $this->sendCommand(base64_encode($this->password), 235);
    }

    private function sendCommand(string $command, int $expectedCode): string
    {
        $this->sendRaw($command);
        return $this->readResponse($expectedCode);
    }

    private function sendRaw(string $data): void
    {
        if (!$this->socket) {
            throw new \Exception("Socket non connecté");
        }
        fwrite($this->socket, $data . "\r\n");
        $this->log[] = "C: " . substr($data, 0, 100);
    }

    private function readResponse(int $expectedCode): string
    {
        $response = '';
        while ($line = fgets($this->socket, 512)) {
            $response .= $line;
            // Fin de réponse multi-ligne : code suivi d'un espace
            if (preg_match('/^\d{3} /m', $line)) {
                break;
            }
        }

        $this->log[] = "S: " . trim(substr($response, 0, 200));

        $code = (int)substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new \Exception("SMTP code {$code} inattendu (attendu: {$expectedCode}). Réponse: " . trim($response));
        }

        return $response;
    }

    private function disconnect(): void
    {
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    public function getLog(): array
    {
        return $this->log;
    }
}
