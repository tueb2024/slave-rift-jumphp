"""Run with Blender --background --factory-startup --python ... -- [--samples N].
No third-party packages. Transparent PNGs are packed using Blender's image API.
Never writes outside the repository's assets/generated tree.
"""
import sys,math,json,hashlib,argparse,shutil
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parent))
import bpy
from mathutils import Vector
from art_spec import PALETTE,WEAPONS
import generate_mechas,generate_bosses,generate_weapons
ROOT=Path(__file__).resolve().parents[2];OUT=ROOT/'assets/generated';WORK=OUT/'.work'

def setup(size,weapon=False):
    scene=bpy.context.scene
    engines={i.identifier for i in scene.render.bl_rna.properties['engine'].enum_items}
    if 'BLENDER_EEVEE_NEXT' in engines:scene.render.engine='BLENDER_EEVEE_NEXT'
    elif 'BLENDER_EEVEE' in engines:scene.render.engine='BLENDER_EEVEE'
    else:
        scene.render.engine='CYCLES';scene.cycles.samples=ARGS.samples
        prefs=bpy.context.preferences.addons['cycles'].preferences
        try:
            prefs.compute_device_type='CUDA';prefs.get_devices();gpu=False
            for device in prefs.devices:device.use=device.type=='CUDA';gpu=gpu or device.use
            scene.cycles.device='GPU' if gpu else 'CPU'
        except Exception as error:print('CUDA unavailable, CPU fallback:',error);scene.cycles.device='CPU'
    scene.render.resolution_x=size[0];scene.render.resolution_y=size[1];scene.render.resolution_percentage=100
    scene.render.film_transparent=True;scene.render.image_settings.file_format='PNG';scene.render.image_settings.color_mode='RGBA'
    scene.view_settings.view_transform='Standard'
    scene.world.color=(.18,.18,.18)
    # Explicit lights and camera survive the per-pose rebuild.
    for obj in list(bpy.data.objects):
        if obj.type in ('LIGHT','CAMERA'):bpy.data.objects.remove(obj,do_unlink=True)
    target=Vector((0,.35,-.05) if weapon else (0,0,1.25))
    loc=Vector((2.3,-4,1.65) if weapon else (0,7,2.6))
    data=bpy.data.cameras.new('SR camera');cam=bpy.data.objects.new('SR camera',data);scene.collection.objects.link(cam)
    cam.location=loc;cam.rotation_euler=(target-loc).to_track_quat('-Z','Y').to_euler();data.type='ORTHO';data.ortho_scale=1.85 if weapon else 3.05;scene.camera=cam
    for name,loc,energy,size in [('Key',(-3,4,6),650,4),('Fill',(4,2,3),400,3),('Rim',(1,-4,5),850,3)]:
        light=bpy.data.lights.new(name,'AREA');light.energy=energy;light.shape='DISK';light.size=size
        obj=bpy.data.objects.new(name,light);scene.collection.objects.link(obj);obj.location=loc;obj.rotation_euler=(target-obj.location).to_track_quat('-Z','Y').to_euler()
    return scene

def pack(paths,columns,rows,size,target):
    w,h=size;aw,ah=w*columns,h*rows;pixels=[0.0]*(aw*ah*4)
    for index,path in enumerate(paths):
        tile=bpy.data.images.load(str(path),check_existing=False);src=list(tile.pixels[:]);col=index%columns;row=index//columns
        for y in range(h):
            dst=((rows-1-row)*h+y)*aw*4+col*w*4;pixels[dst:dst+w*4]=src[y*w*4:(y+1)*w*4]
        bpy.data.images.remove(tile)
    atlas=bpy.data.images.new('SR atlas',width=aw,height=ah,alpha=True);atlas.pixels.foreach_set(pixels)
    atlas.filepath_raw=str(target);atlas.file_format='PNG';atlas.save();bpy.data.images.remove(atlas)

def render_tile(scene,path):scene.render.filepath=str(path);bpy.ops.render.render(write_still=True)
def icon(source,path,dim=False):
    im=bpy.data.images.load(str(source),check_existing=False)
    # The source is a single FPS frame, not the packed atlas.
    im.scale(32,16)
    if dim:
        pixels=list(im.pixels[:])
        for i in range(0,len(pixels),4):
            for j in range(3):pixels[i+j]*=.35
        im.pixels.foreach_set(pixels)
    im.filepath_raw=str(path);im.file_format='PNG';im.save();bpy.data.images.remove(im)

def main():
    OUT.mkdir(parents=True,exist_ok=True);WORK.mkdir(exist_ok=True);models=OUT/'models';models.mkdir(exist_ok=True)
    for obj in list(bpy.data.objects):bpy.data.objects.remove(obj,do_unlink=True)
    for kind in PALETTE:
        folder=OUT/('bosses' if kind=='siege' else 'mechas');folder.mkdir(exist_ok=True);scene=setup((96,112));tiles=[]
        for row in range(10):
            for direction in range(8):
                if kind=='siege':generate_bosses.build(row,direction)
                else:generate_mechas.build(kind,row,direction)
                angle=0 if row==9 else -direction*math.pi/4
                for obj in bpy.data.objects:
                    if obj.get('sr_generated'):
                        x,y,z=obj.location;obj.location=(math.cos(angle)*x-math.sin(angle)*y,math.sin(angle)*x+math.cos(angle)*y,z);obj.rotation_euler.z=angle
                if row==0 and direction==0:bpy.ops.wm.save_as_mainfile(filepath=str(models/(kind+'.blend')),check_existing=False)
                path=WORK/(kind+'_%d_%d.png'%(row,direction));render_tile(scene,path);tiles.append(path)
        pack(tiles,8,10,(96,112),folder/(kind+'.png'))
    for kind in WEAPONS:
        folder=OUT/'weapons';folder.mkdir(exist_ok=True);scene=setup((144,96),True);tiles=[]
        for frame in range(4):
            generate_weapons.build(kind,frame)
            if frame==0:bpy.ops.wm.save_as_mainfile(filepath=str(models/(kind+'.blend')),check_existing=False)
            path=WORK/(kind+'_%d.png'%frame);render_tile(scene,path);tiles.append(path)
        pack(tiles,4,1,(144,96),folder/(kind+'.png'));icon(tiles[0],folder/(kind+'-hud.png'));icon(tiles[0],folder/(kind+'-hud-dim.png'),True)
    manifest={'version':1,'renderer':'blender','blender':bpy.app.version_string,'enemyFrame':[96,112],'enemyAtlas':[768,1120],'weaponFrame':[144,96],
        'files':{str(p.relative_to(OUT)).replace('\\','/'):hashlib.sha256(p.read_bytes()).hexdigest() for p in sorted(OUT.rglob('*')) if p.is_file() and '.work' not in p.parts and p.suffix in ('.png','.blend')}}
    (OUT/'manifest.json').write_text(json.dumps(manifest,indent=2)+'\n');shutil.rmtree(WORK)
    print('SR assets generated:',len(manifest['files']))

if __name__=='__main__':
    args=sys.argv[sys.argv.index('--')+1:] if '--' in sys.argv else []
    parser=argparse.ArgumentParser();parser.add_argument('--samples',type=int,default=16);ARGS=parser.parse_args(args)
    try:main()
    except Exception as error:print('SLAVE RIFT ASSET BUILD FAILED:',error,file=sys.stderr);raise
