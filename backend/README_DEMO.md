# Demo PGAEV (solo entorno local)

Semilla abundante y reproducible para sustentación (objetivos 3, 4 y 5; RF-10 / RF-12 / RF-16).

**No uses estas cuentas fuera de tu máquina.** La contraseña es deliberadamente débil.

## Cómo cargar la demo

Sobre una base limpia:

```bash
cd backend
php artisan migrate:fresh --seeder=DemoSeeder
```

Equivalente:

```bash
php artisan migrate:fresh
php artisan db:seed --class=DemoSeeder
```

`DatabaseSeeder` (el de `php artisan db:seed` a secas) **no** se tocó: los tests de feature siguen creando sus propios datos. Esta demo vive solo en `DemoSeeder`, con `fake('es_CO')` y semilla fija `2026`.

## Pruebas SQLite / PostgreSQL (RNF-07)

```bash
cd backend
composer test                 # SQLite :memory: (phpunit.xml)
cp .env.testing.example .env.testing   # host/puerto/usuario de PG
# Crea la BD pgaev_test una vez
composer test:pgsql           # phpunit.pgsql.xml → pgaev_test
php artisan pgaev:concurrency-test --processes=8
```

El comando de concurrencia exige `DB_CONNECTION=pgsql`. Lanza canjes (stock 1) y otorgamientos de puntos en paralelo; el JSON queda en `storage/app/reports/concurrency_<fecha>.json` y debe reportar `inconsistency_count: 0`.

## Lecturas (RNF-01)

Con `php artisan serve --no-reload` activo (`PHP_CLI_SERVER_WORKERS=8`):

```bash
php artisan pgaev:bench --vus=20 --duration=60
```

Meta: `PGAEV_PERF_P95_MS=500`. El informe se escribe fuera del código de la app.

En el seeder el Copiloto no llama a OpenAI: la retroalimentación lleva el prefijo `[Demo, IA desactivada]`.

## Credenciales (contraseña: `password`)

| Rol | Correo | Notas |
| --- | --- | --- |
| Admin | `admin@pgaev.edu.co` | CU-14: usuarios, roles, suspensión, KPIs globales. |
| Docente | `docente@pgaev.edu.co` | Carlos Mendoza. Cursos: Bases de Datos, Ingeniería de Software II. |
| Docente | `docente2@pgaev.edu.co` | Laura Restrepo. Cursos: Estructuras de Datos, Ética Profesional. |
| Docente | `docente3@pgaev.edu.co` | Andrés Herrera. Sin cursos propios (queda para asignar). |
| Estudiante | `estudiante1@pgaev.edu.co` … `estudiante30@pgaev.edu.co` | Nombres en español (Colombia). |

Códigos de invitación: `PGAEV1` … `PGAEV4`.

## Qué hay en los datos

- 4 cursos publicados, ~20–25 inscritos cada uno (con solapamiento).
- 6 niveles por curso: Novato → Maestro.
- 8 actividades por curso (quiz, tarea, evaluación, proyecto, foro, más un borrador sin publicar). Unas vencidas y otras futuras.
- Entregas desiguales: estudiantes 1–8 muy activos, 9–25 medios, 26–30 baja participación. La mayoría se calificó con `SubmissionService::gradeSubmission` para generar puntos, `point_logs`, niveles, insignias y notificaciones.
- 6 encuentros sincrónicos por curso (4 pasados, 2 próximos) con asistencia variada.
- Foros con 1–3 aportes según el perfil.
- 4 recompensas por curso (2 con aprobación) y canjes aprobado / pendiente / rechazado.
- Insignias: Primer Envío, 5 entregas, 200 pts, Nivel 3, y la manual «Colaborador destacado».
- Fechas repartidas en las últimas 8 semanas para que los gráficos del docente tengan forma.

## Escenas recomendadas

| Escena | Cuenta | Dónde |
| --- | --- | --- |
| Casi sube de nivel (barra cerca del umbral) | `estudiante1@pgaev.edu.co` (Ana García) | Panel estudiante → curso **Bases de Datos**. |
| Canje pendiente de aprobación | `estudiante2@pgaev.edu.co` (Luis Ramírez) | Tienda del curso **Bases de Datos**; el docente Carlos ve el canje en **Canjes**. |
| Baja participación (analíticas / CSV) | `estudiante26@pgaev.edu.co` … `estudiante30@` | Reportes del docente: completitud y alumnos en riesgo. |
| Administración | `admin@pgaev.edu.co` | `/admin`: KPIs, cambio de rol, suspender. |
| Gráficos y clasificación (RF-12 / RF-16) | `docente@pgaev.edu.co` | Dashboard docente y reportes del curso. |
| Foros y asistencia (objetivo 4) | `docente2@pgaev.edu.co` | Detalle de **Estructuras de Datos** o **Ética Profesional**. |
