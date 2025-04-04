<?php

/* For licensing terms, see /license.txt */

$cidReset = true;

require_once __DIR__.'/../inc/global.inc.php';

api_protect_admin_script(true);
api_protect_limit_for_session_admin();

// setting the section (for the tabs)
$this_section = SECTION_PLATFORM_ADMIN;
$htmlHeadXtra[] = '<script>
function selectAll(idCheck,numRows,action) {
    for(i = 0; i < numRows; i++) {
        idcheck = document.getElementById(idCheck + "_" + i);
        idcheck.checked = action == "true";
    }
}
</script>';

$tbl_session_category = Database::get_main_table(TABLE_MAIN_SESSION_CATEGORY);
$tbl_session = Database::get_main_table(TABLE_MAIN_SESSION);

$page = isset($_GET['page']) ? (int) $_GET['page'] : null;
$action = isset($_REQUEST['action']) ? Security::remove_XSS($_REQUEST['action']) : null;
$sort = isset($_GET['sort']) && in_array($_GET['sort'], ['title', 'nbr_session', 'date_start', 'date_end'])
    ? Security::remove_XSS($_GET['sort'])
    : 'title';
$idChecked = isset($_REQUEST['idChecked']) ? Security::remove_XSS($_REQUEST['idChecked']) : null;
$order = isset($_REQUEST['order']) ? Security::remove_XSS($_REQUEST['order']) : 'ASC';
$keyword = null;

if ('delete_on_session' === $action || 'delete_off_session' === $action) {
    $delete_session = 'delete_on_session' == $action ? true : false;
    SessionManager::delete_session_category($idChecked, $delete_session);
    Display::addFlash(Display::return_message(get_lang('The selected categories have been deleted')));
    header('Location: '.api_get_self().'?sort='.$sort);
    exit();
}

$frmSearch = new FormValidator('search', 'get', 'session_category_list.php', '', [], FormValidator::LAYOUT_INLINE);
$frmSearch->addText('keyword', get_lang('Search'), false);
$frmSearch->addButtonSearch(get_lang('Search'));
if ($frmSearch->validate()) {
    $keyword = $frmSearch->exportValues()['keyword'];
}

$interbreadcrumb[] = ['url' => 'session_list.php', 'name' => get_lang('Session list')];

if (isset($_GET['search']) && 'advanced' === $_GET['search']) {
    $interbreadcrumb[] = ['url' => 'session_category_list.php', 'name' => get_lang('Sessions categories list')];
    $tool_name = get_lang('Find a training session');
    Display::display_header($tool_name);
    $form = new FormValidator('advanced_search', 'get');
    $form->addElement('header', '', $tool_name);
    $active_group = [];
    $active_group[] = $form->createElement('checkbox', 'active', '', get_lang('active'));
    $active_group[] = $form->createElement('checkbox', 'inactive', '', get_lang('inactive'));
    $form->addGroup($active_group, '', get_lang('activeSession'), null, false);
    $form->addButtonSearch(get_lang('Search users'));
    $defaults['active'] = 1;
    $defaults['inactive'] = 1;
    $form->setDefaults($defaults);
    $form->display();
} else {
    $limit = 20;
    $from = $page * $limit;
    //if user is crfp admin only list its sessions
    $where = null;
    if (!api_is_platform_admin()) {
        $where .= empty($keyword) ? "" : " WHERE title LIKE '%".Database::escape_string(trim($keyword))."%'";
    } else {
        $where .= empty($keyword) ? "" : " WHERE title LIKE '%".Database::escape_string(trim($keyword))."%'";
    }
    if (empty($where)) {
        $where = " WHERE access_url_id = ".api_get_current_access_url_id()." ";
    } else {
        $where .= " AND access_url_id = ".api_get_current_access_url_id()." ";
    }

    $table_access_url_rel_session = Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_SESSION);
    $query = "SELECT sc.*, (
                SELECT count(s.id) FROM $tbl_session s
                INNER JOIN $table_access_url_rel_session us
                ON (s.id = us.session_id)
                WHERE
                    s.session_category_id = sc.id AND
                    access_url_id = ".api_get_current_access_url_id()."
                ) as nbr_session
	 			FROM $tbl_session_category sc
	 			$where
	 			ORDER BY `$sort` $order
	 			LIMIT $from,".($limit + 1);

    $query_rows = "SELECT count(*) as total_rows
                  FROM $tbl_session_category sc $where ";
    $order = ('ASC' == $order) ? 'DESC' : 'ASC';
    $result_rows = Database::query($query_rows);
    $recorset = Database::fetch_array($result_rows);
    $num = $recorset['total_rows'];
    $result = Database::query($query);
    $Sessions = Database::store_result($result);
    $nbr_results = sizeof($Sessions);
    $tool_name = get_lang('Sessions categories list');
    Display::display_header($tool_name);

    $actionsLeft = Display::url(
            Display::getMdiIcon('file-tree-outline', 'ch-tool-icon-gradient', null, 32, get_lang('Add category')),
            api_get_path(WEB_CODE_PATH).'session/session_category_add.php'
        ).Display::url(
            Display::getMdiIcon('google-classroom', 'ch-tool-icon-gradient', null, 32, get_lang('Training sessions list')),
            api_get_path(WEB_CODE_PATH).'session/session_list.php'
        );
    $actionsRight = $frmSearch->returnForm();

    echo Display::toolbarAction('category', [$actionsLeft, $actionsRight]); ?>
    <form method="post" action="<?php echo api_get_self(); ?>?action=delete&sort=<?php echo $sort; ?>"
          onsubmit="if(!confirm('<?php echo get_lang('Please confirm your choice'); ?>')) return false;">
        <?php
        if (0 == count($Sessions) && isset($_POST['keyword'])) {
            echo Display::return_message(get_lang('No search results'), 'warning');
        } else {
            if ($num > $limit) {
                ?>
                <div>
                    <?php
                    if ($page) {
                        ?>
                        <a href="<?php echo api_get_self(); ?>?page=<?php echo $page
                            - 1; ?>&sort=<?php echo $sort; ?>&order=<?php echo Security::remove_XSS(
                            $order
                        ); ?>&keyword=<?php echo $keyword; ?>"><?php echo get_lang(
                                'Previous'
                            ); ?></a>
                        <?php
                    } else {
                        echo get_lang('Previous');
                    } ?>
                    |
                    <?php
                    if ($nbr_results > $limit) {
                        ?>
                        <a href="<?php echo api_get_self(); ?>?page=<?php echo $page
                            + 1; ?>&sort=<?php echo $sort; ?>&order=<?php echo Security::remove_XSS(
                            $order
                        ); ?>&keyword=<?php echo $keyword; ?>"><?php echo get_lang(
                                'Next'
                            ); ?></a>
                        <?php
                    } else {
                        echo get_lang('Next');
                    } ?>
                </div>
                <?php
            } ?>

            <table class="data_table" width="100%">
                <tr>
                    <th>&nbsp;</th>
                    <th><a href="<?php echo api_get_self(); ?>?sort=title&order=<?php echo ('title' == $sort) ? $order
                            : 'ASC'; ?>"><?php echo get_lang('Category name'); ?></a></th>
                    <th><a href="<?php echo api_get_self(); ?>?sort=nbr_session&order=<?php echo ('nbr_session'
                            == $sort) ? $order : 'ASC'; ?>"><?php echo get_lang('Number sessions'); ?></a></th>
                    <th><a href="<?php echo api_get_self(); ?>?sort=date_start&order=<?php echo ('date_start' == $sort)
                            ? $order : 'ASC'; ?>"><?php echo get_lang('Start Date'); ?></a></th>
                    <th><a href="<?php echo api_get_self(); ?>?sort=date_end&order=<?php echo ('date_end' == $sort)
                            ? $order : 'ASC'; ?>"><?php echo get_lang('End Date'); ?></a></th>
                    <th><?php echo get_lang('Detail'); ?></th>
                </tr>

                <?php
                $i = 0;
            $x = 0;
            foreach ($Sessions as $key => $enreg) {
                if ($key == $limit) {
                    break;
                }
                $sql = 'SELECT COUNT(session_category_id)
                        FROM '.$tbl_session.' s
                        INNER JOIN '.$table_access_url_rel_session.'  us
                        ON (s.id = us.session_id)
                        WHERE
                            s.session_category_id = '.intval($enreg['id']).' AND
                            us.access_url_id = '.api_get_current_access_url_id();

                $rs = Database::query($sql);
                [$nb_courses] = Database::fetch_array($rs); ?>
                    <tr class="<?php echo $i ? 'row_odd' : 'row_even'; ?>">
                        <td><input type="checkbox" id="idChecked_<?php echo $x; ?>" name="idChecked[]"
                                   value="<?php echo $enreg['id']; ?>"></td>
                        <td><?php echo api_htmlentities($enreg['title'], ENT_QUOTES); ?></td>
                        <td><?php echo "<a href=\"session_list.php?id_category=".$enreg['id']."\">".$nb_courses
                                ." Session(s) </a>"; ?></td>
                        <td><?php echo api_format_date($enreg['date_start'], DATE_FORMAT_SHORT); ?></td>
                        <td>
                            <?php
                            if (!empty($enreg['date_end']) && '0000-00-00' != $enreg['date_end']) {
                                echo api_format_date($enreg['date_end'], DATE_FORMAT_SHORT);
                            } else {
                                echo '-';
                            } ?>
                        </td>
                        <td>
                            <a href="session_category_edit.php?&id=<?php echo $enreg['id']; ?>">
                                <?php echo Display::getMdiIcon('pencil', 'ch-tool-icon', null, 22, get_lang('Edit')); ?>
                            </a>
                            <a href="<?php echo api_get_self(
                            ); ?>?sort=<?php echo $sort; ?>&action=delete_off_session&idChecked=<?php echo $enreg['id']; ?>"
                               onclick="if(!confirm('<?php echo get_lang(
                                   'Please confirm your choice'
                               ); ?>')) return false;">
                                <?php echo Display::getMdiIcon('delete', 'ch-tool-icon', null, 22, get_lang('Delete')); ?>
                            </a>
                        </td>
                    </tr>
                    <?php
                    $i = $i ? 0 : 1;
                $x++;
            }
            unset($Sessions); ?>
            </table>
            <br/>
            <div>
                <?php
                if ($num > $limit) {
                    if ($page) {
                        ?>
                        <a href="<?php echo api_get_self(); ?>?page=<?php echo $page
                            - 1; ?>&sort=<?php echo $sort; ?>&order=<?php echo Security::remove_XSS(
                            $_REQUEST['order']
                        ); ?>&keyword=<?php echo $_REQUEST['keyword']; ?>">
                            <?php echo get_lang('Previous'); ?></a>
                        <?php
                    } else {
                        echo get_lang('Previous');
                    } ?>
                    |
                    <?php
                    if ($nbr_results > $limit) {
                        ?>
                        <a href="<?php echo api_get_self(); ?>?page=<?php echo $page
                            + 1; ?>&sort=<?php echo $sort; ?>&order=<?php echo Security::remove_XSS(
                            $_REQUEST['order']
                        ); ?>&keyword=<?php echo $_REQUEST['keyword']; ?>">
                            <?php echo get_lang('Next'); ?></a>

                        <?php
                    } else {
                        echo get_lang('Next');
                    }
                } ?>
            </div>
            <div class="row">
                <div class="col-sm-4">
                    <div class="btn-group">
                        <button type="button" class="btn btn--plain" onclick="selectAll('idChecked',<?php echo $x; ?>,'true');">
                            <?php echo get_lang('Select all'); ?>
                        </button>
                        <button type="button" class="btn btn--plain" onclick="selectAll('idChecked',<?php echo $x; ?>,'false');">
                            <?php echo get_lang('UnSelect all'); ?>
                        </button>
                    </div>
                </div>
                <div class="col-sm-6">
                    <select class="selectpicker form-control" name="action">
                        <option value="delete_off_session" selected="selected">
                            <?php echo get_lang('Delete only the selected categories without sessions'); ?>
                        </option>
                        <option value="delete_on_session">
                            <?php echo get_lang('Delete the selected categories to sessions'); ?>
                        </option>
                    </select>
                </div>
                <div class="col-sm-2">
                    <button class="btn btn--success" type="submit" name="title" value="<?php echo get_lang('Validate'); ?>">
                        <?php echo get_lang('Validate'); ?>
                    </button>
                </div>
            </div>
            <?php
        } ?>
        </table>
    </form>
    <?php
}
Display::display_footer();
