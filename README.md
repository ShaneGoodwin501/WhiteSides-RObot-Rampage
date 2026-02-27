# Whitesides Robot Rampage (Apache + PHP)

This repo contains a single-page browser game (`index.html`) plus a minimal PHP backend endpoint (`highscores.php`) used for **server-side high scores** stored in `highscores.json`.

The frontend talks to the backend at:

- `GET /highscores.php` → returns JSON array of `{ name: string, score: number }`
- `POST /highscores.php` with JSON body `{ name, score }` → updates scores and returns the latest top list

The game JS is hard-wired to this relative endpoint name:

- `const HS_URL = 'highscores.php';` :contentReference[oaicite:2]{index=2}
- Uses `fetch(..., { method: 'GET' })` and `fetch(..., { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(...) })` :contentReference[oaicite:3]{index=3}

The score data file is `highscores.json`. :contentReference[oaicite:4]{index=4}

---

## Repo contents

Expected files at repo root:

- `index.html` — the game UI + logic
- `highscores.php` — PHP API endpoint for reading/writing highscores
- `highscores.json` — initial score seed / persistent score store

---

## Quick start (Docker)

### 1) Add a `Dockerfile`

Create a `Dockerfile` in the repo root:

```Dockerfile
FROM php:8.2-apache

# (Optional) enable helpful Apache modules
RUN a2enmod headers rewrite

# Copy app into Apache web root
COPY index.html /var/www/html/index.html
COPY highscores.php /var/www/html/highscores.php
COPY highscores.json /var/www/html/highscores.json

# Ensure Apache user can write score file (needed for POST updates)
RUN chown -R www-data:www-data /var/www/html \
 && chmod 664 /var/www/html/highscores.json
