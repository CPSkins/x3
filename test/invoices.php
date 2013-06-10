<?php
// Setup PHP Enviropment
error_reporting(E_ALL & ~E_NOTICE);
// Define Contants
define('THIS_SCRIPT', 'invoices');

// Cache Templates & Variables
// get special phrase groups
$phrasegroups = array(
	'invoices',
	'postbit',
	'posting'
);
$specialtemplates = array(
    'smiliecache',
	'bbcodecache',
	'attachmentcache',
);
$globaltemplates = array(
    'invoices.css',
    'invoices_main',
    'invoices_browse_invoices',
    'invoices_customers',
    'invoices_browse_customers',
    'invoices_view_customer',
    'invoices_customer_invoices',
    'invoices_settings',
    'invoices_templates',
    'invoices_phrases',
    'invoices_addedit_customer',
    'invoices_add_invoice',
    'invoices_user_menu',
    'invoices_statistics',
    'invoices_navbar_menu'
);
$actiontemplates = array();

// Include Globals
require_once('./global.php');
require_once('./invoices/pdf/config/lang/eng.php');
require_once('./invoices/pdf/tcpdf.php');
require_once('./invoices/includes/functions.php');
require_once('./invoices/includes/class.phpmailer.php');
require_once('./includes/class_bootstrap_framework.php');
vB_Bootstrap_Framework::init();

// Main Script
if(!($permissions['invoices'] & $vbulletin->bf_ugp['invoices']['access'])){
    print_no_permission();
}
// Select Action
if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'main';
}

//  #######################################################################
//  ###############################  Index Page  ##########################
//  #######################################################################
if ($_REQUEST['do'] == 'main'){

    $userid = $vbulletin->userinfo['userid'];
    if(empty($vbulletin->userinfo['inv_company']))
    {
        $vbulletin->url="invoices.php?" . $vbulletin->session->vars['sessionurl'] . "do=settings";
        eval(print_standard_redirect('invoices_settings', true, true));
    }
    $status = $vbulletin->input->clean_gpc('r', 'status', TYPE_UINT);
    if(!$status)
    {
        $status = 0;
    }
    if($status == 0)
    {
       $totalinvoices = $db->query_first("
                             SELECT COUNT(`id`) AS `totalinvoices`
                             FROM `" . TABLE_PREFIX . "invoices_invoices`
                             WHERE userid=$userid");
    }
    if($status == 1)
    {
       $totalinvoices = $db->query_first("
                             SELECT COUNT(`id`) AS `totalinvoices`
                             FROM `" . TABLE_PREFIX . "invoices_invoices`
                             WHERE userid=$userid AND paid<>'0000-00-00' AND isvoid=0");
    }
    if($status == 2)
    {
       $totalinvoices = $db->query_first("
                             SELECT COUNT(`id`) AS `totalinvoices`
                             FROM `" . TABLE_PREFIX . "invoices_invoices`
                             WHERE userid=$userid AND paid='0000-00-00' AND isvoid=0");
    }
    if($status == 3)
    {
       $today = date('Y-m-d');
       $totalinvoices = $db->query_first("
                             SELECT COUNT(`id`) AS `totalinvoices`
                             FROM `" . TABLE_PREFIX . "invoices_invoices`
                             WHERE userid=$userid AND duedate<'$today' AND paid='0000-00-00' AND isvoid=0 ");
    }
    if($status == 4)
    {
       $totalinvoices = $db->query_first("
                             SELECT COUNT(`id`) AS `totalinvoices`
                             FROM `" . TABLE_PREFIX . "invoices_invoices`
                             WHERE userid=$userid AND isvoid=1");
    }
    $records = $totalinvoices['totalinvoices'];
    $perpage = $vbulletin->userinfo['inv_perpage'];
    $pagenumber = $vbulletin->input->clean_gpc('r', 'pagenumber', TYPE_UINT);
    if (empty($pagenumber))
    {
        $pagenumber = 1;
    }
    $limitlower = ($pagenumber - 1) * $perpage+1;
    $limitupper = ($pagenumber) * $perpage;
    $counter = 0;
    if ($limitupper > $records)
	{
	    $limitupper = $records;
        if ($limitlower > $records)
	    {
	        $limitlower = $records-$perpage;
	    }
	}
	if ($limitlower <= 0)
	{
	    $limitlower = 1;
	}
    $pagenav = construct_page_nav($pagenumber, $perpage, $records, 'invoices.php?' . $vbulletin->session->vars['sessionurl'] . 'do=main&status='.$status);
    if($status == 0)
    {
       $invoices = $vbulletin->db->query_read("
	                               SELECT *
	                               FROM " . TABLE_PREFIX . "invoices_invoices
                                   WHERE userid=$userid
	                               ORDER BY id DESC
                                   LIMIT " . ($limitlower-1) . ", $perpage");
    }
    if($status == 1)
    {
       $invoices = $vbulletin->db->query_read("
	                               SELECT *
	                               FROM " . TABLE_PREFIX . "invoices_invoices
                                   WHERE userid=$userid AND paid<>'0000-00-00' AND isvoid=0
	                               ORDER BY id DESC
                                   LIMIT " . ($limitlower-1) . ", $perpage");
    }
    if($status == 2)
    {
       $invoices = $vbulletin->db->query_read("
	                               SELECT *
	                               FROM " . TABLE_PREFIX . "invoices_invoices
                                   WHERE userid=$userid AND paid='0000-00-00' AND isvoid=0
	                               ORDER BY id DESC
                                   LIMIT " . ($limitlower-1) . ", $perpage");
    }
    if($status == 3)
    {
       $today = date('Y-m-d');
       $invoices = $vbulletin->db->query_read("
  	                               SELECT *
	                               FROM " . TABLE_PREFIX . "invoices_invoices
                                   WHERE userid=$userid AND duedate<'$today' AND paid='0000-00-00' AND isvoid=0
	                               ORDER BY id DESC
                                   LIMIT " . ($limitlower-1) . ", $perpage");
    }
    if($status == 4)
    {
       $invoices = $vbulletin->db->query_read("
	                               SELECT *
	                               FROM " . TABLE_PREFIX . "invoices_invoices
                                   WHERE userid=$userid AND isvoid=1
	                               ORDER BY id DESC
                                   LIMIT " . ($limitlower-1) . ", $perpage");
    }
    while ($invoice = $vbulletin->db->fetch_array($invoices)){
           // Get Customer data
           $custid = $invoice["customerid"];
           $customer = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "invoices_customers WHERE id=$custid");
           if($invoice["paid"]=='0000-00-00')
           {
              $invoicepaid = '';

           } else {
              $invoicepaid = vbdate($vbulletin->userinfo['inv_dateformat'], strtotime($invoice["paid"]), '', '');
           }
           // Prepare Templates
		   $templater = vB_Template::create('invoices_browse_invoices');
		   $templater->register('id', $invoice[id]);
           $templater->register('issued', vbdate($vbulletin->userinfo['inv_dateformat'], strtotime($invoice[issued]), '', ''));
           $templater->register('duedate', vbdate($vbulletin->userinfo['inv_dateformat'], strtotime($invoice[duedate]), '', ''));
           $templater->register('paid', $invoicepaid);
           $templater->register('invoicetotal', getFormatPrice($invoice[invoicetotal],$vbulletin->userinfo[inv_priceformat]));
           $templater->register('sent', $invoice[sent]);
           $templater->register('isvoid', $invoice["isvoid"]);
           $templater->register('customerid', $customer[id]);
           $templater->register('customername', $customer[name]);
           $templater->register('currency', $vbulletin->userinfo[inv_currency]);
		   $browse_invoices .= $templater->render();
    }
    // Get Statistics
    getStatistics($userid);
    // Template for User Menu
    $templater = vB_Template::create('invoices_user_menu');
    $usermenu .= $templater->render();
    // Page Templates
	$templater = vB_Template::create('invoices_main');
	$templater->register_page_templates();
    $navbits = construct_navbits(array("invoices.php?" . $vbulletin->session->vars['sessionurl'] . ""=> $vbphrase["invoices_browse_invoices"]));
  	$navbar = render_navbar_template($navbits);
	$templater->register('navbar', $navbar);
    $templater->register('usermenu', $usermenu);
    $templater->register('status', $status);
    $templater->register('statistics', $statistics);
    $templater->register('copyright', $copyright);
    $templater->register('browse_invoices', $browse_invoices);
	print_output($templater->render());
}

//  #######################################################################
//  ###########################  Browse Customers  ########################
//  #######################################################################
if ($_REQUEST['do'] == 'customers'){

    $userid = $vbulletin->userinfo['userid'];
    if(empty($vbulletin->userinfo['inv_company']))
    {
        $vbulletin->url="invoices.php?" . $vbulletin->session->vars['sessionurl'] . "do=settings";
        eval(print_standard_redirect('invoices_settings', true, true));
    }
    $totalcustomers = $db->query_first("
                           SELECT COUNT(`id`) AS `totalcustomers`
                           FROM `" . TABLE_PREFIX . "invoices_customers`
                           WHERE userid=$userid");
    $records = $totalcustomers['totalcustomers'];
    $perpage = $vbulletin->userinfo['inv_perpage'];
    $pagenumber = $vbulletin->input->clean_gpc('r', 'pagenumber', TYPE_UINT);
    if (empty($pagenumber))
    {
        $pagenumber = 1;
    }
    $limitlower = ($pagenumber - 1) * $perpage+1;
    $limitupper = ($pagenumber) * $perpage;
    $counter = 0;
    if ($limitupper > $records)
	{
	    $limitupper = $records;
        if ($limitlower > $records)
	    {
	        $limitlower = $records-$perpage;
	    }
	}
	if ($limitlower <= 0)
	{
	    $limitlower = 1;
	}
    $pagenav = construct_page_nav($pagenumber, $perpage, $records, 'invoices.php?' . $vbulletin->session->vars['sessionurl'] . 'do=customers');
    $customers = $vbulletin->db->query_read("
	                             SELECT *
	                             FROM " . TABLE_PREFIX . "invoices_customers
                                 WHERE userid=$userid
	                             ORDER BY name ASC
                                 LIMIT " . ($limitlower-1) . ", $perpage");
    while ($customer = $vbulletin->db->fetch_array($customers)){
           // Get Customer Statistics
           $paid = 0;
           $unpaid = 0;
           $overdue = 0;
           $paidamount = 0;
           $unpaidamount = 0;
           $overdueamount = 0;
           getCustomerStatistics($customer["id"]);
           // Prepare Templates
		   $templater = vB_Template::create('invoices_browse_customers');
		   $templater->register('id', $customer[id]);
           $templater->register('name', $customer[name]);
           $templater->register('paid', $paid);
           $templater->register('paidamount', getFormatPrice($paidamount, $vbulletin->userinfo["inv_priceformat"]));
           $templater->register('unpaid', $unpaid);
           $templater->register('unpaidamount', getFormatPrice($unpaidamount, $vbulletin->userinfo["inv_priceformat"]));
           $templater->register('overdue', $overdue);
           $templater->register('overdueamount', getFormatPrice($overdueamount, $vbulletin->userinfo["inv_priceformat"]));
           $templater->register('currency', $vbulletin->userinfo["inv_currency"]);
		   $browse_customers .= $templater->render();
    }
    // Get Statistics
    getStatistics($userid);
    // Template for User Menu
    $templater = vB_Template::create('invoices_user_menu');
    $usermenu .= $templater->render();
    // Page Templates
	$templater = vB_Template::create('invoices_customers');
	$templater->register_page_templates();
    $navbits = construct_navbits(array("invoices.php?" . $vbulletin->session->vars['sessionurl'] . ""=> $vbphrase["invoices_browse_customers"]));
  	$navbar = render_navbar_template($navbits);
	$templater->register('navbar', $navbar);
    $templater->register('usermenu', $usermenu);
    $templater->register('statistics', $statistics);
    $templater->register('copyright', $copyright);
    $templater->register('browse_customers', $browse_customers);
	print_output($templater->render());
}

//  #######################################################################
//  #################################  Settings  ##########################
//  #######################################################################
if ($_REQUEST['do'] == 'settings')
{
    // Check for access permission
    if(!($permissions["invoices"] & $vbulletin->bf_ugp["invoices"]["access"]))
    {
         print_no_permission();
    }
    $userid = $vbulletin->userinfo['userid'];
    // Get Statistics
    getStatistics($userid);
    // Template for User Menu
    $templater = vB_Template::create('invoices_user_menu');
    $usermenu .= $templater->render();
    // Prepare Template
	$templater = vB_Template::create('invoices_settings');
	$templater->register_page_templates();
    $navbits = construct_navbits(array("invoices.php?" . $vbulletin->session->vars['sessionurl'] => $vbulletin->options['invoices_navbar'], '' => $vbphrase['invoices_settings']));
  	$navbar = render_navbar_template($navbits);
	$templater->register('navbar', $navbar);
    $templater->register('usermenu', $usermenu);
    $templater->register('statistics', $statistics);
    $templater->register('userid', $vbulletin->userinfo[userid]);
    $templater->register('company', str_replace('<br>','',$vbulletin->userinfo[inv_company]));
    $templater->register('logo', $vbulletin->userinfo[inv_logo]);
    $templater->register('oldlogo', $vbulletin->userinfo[inv_logo]);
    $templater->register('paypal', $vbulletin->userinfo[inv_paypal]);
    $templater->register('paypal_email', $vbulletin->userinfo[inv_paypal_email]);
    $templater->register('priceformat', $vbulletin->userinfo[inv_priceformat]);
    $templater->register('dateformat', $vbulletin->userinfo[inv_dateformat]);
    $templater->register('currency', $vbulletin->userinfo[inv_currency]);
    $templater->register('currency_long', $vbulletin->userinfo[inv_currency_long]);
    $templater->register('charset', $vbulletin->userinfo[inv_charset]);
    $templater->register('chardir', $vbulletin->userinfo[inv_chardir]);
    $templater->register('vat', $vbulletin->userinfo[inv_vat]);
    $templater->register('nextinvoice', $vbulletin->userinfo[inv_nextinvoice]);
    $templater->register('perpage', $vbulletin->userinfo[inv_perpage]);
    $templater->register('fromname', $vbulletin->userinfo[inv_fromname]);
    $templater->register('fromemail', $vbulletin->userinfo[inv_fromemail]);
    $templater->register('copyright', $copyright);
    print_output($templater->render());
}

//  #######################################################################
//  ##############################  Update Settings  ######################
//  #######################################################################
if ($_REQUEST['do'] == 'updatesettings')
{
    if(!($permissions['invoices'] & $vbulletin->bf_ugp['invoices']['access']))
    {
         print_no_permission();
    }
    $vbulletin->input->clean_array_gpc('p', array(
        'id'    	    => TYPE_INT,
        'userid'	    => TYPE_INT,
        'company'	    => TYPE_NOHTML,
        'fromname'    	=> TYPE_STR,
        'fromemail'    	=> TYPE_STR,
        'paypal'        => TYPE_INT,
        'paypal_email'	=> TYPE_STR,
        'priceformat'	=> TYPE_INT,
        'dateformat'	=> TYPE_STR,
        'currency'	    => TYPE_STR,
        'currency_long'	=> TYPE_STR,
        'vat'	        => TYPE_NUM,
        'nextinvoice'   => TYPE_INT,
        'charset'	    => TYPE_STR,
        'chardir'    	=> TYPE_STR,
        'perpage'    	=> TYPE_INT,
        'logo'			=> TYPE_NOHTML,
        'oldlogo'		=> TYPE_NOHTML,
        'removelogo'  	=> TYPE_UINT
	));
    // Get Values
    $id = $vbulletin->GPC['id'];
    $userid = $vbulletin->userinfo['userid'];
	$company = $db->escape_string($vbulletin->GPC['company']);
    $company = str_replace('\n','<br>',$company);
    $paypal = $vbulletin->GPC['paypal'];
	$paypal_email = $db->escape_string($vbulletin->GPC['paypal_email']);
    $fromname = $db->escape_string($vbulletin->GPC['fromname']);
    $fromemail = $db->escape_string($vbulletin->GPC['fromemail']);
    $priceformat = $vbulletin->GPC['priceformat'];
	$dateformat = $db->escape_string($vbulletin->GPC['dateformat']);
   	$currency = $db->escape_string($vbulletin->GPC['currency']);
	$currency_long = $db->escape_string($vbulletin->GPC['currency_long']);
	$vat = $vbulletin->GPC['vat'];
    $nextinvoice = $vbulletin->GPC['nextinvoice'];
	$charset = $db->escape_string($vbulletin->GPC['charset']);
	$chardir = $db->escape_string($vbulletin->GPC['chardir']);
    $perpage = $vbulletin->GPC['perpage'];
    $oldlogo = $db->escape_string($vbulletin->GPC['oldlogo']);
    // Manage Logo
    $removelogo = $vbulletin->GPC['removelogo'];
    if(!empty($removelogo) AND !empty($oldlogo))
    {
        unlink("invoices/logos/$oldlogo");
		$oldlogo = '';

	}
    $logo_name = $oldlogo;
    if($_FILES['logo']['name']) {
      $ext=get_ext($_FILES['logo']['name']);
      if ($ext<>'gif' AND $ext<>'pdf' AND $ext<>'doc' AND $ext<>'jpg' AND $ext<>'png') {
          $logo_name = $oldlogo;
      }
      $logoname  = $_FILES['logo']["name"];
      if ($logoname=='') {
          $logo_name = $oldlogo;
      } else {
          $logo_name=time()+rand(0,100000).".".$ext;
          $folder="invoices/logos/";
          If(!@move_uploaded_file($_FILES['logo']['tmp_name'],$folder.$logo_name)) {
			 $logo_name = $oldlogo;
		  }
      }
    }
    // Get Active user's userid
    $userid = $vbulletin->userinfo['userid'];
    // Exists. So update it
    $vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "user
                                       SET inv_company = '$company',
                                           inv_logo = '$logo_name',
                                           inv_paypal = '$paypal',
                                           inv_paypal_email = '$paypal_email',
                                           inv_fromname = '$fromname',
                                           inv_fromemail = '$fromemail',
                                           inv_priceformat = '$priceformat',
                                           inv_dateformat = '$dateformat',
                                           inv_currency = '$currency',
                                           inv_currency_long = '$currency_long',
                                           inv_vat = '$vat',
                                           inv_nextinvoice = '$nextinvoice',
                                           inv_charset = '$charset',
                                           inv_chardir = '$chardir',
                                           inv_perpage = '$perpage' WHERE userid=$userid");
    $vbulletin->url="invoices.php?" . $vbulletin->session->vars['sessionurl'] . "do=main";
    eval(print_standard_redirect('invoices_action_ok', true, true));
}


//  #######################################################################
//  ############################  Email Templates  ########################
//  #######################################################################
if ($_REQUEST['do'] == 'templates')
{
    // Check for access permission
    if(!($permissions["invoices"] & $vbulletin->bf_ugp["invoices"]["access"]))
    {
         print_no_permission();
    }
    // Get Active user's userid
    $userid = $vbulletin->userinfo['userid'];
    // Check if he has a profile
    $profile = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "invoices_settings WHERE userid=$userid LIMIT 1");
    $profileid = $profile["id"];
    // Then, check if Profile id exists or not
    if(!$profile)
    {
       // Profile id dosen't exists, let's populate default values as per vB4 requirements
       $profile["id"] = 0;
       $profile["userid"] = $vbulletin->userinfo['userid'];
       $profile["emailtitle1"] = '';
       $profile["emailbody1"] = '';
       $profile["emailtitle2"] = '';
       $profile["emailbody2"] = '';
       $profile["emailtitle3"] = '';
       $profile["emailbody3"] = '';
       $profile["emailtitle4"] = '';
       $profile["emailbody4"] = '';
       $profile["emailfooter"] = '';
    }
    // Get Statistics
    getStatistics($userid);
    // Template for User Menu
    $templater = vB_Template::create('invoices_user_menu');
    $usermenu .= $templater->render();
    // Prepare Template
	$templater = vB_Template::create('invoices_templates');
	$templater->register_page_templates();
    $navbits = construct_navbits(array("invoices.php?" . $vbulletin->session->vars['sessionurl'] => $vbulletin->options['invoices_navbar'], '' => $vbphrase['invoices_templates']));
  	$navbar = render_navbar_template($navbits);
	$templater->register('navbar', $navbar);
    $templater->register('usermenu', $usermenu);
    $templater->register('statistics', $statistics);
	$templater->register('id', $profile[id]);
    $templater->register('userid', $profile[userid]);
    $templater->register('emailtitle1', $profile[emailtitle1]);
    $templater->register('emailbody1', preg_replace("/\r|\n/", '', $profile[emailbody1]));
    $templater->register('emailtitle2', $profile[emailtitle2]);
    $templater->register('emailbody2', preg_replace("/\r|\n/", '', $profile[emailbody2]));
    $templater->register('emailtitle3', $profile[emailtitle3]);
    $templater->register('emailbody3', preg_replace("/\r|\n/", '', $profile[emailbody3]));
    $templater->register('emailtitle4', $profile[emailtitle4]);
    $templater->register('emailbody4', preg_replace("/\r|\n/", '', $profile[emailbody4]));
    $templater->register('emailfooter', preg_replace("/\r|\n/", '', $profile[emailfooter]));
    $templater->register('copyright', $copyright);
    print_output($templater->render());
}

//  #######################################################################
//  ##############################  Update Templates  #####################
//  #######################################################################
if ($_REQUEST['do'] == 'updatetemplates')
{
    if(!($permissions['invoices'] & $vbulletin->bf_ugp['invoices']['access']))
    {
         print_no_permission();
    }
    $vbulletin->input->clean_array_gpc('p', array(
        'id'    	    => TYPE_INT,
        'userid'	    => TYPE_INT,
		'emailtitle1'	=> TYPE_NOHTML,
		'emailtitle2'	=> TYPE_NOHTML,
   		'emailtitle3'	=> TYPE_NOHTML,
   		'emailtitle4'	=> TYPE_NOHTML
	));
    // Get Values
    $id = $vbulletin->GPC['id'];
    $userid = $vbulletin->userinfo['userid'];
	$emailtitle1 = $db->escape_string($vbulletin->GPC['emailtitle1']);
	$emailbody1 = addslashes($_POST['emailbody1']);
	$emailtitle2 = $db->escape_string($vbulletin->GPC['emailtitle2']);
	$emailbody2 = addslashes($_POST['emailbody2']);
	$emailtitle3 = $db->escape_string($vbulletin->GPC['emailtitle3']);
	$emailbody3 = addslashes($_POST['emailbody3']);
	$emailtitle4 = $db->escape_string($vbulletin->GPC['emailtitle4']);
	$emailbody4 = addslashes($_POST['emailbody4']);
	$emailfooter = addslashes($_POST['emailfooter']);
    // Get Active user's userid
    $userid = $vbulletin->userinfo['userid'];
    // Check if he has a profile
    $profile = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "invoices_settings WHERE userid=$userid LIMIT 1");
    $profileid = $profile["id"];
    if($profileid != 0)
    {
       // Exists. So update it
       $vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "invoices_settings
                                       SET emailtitle1 = '$emailtitle1',
                                           emailbody1 = '$emailbody1',
                                           emailtitle2 = '$emailtitle2',
                                           emailbody2 = '$emailbody2',
                                           emailtitle3 = '$emailtitle3',
                                           emailbody3 = '$emailbody3',
                                           emailtitle4 = '$emailtitle4',
                                           emailbody4 = '$emailbody4',
                                           emailfooter = '$emailfooter' WHERE id=$profileid");
    } else {
       // Dosen't exists. Save Settings
       $vbulletin->db->query_write("INSERT INTO " . TABLE_PREFIX . "invoices_settings
                                       SET userid = '$userid',
                                           emailtitle1 = '$emailtitle1',
                                           emailbody1 = '$emailbody1',
                                           emailtitle2 = '$emailtitle2',
                                           emailbody2 = '$emailbody2',
                                           emailtitle3 = '$emailtitle3',
                                           emailbody3 = '$emailbody3',
                                           emailtitle4 = '$emailtitle4',
                                           emailbody4 = '$emailbody4',
                                           emailfooter = '$emailfooter'");
       $profileid=$vbulletin->GPC['id'] = $db->insert_id();
    }
    $vbulletin->url="invoices.php?" . $vbulletin->session->vars['sessionurl'] . "do=main";
    eval(print_standard_redirect('invoices_action_ok', true, true));
}


//  #######################################################################
//  ############################  Invoice Phrases  ########################
//  #######################################################################
if ($_REQUEST['do'] == 'phrases')
{
    // Check for access permission
    if(!($permissions["invoices"] & $vbulletin->bf_ugp["invoices"]["access"]))
    {
         print_no_permission();
    }
    // Get Active user's userid
    $userid = $vbulletin->userinfo['userid'];
    // Check if he has a profile
    $profile = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "invoices_settings WHERE userid=$userid LIMIT 1");
    $profileid = $profile["id"];
    // Then, check if Profile id exists or not
    if(!$profile)
    {
       // Profile id dosen't exists, let's populate default values as per vB4 requirements
       $profile["id"] = 0;
       $profile["userid"] = $vbulletin->userinfo['userid'];
       $profile["invoice"] = '';
       $profile["invoicenbr"] = '';
       $profile["invoiceto"] = '';
       $profile["issued"] = '';
       $profile["duedate"] = '';
       $profile["unpaid"] = '';
       $profile["paid"] = '';
       $profile["overdue"] = '';
       $profile["item"] = '';
       $profile["quantity"] = '';
       $profile["price"] = '';
       $profile["net"] = '';
       $profile["tax"] = '';
       $profile["taxsum"] = '';
       $profile["gross"] = '';
       $profile["subtotal"] = '';
       $profile["discount"] = '';
       $profile["totalnet"] = '';
       $profile["taxamount"] = '';
       $profile["shipping"] = '';
       $profile["balancedue"] = '';
       $profile["terms"] = '';
       $profile["phone"] = '';
       $profile["email"] = '';
       $profile["vatdetails"] = '';
    }
    // Get Statistics
    getStatistics($userid);
    // Template for User Menu
    $templater = vB_Template::create('invoices_user_menu');
    $usermenu .= $templater->render();
    // Prepare Template
	$templater = vB_Template::create('invoices_phrases');
	$templater->register_page_templates();
    $navbits = construct_navbits(array("invoices.php?" . $vbulletin->session->vars['sessionurl'] => $vbulletin->options['invoices_navbar'], '' => $vbphrase['invoices_invoice_phrases']));
  	$navbar = render_navbar_template($navbits);
	$templater->register('navbar', $navbar);
    $templater->register('usermenu', $usermenu);
    $templater->register('statistics', $statistics);
	$templater->register('id', $profile[id]);
    $templater->register('userid', $profile[userid]);
    if(empty($profile["invoice"]))
    {
       $templater->register('invoice', $vbphrase[invoices_invoice]);
    } else {
       $templater->register('invoice', $profile[invoice]);
    }
    if(empty($profile["invoicenbr"]))
    {
       $templater->register('invoicenbr', $vbphrase[invoices_invoice_nbr]);
    } else {
       $templater->register('invoicenbr', $profile[invoicenbr]);
    }
    if(empty($profile["invoiceto"]))
    {
       $templater->register('invoiceto', $vbphrase[invoices_invoice_to]);
    } else {
       $templater->register('invoiceto', $profile[invoiceto]);
    }
    if(empty($profile["issued"]))
    {
       $templater->register('issued', $vbphrase[invoices_issuedate]);
    } else {
       $templater->register('issued', $profile[issued]);
    }
    if(empty($profile["duedate"]))
    {
       $templater->register('duedate', $vbphrase[invoices_duedate]);
    } else {
       $templater->register('duedate', $profile[duedate]);
    }
    if(empty($profile["unpaid"]))
    {
       $templater->register('unpaid', $vbphrase[invoices_unpaid]);
    } else {
       $templater->register('unpaid', $profile[unpaid]);
    }
    if(empty($profile["paid"]))
    {
       $templater->register('paid', $vbphrase[invoices_paid]);
    } else {
       $templater->register('paid', $profile[paid]);
    }
    if(empty($profile["overdue"]))
    {
       $templater->register('overdue', $vbphrase[invoices_overdue]);
    } else {
       $templater->register('overdue', $profile[overdue]);
    }
    if(empty($profile["item"]))
    {
       $templater->register('item', $vbphrase[invoices_item_desc]);
    } else {
       $templater->register('item', $profile[item]);
    }
    if(empty($profile["quantity"]))
    {
       $templater->register('quantity', $vbphrase[invoices_qty]);
    } else {
       $templater->register('quantity', $profile[quantity]);
    }
    if(empty($profile["price"]))
    {
       $templater->register('price', $vbphrase[invoices_price]);
    } else {
       $templater->register('price', $profile[price]);
    }
    if(empty($profile["net"]))
    {
       $templater->register('net', $vbphrase[invoices_net]);
    } else {
       $templater->register('net', $profile[net]);
    }
    if(empty($profile["tax"]))
    {
       $templater->register('tax', $vbphrase[invoices_tax]);
    } else {
       $templater->register('tax', $profile[tax]);
    }
    if(empty($profile["taxsum"]))
    {
       $templater->register('taxsum', $vbphrase[invoices_tax_sum]);
    } else {
       $templater->register('taxsum', $profile[taxsum]);
    }
    if(empty($profile["gross"]))
    {
       $templater->register('gross', $vbphrase[invoices_gross]);
    } else {
       $templater->register('gross', $profile[gross]);
    }
    if(empty($profile["subtotal"]))
    {
       $templater->register('subtotal', $vbphrase[invoices_subtotal]);
    } else {
       $templater->register('subtotal', $profile[subtotal]);
    }
    if(empty($profile["discount"]))
    {
       $templater->register('discount', $vbphrase[invoices_discount]);
    } else {
       $templater->register('discount', $profile[discount]);
    }
    if(empty($profile["totalnet"]))
    {
       $templater->register('totalnet', $vbphrase[invoices_total]);
    } else {
       $templater->register('totalnet', $profile[totalnet]);
    }
    if(empty($profile["taxamount"]))
    {
       $templater->register('taxamount', $vbphrase[invoices_tax_amount]);
    } else {
       $templater->register('taxamount', $profile[taxamount]);
    }
    if(empty($profile["shipping"]))
    {
       $templater->register('shipping', $vbphrase[invoices_shipping]);
    } else {
       $templater->register('shipping', $profile[shipping]);
    }
    if(empty($profile["balancedue"]))
    {
       $templater->register('balancedue', $vbphrase[invoices_balance_due]);
    } else {
       $templater->register('balancedue', $profile[balancedue]);
    }
    if(empty($profile["terms"]))
    {
       $templater->register('terms', $vbphrase[invoices_terms_notes]);
    } else {
       $templater->register('terms', $profile[terms]);
    }
    if(empty($profile["phone"]))
    {
       $templater->register('phone', $vbphrase[invoices_phone]);
    } else {
       $templater->register('phone', $profile[phone]);
    }
    if(empty($profile["email"]))
    {
       $templater->register('email', $vbphrase[invoices_email]);
    } else {
       $templater->register('email', $profile[email]);
    }
    if(empty($profile["vatdetails"]))
    {
       $templater->register('vatdetails', $vbphrase[invoices_vatdetails]);
    } else {
       $templater->register('vatdetails', $profile[vatdetails]);
    }
    $templater->register('copyright', $copyright);
    print_output($templater->render());
}

//  #######################################################################
//  ##############################  Update Phrases  #######################
//  #######################################################################
if ($_REQUEST['do'] == 'updatephrases')
{
    if(!($permissions['invoices'] & $vbulletin->bf_ugp['invoices']['access']))
    {
         print_no_permission();
    }
    $vbulletin->input->clean_array_gpc('p', array(
        'id'    	    => TYPE_INT,
        'userid'	    => TYPE_INT,
		'invoice'	    => TYPE_NOHTML,
		'invoicenbr'	=> TYPE_NOHTML,
   		'invoiceto'	    => TYPE_NOHTML,
   		'issued'	    => TYPE_NOHTML,
   		'duedate'	    => TYPE_NOHTML,
		'unpaid'	    => TYPE_NOHTML,
		'paid'	        => TYPE_NOHTML,
   		'overdue'	    => TYPE_NOHTML,
   		'item'	        => TYPE_NOHTML,
   		'quantity'	    => TYPE_NOHTML,
   		'price'	        => TYPE_NOHTML,
		'net'	        => TYPE_NOHTML,
   		'tax'	        => TYPE_NOHTML,
   		'taxsum'	    => TYPE_NOHTML,
   		'gross'	        => TYPE_NOHTML,
   		'subtotal'	    => TYPE_NOHTML,
		'discount'	    => TYPE_NOHTML,
   		'totalnet'	    => TYPE_NOHTML,
   		'taxamount'	    => TYPE_NOHTML,
   		'shipping'	    => TYPE_NOHTML,
   		'balancedue'	=> TYPE_NOHTML,
		'terms'	        => TYPE_NOHTML,
   		'phone'	        => TYPE_NOHTML,
   		'email'	        => TYPE_NOHTML,
   		'vatdetails'	=> TYPE_NOHTML
	));
    // Get Values
    $id = $vbulletin->GPC['id'];
    $userid = $vbulletin->userinfo['userid'];
	$invoice = $db->escape_string($vbulletin->GPC['invoice']);
	$invoicenbr = $db->escape_string($vbulletin->GPC['invoicenbr']);
   	$invoiceto = $db->escape_string($vbulletin->GPC['invoiceto']);
   	$issued = $db->escape_string($vbulletin->GPC['issued']);
   	$duedate = $db->escape_string($vbulletin->GPC['duedate']);
   	$unpaid = $db->escape_string($vbulletin->GPC['unpaid']);
   	$paid = $db->escape_string($vbulletin->GPC['paid']);
   	$overdue = $db->escape_string($vbulletin->GPC['overdue']);
   	$item = $db->escape_string($vbulletin->GPC['item']);
   	$quantity = $db->escape_string($vbulletin->GPC['quantity']);
   	$price = $db->escape_string($vbulletin->GPC['price']);
   	$net = $db->escape_string($vbulletin->GPC['net']);
   	$tax = $db->escape_string($vbulletin->GPC['tax']);
   	$taxsum = $db->escape_string($vbulletin->GPC['taxsum']);
   	$gross = $db->escape_string($vbulletin->GPC['gross']);
   	$subtotal = $db->escape_string($vbulletin->GPC['subtotal']);
   	$discount = $db->escape_string($vbulletin->GPC['discount']);
   	$totalnet = $db->escape_string($vbulletin->GPC['totalnet']);
   	$taxamount = $db->escape_string($vbulletin->GPC['taxamount']);
   	$shipping = $db->escape_string($vbulletin->GPC['shipping']);
   	$balancedue = $db->escape_string($vbulletin->GPC['balancedue']);
   	$terms = $db->escape_string($vbulletin->GPC['terms']);
   	$phone = $db->escape_string($vbulletin->GPC['phone']);
   	$email = $db->escape_string($vbulletin->GPC['email']);
   	$vatdetails = $db->escape_string($vbulletin->GPC['vatdetails']);
    // Get Active user's userid
    $userid = $vbulletin->userinfo['userid'];
    // Check if he has a profile
    $profile = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "invoices_settings WHERE userid=$userid LIMIT 1");
    $profileid = $profile["id"];
    if($profileid != 0)
    {
       // Exists. So update it
       $vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "invoices_settings
                                       SET invoice = '$invoice',
                                           invoicenbr = '$invoicenbr',
                                           invoiceto = '$invoiceto',
                                           issued = '$issued',
                                           duedate = '$duedate',
                                           unpaid = '$unpaid',
                                           paid = '$paid',
                                           overdue = '$overdue',
                                           item = '$item',
                                           quantity = '$quantity',
                                           price = '$price',
                                           net = '$net',
                                           tax = '$tax',
                                           taxsum = '$taxsum',
                                           gross = '$gross',
                                           subtotal = '$subtotal',
                                           discount = '$discount',
                                           totalnet = '$totalnet',
                                           taxamount = '$taxamount',
                                           shipping = '$shipping',
                                           balancedue = '$balancedue',
                                           terms = '$terms',
                                           phone = '$phone',
                                           email = '$email',
                                           vatdetails = '$vatdetails' WHERE id=$profileid");
    } else {
       // Dosen't exists. Save Settings
       $vbulletin->db->query_write("INSERT INTO " . TABLE_PREFIX . "invoices_settings
                                       SET userid = '$userid',
                                           invoice = '$invoice',
                                           invoicenbr = '$invoicenbr',
                                           invoiceto = '$invoiceto',
                                           issued = '$issued',
                                           duedate = '$duedate',
                                           unpaid = '$unpaid',
                                           paid = '$paid',
                                           overdue = '$overdue',
                                           item = '$item',
                                           quantity = '$quantity',
                                           price = '$price',
                                           net = '$net',
                                           tax = '$tax',
                                           taxsum = '$taxsum',
                                           gross = '$gross',
                                           subtotal = '$subtotal',
                                           discount = '$discount',
                                           totalnet = '$totalnet',
                                           taxamount = '$taxamount',
                                           shipping = '$shipping',
                                           balancedue = '$balancedue',
                                           terms = '$terms',
                                           phone = '$phone',
                                           email = '$email',
                                           vatdetails = '$vatdetails'");
       $profileid=$vbulletin->GPC['id'] = $db->insert_id();
    }
    $vbulletin->url="invoices.php?" . $vbulletin->session->vars['sessionurl'] . "do=main";
    eval(print_standard_redirect('invoices_action_ok', true, true));
}

//  #######################################################################
//  ##########################  Add / Edit Customer  ######################
//  #######################################################################
if ($_REQUEST['do'] == 'addeditcustomer')
{
    // Check for access permission
    if(!($permissions["invoices"] & $vbulletin->bf_ugp["invoices"]["access"]))
    {
         print_no_permission();
    }
    // Get Active user's userid
    $userid = $vbulletin->userinfo['userid'];
    // Check Settings
    if(empty($vbulletin->userinfo['inv_company']))
    {
        $vbulletin->url="invoices.php?" . $vbulletin->session->vars['sessionurl'] . "do=settings";
        eval(print_standard_redirect('invoices_settings', true, true));
    }
    // Get Customer id
    $customerid = $vbulletin->input->clean_gpc('r', 'customerid', TYPE_UINT);
    if(!$customerid)
    {
       // Customer id dosen't exists, let's populate default values
       $customer["id"] = 0;
       $customer["userid"] = $vbulletin->userinfo["userid"];
       $customer["name"] = '';
       $customer["address"] = '';
       $customer["city"] = '';
       $customer["state"] = '';
       $customer["country"] = '';
       $customer["zipcode"] = '';
       $customer["phone"] = '';
       $customer["email"] = '';
       $customer["vatdetails"] = '';
       $customer["terms"] = '';
    } else {
       $customer = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "invoices_customers WHERE id=$customerid LIMIT 1");
       // Who is the owner?
       $owner = $customer["userid"];
       // Check if active user is the owner or if he has moderator permissions
       if($owner != $vbulletin->userinfo['userid'])
       {
           // Check failed. Send him back
           print_no_permission();
       }
    }
    // Get Statistics
    getStatistics($userid);
    // Template for User Menu
    $templater = vB_Template::create('invoices_user_menu');
    $usermenu .= $templater->render();
    // Prepare Template
	$templater = vB_Template::create('invoices_addedit_customer');
	$templater->register_page_templates();
    $navbits = construct_navbits(array("invoices.php?" . $vbulletin->session->vars['sessionurl'] => $vbulletin->options['invoices_navbar'], '' => $vbphrase['invoices_addedit_customer']));
  	$navbar = render_navbar_template($navbits);
	$templater->register('navbar', $navbar);
    $templater->register('usermenu', $usermenu);
    $templater->register('statistics', $statistics);
	$templater->register('id', $customer[id]);
    $templater->register('userid', $customer[userid]);
    $templater->register('name', $customer[name]);
    $templater->register('address', $customer[address]);
    $templater->register('city', $customer[city]);
    $templater->register('state', $customer[state]);
    $templater->register('country', $customer[country]);
    $templater->register('zipcode', $customer[zipcode]);
    $templater->register('phone', $customer[phone]);
    $templater->register('vatdetails', $customer[vatdetails]);
    $templater->register('email', $customer[email]);
    $templater->register('terms', $customer[terms]);
    $templater->register('copyright', $copyright);
    print_output($templater->render());
}

//  #######################################################################
//  ##############################  Update Customer  ######################
//  #######################################################################
if ($_REQUEST['do'] == 'updatecustomer')
{
    if(!($permissions['invoices'] & $vbulletin->bf_ugp['invoices']['access']))
    {
         print_no_permission();
    }
    $vbulletin->input->clean_array_gpc('p', array(
        'id'    	    => TYPE_INT,
        'userid'	    => TYPE_INT,
        'name'        	=> TYPE_STR,
        'address'    	=> TYPE_STR,
        'city'        	=> TYPE_STR,
        'state'    	    => TYPE_STR,
        'country'    	=> TYPE_STR,
        'zipcode'       => TYPE_STR,
        'phone'    	    => TYPE_STR,
        'email'        	=> TYPE_STR,
		'vatdetails'	=> TYPE_STR,
        'terms'        	=> TYPE_STR
	));
    // Get Values
    $id = $vbulletin->GPC['id'];
    $userid = $vbulletin->userinfo['userid'];
	$name = $db->escape_string($vbulletin->GPC['name']);
    $address = $db->escape_string($vbulletin->GPC['address']);
    $city = $db->escape_string($vbulletin->GPC['city']);
    $state = $db->escape_string($vbulletin->GPC['state']);
    $country = $db->escape_string($vbulletin->GPC['country']);
    $zipcode = $db->escape_string($vbulletin->GPC['zipcode']);
    $phone = $db->escape_string($vbulletin->GPC['phone']);
    $email = $db->escape_string($vbulletin->GPC['email']);
    $vatdetails = $db->escape_string($vbulletin->GPC['vatdetails']);
    $terms = $db->escape_string($vbulletin->GPC['terms']);
    if($id != 0)
    {
       // Exists. So update it
       $vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "invoices_customers
                                       SET name = '$name',
                                           address = '$address',
                                           city = '$city',
                                           state = '$state',
                                           country = '$country',
                                           zipcode = '$zipcode',
                                           phone = '$phone',
                                           email = '$email',
                                           vatdetails = '$vatdetails',
                                           terms = '$terms' WHERE id=$id");
    } else {
       // Dosen't exists. Save it
       $vbulletin->db->query_write("INSERT INTO " . TABLE_PREFIX . "invoices_customers
                                       SET userid = '$userid',
                                           name = '$name',
                                           address = '$address',
                                           city = '$city',
                                           state = '$state',
                                           country = '$country',
                                           zipcode = '$zipcode',
                                           phone = '$phone',
                                           email = '$email',
                                           vatdetails = '$vatdetails',
                                           terms = '$terms'");
       $vbulletin->db->insert_id();
    }
    $vbulletin->url="invoices.php?" . $vbulletin->session->vars['sessionurl'] . "do=customers";
    eval(print_standard_redirect('invoices_action_ok', true, true));
}

//  #######################################################################
//  ##############################  Delete Customer  ######################
//  #######################################################################
if ($_REQUEST['do'] == 'deletecustomer')
{
    if(!($permissions['invoices'] & $vbulletin->bf_ugp['invoices']['access']))
    {
         print_no_permission();
    }
    // Get Active user's userid
    $userid = $vbulletin->userinfo['userid'];
    // Check Settings
    if(empty($vbulletin->userinfo['inv_company']))
    {
        $vbulletin->url="invoices.php?" . $vbulletin->session->vars['sessionurl'] . "do=settings";
        eval(print_standard_redirect('invoices_settings', true, true));
    }
    // Get Customer id
    $customerid = $vbulletin->input->clean_gpc('r', 'customerid', TYPE_UINT);
    if(!$customerid)
    {
        $vbulletin->url="invoices.php?" . $vbulletin->session->vars['sessionurl'] . "do=customers";
        eval(print_standard_redirect('invoices_invalid_data', true, true));
    }
    $customer = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "invoices_customers WHERE id=$customerid LIMIT 1");
    // Who is the owner?
    $owner = $customer["userid"];
    // Check if active user is the owner or if he has moderator permissions
    if($owner != $vbulletin->userinfo['userid'])
    {
       // Check failed. Send him back
       print_no_permission();
    }
    // Check if customer has invoices
    $invoices = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "invoices_invoices WHERE customerid=$customerid");
    if($invoices)
    {
       $vbulletin->url="invoices.php?" . $vbulletin->session->vars['sessionurl'] . "do=customers";
       eval(print_standard_redirect('invoices_active_invoices', true, true));
    }
    // Go ahead anddelete
    $vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "invoices_customers WHERE id=$customerid");
    // Redirect back
    $vbulletin->url="invoices.php?" . $vbulletin->session->vars['sessionurl'] . "do=customers";
    eval(print_standard_redirect('invoices_action_ok', true, true));
}


//  #######################################################################
//  ################################  View Customer  ######################
//  #######################################################################
if ($_REQUEST['do'] == 'viewcustomer')
{
    if(!($permissions['invoices'] & $vbulletin->bf_ugp['invoices']['access']))
    {
         print_no_permission();
    }
    // Get Active user's userid
    $userid = $vbulletin->userinfo['userid'];
    // Check Settings
    if(empty($vbulletin->userinfo['inv_company']))
    {
        $vbulletin->url="invoices.php?" . $vbulletin->session->vars['sessionurl'] . "do=settings";
        eval(print_standard_redirect('invoices_settings', true, true));
    }
    // Get Customer id
    $customerid = $vbulletin->input->clean_gpc('r', 'customerid', TYPE_UINT);
    if(!$customerid)
    {
        $vbulletin->url="invoices.php?" . $vbulletin->session->vars['sessionurl'] . "do=customers";
        eval(print_standard_redirect('invoices_invalid_data', true, true));
    }
    $customer = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "invoices_customers WHERE id=$customerid LIMIT 1");
    // Who is the owner?
    $owner = $customer["userid"];
    // Check if active user is the owner or if he has moderator permissions
    if($owner != $vbulletin->userinfo['userid'])
    {
       // Check failed. Send him back
       print_no_permission();
    }
    // Check if customer has invoices
    $invoices = $db->query_read("SELECT * FROM " . TABLE_PREFIX . "invoices_invoices WHERE customerid=$customerid");
    while ($invoice = $vbulletin->db->fetch_array($invoices)){
           if($invoice["paid"]=='0000-00-00')
           {
              $invoicepaid = '';

           } else {
              $invoicepaid = vbdate($vbulletin->userinfo['inv_dateformat'], strtotime($invoice["paid"]), '', '');
           }
           // Prepare Templates
		   $templater = vB_Template::create('invoices_customer_invoices');
		   $templater->register('id', $invoice[id]);
           $templater->register('issued', vbdate($vbulletin->userinfo['inv_dateformat'], strtotime($invoice[issued]), '', ''));
           $templater->register('duedate', vbdate($vbulletin->userinfo['inv_dateformat'], strtotime($invoice[duedate]), '', ''));
           $templater->register('paid', $invoicepaid);
           $templater->register('total', getFormatPrice($invoice[total],$vbulletin->userinfo[inv_priceformat]));
           $templater->register('invoicetotal', getFormatPrice($invoice[invoicetotal],$vbulletin->userinfo[inv_priceformat]));
           $templater->register('sent', $invoice[sent]);
           $templater->register('isvoid', $invoice["isvoid"]);
           $templater->register('currency', $vbulletin->userinfo[inv_currency]);
		   $customer_invoices .= $templater->render();
    }
    // Get Statistics
    getStatistics($userid);
    // Template for User Menu
    $templater = vB_Template::create('invoices_user_menu');
    $usermenu .= $templater->render();
    // Page Templates
	$templater = vB_Template::create('invoices_view_customer');
	$templater->register_page_templates();
    $navbits = construct_navbits(array("invoices.php?" . $vbulletin->session->vars['sessionurl'] . ""=> $customer["name"]));
  	$navbar = render_navbar_template($navbits);
	$templater->register('navbar', $navbar);
    $templater->register('id', $customer[id]);
    $templater->register('name', $customer[name]);
    $templater->register('address', $customer[address]);
    $templater->register('city', $customer[city]);
    $templater->register('zipcode', $customer[zipcode]);
    $templater->register('state', $customer[state]);
    $templater->register('country', $customer[country]);
    $templater->register('phone', $customer[phone]);
    $templater->register('email', $customer[email]);
    $templater->register('vatdetails', $customer[vatdetails]);
    $templater->register('terms', $customer[terms]);
    $templater->register('usermenu', $usermenu);
    $templater->register('status', $status);
    $templater->register('statistics', $statistics);
    $templater->register('customer_invoices', $customer_invoices);
    $templater->register('copyright', $copyright);
	print_output($templater->render());
}

//  #######################################################################
//  ###############################  Issue Invoice  #######################
//  #######################################################################
if ($_REQUEST['do'] == 'addinvoice')
{
    // Check for access permission
    if(!($permissions["invoices"] & $vbulletin->bf_ugp["invoices"]["access"]))
    {
         print_no_permission();
    }
    // Get Active user's userid
    $userid = $vbulletin->userinfo['userid'];
    if(empty($vbulletin->userinfo['inv_company']))
    {
        $vbulletin->url="invoices.php?" . $vbulletin->session->vars['sessionurl'] . "do=settings";
        eval(print_standard_redirect('invoices_settings', true, true));
    }
    // Populate default values
    $invoice["id"] = 0;
    $invoice["userid"] = $vbulletin->userinfo["userid"];
    $invoice["customerid"] = 0;
    $invoice["invoiceid"] = $vbulletin->userinfo["inv_nextinvoice"];
    $invoice["issued"] = date('Y-m-d');
    $invoice["duedate"] = date('Y-m-d');
    $invoice["paid"] = 0;
    $invoice["itemname1"] = '';
    $invoice["itemquantity1"] = 0;
    $invoice["itemprice1"] = 0;
    $invoice["itemnet1"] = 0;
    $invoice["itemtax1"] = $vbulletin->userinfo["inv_vat"];
    $invoice["itemtaxamount1"] = 0;
    $invoice["itemtotal1"] = 0;
    $invoice["itemname2"] = '';
    $invoice["itemquantity2"] = 0;
    $invoice["itemprice2"] = 0;
    $invoice["itemnet2"] = 0;
    $invoice["itemtax2"] = $vbulletin->userinfo["inv_vat"];
    $invoice["itemtaxamount2"] = 0;
    $invoice["itemtotal2"] = 0;
    $invoice["itemname3"] = '';
    $invoice["itemquantity3"] = 0;
    $invoice["itemprice3"] = 0;
    $invoice["itemnet3"] = 0;
    $invoice["itemtax3"] = $vbulletin->userinfo["inv_vat"];
    $invoice["itemtaxamount3"] = 0;
    $invoice["itemtotal3"] = 0;
    $invoice["itemname4"] = '';
    $invoice["itemquantity4"] = 0;
    $invoice["itemprice4"] = 0;
    $invoice["itemnet4"] = 0;
    $invoice["itemtax4"] = $vbulletin->userinfo["inv_vat"];
    $invoice["itemtaxamount4"] = 0;
    $invoice["itemtotal4"] = 0;
    $invoice["itemname5"] = '';
    $invoice["itemquantity5"] = 0;
    $invoice["itemprice5"] = 0;
    $invoice["itemnet5"] = 0;
    $invoice["itemtax5"] = $vbulletin->userinfo["inv_vat"];
    $invoice["itemtaxamount5"] = 0;
    $invoice["itemtotal5"] = 0;
    $invoice["subtotal"] = 0;
    $invoice["discount"] = 0;
    $invoice["total"] = 0;
    $invoice["taxamount"] = 0;
    $invoice["shipping"] = 0;
    $invoice["invoicetotal"] = 0;
    $invoice["terms"] = '';
    $invoice["transid"] = '';
    $invoice["sent"] = 0;
    // Get Statistics
    getStatistics($userid);
    // Template for User Menu
    $templater = vB_Template::create('invoices_user_menu');
    $usermenu .= $templater->render();
    // Get Customer id
    $customerid = $vbulletin->input->clean_gpc('r', 'customerid', TYPE_UINT);
    if(!$customerid)
    {
        $customerid = $invoice["customerid"];
    }
    if($customerid != 0)
    {
        $customer = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "invoices_customers WHERE id=$customerid LIMIT 1");
    }
    // Get Customer list
    $customerlist = getCustomers($userid,$customerid);
    // Prepare Template
	$templater = vB_Template::create('invoices_add_invoice');
	$templater->register_page_templates();
    $navbits = construct_navbits(array("invoices.php?" . $vbulletin->session->vars['sessionurl'] => $vbulletin->options['invoices_navbar'], '' => $vbphrase['invoices_addedit_invoice']));
  	$navbar = render_navbar_template($navbits);
	$templater->register('navbar', $navbar);
    $templater->register('usermenu', $usermenu);
    $templater->register('statistics', $statistics);
    $templater->register('customerlist', $customerlist);
    $templater->register('cname', $customer[name]);
    $templater->register('caddress', $customer[address]);
    $templater->register('ccity', $customer[city]);
    $templater->register('czipcode', $customer[zipcode]);
    $templater->register('cstate', $customer[state]);
    $templater->register('ccountry', $customer[country]);
    $templater->register('cphone', $customer[phone]);
    $templater->register('cemail', $customer[email]);
    $templater->register('cvatdetails', $customer[vatdetails]);
    $templater->register('company', $vbulletin->userinfo[inv_company]);
    $templater->register('logo', $vbulletin->userinfo[inv_logo]);
    $templater->register('currency', $vbulletin->userinfo[inv_currency]);
	$templater->register('id', $invoice[id]);
    $templater->register('userid', $invoice[userid]);
    $templater->register('customerid', $customer[id]);
    $templater->register('invoiceid', $invoice[invoiceid]);
    $templater->register('issued', $invoice[issued]);
    $templater->register('duedate', $invoice[duedate]);
    $templater->register('paid', $invoice[paid]);
    $templater->register('itemname1', $invoice[itemname1]);
    $templater->register('itemquantity1', $invoice[itemquantity1]);
    $templater->register('itemprice1', $invoice[itemprice1]);
    $templater->register('itemnet1', $invoice[itemnet1]);
    $templater->register('itemtax1', $invoice[itemtax1]);
    $templater->register('itemtaxamount1', $invoice[itemtaxamount1]);
    $templater->register('itemtotal1', $invoice[itemtotal1]);
    $templater->register('itemname2', $invoice[itemname2]);
    $templater->register('itemquantity2', $invoice[itemquantity2]);
    $templater->register('itemprice2', $invoice[itemprice2]);
    $templater->register('itemnet2', $invoice[itemnet2]);
    $templater->register('itemtax2', $invoice[itemtax2]);
    $templater->register('itemtaxamount2', $invoice[itemtaxamount2]);
    $templater->register('itemtotal2', $invoice[itemtotal2]);
    $templater->register('itemname3', $invoice[itemname3]);
    $templater->register('itemquantity3', $invoice[itemquantity3]);
    $templater->register('itemprice3', $invoice[itemprice3]);
    $templater->register('itemnet3', $invoice[itemnet3]);
    $templater->register('itemtax3', $invoice[itemtax3]);
    $templater->register('itemtaxamount3', $invoice[itemtaxamount3]);
    $templater->register('itemtotal3', $invoice[itemtotal3]);
    $templater->register('itemname4', $invoice[itemname4]);
    $templater->register('itemquantity4', $invoice[itemquantity4]);
    $templater->register('itemprice4', $invoice[itemprice4]);
    $templater->register('itemnet4', $invoice[itemnet4]);
    $templater->register('itemtax4', $invoice[itemtax4]);
    $templater->register('itemtaxamount4', $invoice[itemtaxamount4]);
    $templater->register('itemtotal4', $invoice[itemtotal4]);
    $templater->register('itemname5', $invoice[itemname5]);
    $templater->register('itemquantity5', $invoice[itemquantity5]);
    $templater->register('itemprice5', $invoice[itemprice5]);
    $templater->register('itemnet5', $invoice[itemnet5]);
    $templater->register('itemtax5', $invoice[itemtax5]);
    $templater->register('itemtaxamount5', $invoice[itemtaxamount5]);
    $templater->register('itemtotal5', $invoice[itemtotal5]);
    $templater->register('subtotal', $invoice[subtotal]);
    $templater->register('discount', $invoice[discount]);
    $templater->register('total', $invoice[total]);
    $templater->register('taxamount', $invoice[taxamount]);
    $templater->register('shipping', $invoice[shipping]);
    $templater->register('invoicetotal', $invoice[invoicetotal]);
    $templater->register('terms', $customer[terms]);
    $templater->register('transid', $invoice[transid]);
    $templater->register('sent', $invoice[sent]);
    $templater->register('copyright', $copyright);
    print_output($templater->render());
}

//  #######################################################################
//  ################################  Save Invoice  #######################
//  #######################################################################
if ($_REQUEST['do'] == 'saveinvoice')
{
    if(!($permissions['invoices'] & $vbulletin->bf_ugp['invoices']['access']))
    {
         print_no_permission();
    }
    $vbulletin->input->clean_array_gpc('p', array(
        'id'    	     => TYPE_INT,
        'userid'	     => TYPE_INT,
        'customerid'     => TYPE_INT,
        'invoiceid'	     => TYPE_INT,
        'issued'       	 => TYPE_STR,
        'duedate'    	 => TYPE_STR,
        'itemname1'      => TYPE_STR,
        'itemquantity1'  => TYPE_INT,
        'itemprice1'     => TYPE_NUM,
        'itemnet1'       => TYPE_NUM,
        'itemtax1'    	 => TYPE_NUM,
        'itemtaxamount1' => TYPE_NUM,
		'itemtotal1'	 => TYPE_NUM,
        'itemname2'      => TYPE_STR,
        'itemquantity2'  => TYPE_INT,
        'itemprice2'     => TYPE_NUM,
        'itemnet2'       => TYPE_NUM,
        'itemtax2'    	 => TYPE_NUM,
        'itemtaxamount2' => TYPE_NUM,
		'itemtotal2'	 => TYPE_NUM,
        'itemname3'      => TYPE_STR,
        'itemquantity3'  => TYPE_INT,
        'itemprice3'     => TYPE_NUM,
        'itemnet3'       => TYPE_NUM,
        'itemtax3'    	 => TYPE_NUM,
        'itemtaxamount3' => TYPE_NUM,
		'itemtotal3'	 => TYPE_NUM,
        'itemname4'      => TYPE_STR,
        'itemquantity4'  => TYPE_INT,
        'itemprice4'     => TYPE_NUM,
        'itemnet4'       => TYPE_NUM,
        'itemtax4'    	 => TYPE_NUM,
        'itemtaxamount4' => TYPE_NUM,
		'itemtotal4'	 => TYPE_NUM,
        'itemname5'      => TYPE_STR,
        'itemquantity5'  => TYPE_INT,
        'itemprice5'     => TYPE_NUM,
        'itemnet5'       => TYPE_NUM,
        'itemtax5'    	 => TYPE_NUM,
        'itemtaxamount5' => TYPE_NUM,
		'itemtotal5'	 => TYPE_NUM,
        'subtotal'       => TYPE_NUM,
        'discount'       => TYPE_NUM,
        'totalnet'       => TYPE_NUM,
        'taxamount'    	 => TYPE_NUM,
        'shipping'       => TYPE_NUM,
		'invoicetotal'	 => TYPE_NUM,
        'terms'        	 => TYPE_STR
	));
    // Get Values
    $userid = $vbulletin->userinfo['userid'];
    $customerid = $vbulletin->GPC['customerid'];
    echo $customerid;
    $invoiceid = $vbulletin->GPC['invoiceid'];
    $issued = $db->escape_string($vbulletin->GPC['issued']);
    $duedate = $db->escape_string($vbulletin->GPC['duedate']);
    $itemname1 = $db->escape_string($vbulletin->GPC['itemname1']);
    $itemquantity1 = $vbulletin->GPC['itemquantity1'];
    $itemprice1 = $vbulletin->GPC['itemprice1'];
    $itemnet1 = $vbulletin->GPC['itemnet1'];
    $itemtax1 = $vbulletin->GPC['itemtax1'];
    $itemtaxamount1 = $vbulletin->GPC['itemtaxamount1'];
    $itemtotal1 = $vbulletin->GPC['itemtotal1'];
    $itemname2 = $db->escape_string($vbulletin->GPC['itemname2']);
    $itemquantity2 = $vbulletin->GPC['itemquantity2'];
    $itemprice2 = $vbulletin->GPC['itemprice2'];
    $itemnet2 = $vbulletin->GPC['itemnet2'];
    $itemtax2 = $vbulletin->GPC['itemtax2'];
    $itemtaxamount2 = $vbulletin->GPC['itemtaxamount2'];
    $itemtotal2 = $vbulletin->GPC['itemtotal2'];
    $itemname3 = $db->escape_string($vbulletin->GPC['itemname3']);
    $itemquantity3 = $vbulletin->GPC['itemquantity3'];
    $itemprice3 = $vbulletin->GPC['itemprice3'];
    $itemnet3 = $vbulletin->GPC['itemnet3'];
    $itemtax3 = $vbulletin->GPC['itemtax3'];
    $itemtaxamount3 = $vbulletin->GPC['itemtaxamount3'];
    $itemtotal3 = $vbulletin->GPC['itemtotal3'];
    $itemname4 = $db->escape_string($vbulletin->GPC['itemname4']);
    $itemquantity4 = $vbulletin->GPC['itemquantity4'];
    $itemprice4 = $vbulletin->GPC['itemprice4'];
    $itemnet4 = $vbulletin->GPC['itemnet4'];
    $itemtax4 = $vbulletin->GPC['itemtax4'];
    $itemtaxamount4 = $vbulletin->GPC['itemtaxamount4'];
    $itemtotal4 = $vbulletin->GPC['itemtotal4'];
    $itemname5 = $db->escape_string($vbulletin->GPC['itemname5']);
    $itemquantity5 = $vbulletin->GPC['itemquantity5'];
    $itemprice5 = $vbulletin->GPC['itemprice5'];
    $itemnet5 = $vbulletin->GPC['itemnet5'];
    $itemtax5 = $vbulletin->GPC['itemtax5'];
    $itemtaxamount5 = $vbulletin->GPC['itemtaxamount5'];
    $itemtotal5 = $vbulletin->GPC['itemtotal5'];
    $subtotal = $vbulletin->GPC['subtotal'];
    $discount = $vbulletin->GPC['discount'];
    $total = $vbulletin->GPC['totalnet'];
    $taxamount = $vbulletin->GPC['taxamount'];
    $shipping = $vbulletin->GPC['shipping'];
    $invoicetotal = $vbulletin->GPC['invoicetotal'];
    $terms = $db->escape_string($vbulletin->GPC['terms']);
    $terms = str_replace('\n','<br>',$terms);
    $today = date("dMy");
    $license_string = generate_transaction_key();
    $transkey = $vbulletin->userinfo['username']."-".$today."-".$license_string;
    $transid = substr($transkey, 0, 32);
    // Save Invoice
    $vbulletin->db->query_write("INSERT INTO " . TABLE_PREFIX . "invoices_invoices
                                        SET userid = '$userid',
                                            customerid = '$customerid',
                                            invoiceid = '$invoiceid',
                                            issued = '$issued',
                                            duedate = '$duedate',
                                            paid = '0000-00-00',
                                            itemname1 = '$itemname1',
                                            itemquantity1 = '$itemquantity1',
                                            itemprice1 = '$itemprice1',
                                            itemnet1 = '$itemnet1',
                                            itemtax1 = '$itemtax1',
                                            itemtaxamount1 = '$itemtaxamount1',
                                            itemtotal1 = '$itemtotal1',
                                            itemname2 = '$itemname2',
                                            itemquantity2 = '$itemquantity2',
                                            itemprice2 = '$itemprice2',
                                            itemnet2 = '$itemnet2',
                                            itemtax2 = '$itemtax2',
                                            itemtaxamount2 = '$itemtaxamount2',
                                            itemtotal2 = '$itemtotal2',
                                            itemname3 = '$itemname3',
                                            itemquantity3 = '$itemquantity3',
                                            itemprice3 = '$itemprice3',
                                            itemnet3 = '$itemnet3',
                                            itemtax3 = '$itemtax3',
                                            itemtaxamount3 = '$itemtaxamount3',
                                            itemtotal3 = '$itemtotal3',
                                            itemname4 = '$itemname4',
                                            itemquantity4 = '$itemquantity4',
                                            itemprice4 = '$itemprice4',
                                            itemnet4 = '$itemnet4',
                                            itemtax4 = '$itemtax4',
                                            itemtaxamount4 = '$itemtaxamount4',
                                            itemtotal4 = '$itemtotal4',
                                            itemname5 = '$itemname5',
                                            itemquantity5 = '$itemquantity5',
                                            itemprice5 = '$itemprice5',
                                            itemnet5 = '$itemnet5',
                                            itemtax5 = '$itemtax5',
                                            itemtaxamount5 = '$itemtaxamount5',
                                            itemtotal5 = '$itemtotal5',
                                            subtotal = '$subtotal',
                                            discount = '$discount',
                                            total = '$total',
                                            taxamount = '$taxamount',
                                            shipping = '$shipping',
                                            invoicetotal = '$invoicetotal',
                                            terms = '$terms',
                                            transid = '$transid',
                                            isvoid = '0',
                                            sent = '0'");
    $vbulletin->db->insert_id();
    // Update Next invoice number
    $vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "user SET inv_nextinvoice=inv_nextinvoice+1 WHERE userid=$userid");
    // Redirect back
    $vbulletin->url="invoices.php?" . $vbulletin->session->vars['sessionurl'] . "do=main";
    eval(print_standard_redirect('invoices_action_ok', true, true));
}

//  #######################################################################
//  #################################  PDF Invoice  #######################
//  #######################################################################
if ($_REQUEST['do'] == 'invoice')
{
    // Check for access permission
    if(!($permissions["invoices"] & $vbulletin->bf_ugp["invoices"]["access"]))
    {
         print_no_permission();
    }
    // Get Active user's userid
    $userid = $vbulletin->userinfo['userid'];
    if(empty($vbulletin->userinfo['inv_company']))
    {
        $vbulletin->url="invoices.php?" . $vbulletin->session->vars['sessionurl'] . "do=settings";
        eval(print_standard_redirect('invoices_settings', true, true));
    }
    // Get Invoice
    $invoiceid = $vbulletin->input->clean_gpc('r', 'invoiceid', TYPE_UINT);
    if(!$invoiceid)
    {
        $vbulletin->url="invoices.php?" . $vbulletin->session->vars['sessionurl'] . "do=main";
        eval(print_standard_redirect('invoices_invalid_data', true, true));
    }
    // Get Action
    $action = $vbulletin->input->clean_gpc('r', 'action', TYPE_UINT);
    if(!$action)
    {
        $action = 2;
    }
    if($action == 5)
    {
           // Mark it as Paid
       $today = date("Y-m-d");
       $vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "invoices_invoices SET paid='$today' WHERE id=$invoiceid");
    }
    $invoice = $vbulletin->db->query_first("SELECT * FROM " . TABLE_PREFIX . "invoices_invoices WHERE id=$invoiceid LIMIT 1");
    if(!$invoice)
    {
        $vbulletin->url="invoices.php?" . $vbulletin->session->vars['sessionurl'] . "do=main";
        eval(print_standard_redirect('invoices_invalid_data', true, true));
    }
    $issued = vbdate($vbulletin->userinfo['inv_dateformat'], strtotime($invoice[issued]), '', '');
    $duedate = vbdate($vbulletin->userinfo['inv_dateformat'], strtotime($invoice[duedate]), '', '');
    // Get Customer data
    $customerid = $invoice["customerid"];
    $customer = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "invoices_customers WHERE id=$customerid LIMIT 1");
    // Get User Settings
    $settings = $vbulletin->db->query_first("SELECT * FROM " . TABLE_PREFIX . "invoices_settings WHERE userid=$userid LIMIT 1");
    if($invoice["paid"]=='0000-00-00')
    {
       $today = date("Y-m-d");
       if($invoice["duedate"]>$today)
       {
          $status = $settings["unpaid"];
       } else {
          $status = $settings["overdue"];
       }
    } else {
       $status = $settings["paid"];
    }
    // create new PDF document
    $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, $vbulletin->userinfo["inv_charset"], false);

    $pdf->SetHeaderData('', '', $settings["invoice"], '');

    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

    // set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

    //set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    //set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

    //set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    // set some language dependent data:
    $lg = Array();
    $lg['a_meta_charset'] = $vbulletin->userinfo["inv_charset"];
    $lg['a_meta_dir'] = $vbulletin->userinfo["inv_chardir"];
    $lg['a_meta_language'] = '';
    $lg['w_page'] = 'page';

    //set some language-dependent strings
    $pdf->setLanguageArray($lg);

    // set default font subsetting mode
    $pdf->setFontSubsetting(true);

    // Set font
    $pdf->SetFont('helvetica', '', 10, '', true);

    // Add a page
    $pdf->AddPage();

    // Set some content to print
    if(!empty($vbulletin->userinfo["inv_logo"]))
    {
        $ext = strtoupper(get_ext($vbulletin->userinfo["inv_logo"]));
        $pdf->Image('invoices/logos/'.$vbulletin->userinfo["inv_logo"].'', 16, 17, 0, 0, $ext, '', 'center', false, 150, 'relative', false, false, 0, true, false, true);
    }
    $html = '<br /><br /><br /><br /><br /><table width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td>
            '.$vbulletin->userinfo["inv_company"].'</td></tr></table>
            <table width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td height="30">&nbsp;</td></tr></table>
            <table width="100%" cellspacing="0" cellpadding="0" border="0">
            <tr><td width="30%">
            '.$settings["invoicenbr"].'</td>
            <td width="70%">
            '.$invoice["invoiceid"].'&nbsp;-&nbsp;<b>'.$status.'</b></td></tr>
            <tr><td align="left">
            '.$settings["issued"].'</td>
            <td align="left">
            '.$issued.'</td></tr>
            <tr><td align="left">
            '.$settings["duedate"].'</td>
            <td align="left">
            '.$duedate.'</td></tr></table>
            <table width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td height="30">&nbsp;</td></tr></table>
            <table width="100%" cellspacing="0" cellpadding="0" border="0">
            <tr><td><b><u>'.$settings["invoiceto"].'</u></b></td></tr>
            <tr><td align="left">
            '.$customer["name"].'<br />';
    if(!empty($customer["address"]))
    {
        $html .= $customer["address"].'<br />';
    }
    if(!empty($customer["city"]))
    {
        $html .= $customer["city"].',&nbsp;';
    }
    if(!empty($customer["state"]))
    {
        $html .= $customer["state"].',&nbsp;';
    }
    if(!empty($customer["zipcode"]))
    {
        $html .= $customer["zipcode"];
    }
    $html .= '<br />';
    if(!empty($customer["country"]))
    {
        $html .= $customer["country"].'<br />';
    }
    if(!empty($customer["phone"]))
    {
        $html .= $settings["phone"].':&nbsp;'.$customer["phone"].'<br />';
    }
    if(!empty($customer["email"]))
    {
        $html .= $settings["email"].':&nbsp;<a href="mailto:'.$customer["email"].'">'.$customer["email"].'</a><br />';
    }
    if(!empty($customer["vatdetails"]))
    {
        $html .= $settings["vatdetails"].':&nbsp;'.$customer["vatdetails"].'<br />';
    }
    $html .= '</td></tr></table>';
    $html .= '<table width="100%" cellspacing="2" cellpadding="2" border="1"><tr>
             <td width="40%" align="center" bgcolor="#CFCFCF">'.$settings["item"].'</td>
             <td width="5%" align="center" bgcolor="#CFCFCF">'.$settings["quantity"].'</td>
             <td width="10%" align="center" bgcolor="#CFCFCF">'.$settings["price"].'</td>
             <td width="10%" align="center" bgcolor="#CFCFCF">'.$settings["net"].'</td>
             <td width="10%" align="center" bgcolor="#CFCFCF">'.$settings["tax"].'</td>
             <td width="10%" align="center" bgcolor="#CFCFCF">'.$settings["taxsum"].'</td>
             <td width="15%" align="center" bgcolor="#CFCFCF">'.$settings["gross"].'</td></tr><tr>
             <td>'.$invoice["itemname1"].'</td>
             <td align="right">'.$invoice["itemquantity1"].'</td>
             <td align="right">'.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["itemprice1"],$vbulletin->userinfo["inv_priceformat"]).'</td>
             <td align="right">'.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["itemnet1"],$vbulletin->userinfo["inv_priceformat"]).'</td>
             <td align="right">'.getFormatPrice($invoice["itemtax1"],$vbulletin->userinfo["inv_priceformat"]).'%</td>
             <td align="right">'.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["itemtaxamount1"],$vbulletin->userinfo["inv_priceformat"]).'</td>
             <td align="right">'.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["itemtotal1"],$vbulletin->userinfo["inv_priceformat"]).'</td></tr>';
    if(!empty($invoice["itemname2"]))
    {
        $html .= '<tr><td>'.$invoice["itemname2"].'</td>
                  <td align="right">'.$invoice["itemquantity2"].'</td>
                  <td align="right">'.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["itemprice2"],$vbulletin->userinfo["inv_priceformat"]).'</td>
                  <td align="right">'.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["itemnet2"],$vbulletin->userinfo["inv_priceformat"]).'</td>
                  <td align="right">'.getFormatPrice($invoice["itemtax2"],$vbulletin->userinfo["inv_priceformat"]).'%</td>
                  <td align="right">'.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["itemtaxamount2"],$vbulletin->userinfo["inv_priceformat"]).'</td>
                  <td align="right">'.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["itemtotal2"],$vbulletin->userinfo["inv_priceformat"]).'</td></tr>';
    }
    if(!empty($invoice["itemname3"]))
    {
        $html .= '<tr><td>'.$invoice["itemname3"].'</td>
                  <td align="right">'.$invoice["itemquantity3"].'</td>
                  <td align="right">'.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["itemprice3"],$vbulletin->userinfo["inv_priceformat"]).'</td>
                  <td align="right">'.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["itemnet3"],$vbulletin->userinfo["inv_priceformat"]).'</td>
                  <td align="right">'.getFormatPrice($invoice["itemtax3"],$vbulletin->userinfo["inv_priceformat"]).'%</td>
                  <td align="right">'.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["itemtaxamount3"],$vbulletin->userinfo["inv_priceformat"]).'</td>
                  <td align="right">'.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["itemtotal3"],$vbulletin->userinfo["inv_priceformat"]).'</td></tr>';
    }
    if(!empty($invoice["itemname4"]))
    {
        $html .= '<tr><td>'.$invoice["itemname4"].'</td>
                  <td align="right">'.$invoice["itemquantity4"].'</td>
                  <td align="right">'.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["itemprice4"],$vbulletin->userinfo["inv_priceformat"]).'</td>
                  <td align="right">'.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["itemnet4"],$vbulletin->userinfo["inv_priceformat"]).'</td>
                  <td align="right">'.getFormatPrice($invoice["itemtax4"],$vbulletin->userinfo["inv_priceformat"]).'%</td>
                  <td align="right">'.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["itemtaxamount4"],$vbulletin->userinfo["inv_priceformat"]).'</td>
                  <td align="right">'.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["itemtotal4"],$vbulletin->userinfo["inv_priceformat"]).'</td></tr>';
    }
    if(!empty($invoice["itemname5"]))
    {
        $html .= '<tr><td>'.$invoice["itemname5"].'</td>
                  <td align="right">'.$invoice["itemquantity5"].'</td>
                  <td align="right">'.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["itemprice5"],$vbulletin->userinfo["inv_priceformat"]).'</td>
                  <td align="right">'.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["itemnet5"],$vbulletin->userinfo["inv_priceformat"]).'</td>
                  <td align="right">'.getFormatPrice($invoice["itemtax5"],$vbulletin->userinfo["inv_priceformat"]).'%</td>
                  <td align="right">'.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["itemtaxamount5"],$vbulletin->userinfo["inv_priceformat"]).'</td>
                  <td align="right">'.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["itemtotal5"],$vbulletin->userinfo["inv_priceformat"]).'</td>
                  </tr>';
    }
    $html .= '</table>
              <table width="100%" cellspacing="0" cellpadding="0" border="0">
              <tr><td width="65%">&nbsp;</td>
              <td width="35%" align="right"><table width="100%" cellspacing="2" cellpadding="2" border="1">
              <tr><td width="58%" align="right" bgcolor="#EBEBEB">
              '.$settings["subtotal"].'&nbsp;</td>
              <td width="42%" align="right">
              '.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["subtotal"],$vbulletin->userinfo["inv_priceformat"]).'</td></tr>
              <tr><td align="right" bgcolor="#EBEBEB">
              '.$settings["discount"].'&nbsp;</td>
              <td align="right">
              '.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["discount"],$vbulletin->userinfo["inv_priceformat"]).'</td></tr>
              <tr><td align="right" bgcolor="#EBEBEB">
              '.$settings["totalnet"].'&nbsp;</td>
              <td align="right">
              '.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["total"],$vbulletin->userinfo["inv_priceformat"]).'</td></tr>
              <tr><td align="right" bgcolor="#EBEBEB">
              '.$settings["taxamount"].'&nbsp;</td>
              <td align="right">
              '.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["taxamount"],$vbulletin->userinfo["inv_priceformat"]).'</td></tr>
              <tr><td align="right" bgcolor="#EBEBEB">
              '.$settings["shipping"].'&nbsp;</td>
              <td align="right">
              '.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["shipping"],$vbulletin->userinfo["inv_priceformat"]).'</td></tr>
              <tr><td align="right" bgcolor="#CFCFCF"><b>
              '.$settings["balancedue"].'</b>&nbsp;</td>
              <td align="right" bgcolor="#CFCFCF"><b>
              '.$vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["invoicetotal"],$vbulletin->userinfo["inv_priceformat"]).'</b></td></tr>
              </table></td></tr></table>
              <table width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td>&nbsp;</td></tr></table>
              <table width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td><b><u>
              '.$settings["terms"].'</u></b><br />'.$invoice["terms"].'</td></tr></table>';
    // Print text using writeHTMLCell()
    $pdf->writeHTMLCell($w=0, $h=0, $x='', $y='', $html, $border=0, $ln=1, $fill=0, $reseth=true, $align='', $autopadding=true);
    // Close and output PDF document
    if($action == 1)
    {
       $pdf->Output($invoice["transid"].'.pdf', 'F');
       $bodytext = preg_replace("/\r|\n/", '', $settings["emailbody1"]);
       $bodytext .= '<br />';
       $bodytext .= preg_replace("/\r|\n/", '', $settings["emailfooter"]);
       $bodytext = str_replace('@customer', $customer["name"], $bodytext);
       $bodytext = str_replace('@invoice', $invoice["invoiceid"], $bodytext);
       $amount = $vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["invoicetotal"],$vbulletin->userinfo["inv_priceformat"]);
       $bodytext = str_replace('@amount', $amount, $bodytext);
       // Initial Email
       $mail = new PHPMailer();
       $mail->From = $vbulletin->userinfo["inv_fromemail"];
       $mail->FromName = $vbulletin->userinfo["invo_fromname"];
       $mail->AddAddress($customer["email"]);
       $mail->Subject = $settings["emailtitle1"];
       $mail->Body = $bodytext;
       $mail->AltBody = $bodytext;
       $mail->AddAttachment($invoice["transid"].'.pdf', $invoice["transid"].'.pdf', 'base64', 'application/pdf');
       $mail->IsHTML(true);
       $mail->CharSet = $vbulletin->userinfo["inv_charset"];
       $mail->IsMail();
       $mail->Send();
       unlink("$invoice[transid].pdf");
       $vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "invoices_invoices SET sent=1 WHERE id=$invoiceid");
    }
    if($action == 2)
    {
       $pdf->Output($invoice["transid"].'.pdf', 'I');
    }
    if($action == 3)
    {
       $pdf->Output($invoice["transid"].'.pdf', 'D');
    }
    if($action == 4)
    {
       $pdf->Output($invoice["transid"].'.pdf', 'F');
       // Overdue Email
       $bodytext = preg_replace("/\r|\n/", '', $settings["emailbody3"]);
       $bodytext .= '<br />';
       $bodytext .= preg_replace("/\r|\n/", '', $settings["emailfooter"]);
       $bodytext = str_replace('@customer', $customer["name"], $bodytext);
       $bodytext = str_replace('@invoice', $invoice["invoiceid"], $bodytext);
       $amount = $vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["invoicetotal"],$vbulletin->userinfo["inv_priceformat"]);
       $bodytext = str_replace('@amount', $amount, $bodytext);
       $mail = new PHPMailer();
       $mail->From = $vbulletin->userinfo["inv_fromemail"];
       $mail->FromName = $vbulletin->userinfo["invo_fromname"];
       $mail->AddAddress($customer["email"]);
       $mail->Subject = $settings["emailtitle3"];
       $mail->Body = $bodytext;
       $mail->AltBody = $bodytext;
       $mail->AddAttachment($invoice["transid"].'.pdf', $invoice["transid"].'.pdf', 'base64', 'application/pdf');
       $mail->IsHTML(true);
       $mail->CharSet = $vbulletin->userinfo["inv_charset"];
       $mail->IsMail();
       $mail->Send();
       unlink("$invoice[transid].pdf");
    }
    if($action == 5)
    {
       $pdf->Output($invoice["transid"].'.pdf', 'F');
       $bodytext = preg_replace("/\r|\n/", '', $settings["emailbody2"]);
       $bodytext .= '<br />';
       $bodytext .= preg_replace("/\r|\n/", '', $settings["emailfooter"]);
       $bodytext = str_replace('@customer', $customer["name"], $bodytext);
       $bodytext = str_replace('@invoice', $invoice["invoiceid"], $bodytext);
       $amount = $vbulletin->userinfo["inv_currency"].getFormatPrice($invoice["invoicetotal"],$vbulletin->userinfo["inv_priceformat"]);
       $bodytext = str_replace('@amount', $amount, $bodytext);
       $mail = new PHPMailer();
       $mail->From = $vbulletin->userinfo["inv_fromemail"];
       $mail->FromName = $vbulletin->userinfo["invo_fromname"];
       $mail->AddAddress($customer["email"]);
       $mail->Subject = $settings["emailtitle2"];
       $mail->Body = $bodytext;
       $mail->AltBody = $bodytext;
       $mail->AddAttachment($invoice["transid"].'.pdf', $invoice["transid"].'.pdf', 'base64', 'application/pdf');
       $mail->IsHTML(true);
       $mail->CharSet = $vbulletin->userinfo["inv_charset"];
       $mail->IsMail();
       $mail->Send();
       unlink("$invoice[transid].pdf");
    }
    // Redirect back
    $vbulletin->url="invoices.php?" . $vbulletin->session->vars['sessionurl'] . "do=main";
    eval(print_standard_redirect('invoices_action_ok', true, true));
}

//  #######################################################################
//  ##############################  Invoice Status  #######################
//  #######################################################################
if ($_REQUEST['do'] == 'changestatus')
{
    // Check for access permission
    if(!($permissions["invoices"] & $vbulletin->bf_ugp["invoices"]["access"]))
    {
         print_no_permission();
    }
    // Get Active user's userid
    $userid = $vbulletin->userinfo['userid'];
    if(empty($vbulletin->userinfo['inv_company']))
    {
        $vbulletin->url="invoices.php?" . $vbulletin->session->vars['sessionurl'] . "do=settings";
        eval(print_standard_redirect('invoices_settings', true, true));
    }
    // Get Invoice
    $invoiceid = $vbulletin->input->clean_gpc('r', 'invoiceid', TYPE_UINT);
    if(!$invoiceid)
    {
        $vbulletin->url="invoices.php?" . $vbulletin->session->vars['sessionurl'] . "do=main";
        eval(print_standard_redirect('invoices_invalid_data', true, true));
    }
    // Get Status
    $status = $vbulletin->input->clean_gpc('r', 'status', TYPE_UINT);
    if(!$status)
    {
        $vbulletin->url="invoices.php?" . $vbulletin->session->vars['sessionurl'] . "do=main";
        eval(print_standard_redirect('invoices_invalid_data', true, true));
    }
    $invoice = $vbulletin->db->query_first("SELECT * FROM " . TABLE_PREFIX . "invoices_invoices WHERE id=$invoiceid LIMIT 1");
    if(!$invoice)
    {
        $vbulletin->url="invoices.php?" . $vbulletin->session->vars['sessionurl'] . "do=main";
        eval(print_standard_redirect('invoices_invalid_data', true, true));
    }
    if($status == 1)
    {
       // Mark it as UnPaid
       $vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "invoices_invoices SET paid='0000-00-00' WHERE id=$invoiceid");
    }
    if($status == 2)
    {
       // Mark it as Void
       $vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "invoices_invoices SET isvoid=1 WHERE id=$invoiceid");
    }
    if($status == 3)
    {
       // Reactivate
       $vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "invoices_invoices SET isvoid=0 WHERE id=$invoiceid");
    }
    // Redirect back
    $vbulletin->url="invoices.php?" . $vbulletin->session->vars['sessionurl'] . "do=main";
    eval(print_standard_redirect('invoices_action_ok', true, true));
}
?>
