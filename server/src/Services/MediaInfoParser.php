<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * MediaInfo Parser - Simple wrapper for mediainfo CLI
 *
 * Supports bulk processing for massive performance gains!
 */
class MediaInfoParser
{
    private string $mediainfoPath;

    public function __construct(string $mediainfoPath = '/usr/bin/mediainfo')
    {
        if (!file_exists($mediainfoPath)) {
            throw new RuntimeException("MediaInfo binary not found at: $mediainfoPath");
        }

        $this->mediainfoPath = $mediainfoPath;
    }

    /**
     * Analyze audio file and extract metadata
     *
     * @param string $filePath Path to audio file
     * @return array<string, mixed> Normalized metadata
     * @throws RuntimeException If analysis fails
     */
    public function analyze(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("File not found: $filePath");
        }

        // Execute mediainfo with JSON output
        $command = sprintf(
            '%s --Output=JSON %s 2>&1',
            escapeshellcmd($this->mediainfoPath),
            escapeshellarg($filePath)
        );

        $output = shell_exec($command);

        if ($output === null || $output === false || $output === '' || $output === '0') {
            throw new RuntimeException("MediaInfo failed to analyze file: $filePath");
        }

        $data = json_decode($output, true);

        if (!is_array($data) || !isset($data['media']['track'])) {
            throw new RuntimeException("Invalid MediaInfo output for file: $filePath");
        }

        return $this->normalizeMetadata($data['media']['track'], $filePath);
    }

    /**
     * Analyze multiple files in bulk (MUCH faster - ~4-5x!)
     * Uses single mediainfo call for entire album
     *
     * @param array<string> $filePaths Array of file paths
     * @return array<string, array<string, mixed>> Array keyed by file path
     */
    public function analyzeBulk(array $filePaths): array
    {
        if ($filePaths === []) {
            return [];
        }

        // Build custom template with ALL fields we need
        // Order MUST match parseBulkOutput()!
        $fields = [
            'FileName',                // 0
            'CompleteName',            // 1
            'Title',                   // 2
            'Performer',               // 3
            'Album',                   // 4
            'Album_Performer',         // 5
            'Genre',                   // 6
            'Track/Position',          // 7
            'Track/Position/Total',    // 8
            'Part',                    // 9 (disc number)
            'Part/Position/Total',     // 10 (total discs)
            'Recorded_Date',           // 11
            'Duration',                // 12
            'FileSize',                // 13
            'OverallBitRate',          // 14
            'SamplingRate',            // 15
            'Channels',                // 16
            'BitDepth',                // 17
            'Compression_Mode',        // 18
            'Format',                  // 19
            'FileExtension',           // 20
            'Cover',                   // 21
        ];

        $template = implode('|', array_map(fn($f) => "%$f%", $fields));

        // Execute mediainfo with template for ALL files at once (single process!)
        $escapedFiles = array_map('escapeshellarg', $filePaths);
        $command = sprintf(
            '%s --Inform="General;%s\n" %s 2>&1',
            escapeshellcmd($this->mediainfoPath),
            $template,
            implode(' ', $escapedFiles)
        );

        $output = shell_exec($command);

        if ($output === null || $output === false || $output === '' || $output === '0') {
            throw new RuntimeException("MediaInfo bulk analysis failed");
        }

        return $this->parseBulkOutput($output, $filePaths);
    }

    /**
     * Parse bulk mediainfo output into normalized metadata
     *
     * @param string $output Raw output from mediainfo
     * @param array<string> $filePaths Original file paths
     * @return array<string, array<string, mixed>>
     */
    private function parseBulkOutput(string $output, array $filePaths): array
    {
        $lines = array_filter(explode("\n", trim($output)));
        $results = [];

        foreach ($lines as $line) {
            $parts = explode('|', $line);

            if (count($parts) < 22) {
                continue; // Invalid line
            }

            $filePath = $parts[1]; // CompleteName

            $results[$filePath] = [
                // Track metadata (for songs table)
                'title' => $parts[2] !== '' && $parts[2] !== '0' ? $parts[2] : pathinfo($filePath, PATHINFO_FILENAME),
                'artist' => $parts[3],
                'genre' => $parts[6],
                'year' => $this->extractYearFromString($parts[11] ?? ''),

                // Track numbers (for songs table)
                'track_number' => (int)$parts[7],
                'disc_number' => (int)$parts[9],

                // Audio properties (for songs table)
                'duration' => (int)round((float)($parts[12] ?? 0) / 1000), // Convert ms to seconds
                'bitrate' => $this->formatBitrate($parts[14] ?? 0),
                'size' => (int)($parts[13] ?? 0),
                'filetype' => strtolower($parts[20] ?? ''),

                // Album metadata (for album aggregation)
                'album' => $parts[4],
                'album_artist' => $parts[5],
                'total_tracks' => (int)$parts[8],
                'total_discs' => (int)($parts[10] ?? 0),
                'original_year' => 0, // Not directly available in template

                // Extended audio properties (nice to have)
                'sample_rate' => (int)($parts[15] ?? 0),
                'channels' => (int)($parts[16] ?? 0),
                'bit_depth' => (int)($parts[17] ?? 0),
                'lossless' => str_contains(strtolower($parts[18] ?? ''), 'lossless'),

                // File info
                'format' => $this->normalizeFiletype($parts[19] ?? '', strtolower($parts[20] ?? '')),
                'file_extension' => strtolower($parts[20] ?? ''),
                'file_size' => (int)($parts[13] ?? 0),

                // Cover
                'has_cover' => ($parts[21] ?? 'No') === 'Yes',

                // Defaults
                'compilation' => 0,
                'album_artist_sort' => '',
            ];
        }

        return $results;
    }

    /**
     * Normalize MediaInfo output to consistent structure
     *
     * @param array<int, array<string, mixed>> $tracks MediaInfo tracks
     * @param string $filePath Original file path
     * @return array<string, mixed> Normalized metadata
     */
    private function normalizeMetadata(array $tracks, string $filePath): array
    {
        $general = $this->findTrack($tracks, 'General');
        $audio = $this->findTrack($tracks, 'Audio');

        // Extract basic metadata
        $metadata = [
            // Track metadata
            'title' => $this->getString($general, 'Title')
                ?: $this->getString($general, 'Track')
                ?: pathinfo($filePath, PATHINFO_FILENAME),
            'artist' => $this->getString($general, 'Performer')
                ?: $this->getString($general, 'Artist')
                ?: '',
            'album' => $this->getString($general, 'Album') ?: '',
            'album_artist' => $this->getString($general, 'Album_Performer')
                ?: $this->getString($general, 'AlbumArtist')
                ?: '',
            'genre' => $this->getString($general, 'Genre') ?: '',

            // Track/disc numbers
            'track_number' => $this->getInt($general, 'Track_Position') ?: 0,
            'total_tracks' => $this->getInt($general, 'Track_Position_Total') ?: 0,
            'disc_number' => $this->getInt($general, 'Part') ?: 0,
            'total_discs' => $this->getInt($general, 'Part_Position_Total') ?: 0,

            // Dates
            'year' => $this->extractYear($general),
            'original_year' => $this->extractOriginalYear($general),

            // Audio properties
            'duration' => (int)round((float)($audio['Duration'] ?? $general['Duration'] ?? 0) / 1000), // Convert ms to seconds
            'bitrate' => $this->formatBitrate($audio['BitRate'] ?? $general['OverallBitRate'] ?? 0),
            'sample_rate' => $this->getInt($audio, 'SamplingRate') ?: 0,
            'channels' => $this->getInt($audio, 'Channels') ?: 0,
            'bit_depth' => $this->getInt($audio, 'BitDepth') ?: 0,
            'lossless' => isset($audio['Compression_Mode']) &&
                str_contains(strtolower($audio['Compression_Mode']), 'lossless'),

            // File info
            'format' => $this->normalizeFiletype($general['Format'] ?? '', strtolower($general['FileExtension'] ?? pathinfo($filePath, PATHINFO_EXTENSION))),
            'file_extension' => strtolower($general['FileExtension'] ?? pathinfo($filePath, PATHINFO_EXTENSION)),
            'file_size' => $this->getInt($general, 'FileSize') ?: filesize($filePath) ?: 0,

            // Additional metadata
            'compilation' => $this->isCompilation($general),
            'album_artist_sort' => $this->getString($general, 'extra.ALBUMARTISTSORT')
                ?: $this->getString($general, 'AlbumArtistSort')
                ?: '',

            // Cover art
            'has_cover' => ($general['Cover'] ?? 'No') === 'Yes',
        ];

        return $metadata;
    }

    /**
     * Find track by type in MediaInfo output
     *
     * @param array<int, array<string, mixed>> $tracks All tracks
     * @param string $type Track type (General, Audio, Video, etc.)
     * @return array<string, mixed> Track data or empty array
     */
    private function findTrack(array $tracks, string $type): array
    {
        foreach ($tracks as $track) {
            if (isset($track['@type']) && $track['@type'] === $type) {
                return $track;
            }
        }
        return [];
    }

    /**
     * Get string value from track data
     *
     * @param array<string, mixed> $track Track data
     * @param string $key Key (supports dot notation for nested keys)
     * @return string Value or empty string
     */
    private function getString(array $track, string $key): string
    {
        $value = $this->getValue($track, $key);
        return is_string($value) ? trim($value) : '';
    }

    /**
     * Get integer value from track data
     *
     * @param array<string, mixed> $track Track data
     * @param string $key Key
     * @return int Value or 0
     */
    private function getInt(array $track, string $key): int
    {
        $value = $this->getValue($track, $key);
        return is_numeric($value) ? (int)$value : 0;
    }

    /**
     * Get value from track data (supports dot notation)
     *
     * @param array<string, mixed> $track Track data
     * @param string $key Key (e.g., "extra.ORIGINALYEAR")
     * @return mixed Value or null
     */
    private function getValue(array $track, string $key): mixed
    {
        if (!str_contains($key, '.')) {
            return $track[$key] ?? null;
        }

        $keys = explode('.', $key);
        $value = $track;

        foreach ($keys as $k) {
            if (!is_array($value) || !isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Extract year from various date fields
     *
     * @param array<string, mixed> $general General track
     * @return int Year or 0
     */
    private function extractYear(array $general): int
    {
        // Try standard date field first
        $dateStr = $this->getString($general, 'Recorded_Date');

        if ($dateStr === '' || $dateStr === '0') {
            // Fallback to extra fields
            $dateStr = $this->getString($general, 'extra.DATE')
                ?: $this->getString($general, 'extra.YEAR')
                ?: $this->getString($general, 'extra.ORIGINALDATE')
                ?: '';
        }

        // Extract 4-digit year
        if (preg_match('/(\d{4})/', $dateStr, $matches)) {
            return (int)$matches[1];
        }

        return 0;
    }

    /**
     * Extract original year
     *
     * @param array<string, mixed> $general General track
     * @return int Original year or 0
     */
    private function extractOriginalYear(array $general): int
    {
        $yearStr = $this->getString($general, 'extra.ORIGINALYEAR')
            ?: $this->getString($general, 'extra.ORIGINALDATE')
            ?: '';

        // Extract 4-digit year
        if (preg_match('/(\d{4})/', $yearStr, $matches)) {
            return (int)$matches[1];
        }

        return 0;
    }

    /**
     * Extract 4-digit year from date string
     *
     * @param string $dateStr Date string
     * @return int Year or 0
     */
    private function extractYearFromString(string $dateStr): int
    {
        if (preg_match('/(\d{4})/', $dateStr, $matches)) {
            return (int)$matches[1];
        }
        return 0;
    }

    /**
     * Format bitrate for display
     *
     * @param mixed $bitrate Bitrate in bps
     * @return string Formatted bitrate (e.g., "320 kbps")
     */
    private function formatBitrate(mixed $bitrate): string
    {
        if (!is_numeric($bitrate) || (int)$bitrate === 0) {
            return 'N/A';
        }

        $kbps = (int)round((int)$bitrate / 1000);
        return $kbps . ' kbps';
    }

    /**
     * Normalize filetype to frontend-compatible format
     *
     * Converts MediaInfo format names to lowercase standard names
     * Expected by frontend: mp3, flac, wav, ogg, m4a, aac, wma, aiff, ape, mpc, opus
     *
     * @param string $format MediaInfo format (e.g., "MPEG Audio", "FLAC")
     * @param string $extension File extension fallback
     * @return string Normalized filetype
     */
    private function normalizeFiletype(string $format, string $extension): string
    {
        $format = strtolower(trim($format));
        $extension = strtolower(trim($extension));

        // Map MediaInfo formats to standard filetypes
        $formatMap = [
            'mpeg audio' => 'mp3',
            'mp3' => 'mp3',
            'flac' => 'flac',
            'wave' => 'wav',
            'wav' => 'wav',
            'vorbis' => 'ogg',
            'ogg' => 'ogg',
            'mpeg-4' => 'm4a',
            'm4a' => 'm4a',
            'aac' => 'aac',
            'wma' => 'wma',
            'aiff' => 'aiff',
            'ape' => 'ape',
            'musepack' => 'mpc',
            'musepack sv8' => 'mpc',
            'mpc' => 'mpc',
            'opus' => 'opus',
        ];

        // Try to map the format first
        if (isset($formatMap[$format])) {
            return $formatMap[$format];
        }

        // Fallback: use extension if it's a known type
        $validExtensions = ['mp3', 'flac', 'wav', 'ogg', 'm4a', 'aac', 'wma', 'aiff', 'ape', 'mpc', 'opus'];
        if (in_array($extension, $validExtensions, true)) {
            return $extension;
        }

        // Last resort: return extension as-is (lowercase)
        return $extension !== '' && $extension !== '0' ? $extension : 'unknown';
    }

    /**
     * Check if album is a compilation
     *
     * @param array<string, mixed> $general General track
     * @return int 1 if compilation, 0 otherwise
     */
    private function isCompilation(array $general): int
    {
        $compilation = $this->getString($general, 'Compilation')
            ?: $this->getString($general, 'extra.COMPILATION')
            ?: '';

        return in_array(strtolower($compilation), ['1', 'true', 'yes', 'y']) ? 1 : 0;
    }

    /**
     * Extract embedded cover art to file
     *
     * @param string $filePath Audio file path
     * @param string $outputPath Output path for cover image
     * @return bool True if cover was extracted
     */
    public function extractCoverArt(string $filePath, string $outputPath): bool
    {
        // MediaInfo can't extract cover art directly
        // We need to use ffmpeg for this
        $command = sprintf(
            'ffmpeg -i %s -an -vcodec copy %s -y 2>&1',
            escapeshellarg($filePath),
            escapeshellarg($outputPath)
        );

        exec($command, $output, $returnCode);

        return $returnCode === 0 && file_exists($outputPath);
    }
}
