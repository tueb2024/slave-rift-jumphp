"""Original sawn-off, eight registered FPS poses. Blender 3.4+, CPU Cycles.
blender -b --factory-startup --python-exit-code 1 --python tools/blender/render_shotgun.py
Editable scene + high resolution RGBA plates stay outside the runtime package.
"""
from pathlib import Path
import bpy, math, sys
from mathutils import Vector
ROOT=Path(__file__).resolve().parents[2]
OUT=ROOT/'tools/blender/shotgun-source';OUT.mkdir(parents=True,exist_ok=True)
bpy.ops.object.select_all(action='SELECT');bpy.ops.object.delete(use_global=False)
scene=bpy.context.scene
scene.render.engine='CYCLES';scene.cycles.samples=256;scene.cycles.use_denoising=False
scene.render.threads_mode='FIXED';scene.render.threads=4
scene.render.resolution_x=288;scene.render.resolution_y=384;scene.render.resolution_percentage=100
scene.render.film_transparent=True;scene.render.image_settings.file_format='PNG';scene.render.image_settings.color_mode='RGBA'
scene.view_settings.view_transform='Standard';scene.view_settings.look='Medium High Contrast';scene.view_settings.exposure=0;scene.view_settings.gamma=1
scene.world.color=(.13,.13,.13)

def material(name,base,metal=0,rough=.5,grain=0):
 m=bpy.data.materials.new(name);m.diffuse_color=(*base,1);m.use_nodes=True
 n=m.node_tree.nodes;links=m.node_tree.links;p=n.get('Principled BSDF');p.inputs['Base Color'].default_value=(*base,1);p.inputs['Metallic'].default_value=metal;p.inputs['Roughness'].default_value=rough
 if grain:
  tex=n.new('ShaderNodeTexNoise');tex.inputs['Scale'].default_value=grain;tex.inputs['Detail'].default_value=3;tex.inputs['Roughness'].default_value=.7
  coords=n.new('ShaderNodeTexCoord');mapping=n.new('ShaderNodeVectorMath');mapping.operation='MULTIPLY';mapping.inputs[1].default_value=(8,1,8) if name=='Oiled walnut' else (1,1,1)
  links.new(coords.outputs['Generated'],mapping.inputs[0]);links.new(mapping.outputs[0],tex.inputs['Vector'])
  ramp=n.new('ShaderNodeValToRGB');ramp.color_ramp.elements[0].position=.22;ramp.color_ramp.elements[0].color=(*(c*.35 for c in base),1);ramp.color_ramp.elements[1].position=.8;ramp.color_ramp.elements[1].color=(*(min(1,c*1.65) for c in base),1)
  links.new(tex.outputs['Fac'],ramp.inputs[0]);links.new(ramp.outputs[0],p.inputs['Base Color'])
  bump=n.new('ShaderNodeBump');bump.inputs['Strength'].default_value=.22;bump.inputs['Distance'].default_value=.012;links.new(tex.outputs['Fac'],bump.inputs['Height']);links.new(bump.outputs[0],p.inputs['Normal'])
 return m
steel=material('Blued steel',(.065,.073,.082),.72,.43,75)
edge=material('Worn machined edges',(.20,.19,.17),.68,.40,95)
dark=material('Recesses',(.012,.013,.014),.4,.6)
wood=material('Oiled walnut',(.16,.060,.021),0,.4,7)
leather=material('Black leather',(.026,.021,.019),0,.58,95)
seam=material('Raised leather seams',(.058,.044,.031),0,.68,100)
skin=material('Warm skin',(.39,.205,.105),0,.65,45)
brass=material('Brass',(.43,.25,.065),.75,.3)
red=material('Shell lacquer',(.25,.023,.012),.1,.4)

root=bpy.data.objects.new('Weapon recoil',None);scene.collection.objects.link(root)
breaker=bpy.data.objects.new('Break action hinge',None);scene.collection.objects.link(breaker);breaker.parent=root;breaker.location=(0,.06,-.09)

def finish(o,name,mat,parent=root):
 o.name=name;o.data.materials.append(mat);o.parent=parent
 return o

def box(name,pos,scale,mat,bevel=.025,parent=root):
 bpy.ops.mesh.primitive_cube_add(size=1,location=pos);o=bpy.context.object;o.scale=scale;bpy.ops.object.transform_apply(location=False,rotation=False,scale=True)
 if bevel:
  mod=o.modifiers.new('Forged edge bevel','BEVEL');mod.width=bevel;mod.segments=3;o.modifiers.new('Weighted corner normals','WEIGHTED_NORMAL')
 return finish(o,name,mat,parent)

def cyl(name,pos,r,depth,mat,axis='Y',parent=root,vertices=32):
 bpy.ops.mesh.primitive_cylinder_add(vertices=vertices,radius=r,depth=depth,location=pos);o=bpy.context.object
 if axis=='Y':o.rotation_euler.x=math.pi/2
 elif axis=='X':o.rotation_euler.y=math.pi/2
 bevel=o.modifiers.new('Machined lip','BEVEL');bevel.width=.006;bevel.segments=2;o.modifiers.new('Weighted cylinder normals','WEIGHTED_NORMAL')
 return finish(o,name,mat,parent)

def oval(name,pos,scale,mat,parent=root):
 bpy.ops.mesh.primitive_uv_sphere_add(segments=24,ring_count=12,location=pos);o=bpy.context.object;o.scale=scale
 for f in o.data.polygons:f.use_smooth=True
 return finish(o,name,mat,parent)

def bar(name,a,b,r,mat,parent=root):
 a,b=Vector(a),Vector(b);o=cyl(name,(a+b)/2,r,(b-a).length,mat,axis='Z',parent=parent,vertices=16);o.rotation_euler=(b-a).to_track_quat('Z','Y').to_euler();return o

# Close, broad breech; short parallel tubes recede toward the vanishing point.
box('Breech block',(0,-.22,.015),(.59,.50,.33),steel,.045)
box('Engraved top tang',(0,-.43,.193),(.19,.52,.038),edge,.015)
box('Locking seam',(0,-.045,.19),(.59,.018,.015),dark,.003)
for x in (-.18,.18):
 box('Receiver shoulder',(x,-.22,.18),(.13,.39,.045),edge,.018)
 cyl('Hinge pin',(x*1.72,-.015,-.04),.052,.028,edge,'X')
 cyl('Hinge pin recess',(x*1.8,-.015,-.04),.017,.032,dark,'X')
 # Barrel coordinates relative to hinge, opening lowers muzzles while chambers rise.
 cyl('Short barrel',(x,.49,.14),.145,1.02,steel,parent=breaker)
 cyl('Muzzle cut lip',(x,1.005,.14),.148,.022,edge,parent=breaker)
 cyl('Dark bore',(x,1.021,.14),.113,.008,dark,parent=breaker)
 cyl('Rear chamber rim',(x,-.028,.14),.146,.025,edge,parent=breaker)
 cyl('Chamber recess',(x,-.043,.14),.111,.009,dark,parent=breaker)
 cyl('Loaded shell head',(x,-.052,.14),.097,.009,brass,parent=breaker)
 cyl('Primer',(x,-.06,.14),.028,.011,edge,parent=breaker)
 box('Barrel edge rib',(x,.48,.284),(.033,.98,.012),edge,.004,parent=breaker)
 # Rear hammers and small brass pins break the outline, readable at 96 px.
 hammer=box('External hammer',(x,-.40,.27),(.065,.13,.14),steel,.02);hammer.rotation_euler.x=-.25
 cyl('Hammer pivot',(x,-.405,.335),.025,.07,edge,'X')
box('Central rib',(0,.47,.264),(.075,1.03,.035),steel,.009,parent=breaker)
box('Brass bead sight',(0,.94,.298),(.035,.06,.04),brass,.012,parent=breaker)
box('Walnut fore-end',(0,.40,-.06),(.49,.70,.15),wood,.065,parent=breaker)
for y in (.20,.27,.34,.41,.48,.55,.62):
 for side in (-1,1):
  grip=box('Fore-end checkering',(side*.245,y,-.065),(.009,.012,.095),dark,.003,parent=breaker);grip.rotation_euler.x=.35
stock=box('Cut walnut grip',(0,-.66,-.16),(.28,.38,.24),wood,.08);stock.rotation_euler.x=-.55
box('Grip heel',(0,-.87,-.29),(.37,.09,.26),dark,.03)
# Lever, screws, receiver grooves. Geometry gives real highlights, not flat lines.
lever=box('Opening lever',(0,-.27,.223),(.065,.28,.028),steel,.012);lever.rotation_euler.z=-.22
oval('Lever thumb pad',(.035,-.405,.237),(.066,.071,.025),edge)
for y in (-.30,-.13):
 cyl('Tang screw',(0,y,.217),.025,.009,edge,'Z')
 box('Screw slot',(0,y,.224),(.034,.008,.005),dark,.002)
for x in (-.265,.265):
 for y in (-.35,-.28,-.21):box('Receiver cut line',(x,y,.181),(.012,.035,.009),dark,.003)
# Fingerless shooting glove wraps grip. Forearms crop naturally at bottom.
bar('Right forearm',(.30,-1.25,-.63),(.24,-.77,-.32),.145,skin)
oval('Right glove palm',(.19,-.65,-.20),(.19,.26,.15),leather)
bar('Right wrist cuff',(.26,-.93,-.40),(.23,-.79,-.32),.16,leather)
for i in range(4):
 y=-.64+i*.066
 oval('Right glove finger',(.245,y,-.12),(.105,.055,.068),leather)
 oval('Exposed right fingertip',(.29,y,-.10),(.051,.043,.046),skin)
bar('Right thumb',(.12,-.72,-.07),(.12,-.48,.025),.065,leather)
# Padded firing hand covers the cut grip; exposed fingertips curl around its left.
oval('Firing hand back',(0,-.83,.045),(.18,.21,.13),leather)
for j in range(3):
 box('Backhand padding',(0,-.87+j*.065,.16),(.20,.053,.022),seam,.012)
 oval('Curled fingertip',(-.15,-.84+j*.065,.065),(.049,.04,.048),skin)
# Support glove, articulated with barrels while opening; forearm stays foreground.
bar('Left forearm',(-.46,-1.12,-.61),(-.38,-.30,-.24),.135,skin)
bar('Left cuff',(-.39,-.45,-.28),(-.37,-.29,-.22),.145,leather)
left=bpy.data.objects.new('Support hand',None);scene.collection.objects.link(left);left.parent=root
oval('Support palm',(-.27,.17,-.22),(.16,.25,.115),leather,parent=left)
for i in range(4):
 y=.02+i*.083
 oval('Support knuckle',(-.31,y,-.11),(.095,.052,.07),leather,parent=left)
 oval('Support fingertip',(-.235,y,-.07),(.065,.042,.052),skin,parent=left)
bar('Support thumb',(-.34,.03,-.11),(-.27,.17,.01),.058,leather,parent=left)
for side in (-1,1):
 for i in range(3):
  box('Glove stitching',(side*.30,-.69+i*.055,-.055),(.025,.012,.008),seam,.003)
# Two red reload cartridges, hidden except in the insertion pose.
shells=[]
for x in (-.16,.16):
 s=bpy.data.objects.new('Reload cartridge',None);scene.collection.objects.link(s);s.parent=root
 cyl('Red shell',(x,-.02,.48),.095,.29,red,parent=s)
 cyl('Shell brass base',(x,-.175,.48),.098,.055,brass,parent=s)
 cyl('Shell base primer',(x,-.207,.48),.025,.006,edge,parent=s)
 shells.append(s)

# Painted warm arsenal light: large soft key upper-left, subdued fill, warm rim.
def light(name,loc,energy,color,size):
 data=bpy.data.lights.new(name,'AREA');data.energy=energy;data.color=color;data.shape='DISK';data.size=size
 o=bpy.data.objects.new(name,data);scene.collection.objects.link(o);o.location=loc;o.rotation_euler=(Vector((0,.2,0))-o.location).to_track_quat('-Z','Y').to_euler();return o
light('Warm upper-left key',(-2,-1.5,4),160,(1,.86,.70),3)
light('Soft cool fill',(2,-.5,2),90,(.69,.76,.87),3)
light('Warm silhouette rim',(-1,2,2),95,(1,.72,.49),2)
cam_data=bpy.data.cameras.new('FPS camera');cam=bpy.data.objects.new('FPS camera',cam_data);scene.collection.objects.link(cam)
cam.location=(.015,-2.7,1.2);target=Vector((0,.32,.18));cam.rotation_euler=(target-cam.location).to_track_quat('-Z','Y').to_euler();cam_data.type='PERSP';cam_data.lens=43;scene.camera=cam

# Layered low-poly flame cards (aligned with view), accompanied by physical light.
flashes=[]
for side in (-1,1):
 for layer,(radius,col) in enumerate(((.43,(1,.15,.012)),(.28,(1,.56,.07)),(.13,(1,.94,.60)))):
  verts=[]
  center=Vector((side*.18,1.12,.23))
  right=Vector((1,0,0));up=Vector((0,.4,.92))
  for i in range(16):
   a=i*math.pi/8;r=radius*(1 if i%2==0 else .33)*(1+.12*math.sin(i*5+side))
   verts.append(center+right*(math.cos(a)*r)+up*(math.sin(a)*r)+Vector((0,-layer*.004,0)))
  mesh=bpy.data.meshes.new('Flame star');mesh.from_pydata(verts,[],[list(range(16))]);mesh.update();o=bpy.data.objects.new('Muzzle flash',mesh);scene.collection.objects.link(o);o.parent=breaker
  m=bpy.data.materials.new('Flash emission');m.use_nodes=True;n=m.node_tree.nodes;n.clear();e=n.new('ShaderNodeEmission');e.inputs[0].default_value=(*col,1);e.inputs[1].default_value=1.4;out=n.new('ShaderNodeOutputMaterial');m.node_tree.links.new(e.outputs[0],out.inputs[0]);mesh.materials.append(m);flashes.append(o)
flashlight=light('Flash reflection',(0,1,1),0,(1,.42,.08),.7)

# Store the actual poses as keyed transformations in the editable model.
for i in range(8):
 root.location=(0,[-0.,-.015,-.04,-.15,-.05,-.05,-.02,0][i], [0,0,.015,.075,-.09,-.09,-.025,0][i])
 root.rotation_euler=(math.radians([0,1,3,-7,0,0,1,0][i]),0,math.radians([0,0,0,-2,-5,-5,-2,0][i]))
 breaker.rotation_euler.x=math.radians([0,0,0,0,-35,-35,-12,0][i])
 left.location=(0,0,0) if i<4 or i>=6 else (.20,-.30,.50)
 # Keep the support arm joined to the moving wrist through opening/insertion.
 arm=bpy.data.objects['Left forearm'];a=Vector((-.46,-1.12,-.61));b=Vector((-.38,-.30,-.24))+left.location
 arm.location=(a+b)/2;arm.rotation_euler=(b-a).to_track_quat('Z','Y').to_euler();arm.scale.z=(b-a).length/.88
 arm.keyframe_insert(data_path='location',frame=i+1);arm.keyframe_insert(data_path='rotation_euler',frame=i+1);arm.keyframe_insert(data_path='scale',frame=i+1)
 cuff=bpy.data.objects['Left cuff'];cuff.location=Vector((-.38,-.37,-.25))+left.location;cuff.keyframe_insert(data_path='location',frame=i+1)
 for obj in (root,breaker,left):
  obj.keyframe_insert(data_path='location',frame=i+1);obj.keyframe_insert(data_path='rotation_euler',frame=i+1)
 for obj in flashes:
  obj.hide_render=i not in (1,2);obj.scale=(.52,.52,.52) if i==1 else (1,1,1);obj.keyframe_insert(data_path='hide_render',frame=i+1);obj.keyframe_insert(data_path='scale',frame=i+1)
 flashlight.data.energy=110 if i==2 else (45 if i==1 else 0);flashlight.data.keyframe_insert(data_path='energy',frame=i+1)
 for shell in shells:
  for child in shell.children:child.hide_render=i!=5;child.keyframe_insert(data_path='hide_render',frame=i+1)
scene.frame_start=1;scene.frame_end=8;scene.frame_set(1)
bpy.ops.wm.save_as_mainfile(filepath=str(OUT/'shotgun.blend'),check_existing=False)
frames=[0] if '--preview' in sys.argv else range(8)
for i in frames:
 scene.frame_set(i+1);scene.render.filepath=str(OUT/f'pose-{i}.png');bpy.ops.render.render(write_still=True)
print('Rendered sawn-off source plates:',OUT)
