<?php

namespace App\Game;

/**
 * Pure objective state machine for the procedural A→B loop.
 *
 * It deliberately does not spawn enemies or render anything: PlayScene/Run
 * consume its state transitions, which keeps objective progression testable.
 */
final class TreasureDirector
{
    public const EXPLORING = 'exploring';
    public const FINAL_WAVE = 'final_wave';
    public const BOSS_READY = 'boss_ready';
    public const BOSS = 'boss';
    public const TREASURE_READY = 'treasure_ready';
    public const COMPLETE = 'complete';

    public string $state = self::EXPLORING;
    public bool $finalRoomReached = false;
    public bool $finalWaveCleared = false;
    public bool $bossDefeated = false;

    /** @param array{min:float[],max:float[]} $finalRoomBounds */
    public function __construct(
        public readonly array $finalRoomBounds,
        public readonly array $treasureAnchor,
        public readonly int $finalWaveSize = 12,
        public readonly float $treasureRadius = 1.8,
    ) {
        if ($finalWaveSize <= 0) throw new \InvalidArgumentException('Final wave size must be positive.');
        if ($treasureRadius <= 0.0) throw new \InvalidArgumentException('Treasure radius must be positive.');
    }

    public static function fromLevel(AssembledLevel $level, int $finalWaveSize = 12): ?self
    {
        if ($level->treasureAnchor === null || $level->rooms === []) return null;
        $terminal = null;
        foreach ($level->rooms as $room) {
            if (($room['type'] ?? '') === 'BOSS_ROOM') $terminal = $room;
        }
        if ($terminal === null) {
            foreach ($level->rooms as $room) {
                if ($terminal === null || ($room['depth'] ?? -1) > ($terminal['depth'] ?? -1)) $terminal = $room;
            }
        }
        if ($terminal === null || !isset($terminal['bounds'])) return null;
        return new self($terminal['bounds'], $level->treasureAnchor, $finalWaveSize);
    }

    /** Returns true exactly once, when the player first enters the terminal room. */
    public function updatePlayer(float $x, float $y, float $z): bool
    {
        if ($this->state === self::EXPLORING && $this->insideFinalRoom($x, $y, $z)) {
            $this->finalRoomReached = true;
            $this->state = self::FINAL_WAVE;
            return true;
        }
        if ($this->state === self::TREASURE_READY && $this->distanceToTreasure($x, $z) <= $this->treasureRadius) {
            $this->state = self::COMPLETE;
        }
        return false;
    }

    public function notifyFinalWaveCleared(): bool
    {
        if ($this->state !== self::FINAL_WAVE) return false;
        $this->finalWaveCleared = true;
        $this->state = self::BOSS_READY;
        return true;
    }

    public function startBoss(): bool
    {
        if ($this->state !== self::BOSS_READY) return false;
        $this->state = self::BOSS;
        return true;
    }

    public function notifyBossDefeated(): bool
    {
        if ($this->state !== self::BOSS) return false;
        $this->bossDefeated = true;
        $this->state = self::TREASURE_READY;
        return true;
    }

    public function distanceToTreasure(float $x, float $z): float
    {
        return hypot((float)$this->treasureAnchor['x'] - $x, (float)$this->treasureAnchor['z'] - $z);
    }

    public function objectiveLabel(): string
    {
        return match ($this->state) {
            self::EXPLORING => 'TROUVE LE TRESOR',
            self::FINAL_WAVE => 'NETTOIE LA SALLE FINALE',
            self::BOSS_READY => 'LE BOSS ARRIVE',
            self::BOSS => 'DETRUIS LE BOSS',
            self::TREASURE_READY => 'RECUPERE LE TRESOR',
            self::COMPLETE => 'NIVEAU NETTOYE',
            default => '',
        };
    }

    private function insideFinalRoom(float $x, float $y, float $z): bool
    {
        $min = $this->finalRoomBounds['min'];
        $max = $this->finalRoomBounds['max'];
        // Small tolerance keeps doorway transitions from flickering at exact seams.
        return $x >= $min[0] - 0.15 && $x <= $max[0] + 0.15
            && $z >= $min[2] - 0.15 && $z <= $max[2] + 0.15
            && $y >= $min[1] - 1.0 && $y <= $max[1] + 1.0;
    }
}
