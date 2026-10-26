<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Throwable;

class BenchCommand extends Command
{
    protected $signature = 'pgaev:bench
        {--base=http://127.0.0.1:8000 : Origen de la API (sin /api)}
        {--vus=20 : Usuarios virtuales (10–25)}
        {--duration=60 : Duración en segundos}
        {--password=password : Contraseña de las cuentas demo}
        {--worker : Uso interno}
        {--token= : Bearer del worker}
        {--role=student : Rol del worker}
        {--course= : Id de curso del worker}
        {--until= : Unix timestamp de fin del worker}';

    protected $description = 'Mide p50/p95/p99 y tasa de error de lecturas (RNF-01)';

    public function handle(): int
    {
        if ($this->option('worker')) {
            return $this->runWorker();
        }

        $vus = max(10, min(25, (int) $this->option('vus')));
        $duration = max(5, (int) $this->option('duration'));
        $base = rtrim((string) $this->option('base'), '/');
        $password = (string) $this->option('password');
        $threshold = (int) config('pgaev.perf.p95_ms', 500);

        $sessions = $this->loginDemoUsers($base, $password);

        if ($sessions['students'] === [] || $sessions['teachers'] === []) {
            $this->error('Faltan cuentas DemoSeeder (estudiante* y docente*). Ejecuta: php artisan db:seed --class=DemoSeeder');

            return self::FAILURE;
        }

        $until = time() + $duration;
        $this->info(sprintf(
            'Bench %d VU × %d s contra %s (meta p95 < %d ms)',
            $vus,
            $duration,
            $base,
            $threshold,
        ));

        $assignments = $this->assignVirtualUsers($sessions, $vus);
        $results = Process::pool(function ($pool) use ($assignments, $base, $until) {
            foreach ($assignments as $index => $assignment) {
                $pool->as('vu-'.$index)
                    ->timeout($until - time() + 30)
                    ->path(base_path())
                    ->command($this->workerCommand([
                        '--worker',
                        '--base='.$base,
                        '--token='.$assignment['token'],
                        '--role='.$assignment['role'],
                        '--course='.$assignment['course_id'],
                        '--until='.$until,
                    ]));
            }
        })->start()->wait();

        $samples = [];
        foreach ($results as $name => $result) {
            $decoded = $this->decodeWorkerOutput($result->output());
            if (! is_array($decoded['samples'] ?? null)) {
                $this->warn($name.' sin muestras: '.trim($result->errorOutput().' '.$result->output()));

                continue;
            }
            foreach ($decoded['samples'] as $sample) {
                $samples[] = $sample;
            }
        }

        $report = $this->buildReport($samples, $base, $vus, $duration, $threshold, $sessions);
        [$jsonPath, $mdPath] = $this->persistReport($report);

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->newLine();
        $this->info('JSON: '.$jsonPath);
        $this->info('Markdown: '.$mdPath);

        return $report['goal_met'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{students: list<array{token: string, course_id: int}>, teachers: list<array{token: string, course_id: int}>}
     */
    private function loginDemoUsers(string $base, string $password): array
    {
        $teachers = [];
        foreach (['docente@pgaev.edu.co'] as $email) {
            $session = $this->login($base, $email, $password);
            if ($session) {
                $teachers[] = $session;
            }
        }

        $students = [];
        $emails = User::query()
            ->where('role', User::ROLE_STUDENT)
            ->where('email', 'like', 'estudiante%@pgaev.edu.co')
            ->orderByRaw('length(email), email')
            ->limit(4)
            ->pluck('email');

        foreach ($emails as $email) {
            $session = $this->login($base, $email, $password);
            if ($session) {
                $students[] = $session;
            }
        }

        return compact('students', 'teachers');
    }

    /**
     * @return array{token: string, course_id: int}|null
     */
    private function login(string $base, string $email, string $password): ?array
    {
        try {
            $response = Http::baseUrl($base)
                ->acceptJson()
                ->timeout(20)
                ->post('/api/login', compact('email', 'password'));
        } catch (Throwable $exception) {
            $this->warn('Login falló para '.$email.': '.$exception->getMessage());

            return null;
        }

        if (! $response->successful() || ! $response->json('token')) {
            $this->warn('Login HTTP '.$response->status().' para '.$email.': '.$response->body());

            return null;
        }

        $courses = Http::baseUrl($base)
            ->withToken($response->json('token'))
            ->acceptJson()
            ->timeout(20)
            ->get('/api/courses');

        $courseId = (int) ($courses->json('courses.0.id') ?? 0);

        if ($courseId === 0) {
            $this->warn($email.' no tiene cursos para el bench.');

            return null;
        }

        return [
            'token' => $response->json('token'),
            'course_id' => $courseId,
        ];
    }

    /**
     * @param  array{students: list<array{token: string, course_id: int}>, teachers: list<array{token: string, course_id: int}>}  $sessions
     * @return list<array{role: string, token: string, course_id: int}>
     */
    private function assignVirtualUsers(array $sessions, int $vus): array
    {
        $teacherCount = max(1, (int) round($vus * 0.25));
        $assignments = [];

        for ($i = 0; $i < $vus; $i++) {
            if ($i < $teacherCount) {
                $source = $sessions['teachers'][$i % count($sessions['teachers'])];
                $assignments[] = ['role' => 'teacher', ...$source];
            } else {
                $source = $sessions['students'][($i - $teacherCount) % count($sessions['students'])];
                $assignments[] = ['role' => 'student', ...$source];
            }
        }

        return $assignments;
    }

    private function runWorker(): int
    {
        $base = rtrim((string) $this->option('base'), '/');
        $token = (string) $this->option('token');
        $role = (string) $this->option('role');
        $courseId = (int) $this->option('course');
        $until = (int) $this->option('until');
        $samples = [];
        $paths = $role === 'teacher'
            ? [
                '/api/courses',
                '/api/courses/'.$courseId.'/leaderboard',
                '/api/courses/'.$courseId.'/analytics',
            ]
            : [
                '/api/courses',
                '/api/courses/'.$courseId.'/leaderboard',
                '/api/me/progress?course_id='.$courseId,
            ];

        $index = 0;

        while (time() < $until) {
            $path = $paths[$index % count($paths)];
            $index++;
            $started = hrtime(true);

            try {
                $response = Http::baseUrl($base)
                    ->withToken($token)
                    ->acceptJson()
                    ->timeout(20)
                    ->get($path);
                $status = $response->status();
            } catch (Throwable $exception) {
                $status = 0;
            }

            $samples[] = [
                'endpoint' => $this->endpointKey($path),
                'ms' => (hrtime(true) - $started) / 1e6,
                'status' => $status,
                'ok' => $status >= 200 && $status < 400,
            ];

            usleep(200_000);
        }

        fwrite(STDOUT, json_encode(['samples' => $samples]));

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeWorkerOutput(string $output): array
    {
        $trimmed = trim($output);
        $decoded = json_decode($trimmed, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strrpos($trimmed, '{"samples"');
        if ($start === false) {
            return [];
        }

        $decoded = json_decode(substr($trimmed, $start), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function endpointKey(string $path): string
    {
        return match (true) {
            str_contains($path, '/leaderboard') => '/leaderboard',
            str_contains($path, '/analytics') => '/analytics',
            str_contains($path, '/me/progress') => '/me/progress',
            default => '/courses',
        };
    }

    /**
     * @param  list<array{endpoint: string, ms: float, status: int, ok: bool}>  $samples
     * @param  array{students: list<array{token: string, course_id: int}>, teachers: list<array{token: string, course_id: int}>}  $sessions
     * @return array<string, mixed>
     */
    private function buildReport(array $samples, string $base, int $vus, int $duration, int $threshold, array $sessions): array
    {
        $byEndpoint = [];
        foreach (['/courses', '/leaderboard', '/me/progress', '/analytics'] as $endpoint) {
            $subset = array_values(array_filter($samples, fn (array $row) => $row['endpoint'] === $endpoint));
            $byEndpoint[$endpoint] = $this->summarize($subset);
        }

        $overall = $this->summarize($samples);
        $goalMet = $overall['count'] > 0
            && $overall['p95_ms'] < $threshold
            && $overall['error_rate'] <= (float) config('pgaev.perf.error_rate_max', 0.01);

        $uncontended = $this->measureUncontended($base, $sessions);

        return [
            'ran_at' => now()->toIso8601String(),
            'base_url' => $base,
            'virtual_users' => $vus,
            'duration_s' => $duration,
            'goal_p95_ms' => $threshold,
            'goal_error_rate_max' => (float) config('pgaev.perf.error_rate_max', 0.01),
            'goal_met' => $goalMet,
            'runtime' => [
                'php' => PHP_VERSION,
                'os' => PHP_OS_FAMILY,
                'php_cli_server_workers' => (int) env('PHP_CLI_SERVER_WORKERS', 1),
                'fork_supported' => PHP_OS_FAMILY !== 'Windows',
                'k6' => false,
                'tool' => 'pgaev:bench',
            ],
            'overall' => $overall,
            'endpoints' => $byEndpoint,
            'uncontended' => $uncontended,
            'notes' => [
                'En Windows el servidor CLI de PHP no hace fork: PHP_CLI_SERVER_WORKERS se ignora y los VU concurrentes se encolan en un hilo. El p95 bajo carga refleja esa cola.',
                'uncontended es 1 cliente en serie (misma API e índices) para separar tiempo de handler de la cola del servidor.',
            ],
        ];
    }

    /**
     * @param  array{students: list<array{token: string, course_id: int}>, teachers: list<array{token: string, course_id: int}>}  $sessions
     * @return array<string, mixed>
     */
    private function measureUncontended(string $base, array $sessions): array
    {
        $student = $sessions['students'][0];
        $teacher = $sessions['teachers'][0];
        $paths = [
            '/courses' => ['token' => $student['token'], 'path' => '/api/courses'],
            '/leaderboard' => ['token' => $student['token'], 'path' => '/api/courses/'.$student['course_id'].'/leaderboard'],
            '/me/progress' => ['token' => $student['token'], 'path' => '/api/me/progress?course_id='.$student['course_id']],
            '/analytics' => ['token' => $teacher['token'], 'path' => '/api/courses/'.$teacher['course_id'].'/analytics'],
        ];
        $byEndpoint = [];
        $all = [];

        foreach ($paths as $key => $target) {
            $samples = [];
            for ($i = 0; $i < 20; $i++) {
                $started = hrtime(true);
                try {
                    $response = Http::baseUrl($base)
                        ->withToken($target['token'])
                        ->acceptJson()
                        ->timeout(20)
                        ->get($target['path']);
                    $status = $response->status();
                } catch (Throwable) {
                    $status = 0;
                }
                $row = [
                    'endpoint' => $key,
                    'ms' => (hrtime(true) - $started) / 1e6,
                    'status' => $status,
                    'ok' => $status >= 200 && $status < 400,
                ];
                $samples[] = $row;
                $all[] = $row;
            }
            $byEndpoint[$key] = $this->summarize($samples);
        }

        return [
            'overall' => $this->summarize($all),
            'endpoints' => $byEndpoint,
        ];
    }

    /**
     * @param  list<array{endpoint?: string, ms: float, status: int, ok: bool}>  $samples
     * @return array<string, mixed>
     */
    private function summarize(array $samples): array
    {
        $count = count($samples);
        $errors = count(array_filter($samples, fn (array $row) => ! $row['ok']));
        $latencies = array_map(fn (array $row) => (float) $row['ms'], $samples);
        sort($latencies);

        return [
            'count' => $count,
            'errors' => $errors,
            'error_rate' => $count === 0 ? 1.0 : round($errors / $count, 4),
            'p50_ms' => $this->percentile($latencies, 50),
            'p95_ms' => $this->percentile($latencies, 95),
            'p99_ms' => $this->percentile($latencies, 99),
        ];
    }

    /**
     * @param  list<float>  $sorted
     */
    private function percentile(array $sorted, float $p): ?float
    {
        if ($sorted === []) {
            return null;
        }

        $index = (int) round(($p / 100) * (count($sorted) - 1));

        return round($sorted[$index], 2);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array{0: string, 1: string}
     */
    private function persistReport(array $report): array
    {
        $dir = $this->resultsDirectory();
        File::ensureDirectoryExists($dir);
        $stamp = now()->format('Y-m-d_His');
        $jsonPath = $dir.DIRECTORY_SEPARATOR.$stamp.'.json';
        $mdPath = $dir.DIRECTORY_SEPARATOR.$stamp.'.md';

        File::put($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        File::put($mdPath, $this->markdown($report));

        return [$jsonPath, $mdPath];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function markdown(array $report): string
    {
        $overall = $report['overall'];
        $status = $report['goal_met'] ? 'CUMPLE' : 'NO CUMPLE';
        $lines = [
            '# Bench PGAEV (RNF-01)',
            '',
            '- Fecha: `'.$report['ran_at'].'`',
            '- Base: `'.$report['base_url'].'`',
            '- VU: **'.$report['virtual_users'].'** × **'.$report['duration_s'].' s**',
            '- Meta local: p95 < **'.$report['goal_p95_ms'].' ms** y error rate ≤ **'.$report['goal_error_rate_max'].'**',
            '- Resultado global ('.$report['virtual_users'].' VU): **'.$status.'** (p95 '.$overall['p95_ms'].' ms, errores '.$overall['error_rate'].')',
            '- Runtime: PHP '.$report['runtime']['php'].' / '.$report['runtime']['os'].' / workers env '.$report['runtime']['php_cli_server_workers'].' / fork '.($report['runtime']['fork_supported'] ? 'sí' : 'no').' / herramienta '.$report['runtime']['tool'],
            '- p95 sin contención (1 cliente): **'.($report['uncontended']['overall']['p95_ms'] ?? '—').' ms**',
            '',
            '| Endpoint | n | p50 (ms) | p95 (ms) | p99 (ms) | error rate |',
            '| --- | ---: | ---: | ---: | ---: | ---: |',
        ];

        foreach ($report['endpoints'] as $endpoint => $stats) {
            $lines[] = sprintf(
                '| `%s` | %d | %s | %s | %s | %s |',
                $endpoint,
                $stats['count'],
                $stats['p50_ms'] ?? '—',
                $stats['p95_ms'] ?? '—',
                $stats['p99_ms'] ?? '—',
                $stats['error_rate'],
            );
        }

        $lines[] = sprintf(
            '| **global** | %d | %s | %s | %s | %s |',
            $overall['count'],
            $overall['p50_ms'] ?? '—',
            $overall['p95_ms'] ?? '—',
            $overall['p99_ms'] ?? '—',
            $overall['error_rate'],
        );
        $lines[] = '';
        $lines[] = '### Sin contención (serie)';
        $lines[] = '';
        $lines[] = '| Endpoint | n | p50 (ms) | p95 (ms) | p99 (ms) | error rate |';
        $lines[] = '| --- | ---: | ---: | ---: | ---: | ---: |';

        foreach ($report['uncontended']['endpoints'] as $endpoint => $stats) {
            $lines[] = sprintf(
                '| `%s` | %d | %s | %s | %s | %s |',
                $endpoint,
                $stats['count'],
                $stats['p50_ms'] ?? '—',
                $stats['p95_ms'] ?? '—',
                $stats['p99_ms'] ?? '—',
                $stats['error_rate'],
            );
        }

        foreach ($report['notes'] ?? [] as $note) {
            $lines[] = '';
            $lines[] = '> '.$note;
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    private function resultsDirectory(): string
    {
        $repo = dirname(base_path());

        if (is_dir($repo.DIRECTORY_SEPARATOR.'perf') || is_dir($repo.DIRECTORY_SEPARATOR.'docs')) {
            return $repo.DIRECTORY_SEPARATOR.'perf'.DIRECTORY_SEPARATOR.'results';
        }

        return base_path('perf'.DIRECTORY_SEPARATOR.'results');
    }

    /**
     * @param  list<string>  $options
     */
    private function workerCommand(array $options): string
    {
        $parts = array_merge([
            escapeshellarg(PHP_BINARY),
            escapeshellarg(base_path('artisan')),
            'pgaev:bench',
        ], array_map(escapeshellarg(...), $options));

        return implode(' ', $parts);
    }
}
