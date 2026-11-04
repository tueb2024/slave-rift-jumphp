"""Original SR plate-and-piston designs; shared by Blender and portable fallback.
Coordinates: X across, Y forward, Z up. Materials never encode health.
"""
import math
PALETTE = {'runner':(255,181,35),'guardian':(35,185,244),'enforcer':(230,48,74),'brute':(187,65,227),'siege':(187,65,227)}
STEEL=(43,55,68); JOINT=(19,27,34); OPTIC=(165,255,229)
WEAPONS=('pistol','smg','assault','gatling','bazooka','sniper','flame','artillery','pulse')

def mecha(kind,row=0,direction=0):
    boss=kind=='siege'; family='brute' if boss else kind
    w,h={'runner':(.57,1.75),'guardian':(.94,2.0),'enforcer':(.70,2.1),'brute':(1.15,2.15)}[family]
    col=PALETTE[kind]; parts=[]
    def box(name,p,s,c=col): parts.append(dict(name=name,p=p,s=s,c=c))
    stride=math.sin((row-1)*math.pi/2)*.19 if 1<=row<=4 else 0
    collapse=direction/7 if row==9 else 0
    recoil=.10 if row in (6,8) else 0
    for side in (-1,1):
        x=side*w*.36; leg=stride*side
        box('foot',(x,.10+leg,.12),(w*.36,.52,.22),STEEL)
        box('shin',(x,leg,.40),(w*.23,.25,.42))
        box('knee',(x,.08+leg,.64),(w*.28,.27,.18),JOINT)
        box('piston',(x,-leg,.85),(w*.17,.19,.34),STEEL)
    box('hip',(0,0,1.0),(w*.72,.38,.22),STEEL)
    box('chassis',(0,0,1.36-recoil),(w,.53,.61))
    box('front-plate',(0,.30,1.39-recoil),(w*.77,.12,.40))
    box('waist-light',(0,.375,1.17),(w*.5,.055,.06),OPTIC)
    box('head',(0,.03,h-.14),(w*.48,.34,.25),STEEL)
    box('sensor',(0,.22,h-.12),(w*.36,.04,.07),OPTIC)
    for side in (-1,1):
        box('shoulder',(side*w*.68,0,1.56),(w*.38,.46,.34))
        box('hinge',(side*w*.68,0,1.26),(w*.25,.25,.20),JOINT)
        box('arm',(side*w*.68,.04+recoil,1.09),(w*.27,.32,.36),STEEL)
    if family=='guardian': box('shield',(-w*.65,.29,1.15),(.55,.17,.88))
    if family=='runner':
        box('antenna',(-.18,0,1.94),(.04,.05,.35),OPTIC)
        box('blade',(.42,.28,.95),(.07,.62,.17),col)
    if family in ('enforcer','brute'):
        for side in ((1,) if family=='enforcer' else (-1,1)):
            box('cannon',(side*w*.69,.39-recoil,1.11),(.23,.95,.23),STEEL)
            box('muzzle',(side*w*.69,.90-recoil,1.11),(.26,.10,.26),JOINT)
            if row==6: box('muzzle-light',(side*w*.69,1.05,1.11),(.20,.27,.20),(255,226,115))
    if boss:
        for side in (-1,1):
            box('siege-pod',(side*.98,-.15,1.93),(.56,.72,.56))
            for z in (1.8,2.0):box('siege-tube',(side*.98,.28,z),(.39,.40,.12),JOINT)
        box('core',(0,.41,1.42),(.32,.14,.32),OPTIC)
        box('crown',(0,-.1,2.4),(.42,.45,.32))
    if collapse:
        for p in parts:
            p['p']=(p['p'][0]*(1+collapse*.4),p['p'][1],p['p'][2]*(1-collapse*.76))
            p['s']=(p['s'][0],p['s'][1],p['s'][2]*(1-collapse*.6))
    return parts

def weapon(kind,frame=0):
    heavy=kind in ('gatling','bazooka','artillery'); length={'pistol':.7,'smg':1.05,'assault':1.4,'sniper':2.1}.get(kind,1.3)
    col=(82,110,126); parts=[]
    def box(n,p,s,c=col):parts.append(dict(name=n,p=p,s=s,c=c))
    recoil=-.12 if frame==3 else -.05 if frame==1 else 0
    box('receiver',(0,recoil,0),(.38 if heavy else .25,.65,.31))
    box('grip',(0,-.21,-.28),(.18,.23,.37),JOINT)
    box('magazine',(.0,.20,-.27),(.22,.26,.38),STEEL)
    box('stock',(0,-.59,-.06),(.28,.47,.21),STEEL)
    barrels=6 if kind=='gatling' else 3 if kind=='artillery' else 1
    for i in range(barrels):
        a=i*2*math.pi/barrels+frame*.18
        x=math.cos(a)*.19 if barrels>1 else 0;z=math.sin(a)*.19 if barrels>1 else 0
        d=.35 if kind=='bazooka' else .10
        box('barrel',(x,length*.5+recoil,z),(d,length,d),STEEL)
        box('bore',(x,length+recoil,z),(d*1.1,.08,d*1.1),JOINT)
        if frame==2:box('flash',(x,length+.15,z),(.18,.27,.18),(255,221,110))
    if kind=='sniper':box('scope',(0,-.05,.30),(.13,.62,.13),JOINT)
    if kind=='flame':box('tank',(-.31,-.1,-.01),(.28,.57,.4),(219,128,34))
    box('sight',(0,.18,.22),(.08,.12,.12),OPTIC)
    return parts
