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
 * This file contains main class for the course format Weeks
 *
 * @since     Moodle 2.0
 * @package   format_qmulweeks
 * @copyright 2019 Matthias Opitz
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot. '/course/format/lib.php');
require_once($CFG->dirroot. '/course/format/weeks2/lib.php');

/**
 * Main class for the Weeks course format
 *
 * @package    format_qmulweeks
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_qmulweeks extends format_weeks2 {

    /**
     * Adds format options elements to the course/section edit form
     *
     * This function is called from {@link course_edit_form::definition_after_data()}
     *
     * @param MoodleQuickForm $mform form the elements are added to
     * @param bool $forsection 'true' if this is a section edit form, 'false' if this is course edit form
     * @return array array of references to the added form elements
     */
    public function create_edit_form_elements(&$mform, $forsection = false) : array {
        global $CFG, $OUTPUT;
        $elements = parent::create_edit_form_elements($mform, $forsection);
        $elements = array_values($elements);

        if ($forsection == false) {
            // Assessment Information
            $elements[] = $mform->addElement('header', 'assessmentinformation', get_string('assessmentinformation', 'format_qmultc'));
            $mform->addHelpButton('assessmentinformation', 'assessmentinformation', 'format_qmultc', '', true);
            $elements[] = $mform->addElement('checkbox', 'enable_assessmentinformation', get_string('enabletab', 'format_qmultc'));
            $elements[] = $mform->addElement('htmleditor', 'content_assessmentinformation', get_string('assessmentinformation', 'format_qmultc'));

            // Extra Tab 1
            $elements[] = $mform->addElement('header', 'extratab1', get_string('extratab', 'format_qmultc', 1));
            $mform->addHelpButton('extratab1', 'extratab', 'format_qmultc', '', true);
            $elements[] = $mform->addElement('checkbox', 'enable_extratab1', get_string('enabletab', 'format_qmultc'));
            $elements[] = $mform->addElement('text', 'title_extratab1', get_string('tabtitle', 'format_qmultc'));
            $elements[] = $mform->addElement('htmleditor', 'content_extratab1', get_string('tabcontent', 'format_qmultc'));

            // Extra Tab 2
            $elements[] = $mform->addElement('header', 'extratab2', get_string('extratab', 'format_qmultc', 2));
            $mform->addHelpButton('extratab2', 'extratab', 'format_qmultc', '', true);
            $elements[] = $mform->addElement('checkbox', 'enable_extratab2', get_string('enabletab', 'format_qmultc'));
            $elements[] = $mform->addElement('text', 'title_extratab2', get_string('tabtitle', 'format_qmultc'));
            $elements[] = $mform->addElement('htmleditor', 'content_extratab2', get_string('tabcontent', 'format_qmultc'));

            // Extra Tab 3
            $elements[] = $mform->addElement('header', 'extratab3', get_string('extratab', 'format_qmultc', 3));
            $mform->addHelpButton('extratab3', 'extratab', 'format_qmultc', '', true);
            $elements[] = $mform->addElement('checkbox', 'enable_extratab3', get_string('enabletab', 'format_qmultc'));
            $elements[] = $mform->addElement('text', 'title_extratab3', get_string('tabtitle', 'format_qmultc'));
            $elements[] = $mform->addElement('htmleditor', 'content_extratab3', get_string('tabcontent', 'format_qmultc'));
        }
        return $elements;
    }

    public function check_assessment_information($data) {
        global $COURSE, $DB;
        // If the Assessment Information option is UNset make sure the Assessment Information Block is removed from that course
        if (! isset($data['enable_assessmentinformation']) || $data['enable_assessmentinformation'] == '0') {
            // get the installed blocks and check if the assessment info block is one of them
            $sql = "SELECT * FROM {context} cx join {block_instances} bi on bi.parentcontextid = cx.id where cx.contextlevel = 50 and cx.instanceid = ".$COURSE->id;
            $installed_blocks = $DB->get_records_sql($sql, array());
            $assessment_info_block_id = false;
            foreach($installed_blocks as $installed_block) {
                if($installed_block->blockname == 'assessment_information') {
                    $assessment_info_block_id = (int)$installed_block->id;
                    break;
                }
            }

            // It is installed and will have to go
            if($assessment_info_block_id) {
                // get block context for the course - then delete the AI block with that context
                $context = $DB->get_record('context', array('instanceid' => $COURSE->id, 'contextlevel' => '50'));
                if (isset($context->id) && $context->id > 0) {
                    $DB->delete_records('block_instances', array('blockname' => 'assessment_information', 'parentcontextid' => $context->id));
                }
            }
        }
    }

    public function edit_form_validation($data, $files, $errors) : array {

        $return = parent::edit_form_validation($data, $files, $errors);

        if (isset($data['enable_extratab1'])) {
            if (empty($data['title_extratab1'])) {
                $return['title_extratab1'] = get_string('titlerequiredwhenenabled', 'format_qmulweeks');
            }
        } else {
            $data['enabled_extratab1'] = 0;
        }
        if (isset($data['enable_extratab2'])) {
            if (empty($data['title_extratab2'])) {
                $return['title_extratab2'] = get_string('titlerequiredwhenenabled', 'format_qmulweeks');
            }
        } else {
            $data['enabled_extratab1'] = 0;
        }
        if (isset($data['enable_extratab3'])) {
            if (empty($data['title_extratab3'])) {
                $return['title_extratab3'] = get_string('titlerequiredwhenenabled', 'format_qmulweeks');
            }
        } else {
            $data['enabled_extratab1'] = 0;
        }

        // Check the AI option and act accordingly
//        $this->check_assessment_information($data);

        return $return;
    }

    /**
     * Updates format options for a course
     *
     * In case if course format was changed to 'Collapsed Topics', we try to copy options
     * 'coursedisplay', 'numsections' and 'hiddensections' from the previous format.
     * If previous course format did not have 'numsections' option, we populate it with the
     * current number of sections.  The layout and colour defaults will come from 'course_format_options'.
     *
     * @param stdClass|array $data return value from {@link moodleform::get_data()} or array with data
     * @param stdClass $oldcourse if this function is called from {@link update_course()}
     *     this object contains information about the course before update
     * @return bool whether there were any changes to the options values
     */
    public function update_course_format_options($data, $oldcourse = null) : bool {
        global $DB;

        $newdata = (array) $data;
        $savedata = array();
        if (isset($newdata['fullname'])) {
            if (isset($newdata['enable_assessmentinformation'])) {
                $savedata['enable_assessmentinformation'] = $newdata['enable_assessmentinformation'];
            } else {
                $savedata['enable_assessmentinformation'] = 0;
            }
            if (isset($newdata['content_assessmentinformation'])) {
                $savedata['content_assessmentinformation'] = $newdata['content_assessmentinformation'];
            }
            if (isset($newdata['enable_extratab1'])) {
                $savedata['enable_extratab1'] = $newdata['enable_extratab1'];
            } else {
                $savedata['enable_extratab1'] = 0;
            }
            if (isset($newdata['title_extratab1'])) {
                $savedata['title_extratab1'] = $newdata['title_extratab1'];
            }
            if (isset($newdata['content_extratab1'])) {
                $savedata['content_extratab1'] = $newdata['content_extratab1'];
            }
            if (isset($newdata['enable_extratab2'])) {
                $savedata['enable_extratab2'] = $newdata['enable_extratab2'];
            } else {
                $savedata['enable_extratab2'] = 0;
            }
            if (isset($newdata['title_extratab2'])) {
                $savedata['title_extratab2'] = $newdata['title_extratab2'];
            }
            if (isset($newdata['content_extratab2'])) {
                $savedata['content_extratab2'] = $newdata['content_extratab2'];
            }
            if (isset($newdata['enable_extratab3'])) {
                $savedata['enable_extratab3'] = $newdata['enable_extratab3'];
            } else {
                $savedata['enable_extratab3'] = 0;
            }
            if (isset($newdata['title_extratab3'])) {
                $savedata['title_extratab3'] = $newdata['title_extratab3'];
            }
            if (isset($newdata['content_extratab3'])) {
                $savedata['content_extratab3'] = $newdata['content_extratab3'];
            }
        }

        $records = $DB->get_records('course_format_options',
                array('courseid' => $this->courseid,
                      'format' => $this->format,
                      'sectionid' => 0
                    ), '', 'name,id,value');

        foreach ($savedata as $key => $value) {
            // from 3.6 on HTML editor will return an array - if so just get the txt to store
            if(gettype($value) == 'array' && isset($value['text'])){
                $value = $value['text'];
            }
             if (isset($records[$key])) {
                if (array_key_exists($key, $newdata) && $records[$key]->value !== $newdata[$key]) {
                    $DB->set_field('course_format_options', 'value',
                            $value, array('id' => $records[$key]->id));
                    $changed = true;
                } else {
                    $DB->set_field('course_format_options', 'value',
                            $value, array('id' => $records[$key]->id));
                    $changed = true;
                }
            } else {
                $DB->insert_record('course_format_options', (object) array(
                    'courseid' => $this->courseid,
                    'format' => $this->format,
                    'sectionid' => 0,
                    'name' => $key,
                    'value' => $value
                ));
            }
        }

        $changes = parent::update_course_format_options($data, $oldcourse);

        return $changes;
    }

    /**
     * Returns the format options stored for this course or course section
     *
     * When overriding please note that this function is called from rebuild_course_cache()
     * and section_info object, therefore using of get_fast_modinfo() and/or any function that
     * accesses it may lead to recursion.
     *
     * @param null|int|stdClass|section_info $section if null the course format options will be returned
     *     otherwise options for specified section will be returned. This can be either
     *     section object or relative section number (field course_sections.section)
     * @return array
     */
    public function get_format_options($section = null) : array {
        global $DB;

        $options = parent::get_format_options($section);

        if ($section === null) {
            // course format options will be returned
            $sectionid = 0;
        } else if ($this->courseid && isset($section->id)) {
            // course section format options will be returned
            $sectionid = $section->id;
        } else if ($this->courseid && is_int($section) &&
                ($sectionobj = $DB->get_record('course_sections',
                        array('section' => $section, 'course' => $this->courseid), 'id'))) {
            // course section format options will be returned
            $sectionid = $sectionobj->id;
        } else {
            // non-existing (yet) section was passed as an argument
            // default format options for course section will be returned
            $sectionid = -1;
        }

        if ($sectionid == 0) {
            $alloptions = $DB->get_records('course_format_options',
                        array('courseid'=>$this->courseid, 'format'=>'qmulweeks',
                            'sectionid'=>0));

            foreach ($alloptions as $option) {
                if (!isset($options[$option->name])) {
                    $options[$option->name] = $option->value;
                }
            }

            $this->formatoptions[$sectionid] = $options;
        }

        return $options;
    }

    /**
     * Definitions of the additional options that this course format uses for course
     *
     * Weeks format uses the following options:
     * - coursedisplay
     * - hiddensections
     * - automaticenddate
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) {
        global $CFG;
        $max_tabs = (isset($CFG->max_tabs) ? $CFG->max_tabs : 5);

        static $courseformatoptions = false;
        if ($courseformatoptions === false) {
            $courseconfig = get_config('moodlecourse');
            $courseformatoptions = array(
                'hiddensections' => array(
                    'default' => $courseconfig->hiddensections,
                    'type' => PARAM_INT,
                ),
                'coursedisplay' => array(
                    'default' => $courseconfig->coursedisplay,
                    'type' => PARAM_INT,
                ),
                'automaticenddate' => array(
                    'default' => 1,
                    'type' => PARAM_BOOL,
                ),
                // format options for the tab-ability
                'section0_ontop0' => array(
                    'default' => false,
                    'type' => PARAM_BOOL,
                    'label' => '',
                    'element_type' => 'hidden',
                ),

                'single_section_tabs' => array(
                    'default' => '0',
                    'type' => PARAM_BOOL,
                ),
/*
                'assessment_info_block_tab' => array(
                    'default' => get_config('format_qmultc', 'defaultshowassessmentinfotab'),
                    'type' => PARAM_BOOL
                ),
*/
                'assessment_info_block_tab' => array(
                    'default' => get_config('format_qmulweeks', 'defaultshowassessmentinfotab'),
                    'type' => PARAM_INT,
                ),
            );

            // the sequence in which the tabs will be displayed
            $courseformatoptions['tab_seq'] = array('default' => '','type' => PARAM_TEXT,'label' => '','element_type' => 'hidden',);

            // now loop through the tabs but don't show them as we only need the DB records...
            $courseformatoptions['tab0_title'] = array('default' => get_string('modulecontent', 'format_qmulgrid'),'type' => PARAM_TEXT,'label' => '','element_type' => 'hidden',);
            $courseformatoptions['tab0'] = array('default' => "",'type' => PARAM_TEXT,'label' => '','element_type' => 'hidden',);
            for ($i = 1; $i <= $max_tabs; $i++) {
                $courseformatoptions['tab'.$i.'_title'] = array('default' => "Tab ".$i,'type' => PARAM_TEXT,'label' => '','element_type' => 'hidden',);
                $courseformatoptions['tab'.$i] = array('default' => "",'type' => PARAM_TEXT,'label' => '','element_type' => 'hidden',);
                $courseformatoptions['tab'.$i.'_sectionnums'] = array('default' => "",'type' => PARAM_TEXT,'label' => '','element_type' => 'hidden',);
            }

            // Allow to store a name for the Assessment Info tab
            $courseformatoptions['tab_assessment_information_title'] = array('default' => get_string('tab_assessment_information_title', 'format_qmulgrid'),'type' => PARAM_TEXT,'label' => '','element_type' => 'hidden',);

            // Allow to store a name for the Assessment Info Block tab
            $courseformatoptions['tab_assessment_info_block_title'] = array('default' => get_string('tab_assessment_info_block_title', 'format_qmulgrid'),'type' => PARAM_TEXT,'label' => '','element_type' => 'hidden',);

        }
        if ($foreditform && !isset($courseformatoptions['coursedisplay']['label'])) {
            $courseformatoptionsedit = array(
                'maxtabs' => array(
                    'label' => get_string('maxtabs_label', 'format_weeks2'),
                    'help' => 'maxtabs',
                    'help_component' => 'format_weeks2',
                    'default' => (isset($CFG->max_tabs) ? $CFG->max_tabs : 5),
                    'type' => PARAM_INT,
//                    'element_type' => 'hidden',
                ),
                'limittabname' => array(
                    'label' => get_string('limittabname_label', 'format_weeks2'),
                    'help' => 'limittabname',
                    'help_component' => 'format_weeks2',
                    'default' => 0,
                    'type' => PARAM_INT,
//                    'element_type' => 'hidden',
                ),

                'hiddensections' => array(
                    'label' => new lang_string('hiddensections'),
                    'help' => 'hiddensections',
                    'help_component' => 'moodle',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            0 => new lang_string('hiddensectionscollapsed'),
                            1 => new lang_string('hiddensectionsinvisible')
                        )
                    ),
                ),
                'coursedisplay' => array(
                    'label' => new lang_string('coursedisplay'),
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            COURSE_DISPLAY_SINGLEPAGE => new lang_string('coursedisplay_single'),
                            COURSE_DISPLAY_COLLAPSE => get_string('coursedisplay_collapse', 'format_qmulweeks'),
                            COURSE_DISPLAY_MULTIPAGE => new lang_string('coursedisplay_multi')
                        )
                    ),
                    'help' => 'coursedisplay',
                    'help_component' => 'moodle',
                ),
                'automaticenddate' => array(
                    'label' => new lang_string('automaticenddate', 'format_weeks'),
                    'help' => 'automaticenddate',
                    'help_component' => 'format_weeks',
                    'element_type' => 'advcheckbox',
                ),

                'section0_ontop' => array(
                    'label' => get_string('section0_label', 'format_weeks2'),
                    'element_type' => 'advcheckbox',
                    'default' => 0,
                    'help' => 'section0',
                    'help_component' => 'format_weeks2',
                    'element_type' => 'hidden',
                ),
                'single_section_tabs' => array(
                    'label' => get_string('single_section_tabs_label', 'format_qmulweeks'),
                    'element_type' => 'advcheckbox',
                    'help' => 'single_section_tabs',
                    'help_component' => 'format_qmulweeks',
                ),
                'assessment_info_block_tab' => array(
                    'label' => get_string('assessment_info_block_tab_label', 'format_qmulweeks'),
                    'help' => 'assessment_info_block_tab',
                    'help_component' => 'format_qmulweeks',
                    'element_type' => 'select',
//                    'element_type' => 'hidden',
                    'element_attributes' => array(
                        array(0 => get_string('assessment_info_block_tab_option0', 'format_qmulweeks'),
                            1 => get_string('assessment_info_block_tab_option1', 'format_qmulweeks'),
                            2 => get_string('assessment_info_block_tab_option2', 'format_qmulweeks'))
                    )
                ),
            );
            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }
        return $courseformatoptions;
    }

    public function section_action($section, $action, $sr) {
        global $PAGE;

        $tcsettings = $this->get_format_options();
        if ($section->section && ($action === 'setmarker' || $action === 'removemarker')) {
            // Format 'tabtopics' allows to set and remove markers in addition to common section actions.
            require_capability('moodle/course:setcurrentsection', context_course::instance($this->courseid));
            course_set_marker($this->courseid, ($action === 'setmarker') ? $section->section : 0);
            return null;
        }

        switch ($action) {
            case 'movetotabzero':
                return $this->move2tab(0, $section, $tcsettings);
                break;
            case 'movetotabone':
                return $this->move2tab(1, $section, $tcsettings);
                break;
            case 'movetotabtwo':
                return $this->move2tab(2, $section, $tcsettings);
                break;
            case 'movetotabthree':
                return $this->move2tab(3, $section, $tcsettings);
                break;
            case 'movetotabfour':
                return $this->move2tab(4, $section, $tcsettings);
                break;
            case 'movetotabfive':
                return $this->move2tab(5, $section, $tcsettings);
                break;
            case 'movetotabsix':
                return $this->move2tab(6, $section, $tcsettings);
                break;
            case 'movetotabseven':
                return $this->move2tab(7, $section, $tcsettings);
                break;
            case 'movetotabeight':
                return $this->move2tab(8, $section, $tcsettings);
                break;
            case 'movetotabnine':
                return $this->move2tab(9, $section, $tcsettings);
                break;
            case 'movetotabten':
                return $this->move2tab(10, $section, $tcsettings);
                break;
            case 'removefromtabs':
                return $this->removefromtabs($PAGE->course, $section, $tcsettings);
                break;
            case 'sectionzeroontop':
                return $this->sectionzeroswitch($tcsettings, true);
                break;
            case 'sectionzeroinline':
                return $this->sectionzeroswitch($tcsettings, false);
                break;
        }

        // For show/hide actions call the parent method and return the new content for .section_availability element.
        $rv = parent::section_action($section, $action, $sr);
        $renderer = $PAGE->get_renderer('format_qmulweeks');
        $rv['section_availability'] = $renderer->section_availability($this->get_section($section));
        return $rv;
    }

// move section ID and section number to tab format settings of a given tab
    public function move2tab($tabnum, $section2move, $settings) {
        global $PAGE;

        $course = $PAGE->course;

        // remove section number from all tab format settings
        $settings = $this->removefromtabs($course, $section2move, $settings);

        // add section number to new tab format settings if not tab0
        if($tabnum > 0){
            $settings['tab'.$tabnum] .= ($settings['tab'.$tabnum] === '' ? '' : ',').$section2move->id;
            $settings['tab'.$tabnum.'_sectionnums'] .= ($settings['tab'.$tabnum.'_sectionnums'] === '' ? '' : ',').$section2move->section;
            $this->update_course_format_options($settings);
        }
        return $settings;
    }

// remove section id from all tab format settings
    public function removefromtabs($course, $section2remove, $settings) {
        global $CFG;

        $max_tabs = (isset($CFG->max_tabs) ? $CFG->max_tabs : 5);

        for($i = 0; $i <= $max_tabs; $i++) {
            if(strstr($settings['tab'.$i], $section2remove->id) > -1) {
                $sections = explode(',', $settings['tab'.$i]);
                $new_sections = array();
                foreach($sections as $section) {
                    if($section != $section2remove->id) {
                        $new_sections[] = $section;
                    }
                }
                $settings['tab'.$i] = implode(',', $new_sections);

                $section_nums = explode(',', $settings['tab'.$i.'_sectionnums']);
                $new_section_nums = array();
                foreach($section_nums as $section_num) {
                    if($section_num != $section2remove->section) {
                        $new_section_nums[] = $section_num;
                    }
                }
                $settings['tab'.$i.'_sectionnums'] = implode(',', $new_section_nums);
                $this->update_course_format_options($settings);
            }
        }
        return $settings;
    }

// switch to show section0 always on top of the tabs
    public function sectionzeroswitch($settings, $value) {
        $settings['section0_ontop'] = $value;
        $this->update_course_format_options($settings);

        return $settings;
    }
}

