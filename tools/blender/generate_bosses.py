"""Siege family: broad shoulder launchers, twin cannons, exposed optical core."""
from generate_mechas import construct
from art_spec import mecha
def build(row=0,direction=0):construct(mecha('siege',row,direction))
