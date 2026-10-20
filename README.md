# PGAEV

Proyecto de investigación universitaria. Sistema web con gamificación para actividades de curso.

**Autor:** Santiago Cuellar

- `backend/` — API en Laravel  
- `frontend/` — interfaz en Vue  

## Instalación rápida

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

```bash
cd frontend
npm ci
npm run dev
```

Frontend: `http://localhost:5173`  
API: `http://127.0.0.1:8000/api`  
Salud: `GET /api/health` (`database: ok`)

Contenedores (API php-fpm + nginx, Postgres 16, SPA en nginx):

```bash
docker compose up --build
```

SPA: `http://localhost:8080` (proxy `/api` → app). CORS: `CORS_ALLOWED_ORIGINS`.

Datos de demostración (cuentas locales): `backend/README_DEMO.md`.  
Lecturas locales (k6 y resultados de bench): `perf/`.