# Contributing to Audinary

## Development Setup

### Requirements

- PHP 8.4+
- Node.js 24+
- PostgreSQL 18
- FFmpeg and MediaInfo
- Composer

### Option A: Docker (recommended)

```bash
docker compose -f docker-compose.dev.yml up -d
```

This starts PostgreSQL and exposes it on port 5432. Then run the frontend and backend locally:

```bash
# Install dependencies
npm run install:all

# Start both frontend (port 3000) and backend (port 8080)
npm run dev
```

### Option B: Fully local

1. Install PostgreSQL 18 and create a database:
   ```sql
   CREATE DATABASE audinary;
   CREATE USER audinary WITH PASSWORD 'audinary';
   GRANT ALL PRIVILEGES ON DATABASE audinary TO audinary;
   ```

2. Copy the config and adjust if needed:
   ```bash
   cp server/src/config_sample.php server/var/config/config.php
   ```

3. Install dependencies and start:
   ```bash
   npm run install:all
   npm run dev
   ```

The frontend dev server runs on `http://localhost:3000` and proxies API requests to the PHP backend on port 8080.

### Running checks

```bash
# PHP static analysis
cd server && composer phpstan

# PHP code style
cd server && composer cs-check

# ESLint
cd client && npm run lint
```

## Project Structure

| Directory | Description |
|-----------|-------------|
| `client/` | Vue.js 3 SPA with Pinia stores, Tailwind CSS |
| `server/src/Controllers/` | API controllers (Slim 4 routes) |
| `server/src/Services/` | Business logic layer |
| `server/src/Repository/` | Database access (PDO + prepared statements) |
| `server/src/Middleware/` | JWT auth, CSRF, rate limiting, security headers |
| `server/src/Models/` | Data models |
| `server/migrations/` | SQL migration files (auto-applied) |
| `server/scripts/` | CLI tools (scanner, backups, cleanup) |

## Pull Requests

1. Fork the repository
2. Create a feature branch from `main`
3. Make your changes with clear, focused commits
4. Run `composer phpstan` and `npm run lint` before submitting
5. Open a pull request against `main`

Keep PRs small and focused on a single change. Separate features into individual branches.

## Code Style

- **PHP**: PSR-12, strict types, type hints on all signatures
- **Vue/JS**: ESLint + Prettier (configured in project)
- **SQL**: Prepared statements only, no raw interpolation
- Use meaningful names, keep functions focused, write comments only where logic is non-obvious
