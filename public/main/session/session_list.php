<?php

/* For licensing terms, see /license.txt */

ini_set('memory_limit', '2024M');

/**
 * List sessions in an efficient and usable way.
 */
$cidReset = true;
require_once __DIR__.'/../inc/global.inc.php';
$this_section = SECTION_PLATFORM_ADMIN;

SessionManager::protectSession(null, false);

// Add the JS needed to use the jqgrid
$htmlHeadXtra[] = api_get_jqgrid_js();

$action = $_REQUEST['action'] ?? null;
$idChecked = $_REQUEST['idChecked'] ?? null;
$idMultiple = $_REQUEST['id'] ?? null;
$listType = isset($_REQUEST['list_type']) ? Security::remove_XSS($_REQUEST['list_type']) : SessionManager::getDefaultSessionTab();
$copySessionContent = isset($_REQUEST['copy_session_content']);
$addSessionContent = 'true' === api_get_setting('session.duplicate_specific_session_content_on_session_copy');

if (!$addSessionContent) {
    $copySessionContent = false;
}

switch ($action) {
    case 'delete_multiple':
        $sessionList = explode(',', $idMultiple);
        foreach ($sessionList as $id) {
            $sessionInfo = api_get_session_info($id);
            if ($sessionInfo) {
                $response = SessionManager::delete($id);
            }
        }
        echo 1;
        exit;
    case 'delete':
        $sessionInfo = api_get_session_info($idChecked);
        if ($sessionInfo) {
            $response = SessionManager::delete($idChecked);
            if ($response) {
                Display::addFlash(
                    Display::return_message(get_lang('Deleted').': '.Security::remove_XSS($sessionInfo['title']))
                );
            }
        }
        $url = 'session_list.php';
        if ('custom' !== $listType) {
            $url = 'session_list.php?list_type='.$listType;
        }
        header('Location: '.$url);
        exit();
    case 'copy':
        $result = SessionManager::copy(
          (int) $idChecked,
          true,
          true,
          false,
          false,
          $copySessionContent
        );
        if ($result) {
            Display::addFlash(Display::return_message(get_lang('ItemCopied')));
        } else {
            Display::addFlash(Display::return_message(get_lang('ThereWasAnError'), 'error'));
        }
        $url = 'session_list.php';
        if ('custom' !== $listType) {
            $url = 'session_list.php?list_type='.$listType;
        }
        header('Location: '.$url);
        exit;
    case 'copy_multiple':
        $sessionList = explode(',', $idMultiple);
        foreach ($sessionList as $id) {
            $sessionIdCopied = SessionManager::copy((int) $id);
            if ($sessionIdCopied) {
                $sessionInfo = api_get_session_info($sessionIdCopied);
                Display::addFlash(Display::return_message(get_lang('ItemCopied').' - '.$sessionInfo['name']));
            } else {
                Display::addFlash(Display::return_message(get_lang('ThereWasAnError'), 'error'));
            }
        }
        $url = 'session_list.php';
        if ('custom' !== $listType) {
            $url = 'session_list.php?list_type='.$listType;
        }
        header('Location: '.$url);
        exit;
    case 'export_csv':
        $selectedSessions = explode(',', $idMultiple);
        SessionManager::exportSessionsAsCSV($selectedSessions);
        break;

    case 'export_multiple':
        $sessionList = explode(',', $idMultiple);
        SessionManager::exportSessionsAsZip($sessionList);
        break;
}

$tool_name = get_lang('Session list');
Display::display_header($tool_name);

$courseId = $_GET['course_id'] ?? null;

$sessionFilter = new FormValidator(
    'course_filter',
    'get',
    '',
    '',
    [],
    FormValidator::LAYOUT_INLINE
);
$courseSelect = $sessionFilter->addSelectAjax(
    'course_name',
    null,
    [],
    [
        'id' => 'course_name',
        'placeholder' => get_lang('Search courses'),
        'url' => api_get_path(WEB_AJAX_PATH).'course.ajax.php?a=search_course',
    ]
);

if (!empty($courseId)) {
    $courseInfo = api_get_course_info_by_id($courseId);
    $courseSelect->addOption($courseInfo['title'], $courseInfo['code'], ['selected' => 'selected']);
}

$url = api_get_self();
$actions = '
<script>
$(function() {
    $("#course_name").on("change", function() {
       var courseId = $(this).val();
       if (!courseId) {
        return;
       }
       window.location = "'.$url.'?course_id="+courseId;
    });
});
</script>';

switch ($listType) {
    case 'replication':
        $url = api_get_path(WEB_AJAX_PATH).'model.ajax.php?a=get_sessions&list_type=replication';
        break;
    default:
        if (!empty($courseId)) {
            $url = api_get_path(WEB_AJAX_PATH).'model.ajax.php?a=get_sessions&course_id='.$courseId;
        } else {
            $url = api_get_path(WEB_AJAX_PATH).'model.ajax.php?a=get_sessions';
        }
        break;
}

if (isset($_REQUEST['keyword'])) {
    //Begin with see the searchOper param
    $filter = new stdClass();
    $filter->groupOp = 'OR';
    $rule = new stdClass();
    $rule->field = 'category_name';
    $rule->op = 'in';
    $rule->data = Security::remove_XSS($_REQUEST['keyword']);
    $filter->rules[] = $rule;
    $filter->groupOp = 'OR';

    $filter = json_encode($filter);
    $url = api_get_path(WEB_AJAX_PATH).'model.ajax.php?'
        .http_build_query([
            'a' => 'get_sessions',
            '_force_search' => 'true',
            'rows' => 20,
            'page' => 1,
            'sidx' => '',
            'sord' => 'asc',
            'filters' => $filter,
            'searchField' => 's.title',
            'searchString' => Security::remove_XSS($_REQUEST['keyword']),
            'searchOper' => 'in',
        ]);
}

if (isset($_REQUEST['id_category'])) {
    $sessionCategory = SessionManager::get_session_category($_REQUEST['id_category']);
    if (!empty($sessionCategory)) {
        //Begin with see the searchOper param
        $url = api_get_path(WEB_AJAX_PATH).'model.ajax.php?'
            .http_build_query([
                'a' => 'get_sessions',
                '_force_search' => 'true',
                'rows' => 20,
                'page' => 1,
                'sidx' => '',
                'sord' => 'asc',
                'filters' => '',
                'searchField' => 'sc.title',
                'searchString' => Security::remove_XSS($sessionCategory['title']),
                'searchOper' => 'in',
            ]);
    }
}

$url .= '&list_type='.$listType;
$result = SessionManager::getGridColumns($listType);
$columns = $result['columns'];
$column_model = $result['column_model'];
$extra_params['autowidth'] = 'true';
$extra_params['height'] = 'auto';

switch ($listType) {
    case 'custom':
        $extra_params['sortname'] = 'display_end_date';
        $extra_params['sortorder'] = 'desc';
        break;
}

if (!isset($_GET['keyword'])) {
    $extra_params['postData'] = [
        'filters' => [
            'groupOp' => 'AND',
            'rules' => $result['rules'],
        ],
    ];
}

$hideSearch = ('true' === api_get_setting('session.hide_search_form_in_session_list'));
$copySessionContentLink = '';
if ($addSessionContent) {
    $copySessionContentLink = ' <a onclick="javascript:if(!confirm('."\'".addslashes(api_htmlentities(get_lang("ConfirmYourChoice"), ENT_QUOTES))."\'".')) return false;" href="session_list.php?copy_session_content=1&list_type='.$listType.'&action=copy&idChecked=\'+options.rowId+\'">'.
        Display::return_icon('copy.png', get_lang('CopyWithSessionContent')).'</a>';
}

//With this function we can add actions to the jgrid (edit, delete, etc)
$action_links = 'function action_formatter(cellvalue, options, rowObject) {
     return \'<a href="session_edit.php?page=resume_session.php&id=\'+options.rowId+\'">'.Display::getMdiIcon('pencil', 'ch-tool-icon', null, 22, get_lang('Edit')).'</a>'.
    '&nbsp;<a href="add_users_to_session.php?page=session_list.php&id_session=\'+options.rowId+\'">'.Display::getMdiIcon('account-multiple-plus', 'ch-tool-icon', null, 22, get_lang('Subscribe users to this session')).'</a>'.
    '&nbsp;<a href="add_courses_to_session.php?page=session_list.php&id_session=\'+options.rowId+\'">'.Display::getMdiIcon('book-open-page-variant', 'ch-tool-icon', null, 22, get_lang('Add courses to this session')).'</a>'.
    '&nbsp;<a onclick="javascript:if(!confirm('."\'".addslashes(api_htmlentities(get_lang("Please confirm your choice"), ENT_QUOTES))."\'".')) return false;"  href="session_list.php?action=copy&idChecked=\'+options.rowId+\'">'.Display::getMdiIcon('text-box-plus', 'ch-tool-icon', null, 22, get_lang('Copy')).'</a>'.
    $copySessionContentLink.
    '<button type="button" title="'.get_lang('Delete').'" onclick="if(confirm('."\'".addslashes(api_htmlentities(get_lang("Please confirm your choice"), ENT_QUOTES))."\'".')) window.location = '."\'session_list.php?action=delete&idChecked=\' + ".'\' + options.rowId +\';">'.Display::getMdiIcon('delete', 'ch-tool-icon', null, 22, get_lang('Delete')).'</button>'.
    '\';
}';

$urlAjaxExtraField = api_get_path(WEB_AJAX_PATH).'extra_field.ajax.php?1=1';
$orderUrl = api_get_path(WEB_AJAX_PATH).'session.ajax.php?a=order';
$deleteUrl = api_get_self().'?list_type='.$listType.'&action=delete_multiple';
$copyUrl = api_get_self().'?list_type='.$listType.'&action=copy_multiple';
$exportUrl = api_get_self().'?list_type='.$listType.'&action=export_multiple';
$exportCsvUrl = api_get_self().'?list_type='.$listType.'&action=export_csv';
$extra_params['multiselect'] = true;

?>
    <script>
        function setSearchSelect(columnName) {
            $("#sessions").jqGrid('setColProp', columnName, {});
        }
        var added_cols = [];
        var original_cols = [];

        function clean_cols(grid, added_cols) {
            // Cleaning
            for (key in added_cols) {
                grid.hideCol(key);
            }
            grid.showCol('title');
            grid.showCol('display_start_date');
            grid.showCol('display_end_date');
            grid.showCol('course_title');
        }

        function show_cols(grid, added_cols) {
            grid.showCol('title').trigger('reloadGrid');
            for (key in added_cols) {
                grid.showCol(key);
            }
        }

        var second_filters = [];

        $(function() {
            date_pick_today = function(elem) {
                $(elem).datetimepicker({dateFormat: "yy-mm-dd"});
                $(elem).datetimepicker('setDate', (new Date()));
            }
            date_pick_one_month = function(elem) {
                $(elem).datetimepicker({dateFormat: "yy-mm-dd"});
                next_month = Date.today().next().month();
                $(elem).datetimepicker('setDate', next_month);
            }

            //Great hack
            register_second_select = function(elem) {
                second_filters[$(elem).val()] = $(elem);
            }

            fill_second_select = function(elem) {
                $(elem).on("change", function() {
                    composed_id = $(this).val();
                    field_id = composed_id.split("#")[0];
                    id = composed_id.split("#")[1];

                    $.ajax({
                        url: "<?php echo $urlAjaxExtraField; ?>&a=get_second_select_options",
                        dataType: "json",
                        data: "type=session&field_id="+field_id+"&option_value_id="+id,
                        success: function(data) {
                            my_select = second_filters[field_id];
                            my_select.empty();
                            $.each(data, function(index, value) {
                                my_select.append($("<option/>", {
                                    value: index,
                                    text: value
                                }));
                            });
                        }
                    });
                });
            }

            <?php
            echo Display::grid_js(
                'sessions',
                $url,
                $columns,
                $column_model,
                $extra_params,
                [],
                $action_links,
                true
            );
            ?>

            setSearchSelect("status");
            var grid = $("#sessions");

            var prmSearch = {
                    multipleSearch : true,
                    overlay : false,
                    width: 'auto',
                    caption: '<?php echo addslashes(get_lang('Search')); ?>',
                    formclass:'data_table',
                    onSearch : function() {
                        var postdata = grid.jqGrid('getGridParam', 'postData');

                        if (postdata && postdata.filters) {
                            filters = jQuery.parseJSON(postdata.filters);
                            clean_cols(grid, added_cols);
                            added_cols = [];
                            $.each(filters, function(key, value) {
                                if (key == 'rules') {
                                    $.each(value, function(subkey, subvalue) {
                                        if (subvalue.data == undefined) {
                                        }
                                        added_cols[subvalue.field] = subvalue.field;
                                    });
                                }
                            });
                            show_cols(grid, added_cols);
                        }
                    },
                    onReset: function() {
                        clean_cols(grid, added_cols);
                    }
                };

            original_cols = grid.jqGrid('getGridParam', 'colModel');

            options = {
                update: function (e, ui) {
                    var rowNum = jQuery("#sessions").getGridParam('rowNum');
                    var page = jQuery("#sessions").getGridParam('page');
                    page = page - 1;
                    var start = rowNum * page;
                    var list = jQuery('#sessions').jqGrid('getRowData');
                    var orderList = [];
                    $(list).each(function(index, e) {
                        index = index + start;
                        orderList.push({'order':index, 'id': e.id});
                    });
                    orderList = JSON.stringify(orderList);
                    $.get("<?php echo $orderUrl; ?>", "order="+orderList, function (result) {
                    });
                }
            };

            // Sortable rows
            grid.jqGrid('sortableRows', options);

            grid.jqGrid('navGrid','#sessions_pager',
                {edit:false,add:false,del:true},
                {height:280,reloadAfterSubmit:false}, // edit options
                {height:280,reloadAfterSubmit:false}, // add options
                {reloadAfterSubmit:true, url: '<?php echo $deleteUrl; ?>' }, // del options
                prmSearch
            ).navButtonAdd('#sessions_pager',{
                caption:"<?php echo addslashes(Display::return_icon('copy.png', get_lang('Copy'))); ?>",
                buttonicon:"ui-icon ui-icon-plus",
                onClickButton: function(a) {
                    var list = $("#sessions").jqGrid('getGridParam', 'selarrrow');
                    if (list.length) {
                        window.location.replace('<?php echo $copyUrl; ?>&id='+list.join(','));
                    } else {
                        alert("<?php echo addslashes(get_lang('Select an option')); ?>");
                    }
                }
            }).navButtonAdd('#sessions_pager',{
                caption:"<?php echo addslashes(Display::return_icon('save_pack.png', get_lang('Export courses reports'))); ?>",
                buttonicon:"ui-icon ui-icon-plus",
                onClickButton: function(a) {
                    var list = $("#sessions").jqGrid('getGridParam', 'selarrrow');
                    if (list.length) {
                        window.location.replace('<?php echo $exportUrl; ?>&id='+list.join(','));
                    } else {
                        alert("<?php echo addslashes(get_lang('Select an option')); ?>");
                    }
                },
                position:"last"
            }).navButtonAdd('#sessions_pager',{
                caption:"<?php echo addslashes(Display::return_icon('export_csv.png', get_lang('Export courses reports complete'))); ?>",
                buttonicon:"ui-icon ui-icon-plus",
                onClickButton: function(a) {
                    var list = $("#sessions").jqGrid('getGridParam', 'selarrrow');
                    if (list.length) {
                        window.location.replace('<?php echo $exportCsvUrl; ?>&id='+list.join(','));
                    } else {
                        alert("<?php echo addslashes(get_lang('Select an option')); ?>");
                    }
                },
                position:"last"
            });

            <?php
            // Create the searching dialog.
            if (true !== $hideSearch) {
                echo 'grid.searchGrid(prmSearch);';
            }
            ?>

            // Fixes search table.
            var searchDialogAll = $("#fbox_"+grid[0].id);
            searchDialogAll.addClass("table");
            var searchDialog = $("#searchmodfbox_"+grid[0].id);
            searchDialog.addClass("ui-jqgrid ui-widget ui-widget-content ui-corner-all");
            searchDialog.css({
                position: "absolute",
                "z-index": "100",
                "float": "left",
                "top": "55%",
                "left": "25%",
                "padding": "5px",
                "border": "1px solid #CCC"
            })
            var gbox = $("#gbox_"+grid[0].id);
            gbox.before(searchDialog);
            gbox.css({clear:"left"});

            // Select first elements by default
            $('.input-elm').each(function(){
                $(this).find('option:first').attr('selected', 'selected');
            });

            $('.delete-rule').each(function(){
                $(this).click(function(){
                    $('.input-elm').each(function(){
                        $(this).find('option:first').attr('selected', 'selected');
                    });
                });
            });
        });
    </script>
<?php

$actionsRight = '';
$actionsLeft = '<a href="'.api_get_path(WEB_CODE_PATH).'session/session_add.php">'.
    Display::getMdiIcon('google-classroom', 'ch-tool-icon-gradient', null, 32, get_lang('Add a training session')).'</a>';
if (api_is_platform_admin()) {
    $actionsLeft .= '<a href="'.api_get_path(WEB_CODE_PATH).'session/add_many_session_to_category.php">'.
        Display::getMdiIcon('tab-plus', 'ch-tool-icon-gradient', null, 32, get_lang('Add training sessions to categories')).'</a>';
    $actionsLeft .= '<a href="'.api_get_path(WEB_CODE_PATH).'session/session_category_list.php">'.
        Display::getMdiIcon('file-tree-outline', 'ch-tool-icon-gradient', null, 32, get_lang('Sessions categories list')).'</a>';
}

echo $actions;
if (api_is_platform_admin()) {
    $actionsRight .= $sessionFilter->returnForm();

    // Create a search-box
    $form = new FormValidator(
        'search_simple',
        'get',
        api_get_self().'?list_type='.$listType,
        '',
        [],
        FormValidator::LAYOUT_INLINE
    );
    $form->addElement('text', 'keyword', null, ['aria-label' => get_lang('Search')]);
    $form->addHidden('list_type', $listType);
    $form->addButtonSearch(get_lang('Search'));
    $actionsRight .= $form->returnForm();
}

echo Display::toolbarAction('toolbar', [$actionsLeft, $actionsRight]);
echo SessionManager::getSessionListTabs($listType);
echo '<div id="session-table" class="table-responsive">';
echo Display::grid_html('sessions');
echo '</div>';

Display::display_footer();
