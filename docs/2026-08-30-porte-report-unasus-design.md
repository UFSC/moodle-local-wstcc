# Porte do `report_unasus` para o Moodle 4.5 — Design

> **Decisão:** porte **completo**, como projeto próprio.
> **Não bloqueia** as funções A1/A2 do web service — as duas frentes correm em
> paralelo, por decisão explícita.
> **Data:** 2026-08-30. **Documento irmão:** `2026-08-30-papeis-tcc-webservice-design.md`.

## Por que existe

O `local_tutores` tem uma **dependência de runtime não declarada** do `report_unasus`:
`lib.php` chama `report_unasus_int_array_to_sql()` em quatro pontos (linhas 371, 398,
568, 598) e `query_alunos_relationship()`. Como a dependência não está no `version.php`,
ela **não quebra na instalação** — quebra na primeira chamada, em produção.

Hoje, no `unasus-dev`, o `report_unasus` **não está instalado**, e a função não existe
em lugar nenhum da árvore. Consequência prática: `get_grupos_orientacao_by_userid()`
só funciona no ramo `$orientadores = null`; passar a lista é fatal.

⚠️ **A decisão foi portar, não contornar.** Fica registrado o que se pesa contra: a
função que falta são seis linhas (`return implode(',', $array)`), e o plugin
disponível é de 2017. O porte se justifica por **querer os relatórios UnaSUS no 4.5**,
não por precisar de um `implode`. Se em algum momento a prioridade mudar, a saída de
uma hora é mover o helper para o `local_tutores` e cortar a dependência.

## Estado do que existe

| fato | valor |
|---|---|
| cópia mais nova na máquina | `php72/www/unasus38-2021/report/unasus` |
| versão | `2017053001` |
| `$plugin->requires` | `2014111006` (**Moodle 2.8.6**) |
| repositório | `UFSC/moodle-report-unasus`, só `master`, sem branch 4.x |
| tamanho | 32 arquivos PHP, 1,7 MB |
| lint sob PHP 8.3 | ✅ **32/32 sem erro de sintaxe** |
| `print_error()` | ❌ **6 chamadas** — a função **não existe mais** no 4.5 |
| `user_picture::fields`, `get_all_user_name_fields` | 0 ocorrências |

Lint limpo **não é** execução limpa: a sintaxe passa, as APIs removidas só aparecem em
runtime. As 6 chamadas a `print_error()` são o piso conhecido, não o teto.

## Dependência circular

```
report_unasus  --declara-->  local_tutores >= 2017051201, local_relationship >= 2015020100
local_tutores  --usa (não declara)-->  report_unasus
```

O Moodle não detecta o ciclo, porque o lado do `local_tutores` não é declarado. Vale
decidir no porte se a dependência passa a ser declarada (e o ciclo, explícito) ou se o
helper migra para o `local_tutores` (e o ciclo desaparece).

Versões instaladas hoje, todas ≥ o que o `report_unasus` exige:
`local_tutores 2026051901`, `local_relationship 2026070202`.

## Escopo do porte

1. Substituir as 6 chamadas a `print_error()` pelo equivalente 4.5.
2. Subir `$plugin->requires` para o 4.5 e revisar `$plugin->dependencies`.
3. Varrer as demais APIs removidas/depreciadas entre 2.8 e 4.5 — **o levantamento
   completo é a primeira tarefa do plano**, não um pressuposto deste desenho.
4. Rodar os relatórios contra dados reais: sintaxe limpa não diz nada sobre as
   queries, que são o corpo do plugin.
5. Decidir o destino do `int_array_to_sql` (ver *Dependência circular*).

## Efeito colateral que o porte resolve

`local/tutores/lib.php` lança duas `moodle_exception` com **componente de string
`report_unasus`** (linhas 150 e 320) — "cohort de estudantes/orientadores não
configurado". Sem o plugin instalado, esses erros saem na tela como string faltando.

São exatamente os caminhos que A1/A2 exercitam. Se o porte demorar, vale trocar o
componente para `local_tutores` de forma independente: as strings equivalentes já
existem lá (`lang/en/local_tutores.php`).

## Riscos

- **Cauda desconhecida.** Só o lint foi executado. O tamanho real do porte só se
  conhece depois da varredura de APIs e da primeira execução.
- **Sem branch 4.x upstream.** O porte cria a primeira; combinar com a política de
  cascata por versão antes de publicar.
- **Prazo.** É medido em semanas. Por isso não está no caminho crítico da turma —
  A1/A2 foram desenhadas para não depender dele.
