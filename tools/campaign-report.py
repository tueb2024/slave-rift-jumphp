"""Summarize campaign-sim JSON or its optional checkpoint JSONL (stdlib only)."""
import argparse, collections, csv, json, statistics
from pathlib import Path
p=argparse.ArgumentParser(description=__doc__)
p.add_argument('inputs',nargs='+');p.add_argument('--csv',required=True);a=p.parse_args()
runs=[]
for name in a.inputs:
 text=Path(name).read_text()
 try:
  data=json.loads(text);runs.extend(data['runs'] if isinstance(data,dict) and 'runs'in data else [data])
 except json.JSONDecodeError:runs.extend(json.loads(line) for line in text.splitlines() if line.strip())
weapons=['COLT .45','DOUBLE COLT','MP44','DOUBLE MP44','BAR','GATLING','DOUBLE GATLING']
def main(s,r):return weapons[sum(s['level']>=x for x in r['milestones'])]
def secondary(s):return ';'.join(f'{k}:{rank}'for k,rank in [('flame',s['flameRank']),('rockets',s['rocketRank']),('pulse',max(1,s['pulseRank'])if s['pulseUnlocked']else 0)]if rank)
def mean(values):return round(statistics.mean(values),3)if values else None
groups=collections.defaultdict(list);flat=[]
for r in runs:
 r.setdefault('milestones',[4,8,12,16,20,24]) # Legacy checkpoint files precede explicit metadata.
 label=f"{r['xpModel']} / {','.join(map(str,r['milestones']))} / heal={r['healMissing']} / {r['profile']}"
 groups[label].append(r)
 for l in r['levels']:
  z=l['result'];b=l['start'];e=z['progression']
  flat.append(dict(model=r['xpModel'],milestones='/'.join(map(str,r['milestones'])),heal=r['healMissing'],profile=r['profile'],rootSeed=r['seed'],stage=l['stage'],mapSeed=l['seed'],surfaceRatio=l['geometry']['surfaceRatio'],mainRooms=l['geometry']['mainRooms'],horizontalScale=round(l['geometry']['horizontalScale'],3),startLevel=b['level'],endLevel=z['level'],startXpTotal=b['collectedXp'],endXpTotal=z['xpTotal'],startWeapon=main(b,r),endWeapon=z['weapon'],startSecondaries=secondary(b),endSecondaries=secondary(e),startHealth=b['health'],startMaxHealth=b['maxHealth'],endHealth=z['health'],endMaxHealth=z['maxHealth'],lowestHealthRatio=z['lowestHealthRatio'],duration=z['time'],kills=l['kills'],bossReached=z['bossReached'],bossDefeated=z['bossDefeated'],treasure=z['result']=='won',result=z['result']))
with open(a.csv,'w')as f:
 w=csv.DictWriter(f,fieldnames=list(flat[0]));w.writeheader();w.writerows(flat)
summary={}
for label,rs in groups.items():
 wins=[r for r in rs if r['state']=='won']
 g={'runs':len(rs),'victoryPercent':100*len(wins)/len(rs),'timeoutCount':sum(r['state']in ['play','upgrade']for r in rs),'winnerMeanEndHealthRatio':mean([r['levels'][-1]['result']['health']/r['levels'][-1]['result']['maxHealth']for r in wins]),'meanDuration':mean([sum(l['duration']for l in r['levels'])for r in rs]),'stages':{},'firstUnlocks':{}}
 for stage in range(1,5):
  ls=[r['levels'][stage-1]for r in rs if len(r['levels'])>=stage]
  g['stages'][stage]={'accessPercent':100*len(ls)/len(rs),'bossReached':sum(l['result']['bossReached']for l in ls),'bossDefeated':sum(l['result']['bossDefeated']for l in ls),'meanStartLevel':mean([l['start']['level']for l in ls]),'meanEndLevel':mean([l['result']['level']for l in ls]),'meanStartHealth':mean([l['start']['health']for l in ls]),'meanEndHealth':mean([l['result']['health']for l in ls]),'meanDuration':mean([l['duration']for l in ls]),'meanKills':mean([l['kills']for l in ls]),'bossWeapons':dict(collections.Counter(l['result']['checkpoints']['boss']['weapon']if l['result']['checkpoints']['boss']else 'NOT REACHED'for l in ls))}
 for weapon in weapons[1:]:
  hits=[]
  for r in rs:
   elapsed=0
   for l in r['levels']:
    event=next((e for e in l['result']['arsenalTimeline']if e['weapon']==weapon),None)
    if event:hits.append({'seed':r['seed'],'stage':l['stage'],'time':round(elapsed+event['time'],1)});break
    elapsed+=l['duration']
  g['firstUnlocks'][weapon]={'runs':len(hits),'stageCounts':dict(collections.Counter(h['stage']for h in hits)),'meanTime':mean([h['time']for h in hits]),'events':hits}
 summary[label]=g
print(json.dumps(summary,indent=2,ensure_ascii=False))
