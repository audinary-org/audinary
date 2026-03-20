<?php

// MPDHandler.php - Eine Klasse zur Integration von MPD in Ihre Anwendung

declare(strict_types=1);

/**
 * MPDHandler - Verwaltet die Audiowiedergabe über Music Player Daemon (MPD)
 */
class MPDHandler
{
    private string $host;
    private int $port;
    private string $password;
    /** @var resource|null */
    private $socket;
    /** @var \Psr\Log\LoggerInterface|null */
    private $logger;
    private bool $connected = false;
    /** @var array<string, mixed> */
    private array $config;

    /**
     * Constructor
     *
     * @param string $host     MPD-Host (Standard: localhost)
     * @param int    $port     MPD-Port (Standard: 6600)
     * @param string $password MPD-Passwort (optional)
     * @param \Psr\Log\LoggerInterface|null $logger   Logger-Instanz
     */
    public function __construct(string $host = 'localhost', int $port = 6600, string $password = '', $logger = null)
    {
        // Lade die Hauptkonfiguration
        $config = loadConfig();

        // Verwende die MPD-Konfiguration aus der Hauptkonfiguration oder die übergebenen Parameter
        $this->host = $config['mpd']['host'] ?? $host;
        $this->port = $config['mpd']['port'] ?? $port;
        $this->password = $config['mpd']['password'] ?? $password;
        $this->logger = $logger;

        // Konfiguration laden
        $this->config = $this->loadConfig();
    }

    /**
     * Laden der MPD-Konfiguration aus der Datenbank oder Standardwerte
     *
     * @return array<string, mixed>
     */
    private function loadConfig(): array
    {
        // Lade die Hauptkonfiguration
        $config = loadConfig();

        // Verwende die MPD-Konfiguration aus der Hauptkonfiguration oder Standardwerte
        return [
            'defaultVolume' => $config['mpd']['defaultVolume'] ?? 80,
            'replaygain' => $config['mpd']['replaygain'] ?? 'auto',
            'outputDevice' => $config['mpd']['outputDevice'] ?? 0
        ];
    }

    /**
     * Verbindung zu MPD herstellen
     */
    public function connect(): bool
    {
        if ($this->connected) {
            return true;
        }

        try {
            if ($this->logger) {
                $this->logger->info("Versuche MPD-Verbindung zu {$this->host}:{$this->port}");
            }

            $socket = @fsockopen($this->host, $this->port, $errno, $errstr, 30);
            if ($socket === false) {
                if ($this->logger) {
                    $this->logger->error("MPD-Verbindungsfehler: $errstr ($errno)");
                }
                return false;
            }
            $this->socket = $socket;

            // MPD-Begrüßungsnachricht lesen
            $response = fgets($this->socket, 1024);

            // Prüfen, ob MPD korrekt antwortet
            if ($response === '' || $response === '0' || $response === false || substr($response, 0, 6) !== 'OK MPD') {
                if ($this->logger) {
                    $this->logger->error("Ungültige MPD-Antwort: " . ($response ?: 'Keine Antwort'));
                }
                if (is_resource($this->socket)) {
                    fclose($this->socket);
                }
                return false;
            }

            // Passwort senden, wenn vorhanden
            if (!empty($this->password)) {
                $result = $this->sendCommand("password {$this->password}");
                if (isset($result['error'])) {
                    if ($this->logger) {
                        $this->logger->error("MPD-Passwort-Fehler: " . $result['error']);
                    }
                    return false;
                }
            }

            $this->connected = true;

            // Standardkonfiguration setzen
            $volResult = $this->sendCommand("setvol {$this->config['defaultVolume']}");
            if (isset($volResult['error']) && $this->logger) {
                $this->logger->error("MPD-Lautstärke-Fehler: " . $volResult['error']);
            }

            if ($this->logger) {
                $this->logger->info("MPD-Verbindung erfolgreich hergestellt");
            }

            return true;
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error("MPD-Verbindungsfehler: " . $e->getMessage());
                $this->logger->error($e->getTraceAsString());
            }
            return false;
        }
    }

    /**
     * Verbindung zu MPD trennen
     */
    public function disconnect(): void
    {
        if ($this->connected && $this->socket) {
            fclose($this->socket);
            $this->connected = false;
        }
    }

    /**
     * Befehl an MPD senden und Antwort lesen
     *
     * @return array<int|string, mixed>
     */
    public function sendCommand(string $command): array
    {
        if (!$this->connected && !$this->connect()) {
            return ['error' => 'Nicht mit MPD verbunden'];
        }

        $command = trim($command) . "\n";
        fwrite($this->socket, $command);

        $response = [];
        $line = fgets($this->socket, 1024);

        while ($line !== false && substr($line, 0, 2) !== 'OK' && substr($line, 0, 3) !== 'ACK') {
            $response[] = trim($line);
            $line = fgets($this->socket, 1024);
        }

        if ($line !== false) {
            $lineStr = $line;
            if (substr($lineStr, 0, 3) === 'ACK') {
                if ($this->logger) {
                    $this->logger->error("MPD-Fehler: $lineStr");
                }
                $response['error'] = trim($lineStr);
            }
        }

        return $response;
    }

    /**
     * Song abspielen
     *
     * @param string $filePath Pfad zur Datei (relativ zum MPD-Musikverzeichnis)
     * @param int    $position Startposition in Sekunden (optional)
     * @return bool Erfolg
     */
    public function playSong(string $filePath, int $position = 0): bool
    {
        // Playlist löschen
        $this->sendCommand("clear");

        // Song zur Playlist hinzufügen
        $filePath = $this->escapePath($filePath);
        $response = $this->sendCommand("add \"$filePath\"");

        if (isset($response['error'])) {
            return false;
        }

        // Wiedergabe starten
        $this->sendCommand("play 0");

        // Position setzen, wenn nicht 0
        if ($position > 0) {
            $this->sendCommand("seekcur $position");
        }

        if ($this->logger) {
            $this->logger->info("MPD: Wiedergabe von '$filePath' gestartet");
        }

        return true;
    }

    /**
     * Playlist abspielen
     *
     * @param array<int, string> $files Array mit Dateipfaden
     * @param int    $startIdx Index des ersten abzuspielenden Songs (optional)
     * @return bool Erfolg
     */
    public function playPlaylist(array $files, int $startIdx = 0): bool
    {
        // Playlist löschen
        $this->sendCommand("clear");

        // Songs zur Playlist hinzufügen
        foreach ($files as $file) {
            $file = $this->escapePath($file);
            $this->sendCommand("add \"$file\"");
        }

        // Wiedergabe starten
        $this->sendCommand("play $startIdx");

        if ($this->logger) {
            $this->logger->info("MPD: Playlist mit " . count($files) . " Titeln gestartet");
        }

        return true;
    }

    /**
     * Pausieren der Wiedergabe
     */
    public function pause(): bool
    {
        $this->sendCommand("pause 1");
        return true;
    }

    /**
     * Fortsetzen der Wiedergabe
     */
    public function resume(): bool
    {
        $this->sendCommand("pause 0");
        return true;
    }

    /**
     * Stoppen der Wiedergabe
     */
    public function stop(): bool
    {
        $this->sendCommand("stop");
        return true;
    }

    /**
     * Nächster Song
     */
    public function next(): bool
    {
        $this->sendCommand("next");
        return true;
    }

    /**
     * Vorheriger Song
     */
    public function previous(): bool
    {
        $this->sendCommand("previous");
        return true;
    }

    /**
     * Lautstärke einstellen
     *
     * @param int $volume Lautstärke (0-100)
     */
    public function setVolume(int $volume): bool
    {
        if ($volume < 0) {
            $volume = 0;
        }
        if ($volume > 100) {
            $volume = 100;
        }

        $this->sendCommand("setvol $volume");
        return true;
    }

    /**
     * Status abrufen
     *
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        $response = $this->sendCommand("status");

        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }

        $status = [];
        foreach ($response as $line) {
            $parts = explode(": ", $line, 2);
            if (count($parts) === 2) {
                $status[$parts[0]] = $parts[1];
            }
        }

        return $status;
    }

    /**
     * Aktuelle Song-Informationen abrufen
     *
     * @return array<string, mixed>
     */
    public function getCurrentSong(): array
    {
        $response = $this->sendCommand("currentsong");

        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }

        $song = [];
        foreach ($response as $line) {
            $parts = explode(": ", $line, 2);
            if (count($parts) === 2) {
                $song[$parts[0]] = $parts[1];
            }
        }

        return $song;
    }

    /**
     * Verfügbare Ausgabegeräte auflisten
     *
     * @return array<int|string, mixed>
     */
    public function listOutputs(): array
    {
        $response = $this->sendCommand("outputs");

        if (isset($response['error'])) {
            return ['error' => $response['error']];
        }

        $outputs = [];
        $currentOutput = [];

        foreach ($response as $line) {
            $parts = explode(": ", $line, 2);
            if (count($parts) === 2) {
                if ($parts[0] === 'outputid') {
                    if ($currentOutput !== []) {
                        $outputs[] = $currentOutput;
                    }
                    $currentOutput = ['id' => (int)$parts[1]];
                } else {
                    $currentOutput[$parts[0]] = $parts[1];
                }
            }
        }

        if ($currentOutput !== []) {
            $outputs[] = $currentOutput;
        }

        return $outputs;
    }

    /**
     * Ausgabegerät aktivieren/deaktivieren
     *
     * @param int  $outputId Ausgabegerät-ID
     * @param bool $enable   true zum Aktivieren, false zum Deaktivieren
     */
    public function setOutput(int $outputId, bool $enable): bool
    {
        $command = $enable ? "enableoutput" : "disableoutput";
        $this->sendCommand("$command $outputId");
        return true;
    }

    /**
     * Datenbank aktualisieren
     */
    public function updateDatabase(): bool
    {
        $this->sendCommand("update");
        return true;
    }

    /**
     * Pfad für MPD escapen
     */
    private function escapePath(string $path): string
    {
        // Lade die Konfiguration
        $config = loadConfig();
        $musicDir = $config['musicDir'];

        // Entfernen Sie den MPD-Musikverzeichnispfad, falls vorhanden
        if (strpos($path, (string) $musicDir) === 0) {
            $path = substr($path, strlen($musicDir));
        }

        // Sonderzeichen escapen
        return str_replace('"', '\\"', $path);
    }

    /**
     * Destruktor
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
