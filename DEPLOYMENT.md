# Dragon Path Online 0.1 Deployment

## Local

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run build
php artisan serve
```

Open:

```text
http://127.0.0.1:8000
```

## Railway MVP

Recommended services:

- Laravel app service
- MySQL database service

Environment variables for Railway:

```env
APP_NAME="Dragon Path Online"
APP_ENV=production
APP_KEY=base64:generate-this-locally
APP_DEBUG=false
APP_URL=https://your-railway-domain.up.railway.app

DB_CONNECTION=mysql
DB_HOST=your-mysql-host
DB_PORT=3306
DB_DATABASE=railway
DB_USERNAME=root
DB_PASSWORD=your-password

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Deploy flow:

1. Push this folder to GitHub.
2. Create a Railway project from the GitHub repo.
3. Add a MySQL database in the same Railway project.
4. Copy the MySQL connection variables into the Laravel service.
5. Generate `APP_KEY` locally with `php artisan key:generate --show` and add it to Railway variables.
6. Railway should detect `Dockerfile` automatically. If it does not, set the builder to `DOCKERFILE` and Dockerfile path to:

```text
Dockerfile
```

7. Set the start command to:

```bash
bash scripts/railway-start.sh
```

8. Generate a public domain in Railway.
9. Check `https://your-domain/health`.

For a shorter release checklist, see [DEPLOY_CHECKLIST.md](DEPLOY_CHECKLIST.md).

## Patch Plan

- 0.1: playable online MVP
- 0.1.1: turn-by-turn combat
- 0.1.2: monster targeting
- 0.1.3: class skills with MP cost and cooldown
- 0.1.4: deployment hardening, production env check, GitHub/Railway publish
- 0.2: level 20+ class expansion and more monsters
- 0.3: village/dungeon scenes
- 0.4: inventory and equipment
- 0.5: art pass with sprite sheets and animations
- 1.0: level 100 progression and account system
