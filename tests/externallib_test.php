<?php

/**
 * WSTcc External functions unit tests
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

class local_wstcc_external_testcase extends externallib_advanced_testcase {

    protected function setUp() {
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
        $returnvalue = local_wstcc_external::create_grade_item($course->id, 'Test Grade', 0, 95);

        // We need to execute the return values cleaning process to simulate the web service server
        $returnvalue = external_api::clean_returnvalue(local_wstcc_external::create_grade_item_returns(), $returnvalue);

        // Assert create
        $this->assertEquals(array('result' => 'update successful'), $returnvalue);

        $grade_item = grade_item::fetch(array('courseid' => $course->id, 'itemname' => 'Test Grade'));
        $this->assertNotEquals(false, $grade_item); // se retornar false, quer dizer que não foi encontrado


        //
        // Test update

        // Executes webservice action
        $returnvalue = local_wstcc_external::create_grade_item($course->id, 'Test Grade', 10, 85);

        // We need to execute the return values cleaning process to simulate the web service server
        $returnvalue = external_api::clean_returnvalue(local_wstcc_external::create_grade_item_returns(), $returnvalue);

        // Assert update
        $this->assertEquals(array('result' => 'update successful'), $returnvalue);

        $grade_item = grade_item::fetch(array('courseid' => $course->id, 'itemname' => 'Test Grade'));
        $this->assertEquals($course->id, $grade_item->courseid);
        $this->assertEquals('Test Grade', $grade_item->itemname);
        $this->assertEquals(10, $grade_item->grademin);
        $this->assertEquals(85, $grade_item->grademax);
    }
}