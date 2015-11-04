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
 * Course globalview block
 *
 * @package    block_course_globalview
 * @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->dirroot.'/blocks/course_globalview/locallib.php');

/**
 * Course globalview block
 *
 * @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_course_globalview extends block_base {
    /**
     * If this is passed as mynumber then showallcourses, irrespective of limit by user.
     */
    const SHOW_ALL_COURSES = -2;

    /**
     * Block initialization
     */
    public function init() {
        $this->title   = get_string('pluginname', 'block_course_globalview');
    }

    /**
     * Return contents of course_globalview block
     *
     * @return stdClass contents of block
     */
    public function get_content() {
        global $USER, $CFG, $DB;
        require_once($CFG->dirroot.'/user/profile/lib.php');

        if($this->content !== NULL) {
            return $this->content;
        }

        $config = get_config('block_course_globalview');

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        $content = array();

        $updatemynumber = optional_param('mynumber', -1, PARAM_INT);
        if ($updatemynumber >= 0) {
            block_course_globalview_update_mynumber($updatemynumber);
        }

        profile_load_custom_fields($USER);

        $showallcourses = ($updatemynumber === self::SHOW_ALL_COURSES);
        list($sortedcourses, $sitecourses, $totalcourses) = block_course_globalview_get_sorted_courses($showallcourses);
        $overviews = block_course_globalview_get_globalviews($sitecourses);

        $renderer = $this->page->get_renderer('block_course_globalview');
        if (!empty($config->showwelcomearea)) {
            require_once($CFG->dirroot.'/message/lib.php');
            $msgcount = message_count_unread_messages();
            $this->content->text = $renderer->welcome_area($msgcount);
        }

        // Number of sites to display.
/*        if ($this->page->user_is_editing() && empty($config->forcedefaultmaxcourses)) {
            $this->content->text .= $renderer->editing_bar_head($totalcourses);
        }*/

        // On cherche si l'utilisateur a un role au niveau des catégories (contextlevel = 40)
        $roles_in_categ=$DB->get_records_sql('SELECT RA.id, CC.id as categ_id, CC.parent, CC.name as category_name, R.name as role_name, R.shortname
          FROM {role_assignments} RA, {context} C, {course_categories} CC, {role} R
          WHERE RA.contextid = C.id
          AND C.`contextlevel` = 40 
          AND C.instanceid = CC.id
          AND RA.userid = ?
          AND RA.roleid = R.id
          ORDER BY categ_id', array($USER->id));
	
        if (sizeof($roles_in_categ) > 0) {
          $current_categ = 0;
          $this->content->text .="<br/>Accès direct à vos catégories :";
          foreach($roles_in_categ as $user_role) {
            $categ_name = $user_role->category_name;
            if (!isset($user_role->role_name) || ($user_role->role_name == "") || ($user_role->role_name == NULL)) {
              $user_role->role_name = $user_role->shortname;
            }

            if ($current_categ == $user_role->categ_id) {
              $this->content->text .= ' / '.$user_role->role_name;
            }
            else {
              if ($current_categ != 0) {
                $this->content->text .= ')';
              }
              $this->content->text .= '<br/>- <a title="Afficher tous les cours de la catégorie '.$categ_name.'" href="'.$CFG->wwwroot.'/course/index.php?categoryid='.$user_role->categ_id.'&resort=name&sesskey='.$USER->sesskey.'" ><strong>'.$categ_name.'</strong> <img src="'.$CFG->wwwroot.'/blocks/course_globalview/pix/folder.png" /></a> ('.$user_role->role_name;
              $current_categ = $user_role->categ_id;
            }
          }
          $this->content->text .= ')<br/><br/>';
        }

        if (empty($sortedcourses)) {
            $this->content->text .= get_string('nocourses', 'block_course_globalview');
        } else {
            // For each course, build category cache.
            $this->content->text .= $renderer->course_globalview($sortedcourses, $overviews);
            $this->content->text .= $renderer->hidden_courses($totalcourses - count($sortedcourses));
        }

        return $this->content;
    }

    /**
     * Allow the block to have a configuration page
     *
     * @return boolean
     */
    public function has_config() {
        return false;
    }

    /**
     * Locations where block can be displayed
     *
     * @return array
     */
    public function applicable_formats() {
        return array('my-index' => true);
    }

    /**
     * Sets block header to be hidden or visible
     *
     * @return bool if true then header will be visible.
     */
    public function hide_header() {
        // Hide header if welcome area is show.
        $config = get_config('block_course_globalview');
        return !empty($config->showwelcomearea);
    }
}
