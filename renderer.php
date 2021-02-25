<?php
// This file is part of Moodle - http://moodle.org/
//
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
 * Renderer for outputting the qmulweeks course format.
 *
 * @package format_qmulweeks
 * @copyright 2020 Matthias Opitz / QMUL
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.3
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/format/renderer.php');
require_once($CFG->dirroot . '/course/format/qmulweeks/lib.php');
require_once($CFG->dirroot . '/course/format/qmulweeks/classes/output/course_renderer.php');
require_once($CFG->dirroot . '/course/format/weeks2/renderer.php');

/**
 * Basic renderer for qmulweeks format.
 *
 * @copyright 2012 Dan Poltawski | 2019 Matthias Opitz
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class format_qmulweeks_renderer extends format_weeks2_renderer {

    private $courseformat = null;
    private $tcsettings;

    /**
     * format_qmulweeks_renderer constructor.
     *
     * @param moodle_page $page
     * @param $target
     * @throws dml_exception
     */
    public function __construct(moodle_page $page, $target) {
        global $COURSE;

        parent::__construct($page, $target);
        $this->courseformat = course_get_format($page->course);
        $this->tcsettings = $this->courseformat->get_format_options();
        // If theme badges are not enabled let's use our own course renderer as we want to add badges to the module output.
        $usethemebadges = get_config('format_qmulweeks', 'usethemebadges');
        if ($usethemebadges != 1) {
            $newcourserenderer = new qmulweeks_course_renderer($page, null);
            // But only if there are less that 1000 students enrolled into the course.
            if (count($newcourserenderer->enrolled_users("")) < 1000) {
                $this->courserenderer = $newcourserenderer;
                // Create objects that contain data about modules and groups used in this course.
                $COURSE->module_data = $this->get_module_data();
                $COURSE->group_assign_data = $this->get_group_assign_data();
            }
        }
    }

    /**
     * Get submission and grading data for modules in this course
     *
     * @return array
     * @throws dml_exception
     */
    protected function get_module_data() {
        global $COURSE, $DB;
        $sql = "
            select
            concat_ws('_', cm.id,a.id, asu.id, ag.id, c.id, ca.id, f.id, fc.id,
                l.id,la.id,lg.id,q.id,qa.id,qg.id,gi.id,gg.id) as row_id
            ,m.name as module_name
            ,gi.hidden as gi_hidden
            ,gi.locked as gi_locked
            ,gg.hidden as gg_hidden
            ,gg.locked as gg_locked
            #,'assign >'
            ,a.id as assign_id
            ,a.name as assign
            ,a.duedate as assign_duedate
            ,a.teamsubmission
            ,a.requireallteammemberssubmit
            ,asu.userid as assign_userid
            ,asu.status as assign_submission_status
            ,asu.timemodified as assign_submit_time
            ,ag.grade as assign_grade
            ,ag.timemodified as assign_grade_time
            #,'choice >'
            ,c.id as choice_id
            ,c.name as choice
            ,c.timeopen as choice_timeopen
            ,c.timeclose as choice_duedate
            ,ca.userid as choice_userid
            ,ca.timemodified as choice_submit_time
            #,'feedback >'
            ,f.id as feedback_id
            ,f.name as feedback
            ,f.timeopen as feedback_timeopen
            ,f.timeclose as feedback_duedate
            ,fc.userid as feedback_userid
            ,fc.timemodified as feedback_submit_time
            #,'lesson >'
            ,l.id as lesson_id
            ,l.name as lesson
            ,l.deadline as lesson_duedate
            ,la.userid as lesson_userid
            ,la.correct
            ,la.timeseen as lesson_submit_time
            ,lg.grade as lesson_grade
            ,lg.completed as lesson_completed
            #,'quiz >'
            ,q.id as quiz_id
            ,q.name as quiz_name
            ,q.timeopen as quiz_timeopen
            ,q.timeclose as quiz_duedate
            ,qa.userid as quiz_userid
            ,qa.state as quiz_state
            ,qa.timestart as quiz_timestart
            ,qa.timefinish as quiz_submit_time
            ,qg.grade as quiz_grade
            from {course_modules} cm
            join {modules} m on m.id = cm.module
            # assign
            left join {assign} a on a.id = cm.instance and a.course = cm.course and m.name = 'assign'
            left join {assign_submission} asu on asu.assignment = a.id
            left join {assign_grades} ag on ag.assignment = asu.assignment and ag.userid = asu.userid
            # choice
            left join {choice} c on c.id = cm.instance and c.course = cm.course and m.name = 'choice'
            left join {choice_answers} ca on ca.choiceid = c.id
            # feedback
            left join {feedback} f on f.id = cm.instance and f.course = cm.course and m.name = 'feedback'
            left join {feedback_completed} fc on fc.feedback = f.id
            # lesson
            left join {lesson} l on l.id = cm.instance and l.course = cm.course and m.name = 'lesson'
            left join {lesson_attempts} la on la.lessonid = l.id
            left join {lesson_grades} lg on lg.lessonid = la.lessonid and lg.userid = la.userid
            # quiz
            left join {quiz} q on q.id = cm.instance and q.course = cm.course and m.name = 'quiz'
            left join {quiz_attempts} qa on qa.quiz = q.id
            left join {quiz_grades} qg on qg.quiz = qa.quiz and qg.userid = qa.userid
            # grading
            left join {grade_items} gi on (gi.courseid = cm.course and gi.itemmodule = m.name and gi.iteminstance = cm.instance)
            left join {grade_grades} gg on (gg.itemid = gi.id and gg.userid = asu.userid)
            where 1
            and cm.course = $COURSE->id
            #and m.name = 'choice'
            #order by m.name
            #order by concat_ws('',cm.id,a.id, asu.id, ag.id, c.id, ca.id, f.id, fc.id, l.id,la.id,lg.id,q.id,qa.id,qg.id)
            #limit 5000
        ";
        return $DB->get_records_sql($sql);
    }
    public function get_module_data0() {
        global $COURSE, $DB;
        $sql = "
            select concat_ws('', cm.id,a.id, asu.id, ag.id, c.id, ca.id, f.id, fc.id, l.id,la.id,lg.id,q.id,qa.id,qg.id) as row_id
            ,m.name as module_name
            #,'assign >'
            ,a.id as assign_id
            ,a.name as assign
            ,a.duedate as assign_duedate
            ,a.teamsubmission
            ,a.requireallteammemberssubmit
            ,asu.userid as assign_userid
            ,asu.status as assign_submission_status
            ,asu.timemodified as assign_submit_time
            ,ag.grade as assign_grade
            ,ag.timemodified as assign_grade_time
            #,'choice >'
            ,c.id as choice_id
            ,c.name as choice
            ,c.timeopen as choice_timeopen
            ,c.timeclose as choice_duedate
            ,ca.userid as choice_userid
            ,ca.timemodified as choice_submit_time
            #,'feedback >'
            ,f.id as feedback_id
            ,f.name as feedback
            ,f.timeopen as feedback_timeopen
            ,f.timeclose as feedback_duedate
            ,fc.userid as feedback_userid
            ,fc.timemodified as feedback_submit_time
            #,'lesson >'
            ,l.id as lesson_id
            ,l.name as lesson
            ,l.deadline as lesson_duedate
            ,la.userid as lesson_userid
            ,la.correct
            ,la.timeseen as lesson_submit_time
            ,lg.grade as lesson_grade
            ,lg.completed as lesson_completed
            #,'quiz >'
            ,q.id as quiz_id
            ,q.name as quiz_name
            ,q.timeopen as quiz_timeopen
            ,q.timeclose as quiz_duedate
            ,qa.userid as quiz_userid
            ,qa.state as quiz_state
            ,qa.timestart as quiz_timestart
            ,qa.timefinish as quiz_submit_time
            ,qg.grade as quiz_grade
            from {course_modules} cm
            join {modules} m on m.id = cm.module
            # assign
            left join {assign} a on a.id = cm.instance and a.course = cm.course and m.name = 'assign'
            left join {assign_submission} asu on asu.assignment = a.id
            left join {assign_grades} ag on ag.assignment = asu.assignment and ag.userid = asu.userid
            # choice
            left join {choice} c on c.id = cm.instance and c.course = cm.course and m.name = 'choice'
            left join {choice_answers} ca on ca.choiceid = c.id
            # feedback
            left join {feedback} f on f.id = cm.instance and f.course = cm.course and m.name = 'feedback'
            left join {feedback_completed} fc on fc.feedback = f.id
            # lesson
            left join {lesson} l on l.id = cm.instance and l.course = cm.course and m.name = 'lesson'
            left join {lesson_attempts} la on la.lessonid = l.id
            left join {lesson_grades} lg on lg.lessonid = la.lessonid and lg.userid = la.userid
            # quiz
            left join {quiz} q on q.id = cm.instance and q.course = cm.course and m.name = 'quiz'
            left join {quiz_attempts} qa on qa.quiz = q.id
            left join {quiz_grades} qg on qg.quiz = qa.quiz and qg.userid = qa.userid
            where 1
            and cm.course = $COURSE->id
        ";
        return $DB->get_records_sql($sql);
    }

    /**
     * Get group related submission and grading data for modules in this course
     *
     * @return array
     * @throws dml_exception
     */
    protected function get_group_assign_data() {
        global $COURSE, $DB;
        $sql = "
            select
            concat_ws('_', g.id,gm.id, asu.id, ag.id, gi.id, gg.id) as row_id
            ,g.id
            ,gi.hidden as gi_hidden
            ,gi.locked as gi_locked
            ,gg.hidden as gg_hidden
            ,gg.locked as gg_locked
            ,gm.id as ID
            ,gm.groupid
            ,gm.userid
            ,asu.assignment
            ,ag.grade
            from {groups} g
            join {groups_members} gm on gm.groupid = g.id
            left join {assign_submission} asu on asu.groupid = g.id
            left join {assign_grades} ag on (ag.assignment = asu.assignment and ag.userid = gm.userid)
            # grading
            left join {grade_items} gi on (gi.courseid = g.courseid
                and gi.itemmodule = 'assign' and gi.iteminstance = asu.assignment)
            left join {grade_grades} gg on (gg.itemid = gi.id and gg.userid = asu.userid)
            where g.courseid = $COURSE->id and asu.userid = 0";
        return $DB->get_records_sql($sql);
    }
    protected function get_group_assign_data0() {
        global $COURSE, $DB;
        $sql = "
            select
             g.id
            , gm.id as ID
            , gm.groupid
            , gm.userid
            , asu.assignment
            ,ag.grade
            from {groups} g
            join {groups_members} gm on gm.groupid = g.id
            left join {assign_submission} asu on asu.groupid = g.id
            left join {assign_grades} ag on (ag.assignment = asu.assignment and ag.userid = gm.userid)
            where g.courseid = $COURSE->id and asu.userid = 0";
        return $DB->get_records_sql($sql);
    }

    /**
     * Require the jQuery files for this class
     */
    public function require_js() {
        $this->page->requires->js_call_amd('format_qmulweeks/tabs', 'init', array());
        $this->page->requires->js_call_amd('format_weeks2/toggle', 'init', array());
    }

    /**
     * Prepare standard tabs with added assessment info tab and extratabs
     *
     * @param array|stdClass $course
     * @param array|stdClass $formatoptions
     * @param array|stdClass $sections
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    public function prepare_tabs($course, $formatoptions, $sections) {
        // Get the standard tabs.
        $tabs = parent::prepare_tabs($course, $formatoptions, $sections);

        // Merge old extratabs.
        $tabs = array_merge($tabs, $this->prepare_extratabs($course, $formatoptions));

        // Merge tab(s) for assessment information (old and new).
        $tabs = array_merge($tabs, $this->prepare_assessment_tabs($course));

        $this->tabs = $tabs;
        return $tabs;
    }

    /**
     * Prepare the old extratabs for legacy reasons
     *
     * @return array
     */
    public function prepare_extratabs($course, $formatoptions) {
        $extratabnames = array('extratab1', 'extratab2', 'extratab3');
        $extratabs = array();
        foreach ($extratabnames as $extratabname) {
            if (isset($this->tcsettings["enable_{$extratabname}"]) &&
                $this->tcsettings["enable_{$extratabname}"] == 1) {
                $tab = (object) new stdClass();
                $tab->id = $extratabname;
                $tab->name = $extratabname;
                $tab->title = $this->tcsettings["title_{$extratabname}"];
                $tab->generic_title = ucfirst($extratabname);
                $tab->sections = $extratabname;
                $tab->section_nums = "";
                $tab->content = format_text($this->tcsettings["content_{$extratabname}"], FORMAT_HTML,
                    array('trusted' => true, 'noclean' => true));
                $extratabs[$tab->id] = $tab;
            }
        }
        return $extratabs;
    }

    /**
     * Prepare the assessment Information tabs (old and new)
     * @param array|stdClass $course
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    public function prepare_assessment_tabs($course) {
        global $DB;

        $tabs = array();

        // Get the installed blocks and check if the assessment info block is one of them.
        $sql = "SELECT * FROM {context} cx
                join {block_instances} bi on bi.parentcontextid = cx.id
                where cx.contextlevel = 50 and cx.instanceid = ".$course->id;
        $installedblocks = $DB->get_records_sql($sql, array());
        $assessmentinfoblockid = false;
        foreach ($installedblocks as $installedblock) {
            if ($installedblock->blockname == 'assessment_information') {
                $assessmentinfoblockid = (int)$installedblock->id;
                break;
            }
        }
        // The assessment info block tab.
        if ($assessmentinfoblockid) {
            // Make sure that "Assessment Info Block" title is replaced by the real one ("Assessment Information").
            if (isset($this->tcsettings['tab_assessment_info_block_title']) &&
                $this->tcsettings['tab_assessment_info_block_title'] == 'Assessment Info Block') {
                $this->tcsettings['tab_assessment_info_block_title'] =
                    get_string('tab_assessment_info_block_title', 'format_qmultopics');
                $record = $DB->get_record('course_format_options', array('courseid' => $course->id,
                    'name' => 'tab_assessment_info_block_title'));
                $record->value = $this->tcsettings['tab_assessment_info_block_title'];
                $DB->update_record('course_format_options', $record);
            }

            $tab = (object) new stdClass();
            $tab->id = "tab_assessment_info_block";
            $tab->name = 'assessment_info_block';
            $tab->title = $this->tcsettings['tab_assessment_info_block_title'];
            $tab->generic_title = get_string('tab_assessment_info_title', 'format_qmulweeks');
            $tab->content = ''; // Not required - we are only interested in the tab.
            $tab->sections = "block_assessment_information";
            $tab->section_nums = "";
            $tabs[$tab->id] = $tab;
            // In case the assment info tab is not present but should be in the tab sequence when used fix this.
            if (strlen($this->tcsettings['tab_seq']) && !strstr($this->tcsettings['tab_seq'], $tab->id)) {
                $this->tcsettings['tab_seq'] .= ','.$tab->id;
            }
        }

        // The old assessment info tab - as a new tab.
        if (isset($this->tcsettings['enable_assessmentinformation']) &&
            $this->tcsettings['enable_assessmentinformation'] == 1) {
            $tab = (object) new stdClass();
            $tab->id = "tab_assessment_information";
            $tab->name = 'assessment_info';
            $tab->title = $this->tcsettings['tab_assessment_information_title'];
            $tab->generic_title = get_string('tab_assessment_information_title', 'format_qmulweeks');
            // Get the synergy assessment info and store the result as content for this tab.
            $tab->content = $this->get_assessmentinformation($this->tcsettings['content_assessmentinformation']);
            $tab->sections = "assessment_information";
            $tab->section_nums = "";
            $tabs[$tab->id] = $tab;
            // In case the assment info tab is not present but should be in the tab sequence when used fix this.
            if (strlen($this->tcsettings['tab_seq']) && !strstr($this->tcsettings['tab_seq'], $tab->id)) {
                $this->tcsettings['tab_seq'] .= ','.$tab->id;
            }
        }

        return $tabs;
    }

    /**
     * Get the content for the assessment information section
     *
     * @param $content
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function get_assessmentinformation($content) {
        global $CFG, $DB, $COURSE, $USER;

        $output = html_writer::tag('div', format_text($content), array('class' => 'assessmentinfo col-12 mb-3'));

        $assignments = $this->get_assignments();

        $assignoutput = html_writer::tag('div', get_string('assignmentsdue', 'format_qmulweeks'),
            array('class' => 'card-header h5'));
        $assignoutput .= html_writer::start_tag('div', array('class' => 'list-group list-group-flush'));
        $assignsubmittedoutput = html_writer::tag('div', get_string('assignmentssubmitted',
            'format_qmulweeks'), array('class' => 'card-header h5'));
        $assignsubmittedoutput .= html_writer::start_tag('div', array('class' => 'list-group list-group-flush'));

        $modinfo = get_fast_modinfo($COURSE);

        $submitted = 0;
        $due = 0;
        foreach ($assignments as $assignment) {

            $context = context_module::instance($assignment->cmid);
            $canviewhidden = has_capability('moodle/course:viewhiddenactivities', $context);

            $hidden = '';
            if (!$assignment->visible) {
                $hidden = ' notvisible';
            }

            $cminfo = $modinfo->get_cm($assignment->cmid);

            $conditionalhidden = false;
            if (!empty($CFG->enableavailability)) {
                $info = new \core_availability\info_module($cminfo);
                if (!$info->is_available_for_all()) {
                    $information = '';
                    if ($info->is_available($information)) {
                        $hidden = ' conditionalhidden';
                        $conditionalhidden = false;
                    } else {
                        $hidden = ' notvisible conditionalhidden';
                        $conditionalhidden = true;
                    }
                }
            }

            $accessiblebutdim = (!$assignment->visible || $conditionalhidden) && $canviewhidden;

            if ((!$assignment->visible || $conditionalhidden) && !$canviewhidden) {
                continue;
            }

            // Check overrides for new duedate.
            $sql = "SELECT
                    module.id,
                    module.allowsubmissionsfromdate AS timeopen,
                    module.duedate AS timeclose";
            $groups = groups_get_user_groups($COURSE->id);
            $groupbysql = '';
            $params = array();
            if ($groups[0]) {
                list ($groupsql, $params) = $DB->get_in_or_equal($groups[0]);
                $sql .= ", CASE WHEN ovrd1.allowsubmissionsfromdate IS NULL THEN MIN(ovrd2.allowsubmissionsfromdate)
                    ELSE ovrd1.allowsubmissionsfromdate END AS timeopenover,
                    CASE WHEN ovrd1.duedate IS NULL THEN MAX(ovrd2.duedate) ELSE ovrd1.duedate END AS timecloseover
                    FROM {assign} module
                    LEFT JOIN {assign_overrides} ovrd1 ON module.id=ovrd1.assignid AND $USER->id=ovrd1.userid
                    LEFT JOIN {assign_overrides} ovrd2 ON module.id=ovrd2.assignid AND ovrd2.groupid $groupsql";
                $groupbysql = " GROUP BY module.id, timeopen, timeclose, ovrd1.allowsubmissionsfromdate, ovrd1.duedate";
            } else {
                $sql .= ", ovrd1.allowsubmissionsfromdate AS timeopenover, ovrd1.duedate AS timecloseover
                     FROM {assign} module
                     LEFT JOIN {assign_overrides} ovrd1
                     ON module.id=ovrd1.assignid AND $USER->id=ovrd1.userid";
            }
            $sql .= " WHERE module.course = ?";
            $sql .= " AND module.id = ?";
            $sql .= $groupbysql;
            $params[] = $COURSE->id;
            $params[] = $assignment->id;
            $overrides = $DB->get_records_sql($sql, $params);
            $overrides = reset($overrides);
            if (!empty($overrides->timecloseover)) {
                $assignment->duedate = $overrides->timecloseover;
                if ($overrides->timeopenover) {
                    $assignment->open = $overrides->open;
                }
            }

            $out = '';
            $url = new moodle_url('/mod/assign/view.php', array('id' => $assignment->cmid));
            if ($assignment->status == 'submitted') {
                $duestatus = get_string('submitted', 'widgettype_assignments');
                $statusclass = 'success';
            } else if ($assignment->status == 'draft') {
                $duestatus = get_string('draft', 'widgettype_assignments');
                $statusclass = 'info';
            } else if ($assignment->duedate > 0 && $assignment->duedate < time()) {
                $duestatus = get_string('overdue', 'widgettype_assignments');
                $statusclass = 'danger';
            } else if ($assignment->duedate > 0 && $assignment->duedate < (time() + 14 * DAYSECS)) {
                $duestatus = get_string('duesoon', 'widgettype_assignments');
                $statusclass = 'warning';
            } else {
                $duestatus = '';
                $statusclass = 'info';
            }

            $duedate = date('d/m/Y', $assignment->duedate);

            $out .= html_writer::start_tag('div', array('class' => 'list-group-item assignment'.$hidden));

            $out .= html_writer::start_tag('div', array('class' => 'd-flex flex-wrap align-items-center mb-2'));
            $out .= $this->output->pix_icon('icon', 'assign', 'mod_assign', ['class' => 'mr-2']);
            $out .= html_writer::link($url, $assignment->name, array('class' => 'name col p-0'));

            if ($assignment->duedate > 0) {
                $out .= html_writer::tag('div', $duedate, array('class' => 'due-date ml-auto badge badge-'.$statusclass,
                    'data-toggle' => 'tooltip', 'data-placement' => 'top', 'title' => $duestatus));
            }
            $out .= html_writer::end_tag('div');

            if ($assignment->showdescription) {
                $out .= html_writer::tag('div', format_text($assignment->intro), array('class' => "summary pl-4"));
            }
            $out .= html_writer::end_tag('div');

            if ($assignment->status == 'submitted') {
                $submitted++;
                $assignsubmittedoutput .= $out;
            } else {
                $due++;
                $assignoutput .= $out;
            }
        }
        if ($submitted == 0) {
            $assignsubmittedoutput .= html_writer::tag('div', get_string('noassignmentssubmitted',
                'format_qmulweeks'), array('class' => 'card-body'));
        }
        if ($due == 0) {
            $assignoutput .= html_writer::tag('div', get_string('noassignmentsdue',
                'format_qmulweeks'), array('class' => 'card-body'));
        }
        $assignoutput .= html_writer::end_tag('div');
        $assignsubmittedoutput .= html_writer::end_tag('div');
        $assignoutput = html_writer::tag('div', $assignoutput, array('class' => 'card'));
        $assignsubmittedoutput = html_writer::tag('div', $assignsubmittedoutput, array('class' => 'card'));

        $output .= html_writer::tag('div', $assignoutput, array('class' => 'col-12 col-md-6 mb-1'));
        $output .= html_writer::tag('div', $assignsubmittedoutput, array('class' => 'col-12 col-md-6 mb-1'));

        return html_writer::tag('div', $output, array('class' => 'row'));
    }

    /**
     * Get assignments for assessment information
     *
     * @return moodle_recordset
     * @throws dml_exception
     */
    public function get_assignments() {
        global $DB, $COURSE, $USER;
        $sql = "
       SELECT a.id, cm.id AS cmid, cm.visible, cm.showdescription, a.name, a.duedate, s.status, a.intro, g.grade, gi.gradepass,
              gi.hidden As gradehidden, a.markingworkflow, uf.workflowstate
         FROM {assign} a
         JOIN {course_modules} cm ON cm.instance = a.id
         JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
         JOIN (SELECT DISTINCT e.courseid
                          FROM {enrol} e
                          JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = :userid1
                         WHERE e.status = :enabled AND ue.status = :active
                           AND ue.timestart < :now1 AND (ue.timeend = 0 OR ue.timeend > :now2)
              ) en ON (en.courseid = a.course)
         LEFT JOIN {assign_submission} s ON s.assignment = a.id AND s.userid = :userid2 AND s.latest = 1
         LEFT JOIN {assign_grades} g ON g.assignment = a.id AND g.userid = :userid3 AND g.attemptnumber = s.attemptnumber
         LEFT JOIN {grade_items} gi ON gi.iteminstance = a.id AND itemmodule = 'assign'
         LEFT JOIN {assign_user_flags} uf ON uf.assignment = a.id AND uf.userid = s.userid
        WHERE a.course = :courseid
        ORDER BY a.duedate
    ";
        $params = [
            'userid1' => $USER->id, 'userid2' => $USER->id, 'userid3' => $USER->id,
            'now1' => time(), 'now2' => time(),
            'active' => ENROL_USER_ACTIVE, 'enabled' => ENROL_INSTANCE_ENABLED,
            'courseid' => $COURSE->id
        ];

        $assignments = $DB->get_recordset_sql($sql, $params);
        return $assignments;
    }

    /**
     * Render a standard tab or an extratab - as long as they are still around...
     *
     * @param array|stdClass $tab
     * @return bool|string
     * @throws coding_exception
     * @throws dml_exception
     */
    public function render_tab($tab) {
        if (!isset($tab)) {
            return false;
        }
        // As long as there are still old extratabs around we need to treat them slightly different from normal tabs.
        // Tthis overriding function may be removed once extratabs are gone.
        if (strstr($tab->id, 'extratab')) {
            return $this->render_extratab($tab);
        } else {
            return parent::render_tab($tab);
        }
    }

    /**
     * Render an extratab
     *
     * @param $tab
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     */
    public function render_extratab($tab) {
        global $DB;
        $o = '';
        if ($tab->sections == '') {
            $o .= html_writer::start_tag('li', array('class' => 'tabitem nav-item', 'style' => 'display:none;'));
        } else {
            $o .= html_writer::start_tag('li', array('class' => 'tabitem nav-item'));
        }

        $sectionsarray = explode(',', str_replace(' ', '', $tab->sections));
        if ($sectionsarray[0]) {
            while ($sectionsarray[0] == "0") { // Remove any occurences of section-0.
                array_shift($sectionsarray);
            }
        }

        if ($this->page->user_is_editing()) {
            // Get the format option record for the given tab - we need the id.
            // If the record does not exist, create it first.
            if (!$DB->record_exists('course_format_options', array('courseid' => $this->page->course->id,
                'name' => 'title_'.$tab->id))) {
                $record = (object) new stdClass();
                $record->courseid = $this->page->course->id;
                $record->format = 'qmulweeks';
                $record->section = 0;
                $record->name = 'title_'.$tab->id;
                $record->value = $tab->id;
                $DB->insert_record('course_format_options', $record);
            }

            $formatoptiontab = $DB->get_record('course_format_options', array('courseid' => $this->page->course->id,
                'name' => 'title_'.$tab->id));
            $itemid = $formatoptiontab->id;
        } else {
            $itemid = false;
        }

        if ($tab->id == 'tab0') {
            $o .= '<span
                data-toggle="tab" id="'.$tab->id.'"
                sections="'.$tab->sections.'"
                section_nums="'.$tab->section_nums.'"
                class="tablink nav-link "
                tab_title="'.$tab->title.'",
                generic_title = "'.$tab->generic_title.'"
                >';
        } else {
            $o .= '<span
                data-toggle="tab" id="'.$tab->id.'"
                sections="'.$tab->sections.'"
                section_nums="'.$tab->section_nums.'"
                class="tablink topictab nav-link "
                tab_title="'.$tab->title.'"
                generic_title = "'.$tab->generic_title.'"
                style="'.($this->page->user_is_editing() ? 'cursor: move;' : '').'">';
        }
        // Render the tab name as inplace_editable.
        $tmpl = new \core\output\inplace_editable('format_weeks2', 'tabname', $itemid,
            $this->page->user_is_editing(),
            format_string($tab->title), $tab->title, get_string('tabtitle_edithint',
                'format_weeks2'),  get_string('tabtitle_editlabel', 'format_weeks2',
                format_string($tab->title)));
        $o .= $this->output->render($tmpl);
        $o .= "</span>";
        $o .= html_writer::end_tag('li');
        return $o;
    }

    /**
     * Render sections with added assessment info and extratab sections
     *
     * @param array|stdClass $course
     * @param array|stdClass $sections
     * @param array|stdClass $formatoptions
     * @param array|stdClass $modinfo
     * @param int $numsections
     * @return string
     * @throws dml_exception
     */
    public function render_sections($course, $sections, $formatoptions, $modinfo, $numsections) {
        global $DB;

        /*
        First we check if the course used a legacy COLLAPSE course display -
        and if so set the coursedisplay option correctly if needed
        */
        if ($formatoptions['coursedisplay'] == COURSE_DISPLAY_COLLAPSE) {
            $cdrecord = $DB->get_record('course_format_options', array('courseid' => $course->id,
                'name' => 'coursedisplay'));
            $cdrecord->value = COURSE_DISPLAY_SINGLEPAGE;
            $DB->update_record('course_format_options', $cdrecord);
            $course->coursedisplay = COURSE_DISPLAY_SINGLEPAGE;
            $formatoptions['coursedisplay'] == COURSE_DISPLAY_SINGLEPAGE;
        }

        $o = '';
        $o .= $this->render_assessment_section($formatoptions);
        $o .= $this->render_extratab_sections($formatoptions);
        $o .= parent::render_sections($course, $sections, $formatoptions, $modinfo, $numsections);
        return $o;
    }

    /**
     * Render extratab sections as long as they are still around...
     *
     * @param array|stdClass $formatoptions
     * @return string
     */
    public function render_extratab_sections($formatoptions) {
        $extratabnames = array('extratab1', 'extratab2', 'extratab3');
        $o = '';
        foreach ($extratabnames as $extratabname) {
            if ($formatoptions['enable_'.$extratabname]) {
                $o .= html_writer::start_tag('li', array('id' => $extratabname, 'section-id' => $extratabname,
                    'class' => 'extratab section', 'style' => 'display: none;'));

                // Show the extratab title.
                $o .= html_writer::start_tag('h3', array('class' => 'sectionname'));
                $o .= $this->tabs[$extratabname]->title;
                $o .= html_writer::end_tag('h3');
                // Show the content.
                $o .= html_writer::start_tag('div', array('class' => 'content'));
                $o .= html_writer::start_tag('div', array('class' => 'summary'));
                $o .= $this->tabs[$extratabname]->content;
                $o .= html_writer::end_tag('div');
                $o .= html_writer::end_tag('div');

                $o .= html_writer::end_tag('li');
            }
        }
        return $o;
    }

    /**
     * Render section for assessment information
     *
     * @param array|stdClass $formatoptions
     * @return string
     */
    public function render_assessment_section($formatoptions) {
        $o = '';
        if (isset($formatoptions['enable_assessmentinformation']) && $formatoptions['enable_assessmentinformation']) {
            // If the option to merge assessment information add a specific class as indicator for JS.
            if (isset($formatoptions['assessment_info_block_tab']) && $formatoptions['assessment_info_block_tab'] == '2') {
                $o .= html_writer::start_tag('div', array('id' => 'content_assessmentinformation_area',
                    'section-id' => 'assessment_information', 'class' => 'section merge_assessment_info',
                    'style' => 'display: none;'));
            } else {
                $o .= html_writer::start_tag('div', array('id' => 'content_assessmentinformation_area',
                    'section-id' => 'assessment_information', 'class' => 'section', 'style' => 'display: none;'));
            }
            $o .= html_writer::start_tag('div', array('class' => 'content'));
            $o .= html_writer::start_tag('div', array('class' => 'summary'));
            $o .= $this->tabs['tab_assessment_information']->content;
            $o .= html_writer::end_tag('div');
            $o .= html_writer::end_tag('div');
            $o .= html_writer::end_tag('div');
        }

        // Get any summary text from the hidden section that is automatically created by the Assessment Information tab.
        $o .= $this->render_aitext();

        // Render an inititially invisible assessment_information_area.
        $content = '';
        $o .= html_writer::tag('div', $content, array('id' => 'assessment_information_area', 'style' => 'display: none;'));

        return $o;
    }

    /**
     * Render any summary text from the hidden section that is automatically created by the Assessment Information tab
     *
     * @return string
     * @throws dml_exception
     */
    public function render_aitext() {
        global $COURSE;
        $o = '';
        if ($airecord = $this->get_ai_section($COURSE) && isset($airecord->summary)) {
            $o .= html_writer::start_tag('div', array('id' => 'assessment_information_summary', 'style' => 'display: none;'));
            $o .= html_writer::div($airecord->summary);
            $o .= html_writer::empty_tag('br');
            $o .= html_writer::end_div();
        }

        return $o;
    }

    /**
     * get a section created by the Assessment Information block
     * for now it is identified by hacking the sequence field of that section:
     * if it contains the section id 666 (the number of the beast as we are doing evil here...) it is related to the AI block.
     *
     * @param $course
     * @return mixed
     * @throws dml_exception
     */
    protected function get_ai_section($course) {
        global $DB;
        $sql = "
            select *
            from {course_sections}
            where course = $course->id
            and (sequence = '666' or sequence like '666,%' or sequence like '%,666,%' or sequence like '%,666')
        ";
        $result = $DB->get_records_sql($sql);
        return reset($result); // Get the 1st element of the returned array - should have one element only anyway.
    }

    /**
     * Start the section list
     *
     * @return string
     */
    protected function start_section_list() {
        $o = '';
        $o .= html_writer::start_tag('div', array('id' => 'modulecontent', 'class' => 'tab-pane modulecontent active'));
        $o .= html_writer::start_tag('ul', array('class' => 'topics weeks2 qmulweeks'));
        return $o;
    }

    /**
     * End the section list
     *
     * @return string
     */
    protected function end_section_list() {
        $o = '';
        $o .= html_writer::end_tag('ul');
        $o .= html_writer::end_tag('div');
        return $o;
    }

    /**
     * Section title - either with toggle or straight.
     *
     * @param stdClass $section
     * @param stdClass $course
     * @return string
     * @throws coding_exception
     */
    public function section_title($section, $course) {
        if ($course->coursedisplay == COURSE_DISPLAY_SINGLEPAGE) {
            // Prepare the toggle.
            if (isset($this->toggle_seq)) {
                $toggleseq = (array) json_decode($this->toggle_seq);
            } else {
                $toggleseq = [];
            }

            // Weird rearranging the array due to error with PHP below version 7.2.
            // NO idea why this is needed - but it works.
            if (version_compare(PHP_VERSION, '7.2.0') < 0) {
                $toggleseq2 = array();
                foreach ($toggleseq as $key => $value) {
                    $toggleseq2[$key] = $value;
                }
                $toggleseq = $toggleseq2;
            }

            $tooltipopen = get_string('tooltip_open', 'format_weeks2');
            $tooltipclosed = get_string('tooltip_closed', 'format_weeks2');

            if (isset($toggleseq[$section->id]) && $toggleseq[$section->id] === '1' ||
                (!count($toggleseq) && isset($course->defaultcollapse) && $course->defaultcollapse)
            ) {
                $toggler = '<i class="toggler toggler_open fa fa-angle-down" title="'.$tooltipopen
                    .'" style="cursor: pointer;"></i>';
                $toggler .= '<i class="toggler toggler_closed fa fa-angle-right" title="'
                    .$tooltipclosed.'" style="cursor: pointer; display: none;"></i>';
            } else {
                $toggler = '<i class="toggler toggler_open fa fa-angle-down" title="'.$tooltipopen
                    .'" style="cursor: pointer; display: none;"></i>';
                $toggler .= '<i class="toggler toggler_closed fa fa-angle-right" title="'.$tooltipclosed
                    .'" style="cursor: pointer;"></i>';
            }

            $toggler .= ' ';
        } else {
            $toggler = '';
        }

        return $toggler.$this->render(course_get_format($course)->inplace_editable_render_section_name($section));
    }

    /**
     * Section body
     *
     * @param stdClass $section
     * @param stdClass $course
     * @return string
     */
    public function section_body($section, $course) {
        $o = '';

        if (isset($this->toggle_seq)) {
            $toggleseq = (array) json_decode($this->toggle_seq);
        } else {
            $toggleseq = [];
        }

        // Weird rearranging the array due to error with PHP below version 7.2.
        // NO idea why this is needed - but it works.
        if (version_compare(PHP_VERSION, '7.2.0') < 0) {
            $toggleseq2 = array();
            foreach ($toggleseq as $key => $value) {
                $toggleseq2[$key] = $value;
            }
            $toggleseq = $toggleseq2;
        }

        if ($course->coursedisplay == COURSE_DISPLAY_SINGLEPAGE &&
            isset($toggleseq[$section->id]) &&
            $toggleseq[$section->id] === '1' ||
            ($section->section == 0 && $section->name == '') ||
            (!count($toggleseq) && isset($course->defaultcollapse) && $course->defaultcollapse)
        ) {
            $o .= html_writer::start_tag('div', array('class' => 'sectionbody summary toggle_area showing'));
        } else {
            $o .= html_writer::start_tag('div',
                array('class' => 'sectionbody summary toggle_area hidden', 'style' => 'display: none;'));
        }
        if ($section->uservisible || $section->visible) {
            // Show summary if section is available or has availability restriction information.
            // Do not show summary if section is hidden but we still display it because of course setting.
            $o .= $this->format_summary_text($section);
        }
        return $o;

    }

}

