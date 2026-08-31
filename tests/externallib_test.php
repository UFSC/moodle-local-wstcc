<?php

/**
 * WSTcc External functions unit tests
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

class externallib_test extends externallib_advanced_testcase {

    protected function setUp(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/wstcc/externallib.php');
    }

    /**
     * Get Username Test
     */
    public function test_get_username() {
        $this->resetAfterTest(true);

        $user = self::getDataGenerator()->create_user();
        $params = $user->id;

        $returnvalue = local_wstcc_external::get_username($params);

        // We need to execute the return values cleaning process to simulate the web service server
        $returnvalue = external_api::clean_returnvalue(local_wstcc_external::get_username_returns(), $returnvalue);

        // Assertions
        $this->assertEquals($user->username, $returnvalue['username']);
    }

    /**
     * Get User by field Test
     */
    public function test_get_users_by_field() {
        global $DB;
        $cpf_test1 = '99999999999';
        $cpf_test2 = "99999999998";
        $this->resetAfterTest(true);

        // idnumber explicito: o create_user() do generator preenche com string
        // VAZIA, e a busca por idnumber no fim deste teste casava com os dois
        // usuarios -- $returnvalue[0] vinha o outro, e a assercao falhava sem
        // que houvesse nada errado com o get_users_by_field.
        $user1 = self::getDataGenerator()->create_user(array('idnumber' => 'IDN-0001'));
        $user2 = self::getDataGenerator()->create_user(array('idnumber' => 'IDN-0002'));

        // ⚠️ REUSA o campo se ele ja' existir. O auth_cas_ufsc cria o campo CPF na
        // instalacao dele -- de forma idempotente, note bem --, entao o site de
        // teste ja' vem com um. Inserir outro por cima criava DOIS campos com o
        // mesmo shortname, estado que o Moodle permite (user_info_field nao tem
        // indice unico) mas que nenhuma instalacao bem cuidada tem. A fixture deve
        // montar o cenario normal; o cenario duplicado tem teste proprio, logo
        // abaixo.
        $id1 = $DB->get_field('user_info_field', 'id', array('shortname' => 'cpf'),
                IGNORE_MULTIPLE);
        if (!$id1) {
            $id1 = $DB->insert_record('user_info_field', array(
                'shortname' => 'cpf', 'name' => 'CPF', 'categoryid' => 1,
                'datatype' => 'text'));
        }

        $DB->insert_record('user_info_data', array(
            'userid' => $user1->id, 'fieldid' => $id1, 'data' => $cpf_test1,
            'dataformat' => 0));

        $DB->insert_record('user_info_data', array(
            'userid' => $user2->id, 'fieldid' => $id1, 'data' => $cpf_test2,
            'dataformat' => 0));
        $field = 'cpf';
        $values = $cpf_test1;
        $returnvalue = local_wstcc_external::get_users_by_field($field, $values);

        // We need to execute the return values cleaning process to simulate the web service server
        $returnvalue = external_api::clean_returnvalue(local_wstcc_external::get_users_by_field_returns(), $returnvalue);

        // Assertions
        $this->assertEquals($cpf_test1, $returnvalue[0]['cpf']);
        $this->assertNotEquals($cpf_test2, $returnvalue[0]['cpf']);

        $values = $cpf_test2;
        $returnvalue = local_wstcc_external::get_users_by_field($field, $values);

        // We need to execute the return values cleaning process to simulate the web service server
        $returnvalue = external_api::clean_returnvalue(local_wstcc_external::get_users_by_field_returns(), $returnvalue);

        // Assertions
        $this->assertEquals($cpf_test2, $returnvalue[0]['cpf']);
        $this->assertNotEquals($cpf_test1, $returnvalue[0]['cpf']);

        ### ID ###
        $field = 'id';
        $values = $user1->id;
        $returnvalue = local_wstcc_external::get_users_by_field($field, $values);

        // We need to execute the return values cleaning process to simulate the web service server
        $returnvalue = external_api::clean_returnvalue(local_wstcc_external::get_users_by_field_returns(), $returnvalue);

        // Assertions
        $this->assertEquals($cpf_test1, $returnvalue[0]['cpf']);
        $this->assertNotEquals($cpf_test2, $returnvalue[0]['cpf']);

        ### email ###
        $field = 'email';
        $values = $user1->email;
        $returnvalue = local_wstcc_external::get_users_by_field($field, $values);

        // We need to execute the return values cleaning process to simulate the web service server
        $returnvalue = external_api::clean_returnvalue(local_wstcc_external::get_users_by_field_returns(), $returnvalue);

        // Assertions
        $this->assertEquals($cpf_test1, $returnvalue[0]['cpf']);
        $this->assertNotEquals($cpf_test2, $returnvalue[0]['cpf']);

        ### username ###
        $field = 'username';
        $values = $user1->username;
        $returnvalue = local_wstcc_external::get_users_by_field($field, $values);

        // We need to execute the return values cleaning process to simulate the web service server
        $returnvalue = external_api::clean_returnvalue(local_wstcc_external::get_users_by_field_returns(), $returnvalue);

        // Assertions
        $this->assertEquals($cpf_test1, $returnvalue[0]['cpf']);
        $this->assertNotEquals($cpf_test2, $returnvalue[0]['cpf']);

        ### idnumber ###
        $field = 'idnumber';
        $values = $user1->idnumber;
        $returnvalue = local_wstcc_external::get_users_by_field($field, $values);

        // We need to execute the return values cleaning process to simulate the web service server
        $returnvalue = external_api::clean_returnvalue(local_wstcc_external::get_users_by_field_returns(), $returnvalue);

        // Assertions
        $this->assertEquals($cpf_test1, $returnvalue[0]['cpf']);
        $this->assertNotEquals($cpf_test2, $returnvalue[0]['cpf']);
    }



    /**
     * ⚠️ DOIS campos de perfil com shortname 'cpf' na mesma instalacao.
     *
     * Nao e' cenario de laboratorio: o auth_cas_ufsc cria o campo CPF na
     * instalacao dele, e quem criar outro pela interface -- ou restaurar um dump
     * que ja' o tenha -- fica com dois. O core NAO garante unicidade: a tabela
     * user_info_field nao declara indice nenhum no install.xml.
     *
     * Com a subconsulta escalar que existia aqui, isso derrubava o web service
     * inteiro com "Subquery returns more than 1 row" -- e o get_users_by_field e'
     * consumido pelo SyncPerson do sistema de TCC.
     */
    public function test_get_users_by_field_com_campo_cpf_duplicado() {
        global $DB;
        $this->resetAfterTest(true);

        $cpf = '99999999977';
        $user = self::getDataGenerator()->create_user(array('idnumber' => 'IDN-DUP'));

        // O primeiro campo pode ja' existir (auth_cas_ufsc); garantimos DOIS.
        $DB->insert_record('user_info_field', array(
            'shortname' => 'cpf', 'name' => 'CPF', 'categoryid' => 1, 'datatype' => 'text'));
        $segundo = $DB->insert_record('user_info_field', array(
            'shortname' => 'cpf', 'name' => 'CPF (duplicado)', 'categoryid' => 1,
            'datatype' => 'text'));

        $this->assertGreaterThan(1, $DB->count_records('user_info_field',
                array('shortname' => 'cpf')), 'O cenario exige mais de um campo cpf.');

        $DB->insert_record('user_info_data', array(
            'userid' => $user->id, 'fieldid' => $segundo, 'data' => $cpf, 'dataformat' => 0));

        $retorno = local_wstcc_external::get_users_by_field('cpf', $cpf);
        $retorno = external_api::clean_returnvalue(
                local_wstcc_external::get_users_by_field_returns(), $retorno);

        $this->assertCount(1, $retorno);
        $this->assertEquals($cpf, $retorno[0]['cpf']);
        $this->assertEquals($user->id, $retorno[0]['id']);
    }

    public function test_get_user_online_text_submission() {
        global $DB;
        $this->resetAfterTest(true);

        // Let's create user and course and assign
        $user = self::getDataGenerator()->create_user();
        $course = self::getDataGenerator()->create_course();

        $assign_data = array('course' => $course->id, 'assignsubmission_onlinetext_enabled' => true);
        $assign = self::getDataGenerator()->create_module('assign', $assign_data);

        // Get enrol plugin
        $enrol = enrol_get_plugin('manual');
        $enrolinstances = enrol_get_instances($course->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance = $courseenrolinstance;
                break;
            }
        }

        // Enrol user to the course
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));
        $enrol->enrol_user($instance, $user->id, $studentrole->id);

        // Create assignment submission by user
        $assign_submission_data = new stdClass();
        $assign_submission_data->assignment = $assign->id;
        $assign_submission_data->status = 'draft';
        $assign_submission_data->userid = $user->id;
        $assign_submission_id = $DB->insert_record('assign_submission', $assign_submission_data);

        // Create assignment submission onlinetext by user
        $asign_sub_onlinetext_data = new stdClass();
        $asign_sub_onlinetext_data->assignment = $assign->id;
        $asign_sub_onlinetext_data->onlinetext = 'Text submmited from user';
        $asign_sub_onlinetext_data->submission = $assign_submission_id;
        $asign_sub_onlinetext_id = $DB->insert_record('assignsubmission_onlinetext', $asign_sub_onlinetext_data);

        // Executes webservice action
        $returnvalue = local_wstcc_external::get_user_online_text_submission($user->id, $assign->cmid);

        // We need to execute the return values cleaning process to simulate the web service server
        $returnvalue = external_api::clean_returnvalue(local_wstcc_external::get_user_online_text_submission_returns(), $returnvalue);

        // Assertions
        $this->assertEquals($asign_sub_onlinetext_data->onlinetext, $returnvalue['onlinetext']);
        $this->assertEquals($assign_submission_data->status, $returnvalue['status']);

    }

    public function test_create_grade_item() {
        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();

        //
        // Test creation

        // Executes webservice action
        $returnvalue = local_wstcc_external::create_grade_item($course->id, 'Test Grade',6, 1, 0, 95);

        // We need to execute the return values cleaning process to simulate the web service server
        $returnvalue = external_api::clean_returnvalue(local_wstcc_external::create_grade_item_returns(), $returnvalue);

        // Assert create
        $this->assertEquals(array('success' => true, 'action' => 'create'), $returnvalue);

        $grade_item = grade_item::fetch(array('courseid' => $course->id, 'itemname' => 'Test Grade'));
        $this->assertNotEquals(false, $grade_item); // se retornar false, quer dizer que não foi encontrado


        //
        // Test update

        // Executes webservice action
        $returnvalue = local_wstcc_external::create_grade_item($course->id, 'Test Grade',6 , 1, 10, 85);

        // We need to execute the return values cleaning process to simulate the web service server
        $returnvalue = external_api::clean_returnvalue(local_wstcc_external::create_grade_item_returns(), $returnvalue);

        // Assert update
        $this->assertEquals(array('success' => true, 'action' => 'update'), $returnvalue);

        $grade_item = grade_item::fetch(array('courseid' => $course->id, 'itemname' => 'Test Grade'));
        $this->assertEquals($course->id, $grade_item->courseid);
        $this->assertEquals('Test Grade', $grade_item->itemname);
        $this->assertEquals(10, $grade_item->grademin);
        $this->assertEquals(85, $grade_item->grademax);
    }

    /**
     * Monta um curso com atividade LTI e devolve array($course, $lti, $grade_item).
     */
    private function setup_lti_com_item() {
        $course = self::getDataGenerator()->create_course();
        $lti = self::getDataGenerator()->create_module('lti', array('course' => $course->id, 'grade' => 100));

        $grade_item = grade_item::fetch(array(
                'courseid' => $course->id, 'iteminstance' => $lti->id,
                'itemtype' => 'mod', 'itemmodule' => 'lti', 'itemnumber' => 0));

        return array($course, $lti, $grade_item);
    }

    /**
     * O set_grade_lti responde sucesso mesmo quando a nota nao teve efeito
     * (sobreposta/travada). O estado da celula no retorno e o que permite ao
     * consumidor avisar quem avaliou, em vez de dizer "salvo com sucesso" e
     * deixar o boletim com o valor antigo.
     */
    public function test_set_grade_lti_devolve_estado_da_celula() {
        $this->resetAfterTest(true);

        list($course, $lti, $grade_item) = $this->setup_lti_com_item();

        $normal = self::getDataGenerator()->create_user();
        $sobreposto = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($normal->id, $course->id);
        $this->getDataGenerator()->enrol_user($sobreposto->id, $course->id);

        // nota que entra normalmente
        $returnvalue = local_wstcc_external::set_grade_lti($course->id, $lti->id, $normal->id, 85);
        $returnvalue = external_api::clean_returnvalue(local_wstcc_external::set_grade_lti_returns(), $returnvalue);

        $this->assertTrue($returnvalue['success']);
        $this->assertFalse($returnvalue['overridden']);
        $this->assertFalse($returnvalue['locked']);
        $this->assertEquals(85, $returnvalue['finalgrade']);

        // nota que o professor sobrepos no livro de notas
        $grade_item->update_final_grade($sobreposto->id, 96);

        $returnvalue = local_wstcc_external::set_grade_lti($course->id, $lti->id, $sobreposto->id, 85);
        $returnvalue = external_api::clean_returnvalue(local_wstcc_external::set_grade_lti_returns(), $returnvalue);

        // sucesso, porque o grade_update funcionou -- mas o boletim continua 96
        $this->assertTrue($returnvalue['success']);
        $this->assertTrue($returnvalue['overridden']);
        $this->assertEquals(96, $returnvalue['finalgrade']);
    }

    /**
     * Limpar tem que resultar em SEM NOTA, e nao em nota zero -- zero e uma
     * avaliacao legitima.
     */
    public function test_clear_grade_lti() {
        $this->resetAfterTest(true);

        list($course, $lti, $grade_item) = $this->setup_lti_com_item();

        $aluno = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($aluno->id, $course->id);

        local_wstcc_external::set_grade_lti($course->id, $lti->id, $aluno->id, 85);

        $returnvalue = local_wstcc_external::clear_grade_lti($course->id, $lti->id, $aluno->id);
        $returnvalue = external_api::clean_returnvalue(local_wstcc_external::clear_grade_lti_returns(), $returnvalue);

        $this->assertTrue($returnvalue['success']);

        $grade_grade = grade_grade::fetch(array('itemid' => $grade_item->id, 'userid' => $aluno->id));
        $this->assertNull($grade_grade->finalgrade);
    }

    /**
     * Escrita destrutiva nao pode furar a protecao do livro de notas.
     */
    public function test_clear_grade_lti_preserva_sobreposta() {
        $this->resetAfterTest(true);

        list($course, $lti, $grade_item) = $this->setup_lti_com_item();

        $aluno = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($aluno->id, $course->id);

        $grade_item->update_final_grade($aluno->id, 96);

        $returnvalue = local_wstcc_external::clear_grade_lti($course->id, $lti->id, $aluno->id);
        $returnvalue = external_api::clean_returnvalue(local_wstcc_external::clear_grade_lti_returns(), $returnvalue);

        $grade_grade = grade_grade::fetch(array('itemid' => $grade_item->id, 'userid' => $aluno->id));
        $this->assertEquals(96, $grade_grade->finalgrade);

        // A nota NAO foi apagada, entao isto nao pode responder sucesso: era o
        // mesmo defeito que o set_grade_lti passou a evitar -- quem chama
        // tentaria limpar de novo a cada rodada, para sempre.
        $this->assertFalse($returnvalue['success']);
        $this->assertTrue($returnvalue['overridden']);
        $this->assertEquals(96, $returnvalue['finalgrade']);
    }

    /**
     * Item de nota travado: o grade_update sai no inicio devolvendo
     * GRADE_UPDATE_ITEM_LOCKED (4), sem tocar em nada. Comparar com
     * `!== GRADE_UPDATE_FAILED` (1) daria sucesso.
     */
    public function test_clear_grade_lti_com_item_travado() {
        $this->resetAfterTest(true);

        list($course, $lti, $grade_item) = $this->setup_lti_com_item();

        $aluno = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($aluno->id, $course->id);

        local_wstcc_external::set_grade_lti($course->id, $lti->id, $aluno->id, 85);
        $grade_item->set_locked(1);

        $returnvalue = local_wstcc_external::clear_grade_lti($course->id, $lti->id, $aluno->id);
        $returnvalue = external_api::clean_returnvalue(local_wstcc_external::clear_grade_lti_returns(), $returnvalue);

        $this->assertFalse($returnvalue['success']);
        $this->assertTrue($returnvalue['locked']);

        $grade_grade = grade_grade::fetch(array('itemid' => $grade_item->id, 'userid' => $aluno->id));
        $this->assertEquals(85, $grade_grade->finalgrade);
    }

    /**
     * Limpar quem nunca teve nota nao pode CRIAR a celula: o get_grade() insere
     * quando nao existe, e isso mudaria ate o que o get_grades_lti devolve
     * depois (o aluno deixaria de estar ausente da resposta).
     */
    public function test_clear_grade_lti_nao_cria_celula() {
        $this->resetAfterTest(true);

        list($course, $lti, $grade_item) = $this->setup_lti_com_item();

        $aluno = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($aluno->id, $course->id);

        $returnvalue = local_wstcc_external::clear_grade_lti($course->id, $lti->id, $aluno->id);
        $returnvalue = external_api::clean_returnvalue(local_wstcc_external::clear_grade_lti_returns(), $returnvalue);

        // idempotente: ja estava sem nota
        $this->assertTrue($returnvalue['success']);

        $this->assertFalse(grade_grade::fetch(array('itemid' => $grade_item->id, 'userid' => $aluno->id)));
    }

    /**
     * O boletim inteiro numa chamada: e o que evita uma escrita por aluno.
     */
    public function test_get_grades_lti() {
        $this->resetAfterTest(true);

        list($course, $lti, $grade_item) = $this->setup_lti_com_item();

        $com_nota = self::getDataGenerator()->create_user();
        $sem_nota = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($com_nota->id, $course->id);
        $this->getDataGenerator()->enrol_user($sem_nota->id, $course->id);

        local_wstcc_external::set_grade_lti($course->id, $lti->id, $com_nota->id, 85);

        $returnvalue = local_wstcc_external::get_grades_lti($course->id, $lti->id);
        $returnvalue = external_api::clean_returnvalue(local_wstcc_external::get_grades_lti_returns(), $returnvalue);

        $this->assertTrue($returnvalue['success']);

        $por_usuario = array();
        foreach ($returnvalue['grades'] as $g) {
            $por_usuario[$g['userid']] = $g;
        }

        $this->assertEquals(85, $por_usuario[$com_nota->id]['grade']);
        $this->assertFalse($por_usuario[$com_nota->id]['overridden']);
        $this->assertFalse($por_usuario[$com_nota->id]['locked']);

        // Contrato 1: quem nunca teve celula fica FORA da resposta. Um
        // `assertTrue($ausente || is_null($grade))` aceitaria os dois
        // comportamentos e nao pegaria regressao nenhuma.
        $this->assertArrayNotHasKey($sem_nota->id, $por_usuario);
    }

    /**
     * Contrato 2: a celula que existe mas esta sem nota vem com grade NULL --
     * e nunca zero, que seria uma avaliacao legitima.
     */
    public function test_get_grades_lti_celula_sem_nota_vem_como_null() {
        $this->resetAfterTest(true);

        list($course, $lti, $grade_item) = $this->setup_lti_com_item();

        $aluno = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($aluno->id, $course->id);

        // get_grade() cria a linha (e assim que ela nasce sem nota no Moodle)
        $grade_item->get_grade($aluno->id);

        $returnvalue = local_wstcc_external::get_grades_lti($course->id, $lti->id);
        $returnvalue = external_api::clean_returnvalue(local_wstcc_external::get_grades_lti_returns(), $returnvalue);

        $por_usuario = array();
        foreach ($returnvalue['grades'] as $g) {
            $por_usuario[$g['userid']] = $g;
        }

        $this->assertArrayHasKey($aluno->id, $por_usuario);
        $this->assertNull($por_usuario[$aluno->id]['grade']);
    }

    /**
     * overridden/locked sao o motivo de existir esta funcao: sem eles, quem
     * chama nao sabe que a escrita nao teria efeito e reenvia para sempre.
     */
    public function test_get_grades_lti_devolve_overridden_e_locked() {
        $this->resetAfterTest(true);

        list($course, $lti, $grade_item) = $this->setup_lti_com_item();

        $sobreposto = self::getDataGenerator()->create_user();
        $travado = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($sobreposto->id, $course->id);
        $this->getDataGenerator()->enrol_user($travado->id, $course->id);

        // update_final_grade() e o caminho do professor editando o livro de
        // notas (o singleview), que e o que marca overridden.
        $grade_item->update_final_grade($sobreposto->id, 96);

        $grade_travado = $grade_item->get_grade($travado->id);
        $grade_travado->set_locked(1);

        $returnvalue = local_wstcc_external::get_grades_lti($course->id, $lti->id);
        $returnvalue = external_api::clean_returnvalue(local_wstcc_external::get_grades_lti_returns(), $returnvalue);

        $por_usuario = array();
        foreach ($returnvalue['grades'] as $g) {
            $por_usuario[$g['userid']] = $g;
        }

        $this->assertTrue($por_usuario[$sobreposto->id]['overridden']);
        $this->assertEquals(96, $por_usuario[$sobreposto->id]['grade']);
        $this->assertTrue($por_usuario[$travado->id]['locked']);
    }

    /**
     * Curso sem o item de nota: falha de negocio, nao exception -- quem chama
     * precisa reportar isso UMA vez, e nao uma por aluno.
     */
    public function test_get_grades_lti_sem_grade_item() {
        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();

        $returnvalue = local_wstcc_external::get_grades_lti($course->id, 99999);
        $returnvalue = external_api::clean_returnvalue(local_wstcc_external::get_grades_lti_returns(), $returnvalue);

        $this->assertFalse($returnvalue['success']);
        // strpos em vez de assertContains/assertStringContainsString: o primeiro
        // saiu no PHPUnit 9 (Moodle 4.5) e o segundo nao existe no 4.8 (Moodle
        // 3.0). Assim a assercao vale nas duas pontas.
        $this->assertTrue(strpos($returnvalue['error_message'], 'grade item not found') !== false);
        $this->assertEquals(array(), $returnvalue['grades']);
    }

    /**
     * O set_grade busca o item por itemname, mas gravava com itemnumber FIXO em
     * 0: a nota lancada no "Eixo 1" (itemnumber 1) ia para a coluna principal da
     * atividade, por cima da nota do TCC. Ver issue #44.
     */
    public function test_set_grade_grava_no_item_do_itemname() {
        $this->resetAfterTest(true);

        list($course, $lti, $grade_item) = $this->setup_lti_com_item();

        $aluno = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($aluno->id, $course->id);

        local_wstcc_external::set_grade_lti($course->id, $lti->id, $aluno->id, 85);
        local_wstcc_external::create_grade_item($course->id, 'Eixo 1', $lti->id, 1, 0, 100);

        $returnvalue = local_wstcc_external::set_grade($course->id, 'Eixo 1', $aluno->id, 20);
        $returnvalue = external_api::clean_returnvalue(local_wstcc_external::set_grade_returns(), $returnvalue);
        $this->assertTrue($returnvalue['success']);

        // os 20 vao para o Eixo 1...
        $eixo = grade_item::fetch(array('courseid' => $course->id, 'itemname' => 'Eixo 1'));
        $nota_do_eixo = grade_grade::fetch(array('itemid' => $eixo->id, 'userid' => $aluno->id));
        $this->assertEquals(20, $nota_do_eixo->finalgrade);

        // ...e a nota do TCC, na coluna principal, continua 85
        $nota_principal = grade_grade::fetch(array('itemid' => $grade_item->id, 'userid' => $aluno->id));
        $this->assertEquals(85, $nota_principal->finalgrade);
    }

    /**
     * O create_grade_item deste plugin cria itens extras na MESMA iteminstance
     * (itemnumber 1..3, "Eixo 1/2/3"). Sem filtrar itemnumber, a leitura cairia
     * na coluna errada onde eles existem.
     */
    public function test_get_grades_lti_ignora_itens_de_eixo() {
        $this->resetAfterTest(true);

        list($course, $lti, $grade_item) = $this->setup_lti_com_item();

        $aluno = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($aluno->id, $course->id);

        local_wstcc_external::set_grade_lti($course->id, $lti->id, $aluno->id, 85);
        local_wstcc_external::create_grade_item($course->id, 'Eixo 1', $lti->id, 1, 0, 100);

        // A nota do eixo entra pelo grade_update com o itemnumber DELE (1).
        // Nao da para usar o set_grade() daqui: ele passa itemnumber 0 fixo e
        // escreveria no item principal -- e o defeito registrado na issue #42,
        // que este teste nao deve depender nem mascarar.
        $eixo = grade_item::fetch(array('courseid' => $course->id, 'itemname' => 'Eixo 1'));
        $nota_do_eixo = $eixo->get_grade($aluno->id);
        $nota_do_eixo->rawgrade = 20;
        $nota_do_eixo->finalgrade = 20;
        grade_update('mod/lti', $course->id, 'mod', 'lti', $lti->id, 1, $nota_do_eixo);

        $returnvalue = local_wstcc_external::get_grades_lti($course->id, $lti->id);
        $returnvalue = external_api::clean_returnvalue(local_wstcc_external::get_grades_lti_returns(), $returnvalue);

        $por_usuario = array();
        foreach ($returnvalue['grades'] as $g) {
            $por_usuario[$g['userid']] = $g;
        }

        // 85 do item principal, nao os 20 do Eixo 1
        $this->assertEquals(85, $por_usuario[$aluno->id]['grade']);
    }
}