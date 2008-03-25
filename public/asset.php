<?PHP
// no-cache ?forces caches to submit the request to the origin server for validation before releasing a cached copy, every time. This is useful to assure that authentication is respected.
// must-revalidate ?tells caches that they must obey any freshness information you give them about a representation. By specifying this header, you�re telling the cache that you want it to strictly follow your rules.
header("Cache-Control: no-cache, must-revalidate");

$query_string = @$_REQUEST;

// required for all pages, after user login is verified function displayloginfor checks all user security functions, gets the users first/last name and customer log as well as loads ovms.ini.php
require_once("config.php");
require_once("dblink.php");
require_once("assetDBManager.php");


// set the page name
$smarty->assign('pageName', 'Assets');

// session_start() creates a session or resumes the current one based on the current session id that's being passed via a request, such as GET, POST, or a cookie.
// If you want to use a named session, you must call session_name() before calling session_start().
session_start();

// creates a new user object from the user class
$user = new User($db);

// validates that the user is logged in properly, if not redirects to the login page.
verify_login($user, $smarty);
$user_id = $user->getUserId();

// get user right for this screen
// $user->checkRightByFunction($screen_name, "function_name");

$view_right	= $user->checkRightByFunction("asset", "read");
$edit_right = $user->checkRightByFunction("asset", "update");
$add_right  = $user->checkRightByFunction("asset", "create");
$del_right  = $user->checkRightByFunction("asset", "delete");


// let's template know how to display the page
$smarty->assign('view_right', $view_right);
$smarty->assign('edit_right', $edit_right);
$smarty->assign('add_right', $add_right);
$smarty->assign('del_right', $del_right);
/* END **** User Right ***************/

/**************Main Area*****************/
if($view_right || $del_right || $edit_right) {
	//$query_string = $_REQUEST;
	extract($query_string);
	$dbObj = new AssetDBManager($db,$user_id);
	if (isset($act) && $act == "Delete" && $del_right)
	{

		$dbObj->deleteAssets($query_string);
	}
	if (!isset($listall) || $listall=="" || $listall==0)
	{
		$limitnum=10;
		$listall=1;
	}
	else
		$limitnum=0;
	$pageno = isset($pageno)?(int)$pageno:0;
	if ($pageno<1) $pageno = 1;
 	$filter_data = $dbObj->searchAssets($query_string,0);
	
	$report_filter_data = array();
    if(is_array($filter_data)){
    	foreach ($filter_data as $key => $valarr) {
    		if (is_array($valarr))
    		{
    			$report_filter_data[] = array('Asset Name' => $valarr['asset_name'],
    										  'System' => $valarr['system_name'],
    										  'IP Address' => $valarr['address_ip'],
    										  'Port' => $valarr['address_port'],
    										  'Product' => $valarr['prod_name'],
    										  'Vendor' => $valarr['prod_vendor']);
    		}
    	}
    }
	$_SESSION['asset_report_data'] = $report_filter_data;
	$_SESSION['asset_report_querystring'] = $query_string;
	$smarty->assign('filter_data', $filter_data);
	$smarty->assign('filter_data_rownum', sizeof($filter_data));

	$summary_data = $dbObj->getSummaryList();

	$system_list  = $dbObj->getSystemList();
	$network_list = $dbObj->getNetworkList();

	$smarty->assign('listall',$listall);
	$smarty->assign('pageno',isset($pageno)?$pageno:0);
	$smarty->assign('order',isset($order)?$order:0);
	$smarty->assign('orderbyfield', isset($orderbyfield)?$orderbyfield:'');
	$smarty->assign('maxpageno',$maxpageno);
	$smarty->assign('vendor', isset($vendor)?$vendor:'');
	$smarty->assign('product', isset($product)?$product:'');
	$smarty->assign('ip', isset($ip)?$ip:'');
	$smarty->assign('port', isset($port)?$port:'');
	$smarty->assign('version', isset($version)?$version:'');
	$smarty->assign('system', isset($system)?$system:'');
	$smarty->assign('summary_data', $summary_data);
	$smarty->assign('system_list', $system_list);
	$smarty->assign('network_list', $network_list);
}

	$smarty->display('assets.tpl');


?>
