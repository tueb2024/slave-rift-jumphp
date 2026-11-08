// Game-runtime VFX/reward fixtures. node tools/flame-review.cjs <url> <out> [after]
const {chromium}=require('playwright'),fs=require('fs'),assert=require('assert/strict');
(async()=>{
 const[url,out,after]=process.argv.slice(2);fs.mkdirSync(out,{recursive:true});
 const browser=await chromium.launch({executablePath:process.env.CHROMIUM_PATH,headless:true,args:['--no-sandbox','--enable-unsafe-swiftshader','--autoplay-policy=no-user-gesture-required']});
 try{
 const page=await browser.newPage({viewport:{width:960,height:540}}),errors=[];
 page.on('pageerror',e=>errors.push(e.message));page.on('console',m=>{if(/CRASH|Fatal error|Warning:/.test(m.text()))errors.push(m.text())});
 await page.goto(url);await page.waitForFunction(()=>document.getElementById('loader')?.style.display==='none',{timeout:120000});await page.locator('#overlay').click();
 await page.addStyleTag({content:'#toolbar {visibility:hidden!important}'});
 const prefix="$scene=\\JumPHP\\Kernel\\Engine::get()->scenes->current();";
 const php=code=>page.evaluate(code=>window.JumPHP.php(code),prefix+code);
 await php("$scene->enter(['gameMode'=>\\App\\Game\\GameMode::horde(42),'playing'=>true]);$run=(new \\ReflectionProperty($scene,'run'))->getValue($scene);$run->state='review';$run->enemies=[];$run->remaining=0;$run->x=0.;$run->z=-12.;$run->y=.75;$run->yaw=0.;$run->flameRank=1;$run->flameActive=1.;$run->primaryStage=2;");
 const frame=async(name,time)=>{
  await php(`$run=(new \\ReflectionProperty($scene,'run'))->getValue($scene);$run->time=${time};(new \\ReflectionMethod($run,'updateFlameSegments'))->invoke($run);`);
  await page.waitForTimeout(120);await page.locator('#canvas').screenshot({path:`${out}/${name}.png`});
 };
 await frame('flame-960',20.);
 await php("(new \\ReflectionProperty($scene,'pitch'))->setValue($scene,.20);");await frame('flame-pitch-960',20.);await php("(new \\ReflectionProperty($scene,'pitch'))->setValue($scene,0.);");
 await page.setViewportSize({width:320,height:180});
 for(let i=0;i<8;i++)await frame(`flame-frame-${i}-320`,20+i*.065);
 await php("$run=(new \\ReflectionProperty($scene,'run'))->getValue($scene);$run->z=18.;");await frame('flame-wall-320',20.);
 if(after){
  await php("$scene->enter(['gameMode'=>\\App\\Game\\GameMode::campaignStage(42,1),'playing'=>true]);$run=(new \\ReflectionProperty($scene,'run'))->getValue($scene);$run->state='review';$run->time=20.;$run->messageTime=0.;$run->enemies=[];$pack=$run->roomSupplies[0];$room=array_values(array_filter((new \\ReflectionProperty($scene,'assembledLevel'))->getValue($scene)->rooms,fn($r)=>$r['id']===$pack['roomId']))[0];$b=$room['bounds'];$run->x=($b['min'][0]+$b['max'][0])/2;$run->z=($b['min'][2]+$b['max'][2])/2-2.2;$run->y=$pack['y']+.45;$run->yaw=0.;");
  await page.waitForTimeout(160);await page.locator('#canvas').screenshot({path:`${out}/healing-room-320.png`});
  await php("$run=(new \\ReflectionProperty($scene,'run'))->getValue($scene);$run->state='upgrade';$run->choices=['FLAMETHROWER'];$run->chooseUpgrade(0);$run->state='upgrade';$run->choices=['MINI_ROCKETS'];$run->chooseUpgrade(0);$run->state='play';$run->remaining=0;$run->intermission=3600.;$run->messageTime=0.;(new \\ReflectionProperty($run,'flameCooldownClock'))->setValue($run,0.);(new \\ReflectionProperty($run,'rocketClock'))->setValue($run,0.);");
  await page.waitForTimeout(160);await page.locator('#canvas').screenshot({path:`${out}/duo-hud-320.png`});
  await php("$run=(new \\ReflectionProperty($scene,'run'))->getValue($scene);$run->state='play';$run->xp=$run->nextXp;(new \\ReflectionMethod($run,'checkLevel'))->invoke($run);file_put_contents('/tmp/duo-review.json',json_encode(['inventory'=>$run->secondaryIds(),'choices'=>$run->choices,'supplies'=>count($run->roomSupplies)]));");
  await page.waitForTimeout(160);await page.locator('#canvas').screenshot({path:`${out}/classic-upgrades-320.png`});
  const state=await page.evaluate(()=>JSON.parse(window.JumPHP.FS.readFile('/tmp/duo-review.json',{encoding:'utf8'})));assert.deepEqual(state.inventory,['flame','rockets']);assert.ok(state.choices.every(c=>['POWER','HASTE','MAGNET','VITALITY'].includes(c)));assert.ok(state.supplies>0);fs.writeFileSync(`${out}/state.json`,JSON.stringify(state,null,2));
 }
 assert.deepEqual(errors,[]);fs.writeFileSync(`${out}/console.json`,JSON.stringify(errors));console.log('PASS runtime VFX and review fixtures');
 }finally{await browser.close()}
})().catch(e=>{console.error(e);process.exit(1)});
