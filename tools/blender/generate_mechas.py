"""Blender-only builders for original coloured chassis."""
import bpy
from art_spec import mecha
MATERIALS={}
def material(rgb):
    key=tuple(rgb)
    if key not in MATERIALS:
        m=bpy.data.materials.new('SR_%d_%d_%d'%key);m.diffuse_color=tuple(v/255 for v in key)+(1,)
        m.use_nodes=True;shader=m.node_tree.nodes.get('Principled BSDF');shader.inputs['Base Color'].default_value=m.diffuse_color
        shader.inputs['Metallic'].default_value=.35;shader.inputs['Roughness'].default_value=.55
        if rgb==(165,255,229):
            shader.inputs['Emission Color'].default_value=m.diffuse_color;shader.inputs['Emission Strength'].default_value=1.8
        MATERIALS[key]=m
    return MATERIALS[key]
def construct(parts):
    for obj in list(bpy.data.objects):
        if obj.get('sr_generated'):
            mesh=obj.data;bpy.data.objects.remove(obj,do_unlink=True)
            if mesh.users==0:bpy.data.meshes.remove(mesh)
    for part in parts:
        bpy.ops.mesh.primitive_cube_add(size=1,location=part['p']);obj=bpy.context.object;obj.name='SR_'+part['name'];obj['sr_generated']=True
        obj.dimensions=part['s'];bpy.ops.object.transform_apply(location=False,rotation=False,scale=True)
        obj.data.materials.append(material(part['c']))
        bevel=obj.modifiers.new('Plate edges','BEVEL');bevel.width=.018;bevel.segments=1
        obj.modifiers.new('Weighted normals','WEIGHTED_NORMAL')
def build(kind,row=0,direction=0):construct(mecha(kind,row,direction))
