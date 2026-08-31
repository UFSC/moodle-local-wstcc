<?php

/**
 * Testes das funções de papel de TCC do local_wstcc.
 *
 * @package local_wstcc
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
