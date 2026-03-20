# Audinary

**Hungry for Music**

A modern, responsive web-based music player for your personal music collection. Stream your library from anywhere with a clean interface, transcoding support, and public sharing.

For more information, visit [audinary.org](https://audinary.org).

## Features

- Stream your music library from any browser
- Native apps for [iOS and Android](https://audinary.org)
- On-the-fly transcoding (FLAC, WAV, etc. to AAC/MP3)
- Album, artist, and song browsing with cover art
- Playlist management with collaborative sharing
- Public share links with optional password protection and download
- Multi-user support with admin panel
- Media scanner with automatic metadata extraction
- Artist images and album gradients
- Wishlist with Last.fm integration
- Automated backups with retention policy
- Internationalization (English, German, French, Russian)

## Quick Start

### Requirements

- Docker and Docker Compose
- A music library accessible via local path, NFS, or SMB

### 1. Download the compose file

```bash
curl -O https://raw.githubusercontent.com/audinary-org/audinary/main/docker-compose.yml
```

### 2. Mount your music library

See the [Music Library Guide](docs/MEDIA_INFO.md) for details on folder structure, supported formats, tagging, and cover art.

Edit `docker-compose.yml` and configure the `music` volume to point to your library.

**Local folder:**
```yaml
volumes:
  music:
    driver: local
    driver_opts:
      type: none
      o: bind
      device: /path/to/your/music
```

**NFS share:**
```yaml
volumes:
  music:
    driver: local
    driver_opts:
      type: nfs
      o: addr=192.168.1.100,ro,nolock
      device: ":/exported/music"
```

**SMB/CIFS share:**
```yaml
volumes:
  music:
    driver: local
    driver_opts:
      type: cifs
      o: username=user,password=pass,ro
      device: "//192.168.1.100/music"
```

### 3. Start

```bash
docker compose up -d
```

Audinary is available at `http://localhost:8080`. Create your first account and run a media scan from the admin panel.

### 4. Optional: customize credentials

Override defaults via environment variables or a `.env` file:

```bash
DB_PASSWORD=your-secure-password
DB_NAME=audinary
DB_USER=audinary
```

## Tech Stack

| Component | Technology |
|-----------|-----------|
| Frontend | Vue.js 3, Pinia, Tailwind CSS, Vite |
| Backend | PHP 8.4, Slim 4, Monolog, PHP-JWT |
| Database | PostgreSQL 18 |
| Runtime | Nginx, PHP-FPM, Supervisor |
| Container | Alpine Linux (trafex/php-nginx) |

## Architecture

```
audinary/
├── client/          Vue.js 3 SPA (frontend)
├── server/
│   ├── public/      Entry point (index.php)
│   ├── src/         PHP source code
│   │   ├── Controllers/
│   │   ├── Services/
│   │   ├── Repository/
│   │   ├── Models/
│   │   ├── Middleware/
│   │   └── Interfaces/
│   ├── routes/      API route definitions
│   ├── migrations/  SQL migrations
│   └── scripts/     CLI tools and scheduler
├── Dockerfile       Multi-stage production build
└── docker-compose.yml
```

## Alternative: Prebuild Installation

If you prefer running Audinary without Docker on an existing server, see the [Prebuild Installation Guide](docs/PREBUILD_INSTALLATION.md).

## Community

Join us on [Telegram](https://t.me/audinary_app) for questions, feedback, and updates.

## Contributing

See [CONTRIBUTING.md](docs/CONTRIBUTING.md) for development setup and guidelines.

## License

This project is licensed under the [GNU Affero General Public License v3.0](https://www.gnu.org/licenses/agpl-3.0.html) (AGPL-3.0).

Copyright (c) 2025-2026 Daniel Hiller & contributors
