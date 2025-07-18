<?php

/* For licensing terms, see /license.txt */

use Chamilo\CoreBundle\Entity\SequenceResource;
use Chamilo\CoreBundle\Entity\Session;
use Chamilo\CoreBundle\Entity\SessionRelCourse;
use Chamilo\CoreBundle\Entity\SessionRelCourseRelUser;
use Chamilo\CoreBundle\Enums\ActionIcon;
use Chamilo\CoreBundle\Enums\ObjectIcon;
use Chamilo\CoreBundle\Framework\Container;
use Chamilo\CoreBundle\Repository\SequenceResourceRepository;

$cidReset = true;
require_once __DIR__.'/../inc/global.inc.php';

// setting the section (for the tabs)
$this_section = SECTION_PLATFORM_ADMIN;

$sessionId = isset($_GET['id_session']) ? (int) $_GET['id_session'] : null;

if (empty($sessionId)) {
    api_not_allowed(true);
}

$session = api_get_session_entity($sessionId);
SessionManager::protectSession($session);

$codePath = api_get_path(WEB_CODE_PATH);
$tool_name = get_lang('Session overview');
$interbreadcrumb[] = [
    'url' => 'session_list.php',
    'name' => get_lang('Session list'),
];

$orig_param = '&origin=resume_session';

$tbl_session = Database::get_main_table(TABLE_MAIN_SESSION);
$tbl_session_rel_class = Database::get_main_table(TABLE_MAIN_SESSION_CLASS);
$em = Database::getManager();
$sessionCategory = $session->getCategory();

$action = $_GET['action'] ?? null;
$url_id = api_get_current_access_url_id();

switch ($action) {
    case 'move_up':
        SessionManager::moveUp($sessionId, $_GET['course_id']);
        header('Location: resume_session.php?id_session='.$sessionId);
        exit;
        break;
    case 'move_down':
        SessionManager::moveDown($sessionId, $_GET['course_id']);
        header('Location: resume_session.php?id_session='.$sessionId);
        exit;
        break;
    case 'add_user_to_url':
        $user_id = $_REQUEST['user_id'];
        $result = UrlManager::add_user_to_url($user_id, $url_id);
        $user_info = api_get_user_info($user_id);
        if ($result) {
            Display::addFlash(
                Display::return_message(
                    get_lang('The user has been added').' '.api_get_person_name($user_info['firstname'], $user_info['lastname']),
                    'confirm'
                )
            );
        }
        break;
    case 'delete':
        // Delete course from session.
        $idChecked = isset($_GET['idChecked']) ? $_GET['idChecked'] : null;
        if (is_array($idChecked)) {
            $usersToDelete = [];
            foreach ($idChecked as $courseCode) {
                // forcing the escape_string
                $courseInfo = api_get_course_info($courseCode);
                SessionManager::unsubscribe_course_from_session(
                    $sessionId,
                    $courseInfo['real_id']
                );
            }
        }

        if (!empty($_GET['class'])) {
            $class = (int) $_GET['class'];
            $result = Database::query(
                "DELETE FROM $tbl_session_rel_class
                       WHERE session_id = $sessionId AND class_id = $class"
            );
            $nbr_affected_rows = Database::affected_rows($result);
            Database::query(
                "UPDATE $tbl_session
                        SET nbr_classes = nbr_classes - $nbr_affected_rows
                        WHERE id = $sessionId");
        }

        if (!empty($_GET['user'])) {
            SessionManager::unsubscribe_user_from_session(
                $sessionId,
                $_GET['user']
            );
        }

        Display::addFlash(Display::return_message(get_lang('Update successful')));
        header('Location: resume_session.php?id_session='.$sessionId);
        exit;

        break;
}

$sessionHeader = Display::page_header(
    Display::getMdiIcon(ObjectIcon::SESSION, 'ch-tool-icon-gradient', null, 32, get_lang('Session')).' '.$session->getTitle(),
    null,
    'h3'
);

$url = Display::url(
    Display::getMdiIcon(ActionIcon::EDIT, 'ch-tool-icon-gradient', null, 32, get_lang('Edit')),
    "session_edit.php?page=resume_session.php&id=$sessionId"
);

$sessionTitle = Display::page_subheader(get_lang('General properties').$url);

$sessionField = new ExtraField('session');
$extraFieldData = $sessionField->getDataAndFormattedValues($sessionId);

$urlList = [];
$isMultipleUrl = api_is_multiple_url_enabled();
if ($isMultipleUrl) {
    $urlList = $session->getUrls();
}

$url = Display::url(
    Display::getMdiIcon(ActionIcon::ADD, 'ch-tool-icon-gradient', null, 32, get_lang('Add')),
    "add_courses_to_session.php?page=resume_session.php&id_session=$sessionId"
);
$courseListToShow = Display::page_subheader(get_lang('Course list').$url);

$courseListToShow .= '<table id="session-list-course" class="table table-hover data_table">
<tr>
  <th width="35%">'.get_lang('Course title').'</th>
  <th width="30%">'.get_lang('Course coach').'</th>
  <th width="10%">'.get_lang('Users number').'</th>
  <th width="25%">'.get_lang('Detail').'</th>
</tr>';

if (0 === $session->getNbrCourses()) {
    $courseListToShow .= '<tr>
			<td colspan="4">'.get_lang('No course for this session').'</td>
		</tr>';
} else {
    $count = 0;
    $courseItem = '';
    $courses = $session->getCourses();

    $allowSkills = ('true' === api_get_setting('skill.allow_skill_rel_items'));

    /** @var SessionRelCourse $sessionRelCourse */
    foreach ($courses as $sessionRelCourse) {
        $course = $sessionRelCourse->getCourse();
        $courseId = $course->getId();
        $courseCode = $course->getCode();
        // Select the number of users
        $numberOfUsers = SessionManager::getCountUsersInCourseSession($course, $session);

        // Get coaches of the courses in session
        $namesOfCoaches = [];
        $coachSubscriptions = $session->getSessionRelCourseRelUsersByStatus($course, Session::COURSE_COACH);

        if ($coachSubscriptions) {
            /** @var SessionRelCourseRelUser $subscription */
            foreach ($coachSubscriptions as $subscription) {
                $namesOfCoaches[] = UserManager::formatUserFullName($subscription->getUser(), true);
            }
        }

        $orderButtons = '';
        if (SessionManager::orderCourseIsEnabled()) {
            $orderButtons = Display::url(
                Display::getMdiIcon(
                    ActionIcon::UP,
                    !$count ? 'ch-tool-icon-disabled' : 'ch-tool-icon',
                    null,
                    ICON_SIZE_SMALL,
                    get_lang('Move up')
                ),
                !$count
                    ? '#'
                    : api_get_self().'?id_session='.$sessionId.'&course_id='.$courseId.'&action=move_up'
            );

            $orderButtons .= Display::url(
                Display::getMdiIcon(
                    ActionIcon::DOWN,
                    $count + 1 == count($courses) ? 'ch-tool-icon-disabled' : 'ch-tool-icon',
                    null,
                    ICON_SIZE_SMALL,
                    get_lang('Move down')
                ),
                $count + 1 == count($courses)
                    ? '#'
                    : api_get_self().'?id_session='.$sessionId.'&course_id='.$courseId.'&action=move_down'
            );
        }

        $courseUrl = api_get_course_url($courseId, $sessionId);

        // hide_course_breadcrumb the parameter has been added to hide the name
        // of the course, that appeared in the default $interbreadcrumb
        $courseItem .= '<tr>
			<td class="title">'
            .Display::url(
                $course->getTitle().' ('.$course->getVisualCode().')',
                $courseUrl
            )
            .'</td>';
        $courseItem .= '<td>'.($namesOfCoaches ? implode('<br>', $namesOfCoaches) : get_lang('none')).'</td>';
        $courseItem .= '<td>'.$numberOfUsers.'</td>';
        $courseItem .= '<td>';
        $courseItem .= Display::url(Display::getMdiIcon(ObjectIcon::HOME, 'ch-tool-icon', null, 22, get_lang('Course')), $courseUrl);

        if ($allowSkills) {
            $courseItem .= Display::url(
                Display::getMdiIcon('shield-star', 'ch-tool-icon', null, 22, get_lang('Skills')),
                $codePath.'skills/skill_rel_course.php?session_id='.$sessionId.'&course_id='.$courseId
            );
        }
        $courseItem .= $orderButtons;

        $courseItem .= Display::url(
            Display::getMdiIcon(ActionIcon::ADD_USER, 'ch-tool-icon', null, 22, get_lang('Add a user')),
            $codePath."session/add_users_to_session_course.php?id_session=$sessionId&course_id=".$courseId
        );
        $courseItem .= Display::url(
            Display::getMdiIcon('account-multiple', 'ch-tool-icon', null, 22, get_lang('Users')),
            $codePath."session/session_course_user_list.php?id_session=$sessionId&course_code=".$courseCode
        );
        $courseItem .= Display::url(
            Display::getMdiIcon('archive-arrow-up', 'ch-tool-icon', null, 22, get_lang('Import users list')),
            $codePath."user/user_import.php?action=import&cid={$courseId}&sid=$sessionId"
        );
        $courseItem .= Display::url(
            Display::getMdiIcon('archive-arrow-down', 'ch-tool-icon', null, 22, get_lang('Export users of a course')),
            $codePath."user/user_export.php?file_type=csv&course_session=$courseCode:$sessionId&addcsvheader=1"
        );
        $courseItem .= Display::url(
            Display::getMdiIcon('chart-box', 'ch-tool-icon', null, 22, get_lang('Reporting')),
            $codePath."tracking/courseLog.php?sid=$sessionId&cid={$courseId}$orig_param&hide_course_breadcrumb=1"
        );
        $courseItem .= Display::url(
            Display::getMdiIcon(ObjectIcon::TEACHER, 'ch-tool-icon', null, 22, get_lang('Edit coach')),
            $codePath."session/session_course_edit.php?id_session=$sessionId&page=resume_session.php&course_code={$courseCode}$orig_param"
        );

        // @todo
        /*$courseItem .= Display::url(
            Display::getMdiIcon(ActionIcon::UPLOAD, 'ch-tool-icon', null, ICON_SIZE_SMALL, get_lang('File upload')),
            '#',
            [
                'class' => 'session-upload-file-btn',
                'data-session' => $sessionId,
                'data-course' => $courseId,
            ]
        );*/
        $courseItem .= Display::url(
            Display::getMdiIcon(ActionIcon::DELETE, 'ch-tool-icon', null, 22, get_lang('Delete')),
            api_get_self()."?id_session=$sessionId&action=delete&idChecked[]={$courseCode}",
            [
                'onclick' => "javascript:if(!confirm('".get_lang('Please confirm your choice')."')) return false;",
            ]
        );

        $courseItem .= '</td></tr>';
        $count++;
    }
    $courseListToShow .= $courseItem;
}
$courseListToShow .= '</table><br />';

$url = '&nbsp;'.Display::url(
        Display::getMdiIcon(ActionIcon::ADD, 'ch-tool-icon-gradient', null, 32, get_lang('Add')),
    $codePath."session/add_users_to_session.php?page=resume_session.php&id_session=$sessionId"
);
$url .= Display::url(
    Display::getMdiIcon('archive-arrow-up', 'ch-tool-icon-gradient', null, 32, get_lang('Import users')),
    $codePath."session/session_user_import.php?id_session=$sessionId"
);
$url .= Display::url(
    Display::getMdiIcon('archive-arrow-down', 'ch-tool-icon-gradient', null, 32, get_lang('Export users list')),
    $codePath."user/user_export.php?file_type=csv&session=$sessionId&addcsvheader=1"
);

$userListToShow = Display::page_subheader(get_lang('User list').$url);
$sessionRelUsers = Container::getSessionRepository()
    ->getUsersByAccessUrl($session, api_get_url_entity(), [Session::STUDENT, Session::DRH]);

if (!empty($sessionRelUsers)) {
    $table = new HTML_Table(['class' => 'table table-bordered', 'id' => 'session-user-list']);
    $table->setHeaderContents(0, 0, '#');
    $table->setHeaderContents(0, 1, get_lang('User'));
    $table->setHeaderContents(0, 2, get_lang('Status'));
    $table->setHeaderContents(0, 3, get_lang('Registration date'));
    $table->setHeaderContents(0, 4, get_lang('Detail'));
    $row = 1;
    foreach ($sessionRelUsers as $sessionRelUser) {
        $user = $sessionRelUser->getUser();
        $userId = $user->getId();

        $userLink = '<a href="'.$codePath.'admin/user_information.php?user_id='.$userId.'">'.
            api_htmlentities(UserManager::formatUserFullName($user, true)).'</a>';

        $reportingLink = Display::url(
            Display::getMdiIcon('chart-box', 'ch-tool-icon', null, 22, get_lang('Reporting')),
            $codePath.'my_space/myStudents.php?student='.$userId.''.$orig_param.'&id_session='
            .$sessionId
        );

        $courseUserLink = Display::url(
            Display::getMdiIcon('book-open-page-variant', 'ch-tool-icon', null, 22, get_lang('Block user from courses in this session')),
            $codePath.'session/session_course_user.php?id_user='.$userId.'&id_session='
            .$sessionId
        );

        $removeLink = Display::url(
            Display::getMdiIcon(ActionIcon::DELETE, 'ch-tool-icon', null, 22, get_lang('Delete')),
            api_get_self().'?id_session='.$sessionId.'&action=delete&user='.$userId,
            ['onclick' => "javascript:if(!confirm('".get_lang('Please confirm your choice')."')) return false;"]
        );

        $addUserToUrlLink = '';
        $editUrl = null;
        /*
        if (isset($sessionInfo['duration']) && !empty($sessionInfo['duration'])) {
            $editUrl = $codePath . 'session/session_user_edit.php?session_id=' . $sessionId . '&user_id=' . $userId;
            $editUrl = Display::url(
                Display::getMdiIcon(ObjectIcon::AGENDA, 'ch-tool-icon', null, ICON_SIZE_SMALL, get_lang('Edit session duration')),
                $editUrl
            );
        }*/
        $link = $reportingLink.$courseUserLink.$removeLink.$addUserToUrlLink.$editUrl;
        switch ($sessionRelUser->getRelationType()) {
            case Session::DRH:
                $status = get_lang('Human Resources Manager');
                $link = Display::url(
                    Display::getMdiIcon(ActionIcon::EDIT, 'ch-tool-icon', null, 22, get_lang('Edit')),
                    $codePath.'admin/dashboard_add_sessions_to_user.php?user='.$userId
                );
                break;
            default:
                $status = get_lang('Learner');
        }

        $registered = Display::dateToStringAgoAndLongDate($sessionRelUser->getRegisteredAt());

        $table->setCellContents($row, 0, $row);
        $table->setCellContents($row, 1, $userLink);
        $table->setCellContents($row, 2, $status);
        $table->setCellContents($row, 3, $registered);
        $table->setCellContents($row, 4, $link);
        $row++;
    }
    $userListToShow .= $table->toHtml();
}

/** @var SequenceResourceRepository $repo */
$repo = $em->getRepository(SequenceResource::class);
$requirementAndDependencies = $repo->getRequirementAndDependencies(
    $sessionId,
    SequenceResource::SESSION_TYPE
);

$requirements = '';
if (!empty($requirementAndDependencies['requirements'])) {
    $requirements = Display::page_subheader(get_lang('Requirements'));
    $requirements .= implode(' + ', array_column($requirementAndDependencies['requirements'], 'admin_link'));
}
$dependencies = '';
if (!empty($requirementAndDependencies['dependencies'])) {
    $dependencies = Display::page_subheader(get_lang('Items that depend on the reference'));
    $dependencies .= implode(', ', array_column($requirementAndDependencies['dependencies'], 'admin_link'));
}

$programmedAnnouncement = new ScheduledAnnouncement();
$programmedAnnouncement = $programmedAnnouncement->allowed();

$tpl = new Template($tool_name);
$tpl->assign('session_header', $sessionHeader);
$tpl->assign('title', $sessionTitle);
$tpl->assign('session', $session);
$tpl->assign('programmed_announcement', $programmedAnnouncement);
$tpl->assign('session_dates', SessionManager::parseSessionDates($session, true));
$tpl->assign('session_visibility', SessionManager::getSessionVisibility($session));
$tpl->assign('url_list', $urlList);
$tpl->assign('extra_fields', $extraFieldData);
$tpl->assign('course_list', $courseListToShow);
$tpl->assign('user_list', $userListToShow);
$tpl->assign('dependencies', $dependencies);
$tpl->assign('requirements', $requirements);

$layout = $tpl->get_template('session/resume_session.tpl');
$tpl->display($layout);
