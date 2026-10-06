import test from 'node:test';
import assert from 'node:assert/strict';
import { chromium } from 'playwright';

test('isolated browser contexts support frames, typed input and cleanup', async () => {
  const browser=await chromium.launch({headless:true});
  try {
    const first=await browser.newContext({viewport:{width:1280,height:800}});
    const second=await browser.newContext({viewport:{width:1280,height:800}});
    const page=await first.newPage();
    await page.setContent('<input aria-label="Test input" style="position:absolute;left:10px;top:10px;width:300px;height:50px"><a id="launchapp" href="moodlemobile://token=test-fixture">Return</a>');
    await page.mouse.click(80,30);
    await page.keyboard.insertText('test input');
    assert.equal(await page.locator('input').inputValue(),'test input');
    await page.keyboard.press('Control+A');
    await page.keyboard.press('Backspace');
    assert.equal(await page.locator('input').inputValue(),'');
    const frame=await page.screenshot({type:'jpeg',quality:65});
    assert.equal(frame[0],0xff); assert.equal(frame[1],0xd8);
    await first.addCookies([{name:'isolated',value:'fixture',url:'https://example.test'}]);
    assert.equal((await second.cookies()).length,0);
    assert.equal(await page.locator('#launchapp').getAttribute('href'),'moodlemobile://token=test-fixture');
    await first.close();
    assert.equal(page.isClosed(),true);
    await second.close();
  } finally { await browser.close(); }
});
