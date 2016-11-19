<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "mt/mt_config.php";
include('../app/config/base.php');

$db = new PDO("mysql:host={$server};dbname={$database}", $user_name, $password, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'"));

$arr_blacklist = array();
if ($_POST) {
    $campaign_id = (int)$_POST['campaign'];
    $i = 1;
    $tmp_arr = array();
    $arr_blacklist = array();
    foreach($_POST['imt_select1'] as $key => $filter) {
        if ($filter) {
            $tmp_arr[] = array($_POST['imt_select1'][$key], $_POST['imt_select2'][$key], $_POST['imt_value'][$key]);
        }
        if ($i % 3 == 0) {
            if ($tmp_arr) {
                $arr_blacklist[] = $tmp_arr;
            }
            $tmp_arr = array();
        }
        $i++;
    }

    $i = 1;
    $tmp_arr = array();
    $arr_whitelist = array();
    foreach($_POST['imt_select1_wl'] as $key => $filter) {
        if ($filter) {
            $tmp_arr[] = array($_POST['imt_select1_wl'][$key], $_POST['imt_select2_wl'][$key], $_POST['imt_value_wl'][$key]);
        }
        if ($i % 3 == 0) {
            if ($tmp_arr) {
                $arr_whitelist[] = $tmp_arr;
            }
            $tmp_arr = array();
        }
        $i++;
    }
    $plugin_filter = array(
        'imt_select1' => $_POST['imt_select1'],
        'imt_select2' => $_POST['imt_select2'],
        'imt_value' => $_POST['imt_value'],
        'imt_select1_wl' => $_POST['imt_select1_wl'],
        'imt_select2_wl' => $_POST['imt_select2_wl'],
        'imt_value_wl' => $_POST['imt_value_wl'],
    );

    $cookie = isset($_COOKIE['plugin_filter']) ? json_decode($_COOKIE['plugin_filter'], true) : array();
    $cookie[$_POST['campaign']] = $plugin_filter;
    $cookie['campaign'] = $campaign_id;
    setcookie("plugin_filter", json_encode($cookie), time() + (365 * 24 * 60 * 60));
}

$stmt = $db->prepare(
    "SELECT o.offer_payout,
    c.camp_cpc
    FROM mt_campaigns AS c
    LEFT JOIN mt_offers AS o ON o.offer_id = c.camp_id
    WHERE c.camp_id = ?"
);
$stmt->execute(array($campaign_id));
$data_camp = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $db->prepare(
    "SELECT c.click_c1,
    COUNT(*) AS clicks,
    (SELECT COUNT(*) FROM mt_click WHERE camp_id = :campaign AND click_moffer = 0 AND click_offer = 1 AND click_c1 = c.click_c1) AS clicks2,
    (SELECT COUNT(*) FROM mt_click WHERE camp_id = :campaign AND click_moffer = 0 AND click_offer = 1 AND click_c1 = c.click_c1 AND click_lead = 1) AS lead
    FROM mt_click AS c
    WHERE camp_id = :campaign
    AND click_moffer = 0
    GROUP BY c.click_c1
    ORDER BY clicks DESC"
);
$stmt->execute(array('campaign' => $campaign_id));
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$allow_filter_items = array(
    "clicks",
    "lp_clicks",
    "lp_ctr",
    "leads",
    "offer_cvr",
    "lp_cvr",
    "rev",
    "spend",
    "epc",
    "roi"
);

$str_filter2 = '';
foreach ($arr_blacklist as $filters) {
    $str_filter = '';
    foreach ($filters as $filter) {
        if (in_array($filter[0], $allow_filter_items) && $filter[2] != '') {
            if ($filter[1] == 'greater') {
                $compaire = '>';
            } elseif ($filter[1] == 'less') {
                $compaire = '<';
            } elseif ($filter[1] == 'equal') {
                $compaire = '==';
            }
            if (!$str_filter) {
                $str_filter = "\$$filter[0] $compaire $filter[2]";
            } else {
                $str_filter .= " && \$$filter[0] $compaire $filter[2]";
            }
        }
    }
    if ($str_filter) {
        if (!$str_filter2) {
            $str_filter2 = "($str_filter)";
        } else {
            $str_filter2 .= " || ($str_filter)";
        }
    }
    $str_filter = '';
}

$str_filter2_wl = '';
foreach ($arr_whitelist as $filters) {
    $str_filter = '';
    foreach ($filters as $filter) {
        if (in_array($filter[0], $allow_filter_items) && $filter[2] != '') {
            if ($filter[1] == 'greater') {
                $compaire = '>';
            } elseif ($filter[1] == 'less') {
                $compaire = '<';
            } elseif ($filter[1] == 'equal') {
                $compaire = '==';
            }
            if (!$str_filter) {
                $str_filter = "\$$filter[0] $compaire $filter[2]";
            } else {
                $str_filter .= " && \$$filter[0] $compaire $filter[2]";
            }
        }
    }
    if ($str_filter) {
        if (!$str_filter2_wl) {
            $str_filter2_wl = "($str_filter)";
        } else {
            $str_filter2_wl .= " || ($str_filter)";
        }
    }
    $str_filter = '';
}

$array_bl_sum = array();
$array_not_bl = array();
$array_wl_sum = array();
$array_not_wl = array();
foreach ($data as $tmp) {
    $clicks = $tmp['clicks'];
    $lp_clicks = $tmp['clicks2'];
    $lp_ctr = $tmp['clicks2'] * 100 / $tmp['clicks'];
    $leads = $tmp['lead'];
    $offer_cvr  = $leads / $lp_clicks;
    $lp_cvr = $leads / $clicks;
    $rev = $tmp['lead'] * $data_camp['offer_payout'];
    $spend = $tmp['clicks'] * $data_camp['camp_cpc'];
    $epc = $rev / $clicks;
    $roi = ($rev - $spend) * 100 / $spend;

    if (eval("return $str_filter2;")) {
        $array_bl_sum[] = $tmp['click_c1'];
    } else {
        $array_not_bl[] = $tmp['click_c1'];
    }

    if (eval("return $str_filter2_wl;")) {
        $array_wl_sum[] = $tmp['click_c1'];
    } else {
        $array_not_wl[] = $tmp['click_c1'];
    }
}
$str_bl_sum = implode('&#13;&#10;', $array_bl_sum);
$str_not_bl = implode('&#13;&#10;', $array_not_bl);
$str_wl_sum = implode('&#13;&#10;', $array_wl_sum);
$str_not_wl = implode('&#13;&#10;', $array_not_wl);

$count_bl_sum = count($array_bl_sum);
$count_not_bl = count($array_not_bl);
$count_wl_sum = count($array_wl_sum);
$count_not_wl = count($array_not_wl);

$code_bl_sum = getJs($array_bl_sum, "#fba6a6");
$code_not_bl = getJs($array_not_bl, "#fba6a6");
$code_wl_sum = getJs($array_wl_sum, "#AFFFA6");
$code_not_wl = getJs($array_not_wl, "#AFFFA6");

function getJs($array, $color) {
    $code = '';
    if ($array) {
        $tmp_array = array();
        foreach ($array as $tmp) {
            $tmp_array[] = "'$tmp'";
        }
        $tmp_str = implode(',', $tmp_array);
        $code = <<<HTML
        arr = [$tmp_str];
        $('.statTableWrapper').each(function( index, element ) {
        if ($(element).find('.statTableType').html() == 'Group Data Stats') {
            table = element;           
        }
        });
        $(table).find(".sRowAlt").each(function( index, element ) {
            if (jQuery.inArray($(element).find('.cName').text(), arr) > -1) {
                $(element).css("background-color", "$color");
            }
        });
HTML;
    }
    return $code;
}
$stmt = $db->prepare(
    "SELECT camp_id,
    camp_name
    FROM mt_campaigns
    WHERE camp_status = 0"
);
$stmt->execute();
$campaigns_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$html_filter = <<<HTML
    <select class="form-control imt_select1" name="imt_select1[]" style="display: inline-block; width: 120px;">
        <option value=""></option>
        <option value="clicks">Clicks</option>
        <option value="lp_clicks">LP clicks</option>
        <option value="lp_ctr">LP CTR</option>
        <option value="leads">Leads</option>
        <option value="offer_cvr">Offer CVR</option>
        <option value="lp_cvr">LP CVR</option>
        <option value="rev">Rev.</option>
        <option value="spend">Spend</option>
        <option value="epc">EPC</option>
        <option value="roi">ROI</option>
    </select>
    <select class="form-control imt_select2" name="imt_select2[]" style="display: inline-block; width: 60px;">
        <option value="greater">&gt;</option>
        <option value="less">&lt;</option>
        <option value="equal">=</option>
    </select>
    <input type="text" class="form-control imt_value" name="imt_value[]" style="display: inline-block; width: 80px;">
HTML;

$html_filter2 = "<div style=\"margin-top: 5px;\">$html_filter AND $html_filter AND $html_filter</div>";
for($i = 1; $i <= 10; $i++) {
    if ($i == 1) {
        $html_filter3 = $html_filter2;
    } else {
        $html_filter3 .= "<div style=\"margin-top: 5px;\">OR</div>" . $html_filter2;
    }
}

$html_filter_wl = <<<HTML
    <select class="form-control imt_select1_wl" name="imt_select1_wl[]" style="display: inline-block; width: 120px;">
        <option value=""></option>
        <option value="clicks">Clicks</option>
        <option value="lp_clicks">LP clicks</option>
        <option value="lp_ctr">LP CTR</option>
        <option value="leads">Leads</option>
        <option value="offer_cvr">Offer CVR</option>
        <option value="lp_cvr">LP CVR</option>
        <option value="rev">Rev.</option>
        <option value="spend">Spend</option>
        <option value="epc">EPC</option>
        <option value="roi">ROI</option>
    </select>
    <select class="form-control imt_select2_wl" name="imt_select2_wl[]" style="display: inline-block; width: 60px;">
        <option value="greater">&gt;</option>
        <option value="less">&lt;</option>
        <option value="equal">=</option>
    </select>
    <input type="text" class="form-control imt_value_wl" name="imt_value_wl[]" style="display: inline-block; width: 80px;">
HTML;

$html_filter2_wl = "<div style=\"margin-top: 5px;\">$html_filter_wl AND $html_filter_wl AND $html_filter_wl</div>";
for($i = 1; $i <= 10; $i++) {
    if ($i == 1) {
        $html_filter3_wl = $html_filter2_wl;
    } else {
        $html_filter3_wl .= "<div style=\"margin-top: 5px;\">OR</div>" . $html_filter2_wl;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="favicon.ico">

    <title>True iMobiTrax Plugin</title>

    <!-- Bootstrap core CSS -->
    <link href="https://getbootstrap.com/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
    <link href="https://getbootstrap.com/assets/css/ie10-viewport-bug-workaround.css" rel="stylesheet">

    <!-- Custom styles for this template -->
    <link href="https://getbootstrap.com/examples/cover/cover.css" rel="stylesheet">

    <!-- Just for debugging purposes. Don't actually copy these 2 lines! -->
    <!--[if lt IE 9]><script src="https://getbootstrap.com/assets/js/ie8-responsive-file-warning.js"></script><![endif]-->
    <script src="https://getbootstrap.com/assets/js/ie-emulation-modes-warning.js"></script>

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
</head>

<body>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script>window.jQuery || document.write('<script src="https://getbootstrap.com/assets/js/vendor/jquery.min.js"><\/script>')</script>
<script src="https://getbootstrap.com/dist/js/bootstrap.min.js"></script>
<!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
<script src="https://maxcdn.bootstrapcdn.com/js/ie10-viewport-bug-workaround.js"></script>

<script>
    function getCookie(name) {
        var matches = document.cookie.match(new RegExp(
            "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
        ));
        return matches ? decodeURIComponent(matches[1]) : undefined;
    }

    $(document).ready(function() {
        if (getCookie('plugin_filter')) {
            cook = jQuery.parseJSON(getCookie('plugin_filter'));
            if (cook['campaign'] !== undefined) {
                updateValues(cook['campaign']);
                $("#item1").val(cook['campaign']);
            } else {
                updateValues($("#item1").val());
            }
        } else {
            updateValues($("#item1").val());
        }

        $("#item1").change(function() {
            updateValues($("#item1").val());
        });
    });

    function updateValues(campaign_id) {
        if (getCookie('plugin_filter')) {
            cook = jQuery.parseJSON(getCookie('plugin_filter'));
            if (cook[campaign_id] !== undefined) {
                $.each(cook[campaign_id].imt_select1, function(index, value) {
                    if (value) {
                        $('.imt_select1:eq(' + (index) + ')').val(value);
                    }
                });
                $.each(cook[campaign_id].imt_select2, function(index, value) {
                    if (value) {
                        $('.imt_select2:eq(' + (index) + ')').val(value);
                    }
                });
                $.each(cook[campaign_id].imt_value, function(index, value) {
                    if (value) {
                        $('.imt_value:eq(' + (index) + ')').val(value);
                    }
                });

                $.each(cook[campaign_id].imt_select1_wl, function(index, value) {
                    if (value) {
                        $('.imt_select1_wl:eq(' + (index) + ')').val(value);
                    }
                });
                $.each(cook[campaign_id].imt_select2_wl, function(index, value) {
                    if (value) {
                        $('.imt_select2_wl:eq(' + (index) + ')').val(value);
                    }
                });
                $.each(cook[campaign_id].imt_value_wl, function(index, value) {
                    if (value) {
                        $('.imt_value_wl:eq(' + (index) + ')').val(value);
                    }
                });
            }
        }
    }


</script>
<div class="site-wrapper">

    <div class="site-wrapper-inner">

        <div class="cover-container" style="width: 100%;">

            <div class="clearfix">
                <div class="inner">
                    <h3 class="masthead-brand">True iMobiTrax Plugin</h3>
                    <nav>
                        <ul class="nav masthead-nav">
                            <li class="active"><a href="campaigns.php">iMobiTrax</a></li>
                            <!--<li><a href="#">Features</a></li>
                            <li><a href="#">Contact</a></li>-->
                        </ul>
                    </nav>
                </div>
            </div>

            <div class="inner cover">
                <div class="row">
                    <form method="post" action="">
                        <div class="form-group text-center" style="margin: 0 auto;">
                            <label for="item1">Campaign:</label>
                            <select class="form-control" id="item1" name="campaign" style="display: inline-block; width: 200px;">
                                <? foreach($campaigns_list as $campaign): ?>
                                <option value="<?=$campaign['camp_id']?>"><?=$campaign['camp_name']?></option>
                                <? endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12 text-center" style="margin-top: 20px;">
                            <h1 class="cover-heading text-center">BlackList</h1>
                            <div id="selects_bl">
                            <?=$html_filter3?>
                            </div>
                        </div>
                        <div class="col-md-12 text-center" style="margin-top: 20px;">
                            <h1 class="cover-heading text-center">WhiteList</h1>
                            <div id="selects_bl">
                                <?=$html_filter3_wl?>
                            </div>
                        </div>
                        <div class="col-md-12" style="margin-top: 20px;">
                            <input class="btn btn-default" type="submit" value="Submit">
                        </div>
                    </form>
                </div>
                <h1 class="cover-heading">Result</h1>
                <div class="row">
                    <div class="col-md-3" style="margin-top: 20px;">
                        BlackList(<?=$count_bl_sum?>)<br><br>
                        <textarea class="form-control" rows="15"><?=$str_bl_sum?></textarea><br>
                        JavaScript<br><br>
                        <textarea class="form-control" rows="3"><?=$code_bl_sum?></textarea>
                    </div>
                    <div class="col-md-3" style="margin-top: 20px;">
                        Not BlackList(<?=$count_not_bl?>)<br><br>
                        <textarea class="form-control" rows="15"><?=$str_not_bl?></textarea><br>
                        JavaScript<br><br>
                        <textarea class="form-control" rows="3"><?=$code_not_bl?></textarea>
                    </div>
                    <div class="col-md-3" style="margin-top: 20px;">
                        WhiteList(<?=$count_wl_sum?>)<br><br>
                        <textarea class="form-control" rows="15"><?=$str_wl_sum?></textarea><br>
                        JavaScript<br><br>
                        <textarea class="form-control" rows="3"><?=$code_wl_sum?></textarea>
                    </div>
                    <div class="col-md-3" style="margin-top: 20px;">
                        Not WhiteList(<?=$count_not_wl?>)<br><br>
                        <textarea class="form-control" rows="15"><?=$str_not_wl?></textarea><br>
                        JavaScript<br><br>
                        <textarea class="form-control" rows="3"><?=$code_not_wl?></textarea>
                    </div>
                </div>
            </div>

            <div class="">
                <div class="inner">
                    <p>Nevep.ru 2016</p>
                </div>
            </div>

        </div>

    </div>

</div>
</body>
</html>