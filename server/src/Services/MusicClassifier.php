<?php

namespace App\Services;

use Exception;

/**
 * MusicClassifier - Extracts music information from text using a trained model
 */
class MusicClassifier
{
    /** @var array<string> */
    private array $commonGenres = [
        'rock', 'pop', 'jazz', 'metal', 'hip hop', 'rap', 'classical',
        'electronic', 'folk', 'country', 'blues', 'reggae', 'punk'
    ];

    /**
     * Extract music information from text
     *
     * @param string $text The text to extract information from
     * @return array<string, array<int, string>> ['genres' => [...], 'decades' => [...], 'artists' => [...]]
     */
    public function extract(string $text): array
    {
        // First try to use the trained classifier if available
        $trainedResult = $this->extractUsingTrainedModel($text);
        if (!empty($trainedResult['genres']) || !empty($trainedResult['decades'])) {
            return $trainedResult;
        }

        // Last resort: manual extraction
        return $this->extractManually($text);
    }

    /**
     * Extract music information using a trained model
     *
     * @param string $text The text to extract information from
     * @return array<string, array<int, string>> ['genres' => [...], 'decades' => [...], 'artists' => [...]]
     */
    private function extractUsingTrainedModel(string $text): array
    {
        $result = [
            'genres' => [],
            'decades' => [],
            'artists' => []
        ];

        // Check if Python is available and model exists
        $modelPath = __DIR__ . '/../../app/models/music_classifier.pkl';
        if (!file_exists($modelPath) || !function_exists('exec')) {
            return $result;
        }

        try {
            // Save text to a temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'music_query_');
            file_put_contents($tempFile, $text);

            // Execute Python script to classify
            $command = sprintf(
                'python3 -c "import pickle; import sys; ' .
                'loaded = pickle.load(open(\'%s\', \'rb\')); ' .
                'query=open(\'%s\', \'r\').read(); ' .
                'vectorizer=loaded[\'vectorizer\']; classifier=loaded[\'classifier\']; ' .
                'genres=loaded[\'genres\']; decades=loaded[\'decades\']; ' .
                'prediction=classifier.predict(vectorizer.transform([query]))[0]; ' .
                'genre_pred=prediction[:len(genres)]; decade_pred=prediction[len(genres):]; ' .
                'top_genre_idx=genre_pred.argmax() if genre_pred.max() > 0.2 else -1; ' .
                'top_decade_idx=decade_pred.argmax() if decade_pred.max() > 0.2 else -1; ' .
                'print(genres[top_genre_idx] if top_genre_idx >= 0 else \'\', end=\'||\'); ' .
                'print(decades[top_decade_idx] if top_decade_idx >= 0 else \'\');"',
                escapeshellarg($modelPath),
                escapeshellarg($tempFile)
            );

            $output = '';
            exec($command, $output, $returnCode);

            // Clean up
            unlink($tempFile);

            if ($returnCode === 0 && $output !== []) {
                $parts = explode('||', $output[0]);

                if ($parts[0] !== '' && $parts[0] !== '0') {
                    $result['genres'][] = trim($parts[0]);
                }

                if (count($parts) > 1 && $parts[1] !== '' && $parts[1] !== '0') {
                    $result['decades'][] = trim($parts[1]);
                }
            }
        } catch (Exception $e) {
            error_log("Error using trained model: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Extract music information manually
     *
     * @param string $text The text to extract information from
     * @return array<string, array<int, string>> ['genres' => [...], 'decades' => [...], 'artists' => [...]]
     */
    private function extractManually(string $text): array
    {
        $result = [
            'genres' => [],
            'decades' => [],
            'artists' => []
        ];

        // Extrahiere Genres
        foreach ($this->commonGenres as $genre) {
            if (stripos($text, (string) $genre) !== false) {
                $result['genres'][] = $genre;
            }
        }

        // Extrahiere Jahrzehnte
        $decades = ['50', '60', '70', '80', '90', '2000', '2010', '2020'];
        foreach ($decades as $decade) {
            if (stripos($text, $decade) !== false) {
                $result['decades'][] = $decade . 's';
            }
        }

        return $result;
    }
}
