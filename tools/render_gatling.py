import bpy, math, sys
from pathlib import Path
from mathutils import Vector, Matrix

out=Path(sys.argv[sys.argv.index('--')+1]).resolve();out.mkdir(parents=True,exist_ok=True)
scene=bpy.context.scene
for o in list(scene.objects):
    if o.type!='MESH': bpy.data.objects.remove(o,do_unlink=True)
for i,o in enumerate(scene.objects):
    m=bpy.data.materials.new(o.name+' gunmetal');m.diffuse_color=(.16,.19,.23,1) if 'Cylinder' in o.name else (.09,.11,.14,1)
    m.use_nodes=True;p=m.node_tree.nodes.get('Principled BSDF');p.inputs['Base Color'].default_value=m.diffuse_color;p.inputs['Metallic'].default_value=.65;p.inputs['Roughness'].default_value=.4
    o.data.materials.clear();o.data.materials.append(m)
camdata=bpy.data.cameras.new('FPS camera');cam=bpy.data.objects.new('FPS camera',camdata);scene.collection.objects.link(cam)
cam.location=(7,10,6);cam.rotation_euler=(Vector((0,-1.5,0))-cam.location).to_track_quat('-Z','Y').to_euler();camdata.type='ORTHO';camdata.ortho_scale=12;scene.camera=cam
def area(name,pos,power,size):
    d=bpy.data.lights.new(name,'AREA');d.energy=power;d.shape='DISK';d.size=size;o=bpy.data.objects.new(name,d);scene.collection.objects.link(o);o.location=pos;o.rotation_euler=(Vector((0,-1,0))-o.location).to_track_quat('-Z','Y').to_euler()
area('key',(-4,2,9),1800,7);area('rim',(5,-5,4),1600,5);area('fill',(4,5,2),900,5)
scene.world.color=(.35,.35,.35);scene.render.engine='CYCLES';scene.cycles.samples=24
scene.render.resolution_x=432;scene.render.resolution_y=288;scene.render.resolution_percentage=100
scene.render.film_transparent=True;scene.render.image_settings.file_format='PNG';scene.render.image_settings.color_mode='RGBA'
scene.view_settings.view_transform='Standard';scene.view_settings.exposure=-.8
barrels=[o for o in scene.objects if o.type=='MESH' and o.name in ['Cylinder.001','Cylinder.002','Cylinder.003','Cylinder.004','Cylinder.005','Cylinder.006']]
original={o.name:o.matrix_world.copy() for o in barrels}
for name,angle in [('ready',0),('spin',math.pi/6)]:
    for o in barrels:o.matrix_world=Matrix.Rotation(angle,4,'Y')@original[o.name]
    scene.render.filepath=str(out/('gatling-'+name+'.png'));bpy.ops.render.render(write_still=True)
