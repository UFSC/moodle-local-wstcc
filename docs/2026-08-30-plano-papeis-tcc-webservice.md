# Papéis de TCC no web service — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dar ao `local_wstcc` duas funções que permitam à ferramenta de TCC identificar o Coordenador de TCC e o Suporte de Orientação, e descobrir o alcance de cada um, sem depender do launch LTI.

**Architecture:** Duas funções novas no `local_wstcc_external` (`externallib.php`). A A1 lê `role_assignments` no contexto do curso e traduz shortname → nome semântico por configuração. A A2 lê os grupos do relationship de orientação da turma e rotula cada membro por papel. Um `locallib.php` novo concentra a leitura de configuração com default em runtime, usado pelas duas.

**Tech Stack:** PHP 8.3, Moodle 4.5, PHPUnit (suíte do Moodle), `local_tutores` + `local_relationship` como dependências.

**Spec:** `docs/2026-08-30-papeis-tcc-webservice-design.md` — leia antes de começar. Este plano não repete as justificativas, só as executa.

## Global Constraints

- Branch: **`MOODLE_405_STABLE`** do `local_wstcc`. Não commitar em `master` nem em `MOODLE_30_STABLE`.
- Comentários, nomes e strings em **português do Brasil**, como o resto do repositório. Comentário explica o **porquê**; o que o código faz é ruído.
- Sintaxe `array(...)` e ausência de type hints escalares, seguindo o `externallib.php` existente. Não modernizar de passagem.
- As funções devolvem **nomes semânticos** (`coordenador_tcc`, `suporte_orientacao`, `orientador`) — **nunca** shortname de papel.
- ⚠️ **NUNCA** acrescentar `suporteorientacao` a `local_tutores_orientador_roles`. Corromperia o vínculo de orientador responsável no `SyncTcc`.
- Nas listas de membros o campo é **`userid`**, nunca `id`.
- Mensagens de commit **sem** trailer `Co-Authored-By`.
- Cada tarefa que mexe em `db/services.php` **sobe `$plugin->version`** em `version.php` (formato `YYYYMMDDXX`; hoje `202608201700`).

### Baseline: a suíte NÃO está verde antes de você começar

```
Tests: 15, Assertions: 45, Errors: 1.
1) local_wstcc_external_testcase::test_get_users_by_field
   dml_read_exception: Subquery returns more than 1 row
```

Esse erro é **pré-existente** e não tem relação com este trabalho. Não conserte, não contorne, não deixe que ele confunda a leitura dos seus resultados: conte os testes novos.

### Comandos

```bash
# Suíte inteira do plugin
docker exec -e XDEBUG_MODE=off local-moodle-unasus-dev-405 \
  bash -c "cd /var/www && php vendor/bin/phpunit --testsuite local_wstcc_testsuite"

# Um teste só
docker exec -e XDEBUG_MODE=off local-moodle-unasus-dev-405 \
  bash -c "cd /var/www && php vendor/bin/phpunit --testsuite local_wstcc_testsuite --filter test_nome_do_teste"
```

⚠️ Rodar por **caminho de arquivo** falha (`Class externallib_test could not be found`): o PHPUnit do 4.5 casa nome de classe com nome de arquivo, e as classes deste repositório são legadas. Use sempre `--testsuite`.

⚠️ **Depois de subir `$plugin->version`, a suíte para de rodar** com *"Moodle PHPUnit
environment was initialised for different version"*. Reinicialize antes de continuar
(leva alguns minutos):

```bash
docker exec local-moodle-unasus-dev-405 \
  bash -c "cd /var/www && php admin/tool/phpunit/cli/init.php"
```

Isso acontece nas Tasks 2 e 3, que sobem a versão. Rode os testes da tarefa **antes** do
bump, e o `init.php` logo depois.

⚠️⚠️ **E o `init.php` pode falhar pela metade**, com
`Moodle PHPUnit environment configuration error: Can not install on non-test site!!`
— foi o que aconteceu em 31/08. A causa: ele **derruba as tabelas antes de instalar**, e
se a derrubada não termina, sobram tabelas `phpu_` sem a `config`; aí `is_test_site()`
responde falso e ele se recusa a instalar. O ambiente fica **inutilizável até limpar**.

Recuperação — apaga só o prefixo de teste (a instalação real aqui usa prefixo **vazio**):

```bash
# 1. o que sobrou
docker exec docker-mysql80 mysql -uroot -p'#m00m00' -N -e "
SELECT table_name FROM information_schema.tables
 WHERE table_schema='moodle_405_unasus_local' AND table_name LIKE 'phpu\_%';"

# 2. derruba o que sobrou (liste os nomes do passo 1 no DROP)
docker exec docker-mysql80 mysql -uroot -p'#m00m00' -e "
USE moodle_405_unasus_local; SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS <nomes do passo 1>;
SET FOREIGN_KEY_CHECKS=1;"

# 3. agora o init completa
docker exec local-moodle-unasus-dev-405 bash -c "cd /var/www && php admin/tool/phpunit/cli/init.php"
```

⚠️ Confira que o `DROP` só cita nomes com o prefixo `phpu_`. A instalação real está no
**mesmo banco**, com prefixo vazio.

---

## File Structure

| arquivo | responsabilidade |
|---|---|
| `local/wstcc/locallib.php` **(criar)** | leitura das configs de papel com default em runtime e log; um lugar só, usado pelas duas funções |
| `local/wstcc/settings.php` **(criar)** | as duas configurações novas, na região "Usuários" |
| `local/wstcc/externallib.php` **(modificar)** | os seis métodos novos (`_parameters`, execução, `_returns` × 2) |
| `local/wstcc/db/services.php` **(modificar)** | declarar as funções e somá-las ao serviço `TCC Services` |
| `local/wstcc/lang/en/local_wstcc.php` **(modificar)** | strings das configs e da exceção nova |
| `local/wstcc/version.php` **(modificar)** | bump a cada mudança em `db/services.php` |
| `local/wstcc/tests/papeis_test.php` **(criar)** | testes das duas funções; arquivo próprio, separado do `externallib_test.php` legado |

---

## Task 1: Leitura de configuração com default em runtime

**Files:**
- Create: `local/wstcc/locallib.php`
- Create: `local/wstcc/settings.php`
- Create: `local/wstcc/tests/papeis_test.php`
- Modify: `local/wstcc/lang/en/local_wstcc.php`

**Interfaces:**
- Produces: `local_wstcc_papeis_configurados($nomeconfig, $default)` → `array` de shortnames; `local_wstcc_mapa_papeis()` → `array` shortname ⇒ nome semântico.

- [ ] **Step 1: Write the failing test**

Crie `local/wstcc/tests/papeis_test.php`:

```php
<?php

/**
 * Testes das funções de papel de TCC do local_wstcc.
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/wstcc/locallib.php');

class local_wstcc_papeis_testcase extends advanced_testcase {

    public function test_config_vazia_cai_no_default_e_avisa() {
        $this->resetAfterTest();

        set_config('local_wstcc_coordtcc_roles', '');

        $papeis = local_wstcc_papeis_configurados('local_wstcc_coordtcc_roles', 'coordtcc');

        // O default e' rede de protecao: sem ele ninguem e' reconhecido e a
        // pessoa cai na tela de estudante sem erro nenhum.
        $this->assertEquals(array('coordtcc'), $papeis);
        $this->assertDebuggingCalled();
    }

    public function test_config_preenchida_manda_no_default() {
        $this->resetAfterTest();

        set_config('local_wstcc_coordtcc_roles', 'coordtcc,coordtcc_polo');

        $papeis = local_wstcc_papeis_configurados('local_wstcc_coordtcc_roles', 'coordtcc');

        $this->assertEquals(array('coordtcc', 'coordtcc_polo'), $papeis);
        $this->assertDebuggingNotCalled();
    }

    public function test_mapa_traduz_shortname_para_nome_semantico() {
        $this->resetAfterTest();

        set_config('local_wstcc_coordtcc_roles', 'coordtcc');
        set_config('local_wstcc_suporte_roles', 'suporteorientacao');
        set_config('local_tutores_orientador_roles', 'orientador');

        $mapa = local_wstcc_mapa_papeis();

        $this->assertEquals('coordenador_tcc', $mapa['coordtcc']);
        $this->assertEquals('suporte_orientacao', $mapa['suporteorientacao']);
        $this->assertEquals('orientador', $mapa['orientador']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker exec -e XDEBUG_MODE=off local-moodle-unasus-dev-405 \
  bash -c "cd /var/www && php vendor/bin/phpunit --testsuite local_wstcc_testsuite --filter local_wstcc_papeis_testcase"
```

Esperado: FAIL — `Failed opening required '/var/www/local/wstcc/locallib.php'`.

- [ ] **Step 3: Write minimal implementation**

Crie `local/wstcc/locallib.php`:

```php
<?php

/**
 * Rotinas de apoio do local_wstcc.
 *
 * @package local_wstcc
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Le uma config de papeis, caindo no default quando ela vier vazia.
 *
 * ⚠️ O default vive AQUI, e nao so' na definicao do settings.php. No Moodle o
 * default de um admin_setting so' e' gravado no install/upgrade: uma restauracao
 * de dump que apague a linha da config NAO o repoe. Neste projeto restaurar dump
 * ja' apagou o token do web service, o campo de CPF e a configuracao LTI -- e o
 * sintoma de uma config de papel vazia e' a pessoa entrar como estudante, sem
 * erro nenhum.
 *
 * @param string $nomeconfig nome da config em {config}
 * @param string $default shortnames separados por virgula
 * @return array lista de shortnames
 */
function local_wstcc_papeis_configurados($nomeconfig, $default) {
    $valor = get_config('moodle', $nomeconfig);

    if ($valor === false || trim($valor) === '') {
        debugging("local_wstcc: a config {$nomeconfig} esta vazia; usando o default '{$default}'.",
                DEBUG_DEVELOPER);
        $valor = $default;
    }

    return array_values(array_filter(array_map('trim', explode(',', $valor)), 'strlen'));
}

/**
 * Mapa shortname => nome semantico de papel de TCC.
 *
 * O papel de orientador reusa a config do local_tutores de proposito: uma config
 * propria criaria duas fontes divergentes para o mesmo fato.
 *
 * @return array
 */
function local_wstcc_mapa_papeis() {
    $mapa = array();

    $grupos = array(
        'coordenador_tcc' => local_wstcc_papeis_configurados('local_wstcc_coordtcc_roles', 'coordtcc'),
        'suporte_orientacao' => local_wstcc_papeis_configurados('local_wstcc_suporte_roles', 'suporteorientacao'),
        'orientador' => local_wstcc_papeis_configurados('local_tutores_orientador_roles', 'orientador'),
    );

    foreach ($grupos as $semantico => $shortnames) {
        foreach ($shortnames as $shortname) {
            $mapa[$shortname] = $semantico;
        }
    }

    return $mapa;
}
```

- [ ] **Step 4: Run test to verify it passes**

Mesmo comando do Step 2. Esperado: `OK (3 tests, 7 assertions)`.

- [ ] **Step 5: Criar a tela de configuração**

Crie `local/wstcc/settings.php`:

```php
<?php

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $available_roles = $DB->get_records('role');
    if ($available_roles) {
        $available_roles = role_fix_names($available_roles, null, ROLENAME_ORIGINAL);
        $assignable_roles = get_roles_for_contextlevels(CONTEXT_COURSE);

        $roles = array();
        foreach ($assignable_roles as $assignable) {
            $role = $available_roles[$assignable];
            $roles[$role->shortname] = $role->localname;
        }

        // Fica em "Usuarios", junto das telas de Grupos do local_tutores: quem
        // cadastra papel de TCC acha as tres configuracoes no mesmo lugar.
        $ADMIN->add('users', new admin_category('papeistcc',
                get_string('papeis_tcc', 'local_wstcc')));

        $settings = new admin_settingpage('papeis_tcc_settings',
                get_string('papeis_tcc_settings', 'local_wstcc'));

        $settings->add(new admin_setting_configmultiselect('local_wstcc_coordtcc_roles',
                get_string('settings_coordtcc_roles', 'local_wstcc'),
                get_string('description_coordtcc_roles', 'local_wstcc'),
                array('coordtcc'), $roles));

        $settings->add(new admin_setting_configmultiselect('local_wstcc_suporte_roles',
                get_string('settings_suporte_roles', 'local_wstcc'),
                get_string('description_suporte_roles', 'local_wstcc'),
                array('suporteorientacao'), $roles));

        $ADMIN->add('papeistcc', $settings);
    }
}
```

Some ao fim de `local/wstcc/lang/en/local_wstcc.php`:

```php
$string['papeis_tcc'] = 'Papéis de TCC';
$string['papeis_tcc_settings'] = 'Papéis de TCC';
$string['settings_coordtcc_roles'] = 'Papéis do Coordenador de TCC';
$string['description_coordtcc_roles'] = 'Papéis do Moodle que identificam o Coordenador de TCC. Atribuídos no CURSO — atribuição na categoria não é reconhecida.';
$string['settings_suporte_roles'] = 'Papéis do Suporte de Orientação';
$string['description_suporte_roles'] = 'Papéis do Moodle que identificam o Suporte de Orientação. O alcance vem dos grupos do relacionamento de orientação.';
$string['turma_ufsc_nao_encontrada'] = 'Não foi possível resolver a turma a partir deste curso.';
```

- [ ] **Step 6: Verificar que a tela carrega de verdade**

⚠️ **`admin/cli/upgrade.php` NÃO serve aqui.** Esta tarefa não muda a versão do plugin,
então o upgrade é no-op e **não avalia o `settings.php`** — um erro de sintaxe ou uma
string faltando passariam despercebidos. Verificação executada em 31/08, que pega os dois:

```bash
cat > /var/www/local/wstcc/verifica_settings.php <<'EOF'
<?php
define('CLI_SCRIPT', true);
require(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
// ⚠️ Sem isto, $hassiteconfig e' falso em CLI e o settings.php nem e' avaliado.
\core\session\manager::set_user(get_admin());
$root = admin_get_root(true, true);
$pagina = $root->locate('papeis_tcc_settings');
echo "pagina: ", $pagina->visiblename, "\n";
foreach ($pagina->settings as $s) {
    echo "  ", $s->name, " -> ", $s->visiblename, "\n";
}
echo "categoria: ", $root->locate('papeistcc')->visiblename, "\n";
EOF
docker exec local-moodle-unasus-dev-405 php /var/www/local/wstcc/verifica_settings.php
rm -f /var/www/local/wstcc/verifica_settings.php
```

Esperado, **com os nomes traduzidos e sem `[[`**:

```
pagina: Papéis de TCC
  local_wstcc_coordtcc_roles -> Papéis do Coordenador de TCC
  local_wstcc_suporte_roles -> Papéis do Suporte de Orientação
categoria: Papéis de TCC
```

⚠️ Se sair `[[papeis_tcc_settings]]`, são as strings em cache — purgue e repita:

```bash
docker exec local-moodle-unasus-dev-405 bash -c "cd /var/www && php admin/cli/purge_caches.php"
```

- [ ] **Step 7: Commit**

```bash
git add local/wstcc/locallib.php local/wstcc/settings.php \
        local/wstcc/lang/en/local_wstcc.php local/wstcc/tests/papeis_test.php
git commit -m "feat(papeis): configuracao dos papeis de TCC com default em runtime"
```

---

## Task 2: A1 — `get_papeis_tcc`

**Files:**
- Modify: `local/wstcc/externallib.php` (somar métodos ao fim da classe)
- Modify: `local/wstcc/db/services.php`
- Modify: `local/wstcc/version.php`
- Modify: `local/wstcc/tests/papeis_test.php`

**Interfaces:**
- Consumes: `local_wstcc_mapa_papeis()` (Task 1).
- Produces: `local_wstcc_external::get_papeis_tcc($userid, $courseid)` → `array('papeis' => array de string)`.

- [ ] **Step 1: Write the failing test**

Some à classe `local_wstcc_papeis_testcase` (e ao topo do arquivo, junto do outro require):

```php
require_once($CFG->dirroot . '/local/wstcc/externallib.php');
```

⚠️ **Todo teste da A1 precisa definir as TRÊS configs**, não só a que está sob exame.
`local_wstcc_mapa_papeis()` lê as três, e config vazia dispara `debugging()` — que estoura
o `assertDebugging*` do próprio teste e esconde o aviso que ele quer observar. Medido em
31/08: sem isto, 4 de 4 testes da A1 quebram com *"Unexpected debugging() call"*.

```php
    /**
     * Define as TRES configs de papel. Ver o aviso acima.
     */
    protected function configurar_papeis() {
        set_config('local_wstcc_coordtcc_roles', 'coordtcc');
        set_config('local_wstcc_suporte_roles', 'suporteorientacao');
        set_config('local_tutores_orientador_roles', 'orientador');
    }
```

Nos quatro testes abaixo, use `$this->configurar_papeis();` no lugar dos `set_config`
individuais.

```php
    /**
     * Cria papel atribuivel em curso E categoria, como coordtcc e
     * suporteorientacao sao no ambiente real.
     */
    protected function criar_papel($shortname) {
        $roleid = create_role($shortname, $shortname, '');
        set_role_contextlevels($roleid, array(CONTEXT_COURSECAT, CONTEXT_COURSE));
        return $roleid;
    }

    public function test_a1_devolve_coordenador_tcc() {
        $this->resetAfterTest();

        set_config('local_wstcc_coordtcc_roles', 'coordtcc');
        $roleid = $this->criar_papel('coordtcc');

        $curso = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        role_assign($roleid, $user->id, context_course::instance($curso->id)->id);

        $retorno = local_wstcc_external::get_papeis_tcc($user->id, $curso->id);
        $retorno = external_api::clean_returnvalue(
                local_wstcc_external::get_papeis_tcc_returns(), $retorno);

        $this->assertEquals(array('coordenador_tcc'), $retorno['papeis']);
    }

    public function test_a1_acumula_papeis_e_ignora_os_nao_configurados() {
        $this->resetAfterTest();

        set_config('local_wstcc_coordtcc_roles', 'coordtcc');
        set_config('local_wstcc_suporte_roles', 'suporteorientacao');
        $coord = $this->criar_papel('coordtcc');
        $suporte = $this->criar_papel('suporteorientacao');
        $outro = $this->criar_papel('papelqualquer');

        $curso = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $ctx = context_course::instance($curso->id)->id;
        role_assign($coord, $user->id, $ctx);
        role_assign($suporte, $user->id, $ctx);
        role_assign($outro, $user->id, $ctx);

        $retorno = local_wstcc_external::get_papeis_tcc($user->id, $curso->id);

        sort($retorno['papeis']);
        $this->assertEquals(array('coordenador_tcc', 'suporte_orientacao'), $retorno['papeis']);
    }

    public function test_a1_nao_enxerga_atribuicao_na_categoria() {
        $this->resetAfterTest();

        set_config('local_wstcc_coordtcc_roles', 'coordtcc');
        $roleid = $this->criar_papel('coordtcc');

        $categoria = $this->getDataGenerator()->create_category();
        $curso = $this->getDataGenerator()->create_course(array('category' => $categoria->id));
        $user = $this->getDataGenerator()->create_user();

        // Atribuicao na CATEGORIA, nao no curso.
        role_assign($roleid, $user->id, context_coursecat::instance($categoria->id)->id);

        $retorno = local_wstcc_external::get_papeis_tcc($user->id, $curso->id);

        // Decisao de 30/08: so' o contexto do curso conta. O desencontro vira log.
        $this->assertEquals(array(), $retorno['papeis']);
        $this->assertDebuggingCalled();
    }

    public function test_a1_sem_papel_nenhum_devolve_lista_vazia_sem_avisar() {
        $this->resetAfterTest();

        set_config('local_wstcc_coordtcc_roles', 'coordtcc');
        $this->criar_papel('coordtcc');

        $curso = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $retorno = local_wstcc_external::get_papeis_tcc($user->id, $curso->id);

        $this->assertEquals(array(), $retorno['papeis']);
        $this->assertDebuggingNotCalled();
    }
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker exec -e XDEBUG_MODE=off local-moodle-unasus-dev-405 \
  bash -c "cd /var/www && php vendor/bin/phpunit --testsuite local_wstcc_testsuite --filter test_a1_"
```

Esperado: FAIL — `Call to undefined method local_wstcc_external::get_papeis_tcc()`.

- [ ] **Step 3: Write minimal implementation**

Some ao fim da classe `local_wstcc_external`, em `externallib.php`:

```php
    public static function get_papeis_tcc_parameters() {
        $keys = array(
                'userid' => new external_value(PARAM_INT, 'User id', VALUE_REQUIRED),
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
        );

        return new external_function_parameters($keys);
    }

    /**
     * Devolve os papeis de TCC que a pessoa tem NESTE curso, em nome semantico.
     *
     * Existe porque o launch LTI do Moodle 4.5 nao carrega papel customizado:
     * todos chegam como Learner, e a identidade tem que vir do dado.
     */
    public static function get_papeis_tcc($userid, $courseid) {
        global $CFG;
        require_once($CFG->dirroot . '/local/wstcc/locallib.php');

        $params = self::validate_parameters(self::get_papeis_tcc_parameters(),
                array('userid' => $userid, 'courseid' => $courseid));

        $context = context_course::instance($params['courseid']);
        $mapa = local_wstcc_mapa_papeis();

        // ⚠️ false = SO' o contexto do curso. Os papeis de TCC sao atribuiveis
        // tambem em categoria (contextlevel 40), e o modelo institucional e'
        // turma = categoria -- entao quem cadastrar na categoria produz uma
        // pessoa que entra como estudante, em silencio. Esta e' a decisao
        // conservadora de 30/08; o desencontro e' denunciado no log abaixo.
        $papeis = self::mapear_papeis(get_user_roles($context, $params['userid'], false), $mapa);

        if (empty($papeis)) {
            $acima = self::mapear_papeis(get_user_roles($context, $params['userid'], true), $mapa);
            if (!empty($acima)) {
                debugging("local_wstcc: usuario {$params['userid']} tem papel de TCC em contexto ACIMA "
                        . "do curso {$params['courseid']} (" . implode(',', $acima) . "), e nao no curso. "
                        . "Atribua no curso.", DEBUG_DEVELOPER);
            }
        }

        return array('papeis' => $papeis);
    }

    /**
     * Traduz registros de get_user_roles em nomes semanticos, sem repetir.
     */
    protected static function mapear_papeis($roles, $mapa) {
        $papeis = array();
        foreach ($roles as $role) {
            if (isset($mapa[$role->shortname])) {
                $papeis[$mapa[$role->shortname]] = true;
            }
        }
        return array_keys($papeis);
    }

    public static function get_papeis_tcc_returns() {
        return new external_single_structure(array(
                'papeis' => new external_multiple_structure(
                        new external_value(PARAM_ALPHAEXT, 'Papel semantico de TCC'),
                        'Papeis de TCC no curso')
        ), 'Papeis de TCC');
    }
```

- [ ] **Step 4: Run test to verify it passes**

Mesmo comando do Step 2. Esperado: `OK (4 tests, ...)`.

- [ ] **Step 5: Publicar a função no serviço**

Em `db/services.php`, some ao array `$functions`:

```php
        'local_wstcc_get_papeis_tcc' => array(
                'classname' => 'local_wstcc_external',
                'methodname' => 'get_papeis_tcc',
                'classpath' => 'local/wstcc/externallib.php',
                'description' => 'Retorna os papéis de TCC do usuário no curso.',
                'type' => 'read',
        ),
```

e some `'local_wstcc_get_papeis_tcc',` à lista `functions` do serviço `'TCC Services'`.

Em `version.php`, troque a linha da versão:

```php
$plugin->version  = 202608310100;
```

- [ ] **Step 6: Rodar o upgrade e conferir que a função entrou no serviço**

```bash
docker exec local-moodle-unasus-dev-405 \
  bash -c "cd /var/www && php admin/cli/upgrade.php --non-interactive"

docker exec docker-mysql80 mysql -uroot -p'#m00m00' -N -e "
USE moodle_405_unasus_local;
SELECT ef.name FROM external_services_functions esf
  JOIN external_services es ON es.id = esf.externalserviceid
  JOIN external_functions ef ON ef.name = esf.functionname
 WHERE es.shortname = 'wstcc_webservice' AND ef.name LIKE '%papeis%';"
```

Esperado: uma linha, `local_wstcc_get_papeis_tcc`. Se vier vazio, confira o `component` do serviço antes de qualquer outra coisa — ver o spec.

- [ ] **Step 7: Commit**

```bash
git add local/wstcc/externallib.php local/wstcc/db/services.php \
        local/wstcc/version.php local/wstcc/tests/papeis_test.php
git commit -m "feat(papeis): get_papeis_tcc devolve papel semantico do curso"
```

---

## Task 2b: Check de estado do mapa de papéis ✅ ENTREGUE

**Não estava no plano original.** Entrou depois que a execução da Task 2 mostrou que o
`debugging()` pedido pela equipe do TCC **não aparece em produção** — ele só produz saída
com depuração de desenvolvedor ligada. Um aviso que só existe em desenvolvimento é pior
que nenhum: cria a sensação de rede de proteção onde não há.

Log por chamada foi descartado pelo motivo oposto: `local_wstcc_mapa_papeis()` lê as três
configs, então uma instalação que use só o coordenador dispararia a linha em **toda**
requisição — e linha que sempre aparece deixa de ser lida. É o defeito que a #63 registrou
no relatório do sync.

**Solução: estado consultável, não anunciado.** `\local_wstcc\check\papeis`, na API de
checks do 4.x, visível em *Administração do site → Relatórios → Verificações de estado*.

**Files:**
- Create: `local/wstcc/classes/check/papeis.php`, `local/wstcc/lib.php`, `local/wstcc/tests/check_papeis_test.php`
- Modify: `local/wstcc/lang/en/local_wstcc.php` (6 strings), `local/wstcc/locallib.php` (a lição)

Três estados, e o terceiro é o que justifica o check:

| situação | resultado |
|---|---|
| as três configs preenchidas, papéis existem | `OK` |
| alguma config em branco (default responde) | `INFO` |
| **nenhum papel existente responde por um semântico** | `WARNING` |

⚠️ O check lê pela **mesma** rotina do web service (`local_wstcc_papeis_configurados()`).
Se as duas divergissem, ele descreveria um mapa que não é o aplicado. O efeito colateral é
que herda o `debugging()` dela — por isso o teste do caso `INFO` afirma
`assertDebuggingCalledCount(3)`.

⚠️ **Armadilha de namespace:** dentro de `namespace local_wstcc\check`, `html_writer`
resolve para `local_wstcc\check\html_writer`. Precisa de `\html_writer::`. Funções
soltas como `s()` caem no global e não precisam.

Verificado em execução: `status: ok`, com o detalhe listando os três semânticos e seus
shortnames.

---

## Task 3: A2 — grupos de orientação da turma

**Files:**
- Modify: `local/wstcc/externallib.php`
- Modify: `local/wstcc/db/services.php`
- Modify: `local/wstcc/version.php`
- Modify: `local/wstcc/tests/papeis_test.php`

**Interfaces:**
- Consumes: `\local_tutores\categoria::turma_ufsc($courseid)`, `local_tutores_grupo_orientacao::get_relationship_orientacao($categoria_turma)`, e o helper de teste `$this->criar_papel($shortname)` **definido na Task 2** — se você está fazendo esta tarefa isolada, ele precisa existir na classe de teste.
- Produces: `local_wstcc_external::get_grupos_orientacao($courseid)` → `array('grupos' => array de array('id', 'nome', 'orientadores', 'suportes', 'estudantes'))`, cada lista com elementos `array('userid' => int)`.

- [ ] **Step 1: Write the failing test**

Some ao topo de `tests/papeis_test.php`, junto dos outros requires:

```php
// tag/lib.php antes do lib.php do relationship: relationship_add_relationship()
// chama tag_set().
require_once($CFG->dirroot . '/tag/lib.php');
require_once($CFG->dirroot . '/local/relationship/lib.php');
require_once($CFG->dirroot . '/local/tutores/lib.php');
```

Some à classe:

```php
    /**
     * Monta uma turma de orientacao completa e devolve os ids uteis.
     *
     * ⚠️ FIXTURE OBRIGATORIA: o orientador A esta em DOIS grupos, com suportes
     * distintos. Os dados do ambiente real nao exercitam esse caso (cada
     * orientador esta em exatamente um grupo), e e' exatamente o caso que o
     * retorno com estudantes existe para acertar: sem eles, os TCCs do
     * orientador receberiam os suportes dos DOIS grupos.
     */
    protected function montar_turma() {
        global $DB;

        $gen = $this->getDataGenerator();

        $studentroleid = $DB->get_field('role', 'id', array('shortname' => 'student'), MUST_EXIST);
        $orientadorroleid = $DB->get_field('role', 'id', array('shortname' => 'editingteacher'), MUST_EXIST);
        $suporteroleid = $this->criar_papel('suporteorientacao');

        set_config('local_tutores_student_roles', 'student');
        set_config('local_tutores_orientador_roles', 'editingteacher');
        set_config('local_wstcc_suporte_roles', 'suporteorientacao');

        $categoria = $gen->create_category();
        $catcontext = context_coursecat::instance($categoria->id);
        $curso = $gen->create_course(array('category' => $categoria->id));

        $relationshipid = relationship_add_relationship((object) array(
                'contextid' => $catcontext->id,
                'name' => 'Grupos de Orientação',
                'tags' => array('grupo_orientacao'),
        ));

        $cohorts = array();
        foreach (array('estudante' => $studentroleid,
                       'orientador' => $orientadorroleid,
                       'suporte' => $suporteroleid) as $papel => $roleid) {
            $cohort = $gen->create_cohort(array('contextid' => $catcontext->id));
            $cohorts[$papel] = relationship_add_cohort((object) array(
                    'relationshipid' => $relationshipid,
                    'cohortid' => $cohort->id,
                    'roleid' => $roleid,
                    // 1 porque o suporte apoia varios grupos: com o default 0 o
                    // seletor deixa de oferecer a pessoa depois do primeiro.
                    'allowdupsingroups' => 1,
                    'uniformdistribution' => 0,
            ));
        }

        $grupos = array();
        foreach (array('A', 'B') as $letra) {
            $grupos[$letra] = relationship_add_group((object) array(
                    'relationshipid' => $relationshipid, 'name' => "Grupo {$letra}",
                    'userlimit' => 0, 'uniformdistribution' => 0));
        }

        $u = array();
        foreach (array('orientador_ab', 'suporte_a', 'suporte_a2', 'suporte_b',
                       'estudante_a', 'estudante_b') as $nome) {
            $u[$nome] = $gen->create_user(array('firstname' => $nome));
        }

        // O MESMO orientador nos dois grupos -- o caso que os dados reais nao tem.
        relationship_add_member($grupos['A'], $cohorts['orientador'], $u['orientador_ab']->id);
        relationship_add_member($grupos['B'], $cohorts['orientador'], $u['orientador_ab']->id);

        relationship_add_member($grupos['A'], $cohorts['suporte'], $u['suporte_a']->id);
        relationship_add_member($grupos['A'], $cohorts['suporte'], $u['suporte_a2']->id);
        relationship_add_member($grupos['B'], $cohorts['suporte'], $u['suporte_b']->id);

        relationship_add_member($grupos['A'], $cohorts['estudante'], $u['estudante_a']->id);
        relationship_add_member($grupos['B'], $cohorts['estudante'], $u['estudante_b']->id);

        // Papel no curso para todo mundo que tem papel de grupo: e' o cadastro
        // real, e o que evita o log de desencontro da Task 5.
        $ctx = context_course::instance($curso->id)->id;
        role_assign($suporteroleid, $u['suporte_a']->id, $ctx);
        role_assign($suporteroleid, $u['suporte_a2']->id, $ctx);
        role_assign($suporteroleid, $u['suporte_b']->id, $ctx);

        return array('courseid' => $curso->id, 'grupos' => $grupos, 'users' => $u);
    }

    /** Extrai os userid de uma das listas de um grupo do retorno. */
    protected function ids($grupo, $chave) {
        $ids = array();
        foreach ($grupo[$chave] as $membro) {
            $ids[] = $membro['userid'];
        }
        sort($ids);
        return $ids;
    }

    public function test_a2_devolve_grupos_com_membros_rotulados_por_papel() {
        $this->resetAfterTest();

        $t = $this->montar_turma();

        $retorno = local_wstcc_external::get_grupos_orientacao($t['courseid']);
        $retorno = external_api::clean_returnvalue(
                local_wstcc_external::get_grupos_orientacao_returns(), $retorno);

        $this->assertCount(2, $retorno['grupos']);

        $porid = array();
        foreach ($retorno['grupos'] as $grupo) {
            $porid[$grupo['id']] = $grupo;
        }

        $a = $porid[$t['grupos']['A']];
        $b = $porid[$t['grupos']['B']];

        $this->assertEquals('Grupo A', $a['nome']);
        $this->assertEquals(array($t['users']['orientador_ab']->id), $this->ids($a, 'orientadores'));
        $this->assertEquals(array($t['users']['estudante_a']->id), $this->ids($a, 'estudantes'));

        $esperado = array($t['users']['suporte_a']->id, $t['users']['suporte_a2']->id);
        sort($esperado);
        $this->assertEquals($esperado, $this->ids($a, 'suportes'));

        // ⚠️ O CASO QUE OS DADOS REAIS NAO TEM: o mesmo orientador esta nos dois
        // grupos, e o suporte do grupo B NAO pode aparecer no grupo A. Se esta
        // assercao cair, o suporte ganha acesso a aluno que nao acompanha.
        $this->assertNotContains($t['users']['suporte_b']->id, $this->ids($a, 'suportes'));
        $this->assertEquals(array($t['users']['suporte_b']->id), $this->ids($b, 'suportes'));
        $this->assertEquals(array($t['users']['estudante_b']->id), $this->ids($b, 'estudantes'));
    }
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker exec -e XDEBUG_MODE=off local-moodle-unasus-dev-405 \
  bash -c "cd /var/www && php vendor/bin/phpunit --testsuite local_wstcc_testsuite --filter test_a2_devolve_grupos"
```

Esperado: FAIL — `Call to undefined method local_wstcc_external::get_grupos_orientacao()`.

- [ ] **Step 3: Write minimal implementation**

Some à classe `local_wstcc_external`:

```php
    public static function get_grupos_orientacao_parameters() {
        $keys = array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
        );

        return new external_function_parameters($keys);
    }

    /**
     * Devolve os grupos de orientacao da turma, com os membros rotulados.
     *
     * ⚠️ Uma chamada POR TURMA. O SyncTcc ja' faz uma chamada por aluno para o
     * orientador; perguntar o suporte por aluno dobraria o custo do sync. Como o
     * suporte e' do GRUPO, uma chamada devolve tudo e o app distribui.
     *
     * ⚠️ NAO reusa get_grupos_orientacao_by_userid(): o ramo com lista chama
     * report_unasus_int_array_to_sql(), do report_unasus, que nao esta instalado
     * nesta arvore. Aqui o IN sai do get_in_or_equal, padrao do proprio lib.php.
     */
    public static function get_grupos_orientacao($courseid) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/wstcc/locallib.php');

        $params = self::validate_parameters(self::get_grupos_orientacao_parameters(),
                array('courseid' => $courseid));

        $categoria_turma = \local_tutores\categoria::turma_ufsc($params['courseid']);
        $relationship = local_tutores_grupo_orientacao::get_relationship_orientacao($categoria_turma);

        $cohorts = self::cohorts_por_papel($relationship->id);

        $sql = "SELECT rg.id AS grupoid, rg.name AS nome, rm.userid, rm.relationshipcohortid
                  FROM {relationship_groups} rg
             LEFT JOIN {relationship_members} rm
                    ON (rm.relationshipgroupid = rg.id)
                 WHERE rg.relationshipid = :relationshipid
              ORDER BY rg.name, rm.userid";

        $linhas = $DB->get_recordset_sql($sql, array('relationshipid' => $relationship->id));

        $grupos = array();
        foreach ($linhas as $linha) {
            if (!isset($grupos[$linha->grupoid])) {
                $grupos[$linha->grupoid] = array(
                        'id' => (int) $linha->grupoid,
                        'nome' => $linha->nome,
                        'orientadores' => array(),
                        'suportes' => array(),
                        'estudantes' => array(),
                );
            }
            if (empty($linha->userid) || !isset($cohorts[$linha->relationshipcohortid])) {
                continue;
            }
            $chave = $cohorts[$linha->relationshipcohortid];
            $grupos[$linha->grupoid][$chave][] = array('userid' => (int) $linha->userid);
        }
        $linhas->close();

        return array('grupos' => array_values($grupos));
    }

    /**
     * Mapa relationship_cohorts.id => chave da lista no retorno.
     */
    protected static function cohorts_por_papel($relationshipid) {
        $mapa = array();

        $conjuntos = array(
                'estudantes' => local_tutores_base_group::get_relationship_cohorts_estudantes($relationshipid),
                'orientadores' => local_tutores_grupo_orientacao::get_relationship_cohorts_orientadores($relationshipid),
                'suportes' => self::relationship_cohorts_suportes($relationshipid),
        );

        foreach ($conjuntos as $chave => $cohorts) {
            foreach (array_keys($cohorts) as $rcid) {
                $mapa[$rcid] = $chave;
            }
        }

        return $mapa;
    }

    /**
     * Cohorts do papel de suporte no relationship.
     *
     * ⚠️ Vive aqui, e nao no local_tutores_orientador_roles: incluir o suporte
     * naquela config o faria ser devolvido como orientador RESPONSAVEL do aluno,
     * corrompendo o vinculo gravado pelo SyncTcc.
     */
    protected static function relationship_cohorts_suportes($relationshipid) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/wstcc/locallib.php');

        $shortnames = local_wstcc_papeis_configurados('local_wstcc_suporte_roles', 'suporteorientacao');
        list($in, $inparams) = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED, 'shortname');

        $sql = "SELECT rc.*
                  FROM {relationship_cohorts} rc
                  JOIN {role} r ON (r.id = rc.roleid)
                 WHERE rc.relationshipid = :relationshipid
                   AND r.shortname {$in}";

        return $DB->get_records_sql($sql,
                array_merge($inparams, array('relationshipid' => $relationshipid)));
    }

    public static function get_grupos_orientacao_returns() {
        $membro = new external_single_structure(array(
                'userid' => new external_value(PARAM_INT, 'Moodle user id'),
        ));

        return new external_single_structure(array(
                'grupos' => new external_multiple_structure(
                        new external_single_structure(array(
                                'id' => new external_value(PARAM_INT, 'Id do grupo'),
                                'nome' => new external_value(PARAM_TEXT, 'Nome do grupo'),
                                'orientadores' => new external_multiple_structure($membro),
                                'suportes' => new external_multiple_structure($membro),
                                'estudantes' => new external_multiple_structure($membro),
                        )),
                        'Grupos de orientacao da turma')
        ), 'Grupos de orientacao');
    }
```

- [ ] **Step 4: Run test to verify it passes**

Mesmo comando do Step 2. Esperado: `OK (1 test, ...)`.

- [ ] **Step 5: Publicar a função no serviço**

Em `db/services.php`, some ao `$functions`:

```php
        'local_wstcc_get_grupos_orientacao' => array(
                'classname' => 'local_wstcc_external',
                'methodname' => 'get_grupos_orientacao',
                'classpath' => 'local/wstcc/externallib.php',
                'description' => 'Retorna os grupos de orientação da turma com orientadores, suportes e estudantes.',
                'type' => 'read',
        ),
```

e `'local_wstcc_get_grupos_orientacao',` à lista do serviço. Em `version.php`: `$plugin->version  = 202608310200;`

- [ ] **Step 6: Rodar o upgrade e conferir**

```bash
docker exec local-moodle-unasus-dev-405 \
  bash -c "cd /var/www && php admin/cli/upgrade.php --non-interactive"
docker exec -e XDEBUG_MODE=off local-moodle-unasus-dev-405 \
  bash -c "cd /var/www && php vendor/bin/phpunit --testsuite local_wstcc_testsuite"
```

Esperado: os testes novos passam; segue **1 erro pré-existente** em `test_get_users_by_field`.

- [ ] **Step 7: Commit**

```bash
git add local/wstcc/externallib.php local/wstcc/db/services.php \
        local/wstcc/version.php local/wstcc/tests/papeis_test.php
git commit -m "feat(papeis): get_grupos_orientacao devolve a turma com membros rotulados"
```

---

## Task 4: A2 — separar erro de vazio

**Files:**
- Modify: `local/wstcc/externallib.php:get_grupos_orientacao`
- Modify: `local/wstcc/tests/papeis_test.php`

**Interfaces:**
- Produces: exceção `moodle_exception('turma_ufsc_nao_encontrada', 'local_wstcc')` quando o curso não resolve turma.

- [ ] **Step 1: Write the failing test**

```php
    public function test_a2_curso_sem_turma_levanta_excecao_propria() {
        $this->resetAfterTest();

        // Curso numa categoria sem nenhum relationship: turma_ufsc() da' false.
        $categoria = $this->getDataGenerator()->create_category();
        $curso = $this->getDataGenerator()->create_course(array('category' => $categoria->id));

        // ⚠️ Tem que ser DISTINTA da de relationship ausente: o app usa o
        // errorcode para nao rebaixar a pessoa para estudante nem reescrever o
        // vinculo em falha de leitura.
        $this->expectException('moodle_exception');
        $this->expectExceptionMessageMatches('/turma/i');

        local_wstcc_external::get_grupos_orientacao($curso->id);
    }

    public function test_a2_turma_sem_relationship_de_orientacao_levanta_a_excecao_do_tutores() {
        $this->resetAfterTest();

        // A turma existe (ha' relationship com tag grupo_tutoria), mas nao ha'
        // relationship de ORIENTACAO.
        $categoria = $this->getDataGenerator()->create_category();
        $catcontext = context_coursecat::instance($categoria->id);
        $curso = $this->getDataGenerator()->create_course(array('category' => $categoria->id));

        relationship_add_relationship((object) array(
                'contextid' => $catcontext->id,
                'name' => 'Tutoria',
                'tags' => array('grupo_tutoria'),
        ));

        try {
            local_wstcc_external::get_grupos_orientacao($curso->id);
            $this->fail('Deveria ter levantado excecao.');
        } catch (moodle_exception $e) {
            $this->assertEquals('relationship_grupo_orientacao_not_available_error', $e->errorcode);
        }
    }

    public function test_a2_relationship_sem_grupos_devolve_lista_vazia() {
        $this->resetAfterTest();

        $categoria = $this->getDataGenerator()->create_category();
        $catcontext = context_coursecat::instance($categoria->id);
        $curso = $this->getDataGenerator()->create_course(array('category' => $categoria->id));

        relationship_add_relationship((object) array(
                'contextid' => $catcontext->id,
                'name' => 'Orientação vazia',
                'tags' => array('grupo_orientacao'),
        ));

        $retorno = local_wstcc_external::get_grupos_orientacao($curso->id);

        // Vazio LITERAL: "esta turma nao tem grupos". Nao e' erro.
        $this->assertEquals(array(), $retorno['grupos']);
    }
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker exec -e XDEBUG_MODE=off local-moodle-unasus-dev-405 \
  bash -c "cd /var/www && php vendor/bin/phpunit --testsuite local_wstcc_testsuite --filter test_a2_curso_sem_turma"
```

Esperado: FAIL — a exceção que sobe é `relationship_grupo_orientacao_not_available_error`, não a da turma. É exatamente a conflação que o `FIXME` do `local_tutores` descreve.

- [ ] **Step 3: Write minimal implementation**

Em `get_grupos_orientacao`, entre a resolução da turma e a busca do relationship:

```php
        $categoria_turma = \local_tutores\categoria::turma_ufsc($params['courseid']);

        // ⚠️ get_relationship() interpola a categoria direto no SQL, sob um FIXME
        // que diz isso. Com false, vira LIKE '%//%', nao casa com nada, e cai no
        // MESMO throw de "relationship nao existe" -- foi assim que a #63
        // (tutoria) ficou com a causa ambigua. Validar aqui separa os estados.
        if (empty($categoria_turma)) {
            throw new moodle_exception('turma_ufsc_nao_encontrada', 'local_wstcc', '', null,
                    "Course: {$params['courseid']}");
        }

        $relationship = local_tutores_grupo_orientacao::get_relationship_orientacao($categoria_turma);
```

- [ ] **Step 4: Run test to verify it passes**

```bash
docker exec -e XDEBUG_MODE=off local-moodle-unasus-dev-405 \
  bash -c "cd /var/www && php vendor/bin/phpunit --testsuite local_wstcc_testsuite --filter test_a2_"
```

Esperado: `OK (4 tests, ...)`.

- [ ] **Step 5: Commit**

```bash
git add local/wstcc/externallib.php local/wstcc/tests/papeis_test.php
git commit -m "fix(papeis): turma nao resolvida deixa de virar 'relationship ausente'"
```

---

## Task 5: A2 — log do suporte sem papel no curso

**Files:**
- Modify: `local/wstcc/externallib.php:get_grupos_orientacao`
- Modify: `local/wstcc/tests/papeis_test.php`

**Interfaces:**
- Consumes: `local_wstcc_papeis_configurados()` (Task 1), o retorno montado na Task 3.

- [ ] **Step 1: Write the failing test**

```php
    public function test_a2_avisa_quando_o_suporte_do_grupo_nao_tem_papel_no_curso() {
        $this->resetAfterTest();

        $t = $this->montar_turma();


        // Tira o papel do suporte_b no curso, mantendo-o no grupo. E' o
        // desencontro "grupo sem papel": a A1 nao o reconhece, ele cai na tela
        // de aluno em silencio -- e sem este log ninguem descobre por que.
        global $DB;

        $ctx = context_course::instance($t['courseid'])->id;
        $roleid = $DB->get_field('role', 'id',
                array('shortname' => 'suporteorientacao'), MUST_EXIST);
        role_unassign($roleid, $t['users']['suporte_b']->id, $ctx);

        local_wstcc_external::get_grupos_orientacao($t['courseid']);

        $this->assertDebuggingCalled();
    }
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker exec -e XDEBUG_MODE=off local-moodle-unasus-dev-405 \
  bash -c "cd /var/www && php vendor/bin/phpunit --testsuite local_wstcc_testsuite --filter test_a2_avisa_quando"
```

Esperado: FAIL — `Expected debugging() message`.

- [ ] **Step 3: Write minimal implementation**

Antes do `return` de `get_grupos_orientacao`:

```php
        self::avisar_suportes_sem_papel($grupos, $params['courseid']);

        return array('grupos' => array_values($grupos));
```

E some o método:

```php
    /**
     * Loga quem e' suporte no GRUPO mas nao tem o papel no CURSO.
     *
     * A identidade vem do papel (a A1 so' olha role_assignments); o alcance vem
     * do grupo. Quem esta no grupo sem o papel nao e' reconhecido e cai na tela
     * de aluno, sem erro nenhum -- este log e' o que transforma isso numa linha
     * em vez de uma investigacao. Sai de graca: os dois conjuntos ja' estao em
     * maos neste request.
     */
    protected static function avisar_suportes_sem_papel($grupos, $courseid) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/wstcc/locallib.php');

        $userids = array();
        foreach ($grupos as $grupo) {
            foreach ($grupo['suportes'] as $membro) {
                $userids[$membro['userid']] = true;
            }
        }
        if (empty($userids)) {
            return;
        }

        $shortnames = local_wstcc_papeis_configurados('local_wstcc_suporte_roles', 'suporteorientacao');
        $context = context_course::instance($courseid);

        list($usersql, $userparams) = $DB->get_in_or_equal(array_keys($userids), SQL_PARAMS_NAMED, 'userid');
        list($rolesql, $roleparams) = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED, 'shortname');

        $sql = "SELECT DISTINCT ra.userid
                  FROM {role_assignments} ra
                  JOIN {role} r ON (r.id = ra.roleid)
                 WHERE ra.contextid = :contextid
                   AND ra.userid {$usersql}
                   AND r.shortname {$rolesql}";

        $compapel = $DB->get_records_sql($sql,
                array_merge($userparams, $roleparams, array('contextid' => $context->id)));

        $sempapel = array_diff(array_keys($userids), array_keys($compapel));
        if (!empty($sempapel)) {
            debugging("local_wstcc: usuarios " . implode(',', $sempapel) . " sao suporte em grupo "
                    . "de orientacao do curso {$courseid} mas NAO tem o papel de suporte no curso. "
                    . "A ferramenta de TCC nao os reconhecera.", DEBUG_DEVELOPER);
        }
    }
```

- [ ] **Step 4: Run test to verify it passes**

Mesmo comando do Step 2. Esperado: `OK (1 test, ...)`.

- [ ] **Step 5: Verificar que o caminho feliz NÃO loga**

Some ao fim de `test_a2_devolve_grupos_com_membros_rotulados_por_papel`:

```php
        // Cadastro coerente nao produz aviso: log que sempre aparece deixa de ser lido.
        $this->assertDebuggingNotCalled();
```

Rode `--filter test_a2_devolve_grupos`. Esperado: `OK`.

- [ ] **Step 6: Commit**

```bash
git add local/wstcc/externallib.php local/wstcc/tests/papeis_test.php
git commit -m "feat(papeis): avisa quando o suporte do grupo nao tem papel no curso"
```

---

## Task 6: Verificação de integração e fechamento

**Files:**
- Modify: `local/wstcc/docs/2026-08-30-papeis-tcc-webservice-design.md` (marcar o que foi entregue)

- [ ] **Step 1: Rodar a suíte inteira do plugin**

```bash
docker exec -e XDEBUG_MODE=off local-moodle-unasus-dev-405 \
  bash -c "cd /var/www && php vendor/bin/phpunit --testsuite local_wstcc_testsuite"
```

Esperado: **12 testes novos passando**, e o `Errors: 1` pré-existente de `test_get_users_by_field` — e **só** ele.

- [ ] **Step 2: Rodar também a suíte do local_tutores**

```bash
docker exec -e XDEBUG_MODE=off local-moodle-unasus-dev-405 \
  bash -c "cd /var/www && php vendor/bin/phpunit --testsuite local_tutores_testsuite"
```

Esperado: sem regressão. As funções novas leem os accessors do `local_tutores`; se algo lá quebrou, é aqui que aparece.

- [ ] **Step 3: Conferir as duas funções no serviço**

```bash
docker exec docker-mysql80 mysql -uroot -p'#m00m00' -N -e "
USE moodle_405_unasus_local;
SELECT es.shortname, es.component, COUNT(esf.id) AS funcoes
  FROM external_services es
  LEFT JOIN external_services_functions esf ON esf.externalserviceid = es.id
 WHERE es.shortname = 'wstcc_webservice' GROUP BY es.id;"
```

Esperado: `wstcc_webservice | local_wstcc | 14` (eram 12).

⚠️ Se o `component` vier vazio, **pare**: o serviço foi criado à mão e o upgrade não atualiza a lista de funções. O caminho é `UPDATE` na linha existente, **nunca** recriar o serviço — o token vive nele.

- [ ] **Step 4: Prova por reversão**

Reverta uma peça e confirme que o teste correspondente fica vermelho pelo motivo certo. Faça pelo menos estas duas:

1. troque o `false` por `true` em `get_user_roles` → `test_a1_nao_enxerga_atribuicao_na_categoria` deve falhar;
2. remova a validação de `$categoria_turma` da Task 4 → `test_a2_curso_sem_turma_levanta_excecao_propria` deve falhar.

Desfaça as duas reversões depois. Teste que continua verde com a peça removida não testa a peça.

- [ ] **Step 5: Commit e push**

```bash
git add local/wstcc/docs/2026-08-30-papeis-tcc-webservice-design.md
git commit -m "docs(papeis): marca A1, A2 e configuracoes como entregues"
git push origin MOODLE_405_STABLE
```

⚠️ E atualize o gitlink no superprojeto, senão o `unasus-dev` continua apontando para o commit antigo:

```bash
cd /home/rsc/workspace/docker/php83/www/unasus-dev
git commit -o local/wstcc -m "chore(wstcc): sobe o ponteiro para as funcoes de papel de TCC"
git push origin UFSC_405_STABLE-DOCKER_UNASUS
```

⚠️ O superprojeto costuma ter outras coisas no index. Use `git commit -o local/wstcc` (commit parcial), **não** `git commit -a`.

---

## Fora deste plano

| item | onde vive |
|---|---|
| Tabela `tcc_suportes`, consumo da A1/A2, escopo × capacidade | `sistema-tcc-r8` (handoff do spec) |
| Porte do `report_unasus` | `docs/2026-08-30-porte-report-unasus-design.md` |
| Cadastro C1 (atribuir `coordtcc`) e C2 (cohort de suporte) na turma | operação, não código |
| Plano B do contexto 40 × 50 | gatilho é a primeira atribuição real de `coordtcc` |
