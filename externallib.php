<?php
/**
 * External Web Service TCC
 *
 * @package    localwstcc
 * @author     Bruno Silveira
 */
require_once($CFG->libdir."/externallib.php");
require_once($CFG->libdir."/gradelib.php");
require_once($CFG->dirroot.'/mod/assign/locallib.php');
require_once($CFG->dirroot."/local/tutores/lib.php");
require_once($CFG->dirroot."/user/lib.php");

use local_ufsc\ufsc;

class local_wstcc_external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function get_user_online_text_submission_parameters() {
        return new external_function_parameters(
                array(
                        'userid' => new external_value(PARAM_INT, 'User id', VALUE_REQUIRED),
                        'coursemoduleid' => new external_value(PARAM_INT, 'Course Module id', VALUE_REQUIRED)
                )
        );
    }

    /**
     * Retorna texto online de determinado usuário em determinada tarefa
     *
     * @param $userid
     * @param $coursemoduleid
     * @return array()
     */
    public static function get_user_online_text_submission($userid, $coursemoduleid) {
        global $DB;

        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(self::get_user_online_text_submission_parameters(),
                array('userid' => $userid,
                        'coursemoduleid' => $coursemoduleid));


        $sql = "SELECT ot.onlinetext, status
                  FROM {assignsubmission_onlinetext} AS ot
                  JOIN {assign_submission} AS assub
                    ON (ot.submission = assub.id)
                  JOIN {user} u
                    ON (assub.userid = u.id)
                  JOIN {course_modules} cm
                    ON (cm.instance = ot.assignment)
                  JOIN {modules} m
                    ON (m.id = cm.module AND m.name LIKE 'assign')
                 WHERE (u.id = :userid  AND cm.id = :coursemoduleid);";

        $result = $DB->get_record_sql($sql, array('userid' => $params['userid'], 'coursemoduleid' => $params['coursemoduleid']));

        return array('onlinetext' => $result->onlinetext, 'status' => $result->status);

    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function get_user_online_text_submission_returns() {
        $keys = array(
                'onlinetext' => new external_value(PARAM_RAW, 'texto online'),
                'status' => new external_value(PARAM_TEXT, 'status')
        );

        return new external_single_structure($keys, 'Texto online de determinado usuário em determinada tarefa.');
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function get_user_text_for_generate_doc_parameters() {
        return new external_function_parameters(
                array(
                        'userid' => new external_value(PARAM_INT, 'User id', VALUE_REQUIRED),
                        'coursemoduleid' => new external_value(PARAM_INT, 'Course Module id', VALUE_REQUIRED)
                )
        );
    }

    /**
     * Retorna texto online de determinado usuário em determinada tarefa para a geração de documento
     *
     * @param $userid
     * @param $coursemoduleid
     * @return array()
     */
    public static function get_user_text_for_generate_doc($userid, $coursemoduleid) {
        global $DB;

        $context = context_module::instance($coursemoduleid);
        $cm = get_coursemodule_from_id(null, $coursemoduleid, null, false, MUST_EXIST);

        $assignsubmission = $DB->get_record('assign_submission',
                array('assignment' => $cm->instance, 'userid' => $userid), 'id', MUST_EXIST);

        $submission_onlinetext = $DB->get_record('assignsubmission_onlinetext',
                array('submission' => $assignsubmission->id), 'id, onlinetext', MUST_EXIST);

        $base_url = new moodle_url("/webservice/pluginfile.php/{$context->id}/assignsubmission_onlinetext/submissions_onlinetext/{$assignsubmission->id}/");

        # Anonymous function que será executada pelo preg_replace_callback
        $callback = function ($matches) use ($base_url) {
            $filename = rawurlencode(rawurldecode($matches[2])); // Faz decode antes para evitar erro ao encodar caracteres que já tem encode
            return $base_url.$filename.'?token=@@TOKEN@@"';
        };

        $finaltextversion = preg_replace_callback('/\"(@@PLUGINFILE@@)\/([^\"]+)\"/', $callback, $submission_onlinetext->onlinetext);

        return array('onlinetext' => $finaltextversion);

    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function get_user_text_for_generate_doc_returns() {
        $keys = array(
                'onlinetext' => new external_value(PARAM_RAW, 'texto online')
        );

        return new external_single_structure($keys, 'Texto online de determinado usuário em determinada tarefa.');
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function get_username_parameters() {
        return new external_function_parameters(
                array(
                        'userid' => new external_value(PARAM_INT, 'User id', VALUE_REQUIRED),
                )
        );
    }

    /**
     * Retorna username de determinado usuário pelo seu ID
     *
     * @param $userid
     * @return array()
     */
    public static function get_username($userid) {
      global $DB;

        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(self::get_username_parameters(),
                array('userid' => $userid));

        $result = $DB->get_field('user', 'username', array('id' => $userid));

        return array('username' => $result);

    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function get_username_returns() {
        $keys = array('username' => new external_value(PARAM_RAW, 'username'));

        return new external_single_structure($keys, 'Username.');
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.4
     */
    public static function get_users_by_field_parameters() {
        $keys = array(
                'field' => new external_value(PARAM_ALPHA, 'the search field can be \'id\' or \'idnumber\' or \'username\' or \'email\''),
                'values' => new external_multiple_structure(new external_value(PARAM_RAW, 'the value to match'))
        );

        return new external_function_parameters($keys);
    }

    /**
     * Get user information for a unique field.
     *
     * @param string $field
     * @param array $values
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @return array An array of arrays containg user profiles.
     * @since Moodle 2.4
     */
    public static function get_users_by_field($field, $values) {
        global $DB;

        $params = self::validate_parameters(self::get_users_by_field_parameters(),
                array('field' => $field, 'values' => $values));

        // This array will keep all the users that are allowed to be searched,
        // according to the current user's privileges.
        $cleanedvalues = array();

        switch ($field) {
            case 'id':
                $paramtype = PARAM_INT;
                break;
            case 'idnumber':
                $paramtype = PARAM_RAW;
                break;
            case 'username':
                $paramtype = PARAM_RAW;
                break;
            case 'email':
                $paramtype = PARAM_EMAIL;
                break;
            default:
                throw new coding_exception('invalid field parameter',
                        'The search field \''.$field.'\' is not supported, look at the web service documentation');
        }

        // Clean the values
        foreach ($values as $value) {
            $cleanedvalue = clean_param($value, $paramtype);
            if ($value != $cleanedvalue) {
                throw new invalid_parameter_exception('The field \''.$field.
                        '\' value is invalid: '.$value.'(cleaned value: '.$cleanedvalue.')');
            }
            $cleanedvalues[] = $cleanedvalue;
        }

        // Retrieve the users
        $users = $DB->get_records_list('user', $field, $cleanedvalues, 'id');

        // Finally retrieve each users information
        $returnedusers = array();
        foreach ($users as $user) {
            $user_details = new stdClass();
            $user_details->id = $user->id;
            $user_details->name = fullname($user);
            $user_details->email = $user->email;
            $user_details->username = $user->username;

            $returnedusers[] = $user_details;
        }

        return $returnedusers;
    }

    /**
     * Returns description of method result value
     *
     * @return external_multiple_structure
     * @since Moodle 2.4
     */
    public static function get_users_by_field_returns() {
        $userfields = array(
                'id' => new external_value(PARAM_INT, 'ID of the user'),
                'name' => new external_value(PARAM_NOTAGS, 'The first name(s) of the user', VALUE_OPTIONAL),
                'email' => new external_value(PARAM_TEXT, 'An email address - allow email as root@localhost', VALUE_OPTIONAL),
                'username' => new external_value(PARAM_RAW, 'The username', VALUE_OPTIONAL)
        );

        return new external_multiple_structure(new external_single_structure($userfields));
    }

    /**
     * Cria ou atualiza o grade item do curso especificado
     *
     * @param $courseid
     * @param $itemname
     * @param $lti_id
     * @param $itemnumber
     * @param $grademin
     * @param $grademax
     * @return array
     */
    public static function create_grade_item($courseid, $itemname, $lti_id, $itemnumber, $grademin, $grademax) {
        $course_category = grade_category::fetch_course_category($courseid);
        $grade_item = grade_item::fetch(array('courseid' => $courseid, 'itemname' => $itemname));

        if (!$grade_item) {
            $grade_item = new grade_item();
            $action = 'create';
        } else {
            $action = 'update';
        }

        $grade_item->courseid = $courseid;
        $grade_item->categoryid = $course_category->id;
        $grade_item->itemname = $itemname;
        $grade_item->iteminstance = $lti_id;
        $grade_item->itemnumber = $itemnumber;
        $grade_item->itemtype = 'mod';
        $grade_item->itemmodule = 'lti';
        $grade_item->grademin = $grademin;
        $grade_item->grademax = $grademax;
        if ($action == 'update') {
            $result = $grade_item->update();
        } else {
            $result = $grade_item->insert();
        }

        return array('success' => (bool) $result, 'action' => $action);
    }

    public static function create_grade_item_parameters() {
        return new external_function_parameters(
                array(
                        'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
                        'itemname' => new external_value(PARAM_RAW, 'Item Name', VALUE_REQUIRED),
                        'lti_id' => new external_value(PARAM_RAW, 'LTI id', VALUE_REQUIRED),
                        'itemnumber' => new external_value(PARAM_RAW, 'Item number', VALUE_REQUIRED),
                        'grademin' => new external_value(PARAM_RAW, 'Grade min', VALUE_REQUIRED),
                        'grademax' => new external_value(PARAM_RAW, 'Grade max', VALUE_REQUIRED)
                )
        );
    }

    public static function create_grade_item_returns() {
        $keys = array(
                'success' => new external_value(PARAM_RAW, 'success'),
                'action' => new external_value(PARAM_RAW, 'action')
        );

        return new external_single_structure($keys, 'Success');
    }

    /**
     * Insere a nota do usuário no item especificado
     *
     * @param $courseid
     * @param $itemname
     * @param $userid
     * @param $grade
     * @return array
     */
    public static function set_grade($courseid, $itemname, $userid, $grade) {
        $error_msg = '';
        $success = false;
        $grade_item = grade_item::fetch(array('courseid' => $courseid, 'itemname' => $itemname));
        if ($grade_item) {
            $grade_grade = $grade_item->get_grade($userid);
            $grade_grade->finalgrade = $grade;
            $grade_grade->rawgrade = $grade;
            $success = $grade_grade->update('manual');

            if (!$success) {
                $error_msg = 'set grade failed';
            }
        } else {
            $error_msg = 'set grade failed: grade item not found';
        }

        return array('success' => $success, 'error_message' => $error_msg);
    }

    public static function set_grade_parameters() {
        return new external_function_parameters(
                array(
                        'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
                        'itemname' => new external_value(PARAM_RAW, 'Item Name', VALUE_REQUIRED),
                        'userid' => new external_value(PARAM_INT, 'User id', VALUE_REQUIRED),
                        'grade' => new external_value(PARAM_INT, 'Grade', VALUE_REQUIRED)
                )
        );
    }

    public static function set_grade_returns() {
        $keys = array(
                'success' => new external_value(PARAM_BOOL, 'success'),
                'error_message' => new external_value(PARAM_RAW, 'error_message')
        );

        return new external_single_structure($keys, 'Success');
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.4
     */
    public static function get_tutor_responsavel_parameters() {
        $keys = array(
                'userid' => new external_value(PARAM_INT, 'User id', VALUE_REQUIRED),
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
        );

        return new external_function_parameters($keys);
    }

    public static function get_tutor_responsavel($userid, $courseid) {
        global $CFG;

        $params = self::validate_parameters(self::get_tutor_responsavel_parameters(),
                array('userid' => $userid, 'courseid' => $courseid));

        $categoria_turma = UFSC::get_categoria_turma_ufsc($params['courseid']);
        $tutor = grupos_tutoria::get_tutor_responsavel_estudante($categoria_turma, $params['userid']);

        return array('id_tutor' => $tutor->id);
    }

    public static function get_tutor_responsavel_returns() {
        $keys = array(
                'id_tutor' => new external_value(PARAM_RAW, 'id_tutor')
        );

        return new external_single_structure($keys, 'Id tutor');
    }

    /**
     * Busca lista de participantes de determinado curso.
     *
     * @return external_function_parameters
     */
    public static function get_students_by_course_parameters() {
        $keys = array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
        );

        return new external_function_parameters($keys);
    }

    public static function get_students_by_course($courseid) {
        $params = self::validate_parameters(self::get_students_by_course_parameters(), array('courseid' => $courseid));

        // Retrieve the users
        $users = self::get_list_of_students_by_course($params['courseid']);

        $returnedusers = array();
        foreach ($users as $user) {
            $user_details = new stdClass();
            $user_details->id = $user->id;

            $returnedusers[] = $user_details;
        }

        return $returnedusers;
    }

    public static function get_students_by_course_returns() {
        $userfields = array('id' => new external_value(PARAM_INT, 'ID of the student'));

        return new external_multiple_structure(new external_single_structure($userfields));
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.4
     */
    public static function get_orientador_responsavel_parameters() {
        $keys = array(
                'userid' => new external_value(PARAM_INT, 'User id', VALUE_REQUIRED),
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
        );

        return new external_function_parameters($keys);
    }

    public static function get_orientador_responsavel($userid, $courseid) {
        global $CFG;

        $params = self::validate_parameters(self::get_orientador_responsavel_parameters(),
                array('userid' => $userid, 'courseid' => $courseid));

        $categoria_turma = ufsc::get_categoria_turma_ufsc($params['courseid']);
        $orientador = grupo_orientacao::get_orientador_responsavel_estudante($categoria_turma, $params['userid']);

        return array('id_orientador' => $orientador->id);
    }

    public static function get_orientador_responsavel_returns() {
        $keys = array(
                'id_orientador' => new external_value(PARAM_INT, 'id_orientador')
        );

        return new external_single_structure($keys, 'Id Orientador');
    }

    /**
     * Função auxiliar que retorna a lista de participantes com papel de estudante ('roleid = 5')
     * de um determinado curso
     *
     * @param $courseid
     * @return array
     */

    protected static function get_list_of_students_by_course($courseid) {
        global $DB;

        $sql = 'SELECT DISTINCT u.id
                  FROM {role_assignments} ra
                  JOIN {user} u
                    ON (u.id = ra.userid)
                  JOIN {context} ctx
                    ON (ctx.id = ra.contextid)
                  JOIN {course} c
                    ON (c.id = ctx.instanceid)
                  JOIN {role} r
                    ON (r.id = ra.roleid)
                 WHERE (roleid = 5 AND c.id = :courseid AND ctx.contextlevel = :contextlevel)
              ORDER BY u.firstname
        ';

        return $DB->get_records_sql($sql, array('courseid' => $courseid, 'contextlevel' => CONTEXT_COURSE));
    }
}
