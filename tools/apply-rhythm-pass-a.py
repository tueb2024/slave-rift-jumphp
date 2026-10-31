#!/usr/bin/env python3
from pathlib import Path

FILES = [
    Path('src/Scene/PlayScene.php'),
    Path('games/slave-rift/src/Scene/PlayScene.php'),
]


def replace_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count != 1:
        raise RuntimeError(f'{label}: expected exactly one match, found {count}')
    return text.replace(old, new, 1)


def patch(path: Path) -> None:
    text = path.read_text()
    text = replace_once(
        text,
        "use App\\Game\\BrushMap;\nuse App\\Game\\Run;",
        "use App\\Game\\BrushMap;\nuse App\\Game\\RhythmClock;\nuse App\\Game\\Run;",
        f'{path}: import',
    )
    text = replace_once(
        text,
        "    private RiftMusic $music;\n    private int $musicWave = 1;",
        "    private RiftMusic $music;\n    private RhythmClock $rhythm;",
        f'{path}: property',
    )
    text = replace_once(
        text,
        "        $this->step = new FixedStep(60, 8);\n",
        "        $this->step = new FixedStep(60, 8);\n        $this->rhythm = new RhythmClock();\n",
        f'{path}: clock init',
    )
    text = replace_once(
        text,
        "        $this->musicWave = $this->run->wave;\n        $this->music->reset($this->musicWave);\n",
        "",
        f'{path}: old music reset',
    )
    text = replace_once(
        text,
        "        $this->step->run($dt, function (float $h) use ($forward, $strafe, $turn, $fire): void {\n            $this->run->step($h, $forward, $strafe, $turn, $fire, $this->jumpPending, false);",
        "        $this->step->run($dt, function (float $h) use ($forward, $strafe, $turn, $fire): void {\n            $this->rhythm->advance($h, $this->run->level);\n            $this->run->step($h, $forward, $strafe, $turn, $fire, $this->jumpPending, false);",
        f'{path}: fixed rhythm advance',
    )
    text = replace_once(
        text,
        "        $this->playAudioEvents($beforeState, $beforeXp, $dt);\n        if ($this->run->wave !== $this->musicWave) {\n            $this->musicWave = $this->run->wave;\n            $this->music->onWaveStart($this->musicWave);\n        }\n        $this->music->update($dt, min(1.0, 0.32 + count($this->run->enemies) / 24.0));",
        "        $this->playAudioEvents($beforeState, $beforeXp, $dt);\n        $this->music->playSteps($this->rhythm->drain(), $this->run->level);",
        f'{path}: music transport',
    )
    text = replace_once(
        text,
        "            $font->draw($r, sprintf('INPUT KEYBOARD  TURN %.2f RAD/S  %s', Run::TURN_SPEED, $arsenal), 4, 48, 1, $cyan);",
        "            $font->draw($r, sprintf('INPUT KEYBOARD  TURN %.2f RAD/S  %s  BPM %.0f', Run::TURN_SPEED, $arsenal, $this->rhythm->bpm($run->level)), 4, 48, 1, $cyan);",
        f'{path}: debug bpm',
    )
    path.write_text(text)
    print(f'patched {path}')


for file in FILES:
    patch(file)
