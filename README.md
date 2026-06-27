# Maritime CRM

Maritime CRM is a streamlined customer relationship platform built for boat dealerships. It provides lead tracking, inventory management, deal workflows, and customer communication tools tailored for the marine sales industry. This system focuses on clarity, efficiency, and dealership specific operations.

## Production optimization

After each deploy (and after changing `.env` secrets, run `php artisan config:clear` first), warm Laravel caches:

```bash
./scripts/optimize-production.sh
```

This runs `config:cache`, `route:cache`, `view:cache`, and `event:cache`. Frontend assets should be built with `npm run build` before deploy.

Optional env tuning:

- `DASHBOARD_CACHE_ENABLED=true` — Redis cache for tenant dashboard sections (default on)
- `DASHBOARD_CACHE_TTL_SECONDS=90` — how long dashboard data is reused per user/filter scope
