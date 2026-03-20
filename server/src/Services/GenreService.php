<?php

namespace App\Services;

use App\Repository\GenreRepository;
use App\Repository\AlbumRepository;
use App\Models\Genre;
use Exception;
use RuntimeException;

class GenreService
{
    private GenreRepository $genreRepository;
    private AlbumRepository $albumRepository;

    public function __construct(string $userId)
    {
        $this->genreRepository = new GenreRepository($userId);
        $this->albumRepository = new AlbumRepository($userId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllGenres(): array
    {
        try {
            $genres = $this->genreRepository->findAll();
            return array_map(fn($genre) => $genre->toArray(), $genres);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to get genres: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchGenres(string $query, int $limit = 20): array
    {
        try {
            return $this->genreRepository->search($query, $limit);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to search genres: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function getAlbumsByGenre(string $genre, array $params = []): array
    {
        try {
            $albums = $this->albumRepository->findByGenre($genre, $params);
            return array_map(fn($album) => $album->toArray(), $albums);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to get albums by genre: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getGenreStatistics(): array
    {
        try {
            $genres = $this->genreRepository->findAll();

            $totalGenres = count($genres);
            $totalAlbums = array_sum(array_map(fn($genre) => $genre->getAlbumCount(), $genres));
            $totalArtists = array_sum(array_map(fn($genre) => $genre->getArtistCount(), $genres));

            return [
                'total_genres' => $totalGenres,
                'total_albums' => $totalAlbums,
                'total_artists' => $totalArtists,
                'most_popular_genre' => $this->getMostPopularGenre($genres),
                'genres_with_most_albums' => $this->getGenresWithMostAlbums($genres, 5)
            ];
        } catch (Exception $e) {
            throw new RuntimeException("Failed to get genre statistics: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param array<int, Genre> $genres
     * @return array<string, mixed>|null
     */
    private function getMostPopularGenre(array $genres): ?array
    {
        if ($genres === []) {
            return null;
        }

        $mostPopular = array_reduce($genres, function ($carry, $genre) {
            if ($carry === null || $genre->getAlbumCount() > $carry->getAlbumCount()) {
                return $genre;
            }
            return $carry;
        });

        return $mostPopular ? $mostPopular->toArray() : null;
    }

    /**
     * @param array<int, Genre> $genres
     * @return array<int, array<string, mixed>>
     */
    private function getGenresWithMostAlbums(array $genres, int $limit): array
    {
        // Sort genres by album count descending
        usort($genres, fn($a, $b) => $b->getAlbumCount() - $a->getAlbumCount());

        // Take top N genres
        $topGenres = array_slice($genres, 0, $limit);

        return array_map(fn($genre) => $genre->toArray(), $topGenres);
    }

    /**
     * @return string[]
     */
    public function validateGenreName(string $genre): array
    {
        $errors = [];

        if (in_array(trim($genre), ['', '0'], true)) {
            $errors[] = 'Genre name cannot be empty';
        }

        if (strlen($genre) > 100) {
            $errors[] = 'Genre name must be less than 100 characters';
        }

        // Check for invalid characters
        if (preg_match('/[<>"\']/', $genre)) {
            $errors[] = 'Genre name contains invalid characters';
        }

        return $errors;
    }
}
