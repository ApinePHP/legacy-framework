<?php
/**
 * This file contains the session management class for RESTful APIs
 * 
 * @license MIT
 * @copyright 2015 Tommy Teasdale
 */

/**
 * Gestion and configuration of the a user session on a RESTful service
 * This class manages user login and logout
 */
final class ApineAPISession implements ApineSessionInterface{
	
	/**
	 * Session token for currently logged in user
	 * 
	 * @var ApineUserToken
	 */
	private $token;
	
	/**
	 * Is a user logged in or not
	 * 
	 * @var boolean
	 */
	private $logged_in = false;
	
	/**
	 * Token duration
	 * 
	 * @var integer
	 */
	private $token_lifespan = 600;
	
	/**
	 * Type of the current user
	 * 
	 * @var integer
	 */
	private $session_type = APINE_SESSION_GUEST;
	
	/**
	 * Construct the session handler
	 * Fetch data from request headers and authenticate the user
	 */
	public function __construct() {
		
		if (is_null(ApineConfig::get('runtime', 'token_lifespan'))) {
			$this->token_lifespan = ApineConfig::get('runtime', 'token_lifespan');
		}
		
		if (isset(ApineRequest::get_request_headers()['Authorization'])) {
		
			$token_id = ApineUserTokenFactory::authentication($name, $token, $this->token_lifespan);
			$token = ApineUserTokenFactory::create_by_id($token_id);
			
			if ($token_id && $token->get_origin() == ApineRequest::server()['HTTP_REFERER'].ApineRequest::server()['HTTP_USER_AGENT']) {
				$this->logged_in = true;
				$this->token = $token;
				$this->session_type = $this->token->get_user()->get_type();
				
				$this->token->set_last_access_date(date('d M Y H:i:s',time() + $this->token_lifespan));
				$this->token->save();
			}
		
		}
		
	}
	
	/**
	 * Get the unique login token string
	 *
	 * @return string
	 */
	public function get_session_identifier () {
		
		return ($this->is_logged_in()) ? $this->token->get_token() : 0; 
		
	}
	
	/**
	 * Get the login token
	 * 
	 * @return ApineUserToken
	 */
	public function get_token () {
		
		return ($this->is_logged_in()) ? $this->token : 0;
		
	}

	/**
	 * Verifies if a user is logged in
	 *
	 * @return boolean
	 */
	public function is_logged_in () {
		
		return (boolean) $this->logged_in;

	}

	/**
	 * Get logged in user
	 *
	 * @return ApineUser
	 */
	public function get_user () {
		
		if ($this->is_logged_in()) {
			return $this->token->get_user();
		}

	}

	/**
	 * Get logged in user's id
	 *
	 * @return integer
	 */
	public function get_user_id () {
		
		if ($this->is_logged_in()) {
			return $this->token->get_user()->get_id();
		}

	}

	/**
	 * Get current session access level
	 *
	 * @return integer
	 */
	public function get_session_type () {
		
		return $this->session_type;

	}

	/**
	 * Set current session access level
	 *
	 * @param integer $a_type
	 *        Session access level type
	 */
	public function set_session_type ($a_type) {
		
		$constants = get_defined_constants(true);
		$constants = $constants['user'];
		$type = false;
		
		foreach ($constants as $name => $value) {
			if(strstr($name, 'APINE_SESSION') && $value == $a_type) {
				$type = $a_type;
				$this->session_type = $a_type;
			}
		}
		
		return $type;

	}

	/**
	 * Log a user in
	 * Look up in database for a matching row with a username and a
	 * password
	 *
	 * @param string $a_user_name
	 *        Username of the user
	 * @param string $a_password
	 *        Password of the user
	 * @return boolean
	 */
	public function login ($a_user_name, $a_password) {
		
		if (!$this->is_logged_in()) {
			/*if ((ApineUserFactory::is_name_exist($a_user_name) || ApineUserFactory::is_email_exist($a_user_name)) && ApineUserFactory::create_by_name($a_user_name)->get_register_date() < "2015-09-04") {
				$encode_pass = hash('sha256', $a_password);
			} else {*/
				$encode_pass = ApineEncryption::hash_password($a_password, ApineUserFactory::create_by_name($a_user_name)->get_username());
			//}
			
			$user_id = ApineUserFactory::authentication($a_user_name, $encode_pass);
			
			if ($user_id) {
				$creation_time = time();
				$new_user_token = new ApineUserToken();
				$new_user_token->set_user($user_id);
				$new_user_token->set_token(ApineEncryption::hash_api_user_token($a_user_name, $a_password, $creation_time));
				$new_user_token->set_creation_date($creation_time);
				
				$this->token = $new_user_token;
				$this->set_session_type($this->token->get_user()->get_type());
				$this->logged_in = true;
				
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}

	}
	
	public function get_expiration_time () {
		
		return date('d M Y H:i:s', strtotime($this->token->get_last_access_date()) + $this->token_lifespan);
		
	}

	/**
	 * Log a user out
	 */
	public function logout () {
		
		if (!$this->is_logged_in()) {
			$this->token->disable();
			$this->token->save();
			$return = true;
		} else {
			$return = false;
		}
		
		return $return;
		
	}

}