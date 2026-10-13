import {createRequire} from 'node:module';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
const require = createRequire('/tmp/studyos-browser/package.json');
const {chromium} = require('playwright');
await fs.mkdir('browser-evidence',{recursive:true});
for (const variant of [
    {name:'chromium-desktop',viewport:{width:1440,height:1000}},
    {name:'chromium-mobile',viewport:{width:390,height:844},isMobile:true,hasTouch:true},
    {name:'edge-desktop',channel:'msedge',viewport:{width:1440,height:1000}},
]) {
    const browser = await chromium.launch({headless:true,channel:variant.channel});
    const context = await browser.newContext({viewport:variant.viewport,isMobile:variant.isMobile,hasTouch:variant.hasTouch});
    const page = await context.newPage();
    const errors=[];
    page.on('pageerror',e=>errors.push(String(e)));
    page.on('response',r=>{if(r.status()>=500)errors.push(`${r.status()} ${r.url()}`);});
    const checkWidth = async () => assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'horizontal page overflow');
    await page.goto('http://127.0.0.1:8000/courses/1');
    await page.getByRole('heading',{name:'Programa curricular'}).waitFor();
    assert.equal(await page.locator('.curriculum-theme').count(),9);
    assert.equal(await page.locator('.package-child-check').count(),33);
    assert(!(await page.locator('body').innerText()).includes('Histórico antigo'));
    await page.locator('.curriculum-theme summary').first().click();
    await checkWidth();
    await page.screenshot({path:`browser-evidence/${variant.name}-uc.png`,fullPage:true});
    const theme=page.locator('.curriculum-theme').first();
    await theme.locator('.package-child-check').first().uncheck();
    assert(await theme.locator('.package-theme-check').evaluate(e=>e.indeterminate),'partial theme must be indeterminate');
    await page.getByRole('button',{name:'Guardar matéria lecionada'}).click();
    await page.getByRole('status').getByText('Matéria lecionada atualizada.').waitFor();
    await page.locator('.curriculum-theme summary').first().click();
    await page.locator('.curriculum-row').first().getByRole('link',{name:'Treinar',exact:true}).click();
    await page.getByText('8 perguntas elegíveis.',{exact:true}).waitFor();
    await page.getByLabel('Número de perguntas').selectOption('5');
    await page.getByLabel('Dificuldade').selectOption('advanced');
    await page.getByText('0 perguntas elegíveis.',{exact:true}).waitFor();
    assert(!(await page.getByRole('button',{name:'Iniciar treino',exact:true}).isEnabled()));
    await page.getByLabel('Dificuldade').selectOption('intermediate');
    await page.getByText('8 perguntas elegíveis.',{exact:true}).waitFor();
    await checkWidth();
    const width=await page.locator('[data-topic-course="1"]').evaluate(e=>e.getBoundingClientRect().width);
    assert(width>250,'curriculum selector collapsed into narrow card');
    await page.screenshot({path:`browser-evidence/${variant.name}-configurator.png`,fullPage:true});
    await page.getByRole('button',{name:'Iniciar treino',exact:true}).click();
    await page.getByText('Treino em curso · Pergunta 1 de 5',{exact:true}).waitFor();
    await page.getByLabel('Resposta',{exact:true}).selectOption('verdadeiro');
    await page.getByRole('button',{name:'Responder e avançar'}).click();
    await page.getByText('Treino em curso · Pergunta 2 de 5',{exact:true}).waitFor();
    await page.getByRole('link',{name:'Sair e retomar depois'}).click();
    await page.getByRole('link',{name:'Retomar treino',exact:true}).first().click();
    await page.getByText('Treino em curso · Pergunta 2 de 5',{exact:true}).waitFor();
    for(let n=2;n<=5;n++) {
        await page.getByLabel('Resposta',{exact:true}).selectOption('verdadeiro');
        await page.getByRole('button',{name:n===5?'Terminar e ver resultados':'Responder e avançar'}).click();
    }
    await page.getByRole('heading',{name:'Resultados e correção'}).waitFor();
    await page.getByRole('heading',{name:'20,0 / 20'}).waitFor();
    await checkWidth();
    await page.screenshot({path:`browser-evidence/${variant.name}-result.png`,fullPage:true});
    await page.goto('http://127.0.0.1:8000/study/create?course_id=1&now=1');
    await page.getByRole('button',{name:'Toda a matéria lecionada'}).click();
    await page.getByRole('button',{name:'Iniciar estudo',exact:true}).click();
    await page.getByRole('heading',{name:'Sessão de estudo',exact:true}).waitFor();
    await page.getByRole('link',{name:'Abrir manual do subtema →'}).first().click();
    await page.getByRole('heading',{name:'Organizações',exact:true}).waitFor();
    await checkWidth();
    await page.goto('http://127.0.0.1:8000/study');
    await page.getByRole('heading',{name:'Continuar estudo'}).waitFor();
    await checkWidth();
    await page.screenshot({path:`browser-evidence/${variant.name}-study.png`,fullPage:true});
    assert.deepEqual(errors,[],'page errors or HTTP 500s');
    await browser.close();
    console.log(`${variant.name}: curriculum, partial marks, filters, sequential training, resume, result, study/manual and responsive layout passed`);
}
