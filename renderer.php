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
 * @copyright 2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.3
 */


defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/course/format/renderer.php');
require_once($CFG->dirroot.'/course/format/qmulweeks/lib.php');


/**
 * Basic renderer for qmulweeks format.
 *
 * @copyright 2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_qmulweeks_renderer extends format_section_renderer_base {
    /**
     * Generate the starting container html for a list of sections
     * @return string HTML to output.
     */
    protected function start_section_list() {
        return html_writer::start_tag('ul', array('class' => 'qmulweeks'));
    }

    /**
     * Generate the closing container html for a list of sections
     * @return string HTML to output.
     */
    protected function end_section_list() {
        return html_writer::end_tag('ul');
    }

    /**
     * Generate the title for this section page
     * @return string the page title
     */
    protected function page_title() {
        return get_string('weeklyoutline');
    }

    /**
     * SYNERGY LEARNING - override the 'section_header' function to add the news section.
     * @param stdClass $section
     * @param stdClass $course
     * @param bool $onsectionpage
     * @param null $sectionreturn
     * @return string
     * @throws coding_exception
     */
    protected function section_header($section, $course, $onsectionpage, $sectionreturn=null) {
        global $PAGE;

        $o = '';
        $currenttext = '';
        $sectionstyle = '';

        if ($section->section != 0) {
            // Only in the non-general sections.
            if (!$section->visible) {
                $sectionstyle = ' hidden';
            } else if (course_get_format($course)->is_section_current($section)) {
                $sectionstyle = ' current';
            }
        }

        $o.= html_writer::start_tag('li', array('id' => 'section-'.$section->section,
                                                'class' => 'section main clearfix'.$sectionstyle, 'role'=>'region',
                                                'aria-label'=> get_section_name($course, $section)));

        // Create a span that contains the section title to be used to create the keyboard section move menu.
        $o .= html_writer::tag('span', $this->section_title($section, $course), array('class' => 'hidden sectionname'));

        $leftcontent = $this->section_left_content($section, $course, $onsectionpage);
        $o.= html_writer::tag('div', $leftcontent, array('class' => 'left side'));

        $rightcontent = $this->section_right_content($section, $course, $onsectionpage);
        $o.= html_writer::tag('div', $rightcontent, array('class' => 'right side'));
        $o.= html_writer::start_tag('div', array('class' => 'content'));

        // When not on a section page, we display the section titles except the general section if null
        $hasnamenotsecpg = (!$onsectionpage && ($section->section != 0 || !is_null($section->name)));

        // When on a section page, we only display the general section title, if title is not the default one
        $hasnamesecpg = ($onsectionpage && ($section->section == 0 && !is_null($section->name)));

        $classes = ' accesshide';
        if ($hasnamenotsecpg || $hasnamesecpg) {
            $classes = '';
        }
        $sectionname = html_writer::tag('span', $this->section_title($section, $course));
        $o.= $this->output->heading($sectionname, 3, 'sectionname' . $classes);

        // SYNERGY LEARNING - this is the only different bit.
        if ($section->section == 0) {
            $o .= $this->output_news($course);
        }

        $o.= html_writer::start_tag('div', array('class' => 'summary'));
        $o.= $this->format_summary_text($section);
        $o.= html_writer::end_tag('div');

        $context = context_course::instance($course->id);
        $o .= $this->section_availability_message($section,
                                                  has_capability('moodle/course:viewhiddensections', $context));

        return $o;
    }

    /**
     * SYNERGY LEARNING - output news section
     * @param object $course
     * @return string
     */
    public function output_news($course) {
        global $CFG, $DB, $OUTPUT, $PAGE;

        $streditsummary = get_string('editsummary');
        $context = context_course::instance($course->id);
        $o = '';

        require_once($CFG->dirroot.'/course/format/topics/locallib.php');
        $subcat = $DB->get_record('course_categories', array('id' => $course->category));
        $o .= $OUTPUT->heading(format_string($subcat->name), 2, 'schoolname');
        $o .= $OUTPUT->heading(format_string($course->fullname), 2, 'coursename');

        if ($PAGE->user_is_editing() && has_capability('moodle/course:update', $context)) {
            $o .= '<p class="clearfix"><a title="' . get_string('editnewssettings', 'format_topics') . '" ' .
                ' href="' . $CFG->wwwroot . '/course/format/topics/newssettings.php' . '?course=' . $course->id . '"><img src="' . $OUTPUT->pix_url('t/edit') . '" ' .
                ' class="iconsmall edit" alt="' . $streditsummary . '" /></a></p>';
        }

        if ($newssettings = $DB->get_record('format_topics_news', array('courseid' => $course->id))) {
            if ($newssettings->displaynews) {
                if($newssettings->usestatictext) {
                    $newstext = $newssettings->statictext;
                } else {
                    $newstext = format_qmultopics_getnews($course);
                }
                $o .= format_qmultopics_getimage($course, $newssettings->alttext);
                $o .= '<div class="static-text"><div class="static-padding">'.$newstext.'</div></div>';
                $o .= '<p class="clearfix" />';
            }
        }

        return $o;
    }
}
