<?php if(!defined('BASEPATH')) {
  exit('No direct script access allowed');
}

use OIX\Util\Logger;

class Oauthclient extends CI_Controller {
  protected $OAuthClientSvc;
  protected $logger;

  function __construct() {
    parent::__construct();

    $this->load->helper(['form', 'url', 'datedown', 'auth']);
    $this->load->library('form_validation');
    $this->load->model('Members_Model');
    $this->load->model('DmaAccounts');
    $this->load->model('Oauthclient_Model');
    $this->load->library('tank_auth');
    $this->lang->load('tank_auth');

    $this->logger = Logger::getInstance();

    if(version_compare(CI_VERSION, '2.1.0', '<')) {
      $this->load->library('security');
    }
    $this->OAuthClientSvc = new \OIX\Services\OAuthClientService();
  }

  function add() {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {
      if(!$this->cisession->userdata('levelManageSuperAdmins')) {
        redirect('/dmamembers/no_permission');
      } else {

        $data['current_tab_key'] = "";
        $data['lnav_key'] = "administrative";

        $data['dma_members'] = $this->Oauthclient_Model->get_dma_members_by_userid($this->cisession->userdata('userId'));

        $data['planData'] = $this->cisession->userdata('planData');
        if(!isset($data['planData']['api']) && !isset($data['planData']['allFeatures'])) {
          $data['tabArray'] = "api_settings_noaccess";
        }

        $this->load->view('includes/left_nav', $data);
        if(!isset($data['planData']['api']) && !isset($data['planData']['allFeatures'])) {
          $this->load->view('includes/widgets/planBlockMessage');
        } else {
          $this->load->view('oauthclient/add', $data);
        }
        $this->load->view('includes/footer-2');
      }
    }
  }

  function edit($apikey_id) {
    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {
      if(!$this->cisession->userdata('levelManageSuperAdmins')) {
        redirect('/dmamembers/no_permission');
      } else {

        $data['current_tab_key'] = "";
        $data['lnav_key'] = "administrative";

        $data['api_setting'] = $this->Oauthclient_Model->get_client($apikey_id);

        $data['planData'] = $this->cisession->userdata('planData');
        if(!isset($data['planData']['api']) && !isset($data['planData']['allFeatures'])) {
          $data['tabArray'] = "api_settings_noaccess";
        }

        $this->load->view('includes/left_nav', $data);
        if(!isset($data['planData']['api']) && !isset($data['planData']['allFeatures'])) {
          $this->load->view('includes/widgets/planBlockMessage');
        } else {
          $this->load->view('oauthclient/edit', $data);
        }
        $this->load->view('includes/footer-2');
      }
    }
  }

  function list_clients() {

    //Authentication Check & Workflow
    if(!$this->tank_auth->is_logged_in() || $this->cisession->userdata('ipVerified') != 1 || ($this->cisession->userdata('isDMA') && $this->cisession->userdata('dmaId') == "")) {
      $this->load->view('includes/loginDeepLinkModule');
    } else {

      if(!$this->cisession->userdata('levelManageSuperAdmins')) {
        redirect('/dmamembers/no_permission');
      } else {

        $data['tabArray'] = "api_settings";
        $data['current_tab_key'] = "api_settings";
        $data['lnav_key'] = "administrative";

        $data['api_settings'] = $this->Oauthclient_Model->get_all_clients($this->cisession->userdata('dmaId'));

        $data['planData'] = $this->cisession->userdata('planData');
        if(!isset($data['planData']['api']) && !isset($data['planData']['allFeatures'])) {
          $data['tabArray'] = "api_settings_noaccess";
        }

        $this->load->view('includes/left_nav', $data);
        $this->load->view('includes/tab_nav', $data);
        if(!isset($data['planData']['api']) && !isset($data['planData']['allFeatures'])) {
          $this->load->view('includes/widgets/planBlockMessage');
        } else {
          $this->load->view('oauthclient/list_clients', $data);
        }
        $this->load->view('includes/footer-2');

      }
    }

  }

  /* ----------------------------------------------------------------------------
   * ------------------------------ AJAX ROUTES ---------------------------------
   * ----------------------------------------------------------------------------
  */

  public function generate_client() {
    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $creds = [
        'dmaMemberId'   => $_POST['member_id'],
        'client_id'     => $this->OAuthClientSvc->generateClientId(),
        'client_secret' => $this->OAuthClientSvc->generateClientSecret(),
    ];

    echo json_encode($creds);

    return true;
  }

  public function add_client() {
    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    /* TODO: we should hash the secret here using a shared algo between laravel and CI
    $hasher = new PasswordHash(
        $this->config->item('phpass_hash_strength'),
        $this->config->item('phpass_hash_portable')
    );
    */

    $exp_time = $_POST['exp_time'];
    if(empty($exp_time)) {
      $exp_time = null;
    }

    $dmaMemeber = $this->Members_Model->get_dma_member($_POST['member_id']);

    $options = [
        'cost' => 11,
    ];
    $hashedSecret = password_hash($_POST['client_secret'], PASSWORD_BCRYPT, $options);

    $this->Oauthclient_Model->add_client(
        [
            'dma_id'                   => $dmaMemeber['dmaId'],
            'user_id'                  => $dmaMemeber['userId'],
            'id'                       => $_POST['client_id'],
            'secret'                   => $hashedSecret,
            'token_expiration_seconds' => $exp_time,
            'redirect'                 => $_POST['callback_url'],
            'name'                     => $_POST['client_name'],
            'created_at'               => date('Y-m-d H:i:s'),
            'updated_at'               => date('Y-m-d H:i:s'),
        ]
    );

    $status = true;

    $json = json_encode($status);
    echo $json;
  }

  /**
   * Update token expiration and allowed domains for API key
   * */
  public function update_client() {
    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $exp_time = $_POST['exp_time'];
    if(empty($exp_time)) {
      $exp_time = null;
    }

    $this->Oauthclient_Model->update_client(
        [
            'id'                       => $_POST['client_id'],
            'token_expiration_seconds' => $exp_time,
            'redirect'                 => $_POST['callback_url'],
            'name'                     => $_POST['client_name'],
            'updated_at'               => date('Y-m-d H:i:s'),
        ]
    );

    $status = true;

    $json = json_encode($status);
    echo $json;
  }

  function revoke_client() {
    if(!$this->tank_auth->is_logged_in()) {
      redirect('/');
    }

    $apikey_id = $this->input->post('apikey_id');
    $this->Oauthclient_Model->revoke_client($apikey_id);

    $status = true;

    $json = json_encode($status);
    echo $json;
  }
}

