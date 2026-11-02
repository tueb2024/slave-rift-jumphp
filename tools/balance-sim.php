<?php

declare(strict_types=1);
require_once __DIR__ . '/test-bootstrap.php';

use App\Game\BrushMap;
use App\Game\GameMode;
use App\Game\BrushMapFactory;
use App\Game\RoomModule;
use App\Game\LevelAssembler;
use App\Game\LevelGraph;
use App\Game\RhythmClock;
use App\Game\Run;
use App\Game\XpCurve;

/** An imperfect, deterministic input policy. Never changes health, XP, targets or damage. */
function simulateProfile(string $profile, int $seed, float $limit = 600.0, string $scenario = 'route', ?GameMode $gameMode = null, ?XpCurve $xpCurve = null, ?Run $preparedRun = null, ?RhythmClock $transport = null): array
{
    $level = (new LevelAssembler())->assemble(new LevelGraph($seed, $gameMode?->depth ?? 7));
    $map = $level->map;
    if ($scenario === 'survival') {
        // Open training arena isolates the wave/XP curve from navigation skill.
        $arena = new RoomModule('balance_arena');
        $arena->addBox(-14, -1, -14, 14, 0, 14, 'metal');
        $arena->addBox(-14, 0, -14, -13.5, 4, 14);
        $arena->addBox(13.5, 0, -14, 14, 4, 14);
        $arena->addBox(-14, 0, -14, 14, 4, -13.5);
        $arena->addBox(-14, 0, 13.5, 14, 4, 14);
        $points = [[0.0, 0.75, 0.0]];
        foreach ([-9.0, 0.0, 9.0] as $x) foreach ([-9.0, 9.0] as $z) $points[] = [$x, 0.75, $z];
        $map = BrushMapFactory::fromGenerated($arena->solids, $points, [0.0, 0.75, 0.0], 0.0);
    }
    $run = $preparedRun ?? new Run($map, $seed, $gameMode, $xpCurve);
    if ($preparedRun === null && $scenario === 'route') $run->setObjective($level, $gameMode?->finalWaveSize ?? 12);
    $rhythm = $transport ?? new RhythmClock();
    [$reaction, $aimError, $collection, $speed] = match ($profile) {
        'faible' => [0.65, 0.65, 3.0, 0.55],
        'bon' => [0.15, 0.08, 7.0, 1.0],
        default => [0.35, 0.3, 5.0, 0.8],
    };
    $dt = 1 / 30; $nextReaction = 0.0; $forward = $strafe = $turn = 0.0;
    $arsenalTimeline=[['time'=>0.0,'level'=>$run->level,'weapon'=>$run->primaryWeaponLabel()]];
    $wave7 = null; $lowestHealth = 100; $lowestHealthRatio = 1.0;
    $checkpoints = ['finalRoom'=>null,'boss'=>null,'bossDefeated'=>null,'treasure'=>null];
    $snapshot = static fn () => ['time'=>round($run->time,1),'xpTotal'=>$run->collectedXp,'wave'=>$run->wave,'level'=>$run->level,
        'weapon'=>$run->primaryWeaponLabel(),'health'=>$run->health,'maxHealth'=>$run->maxHealth,'healthRatio'=>$run->health/$run->maxHealth];
    for ($frame = 0; $frame < (int)($limit / $dt); $frame++) {
        if ($run->state === 'dead' || $run->state === 'won') break;
        if ($run->state === 'upgrade') {
            // Varied choices; prefer healing under pressure, not a single optimized build.
            $slot = ($seed + $run->level) % count($run->choices);
            if ($run->health < $run->maxHealth * 0.6) {
                $heal = array_search('VITALITY', $run->choices, true);
                if ($heal !== false) $slot = $heal;
            }
            $run->chooseUpgrade($slot);
            continue;
        }
        if ($run->time >= $nextReaction) {
            $nextReaction = $run->time + $reaction;
            $goal = $run->routeTarget();
            if ($scenario === 'survival') {
                $points = $run->map->spawnPoints;
                if ($points === []) {
                    [$sx, $sy, $sz] = $run->map->playerStart;
                    $points = [[$sx - 4, $sy, $sz - 4], [$sx + 4, $sy, $sz - 4], [$sx + 4, $sy, $sz + 4], [$sx - 4, $sy, $sz + 4]];
                }
                $point = $points[((int)floor($run->time / 9) + $seed) % count($points)];
                $goal = ['x' => $point[0], 'z' => $point[2]];
            }
            $aim = null; $nearest = INF;
            foreach ($run->enemies as $enemy) {
                $d = hypot($enemy['x'] - $run->x, $enemy['z'] - $run->z);
                $angle = atan2($enemy['x']-$run->x,$enemy['z']-$run->z)-$run->yaw;
                $visibleAngle = abs(atan2(sin($angle),cos($angle)));
                // No omniscient targeting behind the camera. A hit can trigger a turn toward the attacker.
                if ($visibleAngle > 0.75 && $run->hurt <= 0) continue;
                if ($d < $nearest && $run->lineClear([$run->x, $run->y + 0.35, $run->z], [$enemy['x'], $enemy['y'] + 0.15, $enemy['z']])) {
                    $aim = $enemy; $nearest = $d;
                }
            }
            $pickupDistance = $collection;
            foreach ($run->pickups as $pickup) {
                $attentionToXp = match ($profile) {'faible'=>50, 'bon'=>95, default=>80};
                $noticed = ((int)floor($pickup['x']*17+$pickup['z']*31)+$seed) % 100;
                if ($noticed < 0) $noticed += 100;
                if ($pickup['kind'] === 'xp' && $noticed >= $attentionToXp) continue;
                $d = hypot($pickup['x'] - $run->x, $pickup['z'] - $run->z);
                if ($d < $pickupDistance && $run->lineClear([$run->x, $run->y, $run->z], [$pickup['x'], $pickup['y'], $pickup['z']])) {
                    $goal = $pickup; $pickupDistance = $d;
                }
            }
            if ($run->boss?->active && !($run->boss?->defeated ?? false)) {
                $aim = $run->bossPosition;
                if (!$run->boss->vulnerable) foreach ($run->boss->orbs as $orb) if ($orb['alive']) { $aim = $orb; break; }
                $treasure = $run->objective->treasureAnchor;
                $bx = $run->bossPosition['x'] - $treasure['x']; $bz = $run->bossPosition['z'] - $treasure['z'];
                $length = max(0.001, hypot($bx, $bz));
                $goal = ['x' => $run->bossPosition['x'] - $bx / $length * 7,
                    'z' => $run->bossPosition['z'] - $bz / $length * 7];
            }
            if ($run->bossWarning !== null) {
                // Reaction delay and imperfect escape speed can still leave weaker profiles in the blast.
                $goal = ['x' => $run->bossWarning['x'] + 2.5, 'z' => $run->bossWarning['z']];
            }
            // Intermittent attention errors: navigation distracts from aiming.
            $attention = match ($profile) { 'faible' => 0.45, 'bon' => 0.90, default => 0.70 };
            if (fmod($run->time + $seed * 0.7, 4.0) > 4.0 * $attention) $aim = $goal;
            $aim ??= $goal;
            $desired = $aim === null ? $run->yaw : atan2($aim['x'] - $run->x, $aim['z'] - $run->z);
            $error = atan2(sin($desired - $run->yaw), cos($desired - $run->yaw));
            $turn = max(-1.0, min(1.0, -$error * 2.0 + sin($run->time * 1.7 + $seed) * $aimError));
            $noise = match ($profile) {'faible'=>0.25,'bon'=>0.02,default=>0.12};
            $dx = ($goal['x'] ?? $run->x) - $run->x + sin($run->time*0.7+$seed)*$noise;
            $dz = ($goal['z'] ?? $run->z) - $run->z + cos($run->time*0.9+$seed)*$noise;
            if ($nearest < 1.8 && $aim !== null) {
                $dx -= ($aim['x'] - $run->x) * 2; $dz -= ($aim['z'] - $run->z) * 2;
            }
            $hesitation = $profile === 'faible' ? 0.25 : ($profile === 'moyen' ? 0.10 : 0.0);
            if (fmod($run->time + $seed, 3.0) < $hesitation * 3.0) $dx = $dz = 0.0;
            $norm = max(1.0, hypot($dx, $dz));
            $forward = (sin($run->yaw) * $dx + cos($run->yaw) * $dz) / $norm * $speed;
            $strafe = (-cos($run->yaw) * $dx + sin($run->yaw) * $dz) / $norm * $speed;
        }
        $run->step($dt, $forward, $strafe, $turn, false, false, false, $rhythm->advance($dt, $run->level));
        $rhythm->drain();
        if ($run->primaryWeaponLabel() !== $arsenalTimeline[array_key_last($arsenalTimeline)]['weapon']) $arsenalTimeline[]=['time'=>round($run->time,1),'level'=>$run->level,'weapon'=>$run->primaryWeaponLabel()];
        $lowestHealth = min($lowestHealth, $run->health);
        $lowestHealthRatio = min($lowestHealthRatio, $run->health/$run->maxHealth);
        foreach (['finalRoom'=>($run->objective?->finalRoomReached ?? false),'boss'=>($run->boss?->active ?? false),
            'bossDefeated'=>($run->boss?->defeated ?? false),'treasure'=>$run->state==='won'] as $event=>$reached) {
            if ($reached && $checkpoints[$event] === null) $checkpoints[$event] = $snapshot();
        }
        if ($wave7 === null && $run->wave >= 7) $wave7 = ['level' => $run->level, 'weapon' => $run->primaryWeaponLabel(), 'health' => $run->health];
    }
    return ['arsenalTimeline'=>$arsenalTimeline,'xpModel'=>$run->xpCurve->model,'xpTotal'=>$run->collectedXp,'droppedXp'=>$run->droppedXp,'progression'=>$run->progressionSnapshot(), 'scenario' => $scenario, 'profile' => $profile, 'seed' => $seed, 'result' => $run->state, 'time' => round($run->time, 1),
        'wave' => $run->wave, 'level' => $run->level, 'weapon' => $run->primaryWeaponLabel(), 'health' => $run->health,
        'lowestHealth' => $lowestHealth, 'lowestHealthRatio'=>$lowestHealthRatio,'checkpoints'=>$checkpoints,'maxHealth'=>$run->maxHealth, 'kills' => $run->kills, 'wave7' => $wave7,
        'finalRoom' => ($run->objective?->finalRoomReached ?? false), 'bossReached' => ($run->boss?->active ?? false),
        'bossDefeated' => ($run->boss?->defeated ?? false), 'objective' => $run->objective?->state, 'remaining' => $run->remaining, 'liveEnemies' => count($run->enemies), 'player' => [$run->x, $run->y, $run->z], 'stalledEnemies' => $run->state === 'play' ? array_values($run->enemies) : []];
}

if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    $seeds = max(1, min(100, (int)($argv[1] ?? 5)));
    $limit = max(30.0, (float)($argv[2] ?? 600));
    $rows = [];
    $scenario = $argv[3] ?? 'both';
    if (!in_array($scenario, ['route', 'survival', 'both'], true)) throw new InvalidArgumentException('Scenario: route, survival or both');
    $scenarios = $scenario === 'both' ? ['route', 'survival'] : [$scenario];
    foreach ($scenarios as $mode) foreach (['faible', 'moyen', 'bon'] as $profile) for ($seed = 1; $seed <= $seeds; $seed++) $rows[] = simulateProfile($profile, $seed, $limit, $mode);
    $summary = [];
    foreach ($scenarios as $mode) foreach (['faible', 'moyen', 'bon'] as $profile) {
        $group = array_values(array_filter($rows, static fn ($r) => $r['profile'] === $profile && $r['scenario'] === $mode));
        $wave7 = array_values(array_filter($group, static fn ($r) => $r['wave7'] !== null));
        $deaths = []; $weapons = [];
        foreach ($group as $r) if ($r['result'] === 'dead') $deaths[$r['wave']] = ($deaths[$r['wave']] ?? 0) + 1;
        foreach ($wave7 as $r) $weapons[$r['wave7']['weapon']] = ($weapons[$r['wave7']['weapon']] ?? 0) + 1;
        $summary[$mode][$profile] = ['runs' => count($group), 'deathWaves' => $deaths, 'wave7Weapons' => $weapons, 'meanHealth' => array_sum(array_column($group, 'health')) / count($group), 'timeouts'=>count(array_filter($group,static fn($r)=>in_array($r['result'],['play','upgrade'],true))), 'finalRoomPercent' => 100 * count(array_filter($group, static fn ($r) => $r['finalRoom'])) / count($group),
            'bossPercent' => 100 * count(array_filter($group, static fn ($r) => $r['bossReached'])) / count($group),
            'bossWinPercent' => 100 * count(array_filter($group, static fn ($r) => $r['bossDefeated'])) / count($group),
            'victoryPercent' => 100 * count(array_filter($group, static fn ($r) => $r['result'] === 'won')) / count($group),
            'wave7Samples' => count($wave7), 'wave7MeanLevel' => $wave7 === [] ? null : array_sum(array_map(static fn ($r) => $r['wave7']['level'], $wave7)) / count($wave7),
            'wave7MeanHealth' => $wave7 === [] ? null : array_sum(array_map(static fn ($r) => $r['wave7']['health'], $wave7)) / count($wave7)];
    }
    $distribution = static function (array $values): ?array {
        return $values === [] ? null : ['min'=>min($values),'mean'=>array_sum($values)/count($values),'max'=>max($values)];
    };
    foreach ($scenarios as $mode) foreach (['faible','moyen','bon'] as $profile) {
        $group = array_values(array_filter($rows,static fn($r)=>$r['scenario']===$mode && $r['profile']===$profile));
        $stats = &$summary[$mode][$profile];
        $stats['treasureRecoveredPercent'] = 100*count(array_filter($group,static fn($r)=>$r['checkpoints']['treasure']!==null))/count($group);
        foreach (['level','health','lowestHealthRatio'] as $field) $stats['distribution'][$field]=$distribution(array_column($group,$field));
        $stats['weapons']=[];
        foreach ($group as $r) $stats['weapons'][$r['weapon']]=($stats['weapons'][$r['weapon']]??0)+1;
        $stats['nearDeathPercent']=100*count(array_filter($group,static fn($r)=>$r['lowestHealthRatio']<0.3))/count($group);
        foreach (['finalRoom','boss','bossDefeated','treasure'] as $event) {
            $events=array_values(array_filter(array_map(static fn($r)=>$r['checkpoints'][$event],$group),static fn($r)=>$r!==null));
            $stats['checkpoints'][$event]=['count'=>count($events)];
            foreach (['health','maxHealth','healthRatio','level','time'] as $field) $stats['checkpoints'][$event][$field]=$distribution(array_column($events,$field));
        }
        unset($stats);
    }
    echo json_encode(['note' => 'Input-policy guardrail, not a human win-rate prediction. Timeouts remain play/upgrade; no teleporting or extra XP.', 'summary' => $summary, 'runs' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}
