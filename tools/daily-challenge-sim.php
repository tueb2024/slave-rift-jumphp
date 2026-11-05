<?php
declare(strict_types=1);

/**
 * Full four-map daily challenge: real procedural maps, unmodified combat,
 * legal deterministic bot input policies, no injected HP/XP or skipped bosses.
 * The same UTC date determines all four stage seeds across profiles.
 */
require_once __DIR__ . '/balance-sim.php';

use App\Game\CampaignRun;
use App\Game\RhythmClock;

$date = $argv[1] ?? '2026-11-05';
$maxTime = 1800.0; // 30 minutes of game time across all four maps, including transitions.
$profiles = ['faible', 'moyen', 'bon'];
$rows = [];

foreach ($profiles as $profile) {
    $campaign = CampaignRun::daily($date);
    $clock = new RhythmClock(); // One transport for all four maps.
    $stages = [];

    for ($stage = 1; $stage <= 4; $stage++) {
        $run = $campaign->run;
        $startTime = $run->time;
        $startKills = $run->kills;
        $startHp = $run->health;
        $seed = $run->mode->seed;
        // A real continuous run; never set win, skip waves or modify combat stats.
        $result = simulateProfile(
            $profile, $seed, $maxTime, 'route',
            $run->mode, $run->xpCurve, $run, $clock
        );

        $stages[] = [
            'map' => $stage,
            'seed' => $seed,
            'rooms' => $campaign->geometry['mainRooms'] ?? null,
            'surfaceRatio' => $campaign->geometry['surfaceRatio'] ?? null,
            'state' => $result['result'],
            'durationSeconds' => round($run->time - $startTime, 1),
            'elapsedSeconds' => round($run->time, 1),
            'wave' => $run->wave,
            'wavesRequired' => $run->mode->waveCount(),
            'killsThisMap' => $run->kills - $startKills,
            'totalKills' => $run->kills,
            'level' => $run->level,
            'weapon' => $run->primaryWeaponLabel(),
            'power' => $run->selectedSecondary(),
            'hpStart' => $startHp,
            'hpEnd' => $run->health,
            'maxHp' => $run->maxHealth,
            'finalRoomReached' => $result['finalRoom'],
            'bossReached' => $result['bossReached'],
            'bossDefeated' => $result['bossDefeated'],
            'treasure' => $result['checkpoints']['treasure'] !== null,
            'remainingToSpawn' => $run->remaining,
            'enemiesAlive' => count($run->enemies),
        ];
        fwrite(STDERR, sprintf(
            "%s %s map %d/4: %s, %.1f s, %d kills, wave %d/%d, HP %d/%d\n",
            $date, $profile, $stage, $run->state,
            $run->time - $startTime, $run->kills - $startKills,
            $run->wave, $run->mode->waveCount(), $run->health, $run->maxHealth
        ));

        if ($run->state !== 'won' || $stage === 4) {
            break;
        }
        if (!$campaign->advance()) {
            throw new RuntimeException('Completed stage did not advance');
        }
    }

    $success = $campaign->state() === 'won';
    $elapsed = $campaign->run->time;
    $rows[] = [
        'profile' => $profile,
        'result' => $campaign->state(),
        'mapsReached' => count($stages),
        'victory' => $success,
        'totalSeconds' => round($elapsed, 1),
        'within12to18min' => $success && $elapsed >= 720 && $elapsed <= 1080,
        'maps' => $stages,
    ];
}
echo json_encode([
    'mode' => 'daily',
    'utcDate' => $date,
    'targetSeconds' => 900,
    'targetRangeSeconds' => [720,1080],
    'simulationCapSeconds' => $maxTime,
    'note' => 'Imperfect deterministic bots; one challenge date, not independent human win-rate samples. The entire campaign uses real maps/waves/bosses and real upgrades. Audio is not played.',
    'runs' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
