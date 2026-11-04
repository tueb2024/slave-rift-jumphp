"""Verify produced hashes without Blender or Pillow."""
from pathlib import Path
import hashlib,json,struct
root=Path(__file__).resolve().parents[2]/'assets/generated'
data=json.loads((root/'manifest.json').read_text())
for name,digest in data['files'].items():
    path=(root/name).resolve()
    if root.resolve() not in path.parents:raise ValueError('Unsafe manifest path: '+name)
    assert hashlib.sha256(path.read_bytes()).hexdigest()==digest,name
    if path.suffix=='.png':
        raw=path.read_bytes();assert raw[:8]==b'\x89PNG\r\n\x1a\n',name
        size=struct.unpack('>II',raw[16:24])
        expected=(768,1120) if name.startswith(('mechas/','bosses/')) else ((32,16) if '-hud' in name else (576,96))
        assert size==expected,(name,size,expected)
print('Verified',len(data['files']),'generated assets')
