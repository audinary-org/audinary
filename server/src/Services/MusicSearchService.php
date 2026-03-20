<?php

namespace App\Services;

use App\Repository\SongRepository;
use App\Repository\AlbumRepository;
use App\Repository\ArtistRepository;
use App\Repository\GenreRepository;
use Exception;

class MusicSearchService
{
    private SongRepository $songRepository;
    private AlbumRepository $albumRepository;
    private ArtistRepository $artistRepository;
    private GenreRepository $genreRepository;

    public function __construct(string $userId)
    {
        $this->songRepository = new SongRepository($userId);
        $this->albumRepository = new AlbumRepository($userId);
        $this->artistRepository = new ArtistRepository($userId);
        $this->genreRepository = new GenreRepository($userId);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function searchAll(string $query, int $limit = 5): array
    {
        if (in_array(trim($query), ['', '0'], true)) {
            return [
                'albums' => [],
                'artists' => [],
                'songs' => [],
                'genres' => []
            ];
        }

        $like = '%' . $query . '%';

        error_log("DEBUG: MusicSearchService::searchAll() called with query='$query', limit=$limit");
        error_log("DEBUG: Formatted like parameter: '$like'");

        $result = [];

        try {
            error_log("DEBUG: About to call albumRepository->searchQuick()");
            $result['albums'] = $this->albumRepository->searchQuick($like, $limit);
            error_log("DEBUG: albumRepository->searchQuick() completed, found " . count($result['albums']) . " albums");
        } catch (Exception $e) {
            error_log("DEBUG: Error in albumRepository->searchQuick(): " . $e->getMessage());
            throw $e;
        }

        try {
            error_log("DEBUG: About to call artistRepository->searchQuick()");
            $artists = $this->artistRepository->searchQuick($like, $limit);
            $result['artists'] = array_map(fn($artist) => $artist->toArray(), $artists);
            error_log("DEBUG: artistRepository->searchQuick() completed, found " . count($result['artists']) . " artists");
        } catch (Exception $e) {
            error_log("DEBUG: Error in artistRepository->searchQuick(): " . $e->getMessage());
            throw $e;
        }

        try {
            error_log("DEBUG: About to call songRepository->searchQuick()");
            $result['songs'] = $this->songRepository->searchQuick($like, $limit);
            error_log("DEBUG: songRepository->searchQuick() completed, found " . count($result['songs']) . " songs");
        } catch (Exception $e) {
            error_log("DEBUG: Error in songRepository->searchQuick(): " . $e->getMessage());
            throw $e;
        }

        try {
            error_log("DEBUG: About to call genreRepository->searchQuick()");
            $result['genres'] = $this->genreRepository->searchQuick($query, $limit);
            error_log("DEBUG: genreRepository->searchQuick() completed, found " . count($result['genres']) . " genres");
        } catch (Exception $e) {
            error_log("DEBUG: Error in genreRepository->searchQuick(): " . $e->getMessage());
            throw $e;
        }

        error_log("DEBUG: MusicSearchService::searchAll() completed successfully");
        return $result;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function searchSongs(string $query, array $params = []): array
    {
        $params['search'] = $query;
        $songs = $this->songRepository->findAll($params);
        return array_map(fn($song) => $song->toArray(), $songs);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function searchAlbums(string $query, array $params = []): array
    {
        $params['search'] = $query;
        $albums = $this->albumRepository->findAll($params);
        return array_map(fn($album) => $album->toArray(), $albums);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function searchArtists(string $query, array $params = []): array
    {
        $params['search'] = $query;
        $artists = $this->artistRepository->findAll($params);
        return array_map(fn($artist) => $artist->toArray(), $artists);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchGenres(string $query, int $limit = 20): array
    {
        return $this->genreRepository->search($query, $limit);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getSuggestions(string $query, int $limit = 10): array
    {
        return $this->searchAll($query, $limit);
    }
}
