(() => {
const root=document.getElementById('external-prompt-builder');
if(!root)return;
const output=document.getElementById('external-prompt');
const status=document.getElementById('external-prompt-status');
const select=()=>[...root.querySelectorAll('[data-assessment-topic]:checked')].map(e=>({number:e.value,title:e.dataset.topicTitle}));
document.getElementById('generate-external-prompt').addEventListener('click',()=>{
  const topics=select();
  if(!topics.length){status.textContent='Seleciona pelo menos um subtema.';return;}
  const id='STUDYOS-'+Date.now()+'-'+Math.random().toString(36).slice(2,8);
  const count=document.getElementById('external-question-count').value;
  const mode=document.getElementById('external-mode').value;
  const difficulty=document.getElementById('external-difficulty').value;
  const names=topics.map(t=>t.number+' — '+t.title).join('\n');
  const sample={
    schema:'studyos.external-assessment.v1',
    assessment_id:id,
    academic_code:root.dataset.code,
    assessed_on:new Date().toLocaleDateString('en-CA'),
    score_20:0,
    topics:topics.map(t=>({number:t.number,score_percent:0,feedback:'diagnóstico fundamentado nos erros e acertos'})),
    strengths:['competências demonstradas'],
    difficulties:['dificuldades identificadas'],
    next_steps:['ação concreta de estudo ou revisão']
  };
  const common = ['És o tutor académico do meu projeto da UC '+root.dataset.course+'.', 'Os subtemas selecionados no StudyOS são:', names, '', 'Respeita o programa da UC e os materiais deste projeto. Indica quando não tiveres evidência ou fontes suficientes.'];
  if (mode === 'study') {
    output.value=[...common, 'Prepara uma sessão de estudo aprofundado e orientado, explicando os conceitos, modelos e autores relevantes, com exemplos resolvidos, esquemas textuais rigorosos e ligações entre subtemas. Verifica periodicamente a compreensão, mas não atribuas uma nota sem avaliação efetiva.', 'No final indica os subtemas abordados, o que ficou por aprofundar e os próximos passos. Quando houver avaliação suficiente, gera o relatório de conhecimento através de um pedido específico posterior.'].join('\n');
  } else if (mode === 'status') {
    output.value=[...common, 'Analisa apenas os resultados de avaliações realmente realizadas e evidências disponíveis neste projeto. Não infiras competências pelo simples facto de termos conversado sobre um tema. Se não houver evidência avaliativa suficiente, explica a limitação em vez de inventar notas.', 'Gera um relatório de conhecimento atual com classificação global em 0-20 quando sustentada e desempenho percentual por cada subtema selecionado. Quando não houver evidência, usa score_percent 0 e escreve explicitamente no feedback que significa ausência de evidência, não incapacidade. Não substitui uma avaliação oficial.', 'Termina com um único bloco JSON válido, sem comentários, com esta estrutura, preservando schema, academic_code, assessment_id e os números dos tópicos:', JSON.stringify(sample,null,2)].join('\n');
  } else {output.value=[
    'És o avaliador académico do meu projeto da UC '+root.dataset.course+'.',
    'Faz um treino interativo de '+count+' perguntas com dificuldade '+difficulty+'.',
    'Limita as perguntas aos seguintes subtemas confirmados no StudyOS:',
    names,
    '',
    'Apresenta UMA pergunta de cada vez e aguarda a minha resposta. Aceita resoluções manuscritas em fotografias, texto ou fórmulas; se a leitura estiver ambígua pede confirmação. Não inventes passos nem consideres correta uma resposta ilegível.',
    'Corrige e explica cada resposta, mostrando os passos matemáticos e assinalando erros. Faz perguntas suficientemente variadas para avaliar compreensão e não só memorização. Aplica cotações consistentes, incluindo classificação parcial quando justificada.',
    'No final apresenta a classificação em 0 a 20, resultados em percentagem por subtema, forças, dificuldades e recomendações. Distingue claramente ausência de evidência de erro. Não atribuas domínio forte sem evidência suficiente.',
    '',
    'OBRIGATÓRIO PARA IMPORTAÇÃO NO STUDYOS: termina a resposta com UM ÚNICO bloco de código JSON válido, sem comentários, sem Markdown no interior e SEM campos adicionais, seguindo exatamente esta estrutura:',
    JSON.stringify(sample,null,2),
    'Preserva os valores schema, assessment_id, academic_code e os números dos subtemas sem alterações. Usa assessed_on no formato YYYY-MM-DD (data efetiva da avaliação). Preenche score_20 entre 0 e 20 e score_percent entre 0 e 100. Todos os tópicos selecionados devem estar presentes uma única vez. Os arrays strengths, difficulties e next_steps devem ter textos concretos, não placeholders.',
    'Não uses este formato para substituir avaliações oficiais da universidade.'
  ].join('\n');}
  status.textContent='Prompt preparada para '+topics.length+' subtema(s). Copia-a para o projeto ChatGPT desta UC.';
});
document.getElementById('copy-external-prompt').addEventListener('click',async()=>{
 if(!output.value){status.textContent='Gera primeiro a prompt.';return;}
 try{await navigator.clipboard.writeText(output.value);status.textContent='Prompt copiada. Cola-a no projeto ChatGPT da UC.';}
 catch(e){output.focus();output.select();status.textContent='Seleciona e copia o texto manualmente (Ctrl+C).';}
});
})();
// Clipboard permission is triggered by a genuine user click; window opens synchronously to avoid popup blockers.
document.getElementById('open-chatgpt-project')?.addEventListener('click',()=>{
 if(!output.value){status.textContent='Gera primeiro a prompt.';return;}
 const url=document.getElementById('open-chatgpt-project').dataset.projectUrl;
 const tab=window.open(url,'_blank','noopener,noreferrer');
 navigator.clipboard.writeText(output.value).then(()=>{
   status.textContent='Projeto aberto e prompt copiada. Cola-a no ChatGPT com Ctrl+V.';
 }).catch(()=>{
   output.focus();output.select();status.textContent='Projeto aberto. Copia o texto selecionado com Ctrl+C antes de colar no ChatGPT.';
 });
 if(!tab)status.textContent='Permite pop-ups para abrir o projeto, ou utiliza o endereço guardado. A prompt continua disponível para copiar.';
});
