# OneNote institucional e estado da matéria

## Abordagem adotada (8 de outubro de 2026)

O utilizador optou por acompanhar o progresso diretamente no StudyOS, dado que os
apontamentos OneNote são manuscritos. A integração OneNote fica adiada.

Cada UC mostra a percentagem de aulas decorridas face às aulas programadas no
calendário disponível, excluindo avaliações e cancelamentos. A matéria lecionada é
assinalada numa lista de tópicos, independente do progresso temporal e do domínio
demonstrado em exercícios. É possível acrescentar tópicos manualmente por UC.

A análise OneNote abaixo fica como referência para uma integração futura.

## Resultado da análise (7 de outubro de 2026)

O StudyOS importa ficheiros do Moodle para o armazenamento próprio e extrai texto
em segundo plano. Os originais continuam disponíveis mesmo que a extração falhe.
O catálogo deve permitir filtrar por UC/origem e pesquisar título ou nome do ficheiro.
As ligações internas são relativas para conservar o HTTPS da página, incluindo
quando a aplicação corre atrás do proxy do Railway.

Não existe ainda um importador OneNote. `LessonSummary`, `MaterialVersion` e
`SourceChunk` permitem representar os apontamentos e as suas fontes, mas o sumário
atual foi criado pelo bootstrap académico. Não demonstra uma sincronização OneNote.

Os blocos de notas estão no OneDrive da instituição. A pesquisa de integrações
disponíveis não encontrou um conector OneNote direto. SharePoint não está ligado e
a sua disponibilidade não comprova acesso ao conteúdo das páginas OneNote.

## Ligação necessária

A integração própria deve usar Microsoft Graph com autorização delegada da conta
institucional, através de uma aplicação registada no Microsoft Entra. OneNote não
suporta autenticação apenas da aplicação. A permissão mínima de leitura é
`Notes.Read`; a renovação delegada requer `offline_access`. A política da instituição
pode exigir aprovação administrativa.

Antes de ativar esta integração são necessários o registo Entra, os identificadores
da aplicação/inquilino e a configuração segura das credenciais e do callback HTTPS.
O utilizador autentica-se na Microsoft; não fornece palavras-passe nem tokens no chat.
O início/callback devem validar estado, usar PKCE, associar a conta à sessão
autorizada e guardar tokens cifrados. A aplicação atual não tem contas de utilizador:
esta associação e o controlo de acesso têm de ser resolvidos antes de expor notas ou
uma sincronização Microsoft numa página pública.

## Importação a implementar

1. Listar blocos de notas, secções e páginas, seguindo `@odata.nextLink` em todas as
   coleções. Associar as secções a UCs; correspondências ambíguas ficam por resolver.
2. Obter o HTML das páginas (`/me/onenote/pages/{id}/content`) e guardar título, IDs,
   hierarquia, ligação original, datas de criação/alteração, HTML e texto extraído.
   Imagens/anexos exigem recolha própria e, se necessário, OCR; texto manuscrito não
   pode ser considerado extraído só porque a página existe.
3. Comparar ID e hash para importar apenas versões novas. Preservar versões
   anteriores e distinguir remoção real de erro/permissão de acesso.
4. Executar a sincronização em fila após o botão «Sincronizar OneNote», sem bloquear
   o pedido web. Renovar tokens quando possível e pedir nova autenticação apenas
   quando necessário. Mostrar última sincronização, alterações e erros.
5. Analisar os apontamentos por UC em conjunto com sumários e materiais Moodle.
   Cada conclusão aponta para página/versão/trecho. Datas de alteração não são datas
   de aulas; a data da aula só é atribuída quando consta da fonte.

## Informação a apresentar na UC

| Informação | Evidência necessária |
| --- | --- |
| Material disponibilizado | Ficheiro/página publicado no Moodle |
| Matéria lecionada confirmada | Sumário datado ou indicação explícita nos apontamentos |
| Matéria aparentemente trabalhada | Apontamentos/exercícios, com inferência identificada |
| Próximo conteúdo / por confirmar | Programa conhecido e diferença face às fontes disponíveis |
| Domínio demonstrado | Tentativas avaliadas; não inferir de uploads ou presença de notas |

A UC deve mostrar tópicos, última aula documentada, exercícios trabalhados, lacunas
e ligações aos apontamentos/materiais. Uma percentagem do programa só faz sentido
depois de existir um programa completo e correspondências verificáveis entre tópicos.
Ausência de apontamentos não prova que a matéria não foi lecionada.

## Referências oficiais

- https://learn.microsoft.com/en-us/graph/api/resources/onenote-api-overview?view=graph-rest-1.0
- https://learn.microsoft.com/en-us/graph/api/onenote-list-pages?view=graph-rest-1.0
- https://learn.microsoft.com/en-us/graph/api/page-get?view=graph-rest-1.0

Este documento é a análise e o contrato de implementação. Não indica que a ligação
Microsoft ou a importação OneNote já estejam concluídas.
