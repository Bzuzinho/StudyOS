# Blocos pedagógicos StudyOS (v1)

Este formato é opcional e compatível com pacotes `studyos.curriculum-package.proposal.v1`.

O ficheiro Markdown do subtema mantém-se obrigatório e continua a servir de texto pesquisável no corpus. Para um leitor visual rico, acrescentar no nó do subtema do `manifest.json`:

```json
{"id":"IG-01.01","title":"Organizações e gestão","file":"temas/tema-01/subtema-01-01.md","blocks_file":"temas/tema-01/subtema-01-01.blocks.json"}
```

O ficheiro `blocks_file` é JSON com `schema: "studyos.pedagogical-blocks.v1"` e `blocks`, conforme `docs/examples/ig-01-01.blocks.json`.

Cada bloco tem `type`, `mode`, `title` e `body` (Markdown seguro); `mode` é `study`, `review` ou `both`. Blocos `comparison` e `table` incluem `rows` (matriz de strings). `process`, `diagram` e `timeline` incluem `steps` (lista de strings). Conteúdos são validados antes de gravar. O CSS desenha tabelas e fluxos acessíveis, sem execução de HTML externo.

## Regras editoriais
- `study`: desenvolvimento fundamentado, definições, relações, exemplos e quadros; **sem questões nem grelhas de avaliação**.
- `review`: síntese genuína, comparação curta, conceitos-chave e erros frequentes; não repetir automaticamente o desenvolvimento integral.
- `both`: apenas ideias cuja leitura seja útil em ambos os contextos.
- Exercícios, respostas de referência, rubricas e classificações devem constar exclusivamente de `exercicios.json`.
- Não inventar que o texto vem do docente ou da FUC. Preservar a distinção entre documentos oficiais e desenvolvimento pedagógico.
- Diagramas v1 representam sequências; não usar este formato para gráficos estatísticos ou eixos quantitativos, que requerem dados apropriados.

A importação é idempotente e o hash da versão incorpora os blocos. Pacotes antigos sem `blocks_file` mantêm a apresentação Markdown da primeira fase. Importar um pacote com novos blocos cria uma versão de material sem apagar histórico.
