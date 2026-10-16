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
  output.value=[
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
  ].join('\n');
  status.textContent='Prompt preparada para '+topics.length+' subtema(s). Copia-a para o projeto ChatGPT desta UC.';
});
document.getElementById('copy-external-prompt').addEventListener('click',async()=>{
 if(!output.value){status.textContent='Gera primeiro a prompt.';return;}
 try{await navigator.clipboard.writeText(output.value);status.textContent='Prompt copiada. Cola-a no projeto ChatGPT da UC.';}
 catch(e){output.focus();output.select();status.textContent='Seleciona e copia o texto manualmente (Ctrl+C).';}
});
})();