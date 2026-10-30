from pathlib import Path
p = Path('src/Scene/PlayScene.php')
s = p.read_text()
old = "$wheel = property_exists($mouse, 'wheelY') ? (int)$mouse->wheelY : 0;"
new = "$wheel = $this->engine->input instanceof \\App\\Input\\WheelInputManager ? $this->engine->input->consumeWheelY() : 0;"
if old not in s:
    raise SystemExit('wheel expression not found')
p.write_text(s.replace(old, new))
