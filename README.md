# Dragon Path Online 0.1

Playable MMORPG MVP built with Laravel, MySQL-ready storage, and Phaser.

## Features

- Phaser battlefield scene
- Player save/load by browser token
- HP, MP, EXP, level, ATK, DEF
- Turn-by-turn monster battle
- Dice attack results for player and monster
- Monster target selection
- Class skills with MP cost and cooldown
- EXP and level up to 100
- Level 10 class choices
- Level 20 class branches in seed data
- Laravel routes/controllers/models/migrations
- Railway deploy notes

## Run Local

```bash
composer install
npm install
php artisan migrate:fresh --seed
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000`.

## Deploy

See [DEPLOYMENT.md](DEPLOYMENT.md) and [DEPLOY_CHECKLIST.md](DEPLOY_CHECKLIST.md).

Recommended MVP hosting:

```text
Railway: Laravel frontend + backend
Railway MySQL: database
GitHub: source control
```

## Version Scope

This is intentionally small so it can go online quickly. Current 0.1.3 scope supports exploration, dice encounters, turn-by-turn combat, monster targeting, MP-limited skills, cooldowns, EXP, level up, and early class evolution.

## Patch Notes

- 0.1.1: Combat changed from auto-resolve to one player turn plus one monster counter per click.
- 0.1.2: Multi-monster target selection and per-monster HP tracking.
- 0.1.3: Multiple skills per class, MP cost, cooldown, and UI disabled states.
- 0.1.4: Railway start script, production seed step, health check, and deploy checklist.
