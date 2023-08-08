<?php
if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

use OIX\Util\Logger;

class Auth extends CI_Controller {
  protected $logger;

  function __construct() {
    parent::__construct();

    $this->load->helper(['form', 'url', 'security']);
    $this->load->library(['form_validation']);
    if(version_compare(CI_VERSION, '2.1.0', '<')) {
      $this->load->library('security');
    }
    $this->load->library('user_agent');
    $this->load->library('tank_auth');
    $this->lang->load('tank_auth');
    $this->load->model('CreditListings');
    $this->load->model('IncentivePrograms');
    $this->load->model('DmaAccounts');
    $this->load->model('Members_Model');
    $this->load->model('Email_Model');
    $this->load->model('tank_auth/Users');

    $this->logger = Logger::getInstance();
    //$this->logger->error("Test Error Message!");
    //throw new \Exception("Fsck your couch, I'm Rick James!");
    $this->redisIsAvailable = false;
    try {
      $this->redis = new Predis\Client([
                                           'scheme' => $this->config->item('AWS_elasticache_scheme'),
                                           'host'   => $this->config->item('AWS_elasticache_endpoint'),
                                           'port'   => 6379,
                                       ]);
      $this->redis->ping();
      $this->redisIsAvailable = true;
    } catch(Exception $ex) {
      $this->logger->error("Error connection to redis from Auth Controller with message: " . $ex->getMessage());
    }
  }

  //NOTE - this is a DUPLICATE function which is on Tank_auth. It is here because reset_password() will not work with the one on Tank_auth
  function password_regex_match($pwString) {
    //First check if a capital letter exists
    if(!preg_match('/[A-Z]/', $pwString)) {
      $this->form_validation->set_message('password_regex_match', 'Please add an UPPER CASE letter.');

      return false;
    }
    //First check if a lower case letter exists
    if(!preg_match('/[a-z]/', $pwString)) {
      $this->form_validation->set_message('password_regex_match', 'Please add a lower case letter.');

      return false;
    }
    //Last check if either a number or special charater exists
    $numberExists = 0;
    $specialCharExists = 0;
    //If number exists
    if(preg_match('/\\d/', $pwString) > 0) {
      $numberExists = 1;
    }
    //If special character exists
    if(preg_match('/[\'^£$%&*!()}{@#~?><>,|=_+¬-]/', $pwString)) {
      $specialCharExists = 1;
    }
    if($numberExists + $specialCharExists > 0) {
      return true;
    } else {
      $this->form_validation->set_message('password_regex_match', 'Please add a number or special character.');

      return false;
    }
  }

  function index() {
    redirect('/auth/login_form');
  }

  /**
   * Run As Login user on the site
   *
   * @return void
   */
  var $login_id;

  function admin_login($userId, $key) {

    if($key != $this->config->item('oix_admin_core_key')) {

      redirect('/');

    } else {
      if($this->tank_auth->is_logged_in()) {                  // logged in

        redirect('/');

      } else {

        $data['userId'] = $userId;
        $data['key'] = $key;

        $data['memberInfo'] = $this->Members_Model->get_member_by_id($userId);
        $data['memberEmail'] = $data['memberInfo']['email'];
        if($data['memberInfo']['accountType'] == 4) {
          $data['dmaAccounts'] = $this->Members_Model->get_dma_accounts_of_user($data['memberInfo']['userId']);
        }

        $data['login_by_username'] = ($this->config->item('login_by_username', 'tank_auth') AND $this->config->item('use_username', 'tank_auth'));
        $data['login_by_email'] = $this->config->item('login_by_email', 'tank_auth');

        $this->form_validation->set_rules('login', 'Login', 'trim|required|xss_clean');
        $this->form_validation->set_rules('password', 'Password', 'trim|required|xss_clean');
        //$this->form_validation->set_rules('types', 'Member Login', 'trim|required|xss_clean');

        //$data['accounts'] = $this->Members_Model->get_active_accounts_list();

        if($this->input->get('login') != "") {
        }

        // Get login for counting attempts to login
        if($this->config->item('login_count_attempts', 'tank_auth') AND
            ($login = $this->input->post('login'))) {
          $login = $this->security->xss_clean($login);

        } else {
          $login = '';

        }

        $data['use_recaptcha'] = "";
        /*
              $data['use_recaptcha'] = $this->config->item('use_recaptcha', 'tank_auth');
              if ($this->tank_auth->is_max_login_attempts_exceeded($login)) {
                if ($data['use_recaptcha'])
                  $this->form_validation->set_rules('recaptcha_response_field', 'Confirmation Code', 'trim|xss_clean|required|callback__check_recaptcha');
                else
                  $this->form_validation->set_rules('captcha', 'Confirmation Code', 'trim|xss_clean|required|callback__check_captcha');
              }
              */
        $data['errors'] = [];

        if($this->input->post('accounts') != "") {
          if($this->form_validation->run()) {                // validation ok

            if($this->tank_auth->admin_login(
                $this->form_validation->set_value('login'),
                $this->form_validation->set_value('password'),
                $this->form_validation->set_value('remember'),
                $data['login_by_username'],
                $data['login_by_email'],
                $this->input->post('accounts'))) {                // success
              $login_id = [
                  'login_id' => $this->form_validation->set_value('login'),

              ];
              $this->cisession->set_userdata($login_id);

              if($this->cisession->userdata('alevel') == '3' || $this->cisession->userdata('alevel') == '9') {
              } else {
                $this->cisession->sess_destroy();
                redirect('/auth/login/');

              }

              if($this->cisession->userdata('level') == 4) {
                redirect('/myaccounts');
              } else {
                //General Member
                $dmaAccountInfo['dmaId'] = 0;
                $dmaAccountInfo['title'] = "";
                $dmaAccountInfo['mainAdmin'] = $this->cisession->userdata('userId');
                $dmaAccountInfo['profileUrl'] = "";
                $dmaAccountInfo['planHarvestedCredit'] = 0;
                $dmaAccountInfo['planWorkflowTools'] = 0;
                $dmaAccountInfo['planCompliance'] = 0;
                $dmaAccountInfo['planShareCredit'] = 0;
                $dmaAccountInfo['planSharedCredits'] = 0;
                $dmaAccountInfo['planCalendar'] = 0;
                $dmaAccountInfo['planCustomers'] = 0;
                $dmaAccountInfo['planDataPacks'] = [];
                $dmaAccountInfo['levelEditAccount'] = 1;
                $dmaAccountInfo['levelManageSuperAdmins'] = 1;
                $dmaAccountInfo['levelManageAdmins'] = 1;
                $dmaAccountInfo['levelLoadCredit'] = 1;
                $dmaAccountInfo['levelSellCredit'] = 1;
                $dmaAccountInfo['levelBuyCredit'] = 1;
                $this->tank_auth->setDMA($dmaAccountInfo);
                redirect('/dashboard');
              }

            } else {
              $errors = $this->tank_auth->get_error_message();
              if(isset($errors['banned'])) {                // banned user
                $this->_show_message($this->lang->line('auth_message_banned') . ' ' . $errors['banned']);

              } else {
                if(isset($errors['not_activated'])) {        // not activated user
                  redirect('/admin/auth/send_again/');

                } else {                          // fail
                  foreach($errors as $k => $v) {
                    $data['errors'][$k] = $this->lang->line($v);
                  }
                }
              }
            }
          }
        }
        $data['show_captcha'] = false;
        if($this->tank_auth->is_max_login_attempts_exceeded($login)) {
          $data['show_captcha'] = true;
          if($data['use_recaptcha']) {
            $data['recaptcha_html'] = $this->_create_recaptcha();
          } else {
            $data['captcha_html'] = $this->_create_captcha();
          }
        }
        $data['page_title'] = "Admin Login";
        $data['column_override'] = 'span-4 push-4';
        $this->load->view('includes/header', $data);
        $this->load->view('auth/admin_login_form', $data);
        $this->load->view('includes/footer', $data);
      }
    }
  }

  function logout_sso_callback() {
    $requestId = $this->input->get('requestId');

    $requestData = $this->redis->hgetall($requestId);
    $this->redis->del($requestId);
    if($requestData['logoutAction'] == '') {
      redirect('/auth/login_form');

      return true;
    }
    $this->cisession->set_userdata('logout_action', $requestData['logoutAction']);
    redirect($requestData["nextUrl"]);
  }

  function login_sso_callback() {
    $requestId = $this->input->get('requestId');

    $requestData = $this->redis->hgetall($requestId);
    $this->redis->del($requestId);
    if(!isset($requestData['samlName'], $requestData['samlEmail'])) {
      throw new \Exception('Expected SAML Response data missing!');
    }
    if(strtolower(trim($requestData["samlEmail"])) != strtolower(trim($requestData["email"]))) {
      throw new \Exception('SAML Response identity email does not match our application user email!');
    }

    if(isset($requestData['context']) & $requestData['context'] == 'inviteAccept') {
      $this->cisession->set_userdata(['sso_auth_complete' => true]);
      redirect($requestData["nextUrl"]);

      return true;
    }

    $loginResponse = $this->tank_auth->login_unified($requestData['email']);
    if($this->tank_auth->is_logged_in()) {  // logged in
      if(isset($requestData["nextUrl"]) && strlen($requestData["nextUrl"]) > 0) {
        redirect($requestData["nextUrl"]);
      } else {
        redirect('/myaccounts');
      }
    }
  }

  function login_sso_initiate() {
    $nextUrl = $this->input->post('next_url');
    if($this->tank_auth->is_logged_in()) {  // logged in
      if($nextUrl != '') {
        redirect($nextUrl);
      } else {
        redirect('/myaccounts');
      }
    }

    $this->form_validation->set_rules('login', 'Login', 'trim|required|xss_clean');

    $pageSource = null;
    if($this->input->post('loginPage') != "") {
      $pageSource = $this->input->post('loginPage');
    }
    $login = $this->input->post('login');
    $login = $this->security->xss_clean($login);

    if($this->form_validation->run()) {                // validation ok

      $loginAttemptCheck = $this->tank_auth->is_max_login_attempts_exceeded($login);
      if($loginAttemptCheck == "login_block") {
        //TODO: log something useful here
        return false;
      }

      $foundUser = $this->Users->get_user_by_email($login);
      if(!isset($foundUser->userId)) {
        //TODO: log something useful here
        return false;
      }
      $foundUser = $this->Members_Model->get_member_by_id($foundUser->userId);
      //var_dump(isset($foundUser['dmaAccounts']['all'][0]['external_auth_type']), $foundUser['dmaAccounts']);
      //exit;

      if(!isset($foundUser['dmaAccounts']['all'][0]['external_auth_type'])) {
        //TODO: log something useful here
        return false;
      }

      $ssoRequestInfo = [
          'id'           => $foundUser['userId'],
          'email'        => $foundUser['email'],
          'authType'     => $foundUser['dmaAccounts']['all'][0]['external_auth_type'],
          'authProvider' => $foundUser['dmaAccounts']['all'][0]['external_auth_provider'],
          'nextUrl'      => $nextUrl,
      ];

      $ssoRequestHash = sha1(microtime(true) . mt_rand(10000, 90000));
      $this->redis->hmset($ssoRequestHash, $ssoRequestInfo);
      $this->redis->expire($ssoRequestHash, 60 * 5);//TODO: agree on request TTL and implement flow when callback after expired

      redirect('sso/init?requestId=' . urlencode($ssoRequestHash));
    }
  }

  function login_form_email() {
    if($this->tank_auth->is_logged_in()) {  // logged in
      if(isset($_GET['nexturl'])) {
        redirect($_GET['nexturl']);
      } else {
        redirect('/myaccounts');
      }
    } else {
      $this->form_validation->set_rules('login', 'Login', 'trim|required|xss_clean');

      $pageSource = null;
      if($this->input->post('loginPage') != "") {
        $pageSource = $this->input->post('loginPage');
      }
      $login = $this->input->post('login');
      $login = $this->security->xss_clean($login);

      $data['errors'] = [];
      $data['show_login_attempt_warning'] = 0;
      $data['redirect_login_block'] = 0;
      $data['is_sso'] = false;

      if($this->form_validation->run()) {                // validation ok

        $loginAttemptCheck = $this->tank_auth->is_max_login_attempts_exceeded($login);
        if($loginAttemptCheck == "login_warn") {
          $data['show_login_attempt_warning'] = 1;
        } else {
          if($loginAttemptCheck == "login_block") {
            $data['redirect_login_block'] = 1;
          }
        }

        $foundUser = $this->Users->get_user_by_email($login);
        //var_dump($foundUser);
        //exit;
        if(isset($foundUser->userId)) {
          $foundUser = $this->Members_Model->get_member_by_id($foundUser->userId);

          //var_dump($foundUser['dmaAccounts']['all']);
          //exit;
          if(isset($foundUser['dmaAccounts']['all'][0]['external_auth_type'])
              && $foundUser['dmaAccounts']['all'][0]['external_auth_type'] == 'saml') {
            $data['is_sso'] = true;
          }
        }

        $response['success'] = 1;
        $response['is_sso'] = $data['is_sso'];
        $response['show_login_attempt_warning'] = $data['show_login_attempt_warning'];
        $response['redirect_login_block'] = $data['redirect_login_block'];

        echo json_encode($response);
      }

      if($this->input->post('loginPage') == "dmaMemberAccept" || $this->input->post('loginPage') == "creditShareAccept" || $this->input->post('loginPage') == "loginDeepLink") {
        //Login failed don't load anything

      } else {
        $data['page_title'] = "Login Page";
        $data['headerswitch'] = "corp";
        $data['column_override'] = 'span-4 push-4';
        $data['logoutAction'] = "";
        $this->load->view('includes/header', $data);
        $this->load->view('auth/login_page', $data);
        $this->load->view('includes/footer', $data);
      }
    }
  }

  function login_form($reset = "") {
    if($this->tank_auth->is_logged_in()) {  // logged in
      if(isset($_GET['nexturl'])) {
        redirect($_GET['nexturl']);
      } else {
        redirect('/myaccounts');
      }
    } else {

      $data['password_reset_flag'] = $reset;
      $data['login_by_username'] = ($this->config->item('login_by_username', 'tank_auth') AND $this->config->item('use_username', 'tank_auth'));
      $data['login_by_email'] = $this->config->item('login_by_email', 'tank_auth');

      $this->form_validation->set_rules('login', 'Login', 'trim|required|xss_clean');
      $this->form_validation->set_rules('password', 'Password', 'trim|required|xss_clean');
      $this->form_validation->set_rules('remember', 'Remember me', 'integer');

      $pageSource = null;
      if($this->input->post('loginPage') != "") {
        $pageSource = $this->input->post('loginPage');
      }
      //var_dump($this->cisession->userdata('password'));

      // Get login for counting attempts to login
      if($this->config->item('login_count_attempts', 'tank_auth') AND
          ($login = $this->input->post('login'))) {
        $login = $this->security->xss_clean($login);
      } else {
        $login = '';
      }

      $data['errors'] = [];
      $data['show_login_attempt_warning'] = 0;
      $data['redirect_login_block'] = 0;

      if($this->form_validation->run()) {                // validation ok

        $loginAttemptCheck = $this->tank_auth->is_max_login_attempts_exceeded($login);
        if($loginAttemptCheck == "login_warn") {
          $data['show_login_attempt_warning'] = 1;
        } else {
          if($loginAttemptCheck == "login_block") {
            $data['redirect_login_block'] = 1;
          }
        }

        $loginReponse = $this->tank_auth->login(
            $this->form_validation->set_value('login'),
            $this->form_validation->set_value('password'),
            $this->form_validation->set_value('remember'),
            $data['login_by_username'],
            $data['login_by_email'],
            $pageSource);

        if($loginReponse['success'] == 1) {  // success

          $response['success'] = 1;
          $response['show_login_attempt_warning'] = $data['show_login_attempt_warning'];
          $response['redirect_login_block'] = $data['redirect_login_block'];

          echo json_encode($response);

        } else {

          $errors = $this->tank_auth->get_error_message();

          $response['success'] = 0;
          $response['show_login_attempt_warning'] = $data['show_login_attempt_warning'];
          $response['redirect_login_block'] = $data['redirect_login_block'];
          $response['authUrl'] = (isset($loginReponse['authUrl']) ? $loginReponse['authUrl'] : null);
          $response['authUserId'] = (isset($loginReponse['authUserId']) ? $loginReponse['authUserId'] : null);
          $response['authPasswordKey'] = (isset($loginReponse['authPasswordKey']) ? $loginReponse['authPasswordKey'] : null);
          echo json_encode($response);

        }

      }

      if($this->input->post('loginPage') == "dmaMemberAccept" || $this->input->post('loginPage') == "creditShareAccept" || $this->input->post('loginPage') == "loginDeepLink") {
        //Login failed don't load anything

      } else {
        $data['page_title'] = "Login Page";
        $data['headerswitch'] = "corp";
        $data['column_override'] = 'span-4 push-4';
        $data['logoutAction'] = "";
        $this->load->view('includes/header', $data);
        $this->load->view('auth/login_page', $data);
        $this->load->view('includes/footer', $data);
      }
    }
  }

  function accountlocked() {

    $data['page_title'] = "Login Page";
    $data['headerswitch'] = "corp";
    $data['column_override'] = 'span-4 push-4';
    $this->load->view('includes/header', $data);
    $this->load->view('auth/accountlocked', $data);
    $this->load->view('includes/footer', $data);

  }

  function load_DMA_account($dmaId = "", $newMember = "") {

    $loginDeepLink = false;
    if($dmaId == "") {
      $dmaId = $this->input->post('dmaId');
      $loginDeepLink = true;
    }
    //Comment out the above and query a new table to get the Membership Level and then save that to Session
    $dmaAccountInfo = $this->DmaAccounts->get_dma_member_levels_for_member($dmaId, $this->cisession->userdata('userId'));
    $this->tank_auth->setDMA($dmaAccountInfo);

    if($loginDeepLink) {
      return 1;
    } else {
      if($newMember == 3) {
        redirect('/dashboard/welcome_new_share');
      } else {
        if($newMember == 2) {
          redirect('/dashboard/welcome');
        } else {
          if($newMember == 1) {
            redirect('/dashboard/welcome_member');
          } else {
            redirect('/dashboard');
          }
        }
      }
    }
  }

  /**
   * Logout user
   *
   * @return void
   */
  function logout($action = "") {
    $action = ($action == "") ? $this->input->post('action') : $action;
    $isSSO = false;
    $ssoRequestHash = null;
    if($this->cisession->userdata('isSSO') === true) {
      $isSSO = true;
      $ssoProvider = $this->cisession->userdata('ssoProvider');
      $ssoType = $this->cisession->userdata('ssoType');
      $ssoIdentity = $this->cisession->userdata('username');
      $ssoInternalId = $this->cisession->userdata('userId');

      //echo '<pre>';
      //var_dump('HERE', $ssoInternalId, $ssoIdentity, $ssoType, $ssoProvider, $this->uri->uri_string());
      //exit;

      $ssoRequestInfo = [
          'id'           => $ssoInternalId,
          'email'        => $ssoIdentity,
          'authType'     => $ssoType,
          'authProvider' => $ssoProvider,
          'nextUrl'      => $this->agent->referrer(),
          'logoutAction' => $action,
      ];

      $ssoRequestHash = sha1(microtime(true) . mt_rand(10000, 90000));
      $this->redis->hmset($ssoRequestHash, $ssoRequestInfo);
      $this->redis->expire($ssoRequestHash, 60 * 5); //TODO: agree on request TTL and implement flow when callback after expired
    }

    $userIdLoguout = $this->cisession->userdata('userId');

    $this->tank_auth->logout($action);

    //If this is an idle logout, then resend the user back to the URL they were logged out of
    //so that the URL will be used in the resulting re-login screen (so user goes back to where they came from)
    if($action == "idle" || $action == "double") {
      $this->logger->info("User Logout - User ID: " . $userIdLoguout . " - Logout Action: " . $action);

      if($isSSO) {
        echo json_encode(['redirect' => '/sso/logout?requestId=' . urlencode($ssoRequestHash)]);

        return;
      }

      echo json_encode(['success' => 1]);

      return;
    }

    $this->logger->info("User Logout - User ID: " . $userIdLoguout); //Log it
    if($isSSO) {
      redirect('sso/logout?requestId=' . urlencode($ssoRequestHash));

      return;
    }

    redirect('/auth/login_form');
  }

  /**
   * Register user on the site
   *
   * @return void
   */
  /*
 function register()
 {
   if ($this->tank_auth->is_logged_in()) {									// logged in
     redirect('');

   } elseif ($this->tank_auth->is_logged_in(FALSE)) {						// logged in, not activated
     redirect('/auth/send_again/');

   } elseif (!$this->config->item('allow_registration', 'tank_auth')) {	// registration is off
     $this->_show_message($this->lang->line('auth_message_registration_disabled'));

   } else {
     $use_username = $this->config->item('use_username', 'tank_auth');
     if ($use_username) {
       $this->form_validation->set_rules('username', 'Username', 'trim|required|xss_clean|min_length['.$this->config->item('username_min_length', 'tank_auth').']|max_length['.$this->config->item('username_max_length', 'tank_auth').']|alpha_dash');
     }
     $this->form_validation->set_rules('email', 'Email', 'trim|required|xss_clean|valid_email');
     $this->form_validation->set_rules('password', 'Password', 'trim|required|xss_clean|min_length['.$this->config->item('password_min_length', 'tank_auth').']|max_length['.$this->config->item('password_max_length', 'tank_auth').']|alpha_dash');
     $this->form_validation->set_rules('confirm_password', 'Confirm Password', 'trim|required|xss_clean|matches[password]');

     $captcha_registration	= $this->config->item('captcha_registration', 'tank_auth');
     $use_recaptcha			= $this->config->item('use_recaptcha', 'tank_auth');
     if ($captcha_registration) {
       if ($use_recaptcha) {
         $this->form_validation->set_rules('recaptcha_response_field', 'Confirmation Code', 'trim|xss_clean|required|callback__check_recaptcha');
       } else {
         $this->form_validation->set_rules('captcha', 'Confirmation Code', 'trim|xss_clean|required|callback__check_captcha');
       }
     }
     $data['errors'] = array();

     $email_activation = $this->config->item('email_activation', 'tank_auth');

     if ($this->form_validation->run()) {								// validation ok
       if (!is_null($data = $this->tank_auth->create_user(
           $use_username ? $this->form_validation->set_value('username') : '',
           $this->form_validation->set_value('email'),
           $this->form_validation->set_value('password'),
           $email_activation))) {									// success

         $data['site_name'] = $this->config->item('website_name', 'tank_auth');

         if ($email_activation) {									// send "activate" email
           $data['activation_period'] = $this->config->item('email_activation_expire', 'tank_auth') / 3600;

           $this->_send_email('activate', $data['email'], $data);

           unset($data['password']); // Clear password (just for any case)

           $this->_show_message($this->lang->line('auth_message_registration_completed_1'));

         } else {
           if ($this->config->item('email_account_details', 'tank_auth')) {	// send "welcome" email

             $this->_send_email('welcome', $data['email'], $data);
           }
           unset($data['password']); // Clear password (just for any case)

           $this->_show_message($this->lang->line('auth_message_registration_completed_2').' '.anchor('/auth/login/', 'Login'));
         }
       } else {
         $errors = $this->tank_auth->get_error_message();
         foreach ($errors as $k => $v)	$data['errors'][$k] = $this->lang->line($v);
       }
     }
     if ($captcha_registration) {
       if ($use_recaptcha) {
         $data['recaptcha_html'] = $this->_create_recaptcha();
       } else {
         $data['captcha_html'] = $this->_create_captcha();
       }
     }
     $data['use_username'] = $use_username;
     $data['captcha_registration'] = $captcha_registration;
     $data['use_recaptcha'] = $use_recaptcha;


     $this->load->view('auth/register_form', $data);



   }
 }
*/
  /**
   * Send activation email again, to the same or new email address
   *
   * @return void
   */
  /*
 function send_again()
 {
   if (!$this->tank_auth->is_logged_in(FALSE)) {							// not logged in or activated
     redirect('/auth/login/');

   } else {
     $this->form_validation->set_rules('email', 'Email', 'trim|required|xss_clean|valid_email');

     $data['errors'] = array();

     if ($this->form_validation->run()) {								// validation ok
       if (!is_null($data = $this->tank_auth->change_email(
           $this->form_validation->set_value('email')))) {			// success

         $data['site_name']	= $this->config->item('website_name', 'tank_auth');
         $data['activation_period'] = $this->config->item('email_activation_expire', 'tank_auth') / 3600;

         $this->_send_email('activate', $data['email'], $data);

         $this->_show_message(sprintf($this->lang->line('auth_message_activation_email_sent'), $data['email']));

       } else {
         $errors = $this->tank_auth->get_error_message();
         foreach ($errors as $k => $v)	$data['errors'][$k] = $this->lang->line($v);
       }
     }
     $this->load->view('auth/send_again_form', $data);
   }
 }
*/

  /**
   * Activate user account.
   * User is verified by userId and authentication code in the URL.
   * Can be called by clicking on link in mail.
   *
   * @return void
   */
  function activate() {
    $userId = $this->uri->segment(3);
    $new_email_key = $this->uri->segment(4);

    // Activate user
    if($this->tank_auth->activate_user($userId, $new_email_key)) {    // success
      $this->tank_auth->logout();

      $this->load->view('includes/header');
      $this->load->view('auth/general_message');
      $this->load->view('includes/footer');

      //	$this->_show_message($this->lang->line('auth_message_activation_completed').' '.anchor('/auth/login/', 'Login'));

    } else {                                // fail
      $this->_show_message($this->lang->line('auth_message_activation_failed'));
    }
  }

  /**
   * Generate reset code (to change password) and send it to user
   *
   * @return void
   */
  function forgot_password($version = "") {
    if($this->tank_auth->is_logged_in()) {                  // logged in
      redirect('/');

    } else {

      $data['version'] = $version;
      if($version == 'customer_activate') {
        $partnerUsername = $this->input->post('partnerUsername');
      }

      $this->form_validation->set_rules('login', 'Email or login', 'trim|required|xss_clean');

      $data['errors'] = [];

      if($this->form_validation->run()) {                // validation ok
        if(!is_null($this->tank_auth->forgot_password(
            $this->form_validation->set_value('login')))) {

          $userInfo = $this->Members_Model->get_dma_member_by_email($this->form_validation->set_value('login'));

          $isSSO = isset($userInfo['dmaAccounts']['all'][0]['external_auth_type']) && strlen($userInfo['dmaAccounts']['all'][0]['external_auth_type']) > 0;

          //If a user matching email address exists
          if(count($userInfo) > 0 && !$isSSO) {

            // Send email with password activation link
            $emailData['userInfo'] = $userInfo;
            $emailData['email'] = $userInfo['email'];

            if($version == 'customer_activate') {
              $emailData['headline'] = "Activate%20Your%20Account";
              $emailData['updateType'] = 'activate_account';
              $emailData['subject'] = 'Activate Your Account';
            } else {
              if($version == 'activate') {
                $emailData['headline'] = "Activate%20Your%20Account";
                $emailData['updateType'] = 'activate_account';
                $emailData['subject'] = 'Activate Your Account';
              } else {
                $emailData['headline'] = "Reset%20Your%20Password";
                $emailData['updateType'] = 'forgot_password';
                $emailData['subject'] = 'Reset Your Password';
              }
            }
            $emailData['welcomeNameTemplate'] = 0;
            $emailData['button'] = 0;
            $emailData['firstName'] = "";
            $this->Email_Model->_send_email('member_template_1', $emailData['subject'], $emailData['email'], $emailData);

            if($version == 'customer_activate') {
              //get the customer
              $cRequest['cusUserId'] = $userInfo['userId'];
              $cRequest['partnerUsername'] = $partnerUsername;
              $customerData = $this->Members_Model->get_customer($cRequest);
              if(sizeof($customerData) > 0) {
                $cusId = $customerData['cusId'];
                $aRequest['cusStatus'] = 1;
                //mark the customer as "activated" (we do this now)
                $this->Members_Model->update_user_customer($cusId, $aRequest);
              }

              redirect('/customer_welcome/' . $partnerUsername . '/success');
            } else {
              if($version == 'activate') {
                redirect('/auth/forgot_password_confirm/activate');
              } else {
                redirect('/auth/forgot_password_confirm');
              }
            }

          } else {
            redirect('/auth/forgot_password_confirm');
          }

        }
      }

      $data['page_title'] = "Recover Password";
      $data['headerswitch'] = "corp";
      $data['column_override'] = 'span-11';
      $this->load->view('includes/header', $data);
      $this->load->view('auth/forgot_password_form', $data);
      $this->load->view('includes/footer', $data);

    }
  }

  /**
   * Confirm reset code send it to user
   *
   * @return void
   */
  function forgot_password_confirm($version = "") {
    if($this->tank_auth->is_logged_in()) {                  // logged in
      redirect('/');

    } else {

      $data['version'] = $version;

      $data['page_title'] = "Request for Password Reset ";
      $data['headerswitch'] = "corp";
      $data['column_override'] = 'span-11';
      $this->load->view('includes/header', $data);
      $this->load->view('auth/forgot_password_confirm', $data);
      $this->load->view('includes/footer', $data);

    }
  }

  function gm_upgrade_warning() {
    $data = [];
    $this->load->view('includes/header', $data);
    $this->load->view('auth/gm_upgrade_warning', $data);
    $this->load->view('includes/footer', $data);

  }

  function agreement() {
    $this->form_validation->set_rules('agreement', 'Agreement', 'trim|required');

    $data['page_title'] = '';
    $data['headerswitch'] = "corp";

    $data['errors'] = [];
    if($this->form_validation->run()) {                // validation ok
      $this->users->make_agree($this->cisession->userdata('userId'));

      $data = $this->cisession->all_userdata();

      $data['site_name'] = $this->config->item('website_name', 'tank_auth');
      $data['admin_email'] = $this->config->item('admin_email', 'tank_auth');
      $data['userId'] = $this->cisession->userdata('userId');
      $data['username'] = $this->cisession->userdata('username');

      $data['login_by_username'] = ($this->config->item('login_by_username', 'tank_auth') AND
          $this->config->item('use_username', 'tank_auth'));
      $data['login_by_email'] = $this->config->item('login_by_email', 'tank_auth');

      if($this->tank_auth->login(
          $this->cisession->userdata('username'),
          $this->cisession->userdata('password'),
          $this->cisession->userdata('remember'),
          $data['login_by_username'],
          $data['login_by_email'])) {                // success

        //$this->ci->session->unset_userdata(array('password' => '', 'remember' => ''));
        $this->cisession->unset_userdata('password');

        //jira.theoix.com/browse/ES-16
        if(copy("docs/Membership.pdf", 'uploads/Membership_' . $data['userId'] . '.pdf')) {
          $checkExistMembershipAgreement = $this->Members_Model->check_Exist_membershipAgreement('Membership_' . $data['userId'] . '.pdf');
          if($checkExistMembershipAgreement) {
            $membershipAgreement = $this->Members_Model->add_membershipAgreement('uploads/Membership_' . $data['userId'] . '.pdf', 'Membership_' . $data['userId'] . '.pdf', $data['userId']);
          }
        }

        //Get user info for email
        $userInfo = $this->Members_Model->get_member_by_id($this->cisession->userdata('userId'));
        //Email notify OIX Admin
        $emailData['firstName'] = $userInfo['firstName'];
        $emailData['lastName'] = $userInfo['lastName'];
        $emailData['companyName'] = $userInfo['companyName'];
        $emailData['updateType'] = 'memberActivated';
        $emailData['updateTypeName'] = 'Member Activated';
        $this->Email_Model->_send_email('oix_admin_update', 'OIX Admin Update - ' . $emailData['updateTypeName'], $this->config->item("oix_admin_emails_array"), $emailData);

        //Redirect user
        if($userInfo['accountType'] == 4) {
          redirect('/myaccounts/1');
        } else {
          redirect('/dashboard/welcome');
        }

      }

      //redirect('/dashboard');
    } else {

      $this->form_validation->set_errors(['agreement' => 'You must agree to the membership agreement']);

      $this->form_validation->set_errors(['rules' => 'You must agree to the rules of the exchange']);
      $data['page_title'] = 'COMPLETE YOUR MEMBERSHIP PROCESS';
      $this->load->view('includes/header', $data);
      $this->load->view('auth/agree_to_rules', $data);
      $this->load->view('includes/footer', $data);

    }

  }

  function mfa() {
    if(!$this->tank_auth->is_logged_in()) {                // not logged in or not activated

      redirect('/auth/login/');

    } else {

      if($this->cisession->userdata('ipVerified') == 1) {

        $thisIpAddress = $this->Members_Model->get_ip();

        $memberIPs = $this->Members_Model->get_member_ip_addresses($this->cisession->userdata('userId'));
        $ipMatches = 0;
        foreach($memberIPs as $mIp) {
          if($mIp['ipAddress'] == $thisIpAddress) {
            $ipMatches++;
          }
        }
        if($ipMatches == 0) {
          $this->Members_Model->add_ip_address_to_member($this->cisession->userdata('userId'), $thisIpAddress, 1);
        } else {
          $this->Members_Model->add_mfa_to_ip_address_on_member($this->cisession->userdata('userId'), $thisIpAddress);
        }

        //User's address in now approved, so forward them to new location
        if($this->cisession->userdata('isDMA')) {
          redirect('/myaccounts');
        } else {
          redirect('/dashboard/welcome');
        }

      } else {

        $data['page_title'] = '';
        $data['headerswitch'] = "corp";

        $data['myEmailAddress'] = $this->Members_Model->get_member_email($this->cisession->userdata('userId'));
        $data['memberInfo'] = $this->Members_Model->get_member_by_id($this->cisession->userdata('userId'));

        $this->load->view('includes/header', $data);
        $this->load->view('auth/mfa', $data);
        $this->load->view('includes/footer', $data);

      }
    }
  }


  /**
   * Replace user password (forgotten) with a new one (set by user).
   * User is verified by userId and authentication code in the URL.
   * Can be called by clicking on link in mail.
   *
   * @return void
   */

  //!!!!!!!!! - this funciton has 3 uses: (i) reset password when forgot, (ii) change password when logged in, (iii) activate account for enterprise account
  function reset_password($userId = "", $new_pass_key = "", $version = "") {

    $data['userId'] = $userId;

    if($this->tank_auth->is_logged_in()) {
      //If person is logged in, but there is a forgot password ID in the URL, then kick them out so they don't reset the wrong user
      if($userId > 0) {
        redirect('/');
      }
      $userId = $this->cisession->userdata('userId');
      $data['pwAction'] = "change";
      $data['pwActionName'] = "Change";
      $changeTerm = "Changed";
    } else {
      $data['pwAction'] = "reset";
      $data['pwActionName'] = "Reset";
      $changeTerm = "Reset";
    }
    $data['new_pass_key'] = $new_pass_key;
    $data['version'] = $version;

    //$this->form_validation->set_rules('new_password', 'New Password', 'trim|required|xss_clean|min_length['.$this->config->item('password_min_length', 'tank_auth').']|max_length['.$this->config->item('password_max_length', 'tank_auth').']|alpha_dash');
    //$this->form_validation->set_rules('confirm_new_password', 'Confirm New Password', 'trim|required|xss_clean|matches[new_password]');
    if($userId == "") {
      $userId = $this->cisession->userdata('userId');
    }
    $data['userData'] = $this->Members_Model->get_member_by_id($userId);
    $isSSO = isset($data['userData']['dmaAccounts']['all'][0]['external_auth_type']) && strlen($data['userData']['dmaAccounts']['all'][0]['external_auth_type']) > 0;

    if($isSSO) {
      redirect('/');
    }

    $data['pwMinLength'] = ($data['userData']['pwCharacterCountReq'] > 8) ? $data['userData']['pwCharacterCountReq'] : 8;

    $data['customPWerrors'] = [];
    $data['currPwMatchError'] = false;

    $account = [
        [
            'field' => 'new_password',
            'rules' => 'required|xss_clean|matches[confirm_new_password]|min_length[' . $data["pwMinLength"] . ']|callback_password_regex_match',
        ],
        [
            'field' => 'confirm_new_password',
            'rules' => 'trim|required|matches[new_password]',
        ],
    ];

    if($this->tank_auth->is_logged_in()) {
      $current_password_validation = [
          'field' => 'current_password',
          'rules' => 'required|xss_clean',
      ];
      array_push($account, $current_password_validation);
    }

    $this->form_validation->set_rules($account);

    $data['errors'] = [];

    if($this->form_validation->run() && !$isSSO) {                // validation ok

      //FIRST CHECK PASSWORD RESET LIMITS
      $customPWerrors = [];

      // Hash password using phpass
      $hasher = new PasswordHash(
          $this->config->item('phpass_hash_strength', 'tank_auth'),
          $this->config->item('phpass_hash_portable', 'tank_auth'));
      $hashed_password = $hasher->HashPassword($this->form_validation->set_value('new_password'));

      $password_reset_history = $this->Members_Model->get_reset_password_history($userId);

      $pwArrayCount = 0;
      $isMatch = false;
      $pwFirstMatchNum = 0; //This is the first match (used for pwReuseDaysReq)
      foreach($password_reset_history as $pwh) {
        if($hasher->CheckPassword($this->form_validation->set_value('new_password'), $pwh['pwrPWhash'])) {
          if($pwFirstMatchNum == 0) {
            $pwFirstMatchNum = $pwArrayCount;
            $isMatch = true;
          }
        }
        $pwArrayCount++;
      }

      //If this user has a reuse date limit, check it
      if($isMatch && $data['userData']['pwReuseDaysReq'] != '' && $data['userData']['pwReuseDaysReq'] >= 0 && sizeof($password_reset_history) > 0) {
        //last reset
        $lastResetDate = $password_reset_history[$pwFirstMatchNum]['pwrDate'];
        $timeDiff = time() - $lastResetDate;
        $daysDiff = floor($timeDiff / (60 * 60 * 24));

        if($data['userData']['pwReuseDaysReq'] == 0) {
          //Always throw error if there is a match but no matches are allowed
          $customPWerrors['pwReuseDaysReqLeft'] = 0;
        } else {
          if($daysDiff < $data['userData']['pwReuseDaysReq']) {
            // if trying to reuse password within the time frame, then block
            $customPWerrors['pwReuseDaysReqLeft'] = $data['userData']['pwReuseDaysReq'] - $daysDiff;
          } else {
            //if past the window, then allow
          }
        }
      }
      //If this user has a reuse count limit, check it
      if($isMatch && $data['userData']['pwReuseCountReq'] != '' && $data['userData']['pwReuseCountReq'] >= 0 && sizeof($password_reset_history) > 0) {
        if($data['userData']['pwReuseCountReq'] == 0) {
          //Always throw error if there is a match but no matches are allowed
          $customPWerrors['pwReuseCountLeft'] = 0;
        } else {
          if($data['userData']['pwReuseCountReq'] > $pwFirstMatchNum) {
            //add error
            $customPWerrors['pwReuseCountLeft'] = $data['userData']['pwReuseCountReq'] - $pwFirstMatchNum;
          } else {
            // do nothing
          }
        }
      }

      if(sizeof($customPWerrors) > 0) {

        $data['customPWerrors'] = $customPWerrors;

      } else {

        $changeSuccess = false;

        //If logged in, then this is a password change (rather than a reset)
        if($this->tank_auth->is_logged_in()) {
          if($this->tank_auth->change_password(
              $this->form_validation->set_value('current_password'),
              $this->form_validation->set_value('new_password'))) {  // success
            $changeSuccess = true;
          }

          //Else, if this person is NOT logged in, then its a password change
        } else {

          if(!is_null($this->tank_auth->reset_password(
              $userId, $new_pass_key,
              $this->form_validation->set_value('new_password')))) {  // success
            $changeSuccess = true;
          }

        }

        if($changeSuccess) {  // success

          $data['site_name'] = $this->config->item('website_name', 'tank_auth');

          //Add password reset history
          $this->Members_Model->add_reset_password_history($userId, $hashed_password);

          if($version != "activate") {
            // Send email notifying user that their password was reset
            $emailData = $data;
            $emailData['email'] = $data['userData']['email'];
            $emailData['headline'] = "Your%20Password%20Has%20Been%20" . $changeTerm;
            $emailData['welcomeNameTemplate'] = 0;
            $emailData['button'] = 0;
            $emailData['updateType'] = 'password_reset';
            $emailData['firstName'] = "";
            $this->Email_Model->_send_email('member_template_1', 'Your Password Has Been ' . $changeTerm, $emailData['email'], $emailData);
          }

          if($version == "activate") {
            //Activate the user record
            $this->Members_Model->activate_user($userId);
            //Send activation confirmation with login instructions for future login
            $emailData = $data;
            $emailData['email'] = $data['userData']['email'];
            $emailData['headline'] = "Welcome%20to%20Your%20OIX%20Account";
            $emailData['welcomeNameTemplate'] = 1;
            $emailData['button'] = 0;
            $emailData['updateType'] = 'customer_activate_welcome';
            $emailData['firstName'] = $data['userData']['firstName'];
            $this->Email_Model->_send_email('member_template_1', 'Welcome to Your OIX Account', $emailData['email'], $emailData);
          }

          if($this->tank_auth->is_logged_in()) {
            if($this->cisession->userdata('level') == 8) {
              redirect('/signer/password_change_success');
            } else {
              redirect('/myaccount/password_change_success');
            }
          } else {
            redirect('/auth/login_form/reset');
          }

        } else {
          // fail
          //$this->_show_message($this->lang->line('auth_message_new_password_failed'));
          $data['currPwMatchError'] = true;

        }

      }

    } else {

      if(!$this->tank_auth->is_logged_in() && !$isSSO) {

        // Try to activate user by password key (if not activated yet)
        if($this->config->item('email_activation', 'tank_auth')) {
          $this->tank_auth->activate_user($userId, $new_pass_key, false);
        }

        if(!$this->tank_auth->can_reset_password($userId, $new_pass_key)) {
          $this->_show_message($this->lang->line('auth_message_new_password_failed'));
        }

      } else if(!$this->tank_auth->is_logged_in() && $isSSO) {
        $this->_show_message($this->lang->line('auth_message_new_password_failed'));
      }
    }

    $this->load->view('includes/header', $data);
    $this->load->view('auth/reset_password_form', $data);
    $this->load->view('includes/footer', $data);

  }

  /**
   * Change user password
   *
   * @return void
   */
  function change_password() {
    if(!$this->tank_auth->is_logged_in()) {                // not logged in or not activated
      redirect('/auth/login/');

    } else {

      throw new \Exception('General fail'); //do not allow any user to get to this page!

      /*

      $this->form_validation->set_rules('old_password', 'Old Password', 'trim|required|xss_clean');
      $this->form_validation->set_rules('new_password', 'New Password', 'trim|required|xss_clean|min_length[' . $this->config->item('password_min_length', 'tank_auth') . ']|max_length[' . $this->config->item('password_max_length', 'tank_auth') . ']|alpha_dash');
      $this->form_validation->set_rules('confirm_new_password', 'Confirm new Password', 'trim|required|xss_clean|matches[new_password]');

      $data['errors'] = [];

      if($this->form_validation->run()) {                // validation ok
        if($this->tank_auth->change_password(
            $this->form_validation->set_value('old_password'),
            $this->form_validation->set_value('new_password'))) {  // success
          $this->_show_message($this->lang->line('auth_message_password_changed'));

        } else {                            // fail
          $errors = $this->tank_auth->get_error_message();
          foreach($errors as $k => $v) {
            $data['errors'][$k] = $this->lang->line($v);
          }
        }
      }
      $this->load->view('auth/change_password_form', $data);

      */
    }
  }

  function security_update($userId, $new_password_key) {

    $data['page_title'] = "Security Upgrade";
    $data['headerswitch'] = "corp";

    $data['userId'] = $userId;
    $data['new_password_key'] = $new_password_key;

    $this->load->view('includes/header', $data);
    $this->load->view('auth/security_update', $data);
    $this->load->view('includes/footer', $data);

  }

  function security_update_dma($userId, $new_password_key) {

    $data['page_title'] = "Security Upgrade";
    $data['headerswitch'] = "corp";

    $data['userId'] = $userId;
    $data['new_password_key'] = $new_password_key;

    $data['userData'] = $this->Members_Model->get_member_by_id($userId);

    $this->load->view('includes/header', $data);
    $this->load->view('auth/security_update_dma', $data);
    $this->load->view('includes/footer', $data);

  }

  function security_expired($userId, $new_password_key) {

    $data['page_title'] = "Password Expired";
    $data['headerswitch'] = "corp";

    $data['userId'] = $userId;
    $data['new_password_key'] = $new_password_key;

    $data['userData'] = $this->Members_Model->get_member_by_id($userId);

    $this->load->view('includes/header', $data);
    $this->load->view('auth/security_expired', $data);
    $this->load->view('includes/footer', $data);

  }

  /**
   * Replace user email with a new one.
   * User is verified by userId and authentication code in the URL.
   * Can be called by clicking on link in mail.
   *
   * @return void
   */
  function reset_email() {
    $userId = $this->uri->segment(3);
    $new_email_key = $this->uri->segment(4);

    // Reset email
    if($this->tank_auth->activate_new_email($userId, $new_email_key)) {  // success
      $this->tank_auth->logout();
      $this->_show_message($this->lang->line('auth_message_new_email_activated') . ' ' . anchor('/auth/login/', 'Login'));

    } else {                                // fail
      $this->_show_message($this->lang->line('auth_message_new_email_failed'));
    }
  }

  /**
   * Delete user from the site (only when user is logged in)
   *
   * @return void
   */
  function unregister() {
    if(!$this->tank_auth->is_logged_in()) {                // not logged in or not activated
      redirect('/auth/login/');

    } else {
      $this->form_validation->set_rules('password', 'Password', 'trim|required|xss_clean');

      $data['errors'] = [];

      if($this->form_validation->run()) {                // validation ok
        if($this->tank_auth->delete_user(
            $this->form_validation->set_value('password'))) {    // success
          $this->_show_message($this->lang->line('auth_message_unregistered'));

        } else {                            // fail
          $errors = $this->tank_auth->get_error_message();
          foreach($errors as $k => $v) {
            $data['errors'][$k] = $this->lang->line($v);
          }
        }
      }
      $this->load->view('auth/unregister_form', $data);
    }
  }

  /**
   * Show info message
   *
   * @param string
   * @return  void
   */
  function _show_message($message) {
    $this->cisession->set_flashdata('message', $message);
    redirect('/');
  }

  /**
   * Send email message of given type (activate, forgot_password, etc.)
   *
   * @param string
   * @param string
   * @param array
   * @return  void
   */
  /*
 function _send_email($type, $email, &$data)
 {
   $this->load->library('email');
   $this->email->from($this->config->item('webmaster_email', 'tank_auth'), $this->config->item('website_name', 'tank_auth'));
   $this->email->reply_to($this->config->item('webmaster_email', 'tank_auth'), $this->config->item('website_name', 'tank_auth'));
   $this->email->to($email);
   $this->email->subject(sprintf($this->lang->line('auth_subject_'.$type), base_url()));
   $this->email->message($this->load->view('email/'.$type.'-html', $data, TRUE));
   $this->email->set_alt_message($this->load->view('email/'.$type.'-txt', $data, TRUE));
   $this->email->send();
 }
*/
  /**
   * Create CAPTCHA image to verify user as a human
   *
   * @return  string
   */
  function _create_captcha() {
    $this->load->helper('captcha');

    $cap = create_captcha([
                              'img_path'   => './' . $this->config->item('captcha_path', 'tank_auth'),
                              'img_url'    => base_url() . $this->config->item('captcha_path', 'tank_auth'),
                              'font_path'  => './' . $this->config->item('captcha_fonts_path', 'tank_auth'),
                              'font_size'  => $this->config->item('captcha_font_size', 'tank_auth'),
                              'img_width'  => $this->config->item('captcha_width', 'tank_auth'),
                              'img_height' => $this->config->item('captcha_height', 'tank_auth'),
                              'show_grid'  => $this->config->item('captcha_grid', 'tank_auth'),
                              'expiration' => $this->config->item('captcha_expire', 'tank_auth'),
                          ]);

    // Save captcha params in session
    $this->cisession->set_flashdata([
                                        'captcha_word' => $cap['word'],
                                        'captcha_time' => $cap['time'],
                                    ]);

    return $cap['image'];
  }

  /**
   * Callback function. Check if CAPTCHA test is passed.
   *
   * @param string
   * @return  bool
   */
  function _check_captcha($code) {
    $time = $this->cisession->flashdata('captcha_time');
    $word = $this->cisession->flashdata('captcha_word');

    list($usec, $sec) = explode(" ", microtime());
    $now = ((float)$usec + (float)$sec);

    if($now - $time > $this->config->item('captcha_expire', 'tank_auth')) {
      $this->form_validation->set_message('_check_captcha', $this->lang->line('auth_captcha_expired'));

      return false;

    } else {
      if(($this->config->item('captcha_case_sensitive', 'tank_auth') AND
              $code != $word) OR
          strtolower($code) != strtolower($word)) {
        $this->form_validation->set_message('_check_captcha', $this->lang->line('auth_incorrect_captcha'));

        return false;
      }
    }

    return true;
  }

  /**
   * Create reCAPTCHA JS and non-JS HTML to verify user as a human
   *
   * @return  string
   */
  function _create_recaptcha() {
    $this->load->helper('recaptcha');

    // Add custom theme so we can get only image
    $options = "<script>var RecaptchaOptions = {theme: 'custom', custom_theme_widget: 'recaptcha_widget'};</script>\n";

    // Get reCAPTCHA JS and non-JS HTML
    $html = recaptcha_get_html($this->config->item('recaptcha_public_key', 'tank_auth'), null, $this->config->item('enable_ssl_recaptcha', 'tank_auth'));

    return $options . $html;
  }

  /**
   * Callback function. Check if reCAPTCHA test is passed.
   *
   * @return  bool
   */
  function _check_recaptcha() {
    $this->load->helper('recaptcha');

    $resp = recaptcha_check_answer($this->config->item('recaptcha_private_key', 'tank_auth'),
                                   $_SERVER['REMOTE_ADDR'],
                                   $_POST['recaptcha_challenge_field'],
                                   $_POST['recaptcha_response_field']);

    if(!$resp->is_valid) {
      $this->form_validation->set_message('_check_recaptcha', $this->lang->line('auth_incorrect_captcha'));

      return false;
    }

    return true;
  }

  function popup($whcihone = 0) {
    /*
      1 - Login form
      2 - Login required to view
    */
    switch($whcihone) {
      case '1':
        $this->load->view('auth/hidden/loginformpopup');
        break;

      case '2':
        $this->load->view('auth/hidden/loginrequiredtext');
        break;

      default:
        break;
    }
  }

}




/* End of file auth.php */
/* Location: ./application/controllers/auth.php */
