# Papéis de TCC no web service — Design

> **Escopo:** lado **Moodle**. Este documento termina no contrato do web service.
> O consumo (tabela `tcc_suportes`, policies, escopo × capacidade) é do
> `sistema-tcc-r8` e está na seção *Handoff* — registrado, não planejado aqui.
>
> **Plugin:** `local_wstcc`, branch `MOODLE_405_STABLE`.
> **Data:** 2026-08-30. **Relacionadas:** #16, #45, #46, #49, #63, #34.

## Estado: ✅ ENTREGUE (2026-08-31)

Tudo o que este documento especifica está implementado, testado e publicado em
`MOODLE_405_STABLE`. Plano de execução: `2026-08-30-plano-papeis-tcc-webservice.md`.

| item | estado |
|---|---|
| A1 `get_papeis_tcc` | ✅ `54dd219` |
| A2 `get_grupos_orientacao` | ✅ `b0ef38b` |
| A2 — três estados (erro × vazio) | ✅ `0fd4334` |
| A2 — log do suporte sem papel no curso | ✅ `60f0a2b` |
| B — as duas configurações, com default em runtime | ✅ `99ec19d` |
| Publicação no serviço (14 funções) | ✅ conferido no banco |
| Check de estado do mapa de papéis | ✅ `314c033` — **não estava neste desenho**, ver abaixo |

Suíte do `local_wstcc`: **32 testes, 0 falhas**. Suíte do `local_tutores`: 57, 0 falhas.

**Provado pelo endpoint REST**, não só por teste — `A1` devolvendo `{"papeis":["orientador"]}`
e `{"papeis":[]}`, `A2` devolvendo os grupos da turma, e curso sem turma devolvendo
`{"errorcode":"turma_ufsc_nao_encontrada", ...}`. O dado real exercita o suporte em dois
grupos (`userid: 10`), que é o caso que motivou o retorno com estudantes.

### O que mudou em relação a este desenho

**Somado — check de estado.** O `debugging()` previsto na *Observabilidade* só produz saída
com depuração de desenvolvedor ligada: em produção é no-op. Um aviso que só existe em
desenvolvimento é pior que nenhum. O estado passou a ser **consultável** por
`\local_wstcc\check\papeis` (Administração do site → Relatórios), com `WARNING` quando
nenhum papel existente responde por um semântico — caso que o default não cobre.

**Resolvido — o `false` do `get_user_roles` fica.** A coordenação decidiu que os
coordenadores serão **participantes do curso**, então a atribuição nasce no contexto certo
e o "plano B" do contexto 40 × 50 não precisa ser acionado.

**Consertos que apareceram ao executar**, fora do escopo original:

- `local_tutores`: exceções de cohort ausente saíam como `[[string]]` (componente
  `report_unasus`, plugin ausente nas árvores 4.x) — corrigido e backportado nas 4 branches;
- `local_tutores`: `turma_ufsc()` **estourava** (`IN ()`, erro de sintaxe) para curso do
  site e curso inexistente, em vez de devolver `false` — achado pela equipe do TCC ao
  chamar a A2, corrigido e backportado;
- `local_wstcc`: `get_users_by_field` caía com dois campos de perfil `cpf` — blindagem
  aplicada no 4.5 e na linha 3.0, que é onde o `SyncPerson` roda hoje.

## Objetivo

Permitir que a ferramenta de TCC identifique dois papéis novos — **Coordenador de
TCC** e **Suporte de Orientação** — e descubra o alcance de cada um, sem depender do
launch LTI.

No Moodle 4.5 o vocabulário do LTI 1.1 é fechado: **todos chegam como `Learner`**,
papel customizado não trafega (confirmado — o `mod/lti` desta árvore não foi
alterado). A identidade tem que vir do **dado**, como já se fez com o orientador na
#45.

## Decisões tomadas

| # | Decisão |
|---|---|
| 1 | `coordtcc` é atribuído **no curso**, não na categoria — vira o `false` do `get_user_roles()` |
| 2 | O `report_unasus` será portado por completo, em **documento próprio** e **em paralelo**: não bloqueia nada aqui |
| 3 | O vínculo do suporte vira **tabela de junção** `tcc_suportes(tcc_id, person_id)` no lado Rails — aqui, só o contrato que a alimenta |
| 4 | **O papel manda na identidade; o grupo manda no alcance** (ver *Identidade × alcance*) |
| 5 | Os papéis são **configuráveis pelo administrador**; só os nomes semânticos do contrato são fixos |
| 6 | A tela de configuração fica em **Usuários**, junto de "Grupos de Tutoria" e "Grupo de Orientação" |

## Identidade × alcance

Os dois papéis **não** se identificam do mesmo jeito:

| | Coordenador de TCC | Suporte de Orientação |
|---|---|---|
| identidade | papel `coordtcc` no curso | papel `suporteorientacao` no curso |
| alcance | a **turma inteira** | os grupos em que é membro do cohort de suporte |
| precisa de grupo no relationship? | não | sim |
| funções | **A1** | **A1 + A2** |

Para o suporte existem duas fontes possíveis do mesmo fato (papel e cohort).
**A autoritativa é o papel** — mesma forma do orientador, uma chamada resolve que
tela mostrar, e mantém o alcance vazio como estado legítimo.

Consequência dos desencontros, que é comportamento especificado e não defeito:

- **papel sem grupo** → reconhecido, alcance vazio, entra e vê a explicação (decisão 4
  do `PLANO_PAPEIS_E_ACESSO`, mesmo tratamento do orientador recém-designado, #49);
- **grupo sem papel** → não reconhecido, cai na tela de aluno **em silêncio** → a A2
  registra log (ver *Observabilidade*).

## A1 — `local_wstcc_get_papeis_tcc(userid, courseid)`

Responde *"que papéis de TCC esta pessoa tem neste curso?"*. Nenhuma das 12 funções
atuais responde isso: `get_orientador_responsavel` / `get_tutor_responsavel` fazem a
pergunta inversa ("quem é o orientador deste aluno").

```
context_course::instance($courseid)
  → get_user_roles($context, $userid, false)   // false = SÓ o curso (decisão 1)
  → mapeia shortname → nome semântico, descarta o resto
```

Retorno: `{"papeis": ["coordenador_tcc", "suporte_orientacao", "orientador"]}`

**Nunca devolve shortname** — o app não deve conhecer o vocabulário de papéis do
Moodle; renomear um papel aqui não pode quebrar o app lá.

Mapa (nome semântico ← config):

| semântico | config | default |
|---|---|---|
| `coordenador_tcc` | `local_wstcc_coordtcc_roles` (nova) | `coordtcc` |
| `suporte_orientacao` | `local_wstcc_suporte_roles` (nova) | `suporteorientacao` |
| `orientador` | `local_tutores_orientador_roles` (**existente, reusada**) | `orientador` |

⚠️ Reuso deliberado na terceira linha: criar uma config própria para o orientador
produziria duas fontes divergentes para o mesmo fato.

Resolve também a **#49** (orientador recém-designado, antes do `rake tcc:sync`).
Verificado que o pressuposto vale: quem é membro do cohort de orientador nos grupos
é exatamente quem tem o papel `orientador` no curso.

## A2 — `local_wstcc_get_grupos_orientacao(courseid)`

Responde *"quais são os grupos de orientação desta turma, e quem está em cada um?"*.

```
\local_tutores\categoria::turma_ufsc($courseid)      // padrão das funções existentes
  → valida o retorno (ver Estados)
  → get_relationship_orientacao($categoria_turma)
  → grupos + JOIN relationship_members/relationship_cohorts, rotulando por papel
```

Retorno:

```json
{"grupos": [{"id": 1, "nome": "Grupo Dalvan Antônio de Campos",
             "orientadores": [{"userid": 42}],
             "suportes":     [{"userid": 55}, {"userid": 61}],
             "estudantes":   [{"userid": 70}, {"userid": 71}]}]}
```

Quatro exigências que esse formato atende de propósito:

1. **Uma chamada por turma**, não por aluno — perguntar o suporte por aluno dobraria o
   custo do sync, que já faz uma chamada por aluno para o orientador.
2. **Listas, não escalares** — o dado real tem N suportes por grupo *e* N grupos por
   suporte (é o motivo do `allowdupsingroups = 1`).
3. **Estudantes no retorno** — sem eles, o único caminho até o TCC seria pelo
   orientador, e isso **erra** quando um orientador atua em mais de um grupo: os TCCs
   dele receberiam os suportes de todos os grupos, dando a um suporte acesso a alunos
   que não acompanha.
4. **`userid`, não `id`** — o número é casado com o cadastro local do app; o id errado
   grava vínculo para a pessoa errada, em silêncio.

⚠️ **Não reusa `get_grupos_orientacao_by_userid()`.** O ramo com lista chama
`report_unasus_int_array_to_sql()` (`local/tutores/lib.php:371`), função que **não
existe nesta árvore** (`report_unasus` não está instalado). A A2 monta o `IN` com
`$DB->get_in_or_equal(...)`, padrão do próprio `lib.php` para cohorts — é o que a
mantém independente do porte (decisão 2).

## Estados: erro nunca chega como vazio

Regra: **falha é exceção do web service**; lista vazia significa literalmente "não há".
Isso sustenta duas regras do lado Rails — falha do WS não rebaixa a pessoa para
estudante, e o sync não reescreve a lista de suportes quando a leitura falhou.

⚠️ **Hoje dois estados são indistinguíveis.** `get_relationship()`
(`local/tutores/lib.php:221`) interpola a categoria direto no SQL, sob um `FIXME` que
diz exatamente isso: `# FIXME: validar categoria-turma (não aceitar boolean)`. Quando
`turma_ufsc()` não resolve, devolve `false`, que vira `LIKE '%//%'`, não casa com nada,
e cai no **mesmo** `throw` de "relationship não existe" (`lib.php:250` — a mesma linha
que produz a #63 na tutoria).

A A2 valida antes de chamar, e os três estados ficam separados:

| estado | resposta |
|---|---|
| curso não resolve turma (`turma_ufsc()` falso) | exceção **`turma_ufsc_nao_encontrada`** (nova) |
| turma existe, sem relationship de orientação | exceção `relationship_grupo_orientacao_not_available_error` (existente) |
| relationship existe, zero grupos | `{"grupos": []}` |

Fecha o `FIXME` neste caminho de chamada (não nos demais).

### Consequência para a #63 (diagnóstico novo)

A conflação acima significa que a #63 podia estar exibindo a causa errada: "relationship de
tutoria não existe" e "categoria não resolve" são a mesma mensagem.

**A hipótese está refutada — e o argumento não depende de acesso a servidor.**
`turma_ufsc()` casa com **qualquer uma** das duas tags, e o vínculo de orientador passa
pela **mesma** resolução `curso → categoria da turma`. Na rodada de sync que produziu o
erro da #63, os orientadores vincularam **4/4**. Se a categoria não resolvesse, eles
teriam falhado junto. Logo a categoria resolveu, e o erro de tutoria é **ausência
genuína** do relacionamento.

Consistente com a medição local (⚠️ ambiente **local**, base `moodle_405_unasus_local` —
**não** o `unasus-dev.moodle.ufsc.br`, onde o incidente da #63 ocorreu):

```
curso 5 -> turma_ufsc(): '1'          # resolve
   grupo_orientacao -> OK (relationship 1 'Teste_relation_tcc')
   grupo_tutoria    -> EXCECAO: relationship_grupo_tutoria_not_available_error
```

A categoria resolve; o relacionamento de tutoria realmente não existe.

**Regra para distinguir as duas causas em qualquer ambiente, sem mudar código:**
`turma_ufsc()` casa com **qualquer uma** das duas tags. Logo, se
`get_orientador_responsavel` funciona naquele curso, a categoria resolveu — e o erro de
tutoria no mesmo curso é ausência genuína. Só há ambiguidade real quando **as duas**
falham no mesmo curso.

## B — Configuração

`local/wstcc/settings.php` **nasce agora** (o plugin não tem hoje), no formato do
`local_tutores`: `admin_setting_configmultiselect` alimentado por
`get_roles_for_contextlevels(CONTEXT_COURSE)` — lista de papéis que existem de
verdade, não texto livre. Vai em **Usuários**, junto das telas de Grupos (decisão 6),
para que as três configurações de papel fiquem no mesmo lugar.

Aceita **vários papéis por função** (ex.: um `coordtcc_polo` futuro entra sem deploy).

⚠️ **O default vai em DOIS lugares.** Na definição da tela, o default só é gravado no
install/upgrade; uma restauração de dump que apague a linha da config **não** o repõe —
e restaurar dump neste projeto já apagou o token do WS, o campo de CPF e a configuração
LTI. Então a **leitura em runtime** também usa o default quando a config vier vazia, e
registra log. Sem isso, o sintoma é a pessoa entrando como aluno, sem erro nenhum.

⚠️ **Não** acrescentar `suporteorientacao` a `local_tutores_orientador_roles`. É o
atalho que parece resolver: o suporte passaria a ser devolvido como orientador
responsável do aluno, corrompendo o vínculo no `SyncTcc`.

## C — Cadastro por turma

| item | ação | estado no `unasus-dev` |
|---|---|---|
| C1 | atribuir `coordtcc` a quem coordena, **no curso** | papel existe (id 15), **zero atribuições** |
| C2 | cohort de suporte no relationship, `allowdupsingroups = 1` | ✅ montado e validado |

⚠️ `allowdupsingroups` tem default `0`, e com ele o seletor de candidatos deixa de
oferecer a pessoa depois do primeiro grupo — e o suporte costuma apoiar vários.

### Contexto 40 × 50 — critério de decisão, registrado antes de precisar

Os dois papéis são atribuíveis em contexto de **categoria (40)** e de **curso (50)**, e o
modelo institucional é *turma = categoria*. A decisão 1 (`false` no `get_user_roles`) é a
**conservadora**: só enxerga atribuição no curso, e o log denuncia quem for atribuído
acima. Mas quem for atribuído na categoria ainda entra como aluno.

**Plano B, se o cadastro real do C1 atribuir na categoria:** trocar o `false` por `true`.
É uma linha, e **não amplia escopo do lado Rails** — o `TccPolicy::Scope` prende tudo à
`tcc_definition` do launch, então papel herdado da categoria continuaria valendo só na
turma em que a pessoa entrou.

Gatilho: a primeira atribuição real de `coordtcc`. Se vier na categoria, vira `true`; se
vier no curso, o `false` fica. Registrado agora para não virar discussão depois.

## Publicação do serviço

1. Somar `local_wstcc_get_papeis_tcc` e `local_wstcc_get_grupos_orientacao` ao array
   `$functions` **e** à lista do serviço `TCC Services` em `db/services.php`.
2. Subir `$plugin->version` (hoje `202608201700`).
3. ⚠️ **Pré-voo no servidor que receber a turma:** conferir o `component` do serviço
   `wstcc_webservice`. Se tiver sido criado à mão (`component` NULL), o upgrade **não**
   atualiza a lista de funções, e as novas ficam declaradas mas inacessíveis pelo
   token. O caminho é `UPDATE`, **nunca recriar** — o token vive nesse serviço.
   No `unasus-dev` está saudável: `component = 'local_wstcc'`, 12 funções, 1 token.

⚠️ **O `local_tutores` continua sendo dependência, mesmo com o tutor fora do produto.**
O `externallib.php` faz `require_once` de `local/tutores/lib.php` no topo: sem o plugin,
**nenhuma** das funções carrega — as novas inclusive. A decisão de 27/08 (#63) removeu a
*figura do tutor* do TCC, não o plugin do servidor, e `local_tutores_tutor_roles` deixar
de ser usado não muda isso.

## Observabilidade

Três linhas de log, todas de graça no request que já acontece:

1. config de papéis **vazia** (usou o default);
2. papel encontrado em **contexto acima** do curso e não no curso — a decisão 1 é
   regra nova, e ainda não foi exercida pelo papel que ela governa (`coordtcc` tem
   zero atribuições);
3. membro de cohort de suporte **sem** o papel no curso (o desencontro "grupo sem
   papel", que de outro modo é silencioso).

## Testes

- **A1**: devolve cada papel isoladamente e combinados; ignora papel não configurado;
  ⚠️ **não** enxerga atribuição feita na categoria (a decisão 1 virando asserção).
- **A2 — fixture obrigatória: um orientador em DOIS grupos, com suportes distintos.**
  Os dados atuais não exercitam isso (cada orientador está em exatamente um grupo), e
  é justamente o caso que o retorno com estudantes existe para acertar. Sem essa
  fixture, o defeito passa verde.
- **A2 — os três estados**: turma inexistente e relationship ausente levantam exceções
  **distintas**; relationship sem grupos devolve `[]`.
- **Config vazia**: cai no default e loga; não devolve lista vazia de papéis.
- **Prova por reversão**: cada teste novo deve falhar com o código anterior.

## Handoff — lado Rails, fora deste plano

1. Tabela `tcc_suportes(tcc_id, person_id)`, preenchida pelo `SyncTcc` a partir da A2.
2. Consumo da A1 na identidade (`Authentication::User`), com **memoização antes** de
   trocar a fonte — `orientador?` é consultado em cascata e viraria várias chamadas de
   rede por requisição (#49).
3. Separar **escopo de capacidade** nos 12 usos de `view_all?` (`tcc_policy` ×4,
   `chapter_policy` ×2, `lti_tcc_filters`, `authentication/moodle` ×2,
   `instructor_admin_controller`, `user.rb` ×2). Sem isso o Coordenador de TCC é
   inexprimível: ele precisa do escopo do administrador **sem** Configuração e **sem**
   Sidekiq.
4. ⚠️ O Coordenador **não** pode entrar como `Administrator` do LTI — a `AuthConstraint`
   do Sidekiq decide **fora** das policies, e ele viria com o painel junto.

## Verificado no ambiente (2026-08-30, `moodle_405_unasus_local`)

| fato | resultado |
|---|---|
| papéis `coordtcc` (15) e `suporteorientacao` (14) | existem; atribuíveis em contexto 40 **e** 50 |
| atribuições | 28, **todas** em contexto de curso; `coordtcc` com zero |
| papel × cohort — orientador | coincidem (Dalvan, Elanne) |
| papel × cohort — suporte | coincidem (os mesmos 3 CPFs) |
| grupo com mais de um suporte | sim — Dalvan e Elanne têm 2 cada |
| suporte em mais de um grupo | sim — `05838729996` nos dois |
| orientador em mais de um grupo | **não** — daí a fixture obrigatória |
| `allowdupsingroups` no cohort de suporte | `1` |
| `report_unasus` instalado | **não**, em nenhuma árvore 4.x |
| serviço `wstcc_webservice` | `component = 'local_wstcc'`, 12 funções, 1 token |
| Relationship de tutoria | **inexistente** (só a tag `grupo_orientacao`) — ausência genuína, ver #63 |

## Fronteira de segurança: hoje é só o token

⚠️ **Nenhuma das 14 funções do `local_wstcc` chama `validate_context()` ou
`require_capability()`** — as duas novas inclusive. Medido em 31/08 (a contagem no arquivo
inteiro é zero). As funções aqui seguiram o padrão das 12 existentes; consistente com o
arquivo, e ainda assim uma lacuna.

Consequência prática: **o token é a única fronteira do web service.** Quem tem o token lê
qualquer curso, porque as funções não verificam nada além dos tipos dos parâmetros
(`validate_parameters`).

⚠️ **E isso acopla duas coisas que pareciam separadas.** O token do `unasus-dev` pertence ao
Administrador, sem restrição de IP e sem validade, e a recomendação natural seria trocá-lo
por uma conta de serviço com as capabilities mínimas do `README`. **Essa troca, sozinha, não
restringiria nada:** a conta mínima leria qualquer curso do mesmo jeito. A troca só entrega
segurança se vier **junto** com a verificação de contexto nas funções.

**Decisão de 31/08: fica como está por ora**, registrado e não escondido. O consumidor hoje
é um só (o sistema de TCC), e o token de administrador é a fronteira de fato.

**Planejado, como tarefa própria:** somar `validate_context()` às 14 funções, junto da troca
do token por conta de serviço. ⚠️ Fazer só nas duas novas foi **descartado** — meio arquivo
protegido é mais difícil de raciocinar do que a lacuna uniforme de hoje, e ninguém lembra
qual metade. ⚠️ A mudança altera comportamento para qualquer consumidor com permissões
restritas, então exige teste e aviso à equipe do TCC antes de subir.

## Fora de escopo

| item | onde vive |
|---|---|
| Porte do `report_unasus` | `2026-08-30-porte-report-unasus-design.md` |
| Tabela `tcc_suportes` e policies | `sistema-tcc-r8` (handoff) |
| Figura do tutor (removida do produto por decisão de 27/08) | #63 |
| `validate_context()` nas 14 funções + troca do token por conta de serviço | tarefa própria, planejada (ver acima) |
| Conferência de versões de plugin no servidor da turma | #34 |
