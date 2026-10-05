# StudyOS — arquitetura inicial

## Objetivo
Aplicação web 100% online para agregar dados académicos, manter evidência da origem e estado dos factos e, numa fase posterior, orientar estudo com IA.

## Princípios
1. Fonte antes de inferência.
2. Nenhuma data inferida passa a confirmada sem evidência.
3. Cada integração conserva o identificador e payload da fonte.
4. Segredos de feeds e sessões nunca entram em logs.
5. O sistema continua consultável quando uma sincronização falha, mas identifica a idade dos dados.

## Alpha 0.1
- Modelo académico base.
- Ocorrências de aulas/salas.
- Avaliações confirmadas ou candidatas.
- Ligações de sincronização e histórico de execuções.
- Evidência/proveniência.
- Dashboard inicial.
- Preparação para PostgreSQL/Railway.

## Próximos lotes
1. Conector iCalendar InforEstudante.
2. Calendário dinâmico Moodle.
3. Reconciliação Course ↔ SourceCourse.
4. Coletor Moodle de atividades e materiais.
5. Coletor InforEstudante de sumários e regras.
6. Mapa curricular e motor de domínio.
