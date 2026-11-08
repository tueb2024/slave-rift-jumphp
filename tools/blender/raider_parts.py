"""Original agile magenta boss, lean biped with split rocket pods and piston legs."""
import math

def raider(row=0,direction=0):
    steel=(32,39,49);edge=(84,94,105);armor=(146,35,133);bright=(210,64,175);joint=(13,18,25)
    warning=(255,107,37);optic=(165,255,229);parts=[]
    stride=math.sin((row-1)*math.pi/2)*.27 if 1<=row<=4 else 0
    attack=row in (5,6,7);lean=.16 if attack else .08
    def box(n,p,s,c=armor):parts.append(dict(name=n,p=p,s=s,c=c))
    for side in (-1,1):
        x=side*.31;step=side*stride
        box('clawed_foot',(x,.13+step,.12),(.31,.64,.20),steel)
        for toe in (-1,1):box('toe',(x+toe*.09,.43+step,.09),(.07,.18,.1),edge)
        box('piston',(x,-step*.35,.47),(.13,.16,.63),edge)
        box('shin_plate',(x,.10+step*.25,.42),(.24,.20,.40),armor)
        box('knee',(x,.13,.74),(.28,.25,.21),joint)
        box('thigh',(x,-step*.35,.93),(.19,.28,.39),steel)
        box('hip_motor',(side*.35,0,1.12),(.22,.3,.22),edge)
    box('waist',(0,0,1.21),(.52,.33,.24),joint)
    box('rib_cage',(0,lean,1.53),(.78,.45,.52),steel)
    for side in (-1,1):
        box('chest_plate',(side*.23,lean+.23,1.60),(.34,.13,.39),bright)
        box('chest_rib',(side*.21,lean+.31,1.47),(.27,.05,.045),edge)
    box('optic_core',(0,lean+.29,1.52),(.12,.07,.24),warning if attack else optic)
    box('neck',(0,lean,1.91),(.17,.21,.16),joint)
    box('helmet',(0,lean+.08,2.04),(.43,.33,.25),steel)
    box('visor',(0,lean+.26,2.06),(.33,.045,.065),warning)
    for side in (-1,1):
        x=side*.59;front=.20 if attack else -.06
        box('shoulder',(x,front,1.80),(.35,.43,.25),armor)
        box('arm_piston',(x,.08,1.46),(.13,.19,.44),edge)
        box('forearm',(x,.27 if attack else .10,1.25),(.25,.46,.29),steel)
        box('claw',(x,.53 if attack else .31,1.16),(.22,.19,.18),edge)
        box('pod_spine',(side*.49,-.26,1.96),(.39,.49,.57),joint)
        box('pod_plate',(side*.49,-.25,2.22),(.43,.54,.12),bright)
        for z in (1.84,2.02,2.17):
            box('launcher',(side*.49,.12,z),(.28,.30,.13),steel)
            box('launcher_mouth',(side*.49,.28,z),(.20,.025,.08),warning if row==6 else joint)
        if row==6:
            box('muzzle_flash',(side*.49,.48,2.0),(.23,.34,.32),warning)
            box('muzzle_white',(side*.49,.62,2.02),(.09,.16,.13),(255,235,157))
    for i in range(4):box('back_heat_fin',(0,-.30-i*.045,1.52+i*.14),(.40,.09,.06),edge)
    if row==8:box('charge_beacon',(0,lean+.28,2.24),(.16,.12,.08),warning)
    if row==9:
        t=direction/7
        for part in parts:
            x,y,z=part['p'];part['p']=(x*(1+t*.4),y,z*(1-t*.78));sx,sy,sz=part['s'];part['s']=(sx,sy,sz*(1-t*.55))
    return parts
