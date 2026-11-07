const{chromium}=require('playwright'),fs=require('fs'),assert=require('assert/strict');
(async()=>{
const [url,out,size='960']=process.argv.slice(2);fs.mkdirSync(out,{recursive:true});
const b=await chromium.launch({executablePath:process.env.CHROMIUM_PATH,headless:true,args:['--no-sandbox','--enable-unsafe-swiftshader','--autoplay-policy=no-user-gesture-required']});
let errors=[];const p=await b.newPage({viewport:{width:+size,height:Math.round(+size*9/16)}});p.on('pageerror',e=>errors.push(e.message));p.on('console',m=>{if(/CRASH|Fatal error|Warning:/.test(m.text()))errors.push(m.text())});
await p.goto(url);await p.waitForFunction(()=>typeof window.JumPHP?.php==='function',{timeout:120000});await p.waitForFunction(()=>document.getElementById('loader').style.display==='none',{timeout:120000});await p.locator('#overlay').click();await p.waitForTimeout(600);await p.addStyleTag({content:'#toolbar {visibility:hidden!important}'});
const src=fs.readFileSync(require('path').join(__dirname,'shotgun-visual-fixture.php'),'utf8').replace('<?php','');
await p.evaluate(src=>window.JumPHP.php(src),src);
const prefix="$scene=\\JumPHP\\Kernel\\Engine::get()->scenes->current();";
for(const name of ['pistol','dual_pistol','smg','dual_smg','machinegun','minigun','dual_minigun','shotgun']){
 const stage=['pistol','dual_pistol','smg','dual_smg','machinegun','minigun','dual_minigun'].indexOf(name);
 await p.evaluate(code=>window.JumPHP.php(code),prefix+`shotgunVisualFixture($scene,${name==='shotgun'?'true':'false'},${Math.max(0,stage)});`);await p.waitForTimeout(180);
 await p.locator('#canvas').screenshot({path:`${out}/${name}-${size}.png`});
 // Actual game canvas at the requested viewport; no sprite compositing.
}
for(const[pose,age]of[['ignition',.01],['fire',.04],['recoil',.12],['open',.30],['insert',.60],['close',.90],['settle',1.1]]){
 await p.evaluate(code=>window.JumPHP.php(code),prefix+`shotgunVisualFixture($scene,true,0,${age});`);await p.waitForTimeout(180);await p.locator('#canvas').screenshot({path:`${out}/shotgun-${pose}-${size}.png`});
}
await p.evaluate(code=>window.JumPHP.php(code),prefix+"file_put_contents('/tmp/art-check.json',json_encode(['gl'=>\\JumPHP\\Kernel\\Engine::get()->gl()->isAvailable(),'size'=>[\\JumPHP\\Kernel\\Engine::get()->sdl->getTexture('weapon_shotgun')['width'],\\JumPHP\\Kernel\\Engine::get()->sdl->getTexture('weapon_shotgun')['height']]]));");
const checks=await p.evaluate(()=>JSON.parse(window.JumPHP.FS.readFile('/tmp/art-check.json',{encoding:'utf8'})));assert.equal(checks.gl,true);fs.writeFileSync(`${out}/runtime.json`,JSON.stringify({checks,errors},null,2));assert.deepEqual(errors,[]);
await b.close();console.log('PASS runtime captures',url,checks);
})().catch(e=>{console.error(e);process.exit(1)});
