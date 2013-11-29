<?php
/**
 * External Web Service TCC
 *
 * @package    localwstcc
 * @author     Bruno Silveira
 */
require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot.'/mod/assign/locallib.php');

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
        global $CFG;

        $context = context_module::instance($coursemoduleid);
        $cm = get_coursemodule_from_id(null, $coursemoduleid, null , false, MUST_EXIST);

        $assignsubmission = $DB->get_record('assign_submission',
            array('assignment'=>$cm->instance, 'userid'=>$userid), 'id', MUST_EXIST);

        $submission_onlinetext = $DB->get_record('assignsubmission_onlinetext',
            array('submission'=>$assignsubmission->id), 'id, onlinetext',MUST_EXIST);

        $wsurl = $CFG->wwwroot . '/webservice';

        $baseurl = $wsurl . '/pluginfile.php/'. $context->id .
                '/assignsubmission_onlinetext/submissions_onlinetext/' . $assignsubmission->id ;

        $replacement = $baseurl . '${2}?token=@@TOKEN@@"';

        $finaltextversion = preg_replace('/(@@PLUGINFILE@@)(\/[^\.]*\.[a-zA-Z0-9]{3,})+\"/', $replacement, $submission_onlinetext->onlinetext);

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


        $sql = "SELECT username
                  FROM {user}
                 WHERE (id = :userid);";

        $result = $DB->get_record_sql($sql, array('userid' => $params['userid']));

        return array('username' => $result->username);

    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function get_username_returns() {
        $keys = array(
            'username' => new external_value(PARAM_RAW, 'username')
        );

        return new external_single_structure($keys, 'Username.');
    }


}
