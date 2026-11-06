// Real SDL/WASM UI inputs. Death/victory/room fixtures isolate navigation from combat difficulty.
const puppeteer = require('puppeteer');
const assert = require('node:assert/strict');
const fs=require('node:fs');
const [url = 'http://127.0.0.1:18999/', out = '/out'] = process.argv.slice(2);
const prefix = "$rcScene=\\JumPHP\\Kernel\\Engine::get()->scenes->current(); $rcRun=(new \\ReflectionProperty($rcScene,'run'))->getValue($rcScene); $rcMenu=(new \\ReflectionProperty($rcScene,'menu'))->getValue($rcScene); ";
const delay = ms => new Promise(r => setTimeout(r,ms));
(async () => {
 const browser = await puppeteer.launch({headless:true,args:['--no-sandbox','--use-gl=swiftshader','--enable-unsafe-swiftshader','--autoplay-policy=user-gesture-required']});
 const errors=[];
 try {
  for (const mobile of [false,true]) {
   const page=await browser.newPage(), name=mobile?'mobile':'desktop';
   page.on('pageerror',e=>errors.push(name+': '+e.message));
   page.on('console',m=>{if(/CRASH|BOOT FAILED|Fatal error|Uncaught|Warning:/.test(m.text())) errors.push(name+': '+m.text());});
   await page.setViewport({width:mobile?844:960,height:mobile?390:540,isMobile:mobile,hasTouch:mobile,deviceScaleFactor:mobile?2:1});
   await page.goto(url,{waitUntil:'load'});
   await page.waitForFunction(()=>document.getElementById('loader')?.style.display==='none'||document.getElementById('loader')?.classList.contains('error'),{timeout:60000});
   assert.equal(await page.$eval('#loader',e=>e.classList.contains('error')),false);
   if(mobile) await page.touchscreen.tap(422,40); else await page.mouse.click(480,40);
   await page.waitForFunction(()=>typeof window.JumPHP?.php==='function');
   const php=code=>page.evaluate(code=>window.JumPHP.php(code),prefix+code);
   const state=async()=>{
    await php("file_put_contents('/tmp/rc-state.json',json_encode(['state'=>$rcRun?->state,'health'=>$rcRun?->health,'seed'=>$rcRun?->mode->seed,'mode'=>$rcRun?->mode->kind,'tier'=>$rcRun?->mode->difficultyTier,'objective'=>$rcRun?->objective?->state,'screen'=>$rcMenu->screen,'shotgunShots'=>$rcRun?->shotgunSerial,'bossHp'=>$rcRun?->boss?->health,'gates'=>$rcRun?->encounters?->gates,'bossShots'=>$rcRun?->bossShots,'bossProjectiles'=>count($rcRun?->bossProjectiles??[]),'secondaries'=>$rcRun?->secondaryInventory(),'mouse'=>[\\JumPHP\\Kernel\\Engine::get()->input->getMouse()->x,\\JumPHP\\Kernel\\Engine::get()->input->getMouse()->y]]));");
    return page.evaluate(()=>JSON.parse(window.JumPHP.FS.readFile('/tmp/rc-state.json',{encoding:'utf8'})));
   };
   const tap=async(x,y)=>{
    const p=await page.$eval('#canvas',(e,v)=>{const b=e.getBoundingClientRect(),s=Math.min(b.width/320,b.height/180);return[b.x+(b.width-320*s)/2+v[0]*s,b.y+(b.height-180*s)/2+v[1]*s];},[x,y]);
    if(mobile) await page.touchscreen.tap(...p); else await page.mouse.click(...p);
    await delay(450);
   };
   const expect=async(key,value)=>{const s=await state();assert.equal(s[key],value,`${name}: ${key}: ${JSON.stringify(s)}`);};
   try {
    await expect('screen','main'); await expect('state',null);
    await page.screenshot({path:`${out}/${name}-main.png`});
    await tap(160,60); await expect('mode','normal'); await expect('screen','play');
    const first=(await state()).seed;
    assert.equal((await state()).gates.filter(g=>!g.open).length,3,'Normal procedural encounters must start gated');
    await php("$g=$rcRun->encounters->gates[0];[$x,$y,$z]=$g['position'];if(!$rcRun->collides($x,$y+.7,$z,.28,.7))throw new \\RuntimeException('Gate must block actors');$rcRun->x=$x-$g['normal'][0]*2.2;$rcRun->y=$y+.7;$rcRun->z=$z-$g['normal'][2]*2.2;$rcRun->yaw=atan2($g['normal'][0],$g['normal'][2]);");
    await delay(150);await page.screenshot({path:`${out}/${name}-gate-closed.png`});
    // Verify all packaged PCM paths after the first user gesture, including reserved bass channels.
    await php("$rcMusic=(new \\ReflectionProperty($rcScene,'music'))->getValue($rcScene); $rcMusic->register(); $rcSampler=(new \\ReflectionProperty($rcMusic,'sampler'))->getValue($rcMusic); $rcMusic->playSteps([0],1); file_put_contents('/tmp/music-state.json',json_encode(['notes'=>count((new \\ReflectionProperty($rcSampler,'chunks'))->getValue($rcSampler)),'files'=>count(glob(JUMPHP_ROOT.'/assets/audio/*.wav')),'bass'=>Mix_Playing(0)||Mix_Playing(1)]));");
    const musicState=await page.evaluate(()=>JSON.parse(window.JumPHP.FS.readFile('/tmp/music-state.json',{encoding:'utf8'})));
    assert.equal(musicState.files,53); assert.equal(musicState.notes,48); assert.equal(musicState.bass,true);
    const seedStorageBefore=await page.evaluate(()=>({local:JSON.stringify(localStorage),session:JSON.stringify(sessionStorage),cookies:document.cookie}));
    await tap(298,10); await expect('screen','pause');
    await tap(160,71); await expect('screen','play');
    await php("$rcRun->state='dead';"); await delay(100);
    await page.screenshot({path:`${out}/${name}-dead.png`});
    await tap(160,90); await expect('seed',first); await expect('state','play');
    await php("$rcRun->state='dead';"); await delay(100);
    await tap(160,118); assert.notEqual((await state()).seed,first,'new random map reused seed');
    await php("$rcRun->state='won';"); await delay(100);
    await page.screenshot({path:`${out}/${name}-won.png`});
    await tap(160,146); await expect('screen','main'); await expect('state',null);
    assert.deepEqual(await page.evaluate(()=>({local:JSON.stringify(localStorage),session:JSON.stringify(sessionStorage),cookies:document.cookie})),seedStorageBefore,'normal runs must not persist seeds in browser storage');
    await tap(160,88); await expect('screen','daily');
    await tap(160,58); await expect('mode','daily'); await expect('tier',1);
    const dailySeed=(await state()).seed;
    await php("$rcRun->state='dead';");await delay(100);
    await tap(160,90);await expect('seed',dailySeed);await expect('tier',1);
    for(let stage=1;stage<=4;stage++){
     await expect('tier',stage);
     await php("$rcRun->state='won';");await delay(100);
     await expect('screen',stage===4?'won':'level_complete');
     await page.screenshot({path:`${out}/${name}-daily-map-${stage}.png`});
     if(stage<4){await tap(160,90);await expect('state','play');}
    }
    await tap(160,118);await expect('screen','main');
    await tap(160,116); await expect('mode','horde'); await expect('objective',null);
    await page.screenshot({path:`${out}/${name}-horde.png`});
    const performance=[];
    for(const population of [25,50,100,200]){
     await php(`$rcRun->enemies=[];$rcRun->boss=null;$rcRun->remaining=0;$rcRun->intermission=3600;$rcRun->health=100000;$rcRun->x=0;$rcRun->z=-18;$rcRun->y=.75;$rcRun->yaw=0;
      $spawn=new \\ReflectionMethod($rcRun,'spawnEnemyAt');for($i=0;$i<${population};$i++)$spawn->invoke($rcRun,-15.+($i%15)*2,.55,-12.+intdiv($i,15)*2,['runner','guardian','enforcer','brute'][$i%4]);
      foreach($rcRun->enemies as &$e){$e['speed']=0.;$e['health']=100000;$e['maxHealth']=100000;}unset($e);
      (new \\ReflectionProperty($rcScene,'recentFrames'))->setValue($rcScene,[]);(new \\ReflectionProperty($rcScene,'simulationTimes'))->setValue($rcScene,[]);`);
     await delay(2500);
     await php(`$frames=(new \\ReflectionProperty($rcScene,'recentFrames'))->getValue($rcScene);$cpu=(new \\ReflectionProperty($rcScene,'simulationTimes'))->getValue($rcScene);sort($frames);$world=(new \\ReflectionProperty($rcScene,'world'))->getValue($rcScene);
      file_put_contents('/tmp/mecha-perf.json',json_encode(['population'=>count($rcRun->enemies),'visible'=>$world->visibleEnemies,'webgl'=>\\JumPHP\\Kernel\\Engine::get()->gl()->isAvailable(),'samples'=>count($frames),'meanFrameMs'=>array_sum($frames)/max(1,count($frames)),'p95FrameMs'=>$frames[(int)floor((count($frames)-1)*.95)]??null,'meanSimulationPerRenderFrameMs'=>array_sum($cpu)/max(1,count($cpu))]));`);
     const metrics=await page.evaluate(()=>JSON.parse(window.JumPHP.FS.readFile('/tmp/mecha-perf.json',{encoding:'utf8'})));
     assert.equal(metrics.population,population);assert.equal(metrics.webgl,true);performance.push(metrics);
     await page.screenshot({path:`${out}/${name}-mechas-${population}.png`});
    }
    fs.writeFileSync(`${out}/${name}-webgl-performance.json`,JSON.stringify({scope:'Headless Chromium SwiftShader; rendered frame time includes CPU, browser and software GPU. Not physical phone performance.',results:performance},null,2));
    await php("$rcRun->enemies=[];$rcRun->health=100;$rcRun->intermission=0;");
    await php("$rcRun->state='dead';"); await delay(100);
    await tap(160,90); await expect('mode','horde'); await expect('state','play');
    await php("$rcRun->state='dead';"); await delay(100);
    await tap(160,118); await expect('screen','main');
    await tap(160,144); await expect('screen','controls');
    await tap(160,152); await expect('screen','main');
    if(!mobile){await page.keyboard.press('Enter');await delay(450);}else await tap(160,60);
    await expect('mode','normal');
    await php("$rcRun->state='upgrade';$rcRun->choices=['FLAMETHROWER'];$rcRun->chooseUpgrade(0);");
    await delay(150); assert.equal((await state()).secondaries.length,1);
    await php("$rcRun->state='upgrade';$rcRun->choices=['EMP'];$rcRun->chooseUpgrade(0);$rcRun->empFx=.65;");
    await delay(100); assert.equal((await state()).secondaries[0].id,'emp');
    await page.screenshot({path:`${out}/${name}-secondary-arsenal.png`});
    // Enter an upgrade fixture and select its second row by a real pointer/tap.
    await php("$rcRun->state='upgrade'; $rcRun->choices=['POWER','MAGNET','VITALITY'];");
    await delay(100); await tap(160,100); await expect('state','play');
    await php("$b=$rcRun->objective->finalRoomBounds; $rcRun->time=20; $rcRun->x=($b['min'][0]+$b['max'][0])/2; $rcRun->z=($b['min'][2]+$b['max'][2])/2; $rcRun->y=$rcRun->bossPosition['y']-0.5; $rcRun->yaw=atan2($rcRun->bossPosition['x']-$rcRun->x,$rcRun->bossPosition['z']-$rcRun->z); $rcRun->enemies=[]; $rcRun->remaining=0; $rcRun->objective->state=\\App\\Game\\TreasureDirector::BOSS_READY;");
    await delay(200); await expect('objective','boss');
    await page.screenshot({path:`${out}/${name}-boss-orbs.png`});
    await php("foreach($rcRun->boss->orbs as $i=>$_) $rcRun->boss->damageOrb($i,10000);");
    await delay(100); await page.screenshot({path:`${out}/${name}-boss-vulnerable.png`});
    await php("$b=$rcRun->bossPosition;$p=$rcRun->objective->treasureAnchor;$d=max(.001,hypot($b['x']-$p['x'],$b['z']-$p['z']));$rcRun->x=$b['x']-($b['x']-$p['x'])/$d*5;$rcRun->z=$b['z']-($b['z']-$p['z'])/$d*5;$rcRun->yaw=atan2($b['x']-$rcRun->x,$b['z']-$rcRun->z);$rcRun->shotgunReload=0;");
    const beforeShot=(await state()).bossHp;
    await delay(600);assert.equal((await state()).bossHp,beforeShot,'Rhythmic primary must not damage core');
    if(mobile)await tap(280,137);else{await page.keyboard.down('f');await delay(180);await page.keyboard.up('f');await delay(100);}
    assert.ok((await state()).shotgunShots>0,`${name}: intentional shotgun input was ignored`);
    assert.ok((await state()).bossHp<beforeShot,`${name}: shotgun did not damage exposed core`);
    await page.screenshot({path:`${out}/${name}-shotgun-manual.png`});
    await php("(new \\ReflectionMethod($rcRun,'launchBossProjectiles'))->invoke($rcRun,['x'=>$rcRun->x,'y'=>$rcRun->y,'z'=>$rcRun->z]);");
    await delay(70);assert.ok((await state()).bossShots>0,`${name}: boss projectile pipeline did not launch`);
    await page.screenshot({path:`${out}/${name}-boss-projectiles.png`});
    await php("$rcRun->boss->damage(10000,'shotgun');"); await delay(100); await expect('objective','treasure_ready');
    await page.screenshot({path:`${out}/${name}-treasure.png`});
    await php("$rcRun->x=$rcRun->objective->treasureAnchor['x'];$rcRun->z=$rcRun->objective->treasureAnchor['z'];");
    await delay(100); await expect('state','won');
    await tap(160,90); await expect('state','play');
   }catch(e){await page.screenshot({path:`${out}/${name}-failure.png`});console.error(await state());throw e;}
   await page.close();
  }
  assert.deepEqual(errors,[],errors.join('\n'));
  console.log('PASS: desktop + mobile/HiDPI menus, normal replay/new seed, one daily campaign, maps 1..4, horde, controls, upgrades, pause, boss/treasure/victory fixtures.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
