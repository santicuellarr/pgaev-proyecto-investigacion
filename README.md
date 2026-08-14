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
