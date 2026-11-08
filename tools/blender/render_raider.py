"""blender -b --factory-startup --python-exit-code 1 --python tools/blender/render_raider.py"""
import bpy,math,sys
from pathlib import Path
from mathutils import Vector
sys.path.insert(0,str(Path(__file__).resolve().parent))
from raider_parts import raider
ROOT=Path(__file__).resolve().parents[2];OUT=ROOT/'tools/blender/raider-source';OUT.mkdir(parents=True,exist_ok=True)
bpy.ops.object.select_all(action='SELECT');bpy.ops.object.delete(use_global=False)
scene=bpy.context.scene;scene.render.engine='CYCLES';scene.cycles.samples=24;scene.cycles.use_denoising=False;scene.render.threads_mode='FIXED';scene.render.threads=4
scene.render.resolution_x=192;scene.render.resolution_y=224;scene.render.resolution_percentage=100
scene.render.film_transparent=True;scene.render.image_settings.file_format='PNG';scene.render.image_settings.color_mode='RGBA';scene.view_settings.view_transform='Standard';scene.world.color=(.18,.18,.18)
mats={}
def material(rgb):
    if rgb not in mats:
        m=bpy.data.materials.new(str(rgb));m.use_nodes=True;p=m.node_tree.nodes.get('Principled BSDF');p.inputs['Base Color'].default_value=tuple(c/255 for c in rgb)+(1,);p.inputs['Metallic'].default_value=.55;p.inputs['Roughness'].default_value=.43
        if rgb in [(255,107,37),(165,255,229),(255,235,157)]:
            key='Emission Color' if 'Emission Color' in p.inputs else 'Emission';p.inputs[key].default_value=tuple(c/255 for c in rgb)+(1,)
            if 'Emission Strength' in p.inputs:p.inputs['Emission Strength'].default_value=1.5
        mats[rgb]=m
    return mats[rgb]
target=Vector((0,0,1.2));data=bpy.data.cameras.new('Raider camera');cam=bpy.data.objects.new('Raider camera',data);scene.collection.objects.link(cam);cam.location=(0,6,2.7);cam.rotation_euler=(target-cam.location).to_track_quat('-Z','Y').to_euler();data.type='ORTHO';data.ortho_scale=2.9;scene.camera=cam
for name,loc,energy,color in [('key',(-3,4,6),350,(1,.83,.68)),('fill',(4,2,3),160,(.68,.76,1)),('rim',(0,-3,4),400,(1,.48,.28))]:
    light=bpy.data.lights.new(name,'AREA');light.energy=energy;light.color=color;light.size=3;o=bpy.data.objects.new(name,light);scene.collection.objects.link(o);o.location=loc;o.rotation_euler=(target-o.location).to_track_quat('-Z','Y').to_euler()
for row in range(10):
 for direction in range(8):
    for o in list(bpy.data.objects):
        if o.type=='MESH':bpy.data.objects.remove(o,do_unlink=True)
    angle=0 if row==9 else -direction*math.pi/4
    for part in raider(row,direction):
        bpy.ops.mesh.primitive_cube_add(size=1,location=part['p']);o=bpy.context.object;o.name=part['name'];o.scale=part['s'];bpy.ops.object.transform_apply(location=False,rotation=False,scale=True)
        bevel=o.modifiers.new('Forged bevel','BEVEL');bevel.width=.022;bevel.segments=2;o.modifiers.new('Weighted normals','WEIGHTED_NORMAL');o.data.materials.append(material(part['c']))
        x,y,z=o.location;o.location=(math.cos(angle)*x-math.sin(angle)*y,math.sin(angle)*x+math.cos(angle)*y,z);o.rotation_euler.z=angle
    if row==0 and direction==0:bpy.ops.wm.save_as_mainfile(filepath=str(OUT/'raider.blend'),check_existing=False)
    scene.render.filepath=str(OUT/f'{row}-{direction}.png');bpy.ops.render.render(write_still=True)
