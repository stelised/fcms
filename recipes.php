<?php
session_start();
if (get_magic_quotes_gpc()) {
    $_REQUEST = array_map('stripslashes', $_REQUEST);
    $_GET = array_map('stripslashes', $_GET);
    $_POST = array_map('stripslashes', $_POST);
    $_COOKIE = array_map('stripslashes', $_COOKIE);
}
include_once('inc/config_inc.php');
include_once('inc/util_inc.php');

// Check that the user is logged in
isLoggedIn();
$current_user_id = (int)escape_string($_SESSION['login_id']);

header("Cache-control: private");
include_once('inc/recipes_class.php');
$rec = new Recipes($current_user_id, 'mysql', $cfg_mysql_host, $cfg_mysql_db, $cfg_mysql_user, $cfg_mysql_pass);

// Setup the Template variables;
$TMPL['pagetitle'] = T_('Recipes');
$TMPL['path'] = "";
$TMPL['admin_path'] = "admin/";
$TMPL['javascript'] = '
<script type="text/javascript">
//<![CDATA[
Event.observe(window, \'load\', function() {
    if (!$$(\'.delrec input[type="submit"]\')) { return; }
    $$(\'.delrec input[type="submit"]\').each(function(item) {
        item.onclick = function() { return confirm(\''.T_('Are you sure you want to DELETE this?').'\'); };
        var hid = document.createElement(\'input\');
        hid.setAttribute(\'type\', \'hidden\');
        hid.setAttribute(\'name\', \'confirmed\');
        hid.setAttribute(\'value\', \'true\');
        item.insert({\'after\':hid});
    });
    if ($(\'toolbar\')) {
        $(\'toolbar\').removeClassName("hideme");
    }
    if ($(\'smileys\')) {
        $(\'smileys\').removeClassName("hideme");
    }
    if ($(\'upimages\')) {
        $(\'upimages\').removeClassName("hideme");
    }
    return true;
});
//]]>
</script>';

// Show Header
include_once(getTheme($current_user_id) . 'header.php');

echo '
        <div id="recipe" class="centercontent">';

$show = true;

// Add recipe
if (isset($_POST['submitadd'])) {
    $name = escape_string($_POST['name']);
    $recipe = escape_string($_POST['post']);
    $sql = "INSERT INTO `fcms_recipes` 
                (`name`, `category`, `recipe`, `user`, `date`) 
            VALUES('$name', 
                '".escape_string($_POST['category'])."', '$recipe', $current_user_id, NOW()
            )";
    mysql_query($sql) or displaySQLError(
        'New Recipe Error', __FILE__ . ' [' . __LINE__ . ']', $sql, mysql_error()
        );
    $rec_id = mysql_insert_id();
    echo '
            <p class="ok-alert" id="add">'.T_('Recipe Added Successfully').'</p>
            <script type="text/javascript">
                window.onload=function(){ var t=setTimeout("$(\'add\').toggle()",3000); }
            </script>';
    // Email members
    $sql = "SELECT u.`email`, s.`user` "
         . "FROM `fcms_user_settings` AS s, `fcms_users` AS u "
         . "WHERE `email_updates` = '1'"
         . "AND u.`id` = s.`user`";
    $result = mysql_query($sql) or displaySQLError(
        'Email Updates Error', __FILE__ . ' [' . __LINE__ . ']', 
        $sql, mysql_error()
    );
    if (mysql_num_rows($result) > 0) {
        switch ($_POST['category']) {
            case T_('Appetizer'):
                $cat = "1";
                break;
            case T_('Breakfast'):
                $cat = "2";
                break;
            case T_('Dessert'):
                $cat = "3";
                break;
            case T_('Entree (Meat)'):
                $cat = "4";
                break;
            case T_('Entree (Seafood)'):
                $cat = "5";
                break;
            case T_('Entree (Vegetarian)'):
                $cat = "6";
                break;
            case T_('Salad'):
                $cat = "7";
                break;
            case T_('Side Dish'):
                $cat = "8";
                break;
            case T_('Soup'):
                $cat = "9";
                break;
            default:
                $cat = "1";
                break;
        }
        while ($r = mysql_fetch_array($result)) {
            $recipe_name = $name;
            $name = getUserDisplayName($current_user_id);
            $to = getUserDisplayName($r['user']);
            $subject = sprintf(T_('%s has added the recipe: %s'), $name, $recipe_name);
            $email = $r['email'];
            $url = getDomainAndDir();
            $msg = T_('Dear').' '.$to.',

'.$subject.'

'.$url.'recipes.php?category='.$cat.'

----
'.T_('To stop receiving these notifications, visit the following url and change your \'Email Update\' setting to No:').'

'.$url.'settings.php

';
            mail($email, $subject, $msg, $email_headers);
        }
    }
}

// Edit recipe
if (isset($_POST['submitedit'])) {
    $name = escape_string($_POST['name']);
    $recipe = escape_string($_POST['post']);
    $sql = "UPDATE `fcms_recipes` "
         . "SET `name` = '$name', "
            . "`category` = '" . escape_string($_POST['category']) . "', "
            . "`recipe` = '$recipe' "
         . "WHERE `id` = " . escape_string($_POST['id']);
    mysql_query($sql) or displaySQLError(
        'Edit Recipe Error', __FILE__ . ' [' . __LINE__ . ']', $sql, mysql_error()
        );
    echo '
            <p class="ok-alert" id="edit">'.T_('Changes Updated Successfully').'</p>
            <script type="text/javascript">
                window.onload=function(){ var t=setTimeout("$(\'edit\').toggle()",3000); }
            </script>';
}

// Delete confirmation
if (isset($_POST['delrecipe']) && !isset($_POST['confirmed'])) {
    $show = false;
    echo '
                <div class="info-alert clearfix">
                    <form action="recipes.php" method="post">
                        <h2>'.T_('Are you sure you want to DELETE this?').'</h2>
                        <p><b><i>'.T_('This can NOT be undone.').'</i></b></p>
                        <div>
                            <input type="hidden" name="id" value="'.$_POST['id'].'"/>
                            <input style="float:left;" type="submit" id="delconfirm" name="delconfirm" value="'.T_('Yes').'"/>
                            <a style="float:right;" href="recipes.php">'.T_('Cancel').'</a>
                        </div>
                    </form>
                </div>';

// Delete recipe
} elseif (isset($_POST['delconfirm']) || isset($_POST['confirmed'])) {
    $sql = "DELETE FROM `fcms_recipes` WHERE `id` = " . escape_string($_POST['id']);
    mysql_query($sql) or displaySQLError(
        'Delete Recipe Error', __FILE__ . ' [' . __LINE__ . ']', $sql, mysql_error()
        );
    echo '
            <p class="ok-alert" id="del">'.T_('Recipe Deleted Successfully').'</p>
            <script type="text/javascript">
                window.onload=function(){ var t=setTimeout("$(\'del\').toggle()",2000); }
            </script>';
}

// Add recipe form
if (isset($_GET['addrecipe']) && checkAccess($current_user_id) <= 5) {
    $show = false;
    $cat = isset($_GET['cat']) ? $_GET['cat'] : 'error';
    $rec->displayForm('add', 0, 'error', $cat, 'error');
}

// Edit recipe form
if (isset($_POST['editrecipe'])) {
    $show = false;
    $rec->displayForm('edit', $_POST['id'], $_POST['name'], $_POST['category'], $_POST['post']);
}

// Show recipes in specific Category
if (isset($_GET['category'])) {
    // Santizing user input - category - only allow digits 0-9
    if (preg_match('/^\d+$/', $_GET['category'])) {
        $show = false;
        $page = 1; $id = 0;
        if (isset($_GET['page'])) {
            if (preg_match('/^\d+$/', $_GET['page'])) {
                $page = escape_string($_GET['page']);
            }
        }
        if (isset($_GET['id'])) {
            if (preg_match('/^\d+$/', $_GET['id'])) {
                $id = escape_string($_GET['id']);
            }
        }
        $rec->showRecipeInCategory(escape_string($_GET['category']), $page, $id);
    }
}
if ($show) {
    $rec->showRecipes();
}

echo '
            </div><!-- #recipe .centercontent -->';


// Show Footer
include_once(getTheme($current_user_id) . 'footer.php');
