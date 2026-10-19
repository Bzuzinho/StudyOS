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
    await page.getByRole('heading',{name:'Introdução à Gestão no ChatGPT'}).waitFor();
    const selectedTopic=page.locator('[data-assessment-topic][value="1.1"]');
    assert(await selectedTopic.isChecked(),'selected course topic must be preselected in ChatGPT training');
    await page.getByLabel('Número de perguntas').selectOption('5');
    await page.getByLabel('Grau de dificuldade').selectOption('progressiva');
    await page.getByRole('button',{name:'Gerar prompt',exact:true}).click();
    const generatedPrompt=await page.locator('#external-prompt').inputValue();
    assert(generatedPrompt.includes('studyos.external-assessment.v2'),'generated prompt must include the StudyOS import schema');
    assert(generatedPrompt.includes('1.1'),'generated prompt must include the selected curricular number');
    assert(generatedPrompt.includes('UMA pergunta de cada vez'),'generated prompt must enforce interactive training');
    await checkWidth();
    await page.screenshot({path:`browser-evidence/${variant.name}-chatgpt-training.png`,fullPage:true});
    const report={
        schema:'studyos.external-assessment.v2',
        assessment_id:'STUDYOS-BROWSER-'+variant.name.toUpperCase().replace(/[^A-Z0-9_-]/g,'-'),
        academic_code:'14020',
        assessed_on:'2026-10-19',
        activity_type:'practice',
        score_20:16,
        topics:[{number:'1.1',score_percent:80,evidence_count:3,feedback:'Boa compreensão; rever aplicação dos conceitos.'}],
        strengths:['Conceitos fundamentais'],
        difficulties:['Aplicação a casos'],
        conceptual_errors:[],
        recommendations:['Rever exemplos do subtema 1.1'],
        review_topics:['1.1'],
        mastered_topics:[]
    };
    await page.getByLabel('Relatório do ChatGPT').fill(JSON.stringify(report));
    await page.getByRole('button',{name:'Analisar relatório',exact:true}).click();
    await page.getByRole('heading',{name:'3. Pré-visualização da importação'}).waitFor();
    assert((await page.locator('#import-preview').innerText()).includes('Estado proposto após integrar o histórico'),'preview must show proposed mastery state');
    await page.getByRole('button',{name:'Confirmar e importar',exact:true}).click();
    await page.getByText('Relatório importado e estado de conhecimento atualizado. As notas oficiais não foram alteradas.',{exact:true}).waitFor();
    const importedAssessment=page.getByRole('article').filter({hasText:report.assessment_id});
    await importedAssessment.getByText('16,00/20',{exact:false}).waitFor();
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
