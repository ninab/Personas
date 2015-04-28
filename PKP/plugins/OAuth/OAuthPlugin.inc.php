<?php 

import('classes.plugins.GenericPlugin'); 
// import('lib.pkp.classes.db.DAO');

class OAuthPlugin extends GenericPlugin { 
	/**
	 * Called as a plugin is registered to the registry
	 * @param $category String Name of category plugin was registered to
	 * @return boolean True iff plugin initialized successfully; if false,
	 * 	the plugin will not be registered.
	 */
	function register($category, $path) {
		$success = parent::register($category, $path);

		if ($success && (!$this->getEnabled())) {		
			// print __FILE__." on ".__LINE__." for ".__FUNCTION__."<br/>";		
		}
		// Handler for OAuth requests
		HookRegistry::register('Application::getRequest', array(&$this, 'setupOAuthHandler'));
		
		$application =& PKPApplication::getApplication();
		$products = $application->getEnabledProducts('plugins.generic');

		// print __FILE__." on ".__LINE__." for <pre>".print_r($this, TRUE)."</pre><br/>";		
		// print __FILE__." on ".__LINE__." for <pre>".print_r($_SERVER['PATH_INFO'], TRUE)."</pre><br/>";		
		$params = array_values(array_filter(explode("/", $_SERVER['PATH_INFO'])));
		
		if ($params[1] == 'oauth') {
			// print __FILE__." on ".__LINE__." for <pre>".print_r($params, TRUE)."</pre><br/>";				
			unset($params[0]);
			$this->setupOAuthHandler('OAuthPlugin', array_values($params));
		}
				
		return $success;
	}
	
	function getDriver() {
		return Config::getVar('database', 'driver');
	}
	
	function getHost() {
		return Config::getVar('database', 'host');
	}
	
	function getUsername() {
		return Config::getVar('database', 'username');
	}		
	
	function getPassword() {
		return Config::getVar('database', 'password');
	}

	function getDatabasename() {
		return Config::getVar('database', 'name');
	}	

	function getPersistent() {
		return Config::getVar('database', 'persistent') ? true : false;
	}		
	
	function &getConnection() {
		$conn = mysql_connect ($this->getHost(), $this->getUsername(), $this->getPassword());

		if (!$conn) {
			$this->errors[] = __('plugins.generic.openads.error.dbConnectionError');
			$returner = false;
			return $returner;
		}
		mysql_select_db ($this->getDatabasename(), $conn);
		return $conn;
	}	
	
	function getEnabled() {
		if (Config::getVar('general', 'installed')) return true;
		return parent::getEnabled();
	}	
	
	/**
	 * Install default settings on system install.
	 * @return string
	 */
	function getInstallSitePluginSettingsFile() {
		// print __FILE__." on ".__LINE__." for ".__FUNCTION__." and " . $this->getPluginPath() . "<br/>";
		return $this->getPluginPath() . '/settings.xml';
	}

	/**
	 * Install default settings on journal creation.
	 * @return string
	 */
	function getContextSpecificPluginSettingsFile() {
		// print __FILE__." on ".__LINE__." for ".__FUNCTION__."<br/>";
		return $this->getPluginPath() . '/settings.xml';
	}	
	
	function getName() {
		return 'OAuth';
	}

	/**
	 * Return the localized name of this plugin.
	 * @return string
	 */
	function getDisplayName() {
		return __('plugins.auth.OAuth.displayName');
	}

	/**
	 * Return the localized description of this plugin.
	 * @return string
	 */
	function getDescription() {
		return __('plugins.auth.OAuth.description');
	}

	// The main functionality
	//
	// Core Plugin Functions
	// (Must be implemented by every authentication plugin)
	//

	/**
	 * Returns an instance of the authentication plugin
	 * @param $settings array settings specific to this instance.
	 * @param $authId int identifier for this instance
	 * @return OAuthPlugin
	 */
	function &getInstance($settings, $authId) {
		$returner = new OAuthPlugin($settings, $authId);
		return $returner;
	}
	
	function setupOAuthHandler($hookName, $params) {
		$page =& $params[0];

		if ($page == 'oauth') {
			// consumer elements from OAuth library
			require_once(dirname(__FILE__).'/src/OAuth2/Autoloader.php');
			OAuth2\Autoloader::register();

			// register test classes
			OAuth2\Autoloader::register(dirname(__FILE__).'/lib');

			// print __FILE__." on ".__LINE__." for ".__FUNCTION__."<br/>";

			$connectionCharset = Config::getVar('i18n', 'connection_charset');
			$debug = Config::getVar('database', 'debug') ? true : false;
			$connectOnInit = true;
			$forceNew = false;	

			$dsn = $this->getDriver().':dbname='.$this->getDatabasename().';host='.$this->getHost();
	
			// server elements

			// $dsn is the Data Source Name for your database, for exmaple "mysql:dbname=my_oauth2_db;host=localhost"
			$storage = new OAuth2\Storage\Pdo(array('dsn' => $dsn, 'username' => $this->getUsername(), 'password' => $this->getPassword()));

			// Pass a storage object or array of storage objects to the OAuth2 server class
			$server = new OAuth2\Server($storage);

			// Add the "Client Credentials" grant type (it is the simplest of the grant types)
			$server->addGrantType(new OAuth2\GrantType\ClientCredentials($storage));

			// Add the "Authorization Code" grant type (this is where the oauth magic happens)
			$server->addGrantType(new OAuth2\GrantType\AuthorizationCode($storage));	

			$this->addLocaleData();		
			$op =& $params[1];

			if ($op) {
				// print __FILE__." on ".__LINE__." for ".__FUNCTION__." and " . print_r($op, TRUE) . "<br/>";		
				$OAuthFunctions  = array(
					'userExists',
					'getUserInfo',
					'getConsumerKey',
					'getLoginForm',
					'AuthorizeController',
					'TokenController',
					'ResourceController',					
					'getRequestToken',
					'getResourceToken',					
					'getAccessToken',										
				);
				
				// $this->{$methodName}($arg1, $arg2, $arg3);
				// $this->$methodName($arg1, $arg2, $arg3);
				// call_user_func_array(array($this, $methodName), array($arg1, $arg2, $arg3));
				
				if (in_array($op, $OAuthFunctions)) {
					$this->$op($server, $storage);	
				}
			}
		}
	}
	

	/**
	 * Authenticate a username and password.
	 * @param $username string
	 * @param $password string
	 * @return boolean true if authentication is successful
	 */
	function getAuthentication($username, $password) {
		// check to see if they are logged in
		$sessionManager =& SessionManager::getManager();
		$session =& $sessionManager->getUserSession();

		$userId = $session->getUserId();
		
		if (!isset($userId) || empty($userId)) {
			// display an authorization form
			if (empty($_POST)) {
			  exit('
				<form method="post">
				  <label>Do You Authorize TestClient?</label><br />
				  <input type="submit" name="authorized" value="yes">
				  <input type="submit" name="authorized" value="no">
				</form>');
			}
			else {
				$request = OAuth2\Request::createFromGlobals();
				$response = new OAuth2\Response();

				// validate the authorize request
				if (!$server->validateAuthorizeRequest($request, $response)) {
				    $response->send();
				    die;
				}			
			
				// print the authorization code if the user has authorized your client
				$is_authorized = ($_POST['authorized'] === 'yes');
				$server->handleAuthorizeRequest($request, $response, $is_authorized);
				if ($is_authorized) {
  					// this is only here so that you get to see your code in the cURL request. Otherwise, we'd redirect back to the client
  					$code = substr($response->getHttpHeader('Location'), strpos($response->getHttpHeader('Location'), 'code=')+5, 40);
  					exit("SUCCESS! Authorization Code: $code");
				}
				$response->send();			
			}
		}
		
		return isset($userId) && !empty($userId);	
	}	

	function getConsumerKey($server, $storage) {
		// get the consumer key to start the process
		// print __LINE__." - Hello world.";
		exit;	
	}
	
	// authorize elements
	function getLoginForm($server, $storage) {
		// redirect user to a login if the user is not logged in
		
	}
	
	function AuthorizeController($server, $storage) {
		$request = OAuth2\Request::createFromGlobals();
		$response = new OAuth2\Response();

		// validate the authorize request
		if (!$server->validateAuthorizeRequest($request, $response)) {
			$response->send();
			die;
		}
		// display an authorization form
		if (empty($_POST)) {
		  exit('
		<form method="post">
		  <label>Do You Authorize TestClient?</label><br />
		  <input type="submit" name="authorized" value="yes">
		  <input type="submit" name="authorized" value="no">
		</form>');
		}

		// print the authorization code if the user has authorized your client
		$is_authorized = ($_POST['authorized'] === 'yes');
		$server->handleAuthorizeRequest($request, $response, $is_authorized);
		if ($is_authorized) {
		  // this is only here so that you get to see your code in the cURL request. Otherwise, we'd redirect back to the client
		  $code = substr($response->getHttpHeader('Location'), strpos($response->getHttpHeader('Location'), 'code=')+5, 40);
		  exit("SUCCESS Authorization Code: $code");
		}
		$response->send();
	}


	function TokenController($server, $storage) {
		// Handle a request for an OAuth2.0 Access Token and send the response to the client
		$server->handleTokenRequest(OAuth2\Request::createFromGlobals())->send();
	}


	function ResourceController($server, $storage) {
		// Handle a request to a resource and authenticate the access token
		if (!$server->verifyResourceRequest(OAuth2\Request::createFromGlobals())) {
			$server->getResponse()->send();
			die;
		}
		echo json_encode(array('success' => true, 'message' => 'You accessed my APIs!'));
	}


	// token elements
	function getRequestToken($server, $storage) {
		// get the request token aka token request
		$server->handleTokenRequest(OAuth2\Request::createFromGlobals())->send();
	}
	

	function getResourceToken($server, $storage) {
		// Handle a request for an OAuth2.0 Access Token and send the response to the client
		if (!$server->verifyResourceRequest(OAuth2\Request::createFromGlobals())) {
			$server->getResponse()->send();
			die;
		}
		echo json_encode(array('success' => true, 'message' => 'You accessed my APIs!'));	
	}
	

	function getAccessToken($server, $storage) {
		// get the access token

		$request = OAuth2\Request::createFromGlobals();
		$response = new OAuth2\Response();

		// validate the authorize request
		if (!$server->validateAuthorizeRequest($request, $response)) {
    		$response->send();	
	    	die;
		}
	}

	//
	// Optional Plugin Functions
	//

	/**
	 * Check if a username exists.
	 * @param $username string
	 * @return boolean
	 */
	function userExists($username) {
		return false;
	}

	/**
	 * Retrieve user profile information from the LDAP server.
	 * @param $user User to update
	 * @return boolean true if successful
	 */
	function getUserInfo(&$user) {
		return false;
	}

	/**
	 * Store user profile information on the LDAP server.
	 * @param $user User to store
	 * @return boolean true if successful
	 */
	function setUserInfo(&$user) {
		$valid = false;
		if ($this->open()) {
			if ($entry = $this->getUserEntry($user->getUsername())) {
				$userdn = ldap_get_dn($this->conn, $entry);
				if ($this->bind($this->settings['managerdn'], $this->settings['managerpwd'])) {
					$attr = array();
					$this->userToAttr($user, $attr);
					$valid = ldap_modify($this->conn, $userdn, $attr);
				}
			}
			$this->close();
		}
		return $valid;
	}

	/**
	 * Change a user's password on the LDAP server.
	 * @param $username string user to update
	 * @param $password string the new password
	 * @return boolean true if successful
	 */
	function setUserPassword($username, $password) {	
		return false;
	}

	/**
	 * Create a user on the LDAP server.
	 * @param $user User to create
	 * @return boolean true if successful
	 */
	function createUser(&$user) {
		// creating users is outside of the scope of OAuth
		return false;
	}

	/**
	 * Delete a user from the LDAP server.
	 * @param $username string user to delete
	 * @return boolean true if successful
	 */
	function deleteUser($username) {
		// deleting users is outside of the scope of OAuth
		return false;
	}
	

	function getManagementVerbs() {
		$verbs = parent::getManagementVerbs();
		if (!$this->getEnabled()) return $verbs;
		$verbs[] = array(
			'settings', __('plugins.auth.OAuth.settings')
		);
		return $verbs;
	}

	function manage($verb, $args) {
		if (parent::manage($verb, $args)) return true;
		if (!$this->getEnabled()) return false;
		switch ($verb) {
			case 'settings':
				$journal =& Request::getJournal();
				$this->import('SettingsForm');
				$form = new SettingsForm($this, $journal->getId());
				if (Request::getUserVar('save')) {
					$form->readInputData();
					if ($form->validate()) {
						$form->execute();
						Request::redirect(null, null, 'plugins');
					} else {
						$form->display();
					}
				} else {
					$form->initData();
					$form->display();
				}
				break;
			default:
				return false;
		}
		return true;
	}
	
}