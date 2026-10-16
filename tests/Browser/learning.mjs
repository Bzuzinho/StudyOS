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
    try {
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
    await page.getByRole('heading',{name:'Praticar Introdução à Gestão no ChatGPT'}).waitFor();
    const selectedTopic=page.locator('[data-assessment-topic][value="1.1"]');
    assert(await selectedTopic.isChecked(),'selected course topic must be preselected in ChatGPT training');
    await page.getByLabel('Número de perguntas').selectOption('5');
    await page.getByLabel('Grau de dificuldade').selectOption('progressiva');
    await page.getByRole('button',{name:'Gerar prompt',exact:true}).click();
    const generatedPrompt=await page.locator('#external-prompt').inputValue();
    assert(generatedPrompt.includes('studyos.external-assessment.v1'),'generated prompt must include the StudyOS import schema');
    assert(generatedPrompt.includes('1.1'),'generated prompt must include the selected curricular number');
    assert(generatedPrompt.includes('UMA pergunta de cada vez'),'generated prompt must enforce interactive training');
    await checkWidth();
    await page.screenshot({path:`browser-evidence/${variant.name}-chatgpt-training.png`,fullPage:true});
    const report={
        schema:'studyos.external-assessment.v1',
        assessment_id:'STUDYOS-BROWSER-001',
        academic_code:'14020',
        assessed_on:'2026-10-16',
        score_20:16,
        topics:[{number:'1.1',score_percent:80,feedback:'Boa compreensão; rever aplicação dos conceitos.'}],
        strengths:['Conceitos fundamentais'],
        difficulties:['Aplicação a casos'],
        next_steps:['Rever exemplos do subtema 1.1']
    };
    await page.getByLabel('Relatório JSON devolvido pelo ChatGPT').fill(JSON.stringify(report));
    await page.getByRole('button',{name:'Importar avaliação',exact:true}).click();
    await page.getByText('Avaliação externa importada. O histórico oficial e as notas universitárias não foram alterados.',{exact:true}).waitFor();
    await page.getByText('16,00/20',{exact:false}).waitFor();
    await checkWidth();
    await page.screenshot({path:`browser-evidence/${variant.name}-chatgpt-result.png`,fullPage:true});
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
    } catch (error) {
        console.error('PAGE STATE:',await page.locator('body').innerText());
        await page.screenshot({path:`browser-evidence/${variant.name}-failure.png`,fullPage:true});
        throw error;
    }
    await browser.close();
    console.log(`${variant.name}: curriculum, partial marks, ChatGPT training/import, study/manual and responsive layout passed`);
}
