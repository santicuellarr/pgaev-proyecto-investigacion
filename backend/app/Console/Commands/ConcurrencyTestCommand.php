<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\PointLog;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\User;
use App\Models\UserCourseStat;
use App\Services\GamificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ConcurrencyTestCommand extends Command
{
    protected $signature = 'pgaev:concurrency-test
        {--processes=8 : Cantidad de procesos de canje y de otorgamiento de puntos}
        {--worker= : Uso interno (redeem|award)}
        {--user= : Id de usuario (worker)}
        {--reward= : Id de recompensa (worker redeem)}
        {--course= : Id de curso (worker award)}
        {--points=7 : Puntos a otorgar (worker award)}
        {--key= : Idempotency key (worker redeem)}';

    protected $description = 'Prueba de concurrencia en PostgreSQL: canjes con stock 1 y puntos simultáneos';

    public function handle(GamificationService $gamification): int
    {
        $worker = $this->option('worker');

        if (is_string($worker) && $worker !== '') {
            return $this->runWorker($gamification, $worker);
        }

        if (config('database.default') !== 'pgsql') {
            $this->error('pgaev:concurrency-test debe ejecutarse contra PostgreSQL (DB_CONNECTION=pgsql).');

            return self::FAILURE;
        }

        $processes = max(2, (int) $this->option('processes'));
        $awardPoints = 7;
        $startingBalance = 100;
        $rewardCost = 10;

        $fixture = $this->prepareFixture($startingBalance, $rewardCost);

        $this->info(sprintf(
            'Lanzando %d canjes y %d otorgamientos en paralelo contra %s/%s …',
            $processes,
            $processes,
            config('database.default'),
            config('database.connections.pgsql.database'),
        ));

        $results = Process::pool(function ($pool) use ($fixture, $processes, $awardPoints) {
            $env = $this->childEnv();

            for ($i = 0; $i < $processes; $i++) {
                $pool->as('redeem-'.$i)
                    ->timeout(45)
                    ->path(base_path())
                    ->env($env)
                    ->command($this->workerCommand([
                        '--worker=redeem',
                        '--user='.$fixture['user_id'],
                        '--reward='.$fixture['reward_id'],
                        '--key=conc-redeem-'.$fixture['run_id'].'-'.$i,
                    ]));

                $pool->as('award-'.$i)
                    ->timeout(45)
                    ->path(base_path())
                    ->env($env)
                    ->command($this->workerCommand([
                        '--worker=award',
                        '--user='.$fixture['user_id'],
                        '--course='.$fixture['course_id'],
                        '--points='.$awardPoints,
                    ]));
            }
        })->start()->wait();

        $workers = [];
        foreach ($results as $name => $result) {
            $decoded = json_decode($result->output(), true);
            $workers[$name] = [
                'ok' => (bool) ($decoded['ok'] ?? false),
                'exit' => $result->exitCode(),
                'error' => $decoded['error'] ?? trim($result->errorOutput()),
            ];
        }

        $reward = Reward::query()->findOrFail($fixture['reward_id']);
        $stats = UserCourseStat::query()
            ->where('user_id', $fixture['user_id'])
            ->where('course_id', $fixture['course_id'])
            ->firstOrFail();

        $approved = Redemption::query()->where('reward_id', $fixture['reward_id'])->where('status', 'approved')->count();
        $totalRedemptions = Redemption::query()->where('reward_id', $fixture['reward_id'])->count();
        $sumDeltas = (int) PointLog::query()
            ->where('user_id', $fixture['user_id'])
            ->where('course_id', $fixture['course_id'])
            ->sum('delta');

        $redeemOk = count(array_filter($workers, fn (array $row, string $name) => str_starts_with($name, 'redeem-') && $row['ok'], ARRAY_FILTER_USE_BOTH));
        $awardOk = count(array_filter($workers, fn (array $row, string $name) => str_starts_with($name, 'award-') && $row['ok'], ARRAY_FILTER_USE_BOTH));

        $inconsistencies = [];

        if ($approved !== 1) {
            $inconsistencies[] = "Se esperaba 1 canje aprobado, hubo {$approved}.";
        }

        if ($totalRedemptions !== 1) {
            $inconsistencies[] = "Se esperaba 1 fila de canje, hubo {$totalRedemptions}.";
        }

        if ($reward->stock < 0) {
            $inconsistencies[] = "Stock negativo: {$reward->stock}.";
        }

        if ($reward->stock !== 0) {
            $inconsistencies[] = "Stock final distinto de 0: {$reward->stock}.";
        }

        if ((int) $stats->total_points !== $sumDeltas) {
            $inconsistencies[] = sprintf(
                'Saldo %d distinto de sum(point_logs.delta) %d.',
                (int) $stats->total_points,
                $sumDeltas,
            );
        }

        $expectedBalance = $startingBalance - $rewardCost + ($awardOk * $awardPoints);
        if ((int) $stats->total_points !== $expectedBalance) {
            $inconsistencies[] = sprintf(
                'Saldo %d distinto del esperado %d (inicio %d - canje %d + %d×%d pts).',
                (int) $stats->total_points,
                $expectedBalance,
                $startingBalance,
                $rewardCost,
                $awardOk,
                $awardPoints,
            );
        }

        $payload = [
            'ran_at' => now()->toIso8601String(),
            'connection' => config('database.default'),
            'database' => config('database.connections.pgsql.database'),
            'processes' => [
                'redeem' => $processes,
                'award' => $processes,
            ],
            'starting_balance' => $startingBalance,
            'reward_cost' => $rewardCost,
            'award_delta' => $awardPoints,
            'results' => [
                'approved_redemptions' => $approved,
                'total_redemptions' => $totalRedemptions,
                'stock' => (int) $reward->stock,
                'final_balance' => (int) $stats->total_points,
                'sum_deltas' => $sumDeltas,
                'worker_redeem_ok' => $redeemOk,
                'worker_award_ok' => $awardOk,
            ],
            'workers' => $workers,
            'inconsistencies' => $inconsistencies,
            'inconsistency_count' => count($inconsistencies),
        ];

        $directory = storage_path('app/reports');
        File::ensureDirectoryExists($directory);
        $filename = 'concurrency_'.now()->format('Y-m-d_His').'.json';
        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->newLine();
        $this->info('Reporte: '.$path);

        if ($inconsistencies !== []) {
            $this->error(count($inconsistencies).' inconsistencia(s) detectada(s).');

            return self::FAILURE;
        }

        $this->info('0 inconsistencias.');

        return self::SUCCESS;
    }

    /**
     * @return array{user_id: int, course_id: int, reward_id: int, run_id: string}
     */
    private function prepareFixture(int $startingBalance, int $rewardCost): array
    {
        $runId = now()->format('YmdHis');
        $email = 'concurrency-'.$runId.'@pgaev.local';

        $teacher = User::factory()->teacher()->create([
            'name' => 'Docente Concurrencia',
            'email' => 'concurrency-teacher-'.$runId.'@pgaev.local',
        ]);

        $student = User::factory()->create([
            'name' => 'Estudiante Concurrencia',
            'email' => $email,
        ]);

        $course = Course::factory()->create([
            'owner_id' => $teacher->id,
            'title' => 'Curso concurrencia '.$runId,
            'invite_code' => strtoupper(Str::random(6)),
        ]);

        Enrollment::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        UserCourseStat::query()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'total_points' => $startingBalance,
            'current_level' => 1,
            'streak_days' => 0,
        ]);

        PointLog::query()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'source_id' => 0,
            'source_type' => 'concurrency_seed',
            'delta' => $startingBalance,
            'balance_after' => $startingBalance,
            'created_at' => now(),
        ]);

        $reward = Reward::factory()->create([
            'course_id' => $course->id,
            'name' => 'Recompensa stock 1',
            'cost_points' => $rewardCost,
            'stock' => 1,
            'needs_approval' => false,
        ]);

        return [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'reward_id' => $reward->id,
            'run_id' => $runId,
        ];
    }

    private function runWorker(GamificationService $gamification, string $worker): int
    {
        try {
            if ($worker === 'redeem') {
                $gamification->redeemReward(
                    (int) $this->option('user'),
                    (int) $this->option('reward'),
                    (string) $this->option('key'),
                );
            } elseif ($worker === 'award') {
                $gamification->awardPoints(
                    (int) $this->option('user'),
                    (int) $this->option('course'),
                    (int) $this->option('points'),
                    'concurrency_award',
                    0,
                );
            } else {
                $this->line(json_encode(['ok' => false, 'error' => 'worker desconocido']));

                return self::FAILURE;
            }

            $this->line(json_encode(['ok' => true, 'worker' => $worker]));

            return self::SUCCESS;
        } catch (ValidationException $exception) {
            $this->line(json_encode([
                'ok' => false,
                'worker' => $worker,
                'error' => collect($exception->errors())->flatten()->first() ?? $exception->getMessage(),
            ]));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->line(json_encode([
                'ok' => false,
                'worker' => $worker,
                'error' => $exception->getMessage(),
            ]));

            return self::SUCCESS;
        }
    }

    /**
     * @param  list<string>  $options
     */
    private function workerCommand(array $options): string
    {
        $parts = array_merge([
            escapeshellarg(PHP_BINARY),
            escapeshellarg(base_path('artisan')),
            'pgaev:concurrency-test',
        ], array_map(escapeshellarg(...), $options));

        return implode(' ', $parts);
    }

    /**
     * @return array<string, string>
     */
    private function childEnv(): array
    {
        $pgsql = config('database.connections.pgsql');

        return [
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => (string) $pgsql['host'],
            'DB_PORT' => (string) $pgsql['port'],
            'DB_DATABASE' => (string) $pgsql['database'],
            'DB_USERNAME' => (string) $pgsql['username'],
            'DB_PASSWORD' => (string) ($pgsql['password'] ?? ''),
            'APP_ENV' => (string) config('app.env'),
        ];
    }
}
