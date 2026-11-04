"""Nine industrial weapons; same plate, receiver and luminous sight language."""
from generate_mechas import construct
from art_spec import weapon
def build(kind,frame=0):construct(weapon(kind,frame))
