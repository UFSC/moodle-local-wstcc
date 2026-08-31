<?php

/**
 * Testes do check de mapa de papéis do local_wstcc.
 *
 * @package local_wstcc
 */

defined('MOODLE_INTERNAL') || die();

class check_papeis_test extends advanced_testcase {

    /** Cria um papel com o shortname pedido, atribuivel em curso. */
    protected function criar_papel($shortname) {
        $roleid = create_role($shortname, $shortname, '');
        set_role_contextlevels($roleid, array(CONTEXT_COURSECAT, CONTEXT_COURSE));
        return $roleid;
    }

    public function test_tudo_configurado_e_existente_da_ok() {
        $this->resetAfterTest();

        $this->criar_papel('coordtcc');
        $this->criar_papel('suporteorientacao');
        $this->criar_papel('orientador');
        set_config('local_wstcc_coordtcc_roles', 'coordtcc');
        set_config('local_wstcc_suporte_roles', 'suporteorientacao');
        set_config('local_tutores_orientador_roles', 'orientador');

        $resultado = (new \local_wstcc\check\papeis())->get_result();

        $this->assertEquals(\core\check\result::OK, $resultado->get_status());
    }

    public function test_config_vazia_com_papel_existente_e_informativo() {
        $this->resetAfterTest();

        // Os papeis existem; so' a config nao foi preenchida. O default responde,
        // entao NAO e' defeito -- e' estado que quem investiga precisa enxergar.
        $this->criar_papel('coordtcc');
        $this->criar_papel('suporteorientacao');
        $this->criar_papel('orientador');
        set_config('local_wstcc_coordtcc_roles', '');
        set_config('local_wstcc_suporte_roles', '');
        set_config('local_tutores_orientador_roles', '');

        $resultado = (new \local_wstcc\check\papeis())->get_result();

        $this->assertEquals(\core\check\result::INFO, $resultado->get_status());
        $this->assertStringContainsString('padrão', $resultado->get_summary());

        // O check le pela MESMA rotina do web service -- entao herda o aviso de
        // desenvolvimento dela, um por config em branco. E' proposital: se as duas
        // divergissem, o check descreveria um mapa que nao e' o aplicado.
        $this->assertDebuggingCalledCount(3);
    }

    public function test_papel_inexistente_avisa() {
        $this->resetAfterTest();

        // ⚠️ O caso que realmente machuca: a config aponta para um papel que nao
        // existe, entao NINGUEM e' reconhecido como coordenador -- e a pessoa cai
        // na tela de estudante sem erro nenhum. E' o que este check torna visivel.
        $this->criar_papel('suporteorientacao');
        $this->criar_papel('orientador');
        set_config('local_wstcc_coordtcc_roles', 'papel_que_nao_existe');
        set_config('local_wstcc_suporte_roles', 'suporteorientacao');
        set_config('local_tutores_orientador_roles', 'orientador');

        $resultado = (new \local_wstcc\check\papeis())->get_result();

        $this->assertEquals(\core\check\result::WARNING, $resultado->get_status());
        $this->assertStringContainsString('coordenador_tcc', $resultado->get_summary());
    }

    public function test_o_check_esta_registrado_na_arvore_de_status() {
        $this->resetAfterTest();

        $refs = array();
        foreach (\core\check\manager::get_status_checks() as $check) {
            $refs[] = $check->get_ref();
        }

        // Sem o lib.php com local_wstcc_status_checks(), o check existe mas nao
        // aparece em lugar nenhum -- que e' o defeito que ele veio consertar.
        $this->assertContains('local_wstcc_papeis', $refs);
    }
}
