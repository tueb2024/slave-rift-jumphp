<?php

namespace App\Game;

/** Bounded, deterministic PPV simulation. No renderer or host dependency. */
final class Run
{
    public const PLAYER_RADIUS = 0.28;
    public const PLAYER_HALF_HEIGHT = 0.70;
    public const MOVE_SPEED = 3.8;
    public const TURN_SPEED = 2.35;
    public const JUMP_SPEED = 5.2;
    public const GRAVITY = 13.5;
    public const MAX_ENEMIES = 48;
    public const MAX_PICKUPS = 80;
    public const HEALTH_CAP = 180;
    public const MAX_VISUAL_TRACES = 40;
    public const MAX_VISUAL_CORPSES = 16;

    public float $x;
    public float $y;
    public float $z;
    public float $yaw;
    public float $vy = 0.0;
    public bool $grounded = false;
    public int $health = 100;
    public int $maxHealth = 100;
    public int $shots = 0;
    public int $spawned = 0;
    public readonly GameMode $mode;
    public readonly XpCurve $xpCurve;
    public int $collectedXp = 0;
    public int $droppedXp = 0;
    public int $hits = 0;
    public int $kills = 0;
    public int $score = 0;
    public int $combo = 0;
    public float $comboTime = 0.0;
    public float $multiplier = 1.0;
    public float $muzzle = 0.0;
    public float $hurt = 0.0;
    public float $pulse = 0.0;
    public float $specialFlash = 0.0;
    public float $time = 0.0;
    public string $state = 'play';
    public array $enemies = [];
    public readonly Navigation $navigation;
    public int $navigationRecoveries = 0;
    private int $navigationBudget = 2;
    private array $botPath = [];
    private ?array $botGoal = null;
    private ?array $botPreviousPosition = null;
    private float $botRepathAt = 0.0;
    /** @var array<int,array<string,mixed>> Short-lived visual death animations; excluded from live enemy pressure. */
    public array $corpses = [];
    public array $pickups = [];
    public array $traces = [];
    public int $losTests = 0;
    public int $collisionTests = 0;
    public int $neighborChecks = 0;
    public int $wave = 1;
    public float $waveTime = 0.0;
    public float $intermission = 0.0;
    public int $remaining = 12;
    public int $level = 1;
    public int $xp = 0;
    public int $nextXp = 6;
    public bool $pulseUnlocked = false;
    public int $primaryStage = 0;
    // Compatibility mirrors for the current HUD renderer. primaryStage is authoritative.
    public int $autoTier = 0;
    public string $autoWeapon = 'COLT';
    public int $autoGuns = 1;
    public int $flameRank = 0;
    public int $rocketRank = 0;
    public int $sniperRank = 0;
    public int $artilleryRank = 0;
    public int $empRank = 0;
    public ?string $equippedSecondary = null;
    private float $empClock = .5;
    public float $empFx = 0.0;
    public float $bossStun = 0.0;
    public const SHOTGUN_PELLETS = 8;
    public const SHOTGUN_DAMAGE = 70;
    public const SHOTGUN_RANGE = 7.0;
    public const SHOTGUN_RELOAD = 1.15;
    public const MAX_BOSS_PROJECTILES = 24;
    public float $shotgunReload = 0.0;
    public float $shotgunFlash = 0.0;
    public int $shotgunSerial = 0;
    public int $bossShots = 0;
    public int $bossProjectileHits = 0;
    public array $bossProjectiles = [];
    public array $combatAudio = [];
    public int $primaryShotSerial = 0;
    public int $primaryShotSide = 0;
    public array $projectiles = [];
    public array $explosions = [];
    public array $flameSegments = [];
    private float $sniperClock = 1.0;
    private float $artilleryClock = 1.5;
    private int $hordeBossWave = 0;
    public float $flameActive = 0.0;
    public array $choices = [];
    public string $message = 'COLT .45 - TIR RYTHMÉ';
    public float $messageTime = 4.0;

    public ?TreasureDirector $objective = null;
    public ?EncounterGates $encounters = null;
    public ?BossController $boss = null;
    public ?array $bossPosition = null;
    public ?array $bossWarning = null;
    public array $route = [];
    private int $routeIndex = 0;
    private float $bossAttackClock = 2.5;

    private float $pulseClock = 1.0;
    private SecondaryScheduler $secondaryScheduler;
    private array $secondaryLastFired = [];
    private array $secondaryEvents = [];
    /** @var list<array{delay:float,guns:int,split:bool,stage:int,lateral:float}> */
    private array $primaryBurstQueue = [];
    /** @var list<int> */
    private array $primaryAudioEvents = [];
    private int $primaryGunPhase = 0;
    private float $flameCooldownClock = 4.5;
    private float $flameTickClock = 0.0;
    private float $rocketClock = 3.0;
    private float $spawnClock = 0.4;
    private float $invincible = 0.0;
    private int $nextId = 1;
    private array $recentSpawnPoints = [];
    private int $rng;
    private array $lastGroundedPosition;
    private int $damageRank = 0;
    private int $hasteRank = 0;
    private int $magnetRank = 0;
    private int $pulseRank = 0;

    public function __construct(public readonly BrushMap $map, int $seed = 1337, ?GameMode $mode = null, ?XpCurve $xpCurve = null)
    {
        $this->navigation = new Navigation($map);
        $this->xpCurve = $xpCurve ?? new XpCurve();
        $this->secondaryScheduler = new SecondaryScheduler();
        $this->mode = $mode ?? GameMode::fixed($seed);
        $this->remaining = $this->mode->waveBudget(1);
        $this->nextXp=$this->mode->xpThreshold(1);
        // Quake deathmatch maps often use info_player_start as an intermission
        // camera. DM1's is above empty space, so use a real deathmatch spawn.
        [$this->x, $this->y, $this->z] = $map->spawnPoints[0] ?? $map->playerStart;
        $this->yaw = $map->playerYaw;
        $this->lastGroundedPosition = [$this->x, $this->y, $this->z];
        $this->rng = max(1, $seed % 65521);
        $this->syncPrimaryStage();
    }

    public function step(float $dt, float $forward, float $strafe, float $turn, bool $fire = false, bool $jump = false, bool $special = false, array $rhythmSteps = []): void
    {
        if ($this->state !== 'play') return;
        $dt = max(0.0, min($dt, 0.05));
        $this->losTests = $this->collisionTests = $this->neighborChecks = 0;
        $this->time += $dt;
        $this->waveTime += $dt;
        foreach (['muzzle', 'hurt', 'pulse', 'specialFlash', 'messageTime', 'invincible','empFx','bossStun','shotgunReload','shotgunFlash'] as $field) {
            $this->$field = max(0.0, $this->$field - $dt);
        }
        if ($this->comboTime > 0) {
            $this->comboTime = max(0.0, $this->comboTime - $dt);
            if ($this->comboTime === 0.0) $this->resetCombo();
        }
        foreach ($this->traces as $i => &$trace) {
            $trace['life'] -= $dt;
            if ($trace['life'] <= 0) unset($this->traces[$i]);
        }
        unset($trace);
        foreach ($this->corpses as $i => &$corpse) {
            $corpse['age'] += $dt;
            if ($corpse['age'] >= 0.64) unset($this->corpses[$i]);
        }
        unset($corpse);
        $this->yaw -= max(-1.0, min(1.0, $turn)) * self::TURN_SPEED * $dt;
        $length = max(1.0, hypot($forward, $strafe));
        $dx = (sin($this->yaw) * $forward - cos($this->yaw) * $strafe) / $length * self::MOVE_SPEED * $dt;
        $dz = (cos($this->yaw) * $forward + sin($this->yaw) * $strafe) / $length * self::MOVE_SPEED * $dt;
        if ($jump && $this->grounded) {
            $this->vy = self::JUMP_SPEED;
            $this->grounded = false;
        }
        $this->movePlayer($dx, $dz);
        $this->vy -= self::GRAVITY * $dt;
        $this->vertical($this->vy * $dt);
        if ($this->grounded) $this->lastGroundedPosition = [$this->x, $this->y, $this->z];
        if ($this->y < $this->lastGroundedPosition[1] - 3.0) {
            [$this->x, $this->y, $this->z] = $this->lastGroundedPosition;
            $this->vy = 0.0;
            $this->grounded = true;
        }

        $this->stepObjective($dt);
        if ($this->state !== 'play') return;
        $this->stepHordeBoss($dt);
        $this->stepWaves($dt);
        $this->stepEnemies($dt);
        if ($this->health <= 0) {
            $this->health = 0;
            $this->state = 'dead';
            return;
        }

        if ($fire) $this->fireShotgun();
        $this->stepPrimaryBursts($dt);
        foreach ($rhythmSteps as $rhythmStep) $this->triggerPrimaryRhythmStep((int)$rhythmStep);
        $this->stepSecondaries($dt);
        $this->stepProjectiles($dt);
        $this->updateFlameSegments();

        $this->stepPickups($dt);
        $this->combatAudio=array_slice($this->combatAudio,-16);
    }

    public function progressionSnapshot(): array
    {
        return ['empRank'=>$this->empRank,'empClock'=>$this->empClock,'equippedSecondary'=>$this->equippedSecondary,'level'=>$this->level,'xp'=>$this->xp,'collectedXp'=>$this->collectedXp,'maxHealth'=>$this->maxHealth,
            'health'=>$this->health,'damageRank'=>$this->damageRank,'hasteRank'=>$this->hasteRank,'magnetRank'=>$this->magnetRank,
            'pulseRank'=>$this->pulseRank,'pulseUnlocked'=>$this->pulseUnlocked,'flameRank'=>$this->flameRank,'rocketRank'=>$this->rocketRank,
            'pulseClock'=>$this->pulseClock,'flameCooldownClock'=>$this->flameCooldownClock,'rocketClock'=>$this->rocketClock,
            'score'=>$this->score,'kills'=>$this->kills,'sniperRank'=>$this->sniperRank,'artilleryRank'=>$this->artilleryRank,
            'sniperClock'=>$this->sniperClock,'artilleryClock'=>$this->artilleryClock,'time'=>$this->time,
            'flameActive'=>$this->flameActive,'flameTickClock'=>$this->flameTickClock,'scheduler'=>$this->secondaryScheduler->snapshot()];
    }

    public function restoreProgression(array $saved, float $healMissing = 0.35): void
    {
        foreach (['level','xp','collectedXp','maxHealth','health','damageRank','hasteRank','magnetRank','pulseRank',
            'pulseUnlocked','flameRank','rocketRank','sniperRank','artilleryRank','sniperClock','artilleryClock','time','flameActive','flameTickClock','pulseClock','flameCooldownClock','rocketClock','score','kills','empRank','empClock','equippedSecondary'] as $field) {
            if (array_key_exists($field,$saved)) $this->$field=$saved[$field];
        }
        if(isset($saved['scheduler']))$this->secondaryScheduler->restore($saved['scheduler']);
        $power=$this->selectedSecondary();if($power!==null)$this->equipSecondary($power);
        $this->health=min($this->maxHealth,$this->health+(int)round(($this->maxHealth-$this->health)*max(0,min(1,$healMissing))));
        $this->nextXp=$this->mode->xpThreshold($this->level);
        $this->syncPrimaryStage();
    }

    public function setObjective(AssembledLevel $level, int $finalWaveSize = 12): void
    {
        $this->objective = TreasureDirector::fromLevel($level, $finalWaveSize);
        $this->navigation->setRooms($level->rooms);
        $waveCount=$this->mode->hasFiniteEncounters()?$this->mode->waveCount():3;
        $this->encounters=new EncounterGates($level->rooms,$waveCount);
        $this->navigation->setBlockers($this->encounters->blockers());
        $this->route = [];
        foreach ($level->rooms as $room) {
            if (!$room['mainPath']) continue;
            foreach ($room['connectors'] as $c) if ($c['role'] === 'main_exit') {
                $this->route[] = ['x' => $c['position'][0], 'y' => $c['position'][1] + 0.75, 'z' => $c['position'][2]];
            }
        }
        if ($level->treasureAnchor !== null) $this->route[] = $level->treasureAnchor;
        if ($this->objective === null || $level->bossAnchor === null) return;
        // Wall opposite the entry, behind the treasure. Works after room rotation.
        $anchor = $level->bossAnchor;
        $treasure = $level->treasureAnchor;
        $dx = $treasure['x'] - $anchor['x']; $dz = $treasure['z'] - $anchor['z'];
        $length = max(0.001, hypot($dx, $dz));
        $nx = $dx / $length; $nz = $dz / $length;
        $this->bossPosition = ['x' => $anchor['x'] + $nx * $length * (8.5/6),
            'y' => $anchor['y'] + 0.5, 'z' => $anchor['z'] + $nz * $length * (8.5/6)];
        $this->boss = new BossController(BossController::WALL, $this->mode->bossHealth, 6.0);
        $positions = [];
        foreach ([-2.5, 0.0, 2.5] as $offset) {
            $positions[] = ['x' => $this->bossPosition['x'] + $nz * $offset - $nx * 0.8,
                'y' => $this->bossPosition['y'], 'z' => $this->bossPosition['z'] - $nx * $offset - $nz * 0.8];
        }
        $this->boss->armOrbs($positions, 48);
    }

    private function advanceRoute(): void
    {
        while (isset($this->route[$this->routeIndex + 1])) {
            $p = $this->route[$this->routeIndex];
            $closed=false;
            foreach($this->encounters?->blockers()??[] as $g)if(hypot($p['x']-$g['position'][0],$p['z']-$g['position'][2])<.1)$closed=true;
            if ($closed || hypot($p['x'] - $this->x, $p['z'] - $this->z) > 0.65) break;
            $this->routeIndex++;
        }
    }

    public function routeTarget(): ?array
    {
        $target=$this->route[$this->routeIndex]??null;
        if($target!==null)foreach($this->encounters?->blockers()??[] as $g){
            if(hypot($target['x']-$g['position'][0],$target['z']-$g['position'][2])<.1){
                $target['x']-=$g['normal'][0]*.95;$target['z']-=$g['normal'][2]*.95;
            }
        }
        return $target;
    }

    private function stepObjective(float $dt): void
    {
        $this->advanceRoute();
        $objective = $this->objective;
        if ($objective === null) return;
        $wavesComplete=($this->encounters===null && !$this->mode->hasFiniteEncounters()) || ($this->encounters!==null && $this->encounters->blockers()===[]) || ($this->wave >= $this->mode->waveCount() && $this->remaining===0 && $this->enemies===[]);
        if (($objective->state !== TreasureDirector::EXPLORING || $wavesComplete) && $objective->updatePlayer($this->x, $this->y, $this->z)) {
            // Survivors retain their target and path across the final-room transition.
            $this->remaining = $objective->finalWaveSize;
            $this->intermission = 0.0;
            $this->message = 'SALLE FINALE : ELIMINEZ LES ENNEMIS';
            $this->messageTime = 3.0;
        }
        if ($objective->state === TreasureDirector::FINAL_WAVE && $this->remaining === 0 && $this->enemies === []) $objective->notifyFinalWaveCleared();
        if ($objective->state === TreasureDirector::BOSS_READY && $this->boss !== null) {
            $objective->startBoss();
            $this->boss->activate();
            $this->message = 'MODULES PUIS COEUR : CANON SCIE [F] / TAP TIR';
            $this->messageTime = 6.0;
        }
        if ($objective->state === TreasureDirector::BOSS && $this->boss !== null) {
            $this->updateBossCombat($dt);
            if ($this->boss->defeated) {
                $objective->notifyBossDefeated();$this->bossWarning=null;$this->bossProjectiles=[];
                if($this->bossPosition!==null)$this->explode([$this->bossPosition['x'],$this->bossPosition['y'],$this->bossPosition['z']],0,3.0);
                $this->message='SIEGE DÉTRUIT : RECUPEREZ LE TRESOR';$this->messageTime=6;
            }
        }
        if ($objective->state === TreasureDirector::COMPLETE) {
            $this->score += 10000;
            $this->state = 'won';
        }
    }

    private function stepHordeBoss(float $dt): void
    {
        if($this->mode->kind!==GameMode::HORDE)return;
        if($this->wave%5===0 && $this->hordeBossWave!==$this->wave && !($this->boss?->active&&!$this->boss->defeated)){
            $this->hordeBossWave=$this->wave;$tier=min(6,intdiv($this->wave,5));
            $this->boss=new BossController(BossController::WALL,2200+min(2500,($tier-1)*500),max(3.5,6-$tier*.35));
            $this->bossPosition=['x'=>0.0,'y'=>1.3,'z'=>$this->z>0?-13.0:13.0];$positions=[];
            foreach([-2.5,0,2.5]as$x)$positions[]=['x'=>$x,'y'=>1.2,'z'=>$this->bossPosition['z']];
            $this->boss->armOrbs($positions,48);$this->boss->activate();$this->bossAttackClock=1.8;
            $this->message='SIEGE : MODULES PUIS COEUR / VAGUE '.$this->wave;$this->messageTime=4;
        }
        if($this->boss?->active&&!$this->boss->defeated)$this->updateBossCombat($dt);
        if($this->boss?->defeated){$this->bossWarning=null;$this->bossProjectiles=[];}
    }

    /** Telegraph fixes the aim before launch; projectiles never home. EMP freezes the whole encounter. */
    private function updateBossCombat(float $dt): void
    {
        if ($this->bossStun > 0) return;
        $this->boss->update($dt);
        $this->stepBossProjectiles($dt);
        $this->bossAttackClock -= $dt;
        if ($this->bossWarning !== null) {
            $this->bossWarning['time'] -= $dt;
            if ($this->bossWarning['time'] <= 0) {
                $this->launchBossProjectiles($this->bossWarning);
                $this->bossWarning = null;
            }
        } elseif ($this->bossAttackClock <= 0) {
            $this->bossWarning = ['x'=>$this->x, 'y'=>$this->y+.2, 'z'=>$this->z, 'time'=>.9];
            $this->combatAudio[] = 'boss_charge';
            $this->bossAttackClock = max(1.6, (3.8-$this->boss->phase*.45)/$this->mode->bossPressure());
        }
    }

    private function launchBossProjectiles(array $target): void
    {
        $p = $this->bossPosition;
        $dx=$target['x']-$p['x']; $dz=$target['z']-$p['z'];
        $distance=max(.001,hypot($dx,$dz)); $angle=atan2($dx,$dz);
        $speed=5.0+$this->boss->phase*.65;
        $fan=match($this->boss->phase){1=>[0.0],2=>[-.22,0.0,.22],default=>[-.42,-.21,0.0,.21,.42]};
        foreach($fan as $offset){
            if(count($this->bossProjectiles)>=self::MAX_BOSS_PROJECTILES)break;
            $a=$angle+$offset;
            $this->bossProjectiles[]=['x'=>$p['x'], 'y'=>$p['y'], 'z'=>$p['z'],
                'vx'=>sin($a)*$speed, 'vy'=>($target['y']-$p['y'])/$distance*$speed,
                'vz'=>cos($a)*$speed, 'life'=>6.0, 'radius'=>.18, 'damage'=>12+$this->boss->phase*3];
            $this->bossShots++;
        }
        $this->combatAudio[]='boss_launch';
    }

    /** Substeps sweep the collision volume through walls and the player's vertical body. */
    private function stepBossProjectiles(float $dt): void
    {
        foreach($this->bossProjectiles as $id=>&$p){
            $p['life']-=$dt;
            $n=max(1,(int)ceil(hypot($p['vx'],$p['vz'])*$dt/.08));
            for($i=0;$i<$n;$i++){
                $p['x']+=$p['vx']*$dt/$n; $p['y']+=$p['vy']*$dt/$n; $p['z']+=$p['vz']*$dt/$n;
                if($p['life']<=0 || $this->collides($p['x'],$p['y'],$p['z'],$p['radius'],$p['radius'])){
                    unset($this->bossProjectiles[$id]); break;
                }
                if(hypot($p['x']-$this->x,$p['z']-$this->z)<self::PLAYER_RADIUS+$p['radius']
                    && abs($p['y']-$this->y)<self::PLAYER_HALF_HEIGHT+$p['radius']){
                    if($this->invincible<=0){
                        $this->health-=(int)round($p['damage']*$this->mode->damageScale($this->wave));
                        $this->hurt=.3; $this->invincible=.35; $this->bossProjectileHits++; $this->resetCombo();
                        $this->combatAudio[]='boss_impact';
                    }
                    unset($this->bossProjectiles[$id]); break;
                }
            }
        }
        unset($p);
        $this->bossProjectiles=array_values($this->bossProjectiles);
    }

    public function shotgunAvailable(): bool
    {
        return $this->boss !== null && $this->boss->active && !$this->boss->defeated;
    }

    /** Dedicated encounter tool: unlimited shells, manual aim, fixed spread and automatic reload. */
    public function fireShotgun(): bool
    {
        if($this->state!=='play' || !$this->shotgunAvailable() || $this->shotgunReload>0)return false;
        $this->shotgunReload=self::SHOTGUN_RELOAD; $this->shotgunFlash=.22; $this->shotgunSerial++;
        $this->combatAudio[]='shotgun_fire';
        $yaw=$this->yaw;
        for($pellet=0;$pellet<self::SHOTGUN_PELLETS;$pellet++){
            $this->yaw=$yaw+($pellet-3.5)*.022;
            $id=$this->findTarget(self::SHOTGUN_RANGE,.045);
            if($id!==null){
                $e=$this->enemies[$id]; $this->addVisualTrace($e,0.0,'shotgun');
                $this->damage($id,self::SHOTGUN_DAMAGE); $this->hits++;
            } else $this->fireAtBoss(self::SHOTGUN_DAMAGE,0,self::SHOTGUN_RANGE,.13,'shotgun','shotgun');
        }
        $this->yaw=$yaw;
        return true;
    }

    private function fireAtBoss(int $damage, int $stage, float $range = 24.0, float $cone = 1.05, ?string $traceType = null, string $source = 'primary'): bool
    {
        if ($this->boss === null || !$this->boss->active || $this->boss->defeated) return false;
        $targets = $this->boss->vulnerable ? [-1 => $this->bossPosition] : $this->boss->orbs;
        foreach ($targets as $index => $target) {
            if ($target === null || ($index >= 0 && !$target['alive'])) continue;
            if (hypot($target['x'] - $this->x, $target['z'] - $this->z) > $range) continue;
            if (abs(self::angleDelta(atan2($target['x'] - $this->x, $target['z'] - $this->z), $this->yaw)) > $cone) continue;
            if (!$this->lineClear([$this->x, $this->y + 0.35, $this->z], [$target['x'], $target['y'], $target['z']])) continue;
            $this->addVisualTrace($target, 0.0, $traceType ?? ($stage <= 1 ? 'pistol' : 'auto'));
            if ($index < 0) $this->boss->damage($damage, $source);
            else $this->boss->damageOrb($index, $damage);
            return true;
        }
        return false;
    }

    public function chooseUpgrade(int $slot): bool
    {
        if ($this->state !== 'upgrade' || !isset($this->choices[$slot])) return false;
        $choice = $this->choices[$slot];
        $power=match($choice){'PULSE'=>'pulse','FLAMETHROWER'=>'flame','MINI_ROCKETS'=>'rockets','SNIPER'=>'sniper','ARTILLERY'=>'artillery','EMP'=>'emp',default=>null};
        if($power!==null)$this->equipSecondary($power);
        match ($choice) {
            'EMP' => $this->empRank=min(3,$this->empRank+1),
            'POWER' => $this->damageRank++,
            'HASTE' => $this->hasteRank++,
            'MAGNET' => $this->magnetRank++,
            'PULSE' => $this->upgradePulse(),
            'FLAMETHROWER' => $this->upgradeFlamethrower(),
            'MINI_ROCKETS' => $this->upgradeMiniRockets(),
            'SNIPER' => $this->sniperRank = min(3,$this->sniperRank+1),
            'ARTILLERY' => $this->artilleryRank = min(3,$this->artilleryRank+1),
            default => $this->upgradeVitality(),
        };
        $this->choices = [];
        $this->state = 'play';
        $this->message = $this->upgradeLabel($choice) . ' / ' . $this->primaryWeaponLabel();
        $this->messageTime = 2.4;
        $this->checkLevel();
        return true;
    }

    private function upgradeVitality(): void
    {
        $this->maxHealth = min(self::HEALTH_CAP, $this->maxHealth + 15);
        $this->health = min($this->maxHealth, $this->health + 25);
    }

    private function upgradePulse(): void
    {
        $this->pulseUnlocked = true;
        $this->pulseRank++;
        $this->pulseClock = min($this->pulseClock, 0.35);
    }

    private function upgradeFlamethrower(): void
    {
        $this->flameRank = min(3, $this->flameRank + 1);
        $this->flameCooldownClock = min($this->flameCooldownClock, 0.45);
    }

    private function upgradeMiniRockets(): void
    {
        $this->rocketRank = min(4, $this->rocketRank + 1);
        $this->rocketClock = min($this->rocketClock, 0.45);
    }

    private function syncPrimaryStage(): void
    {
        $next = $this->mode->kind===GameMode::DAILY ? $this->mode->primaryStageForKills($this->kills) : $this->xpCurve->stageForLevel($this->level);
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
            $this->message = $this->primaryWeaponLabel() . ' / RYTHME SYNCHRONISÉ';
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
            1 => 'DOUBLE COLT',
            2 => 'MP44',
            3 => 'DOUBLE MP44',
            4 => 'BAR',
            5 => 'GATLING',
            default => 'DOUBLE GATLING',
        };
    }

    /** @return list<int> primary stages that actually emitted a shot event */
    public function drainPrimaryAudioEvents(): array
    {
        $events = $this->primaryAudioEvents;
        $this->primaryAudioEvents = [];
        return $events;
    }
    public function upgradeLabel(string $choice): string
    {
        return match ($choice) {
            'POWER' => 'PUISSANCE', 'HASTE' => 'CADENCE', 'MAGNET' => 'AIMANT',
            'VITALITY' => $this->maxHealth >= self::HEALTH_CAP ? 'SOINS' : 'VITALITÉ', 'PULSE' => 'IMPULSION',
            'FLAMETHROWER' => 'LANCE-FLAMMES', 'EMP'=>'IEM', 'MINI_ROCKETS' => 'ROQUETTES GUIDÉES', 'SNIPER'=>'RAYON DE PRÉCISION', 'ARTILLERY'=>'ARTILLERIE',
            default => $choice,
        };
    }

    public function upgradeDescription(string $choice): string
    {
        $id=match($choice){'PULSE'=>'pulse','FLAMETHROWER'=>'flame','MINI_ROCKETS'=>'rockets','SNIPER'=>'sniper','ARTILLERY'=>'artillery','EMP'=>'emp',default=>null};
        if($id!==null && $this->selectedSecondary()!==null && $this->selectedSecondary()!==$id)return 'REMPLACE LE POUVOIR / '.$this->upgradeLabel($choice);
        return match ($choice) {
            'POWER' => '+30% DÉGÂTS / SECONDAIRES RENFORCÉES',
            'HASTE' => 'SECONDAIRES PLUS RAPIDES',
            'MAGNET' => 'COLLECTE À PLUS GRANDE DISTANCE',
            'PULSE' => $this->pulseUnlocked ? 'IMPULSION PLUS FORTE ET PLUS LARGE' : 'DÉBLOQUE UNE IMPULSION RADIALE',
            'FLAMETHROWER' => $this->flameRank === 0 ? 'CÔNE DE FLAMMES PENDANT 2.4 S' : 'FLAMMES PLUS LONGUES ET PUISSANTES',
            'MINI_ROCKETS' => $this->rocketRank === 0 ? 'ROQUETTES VISIBLES / EXPLOSION DE ZONE' : 'PLUS DE ROQUETTES ET DE DÉGÂTS',
            'SNIPER' => 'RAYON LONGUE PORTÉE / CIBLE DANGEREUSE',
            'ARTILLERY' => 'EXPLOSION DE ZONE / REMPLACE LE POUVOIR',
            'EMP' => 'PARALYSE 2 S / RAYON 3 M / REMPLACE LE POUVOIR',
            default => $this->maxHealth >= self::HEALTH_CAP ? 'SOIGNE 25 PV' : '+15 PV MAX / SOIGNE 25 PV',
        };
    }

    public function arsenalLabel(): string
    {
        $parts = [$this->primaryWeaponLabel()];
        if ($this->flameRank > 0) $parts[] = 'FEU NIV ' . $this->flameRank;
        if ($this->rocketRank > 0) $parts[] = 'ROQUETTES NIV ' . $this->rocketRank;
        if ($this->pulseUnlocked) $parts[] = 'IMPULSION NIV ' . max(1, $this->pulseRank);
        return implode(' + ', $parts);
    }
    /** Demo controls, also used by the integration smoke test. */
    public function autopilot(): array
    {
        $target = null;
        $best = INF;
        $visibleTarget = null;
        $visibleScore = INF;
        foreach ($this->enemies as $enemy) {
            $d = hypot($enemy['x'] - $this->x, $enemy['z'] - $this->z);
            if ($d < $best) { $target = $enemy; $best = $d; }
            if ($d <= 19.0 && $this->lineClear(
                [$this->x, $this->y + 0.35, $this->z],
                [$enemy['x'], $enemy['y'] + 0.15, $enemy['z']],
            )) {
                $angle = abs(self::angleDelta(atan2($enemy['x'] - $this->x, $enemy['z'] - $this->z), $this->yaw));
                $score = $d + $angle * 5.0;
                if ($score < $visibleScore) { $visibleTarget = $enemy; $visibleScore = $score; }
            }
        }
        if ($target === null) return [0.25, 0.4, 0.3, false];
        if ($visibleTarget === null) {
            $goal = $this->navigationTarget($target);
            $goalAngle = atan2($goal['x']-$this->x,$goal['z']-$this->z);
            $error = self::angleDelta($goalAngle,$this->yaw);
            return [abs($error)<.7 ? .72 : .1,0.0,max(-1.0,min(1.0,-$error*1.5)),false];
        }
        // Prefer an enemy the weapons can actually hit. The nearest enemy may be
        // on the other side of a DM1 wall, where it would otherwise hold aim forever.
        $aimTarget = $visibleTarget;
        $error = self::angleDelta(atan2($aimTarget['x'] - $this->x, $aimTarget['z'] - $this->z), $this->yaw);
        $forward = hypot($visibleTarget['x'] - $this->x, $visibleTarget['z'] - $this->z) < 3.2 ? -0.35 : 0.25;
        return [$forward, 0.0, max(-1.0, min(1.0, -$error * 2)), abs($error) < 0.12];
    }

    private function stepWaves(float $dt): void
    {
        if ($this->objective !== null && in_array($this->objective->state, [TreasureDirector::BOSS_READY, TreasureDirector::BOSS, TreasureDirector::TREASURE_READY, TreasureDirector::COMPLETE], true)) return;
        if ($this->objective?->state === TreasureDirector::FINAL_WAVE && $this->remaining === 0 && $this->enemies === []) return;
        if ($this->intermission > 0) {
            $this->intermission = max(0.0, $this->intermission - $dt);
            if ($this->intermission <= 0) {
                $this->wave++;
                $this->waveTime = 0.0;
                $this->remaining = $this->mode->waveBudget($this->wave);
                $this->spawnClock = 0.0;
                $this->message = 'VAGUE ' . $this->wave . ($this->wave % 4 === 0 ? ' - BRUTES' : '');
                $this->messageTime = 2.5;
            }
            return;
        }
        if ($this->remaining === 0 && $this->enemies === [] && !($this->boss?->active && !$this->boss->defeated)) {
            if($this->encounters?->clear($this->wave)){
                $this->navigation->setBlockers($this->encounters->blockers());
                $this->botPath=[];
                $this->combatAudio[]='gate_open';
                $this->message='PORTE OUVERTE : AVANCEZ';$this->messageTime=3.0;
            }
            if($this->encounters!==null && $this->wave>=count($this->encounters->gates))return;
            if($this->mode->hasFiniteEncounters() && $this->wave >= $this->mode->waveCount())return;
            $waveBonus = (int)round(($this->wave * 150 + $this->combo * 50) * $this->multiplier);
            $this->score += $waveBonus;
            $this->intermission = $this->mode->intermissionSeconds();
            $this->health = min($this->maxHealth, $this->health + 4);
            $this->message = 'BONUS VAGUE +' . $waveBonus . ' / SOIN +4';
            $this->messageTime = 3.0;
            return;
        }
        if($this->encounters!==null && $this->objective?->state===TreasureDirector::EXPLORING
            && !$this->encounters->inZone($this->x,$this->y,$this->z,$this->wave))return;
        $this->spawnClock -= $dt;
        if ($this->remaining <= 0 || $this->spawnClock > 0 || count($this->enemies) >= $this->mode->enemyLimit($this->wave)) return;
        $batch = min($this->remaining, $this->mode->spawnBatch($this->wave));
        $before=$this->remaining;
        for ($i = 0; $i < $batch && count($this->enemies) < $this->mode->enemyLimit($this->wave); $i++) {
            if ($this->spawnEnemy()) $this->remaining--;
        }
        $this->spawnClock = $before===$this->remaining && $this->mode->hasFiniteEncounters() ? .12 : $this->mode->spawnInterval($this->wave,$this->kills);
    }

    private function spawnEnemy(): bool
    {
        $type = $this->chooseEnemyType();
        $radius = $this->enemyRadius($type);
        if ($this->map->spawnPoints !== []) {
            $candidates = [];
            foreach ($this->map->spawnPoints as $index => $point) {
                [$x, $mapY, $z] = $point;
                $y = $mapY - 0.20;
                if ($this->objective?->state === TreasureDirector::FINAL_WAVE && !$this->insideFinalArena($x, $z)) continue;
                if(!$this->spawnInEncounter($x,$y,$z))continue;
                $distance = hypot($x - $this->x, $z - $this->z);
                if ($distance < ($type === 'brute' ? 8.0 : 4.5) || $distance > 34.0) continue;
                // DM1 has stacked floors. A spawn directly above the player
                // reads as an enemy walking on the ceiling from below.
                if (abs($y - $this->y) > 1.5) continue;
                if ($this->collides($x, $y, $z, 0.42, 0.55)) continue;
                [$occupied, $nearby] = $this->spawnCrowding($x, $y, $z, $radius, $type);
                if ($occupied) continue;
                $angle = abs(self::angleDelta(atan2($x - $this->x, $z - $this->z), $this->yaw));
                $visible = $this->lineClear([$this->x, $this->y + 0.35, $this->z], [$x, $y + 0.25, $z]);
                if ($this->objective !== null && !$visible) continue;
                $distanceScore = abs($distance - ($type === 'brute' ? 14.0 : 8.0)) * 0.28;
                $frontPenalty = $angle < 1.05 ? (1.05 - $angle) * 5.5 : 0.0;
                $visibilityPenalty = $visible ? 2.4 : -1.3;
                $recentPenalty = 0.0;
                foreach ($this->recentSpawnPoints as $age => $used) {
                    if ($used === $index) $recentPenalty += 3.0 - $age * 0.8;
                }
                $candidates[] = [
                    'score' => $distanceScore + $frontPenalty + $visibilityPenalty + $nearby * 1.5 + $recentPenalty + $this->random() * 0.55,
                    'x' => $x, 'y' => $y, 'z' => $z, 'index' => $index,
                ];
            }
            if ($candidates !== []) {
                usort($candidates, static fn (array $a, array $b): int => $a['score'] <=> $b['score']);
                $spawn = $candidates[0];
                $this->rememberSpawnPoint($spawn['index']);
                return $this->spawnEnemyAt($spawn['x'], $spawn['y'], $spawn['z'], $type);
            }
        }
        $fallbackCandidates = [];
        for ($attempt = 0; $attempt < ($this->mode->hasFiniteEncounters()?36:20); $attempt++) {
            $a = $this->random() * M_PI * 2;
            $anchorIndex = null;
            if ($this->map->spawnPoints !== [] && !$this->mode->hasFiniteEncounters()) {
                $anchorIndex = min(count($this->map->spawnPoints) - 1, (int)floor($this->random() * count($this->map->spawnPoints)));
                [$baseX, $baseY, $baseZ] = $this->map->spawnPoints[$anchorIndex];
                $r = 2.5 + $this->random() * 3.5;
            } else {
                [$baseX, $baseY, $baseZ] = [$this->x, $this->y, $this->z];
                $r = ($type === 'brute' ? 8.0 : ($this->mode->hasFiniteEncounters()?4.5:6.0)) + $this->random() * ($this->mode->hasFiniteEncounters()?6.0:4.0);
            }
            $x = $baseX + sin($a) * $r;
            $z = $baseZ + cos($a) * $r;
            $distance = hypot($x - $this->x, $z - $this->z);
            if ($distance < ($type === 'brute' ? 8.0 : 4.5) || $distance > 34.0) continue;
            if ($this->objective?->state === TreasureDirector::FINAL_WAVE && !$this->insideFinalArena($x, $z)) continue;
            $ground = $this->supportHeight($x, $z, 0.42, $baseY + 2.0);
            if ($ground === null) continue;
            $y = $ground + 0.55;
            if(!$this->spawnInEncounter($x,$y,$z))continue;
            if (abs($y - $this->y) > 1.5) continue;
            if ($this->collides($x, $y, $z, 0.42, 0.55)) continue;
            [$occupied, $nearby] = $this->spawnCrowding($x, $y, $z, $radius, $type);
            if ($occupied) continue;
            $angle = abs(self::angleDelta(atan2($x - $this->x, $z - $this->z), $this->yaw));
            $visible = $this->lineClear([$this->x, $this->y + 0.35, $this->z], [$x, $y + 0.2, $z]);
            if ($this->objective !== null && !$visible) continue;
            $recentPenalty = 0.0;
            foreach ($this->recentSpawnPoints as $age => $used) {
                if ($used === $anchorIndex) $recentPenalty += 3.0 - $age * 0.8;
            }
            $fallbackCandidates[] = ['x' => $x, 'y' => $y, 'z' => $z,
                'index' => $anchorIndex,
                'score' => abs($distance - ($type === 'brute' ? 14.0 : 8.0)) * 0.28 + ($angle < 1.05 ? (1.05 - $angle) * 5.5 : 0.0)
                    + ($visible ? 2.4 : -1.3) + $nearby * 1.5 + $recentPenalty + $this->random() * 0.55];
        }
        if ($fallbackCandidates !== []) {
            usort($fallbackCandidates, static fn (array $a, array $b): int => $a['score'] <=> $b['score']);
            $point = $fallbackCandidates[0];
            if ($point['index'] !== null) $this->rememberSpawnPoint($point['index']);
            return $this->spawnEnemyAt($point['x'], $point['y'], $point['z'], $type);
        }
        return false;
    }

    private function chooseEnemyType(): string
    {
        $weights = self::enemyWeightsForWave($this->mode->compositionWave($this->wave));
        $roll = $this->random();
        $cursor = 0.0;
        foreach ($weights as $type => $weight) {
            $cursor += $weight;
            if ($roll <= $cursor) return $type;
        }
        return 'brute';
    }

    /**
     * Four durability classes, progressively weighted toward tougher enemies.
     * Weapon DPS still grows faster than enemy health through DifficultyDirector;
     * this changes composition, not the underlying elasticity rule.
     *
     * @return array{runner:float,guardian:float,enforcer:float,brute:float}
     */
    public static function enemyWeightsForWave(int $wave): array
    {
        return match (true) {
            $wave <= 1 => ['runner' => 0.44, 'guardian' => 0.44, 'enforcer' => 0.04, 'brute' => 0.08],
            $wave <= 3 => ['runner' => 0.40, 'guardian' => 0.38, 'enforcer' => 0.12, 'brute' => 0.10],
            $wave <= 6 => ['runner' => 0.35, 'guardian' => 0.33, 'enforcer' => 0.18, 'brute' => 0.14],
            $wave <= 10 => ['runner' => 0.32, 'guardian' => 0.33, 'enforcer' => 0.23, 'brute' => 0.12],
            default => ['runner' => 0.22, 'guardian' => 0.30, 'enforcer' => 0.28, 'brute' => 0.20],
        };
    }

    private function enemyRadius(string $type): float
    {
        return DifficultyDirector::enemyBaseProfile($type)['radius'];
    }

    private function spawnCrowding(float $x, float $y, float $z, float $radius, string $type): array
    {
        $nearby = 0;
        foreach ($this->enemies as $enemy) {
            if (abs($y - $enemy['y']) > 1.4) continue;
            $distance = hypot($x - $enemy['x'], $z - $enemy['z']);
            $spacing = ($type === 'brute' || $enemy['type'] === 'brute' ? 1.95 : 1.60)
                + $radius + $enemy['radius'];
            if ($distance < $spacing) return [true, $nearby];
            if ($distance < 5.0) $nearby++;
        }
        return [false, $nearby];
    }

    private function rememberSpawnPoint(int $index): void
    {
        array_unshift($this->recentSpawnPoints, $index);
        $this->recentSpawnPoints = array_slice($this->recentSpawnPoints, 0, 4);
    }

    private function insideFinalArena(float $x, float $z): bool
    {
        if ($this->objective === null) return true;
        $b = $this->objective->finalRoomBounds;
        return $x >= $b['min'][0] + 0.6 && $x <= $b['max'][0] - 0.6
            && $z >= $b['min'][2] + 0.6 && $z <= $b['max'][2] - 0.6;
    }

    private function spawnInEncounter(float $x,float $y,float $z): bool
    {
        if($this->encounters===null || $this->objective?->state!==TreasureDirector::EXPLORING)return true;
        return $this->encounters->inZone($x,$y,$z,$this->wave);
    }

    private function spawnEnemyAt(float $x, float $y, float $z, string $type): bool
    {
        if ($this->objective?->state === TreasureDirector::FINAL_WAVE && !$this->insideFinalArena($x, $z)) return false;
        $profile = DifficultyDirector::enemyBaseProfile($type);
        $hp = DifficultyDirector::enemyHealthFor($type, $this->mode->enemyHealthScale($this->wave));
        $speed = $profile['speed'] * DifficultyDirector::speedScaleFor($this->wave, $this->level);
        $damage = (int)round($profile['damage'] * $this->mode->damageScale($this->wave));
        $radius = $profile['radius'];
        if ($this->objective !== null && !$this->lineClear([$this->x, $this->y + 0.35, $this->z], [$x, $y + 0.15, $z])) return false;
        $this->spawned++;
        $id = $this->nextId++;
        $yaw = atan2($this->x - $x, $this->z - $z);
        $this->enemies[$id] = ['id' => $id, 'type' => $type, 'x' => $x, 'y' => $y, 'z' => $z,
            'health' => $hp, 'maxHealth' => $hp, 'speed' => $speed, 'damage' => $damage,
            'radius' => $radius, 'flash' => 0.0, 'cooldown' => 0.6, 'age' => 0.0,
            'pursuing'=>$this->objective !== null || $this->lineClear([$x,$y+.28,$z],[$this->x,$this->y+.35,$this->z]),
            'spawnDelay'=>.75,'stun'=>0.0,
            'yaw' => $yaw, 'attackTime' => 0.0, 'moving' => false, 'animState' => 'idle', 'windup'=>0.0, 'aimX'=>$this->x, 'aimZ'=>$this->z];
        return true;
    }

    private function stepEnemies(float $dt): void
    {
        $this->navigationBudget = 2;
        $this->navigation->pump($this->enemies);
        $grid = [];
        foreach ($this->enemies as $id => $entry) {
            $cell = (int)floor($entry['x'] / 1.5) . ':' . (int)floor($entry['z'] / 1.5);
            $grid[$cell][$id] = true;
        }
        foreach ($this->enemies as $id => &$enemy) {
            $enemy['flash'] = max(0.0, $enemy['flash'] - $dt);
            $enemy['cooldown'] -= $dt;
            $enemy['attackTime'] = max(0.0, $enemy['attackTime'] - $dt);
            $enemy['age'] += $dt;
            $enemy['moving'] = false;
            $enemy['stun']=max(0,($enemy['stun']??0)-$dt);$enemy['spawnDelay']=max(0,($enemy['spawnDelay']??0)-$dt);
            if($enemy['stun']>0 || $enemy['spawnDelay']>0){$enemy['windup']=0.0;continue;}
            $dx = $this->x - $enemy['x'];
            $dz = $this->z - $enemy['z'];
            $d = max(0.001, hypot($dx, $dz));
            $angle = atan2($dx, $dz);
            $enemy['yaw'] = $angle;
            $enemy['sightAt'] ??= 0.0;
            if ($this->time >= $enemy['sightAt']) {
                $enemy['visible'] = $this->lineClear([$enemy['x'],$enemy['y']+.28,$enemy['z']],[$this->x,$this->y+.35,$this->z]);
                $enemy['sightAt'] = $this->time + .25 + ($id % 5) * .015;
                if ($enemy['visible']) $enemy['pursuing'] = true;
            }
            if (!($enemy['pursuing'] ?? false)) continue;
            $ranged = $enemy['type'] === 'enforcer';
            $heavy = $enemy['type'] === 'brute';
            $attackRange = $ranged ? ($d > 2.0 && $d < 17.0) : ($d < $enemy['radius'] + ($heavy ? 1.05 : .55) && abs($this->y-$enemy['y'])<1.1);
            $enemy['windup'] ??= 0.0;
            $enemy['aimX'] ??= $this->x; $enemy['aimZ'] ??= $this->z;
            if ($enemy['windup'] > 0) {
                $enemy['windup'] = max(0,$enemy['windup']-$dt);
                $enemy['attackTime'] = .2;
                if ($enemy['windup'] <= 0) {
                    // Locked aim gives movement a real defensive purpose. Check geometry at impact.
                    $hit = $ranged ? hypot($this->x-$enemy['aimX'],$this->z-$enemy['aimZ'])<.85 : $attackRange;
                    if ($hit && $this->invincible<=0 && $this->lineClear([$enemy['x'],$enemy['y']+.28,$enemy['z']],[$this->x,$this->y+.35,$this->z])) {
                        $this->health-=$enemy['damage'];$this->hurt=.28;$this->invincible=.25;$this->resetCombo();
                    }
                    $enemy['cooldown'] = $ranged ? 1.9 : 1.65;
                }
            } elseif ($attackRange && $enemy['cooldown']<=0 && $this->lineClear([$enemy['x'],$enemy['y']+.28,$enemy['z']],[$this->x,$this->y+.35,$this->z])) {
                if ($ranged || $heavy || $enemy['type'] === 'runner') {
                    $enemy['windup']=$ranged ? .65 : ($heavy ? 1.0 : .4);
                    $enemy['aimX']=$this->x; $enemy['aimZ']=$this->z;$enemy['attackTime']=.2;
                } else {
                    if($this->invincible<=0){$this->health-=$enemy['damage'];$this->hurt=.25;$this->invincible=.25;$this->resetCombo();}
                    $enemy['cooldown']=$enemy['type']==='runner' ? .7 : 1.0;$enemy['attackTime']=.32;
                }
            }
            if ($d >= .55 && $enemy['windup']<=0 && !($ranged && $d>5 && $d<13 && ($enemy['visible']??false))) {
                $waypoint = $this->pursuitWaypoint($enemy);
                $dx = $waypoint[0] - $enemy['x']; $dz = $waypoint[2] - $enemy['z'];
                if (hypot($dx,$dz)<.001) { $enemy['animState']='idle'; continue; }
                $seekDistance = max(.001,hypot($dx,$dz));
                $separateX = $separateZ = 0.0;
                $cx = (int)floor($enemy['x'] / 1.5); $cz = (int)floor($enemy['z'] / 1.5);
                for ($ix = $cx - 1; $ix <= $cx + 1; $ix++) for ($iz = $cz - 1; $iz <= $cz + 1; $iz++) {
                    foreach ($grid[$ix . ':' . $iz] ?? [] as $otherId => $_) {
                        if ($otherId === $id) continue;
                        $this->neighborChecks++;
                        $other = $this->enemies[$otherId];
                        if (abs($enemy['y'] - $other['y']) > 1.1) continue;
                        $awayX = $enemy['x'] - $other['x']; $awayZ = $enemy['z'] - $other['z'];
                        $gap = hypot($awayX, $awayZ);
                        if ($gap < 0.001 || $gap >= 1.6) continue;
                        $weight = (1.6 - $gap) / (1.6 * $gap);
                        $separateX += $awayX * $weight;
                        $separateZ += $awayZ * $weight;
                    }
                }
                $seekAngle = atan2($dx / $seekDistance + $separateX * 0.12, $dz / $seekDistance + $separateZ * 0.12);
                $side = $id % 2 === 0 ? 1 : -1;
                // Backing out is essential when two bodies meet at a door jamb.
                foreach ([0.0, $side * 0.65, -$side * 0.65, $side * 1.3, -$side * 1.3, $side * 2.1, -$side * 2.1, M_PI] as $offset) {
                    $speed = $enemy['speed'] * $dt;
                    $nx = $enemy['x'] + sin($seekAngle + $offset) * $speed;
                    $nz = $enemy['z'] + cos($seekAngle + $offset) * $speed;
                    $ground = $this->supportHeight($nx, $nz, $enemy['radius'], $enemy['y'] - 0.55 + 0.58);
                    if ($ground === null || $ground < $enemy['y'] - .55 - .8) continue;
                    $ny = $ground + 0.55;
                    if ($this->collides($nx, $ny, $nz, $enemy['radius'], 0.55)) continue;
                    $blocked = false;
                    $gx = (int)floor($nx / 1.5); $gz = (int)floor($nz / 1.5);
                    for ($ix = $gx - 1; $ix <= $gx + 1 && !$blocked; $ix++) for ($iz = $gz - 1; $iz <= $gz + 1 && !$blocked; $iz++) {
                        foreach ($grid[$ix . ':' . $iz] ?? [] as $otherId => $_) {
                            if ($otherId === $id) continue;
                            $this->neighborChecks++;
                            $other = $this->enemies[$otherId];
                            $spacing = $enemy['radius'] + $other['radius'] + 0.04;
                            if (abs($ny - $other['y']) < 1.1) {
                                $nextDistance = hypot($nx - $other['x'], $nz - $other['z']);
                                $oldDistance = hypot($enemy['x'] - $other['x'], $enemy['z'] - $other['z']);
                                if ($nextDistance < $spacing && $nextDistance <= $oldDistance) { $blocked = true; break; }
                            }
                        }
                    }
                    if (!$blocked) {
                        $oldCell = (int)floor($enemy['x'] / 1.5) . ':' . (int)floor($enemy['z'] / 1.5);
                        $newCell = $gx . ':' . $gz;
                        if ($oldCell !== $newCell) { unset($grid[$oldCell][$id]); $grid[$newCell][$id] = true; }
                        $enemy['x'] = $nx; $enemy['y'] = $ny; $enemy['z'] = $nz;
                        $enemy['moving'] = true;
                        break;
                    }
                }
            }
            $enemy['animState'] = $enemy['flash'] > 0 ? 'pain' : ($enemy['attackTime'] > 0 ? 'attack' : ($enemy['moving'] ? 'walk' : 'idle'));
        }
        unset($enemy);
    }

    private function pursuitWaypoint(array &$enemy): array
    {
        $from=[$enemy['x'],$enemy['y']-.55,$enemy['z']];
        $goal=[$this->x,$this->y-self::PLAYER_HALF_HEIGHT,$this->z];
        $enemy['path'] ??= [];
        $ready=$this->navigation->poll($enemy['id']);
        if($ready!==null){$enemy['path']=$ready;$enemy['pathSerial']=($enemy['pathSerial']??0)+1;}
        $enemy['repathAt'] ??= 0.0;
        $enemy['progressAt'] ??= $this->time;
        $enemy['progressPosition'] ??= $from;
        if($this->time-$enemy['progressAt']>=2.0){
            $p=$enemy['progressPosition'];
            $routeDistance=0.0;$previous=$from;
            foreach($enemy['path'] as $point){$routeDistance+=hypot($point[0]-$previous[0],$point[2]-$previous[2]);$previous=$point;}
            $samePath=($enemy['progressSerial']??-1)===($enemy['pathSerial']??0);
            $noRouteProgress=$enemy['path']!==[]&&$samePath&&$routeDistance>=($enemy['progressDistance']??INF)-.2;
            // Check both displacement and remaining route length, including large steering loops.
            if(hypot($from[0]-$p[0],$from[2]-$p[2])<.45 || $noRouteProgress){
                $enemy['path']=[];$enemy['repathAt']=0.0;$this->navigationRecoveries++;
            }
            $enemy['progressAt']=$this->time;$enemy['progressPosition']=$from;
            $enemy['progressDistance']=$routeDistance;$enemy['progressSerial']=$enemy['pathSerial']??0;
        }
        while($enemy['path']!==[]){$p=$enemy['path'][0];
            if(hypot($from[0]-$p[0],$from[2]-$p[2])>.3||abs($from[1]-$p[1])>.6)break;
            array_shift($enemy['path']);
        }
        $old=$enemy['pathGoal']??$from;
        $changed=hypot($goal[0]-$old[0],$goal[2]-$old[2])>1.5||abs($goal[1]-$old[1])>.6;
        if($this->time>=$enemy['repathAt'] && ($changed||$enemy['path']===[]) && $this->navigationBudget>0){
            $this->navigationBudget--;
            $planned=$this->navigation->request($enemy['id'],$from,$goal,$enemy['radius']+.025);
            if($planned!==null){$enemy['path']=$planned;$enemy['pathSerial']=($enemy['pathSerial']??0)+1;}
            $enemy['pathGoal']=$goal;$enemy['repathAt']=$this->time+.65+($enemy['id']%7)*.07;
        }
        return $enemy['path'][0]??$from;
    }

    /** Shared geometry routing for input-policy bots, without moving or aiming for them. */
    public function navigationTarget(array $goal): array
    {
        $from=[$this->x,$this->y-self::PLAYER_HALF_HEIGHT,$this->z];
        $to=[$goal['x'],$goal['y']??$this->y,$goal['z']];
        $to[1]-=self::PLAYER_HALF_HEIGHT;
        $old=$this->botGoal??$from;
        // Slow reaction profiles can step over a grid waypoint between decisions.
        // Consume points passed by the actual movement segment, avoiding endless oscillation.
        $last=$this->botPreviousPosition??$from;
        $dx=$from[0]-$last[0];$dz=$from[2]-$last[2];$length2=$dx*$dx+$dz*$dz;
        while($this->botPath!==[]){
            $p=$this->botPath[0];
            $t=$length2>.00001?max(0,min(1,(($p[0]-$last[0])*$dx+($p[2]-$last[2])*$dz)/$length2)):1;
            if(hypot($p[0]-$last[0]-$t*$dx,$p[2]-$last[2]-$t*$dz)>.3)break;
            array_shift($this->botPath);
        }
        $this->botPreviousPosition=$from;
        if($this->time >= $this->botRepathAt && ($this->botPath===[]||hypot($to[0]-$old[0],$to[2]-$old[2])>1.5)){
            $this->botPath=$this->navigation->path($from,$to,self::PLAYER_RADIUS+.025,self::PLAYER_HALF_HEIGHT);
            $this->botGoal=$to;$this->botRepathAt=$this->time+.5;
        }
        $p=$this->botPath[0]??$from;
        return ['x'=>$p[0],'y'=>$p[1]+self::PLAYER_HALF_HEIGHT,'z'=>$p[2]];
    }

    private function triggerPrimaryRhythmStep(int $step): void
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
        $cone = 1.05; // normal weapons deliberately keep strong auto-aim
        $targets = $this->findTargets(19.0, $cone, $targetCount);
        $baseDamage = match ($stage) {
            0, 1 => 38,
            2, 3 => 14,
            4 => 10,
            default => 13,
        };
        $damage = (int)round($baseDamage * (1 + ($this->damageRank + DifficultyDirector::virtualDamageRankForStage($stage)) * 0.30));
        if ($targets === []) {
            if ($this->fireAtBoss($damage * max(1, $guns), $stage)) {
                $this->shots += $guns;
                $this->hits += $guns;
                $this->muzzle = 0.11;
                if (count($this->primaryAudioEvents) < 32) $this->primaryAudioEvents[] = $stage;
            }
            return;
        }
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
        $this->primaryShotSerial++;$this->primaryShotSide=1-$this->primaryGunPhase;
    }
    public function selectedSecondary(): ?string
    {
        if($this->equippedSecondary!==null)return $this->equippedSecondary;
        foreach(['flame'=>$this->flameRank,'rockets'=>$this->rocketRank,'sniper'=>$this->sniperRank,'artillery'=>$this->artilleryRank,'emp'=>$this->empRank,'pulse'=>$this->pulseUnlocked?1:0]as$id=>$rank)if($rank>0)return $id;
        return null;
    }
    public function equipSecondary(string $id): void
    {
        if(!in_array($id,['flame','rockets','sniper','artillery','emp','pulse'],true))throw new \InvalidArgumentException('Unknown power');
        $same=$this->selectedSecondary()===$id;
        foreach(['flame'=>'flameRank','rockets'=>'rocketRank','sniper'=>'sniperRank','artillery'=>'artilleryRank','emp'=>'empRank','pulse'=>'pulseRank']as$key=>$field)if($key!==$id)$this->$field=0;
        if($id!=='pulse')$this->pulseUnlocked=false;
        $this->equippedSecondary=$id;
        if(!$same){$this->flameActive=0;$this->flameSegments=[];$this->projectiles=[];$this->explosions=[];$this->pulse=0;$this->empFx=0;$this->secondaryScheduler=new SecondaryScheduler();$this->secondaryLastFired=[];}
    }
    private function empTargetAvailable(): bool
    {
        foreach($this->enemies as$e)if(hypot($e['x']-$this->x,$e['z']-$this->z)<=3.0 && abs($e['y']-$this->y)<1.5)return true;
        return $this->boss?->active && !$this->boss->defeated && hypot($this->bossPosition['x']-$this->x,$this->bossPosition['z']-$this->z)<=3.0;
    }
    private function fireEmp(): bool
    {
        if(!$this->empTargetAvailable())return false;
        foreach($this->enemies as&$e)if(hypot($e['x']-$this->x,$e['z']-$this->z)<=3.0 && abs($e['y']-$this->y)<1.5){$e['stun']=2.0;$e['windup']=0.0;}unset($e);
        if($this->boss?->active && hypot($this->bossPosition['x']-$this->x,$this->bossPosition['z']-$this->z)<=3.0)$this->bossStun=2.0;
        $this->empFx=.65;$this->empClock=$this->secondaryCooldown('emp');return true;
    }
    public function secondaryCooldown(string $id): float
    {
        return match($id){
            'emp'=>max(9.0,12.0-($this->empRank-1)),
            'sniper'=>max(3.0,6.0-$this->sniperRank*.6-$this->hasteRank*.08),
            'artillery'=>max(4.0,8.5-$this->artilleryRank*.7-$this->hasteRank*.1),
            'pulse'=>max(1.0,2.4-$this->pulseRank*.15-$this->hasteRank*.04),
            'rockets'=>max(2.0,4.5-($this->rocketRank-1)*.55-$this->hasteRank*.12),
            default=>max(5.4,8.0-($this->flameRank-1)*.7-$this->hasteRank*.15),
        };
    }

    public function secondaryInventory(): array
    {
        $out=[];
        foreach(['flame'=>[$this->flameRank,$this->flameCooldownClock], 'rockets'=>[$this->rocketRank,$this->rocketClock],
            'sniper'=>[$this->sniperRank,$this->sniperClock], 'artillery'=>[$this->artilleryRank,$this->artilleryClock],
            'emp'=>[$this->empRank,$this->empClock],
            'pulse'=>[$this->pulseUnlocked?max(1,$this->pulseRank):0,$this->pulseClock]] as$id=>[$rank,$remaining]){
            if($rank<=0 || $id!==$this->selectedSecondary())continue;
            $active=$id==='flame' && $this->flameActive>0;
            $out[]=['id'=>$id,'rank'=>$rank,'remaining'=>max(0,$remaining),'cooldown'=>$this->secondaryCooldown($id),
                'ready'=>$remaining<=0 && !$active,'active'=>$active,'flash'=>isset($this->secondaryLastFired[$id]) && $this->time-$this->secondaryLastFired[$id]<0.18];
        }
        return $out;
    }

    public function drainSecondaryEvents(): array
    {
        $events=$this->secondaryEvents;$this->secondaryEvents=[];return $events;
    }

    private function bossTargetAvailable(float $range,float $cone): bool
    {
        if($this->boss===null || !$this->boss->active || $this->boss->defeated)return false;
        $targets=$this->boss->vulnerable?[$this->bossPosition]:$this->boss->orbs;
        foreach($targets as$t){
            if($t===null || (isset($t['alive'])&&!$t['alive']))continue;
            if(hypot($t['x']-$this->x,$t['z']-$this->z)>$range)continue;
            if(abs(self::angleDelta(atan2($t['x']-$this->x,$t['z']-$this->z),$this->yaw))>$cone)continue;
            if($this->lineClear([$this->x,$this->y+.35,$this->z],[$t['x'],$t['y'],$t['z']]))return true;
        }
        return false;
    }

    private function stepSecondaries(float $dt): void
    {
        $this->empClock-=$dt;
        if (!$this->pulseUnlocked && $this->empRank<=0 && $this->flameRank<=0 && $this->rocketRank<=0 && $this->sniperRank<=0 && $this->artilleryRank<=0) return;
        if($this->sniperRank>0)$this->sniperClock-=$dt;
        if($this->artilleryRank>0)$this->artilleryClock-=$dt;
        if($this->pulseUnlocked)$this->pulseClock-=$dt;
        if($this->rocketRank>0)$this->rocketClock-=$dt;
        if($this->flameRank>0){
            if($this->flameActive<=0)$this->flameCooldownClock-=$dt;
            $this->stepFlamethrower($dt);
        }
        // Context changes only the insertion order; previously queued weapons keep their turn.
        $urgent=$this->findTarget(2.5,M_PI)!==null;
        $order=$urgent?['emp','pulse','flame','rockets','sniper','artillery']:['emp','rockets','sniper','artillery','flame','pulse'];
        $ready=[];$eligible=[];
        foreach($order as$id){
            if($id!==$this->selectedSecondary())continue;
            [$owned,$clock,$range,$cone]=match($id){
                'emp'=>[$this->empRank>0,$this->empClock,3.0,M_PI],
                'sniper'=>[$this->sniperRank>0,$this->sniperClock,40.0,1.1],
                'artillery'=>[$this->artilleryRank>0,$this->artilleryClock,26.0,1.2],
                'pulse'=>[$this->pulseUnlocked,$this->pulseClock,3.6+$this->pulseRank*.35,M_PI],
                'flame'=>[$this->flameRank>0 && $this->flameActive<=0,$this->flameCooldownClock,5.2+max(0,$this->flameRank-1)*.7,.48+max(0,$this->flameRank-1)*.1],
                default=>[$this->rocketRank>0,$this->rocketClock,22.0,1.15],
            };
            if(!$owned || $clock>0)continue;
            $ready[]=$id;
            if(($id==='emp' && $this->empTargetAvailable()) || ($id!=='emp' && ($this->findTarget($range,$cone)!==null || $this->bossTargetAvailable($range,$cone))))$eligible[]=$id;
        }
        $spacing=30.0/(RhythmClock::BPM_BASE+max(0,$this->level-1)*RhythmClock::BPM_PER_LEVEL);
        $id=$this->secondaryScheduler->step($dt,$ready,$eligible,$spacing);
        if($id===null)return;
        $fired=match($id){'emp'=>$this->fireEmp(),'pulse'=>$this->firePulse(),'rockets'=>$this->fireMiniRockets(),'sniper'=>$this->fireSniper(),'artillery'=>$this->fireExplosive('artillery'),default=>(function(){ $this->startFlamethrower();return true; })()};
        if($fired){
            $this->secondaryLastFired[$id]=$this->time;
            if(count($this->secondaryEvents)>=64)array_shift($this->secondaryEvents);
            $this->secondaryEvents[]=['id'=>$id,'time'=>$this->time,'spacing'=>$spacing];
        }
    }

    private function stepFlamethrower(float $dt): void
    {
        if ($this->flameActive > 0.0) {
            $this->flameActive = max(0.0, $this->flameActive - $dt);
            $this->flameTickClock -= $dt;
            if ($this->flameTickClock <= 0.0) {
                $this->fireFlameTick();
                $this->flameTickClock += 0.12;
            }
            return;
        }
    }

    private function startFlamethrower(): void
    {
        $this->flameActive = 2.4 + ($this->flameRank - 1) * 0.45;
        $this->flameCooldownClock = max(5.4, 8.0 - ($this->flameRank - 1) * 0.7 - $this->hasteRank * 0.15);
        $this->flameTickClock = 0.0;
        $this->specialFlash = max($this->specialFlash, 0.18);
    }

    private function fireFlameTick(): void
    {
        $range = 5.2 + ($this->flameRank - 1) * 0.7;
        $cone = 0.48 + ($this->flameRank - 1) * 0.10;
        $limit = 6 + ($this->flameRank - 1) * 2;
        $targets = $this->findTargets($range, $cone, $limit);
        $baseDamage = 9 + ($this->flameRank - 1) * 3;
        foreach ($targets as $index => $id) {
            if (!isset($this->enemies[$id])) continue;
            $enemy = $this->enemies[$id];
            if ($index < 3) $this->addVisualTrace($enemy, 0.10, 'flame', ($index - 1) * 0.08);
            $this->damage($id, (int)round($baseDamage * (1 + $this->damageRank * 0.18)));
            $this->hits++;
        }
        if ($targets === [] && $this->fireAtBoss((int)round($baseDamage*(1+$this->damageRank*.18)),5,$range,$cone)) $this->muzzle=max($this->muzzle,0.035);
        if ($targets !== []) {
            $this->shots++;
            $this->muzzle = max($this->muzzle, 0.035);
        }
    }

    private function fireMiniRockets(): bool
    {
        return $this->fireExplosive('rockets');
    }

    private function fireSniper(): bool
    {
        $targets=$this->findTargets(40,1.1,200);
        usort($targets,function($a,$b){
            $score=function($id){$e=$this->enemies[$id];return ($e['type']==='enforcer'?80:($e['type']==='brute'?60:0))+hypot($e['x']-$this->x,$e['z']-$this->z);};
            return $score($b)<=>$score($a);
        });
        $damage=(int)round((180+$this->sniperRank*55)*(1+$this->damageRank*.2));
        if($targets===[]){if(!$this->fireAtBoss($damage,5,40,1.1,'sniper'))return false;}
        else {$id=$targets[0];$this->addVisualTrace($this->enemies[$id],.15,'sniper');$this->damage($id,$damage);}
        $this->sniperClock=$this->secondaryCooldown('sniper');$this->shots++;$this->hits++;return true;
    }

    private function fireExplosive(string $kind): bool
    {
        if(count($this->projectiles)>=16)return false;
        $range=$kind==='artillery'?26.0:22.0;
        $ids=$this->findTargets($range,1.2,1);$target=null;
        if($ids!==[])$target=$this->enemies[$ids[0]];
        elseif($this->bossTargetAvailable($range,1.2)){
            if($this->boss->vulnerable)$target=$this->bossPosition;
            else foreach($this->boss->orbs as$orb)if($orb['alive'] && $this->lineClear([$this->x,$this->y+.35,$this->z],[$orb['x'],$orb['y'],$orb['z']])){$target=$orb;break;}
        }
        if($target===null)return false;
        $rank=$kind==='artillery'?$this->artilleryRank:$this->rocketRank;
        $this->projectiles[]=['x'=>$this->x,'y'=>$this->y+.35,'z'=>$this->z,
            'targetId'=>$ids[0]??null,'tx'=>$target['x'],'ty'=>$target['y']+.1,'tz'=>$target['z'],'kind'=>$kind,'age'=>0.0,
            'damage'=>(int)round((($kind==='artillery'?150:95)+$rank*28)*(1+$this->damageRank*.2)),
            'radius'=>$kind==='artillery'?3.6:2.6,'speed'=>$kind==='artillery'?12.0:18.0];
        if($kind==='artillery')$this->artilleryClock=$this->secondaryCooldown($kind);
        else $this->rocketClock=$this->secondaryCooldown($kind);
        $this->shots++;$this->specialFlash=.12;return true;
    }

    private function stepProjectiles(float $dt): void
    {
        foreach($this->explosions as$i=>&$e){$e['life']-=$dt;if($e['life']<=0)unset($this->explosions[$i]);}unset($e);
        foreach($this->projectiles as$i=>&$p){
            if(($p['kind']??'')==='rockets' && isset($this->enemies[$p['targetId']??-1])){$t=$this->enemies[$p['targetId']];$p['tx']=$t['x'];$p['ty']=$t['y']+.1;$p['tz']=$t['z'];}
            $p['age']+=$dt;$a=[$p['x'],$p['y'],$p['z']];$b=[$p['tx'],$p['ty'],$p['tz']];
            if(($p['kind']??'')==='rockets'){ $desired=atan2($b[0]-$a[0],$b[2]-$a[2]);$heading=$p['heading']??$desired;$delta=self::angleDelta($desired,$heading);$heading+=max(-3.5*$dt,min(3.5*$dt,$delta));$p['heading']=$heading;$distance=hypot($b[0]-$a[0],$b[2]-$a[2]);$b[0]=$a[0]+sin($heading)*$distance;$b[2]=$a[2]+cos($heading)*$distance;}
            $d=sqrt(($b[0]-$a[0])**2+($b[1]-$a[1])**2+($b[2]-$a[2])**2);$t=min(1,$p['speed']*$dt/max(.001,$d));
            $next=[$a[0]+($b[0]-$a[0])*$t,$a[1]+($b[1]-$a[1])*$t,$a[2]+($b[2]-$a[2])*$t];
            $blocked=!$this->lineClear($a,$next);
            if($blocked){ // Stop at the near side of geometry, never at the hidden target.
                $lo=0.0;$hi=1.0;for($j=0;$j<10;$j++){$m=($lo+$hi)/2;$q=[$a[0]+($next[0]-$a[0])*$m,$a[1]+($next[1]-$a[1])*$m,$a[2]+($next[2]-$a[2])*$m];if($this->lineClear($a,$q))$lo=$m;else $hi=$m;}
                $next=[$a[0]+($next[0]-$a[0])*$lo,$a[1]+($next[1]-$a[1])*$lo,$a[2]+($next[2]-$a[2])*$lo];
            }
            [$p['x'],$p['y'],$p['z']]=$next;
            if($blocked||$t>=1||$p['age']>4){$this->explode($next,$p['damage'],$p['radius']);unset($this->projectiles[$i]);}
        }unset($p);
    }

    private function explode(array $at,int $damage,float $radius): void
    {
        if(count($this->explosions)>=8)array_shift($this->explosions);
        $this->explosions[]=['x'=>$at[0],'y'=>$at[1],'z'=>$at[2],'radius'=>$radius,'life'=>.7,'totalLife'=>.7];
        foreach($this->enemies as$id=>$e){
            $d=sqrt(($e['x']-$at[0])**2+($e['y']+.15-$at[1])**2+($e['z']-$at[2])**2);
            if($d<=$radius && $this->lineClear($at,[$e['x'],$e['y']+.15,$e['z']])){$this->damage($id,(int)round($damage*(1-.5*$d/$radius)));$this->hits++;}
        }
        if($this->boss?->active && !$this->boss->defeated){
            $targets=$this->boss->vulnerable?[-1=>$this->bossPosition]:$this->boss->orbs;
            foreach($targets as$id=>$e)if($e!==null&&($id<0||$e['alive']) && hypot($e['x']-$at[0],$e['z']-$at[2])<$radius && $this->lineClear($at,[$e['x'],$e['y'],$e['z']])){
                if($id<0)$this->boss->damage((int)round($damage*1.5));else $this->boss->damageOrb($id,$damage);
            }
        }
    }

    private function updateFlameSegments(): void
    {
        $this->flameSegments=[];if($this->flameActive<=0)return;
        $range=5.2+max(0,$this->flameRank-1)*.7;
        foreach([-.13,0,.13]as$offset){$a=[$this->x,$this->y+.23,$this->z];
            for($d=.3;$d<=$range;$d+=.3){$b=[$this->x+sin($this->yaw+$offset)*$d,$this->y+.23,$this->z+cos($this->yaw+$offset)*$d];
                if(!$this->lineClear($a,$b))break;$this->flameSegments[]=['a'=>$a,'b'=>$b,'d'=>$d];$a=$b;
            }
        }
    }

    private function addVisualTrace(array $enemy, float $height, string $type, float $lateral = 0.0): void
    {
        if (count($this->traces) >= self::MAX_VISUAL_TRACES) array_shift($this->traces);
        $height=.15;
        if(!$this->lineClear([$this->x,$this->y+.35,$this->z],[$enemy['x'],$enemy['y']+$height,$enemy['z']]))return;
        $life = $type==='sniper' ? .22 : (in_array($type, ['flame', 'rocket'], true) ? .075 : .09);
        $this->traces[] = [
            'startX' => $this->x,
            'startY' => $this->y + 0.35,
            'startZ' => $this->z,
            'endX' => $enemy['x'], 'endY' => $enemy['y'] + $height, 'endZ' => $enemy['z'],
            'x' => $enemy['x'], 'y' => $enemy['y'] + $height, 'z' => $enemy['z'],
            'life' => $life, 'totalLife' => $life, 'type' => $type,
        ];
    }

    private function findTarget(float $range, float $cone): ?int
    {
        return $this->findTargets($range, $cone, 1)[0] ?? null;
    }

    /** @return list<int> */
    private function findTargets(float $range, float $cone, int $limit): array
    {
        $candidates = [];
        foreach ($this->enemies as $id => $enemy) {
            $d = hypot($enemy['x'] - $this->x, $enemy['z'] - $this->z);
            if ($d > $range) continue;
            $angle = abs(self::angleDelta(atan2($enemy['x'] - $this->x, $enemy['z'] - $this->z), $this->yaw));
            if ($angle > $cone) continue;
            $score = $angle * 7 + $d * 0.14;
            if ($this->lineClear([$this->x, $this->y + 0.35, $this->z], [$enemy['x'], $enemy['y'] + 0.15, $enemy['z']])) {
                $candidates[$id] = $score;
            }
        }
        asort($candidates);
        return array_slice(array_map('intval', array_keys($candidates)), 0, max(1, $limit));
    }

    private function firePulse(): bool
    {
        $targets = [];
        foreach ($this->enemies as $id => $enemy) {
            if (hypot($enemy['x'] - $this->x, $enemy['z'] - $this->z) <= 3.6 + $this->pulseRank * 0.35 &&
                $this->lineClear([$this->x, $this->y, $this->z], [$enemy['x'], $enemy['y'], $enemy['z']])) $targets[] = $id;
        }
        if ($targets === []) {
            if (!$this->fireAtBoss(30+$this->pulseRank*14,5,3.6+$this->pulseRank*.35,M_PI)) return false;
        }
        foreach ($targets as $id) $this->damage($id, 45 + $this->pulseRank * 20);
        $this->pulse = 0.25;
        $this->pulseClock = $this->secondaryCooldown('pulse');
        return true;
    }

    private function damage(int $id, int $amount): void
    {
        if (!isset($this->enemies[$id])) return;
        $this->enemies[$id]['health'] -= $amount;
        $this->enemies[$id]['flash'] = 0.12;
        $this->enemies[$id]['animState'] = 'pain';
        if ($this->enemies[$id]['health'] > 0) return;
        $enemy = $this->enemies[$id];
        unset($this->enemies[$id]);
        if (count($this->corpses) >= self::MAX_VISUAL_CORPSES) array_shift($this->corpses);
        $this->corpses[] = array_merge($enemy, ['age' => 0.0, 'animState' => 'death']);
        $this->kills++;
        if($this->mode->kind===GameMode::DAILY)$this->syncPrimaryStage();
        $this->combo++;
        $this->comboTime = 3.5;
        $this->multiplier = match (true) {
            $this->combo >= 24 => 10.0,
            $this->combo >= 16 => 5.0,
            $this->combo >= 11 => 3.0,
            $this->combo >= 7 => 2.0,
            $this->combo >= 4 => 1.5,
            $this->combo >= 2 => 1.2,
            default => 1.0,
        };
        $baseScore = match ($enemy['type']) {
            'runner' => 100,
            'brute' => 800,
            'enforcer' => 400,
            default => 250,
        };
        $this->score += (int)round(($baseScore + $this->wave * 25) * $this->multiplier);
        $kind = $this->kills % 14 === 0 ? 'health' : 'xp';
        $value = $kind === 'health' ? 14 : $this->xpCurve->reward($enemy['type']);
        if ($kind === 'xp') $this->droppedXp += $value;
        if (count($this->pickups) >= self::MAX_PICKUPS) {
            $key = array_key_first($this->pickups);
            if ($kind === 'xp' && $this->pickups[$key]['kind'] === 'xp') $this->pickups[$key]['value'] += $value;
            else array_shift($this->pickups);
        }
        if (count($this->pickups) < self::MAX_PICKUPS) {
            $this->pickups[] = ['x' => $enemy['x'], 'y' => $enemy['y'] - 0.3, 'z' => $enemy['z'], 'kind' => $kind, 'value' => $value, 'age' => 0.0];
        }
    }

    private function stepPickups(float $dt): void
    {
        foreach ($this->pickups as $id => &$pickup) {
            $pickup['age'] += $dt;
            $dx = $this->x - $pickup['x'];
            $dz = $this->z - $pickup['z'];
            $d = hypot($dx, $dz);
            if ($d < 3.2 + $this->magnetRank * 1.0 && $this->lineClear([$pickup['x'], $pickup['y'], $pickup['z']], [$this->x, $this->y, $this->z])) {
                if ($d < 0.6) {
                    if ($pickup['kind'] === 'xp') { $this->xp += $pickup['value']; $this->collectedXp += $pickup['value']; }
                    else $this->health = min($this->maxHealth, $this->health + $pickup['value']);
                    unset($this->pickups[$id]);
                } else {
                    $s = min($d, $dt * 6);
                    $pickup['x'] += $dx / $d * $s;
                    $pickup['z'] += $dz / $d * $s;
                }
            }
        }
        unset($pickup);
        $this->checkLevel();
    }

    private function checkLevel(): void
    {
        if ($this->xp < $this->nextXp || $this->state !== 'play') return;
        $this->xp -= $this->nextXp;
        $this->level++;
        $this->nextXp = $this->mode->xpThreshold($this->level);
        $this->syncPrimaryStage();

        $weaponPool = $this->weaponUpgradePool();
        $this->choices = [];
        if ($weaponPool !== []) {
            $index = (int)floor($this->random() * count($weaponPool));
            $this->choices[] = $weaponPool[$index];
            array_splice($weaponPool, $index, 1);
        }
        $pool = ['POWER', 'HASTE', 'MAGNET'];
        if($this->level%3===0)$pool[]='VITALITY';
        if (!$this->pulseUnlocked || $this->pulseRank < 3) $pool[] = 'PULSE';
        $pool = array_merge($pool, $weaponPool);
        while (count($this->choices) < 3 && $pool !== []) {
            $index = (int)floor($this->random() * count($pool));
            $this->choices[] = $pool[$index];
            array_splice($pool, $index, 1);
        }
        $this->state = 'upgrade';
    }

    /** @return list<string> */
    private function weaponUpgradePool(): array
    {
        $pool = [];
        if($this->level>=8 && $this->empRank<3)$pool[]='EMP';
        if ($this->level >= 3 && $this->flameRank < 3) $pool[] = 'FLAMETHROWER';
        if ($this->level >= 3 && $this->rocketRank < 4) $pool[] = 'MINI_ROCKETS';
        if($this->level>=5 && $this->sniperRank<3)$pool[]='SNIPER';
        if($this->level>=8 && $this->artilleryRank<3)$pool[]='ARTILLERY';
        return $pool;
    }
    private function resetCombo(): void
    {
        $this->combo = 0;
        $this->comboTime = 0.0;
        $this->multiplier = 1.0;
    }

    private function movePlayer(float $dx, float $dz): void
    {
        foreach ([[$dx, 0.0], [0.0, $dz]] as [$sx, $sz]) {
            $nx = $this->x + $sx;
            $nz = $this->z + $sz;
            $floor = null;
            if ($this->grounded) {
                $floor = $this->supportHeight($nx, $nz, self::PLAYER_RADIUS, $this->y - self::PLAYER_HALF_HEIGHT + 0.58);
                if ($floor === null || $floor < $this->y - self::PLAYER_HALF_HEIGHT - 0.8) continue;
            }
            if (!$this->collides($nx, $this->y, $nz, self::PLAYER_RADIUS, self::PLAYER_HALF_HEIGHT)) {
                $this->x = $nx; $this->z = $nz;
            } elseif ($this->grounded && $floor !== null) {
                $rise = $floor - ($this->y - self::PLAYER_HALF_HEIGHT);
                if ($rise > 0.0 && $rise <= 0.58 &&
                    !$this->collides($nx, $this->y + $rise + 0.002, $nz, self::PLAYER_RADIUS, self::PLAYER_HALF_HEIGHT)) {
                    $this->x = $nx; $this->z = $nz; $this->y += $rise + 0.002;
                }
            }
        }
    }

    private function vertical(float $dy): void
    {
        $this->grounded = false;
        if (!$this->collides($this->x, $this->y + $dy, $this->z, self::PLAYER_RADIUS, self::PLAYER_HALF_HEIGHT)) {
            $this->y += $dy;
            return;
        }
        $safe = 0.0; $blocked = 1.0;
        for ($i = 0; $i < 12; $i++) {
            $t = ($safe + $blocked) * 0.5;
            if ($this->collides($this->x, $this->y + $dy * $t, $this->z, self::PLAYER_RADIUS, self::PLAYER_HALF_HEIGHT)) $blocked = $t;
            else $safe = $t;
        }
        $this->y += $dy * $safe;
        $this->grounded = $dy < 0;
        $this->vy = 0.0;
    }

    public function lineClear(array $a, array $b): bool
    {
        $this->losTests++;
        foreach (array_merge($this->map->solids,$this->encounters?->blockers()??[]) as $solid) {
            $lo = 0.001; $hi = 0.999;
            for ($axis = 0; $axis < 3; $axis++) {
                $d = $b[$axis] - $a[$axis];
                if (abs($d) < 1e-8) {
                    if ($a[$axis] < $solid['min'][$axis] || $a[$axis] > $solid['max'][$axis]) { $lo = 2; break; }
                } else {
                    $t0 = ($solid['min'][$axis] - $a[$axis]) / $d;
                    $t1 = ($solid['max'][$axis] - $a[$axis]) / $d;
                    $lo = max($lo, min($t0, $t1)); $hi = min($hi, max($t0, $t1));
                }
                if ($lo > $hi) break;
            }
            if ($lo <= $hi) return false;
        }
        return true;
    }

    public function collides(float $x, float $y, float $z, float $radius, float $halfHeight): bool
    {
        $this->collisionTests++;
        return $this->navigation->collides($x,$y,$z,$radius,$halfHeight);
    }

    private function supportHeight(float $x, float $z, float $radius, float $ceiling): ?float
    {
        return $this->navigation->support($x,$z,$radius,$ceiling);
    }

    private function random(): float
    {
        $this->rng = ($this->rng * 25173 + 13849) % 65521;
        return $this->rng / 65521;
    }

    private static function angleDelta(float $a, float $b): float
    {
        $d = fmod($a - $b + M_PI, 2 * M_PI);
        if ($d < 0) $d += 2 * M_PI;
        return $d - M_PI;
    }
}

