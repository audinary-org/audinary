# Music Library Guide

This document explains how to organize your music library so Audinary's media scanner can properly detect your files, metadata, cover art, and artist images.

## Folder Structure

Audinary expects a **two-level folder structure**: `Artist / Album / Tracks`.

```
music/
├── Linkin Park/
│   ├── artist.jpg
│   ├── Hybrid Theory/
│   │   ├── cover.jpg
│   │   ├── 01 Papercut.flac
│   │   ├── 02 One Step Closer.flac
│   │   └── ...
│   └── Meteora/
│       ├── cover.jpg
│       ├── 01 Don't Stay.flac
│       └── ...
├── Various Artists/
│   ├── artist.jpg
│   ├── Bravo Hits 100/
│   │   ├── cover.jpg
│   │   ├── 01 Track.mp3
│   │   └── ...
│   └── ...
└── ...
```

**Important:** Files that are not inside an `Artist/Album/` structure will be skipped by default. The first directory level is treated as the artist name, the second as the album name.

## Supported Audio Formats

`mp3` `flac` `wav` `ogg` `m4a` `aac` `wma` `aiff` `aif` `ape` `wv` `mpc` `opus` `ra` `rm` `mka`

The list of allowed extensions can be customized in the admin panel under settings.

## Metadata Tags

Audinary uses **MediaInfo** to read metadata from audio files. The following tags are recognized:

### Track-level tags

| Tag | Description | Fallback |
|-----|-------------|----------|
| `Title` | Track title | Filename without extension |
| `Performer` / `Artist` | Track artist | Folder name (artist level) |
| `Track/Position` | Track number | — |
| `Part` | Disc number | 1 |
| `Genre` | Genre | — |
| `Duration` | Track length | — |

### Album-level tags (aggregated from all tracks)

| Tag | Description | Fallback |
|-----|-------------|----------|
| `Album` | Album name | Folder name (album level) |
| `Album_Performer` | Album artist | Most common track artist |
| `Recorded_Date` / `DATE` / `YEAR` | Release year | — |
| `ORIGINALYEAR` | Original release year | — |
| `Track/Position/Total` | Total tracks | Highest track number |
| `Part/Position/Total` | Total discs | Highest disc number |
| `Compilation` | Compilation flag (`1`, `true`, `yes`) | — |

### Audio properties (read automatically)

Sample rate, channels, bit depth, bitrate, compression mode (lossless/lossy), and file format are extracted automatically and displayed in the UI.

## Tag-First Mode

By default, Audinary uses folder names to determine artist and album. With **tag-first mode** enabled (admin panel), embedded metadata tags take priority over folder names. This is useful if your folder structure doesn't match your tags.

| Mode | Artist source | Album source |
|------|---------------|--------------|
| Default | Folder name | Tag → Folder name |
| Tag-first | Tag → Folder name | Tag → Folder name |

## Cover Art

The scanner looks for album cover images in each album folder.

**Recognized filenames:** `cover`, `folder`, `front`
**Recognized extensions:** `jpg`, `jpeg`, `png`, `webp`

Search order: `cover.jpg` → `cover.png` → `folder.jpg` → `front.jpg` → ...

If no local cover image is found, the scanner extracts embedded cover art from audio files using FFmpeg.

Covers are automatically converted to WebP (500px, quality 80) with a thumbnail (250px) for the UI.

**Tip:** For best results, place a `cover.jpg` (at least 500x500px) in each album folder.

## Artist Images

The scanner looks for artist images in each artist folder (one level above albums).

**Recognized filenames:** `artist`, `band`, `photo`, `folder`
**Recognized extensions:** `jpg`, `jpeg`, `png`

Images are automatically cropped to square and converted to WebP (450px, quality 80) with a thumbnail (250px).

**Tip:** Place an `artist.jpg` in each artist folder for the best browsing experience.

## Color Gradients

Audinary automatically generates color gradients from cover art and artist images. These are used as background gradients in the UI when displaying albums and artists. No manual configuration is needed.

## Compilation Albums / Various Artists

For compilation albums (e.g., "Bravo Hits", soundtracks):

1. Place the album in a `Various Artists/` folder (or any artist name you prefer)
2. Set the `Album_Performer` / `Album Artist` tag in your audio files to "Various Artists"
3. Set the individual `Artist` / `Performer` tag on each track to the actual performer

Audinary will display the album under the folder artist while showing individual track artists in the player.

## Scanner Options

The media scanner can be run from the admin panel or via CLI:

```bash
php scripts/scan_media_data.php [options]
```

| Option | Description |
|--------|-------------|
| `--full` | Full rescan, ignoring modification times |
| `--update-artist-image` | Update artist images only |
| `--update-cover-images` | Update album covers only |
| `--update-gradients` | Recalculate color gradients |
| `--list-missing-artist-images` | List artists without images |
| `--list-missing-cover-images` | List albums without covers |
| `--fix-filetypes` | Normalize file type values |
| `--debug` | Enable debug logging |

By default, the scanner only processes files that have changed since the last scan (based on file modification time). Use `--full` to force a complete rescan.

## Recommended Tagging Tools

For best results, tag your audio files properly before scanning. Popular tools:

- [MusicBrainz Picard](https://picard.musicbrainz.org/) — Automatic tagging with MusicBrainz database
- [Mp3tag](https://www.mp3tag.de/) — Powerful tag editor for Windows
- [beets](https://beets.io/) — Command-line music organizer and tagger
