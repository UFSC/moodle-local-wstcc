<?php

/**
 * Testes das funções de papel de TCC do local_wstcc.
 *
 * @package local_wstcc
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/wstcc/locallib.php');
require_once($CFG->dirroot . '/local/wstcc/externallib.php');
// tag/lib.php antes do lib.php do relationship: relationship_add_relationship()
// chama tag_set().
require_once($CFG->dirroot . '/tag/lib.php');
require_once($CFG->dirroot . '/local/relationship/lib.php');
require_once($CFG->dirroot . '/local/tutores/lib.php');

class papeis_test extends advanced_testcase {

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

    /**
     * Define as TRES configs de papel.
     *
     * ⚠️ Necessario mesmo nos testes que so' usam uma: o mapa le as tres, e
     * config vazia dispara debugging() -- que estoura o assertDebugging* do
     * proprio teste. Deixar so' a config sob teste torna o exame ruidoso e
     * esconde o aviso que o teste realmente quer observar.
     */
    protected function configurar_papeis() {
        set_config('local_wstcc_coordtcc_roles', 'coordtcc');
        set_config('local_wstcc_suporte_roles', 'suporteorientacao');
        set_config('local_tutores_orientador_roles', 'orientador');
    }

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

        $this->configurar_papeis();
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

        $this->configurar_papeis();
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

        $this->configurar_papeis();
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

        $this->configurar_papeis();
        $this->criar_papel('coordtcc');

        $curso = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $retorno = local_wstcc_external::get_papeis_tcc($user->id, $curso->id);

        $this->assertEquals(array(), $retorno['papeis']);
        $this->assertDebuggingNotCalled();
    }

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
        set_config('local_wstcc_coordtcc_roles', 'coordtcc');

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

        // Papel no curso para quem tem papel de grupo: e' o cadastro real, e o
        // que evita o log de desencontro.
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

        // Cadastro coerente nao produz aviso: aviso que sempre aparece deixa de
        // ser lido, e passa a esconder o aviso de verdade.
        $this->assertDebuggingNotCalled();
    }

    public function test_a2_curso_sem_turma_levanta_excecao_propria() {
        $this->resetAfterTest();
        $this->configurar_papeis();

        // Curso numa categoria sem nenhum relationship: turma_ufsc() da' false.
        $categoria = $this->getDataGenerator()->create_category();
        $curso = $this->getDataGenerator()->create_course(array('category' => $categoria->id));

        try {
            local_wstcc_external::get_grupos_orientacao($curso->id);
            $this->fail('Deveria ter levantado moodle_exception.');
        } catch (moodle_exception $e) {
            // ⚠️ Tem que ser DISTINTA da de relationship ausente: o app usa o
            // errorcode para nao rebaixar a pessoa para estudante nem reescrever
            // o vinculo em falha de leitura.
            $this->assertEquals('turma_ufsc_nao_encontrada', $e->errorcode);
            $this->assertEquals('local_wstcc', $e->module);
            $this->assertStringNotContainsString('[[', $e->getMessage());
        }
    }

    public function test_a2_turma_sem_relationship_de_orientacao_levanta_a_excecao_do_tutores() {
        $this->resetAfterTest();
        $this->configurar_papeis();

        // A turma EXISTE (ha' relationship com tag grupo_tutoria), mas nao ha'
        // relationship de ORIENTACAO. E' o estado intermediario da #63.
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
            $this->fail('Deveria ter levantado moodle_exception.');
        } catch (moodle_exception $e) {
            $this->assertEquals('relationship_grupo_orientacao_not_available_error', $e->errorcode);
            $this->assertStringNotContainsString('[[', $e->getMessage());
        }
    }

    public function test_a2_relationship_sem_grupos_devolve_lista_vazia() {
        $this->resetAfterTest();

        // ⚠️ As configs precisam casar com os papeis dos cohorts criados abaixo.
        // local_tutores le $CFG->local_tutores_student_roles direto (lib.php:39) e
        // levanta "Undefined property" se ela nao existir.
        set_config('local_tutores_student_roles', 'student');
        set_config('local_tutores_orientador_roles', 'editingteacher');
        set_config('local_wstcc_suporte_roles', 'suporteorientacao');
        set_config('local_wstcc_coordtcc_roles', 'coordtcc');

        $categoria = $this->getDataGenerator()->create_category();
        $catcontext = context_coursecat::instance($categoria->id);
        $curso = $this->getDataGenerator()->create_course(array('category' => $categoria->id));

        $rid = relationship_add_relationship((object) array(
                'contextid' => $catcontext->id,
                'name' => 'Orientação vazia',
                'tags' => array('grupo_orientacao'),
        ));

        // Cohorts de papel existem; o que nao ha' sao GRUPOS.
        global $DB;
        $gen = $this->getDataGenerator();
        foreach (array('student', 'editingteacher') as $shortname) {
            $cohort = $gen->create_cohort(array('contextid' => $catcontext->id));
            relationship_add_cohort((object) array(
                    'relationshipid' => $rid,
                    'cohortid' => $cohort->id,
                    'roleid' => $DB->get_field('role', 'id', array('shortname' => $shortname), MUST_EXIST),
                    'allowdupsingroups' => 1, 'uniformdistribution' => 0));
        }
        $cohort = $gen->create_cohort(array('contextid' => $catcontext->id));
        relationship_add_cohort((object) array(
                'relationshipid' => $rid, 'cohortid' => $cohort->id,
                'roleid' => $this->criar_papel('suporteorientacao'),
                'allowdupsingroups' => 1, 'uniformdistribution' => 0));

        $retorno = local_wstcc_external::get_grupos_orientacao($curso->id);

        // Vazio LITERAL: "esta turma nao tem grupos". Nao e' erro.
        $this->assertEquals(array(), $retorno['grupos']);
    }

    public function test_a2_avisa_quando_o_suporte_do_grupo_nao_tem_papel_no_curso() {
        global $DB;

        $this->resetAfterTest();

        $t = $this->montar_turma();

        // Tira o papel do suporte_b no curso, mantendo-o no grupo. E' o
        // desencontro "grupo sem papel": a A1 nao o reconhece, ele cai na tela
        // de aluno em silencio -- e sem este log ninguem descobre por que.
        $ctx = context_course::instance($t['courseid'])->id;
        $roleid = $DB->get_field('role', 'id',
                array('shortname' => 'suporteorientacao'), MUST_EXIST);
        role_unassign($roleid, $t['users']['suporte_b']->id, $ctx);

        local_wstcc_external::get_grupos_orientacao($t['courseid']);

        $this->assertDebuggingCalled();
    }
}
