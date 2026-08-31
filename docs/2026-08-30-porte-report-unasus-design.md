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

## Resultado do spike (2026-08-31)

Varredura feita com o **tokenizer do PHP** (`token_get_all`), não com regex: string e
comentário não contaminam o resultado. 32 arquivos, 142 nomes de função chamados, 60
classes instanciadas, cruzados contra 4.242 funções globais e 14.266 classes do 4.5 e
1.745 funções internas do PHP 8.3.

### Superfície de API removida: menor do que se temia

| achado | ocorrências | situação |
|---|---|---|
| `print_error()` | 6 | removida no 4.5 — troca mecânica |
| `get_magic_quotes_gpc()` | 1 | removida no **PHP 8.0**; está em código de terceiros embutido (`graph/highcharts/exporting-server/`) |
| `Zend_Http_Client` | 1 | ⚠️ o Zend saiu do core do Moodle; `lib/zend` não existe |
| funções em `deprecatedlib.php` | **0** | nenhuma chamada depreciada |

Falsos positivos descartados na conferência: `moodle_url` (existe — é `core\url` com
`class_alias` em `lib/classes/url.php:908`) e quatro `query_*` (métodos privados
declarados com `&` de retorno, que o detector não registrava como definição).

### O que impede o plugin de CARREGAR hoje — medido por execução

Requerer `report/unasus/locallib.php` num Moodle 4.5 falha. São **dois** bloqueadores,
em série:

1. **`datastructures.php:576`** — `report_unasus_dado_atividades_nota_atribuida` estende
   `report_unasus_dado_atividades_alunos_render`, declarada só na **linha 583**.
   `Error: Class ... not found`. É a **única** ocorrência do padrão no plugin; o conserto
   é mover duas linhas.
2. **`sistematcc.php:6-7`** — `include 'Zend/Loader/Autoloader.php'` seguido de
   `Zend_Loader_Autoloader::autoload(...)`, **código de topo**, executado no include.
   Com o Zend fora do core, dá `Error: Class "Zend_Loader_Autoloader" not found`.
   Corrigido o bloqueador 1, este aparece — verificado por execução.

⚠️ **Isso está no caminho de `locallib.php`**, que é exatamente o arquivo que define
`report_unasus_int_array_to_sql()` — a função que motivou o porte. Ou seja: instalar o
plugin como está não satisfaz a dependência do `local_tutores`; ele nem carrega.

### Dimensão da substituição do Zend

`sistematcc.php` tem 114 linhas e o uso do Zend está confinado a: o construtor
(`new Zend_Http_Client`) e um único caminho de POST (`setUri` / `setRawData` /
`request('POST')` / `catch Zend_Http_Client_Adapter_Exception`). Substituível pela classe
`curl` do Moodle sem redesenhar a classe.

### O que o spike NÃO mediu

A varredura ignora chamadas de **método** (`->` e `::`) e acessos a propriedade: mudanças
de assinatura em `$OUTPUT`, `html_table`, `$DB` e afins **não** aparecem aqui. Também não
diz nada sobre semântica — as *queries*, que são o corpo do plugin, só se provam
executando os relatórios contra dados reais. O spike mede a superfície de **carga**, e ela
está a poucos consertos de fechar; o corpo continua por medir.

## Escopo do porte

0. Destravar a **carga**: mover a subclasse de `datastructures.php:576` para depois do
   pai, e trocar o cliente Zend de `sistematcc.php` pela classe `curl` do Moodle. Sem
   isto o plugin não carrega, e nada mais pode ser medido.
1. Substituir as 6 chamadas a `print_error()` pelo equivalente 4.5.
2. Subir `$plugin->requires` para o 4.5 e revisar `$plugin->dependencies`.
3. ✅ **Feito pelo spike** para funções e classes (ver acima). Falta a varredura de
   **métodos e propriedades**, que o tokenizer não cobriu.
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
