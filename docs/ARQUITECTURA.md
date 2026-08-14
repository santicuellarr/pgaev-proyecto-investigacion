# Arquitectura PGAEV

## Resumen

- **Backend:** Laravel (`backend/`) — API REST, autenticación Sanctum, gamificación, OpenAI, reportes.
- **Frontend:** Vue 3 + Vite (`frontend/`) — paneles estudiante/docente, tienda de recompensas, leaderboard.

## Flujo

1. El frontend consume la API en `backend/routes/api.php`.
2. Autenticación con tokens Sanctum.
3. Base de datos SQLite/MySQL según `.env`.

## Despliegue local

- Backend: `composer install`, `php artisan migrate --seed`, `php artisan serve`
- Frontend: `npm install`, `npm run dev`
