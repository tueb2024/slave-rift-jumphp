<?php

namespace App\Game;

/**
 * Deterministic boss state machine, independent from rendering.
 *
 * The first implementation supports the intended wall boss: destroy every orb,
 * exploit a short vulnerability window, then repeat until the boss is dead.
 * A dynamic boss can use the same health/phase contract later.
 */
final class BossController
{
    public const WALL = 'wall';
    public const DYNAMIC = 'dynamic';

    public int $health;
    public int $phase = 1;
    public bool $active = false;
    public bool $defeated = false;
    public bool $vulnerable = false;
    public float $vulnerableTimer = 0.0;
    public float $time = 0.0;
    public float $ultimateCooldown = 0.0;

    /** @var list<array{x:float,y:float,z:float,hp:int,maxHp:int,alive:bool}> */
    public array $orbs = [];

    /** @var list<array{x:float,y:float,z:float}> */
    private array $orbPositions = [];
    private int $orbHealth = 0;

    public function __construct(
        public readonly string $kind,
        public readonly int $maxHealth,
        public readonly float $vulnerabilityDuration = 4.0,
    ) {
        if (!in_array($kind, [self::WALL, self::DYNAMIC], true)) {
            throw new \InvalidArgumentException('Unknown boss kind: ' . $kind);
        }
        if ($maxHealth <= 0) throw new \InvalidArgumentException('Boss health must be positive.');
        $this->health = $maxHealth;
    }

    /** @param list<array{x:float,y:float,z:float}> $positions */
    public function armOrbs(array $positions, int $health = 60): void
    {
        if ($health <= 0) throw new \InvalidArgumentException('Orb health must be positive.');
        $this->orbPositions = array_values($positions);
        $this->orbHealth = $health;
        $this->resetOrbs();
    }

    public function activate(): void
    {
        if ($this->defeated) return;
        $this->active = true;
        if ($this->kind === self::WALL && $this->orbs !== []) $this->vulnerable = false;
    }

    public function update(float $dt): void
    {
        if (!$this->active || $this->defeated) return;
        $dt = max(0.0, min(0.25, $dt));
        $this->time += $dt;
        $this->ultimateCooldown = max(0.0, $this->ultimateCooldown - $dt);

        if ($this->vulnerable) {
            $this->vulnerableTimer = max(0.0, $this->vulnerableTimer - $dt);
            if ($this->vulnerableTimer <= 0.0 && !$this->defeated) {
                $this->vulnerable = false;
                if ($this->kind === self::WALL && $this->orbPositions !== []) $this->resetOrbs();
            }
        }

        $ratio = $this->health / $this->maxHealth;
        $this->phase = $ratio <= 0.25 ? 3 : ($ratio <= 0.55 ? 2 : 1);
    }

    public function damage(int $amount): int
    {
        if (!$this->active || $this->defeated || $amount <= 0) return 0;
        if ($this->kind === self::WALL && !$this->vulnerable) return 0;

        $before = $this->health;
        $this->health = max(0, $this->health - $amount);
        if ($this->health === 0) {
            $this->defeated = true;
            $this->vulnerable = false;
            $this->vulnerableTimer = 0.0;
        }
        return $before - $this->health;
    }

    public function damageOrb(int $index, int $amount): int
    {
        if (!$this->active || $this->defeated || $amount <= 0 || !isset($this->orbs[$index]) || !$this->orbs[$index]['alive']) return 0;
        $before = $this->orbs[$index]['hp'];
        $this->orbs[$index]['hp'] = max(0, $before - $amount);
        if ($this->orbs[$index]['hp'] === 0) $this->orbs[$index]['alive'] = false;

        if ($this->kind === self::WALL && !$this->hasLiveOrb()) {
            $this->vulnerable = true;
            $this->vulnerableTimer = $this->vulnerabilityDuration;
        }
        return $before - $this->orbs[$index]['hp'];
    }

    public function hasLiveOrb(): bool
    {
        foreach ($this->orbs as $orb) if ($orb['alive']) return true;
        return false;
    }

    public function canUseUltimate(): bool
    {
        return $this->kind === self::DYNAMIC && $this->active && !$this->defeated && $this->ultimateCooldown <= 0.0;
    }

    public function consumeUltimate(float $cooldown): bool
    {
        if (!$this->canUseUltimate()) return false;
        $this->ultimateCooldown = max(0.1, $cooldown);
        return true;
    }

    private function resetOrbs(): void
    {
        $this->orbs = [];
        foreach ($this->orbPositions as $position) {
            $this->orbs[] = [
                'x' => (float)$position['x'], 'y' => (float)$position['y'], 'z' => (float)$position['z'],
                'hp' => $this->orbHealth, 'maxHp' => $this->orbHealth, 'alive' => true,
            ];
        }
    }
}
