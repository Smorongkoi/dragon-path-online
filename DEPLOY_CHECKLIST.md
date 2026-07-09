# Deploy Checklist

Use this when publishing Dragon Path Online 0.1.4.

## Before GitHub

```bash
php artisan test
npm run build
```

Make sure these files are committed:

- `railway.json`
- `scripts/railway-start.sh`
- `DEPLOYMENT.md`
- `README.md`
- Laravel app, database migrations, seeders, and Phaser assets

Do not commit:

- `.env`
- `vendor/`
- `node_modules/`
- `storage/logs/`
- `public/build/`

## GitHub

```bash
git init
git add .
git commit -m "Release Dragon Path Online 0.1.4"
git branch -M main
git remote add origin https://github.com/YOUR_NAME/YOUR_REPO.git
git push -u origin main
```

## Railway

1. Create a new Railway project from the GitHub repository.
2. Add a MySQL database service.
3. Copy MySQL variables into the Laravel service.
4. Add an `APP_KEY`.
5. Confirm `railway.json` is detected.
6. Deploy.
7. Open `/health` on the public domain.
8. Open `/` and test: explore map, fight, use skill, level/EXP save.

## Required Railway Variables

```env
APP_NAME="Dragon Path Online"
APP_ENV=production
APP_KEY=base64:your-generated-key
APP_DEBUG=false
APP_URL=https://your-domain.up.railway.app

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
