<?php

/* For licensing terms, see /license.txt */

/**
 * This tool allows platform admins to create courses by uploading a CSV file
 * Copyright (c) 2005 Bart Mollet <bart.mollet@hogent.be>.
 */

use Chamilo\CoreBundle\Entity\UserAuthSource;

/**
 * Validates imported data.
 *
 * @param array $courses
 *
 * @return array
 */
function validate_courses_data($courses)
{
    $errors = [];
    $coursecodes = [];
    foreach ($courses as $index => $course) {
        $course['line'] = $index + 1;

        // 1. Check whether mandatory fields are set.
        $mandatory_fields = ['Code', 'Title', 'CourseCategory'];
        foreach ($mandatory_fields as $field) {
            if (empty($course[$field])) {
                $course['error'] = get_lang($field.'Mandatory');
                $errors[] = $course;
            }
        }

        // 2. Check current course code.
        if (!empty($course['Code'])) {
            // 2.1 Check whether code has been already used by this CVS-file.
            if (isset($coursecodes[$course['Code']])) {
                $course['error'] = get_lang('A code has been used twice in the file. This is not authorized. Courses codes should be unique.');
                $errors[] = $course;
            } else {
                // 2.2 Check whether course code has been occupied.
                $courseInfo = api_get_course_info($course['Code']);
                if (!empty($courseInfo)) {
                    $course['error'] = get_lang('This code exists');
                    $errors[] = $course;
                }
            }
            $coursecodes[$course['Code']] = 1;
        }

        // 3. Check whether teacher exists.
        $teacherList = getTeacherListInArray($course['Teacher']);

        if (!empty($teacherList)) {
            foreach ($teacherList as $teacher) {
                $teacherInfo = api_get_user_info_from_username($teacher);
                if (empty($teacherInfo)) {
                    $course['error'] = get_lang('Unknown trainer').' ('.$teacher.')';
                    $errors[] = $course;
                }
            }
        }

        if (!empty($course['CourseCategory'])) {
            $categoryInfo = CourseCategory::getCategory($course['CourseCategory']);
            if (empty($categoryInfo)) {
                CourseCategory::add(
                    $course['CourseCategory'],
                    $course['CourseCategoryName'] ?: $course['CourseCategory'],
                    'TRUE'
                );
            }
        } else {
            $course['error'] = get_lang('No course category was provided');
            $errors[] = $course;
        }
    }

    return $errors;
}

/**
 * Get the teacher list.
 *
 * @param array $teachers
 *
 * @return array
 */
function getTeacherListInArray($teachers)
{
    if (!empty($teachers)) {
        return explode('|', $teachers);
    }

    return [];
}

/**
 * Saves imported data.
 *
 * @param array $courses List of courses
 */
function save_courses_data($courses)
{
    $msg = '';
    foreach ($courses as $course) {
        $course_language = $course['Language'];
        $teachers = getTeacherListInArray($course['Teacher']);
        $teacherList = [];
        $creatorId = api_get_user_id();

        if (!empty($teachers)) {
            foreach ($teachers as $teacher) {
                $teacherInfo = api_get_user_info_from_username($teacher);
                if (!empty($teacherInfo)) {
                    $teacherList[] = $teacherInfo;
                }
            }
        }

        $params = [];
        $params['title'] = $course['Title'];
        $params['wanted_code'] = $course['Code'];
        $params['tutor_name'] = null;
        $params['course_category'] = $course['CourseCategory'];
        $params['course_language'] = $course_language;
        $params['user_id'] = $creatorId;
        $addMeAsTeacher = $_POST['add_me_as_teacher'] ?? false;
        $params['add_user_as_teacher'] = $addMeAsTeacher;
        $course = CourseManager::create_course($params);

        if (null !== $course) {
            if (!empty($teacherList)) {
                foreach ($teacherList as $teacher) {
                    CourseManager::subscribeUser(
                        $teacher['user_id'],
                        $course->getId(),
                        COURSEMANAGER
                    );
                }
            }
            $msg .= '<a href="'.api_get_course_url($course->getId()).'/">
                    '.$course->getTitle().'</a> '.get_lang('Created').'<br />';
        }
    }

    if (!empty($msg)) {
        echo Display::return_message($msg, 'normal', false);
    }
}

/**
 * Read the CSV-file.
 *
 * @param string $file Path to the CSV-file
 *
 * @return array All course-information read from the file
 */
function parse_csv_courses_data($file)
{
    return Import::csv_reader($file);
}

$cidReset = true;

require_once __DIR__.'/../inc/global.inc.php';

$this_section = SECTION_PLATFORM_ADMIN;
api_protect_admin_script();

$defined_auth_sources[] = UserAuthSource::PLATFORM;

if (isset($extAuthSource) && is_array($extAuthSource)) {
    $defined_auth_sources = array_merge($defined_auth_sources, array_keys($extAuthSource));
}

$tool_name = get_lang('Import courses list').' CSV';

$interbreadcrumb[] = ['url' => 'index.php', 'name' => get_lang('Administration')];

set_time_limit(0);
Display::display_header($tool_name);

if (isset($_POST['formSent']) && $_POST['formSent']) {
    if (empty($_FILES['import_file']['tmp_name'])) {
        $error_message = get_lang('The file upload has failed.');
        echo Display::return_message($error_message, 'error', false);
    } else {
        $allowed_file_mimetype = ['csv'];

        $ext_import_file = substr($_FILES['import_file']['name'], strrpos($_FILES['import_file']['name'], '.') + 1);

        if (!in_array($ext_import_file, $allowed_file_mimetype)) {
            echo Display::return_message(get_lang('You must import a file corresponding to the selected format'), 'error');
        } else {
            $courses = parse_csv_courses_data($_FILES['import_file']['tmp_name']);

            $errors = validate_courses_data($courses);
            if (0 == count($errors)) {
                save_courses_data($courses);
            }
        }
    }
}

if (isset($errors) && 0 != count($errors)) {
    $error_message = '<ul>';
    foreach ($errors as $index => $error_course) {
        $error_message .= '<li>'.get_lang('Line').' '.$error_course['line'].': <strong>'.$error_course['error'].'</strong>: ';
        $error_message .= get_lang('Course').': '.$error_course['Title'].' ('.$error_course['Code'].')';
        $error_message .= '</li>';
    }
    $error_message .= '</ul>';
    echo Display::return_message($error_message, 'error', false);
}

$form = new FormValidator(
    'import',
    'post',
    api_get_self(),
    null,
    ['enctype' => 'multipart/form-data']
);
$form->addHeader($tool_name);
$form->addElement('file', 'import_file', get_lang('CSV file import location'));
$form->addElement('checkbox', 'add_me_as_teacher', null, get_lang('Add me as teacher in the imported courses.'));
$form->addButtonImport(get_lang('Import'), 'save');
$form->addElement('hidden', 'formSent', 1);
$form->display();

$content = '
<div style="clear: both;"></div>
<p>'.get_lang('The CSV file must look like this').' ('.get_lang('Fields in <b>bold</b> are mandatory.').') :</p>
<blockquote>
<pre>
<b>Code</b>;<b>Title</b>;<b>CourseCategory</b>;<b>CourseCategoryName</b>;Teacher;Language
BIO0015;Biology;BIO;Science;teacher1;english
BIO0016;Maths;MATH;Engineerng;teacher2|teacher3;english
BIO0017;Language;LANG;;;english
</pre>
</blockquote>';
echo Display::prose($content);

Display::display_footer();
