// Run in the same Puppeteer image as pinned JumPHP's webshot command.
// UI fixtures force death/victory to exercise real taps; combat is tested by ReleaseFlowTest.
const puppeteer = require('puppeteer');
const assert = require('node:assert/strict');
const [url = 'http://127.0.0.1:18999/', out = '/out'] = process.argv.slice(2);
const prefix = "$rcScene = \\JumPHP\\Kernel\\Engine::get()->scenes->current(); $rcRun = (new \\ReflectionProperty($rcScene, 'run'))->getValue($rcScene); ";
(async () => {
  const browser = await puppeteer.launch({headless: true, args: ['--no-sandbox', '--use-gl=swiftshader', '--enable-unsafe-swiftshader', '--autoplay-policy=no-user-gesture-required']});
  const errors = [];
  try {
    for (const mobile of [false, true]) {
      const page = await browser.newPage();
      const name = mobile ? 'mobile' : 'desktop';
      await page.setViewport({width: mobile ? 844 : 960, height: mobile ? 390 : 540, isMobile: mobile, hasTouch: mobile, deviceScaleFactor: 1});
      page.on('pageerror', e => errors.push(`${name}: ${e.message}`));
      page.on('console', m => { if (/CRASH|BOOT FAILED|Fatal error|Uncaught|Warning:/.test(m.text())) errors.push(`${name}: ${m.text()}`); });
      await page.goto(url, {waitUntil: 'load'});
      await page.waitForFunction(() => document.getElementById('loader')?.style.display === 'none' || document.getElementById('loader')?.classList.contains('error'), {timeout: 60000});
      assert.equal(await page.$eval('#loader', e => e.classList.contains('error')), false, `${name}: engine boot failed`);
      // The engine's audio/play overlay, if present; outside the game's JOUER zone.
      if (mobile) await page.touchscreen.tap(422, 40); else await page.mouse.click(480, 40);
      await page.waitForFunction(() => typeof window.JumPHP?.php === 'function');
      const php = code => page.evaluate(code => window.JumPHP.php(code), prefix + code);
      const state = async () => {
        await php("file_put_contents('/tmp/rc-state.json', json_encode(['state'=>$rcRun->state,'health'=>$rcRun->health,'time'=>$rcRun->time,'welcome'=>(new \\ReflectionProperty($rcScene,'welcome'))->getValue($rcScene),'paused'=>(new \\ReflectionProperty($rcScene,'paused'))->getValue($rcScene)]));");
        return page.evaluate(() => JSON.parse(window.JumPHP.FS.readFile('/tmp/rc-state.json', {encoding:'utf8'})));
      };
      const tap = async (x, y) => {
        const p = await page.$eval('#canvas', (e, v) => {
          const b = e.getBoundingClientRect(), s = Math.min(b.width / 320, b.height / 180);
          return [b.x + (b.width - 320*s)/2 + v[0]*s, b.y + (b.height - 180*s)/2 + v[1]*s];
        }, [x, y]);
        if (mobile) await page.touchscreen.tap(...p); else await page.mouse.click(...p);
        await new Promise(r => setTimeout(r, 200));
      };
      assert.equal((await state()).welcome, true);
      await page.screenshot({path: `${out}/${name}-welcome.png`});
      await tap(160, 92);
      assert.equal((await state()).welcome, false, `${name}: JOUER did not start`);
      await tap(298, 10);
      assert.equal((await state()).paused, true);
      await tap(160, 78);
      assert.equal((await state()).paused, false);
      for (const terminal of ['dead', 'won']) {
        await php(`$rcRun->state='${terminal}';`);
        await new Promise(r => setTimeout(r, 100));
        await page.screenshot({path: `${out}/${name}-${terminal}.png`});
        await tap(160, 118);
        const fresh = await state();
        assert.equal(fresh.state, 'play', `${name}: restart from ${terminal} failed`);
        assert.equal(fresh.health, 100);
        assert.equal(fresh.welcome, false);
      }
      if (!mobile) {
        await php("$rcRun->state='dead';");
        await page.keyboard.press('Enter');
        await new Promise(r => setTimeout(r, 100));
        assert.equal((await state()).state, 'play');
      }
      // Capture the actual boss/orb/vulnerability/treasure renderer in the generated terminal room.
      await php("$b=$rcRun->objective->finalRoomBounds; $rcRun->x=($b['min'][0]+$b['max'][0])/2; $rcRun->z=($b['min'][2]+$b['max'][2])/2; $rcRun->y=$rcRun->bossPosition['y']-0.5; $rcRun->yaw=atan2($rcRun->bossPosition['x']-$rcRun->x,$rcRun->bossPosition['z']-$rcRun->z); $rcRun->enemies=[]; $rcRun->remaining=0; $rcRun->objective->state=\\App\\Game\\TreasureDirector::BOSS_READY;");
      await new Promise(r => setTimeout(r, 200));
      await page.screenshot({path: `${out}/${name}-boss-orbs.png`});
      await php("foreach ($rcRun->boss->orbs as $i=>$_) $rcRun->boss->damageOrb($i,10000);");
      await new Promise(r => setTimeout(r, 100));
      await page.screenshot({path: `${out}/${name}-boss-vulnerable.png`});
      await php("$rcRun->boss->damage(10000);");
      await new Promise(r => setTimeout(r, 100));
      await page.screenshot({path: `${out}/${name}-treasure.png`});
      await page.close();
    }
    assert.deepEqual(errors, [], errors.join('\n'));
    console.log('PASS: desktop clicks/keyboard and mobile taps: welcome, pause, death restart, victory restart; boss render fixtures captured.');
  } finally { await browser.close(); }
})().catch(e => {console.error(e); process.exit(1);});
