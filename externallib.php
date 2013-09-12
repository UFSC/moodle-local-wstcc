<?php
/**
 * External Web Service TCC
 *
 * @package    localwstcc
 * @author     Bruno Silveira
 */
require_once($CFG->libdir . "/externallib.php");
require_once($CFG->libdir . "/gradelib.php");

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

    /**
     * Cria ou atualiza o grade item do curso especificado
     *
     * @return array
     */
    public static function create_grade_item($courseid, $itemname, $lti_id, $itemnumber) {
        $action = '';
        $course_category = grade_category::fetch_course_category($courseid);
        $grade_item = grade_item::fetch(array('courseid' => $courseid, 'itemname' => $itemname));

        if ($grade_item) {
            $g = new grade_item($grade_item);
        } else {
            $g = new grade_item();
        }

        $g->courseid = $courseid;
        $g->categoryid = $course_category->id;
        $g->itemname = $itemname;
        $g->iteminstance = $lti_id;
        $g->itemnumber = $itemnumber;
        $g->itemtype = 'mod';
        $g->itemmodule = 'lti';
        if ($grade_item) {
            $result = $g->update();
            $action = 'update';
        } else {
            $result = $g->insert();
            $action = 'create';
        }

        return array('success' => $result, 'action' => $action);
    }

    public static function create_grade_item_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_REQUIRED),
                'itemname' => new external_value(PARAM_RAW, 'Item Name', VALUE_REQUIRED),
                'lti_id' => new external_value(PARAM_RAW, 'LTI id', VALUE_REQUIRED),
                'itemnumber' => new external_value(PARAM_RAW, 'Item number', VALUE_REQUIRED)
            )
        );
    }

    public static function create_grade_item_returns() {
        $keys = array(
            'success' => new external_value(PARAM_BOOL, 'success'),
            'action' => new external_value(PARAM_RAW, 'action'),
        );

        return new external_single_structure($keys, 'Success');
    }

    /**
     * Insere a nota do usuário no item especificado
     *
     * @return array
     */
    public static function set_grade($courseid, $itemname, $userid, $grade) {
        $error_msg = ''; $success = false;
        $grade_item = grade_item::fetch(array('courseid' => $courseid, 'itemname' => $itemname));
        if($grade_item) {
            $grade_grade = $grade_item->get_grade($userid);
            $grade_grade->finalgrade = $grade;
            $grade_grade->rawgrade = $grade;
            $success = $grade_grade->update('manual');

            if(!$success) {
                $error_msg = 'set grade failed' ;
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
}
