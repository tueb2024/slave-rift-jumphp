#!/usr/bin/env python3
from pathlib import Path
import re

RUN_FILES = [Path('src/Game/Run.php'), Path('games/slave-rift/src/Game/Run.php')]
SCENE_FILES = [Path('src/Scene/PlayScene.php'), Path('games/slave-rift/src/Scene/PlayScene.php')]


def replace_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count != 1:
        raise RuntimeError(f'{label}: expected exactly one match, found {count}')
    return text.replace(old, new, 1)


def regex_once(text: str, pattern: str, replacement: str, label: str) -> str:
    text, count = re.subn(pattern, replacement, text, count=1, flags=re.S)
    if count != 1:
        raise RuntimeError(f'{label}: expected exactly one regex match, found {count}')
    return text


def patch_run(path: Path) -> None:
    text = path.read_text()

    text = replace_once(
        text,
        "    public bool $pulseUnlocked = false;\n    public int $autoTier = 0;\n    public string $autoWeapon = 'LOCKED';\n    public int $autoGuns = 1;",
        "    public bool $pulseUnlocked = false;\n    public int $primaryStage = 0;\n    // Compatibility mirrors for the current HUD renderer. primaryStage is authoritative.\n    public int $autoTier = 0;\n    public string $autoWeapon = 'COLT';\n    public int $autoGuns = 1;",
        f'{path}: primary public state',
    )
    text = replace_once(
        text,
        "    public string $message = 'COLT .45 AUTO - KEYBOARD CONTROL';",
        "    public string $message = 'COLT .45 - RHYTHM FIRE';",
        f'{path}: initial message',
    )
    text = replace_once(
        text,
        "    private float $manualClock = 0.0;\n    private float $autoClock = 0.0;\n    private float $pulseClock = 1.0;",
        "    private float $pulseClock = 1.0;\n    /** @var list<array{delay:float,guns:int,split:bool,stage:int,lateral:float}> */\n    private array $primaryBurstQueue = [];\n    /** @var list<int> */\n    private array $primaryAudioEvents = [];\n    private int $primaryGunPhase = 0;",
        f'{path}: primary private state',
    )
    text = replace_once(
        text,
        "        $this->lastGroundedPosition = [$this->x, $this->y, $this->z];\n        $this->rng = max(1, $seed % 65521);",
        "        $this->lastGroundedPosition = [$this->x, $this->y, $this->z];\n        $this->rng = max(1, $seed % 65521);\n        $this->syncPrimaryStage();",
        f'{path}: constructor stage sync',
    )
    text = replace_once(
        text,
        "    public function step(float $dt, float $forward, float $strafe, float $turn, bool $fire = false, bool $jump = false, bool $special = false): void",
        "    public function step(float $dt, float $forward, float $strafe, float $turn, bool $fire = false, bool $jump = false, bool $special = false, array $rhythmSteps = []): void",
        f'{path}: step signature',
    )
    old_weapon_step = """        $this->manualClock -= $dt;
        $this->autoClock -= $dt;
        $this->pulseClock -= $dt;

        // Every weapon is automatic. The keyboard controls position and facing;
        // the simulation owns all weapon clocks so browser frame drops cannot change cadence.
        if ($this->manualClock <= 0) $this->fireColt();
        if ($this->autoTier > 0 && $this->autoClock <= 0) $this->fireAutoWeapon();
        if ($this->pulseUnlocked && $this->pulseClock <= 0) $this->firePulse();"""
    new_weapon_step = """        $this->pulseClock -= $dt;
        $this->stepPrimaryBursts($dt);
        foreach ($rhythmSteps as $rhythmStep) $this->triggerPrimaryRhythmStep((int)$rhythmStep);
        if ($this->pulseUnlocked && $this->pulseClock <= 0) $this->firePulse();"""
    text = replace_once(text, old_weapon_step, new_weapon_step, f'{path}: rhythmic primary step')

    for obsolete in [
        "            'STEN' => $this->setAutoTier(1),\n",
        "            'SMG' => $this->setAutoTier(2),\n",
        "            'MACHINE GUN' => $this->setAutoTier(3),\n",
        "            'SULFATEUSE' => $this->setAutoTier(4),\n",
        "            'AKIMBO' => $this->autoGuns = max(2, $this->autoGuns),\n",
        "            'TRIPLE GUN' => $this->autoGuns = max(3, $this->autoGuns),\n",
    ]:
        text = replace_once(text, obsolete, '', f'{path}: remove old primary upgrade {obsolete.strip()}')

    primary_state_methods = r'''    private function syncPrimaryStage(): void
    {
        $next = self::primaryStageForLevel($this->level);
        $changed = $next !== $this->primaryStage;
        $this->primaryStage = $next;
        [$tier, $weapon, $guns] = match ($next) {
            0 => [0, 'COLT', 1],
            1 => [0, 'COLT', 2],
            2 => [1, 'MP44', 1],
            3 => [1, 'MP44', 2],
            4 => [3, 'BAR', 1],
            5 => [4, 'GATLING', 1],
            default => [4, 'GATLING', 2],
        };
        $this->autoTier = $tier;
        $this->autoWeapon = $weapon;
        $this->autoGuns = $guns;
        if ($changed) {
            $this->primaryBurstQueue = [];
            $this->message = $this->primaryWeaponLabel() . ' / RHYTHM LOCK';
            $this->messageTime = 2.6;
        }
    }

    public static function primaryStageForLevel(int $level): int
    {
        return match (true) {
            $level >= 24 => 6,
            $level >= 20 => 5,
            $level >= 16 => 4,
            $level >= 12 => 3,
            $level >= 8 => 2,
            $level >= 4 => 1,
            default => 0,
        };
    }

    public function primaryWeaponLabel(): string
    {
        return match ($this->primaryStage) {
            0 => 'COLT .45',
            1 => 'DUAL COLT',
            2 => 'MP44',
            3 => 'DUAL MP44',
            4 => 'BAR',
            5 => 'GATLING',
            default => 'DUAL GATLING',
        };
    }

    /** @return list<int> primary stages that actually emitted a shot event */
    public function drainPrimaryAudioEvents(): array
    {
        $events = $this->primaryAudioEvents;
        $this->primaryAudioEvents = [];
        return $events;
    }
'''
    text = regex_once(
        text,
        r"    private function setAutoTier\(int \$tier\): void\n    \{.*?\n    \}\n(?=\n    public function upgradeDescription)",
        primary_state_methods.rstrip(),
        f'{path}: replace setAutoTier',
    )

    text = replace_once(
        text,
        "            'POWER' => '+35% COLT / +28% AUTO / STRONGER SECONDARIES',",
        "            'POWER' => '+30% PRIMARY DAMAGE / STRONGER SECONDARIES',",
        f'{path}: power description',
    )
    text = replace_once(
        text,
        "            'HASTE' => 'FASTER COLT / AUTO / SECONDARY CYCLES',",
        "            'HASTE' => 'FASTER SECONDARY CYCLES',",
        f'{path}: haste description',
    )
    for obsolete in [
        "            'STEN' => 'UNLOCK 0.27s AUTO WEAPON',\n",
        "            'SMG' => 'EVOLVE AUTO WEAPON TO 0.20s',\n",
        "            'MACHINE GUN' => 'EVOLVE AUTO WEAPON TO 0.145s',\n",
        "            'SULFATEUSE' => 'EVOLVE AUTO WEAPON TO 0.095s',\n",
        "            'AKIMBO' => 'SECOND SULFATEUSE / SPLIT OR FOCUS FIRE',\n",
        "            'TRIPLE GUN' => 'THIRD SULFATEUSE',\n",
    ]:
        text = replace_once(text, obsolete, '', f'{path}: remove old description')

    arsenal = r'''    public function arsenalLabel(): string
    {
        $parts = [$this->primaryWeaponLabel()];
        if ($this->flameRank > 0) $parts[] = 'FLAME L' . $this->flameRank;
        if ($this->shurikenRank > 0) $parts[] = 'SHURIKEN L' . $this->shurikenRank;
        if ($this->pulseUnlocked) $parts[] = 'PULSE L' . max(1, $this->pulseRank);
        return implode(' + ', $parts);
    }
'''
    text = regex_once(
        text,
        r"    public function arsenalLabel\(\): string\n    \{.*?\n    \}\n(?=\n    /\*\* Demo controls)",
        arsenal.rstrip(),
        f'{path}: arsenal label',
    )

    primary_fire = r'''    private function triggerPrimaryRhythmStep(int $step): void
    {
        $step = (($step % 16) + 16) % 16;
        $beat = 60.0 / (RhythmClock::BPM_BASE + max(0, $this->level - 1) * RhythmClock::BPM_PER_LEVEL);
        switch ($this->primaryStage) {
            case 0:
                if (($step % 4) === 0) $this->firePrimaryEvent(1, false, 0.0, 0);
                break;
            case 1:
                if (($step % 2) === 0) {
                    $lateral = $this->primaryGunPhase === 0 ? -0.16 : 0.16;
                    $this->firePrimaryEvent(1, false, $lateral, 1);
                    $this->primaryGunPhase = 1 - $this->primaryGunPhase;
                }
                break;
            case 2:
            case 3:
                if (($step % 4) !== 0) break;
                $guns = $this->primaryStage === 3 ? 2 : 1;
                $stage = $this->primaryStage;
                $this->firePrimaryEvent($guns, false, 0.0, $stage);
                // Long-short spacing gives the MP44 burst a deliberate swing.
                $this->queuePrimaryBurst($beat * 0.42, $guns, false, $stage);
                $this->queuePrimaryBurst($beat * 0.66, $guns, false, $stage);
                break;
            case 4:
                if (($step % 4) !== 0) break;
                $this->firePrimaryEvent(1, false, 0.0, 4);
                foreach ([0.11, 0.22, 0.33, 0.44, 0.55] as $fraction) {
                    $this->queuePrimaryBurst($beat * $fraction, 1, false, 4);
                }
                break;
            case 5:
                $this->firePrimaryEvent(1, false, 0.0, 5);
                break;
            default:
                // Ultimate stage: one rhythmic event, two guns, two targets when available.
                $this->firePrimaryEvent(2, true, 0.0, 6);
                break;
        }
    }

    private function queuePrimaryBurst(float $delay, int $guns, bool $split, int $stage, float $lateral = 0.0): void
    {
        if (count($this->primaryBurstQueue) >= 24) array_shift($this->primaryBurstQueue);
        $this->primaryBurstQueue[] = compact('delay', 'guns', 'split', 'stage', 'lateral');
    }

    private function stepPrimaryBursts(float $dt): void
    {
        if ($this->primaryBurstQueue === []) return;
        $ready = [];
        foreach ($this->primaryBurstQueue as $index => &$burst) {
            $burst['delay'] -= $dt;
            if ($burst['delay'] <= 0.0) {
                $ready[] = $burst;
                unset($this->primaryBurstQueue[$index]);
            }
        }
        unset($burst);
        $this->primaryBurstQueue = array_values($this->primaryBurstQueue);
        foreach ($ready as $burst) {
            $this->firePrimaryEvent($burst['guns'], $burst['split'], $burst['lateral'], $burst['stage']);
        }
    }

    private function firePrimaryEvent(int $guns, bool $splitTargets = false, float $singleLateral = 0.0, ?int $stage = null): void
    {
        $stage ??= $this->primaryStage;
        $targetCount = $splitTargets ? max(1, $guns) : 1;
        $cone = $stage >= 5 ? 0.24 : 0.18;
        $targets = $this->findTargets(19.0, $cone, $targetCount);
        if ($targets === []) return;

        $baseDamage = match ($stage) {
            0, 1 => 38,
            2, 3 => 14,
            4 => 10,
            default => 13,
        };
        $damage = (int)round($baseDamage * (1 + $this->damageRank * 0.30));
        $fired = 0;
        for ($gun = 0; $gun < max(1, $guns); $gun++) {
            $target = $splitTargets ? ($targets[$gun] ?? $targets[0]) : $targets[0];
            if (!isset($this->enemies[$target])) continue;
            $enemy = $this->enemies[$target];
            $lateral = $guns > 1 ? ($gun - ($guns - 1) / 2) * 0.22 : $singleLateral;
            $this->addVisualTrace($enemy, 0.14, $stage <= 1 ? 'pistol' : 'auto', $lateral);
            $this->damage($target, $damage);
            $this->shots++;
            $this->hits++;
            $fired++;
        }
        if ($fired === 0) return;
        $this->muzzle = max($this->muzzle, 0.11);
        if (count($this->primaryAudioEvents) >= 32) array_shift($this->primaryAudioEvents);
        $this->primaryAudioEvents[] = $stage;
    }
'''
    text = regex_once(
        text,
        r"    private function fireColt\(\): void\n    \{.*?\n    \}\n\n    private function fireAutoWeapon\(\): void\n    \{.*?\n    \}\n(?=\n    private function stepFlamethrower)",
        primary_fire.rstrip(),
        f'{path}: replace primary fire methods',
    )

    text = replace_once(
        text,
        "        $this->level++;\n        $this->nextXp = 6 + ($this->level - 1) * 4;",
        "        $this->level++;\n        $this->nextXp = 6 + ($this->level - 1) * 4;\n        $this->syncPrimaryStage();",
        f'{path}: level primary sync',
    )

    new_pool = r'''    /** @return list<string> */
    private function weaponUpgradePool(): array
    {
        $pool = [];
        if ($this->level >= 3 && $this->flameRank < 3) $pool[] = 'FLAMETHROWER';
        if ($this->level >= 3 && $this->shurikenRank < 3) $pool[] = 'SHURIKEN';
        return $pool;
    }
'''
    text = regex_once(
        text,
        r"    /\*\* @return list<string> \*/\n    private function weaponUpgradePool\(\): array\n    \{.*?\n    \}\n(?=\n    private function resetCombo)",
        new_pool.rstrip(),
        f'{path}: weapon upgrade pool',
    )

    path.write_text(text)
    print(f'patched {path}')


def patch_scene(path: Path) -> None:
    text = path.read_text()
    text = replace_once(
        text,
        "            $this->rhythm->advance($h, $this->run->level);\n            $this->run->step($h, $forward, $strafe, $turn, $fire, $this->jumpPending, false);",
        "            $rhythmSteps = $this->rhythm->advance($h, $this->run->level);\n            $this->run->step($h, $forward, $strafe, $turn, $fire, $this->jumpPending, false, $rhythmSteps);",
        f'{path}: pass rhythm to simulation',
    )
    old_audio = """        $this->weaponSfxCooldown = max(0.0, $this->weaponSfxCooldown - $dt);
        if ($this->run->muzzle > $this->prevMuzzle + 0.01 && $this->weaponSfxCooldown <= 0.0) {
            $name = $this->run->autoTier >= 3 ? 'heavy_fire' : ($this->run->autoTier > 0 ? 'sten_fire' : 'colt_fire');
            $this->sound()->playSfx($name, 0, true);
            $this->weaponSfxCooldown = $this->run->autoTier > 0 ? 0.12 : 0.20;
        }"""
    new_audio = """        foreach ($this->run->drainPrimaryAudioEvents() as $stage) {
            $this->sound()->playSfx('rift_hat', 0, false);
            $name = $stage <= 1 ? 'colt_fire' : ($stage <= 3 ? 'sten_fire' : 'heavy_fire');
            $this->sound()->playSfx($name, 0, true);
        }"""
    text = replace_once(text, old_audio, new_audio, f'{path}: rhythmic shot audio')

    old_key = """        $weaponKey = match (true) {
            $run->autoTier === 0 => 'weapon_colt',
            $run->autoTier <= 2 => 'weapon_mp44',
            $run->autoTier === 3 => 'weapon_bar',
            default => 'weapon_gatling',
        };"""
    new_key = """        $weaponKey = match (true) {
            $run->primaryStage <= 1 => 'weapon_colt',
            $run->primaryStage <= 3 => 'weapon_mp44',
            $run->primaryStage === 4 => 'weapon_bar',
            default => 'weapon_gatling',
        };"""
    text = replace_once(text, old_key, new_key, f'{path}: primary sprite mapping')
    text = replace_once(
        text,
        "        $drawH = (int)round($h * ($run->autoTier === 0 ? 0.27 : 0.29));",
        "        $drawH = (int)round($h * ($run->primaryStage <= 1 ? 0.27 : 0.29));",
        f'{path}: primary sprite size',
    )
    text = replace_once(
        text,
        "        $copies = $run->autoTier >= 4 ? max(1, min(3, $run->autoGuns)) : 1;",
        "        $copies = in_array($run->primaryStage, [1, 3, 6], true) ? 2 : 1;",
        f'{path}: dual weapon copies',
    )
    text = replace_once(
        text,
        "        $arsenal = $run->arsenalLabel() === 'COLT .45 ONLY' ? 'COLT .45 AUTO' : $run->arsenalLabel();",
        "        $arsenal = $run->arsenalLabel();",
        f'{path}: arsenal label',
    )
    path.write_text(text)
    print(f'patched {path}')


for file in RUN_FILES:
    patch_run(file)
for file in SCENE_FILES:
    patch_scene(file)
