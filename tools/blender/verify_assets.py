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

# Active sawn-off has its own eight-pose authoring pipeline, outside generated/.
repo=root.parent.parent
manifest=repo/'tools/blender/shotgun-source/manifest.json'
if manifest.exists():
    shotgun=json.loads(manifest.read_text())
    assert shotgun['frame']==[96,128] and shotgun['atlas']==[768,128]
    for name,digest in shotgun['files'].items():
        path=(repo/name).resolve()
        assert repo.resolve() in path.parents,name
        assert hashlib.sha256(path.read_bytes()).hexdigest()==digest,name
    print('Verified sawn-off model, eight source plates and runtime atlas')

flame_manifest=repo/'tools/flame-source/manifest.json'
if flame_manifest.exists():
    flame=json.loads(flame_manifest.read_text())
    assert flame['atlas']==[768,128] and flame['frame']==[96,128]
    for name,digest in flame['files'].items():
        path=(repo/name).resolve()
        assert repo.resolve() in path.parents,name
        assert hashlib.sha256(path.read_bytes()).hexdigest()==digest,name
    print('Verified flowing flame source and runtime atlas')

raider_manifest=repo/'tools/blender/raider-source/manifest.json'
if raider_manifest.exists():
    for name,digest in json.loads(raider_manifest.read_text())['files'].items():
        path=(repo/name).resolve()
        assert repo.resolve() in path.parents,name
        assert hashlib.sha256(path.read_bytes()).hexdigest()==digest,name
    print('Verified agile raider model and active boss atlas')
