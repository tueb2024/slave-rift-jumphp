#!/usr/bin/env python3
from pathlib import Path

path = Path('tools/simulation-test.php')
text = path.read_text()

def once(old: str, new: str, label: str) -> None:
    global text
    count = text.count(old)
    if count != 1:
        raise RuntimeError(f'{label}: expected one match, got {count}')
    text = text.replace(old, new, 1)

once(
    "require __DIR__ . '/../src/Game/BrushMap.php';\nrequire __DIR__ . '/../src/Game/Run.php';",
    "require __DIR__ . '/../src/Game/BrushMap.php';\nrequire __DIR__ . '/../src/Game/RhythmClock.php';\nrequire __DIR__ . '/../src/Game/Run.php';",
    'require RhythmClock',
)
once(
    "use App\\Game\\BrushMap;\nuse App\\Game\\Run;",
    "use App\\Game\\BrushMap;\nuse App\\Game\\RhythmClock;\nuse App\\Game\\Run;",
    'use RhythmClock',
)
once(
    "check($run->arsenalLabel() === 'COLT .45 ONLY', 'run starts with manual Colt arsenal');",
    "check($run->arsenalLabel() === 'COLT .45', 'run starts with rhythmic Colt arsenal');\n"
    "foreach ([1 => 0, 4 => 1, 8 => 2, 12 => 3, 16 => 4, 20 => 5, 24 => 6] as $level => $stage) {\n"
    "    check(Run::primaryStageForLevel($level) === $stage, \"primary stage $stage unlocks at level $level\");\n"
    "}",
    'primary contract',
)
once(
    "$sawDeathAnimation = false;\n$sawTracer = false;\nfor ($i = 0; $i < 600 && $dm1->state !== 'dead'; $i++) {",
    "$sawDeathAnimation = false;\n$sawTracer = false;\n$dm1Rhythm = new RhythmClock();\nfor ($i = 0; $i < 600 && $dm1->state !== 'dead'; $i++) {",
    'DM1 rhythm init',
)
once(
    "    [$forward, $strafe, $turn, $fire] = $dm1->autopilot();\n    $dm1->step(1 / 60, $forward, $strafe, $turn, $fire);",
    "    [$forward, $strafe, $turn, $fire] = $dm1->autopilot();\n"
    "    $rhythmSteps = $dm1Rhythm->advance(1 / 60, $dm1->level);\n"
    "    $dm1->step(1 / 60, $forward, $strafe, $turn, $fire, false, false, $rhythmSteps);",
    'DM1 rhythmic step',
)
path.write_text(text)
print('patched tools/simulation-test.php')
