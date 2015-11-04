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
 * course_globalview block rendrer
 *
 * @package    block_course_globalview
 * @copyright  2012 Adam Olley <adam.olley@netspot.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

/**
 * Course_globalview block rendrer
 *
 * @copyright  2012 Adam Olley <adam.olley@netspot.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_course_globalview_renderer extends plugin_renderer_base {

    /**
     * Construct contents of course_globalview block
     *
     * @param array $courses list of courses in sorted order
     * @param array $overview list of course overviews
     * @return string html to be displayed in course_globalview block
     */
    public function course_globalview($courses, $overviews) {
        global $DB, $CFG, $USER;

        $html = '';
        $config = get_config('block_course_globalview');
        $ismovingcourse = false;
        $courseordernumber = 0;
        $maxcourses = count($courses);
        $userediting = false;
        $category = 0;

        foreach ($courses as $key => $course) {
  
            if (($category == 0) || ($category != $course->category)) {
              $category = $course->category;
		          // Récupération du nom de la catégorie et la catégorie mère si besoin
		          $categ = $DB->get_record('course_categories', array('id'=>$category));
		          $categ_name = $categ->name;

		          if ($categ->parent != 0) {
			          $categ_parent = $DB->get_record('course_categories', array('id'=>$categ->parent));
			          $categ_name = $categ_parent->name."/".$categ_name;
		          }

              $html .= '<br/><strong>'.$categ_name.'</strong> <a title="Afficher tous les cours de la catégorie '.$categ->name.'" href="'.$CFG->wwwroot.'/course/index.php?categoryid='.$categ->id.'&resort=name&sesskey='.$USER->sesskey.'" ><img src="'.$CFG->wwwroot.'/blocks/course_globalview/pix/folder.png" /></a><br/>';

            }
  
            // No need to pass title through s() here as it will be done automatically by html_writer.
            $attributes = array('title' => $course->fullname);
            if ($course->id > 0) {
                if (empty($course->visible)) {
                    $attributes['class'] = 'dimmed';
                }
                $courseurl = new moodle_url('/course/view.php', array('id' => $course->id));
                $coursefullname = format_string(get_course_display_name_for_list($course), true, $course->id);
                $link = html_writer::link($courseurl, $coursefullname, $attributes);
                $html .= $link."<br/>";
            } else {
                $html .= $this->output->heading(html_writer::link(
                    new moodle_url('/auth/mnet/jump.php', array('hostid' => $course->hostid, 'wantsurl' => '/course/view.php?id='.$course->remoteid)),
                    format_string($course->shortname, true), $attributes) . ' (' . format_string($course->hostname) . ')', 2, 'title');
            }

            if (!empty($config->showchildren) && ($course->id > 0)) {
                // List children here.
                if ($children = block_course_globalview_get_child_shortnames($course->id)) {
                    $html .= html_writer::tag('span', $children, array('class' => 'coursechildren'));
                }
            }

            // If user is moving courses, then down't show overview.
            if (isset($overviews[$course->id]) && !$ismovingcourse) {
                $html .= $this->activity_display($course->id, $overviews[$course->id]);
            }

            $courseordernumber++;
        }
        // Wrap course list in a div and return.
        return html_writer::tag('div', $html, array('class' => 'course_list'));
    }

    /**
     * Coustuct activities globalview for a course
     *
     * @param int $cid course id
     * @param array $overview overview of activities in course
     * @return string html of activities overview
     */
    protected function activity_display($cid, $overview) {
        $output = html_writer::start_tag('div', array('class' => 'activity_info'));
        foreach (array_keys($overview) as $module) {
            $output .= html_writer::start_tag('div', array('class' => 'activity_globalview'));
            $url = new moodle_url("/mod/$module/index.php", array('id' => $cid));
            $modulename = get_string('modulename', $module);
            $icontext = html_writer::link($url, $this->output->pix_icon('icon', $modulename, 'mod_'.$module, array('class'=>'iconlarge')));
            if (get_string_manager()->string_exists("activityglobalview", $module)) {
                $icontext .= get_string("activityglobalview", $module);
            } else {
                $icontext .= get_string("activityglobalview", 'block_course_globalview', $modulename);
            }

            // Add collapsible region with overview text in it.
            $output .= $this->collapsible_region($overview[$module], '', 'region_'.$cid.'_'.$module, $icontext, '', true);

            $output .= html_writer::end_tag('div');
        }
        $output .= html_writer::end_tag('div');
        return $output;
    }

    /**
     * Constructs header in editing mode
     *
     * @param int $max maximum number of courses
     * @return string html of header bar.
     */
    public function editing_bar_head($max = 0) {
        $output = $this->output->box_start('notice');

        $options = array('0' => get_string('showfiles', 'block_course_globalview'));
        $options[1] = get_string('showfilesyes', 'block_course_globalview');
        $options[2] = get_string('showfilesno', 'block_course_globalview');
        $url = new moodle_url('/my/index.php');
        $select = new single_select($url, 'mynumber', $options, block_course_globalview_get_max_user_courses(), array());
        $select->set_label(get_string('showfilesdisplay', 'block_course_globalview'));
        $output .= $this->output->render($select);

        $output .= $this->output->box_end();
        return $output;
    }

    /**
     * Show hidden courses count
     *
     * @param int $total count of hidden courses
     * @return string html
     */
    public function hidden_courses($total) {
        if ($total <= 0) {
            return;
        }
        $output = $this->output->box_start('notice');
        $plural = $total > 1 ? 'plural' : '';
        $config = get_config('block_course_globalview');
        // Show view all course link to user if forcedefaultmaxcourses is not empty.
        if (!empty($config->forcedefaultmaxcourses)) {
            $output .= get_string('hiddencoursecount'.$plural, 'block_course_globalview', $total);
        } else {
            $a = new stdClass();
            $a->coursecount = $total;
            $a->showalllink = html_writer::link(new moodle_url('/my/index.php', array('mynumber' => block_course_globalview::SHOW_ALL_COURSES)),
                    get_string('showallcourses'));
            $output .= get_string('hiddencoursecountwithshowall'.$plural, 'block_course_globalview', $a);
        }

        $output .= $this->output->box_end();
        return $output;
    }

    /**
     * Creates collapsable region
     *
     * @param string $contents existing contents
     * @param string $classes class names added to the div that is output.
     * @param string $id id added to the div that is output. Must not be blank.
     * @param string $caption text displayed at the top. Clicking on this will cause the region to expand or contract.
     * @param string $userpref the name of the user preference that stores the user's preferred default state.
     *      (May be blank if you do not wish the state to be persisted.
     * @param bool $default Initial collapsed state to use if the user_preference it not set.
     * @return bool if true, return the HTML as a string, rather than printing it.
     */
    protected function collapsible_region($contents, $classes, $id, $caption, $userpref = '', $default = false) {
            $output  = $this->collapsible_region_start($classes, $id, $caption, $userpref, $default);
            $output .= $contents;
            $output .= $this->collapsible_region_end();

            return $output;
        }

    /**
     * Print (or return) the start of a collapsible region, that has a caption that can
     * be clicked to expand or collapse the region. If JavaScript is off, then the region
     * will always be expanded.
     *
     * @param string $classes class names added to the div that is output.
     * @param string $id id added to the div that is output. Must not be blank.
     * @param string $caption text displayed at the top. Clicking on this will cause the region to expand or contract.
     * @param string $userpref the name of the user preference that stores the user's preferred default state.
     *      (May be blank if you do not wish the state to be persisted.
     * @param bool $default Initial collapsed state to use if the user_preference it not set.
     * @return bool if true, return the HTML as a string, rather than printing it.
     */
    protected function collapsible_region_start($classes, $id, $caption, $userpref = '', $default = false) {
        // Work out the initial state.
        if (!empty($userpref) and is_string($userpref)) {
            user_preference_allow_ajax_update($userpref, PARAM_BOOL);
            $collapsed = get_user_preferences($userpref, $default);
        } else {
            $collapsed = $default;
            $userpref = false;
        }

        if ($collapsed) {
            $classes .= ' collapsed';
        }

        $output = '';
        $output .= '<div id="' . $id . '" class="collapsibleregion ' . $classes . '">';
        $output .= '<div id="' . $id . '_sizer">';
        $output .= '<div id="' . $id . '_caption" class="collapsibleregioncaption">';
        $output .= $caption . ' ';
        $output .= '</div><div id="' . $id . '_inner" class="collapsibleregioninner">';

        return $output;
    }

    /**
     * Close a region started with print_collapsible_region_start.
     *
     * @return string return the HTML as a string, rather than printing it.
     */
    protected function collapsible_region_end() {
        $output = '</div></div></div>';
        return $output;
    }

    /**
     * Cretes html for welcome area
     *
     * @param int $msgcount number of messages
     * @return string html string for welcome area.
     */
    public function welcome_area($msgcount) {
        global $USER;
        $output = $this->output->box_start('welcome_area');

        $picture = $this->output->user_picture($USER, array('size' => 75, 'class' => 'welcome_userpicture'));
        $output .= html_writer::tag('div', $picture, array('class' => 'profilepicture'));

        $output .= $this->output->box_start('welcome_message');
        $output .= $this->output->heading(get_string('welcome', 'block_course_globalview', $USER->firstname));

        $plural = 's';
        if ($msgcount > 0) {
            $output .= get_string('youhavemessages', 'block_course_globalview', $msgcount);
            if ($msgcount == 1) {
                $plural = '';
            }
        } else {
            $output .= get_string('youhavenomessages', 'block_course_globalview');
        }
        $output .= html_writer::link(new moodle_url('/message/index.php'), get_string('message'.$plural, 'block_course_globalview'));
        $output .= $this->output->box_end();
        $output .= $this->output->box('', 'flush');
        $output .= $this->output->box_end();

        return $output;
    }
}
