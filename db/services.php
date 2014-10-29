<?php

// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Web service local plugin tcc external functions and service definitions.
 *
 * @package    localwstcc
 * @author     Bruno Silveira
 */

// We defined the web service functions to install.
$functions = array(
        'local_wstcc_create_grade_item' => array(
                'classname' => 'local_wstcc_external',
                'methodname' => 'create_grade_item',
                'classpath' => 'local/wstcc/externallib.php',
                'description' => 'Cria item de nota.',
                'type' => 'write',
        ),
        'local_wstcc_get_user_online_text_submission' => array(
                'classname' => 'local_wstcc_external',
                'methodname' => 'get_user_online_text_submission',
                'classpath' => 'local/wstcc/externallib.php',
                'description' => 'Retorna o texto submetio pelo usuário e o status dele.',
                'type' => 'read',
        ),
        'local_wstcc_get_user_text_for_generate_doc' => array(
                'classname' => 'local_wstcc_external',
                'methodname' => 'get_user_text_for_generate_doc',
                'classpath' => 'local/wstcc/externallib.php',
                'description' => 'Retorna o texto submetio pelo usuário e o status dele para geração de documento.',
                'type' => 'read',
        ),
        'local_wstcc_get_username' => array(
                'classname' => 'local_wstcc_external',
                'methodname' => 'get_username',
                'classpath' => 'local/wstcc/externallib.php',
                'description' => 'Retorna username.',
                'type' => 'read',
        ),
        'local_wstcc_get_users_by_field' => array(
                'classname' => 'local_wstcc_external',
                'methodname' => 'get_users_by_field',
                'classpath' => 'local/wstcc/externallib.php',
                'description' => 'Retorna os dados dos usuários informados.',
                'type' => 'read',
        ),
        'local_wstcc_get_tutor_responsavel' => array(
                'classname' => 'local_wstcc_external',
                'methodname' => 'get_tutor_responsavel',
                'classpath' => 'local/wstcc/externallib.php',
                'description' => 'Retorna o tutor responsável de cada estudante.',
                'type' => 'read',
        ),
        'local_wstcc_get_students_by_course' => array(
                'classname' => 'local_wstcc_external',
                'methodname' => 'get_students_by_course',
                'classpath' => 'local/wstcc/externallib.php',
                'description' => 'Retorna a lista de estudantes de um curso específico.',
                'type' => 'read',
        ),
        'local_wstcc_get_orientador_responsavel' => array(
                'classname' => 'local_wstcc_external',
                'methodname' => 'get_orientador_responsavel',
                'classpath' => 'local/wstcc/externallib.php',
                'description' => 'Retorna o orientador responsável de cada estudante.',
                'type' => 'read',
        ),
        'local_wstcc_set_grade' => array(
                'classname' => 'local_wstcc_external',
                'methodname' => 'set_grade',
                'classpath' => 'local/wstcc/externallib.php',
                'description' => 'Define nota para um grade_item pesquisando pelo nome',
                'type' => 'write',
        ),
        'local_wstcc_set_grade_lti' => array(
                'classname' => 'local_wstcc_external',
                'methodname' => 'set_grade_lti',
                'classpath' => 'local/wstcc/externallib.php',
                'description' => 'Define nota para um grade_item pesquisando pelo coursemoduleid',
                'type' => 'write',
        )
);

// We define the services to install as pre-build services. A pre-build service is not editable by administrator.
$services = array(
        'TCC Services' => array(
                'functions' => array(
                        'local_wstcc_create_grade_item',
                        'local_wstcc_get_user_online_text_submission',
                        'local_wstcc_get_username',
                        'local_wstcc_get_user_text_for_generate_doc',
                        'local_wstcc_get_users_by_field',
                        'local_wstcc_get_tutor_responsavel',
                        'local_wstcc_get_students_by_course',
                        'local_wstcc_get_orientador_responsavel',
                        'local_wstcc_set_grade',
                        'local_wstcc_set_grade_lti'
                ),
                'restrictedusers' => 1,
                'downloadfiles' => 1,
                'enabled' => 1,
                'shortname' => 'wstcc_webservice'
        )
);
