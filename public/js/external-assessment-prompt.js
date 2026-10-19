(() => {
const root=document.getElementById('external-prompt-builder');
if(!root)return;
const output=document.getElementById('external-prompt');
const status=document.getElementById('external-prompt-status');
const select=()=>[...root.querySelectorAll('[data-assessment-topic]:checked')].map(e=>({number:e.value,title:e.dataset.topicTitle}));
const buildSample=(topics,id,mode)=>({
  schema:'studyos.external-assessment.v2',
  assessment_id:id,
  academic_code:root.dataset.code,
  assessed_on:new Date().toLocaleDateString('en-CA'),
  activity_type:mode==='status'?'diagnostic':'practice',
  score_20:mode==='status'?null:0,
  topics:topics.map(t=>({number:t.number,score_percent:mode==='status'?null:0,evidence_count:mode==='status'?0:1,feedback:'diagnóstico fundamentado nas evidências disponíveis'})),
  strengths:['competências demonstradas'],
  difficulties:['dificuldades identificadas'],
  conceptual_errors:['erros conceptuais recorrentes, quando existirem'],
  recommendations:['ação concreta de estudo ou revisão'],
  review_topics:['números dos subtemas que necessitam de revisão'],
  mastered_topics:['números dos subtemas com domínio demonstrado']
});

document.getElementById('generate-external-prompt').addEventListener('click',()=>{
  const topics=select();
  if(!topics.length){status.textContent='Seleciona pelo menos um subtema.';return;}
  const id='STUDYOS-'+Date.now()+'-'+Math.random().toString(36).slice(2,8);
  const count=document.getElementById('external-question-count').value;
  const mode=document.getElementById('external-mode').value;
  const difficulty=document.getElementById('external-difficulty').value;
  const names=topics.map(t=>t.number+' — '+t.title).join('\n');
  const sample=buildSample(topics,id,mode);
  const common=[
    'És o tutor académico do meu projeto da UC '+root.dataset.course+' ('+root.dataset.code+').',
    'Os subtemas selecionados no StudyOS são:',
    names,
    '',
    'Respeita o programa da UC e os materiais deste projeto. Não inventes resultados, classificações ou evidências. Distingue sempre matéria abordada de conhecimento efetivamente demonstrado.'
  ];

  if(mode==='study'){
    output.value=[
      ...common,
      'Prepara uma sessão de estudo ou revisão aprofundada e orientada, explicando conceitos, modelos, autores e aplicações práticas relevantes. Usa exemplos resolvidos e verifica a compreensão durante a sessão.',
      'Considera as dificuldades já identificadas no projeto quando existirem, mas não atribuas uma classificação apenas porque estudámos a matéria.',
      'No final resume os subtemas trabalhados, dúvidas que permaneceram e próximos passos. Só gera um relatório de conhecimento quando houver evidência avaliativa suficiente.'
    ].join('\n');
  }else if(mode==='status'){
    output.value=[
      ...common,
      'Faz um diagnóstico do estado de conhecimento usando APENAS avaliações, exercícios corrigidos e outras evidências efetivas existentes neste projeto. Conversas de estudo, leitura de matéria ou simples exposição ao conteúdo não contam como domínio demonstrado.',
      'Para cada subtema indica quantas evidências concretas sustentam o diagnóstico em evidence_count. Quando não existir evidência suficiente para atribuir uma percentagem, usa score_percent=null e evidence_count=0. Nunca uses 0% para representar ausência de evidência.',
      'score_20 deve ser null quando não existir uma classificação global sustentada. review_topics e mastered_topics devem usar apenas os números canónicos dos subtemas listados acima.',
      'Termina com UM ÚNICO bloco JSON válido, sem comentários nem texto dentro do bloco, seguindo exatamente esta estrutura:',
      JSON.stringify(sample,null,2),
      'Preserva schema, assessment_id, academic_code e os números dos subtemas. assessed_on deve usar YYYY-MM-DD.'
    ].join('\n');
  }else{
    output.value=[
      'És o avaliador académico do meu projeto da UC '+root.dataset.course+' ('+root.dataset.code+').',
      'Faz um treino interativo de '+count+' perguntas com dificuldade '+difficulty+'.',
      'Limita as perguntas aos seguintes subtemas confirmados no StudyOS:',
      names,
      '',
      'Apresenta UMA pergunta de cada vez e aguarda a minha resposta. Aceita resoluções manuscritas em fotografias, texto ou fórmulas; se a leitura estiver ambígua pede confirmação. Não inventes passos nem consideres correta uma resposta ilegível.',
      'Corrige e explica cada resposta. Usa perguntas suficientemente variadas para avaliar compreensão e não apenas memorização. Aplica cotações consistentes e classificação parcial quando justificada.',
      'No final apresenta classificação global em 0-20, desempenho percentual por subtema, pontos fortes, dificuldades, erros conceptuais e recomendações. evidence_count deve indicar quantas respostas/itens corrigidos sustentam o resultado de cada subtema.',
      'Se um subtema selecionado não tiver sido efetivamente avaliado, usa score_percent=null e evidence_count=0. Ausência de evidência nunca equivale a 0% de conhecimento.',
      '',
      'OBRIGATÓRIO PARA IMPORTAÇÃO NO STUDYOS: termina com UM ÚNICO bloco de código JSON válido, sem comentários, seguindo exatamente esta estrutura:',
      JSON.stringify(sample,null,2),
      'Preserva schema, assessment_id, academic_code e os números dos subtemas. Usa assessed_on em YYYY-MM-DD. score_20 deve estar entre 0 e 20 quando existir classificação global. score_percent deve estar entre 0 e 100 apenas quando evidence_count>0.',
      'review_topics e mastered_topics devem conter apenas números dos subtemas listados. Não uses este relatório para substituir notas oficiais da universidade.'
    ].join('\n');
  }
  status.textContent='Prompt preparada para '+topics.length+' subtema(s). Copia-a para o projeto ChatGPT desta UC.';
});

document.getElementById('copy-external-prompt').addEventListener('click',async()=>{
 if(!output.value){status.textContent='Gera primeiro a prompt.';return;}
 try{await navigator.clipboard.writeText(output.value);status.textContent='Prompt copiada. Cola-a no projeto ChatGPT da UC.';}
 catch(e){output.focus();output.select();status.textContent='Seleciona e copia o texto manualmente (Ctrl+C).';}
});

document.getElementById('open-chatgpt-project')?.addEventListener('click',()=>{
 if(!output.value){status.textContent='Gera primeiro a prompt.';return;}
 const url=document.getElementById('open-chatgpt-project').dataset.projectUrl;
 const tab=window.open(url,'_blank');
 if(tab)tab.opener=null;
 navigator.clipboard.writeText(output.value).then(()=>{
   status.textContent='Projeto aberto e prompt copiada. Cola-a no ChatGPT com Ctrl+V.';
 }).catch(()=>{
   output.focus();output.select();status.textContent='Projeto aberto. Copia o texto selecionado com Ctrl+C antes de colar no ChatGPT.';
 });
 if(!tab)status.textContent='Permite pop-ups para abrir o projeto, ou utiliza o endereço guardado. A prompt continua disponível para copiar.';
});

const normalization=document.getElementById('normalization-prompt');
document.getElementById('copy-normalization-prompt')?.addEventListener('click',async()=>{
 if(!normalization?.value)return;
 try{await navigator.clipboard.writeText(normalization.value);}
 catch(e){normalization.focus();normalization.select();}
});
})();
