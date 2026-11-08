"""Agile magenta raider: shared geometry for dedicated and full Blender builds."""
from generate_mechas import construct
from raider_parts import raider
def build(row=0,direction=0):construct(raider(row,direction))
