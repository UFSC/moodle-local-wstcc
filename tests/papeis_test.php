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
}
