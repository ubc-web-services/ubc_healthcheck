<?php
/*
 * @author UCB IT WebServices, May 28th 2015
 *
 * This is a basic health check page that is monitored by SCOM.
 *
 * Checks if the database is reachable and we can run a query.
 * Checks if the default Drupal files folder is reachable and writeable.
 * Checks if a CLF theme is installed and checks for CLF CDN assets.
 *
 * SCOM checks for the STATUS_OK string to be present in the page.
 * The STATUS_OK string is included only if all health checks pass.
 *
 * @file
 * Contains \Drupal\healthcheck\Monitor\StatusMonitor.
 */
namespace Drupal\ubc_healthcheck\Monitor;
use Drupal\Core\Site\Settings;
define('STATUS_OK', 	'<!-- SCOM HEALTH CHECK STATUS OK -->');
define('STATUS_ERR', 	'<!-- SCOM HEALTH CHECK STATUS ERR -->');
define('CLF_ASSET_URL', 'http://cdn.ubc.ca/clf/7.0.4/js/ubc-clf.min.js');
class StatusMonitor {

  /*
   * Test the DB connection by querying the node table
   */
  function getDBStatus() {
    try {
      $result = db_query('SELECT COUNT(nid) AS ok FROM {node}')->fetch();
      return $result->ok;
    }
    catch (Exception $e) {
      return $e;
    }
    return FALSE;
  }
  /*
   * Return the status of the DB connection test
   * @return $html string
   */
  function setDBStatusHTML(&$db_status) {
    $html  = '<h3>DB Connection:</h3>';
    if(is_numeric($db_status)) {
      $html .= '<div class="check-ok">Connected</div>';
    }
    elseif(is_object($db_status)) {
      $html .= '<div class="check-down">PDOException</div>';
    }
    else {
      $html .= '<div class="check-down">Error</div>';
    }
    return $html;
  }
  /*
   * Test the File system
   */
  function getFilesStatus() {
    try {
      $files_path = Settings::get('file_public_path', \Drupal::service('site.path') . '/files');
      if(is_writable($files_path)) {
        return 1;
      }
    }
    catch(Exception $e) {
      return $e;
    }
    return FALSE;
  }
  /*
   * Return the status of the File directory test
   * @return $html string
   */
  function setFileStatusHTML(&$files_status) {
    $html  = '<h3>File System:</h3>';
    if(is_numeric($files_status)) {
      $html .= '<div class="check-ok">Writeable</div>';
    }
    elseif(is_object($files_status)) {
      $html .= '<div class="check-down">Exception</div>';
    }
    else {
      $html .= '<div class="check-down">Error</div>';
    }
    return $html;
  }
  /*
   * Checks if CLF is enabled and if CDN assets are available
   */
  function getThemeStatus() {
    try {
      //$themes = list_themes(TRUE);
      $themes = \Drupal::service('theme_handler')->listInfo();
    if(array_key_exists('galactus', $themes)) {
      if($themes['galactus']->status == 1) {
        return $this->galactusStatus();
      }
    }
    return -1;
    }
    catch(Exception $e) {
      return $e;
    }
    return FALSE;
  }
  /*
   * Return the status of the CLF Theme asset test
   * @return $html string
   */
  function setThemeStatus(&$theme_status) {
    $html  = '<h3>CLF THEME:</h3>';
    if(is_numeric($theme_status)) {
      if($theme_status == 1) $html .= '<div class="check-ok">CLF Assets Available</div>';
        if($theme_status == -1) $html .= '<div class="check-ok">CLF Theme Not Installed</div>';
    }
    elseif(is_object($theme_status)) {
      $html .= '<div class="check-down">Exception</div>';
    }
    else {
      $html .= '<div class="check-down">Error</div>';
    }
    return $html;
  }
  /*
   * Performs a curl request to see if CDN assets are reachable
   */
  function galactusStatus() {
    $curl = curl_init(CLF_ASSET_URL);
    curl_setopt($curl, CURLOPT_NOBODY, true);
    $result = curl_exec($curl);
    $ret = false;
    if($result !== false) {
      $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if($statusCode == 200) {
          $ret = 1;
        }
    }
    curl_close($curl);
    return $ret;
  }
  /*
   * Calculates script execution time
   */
  function insertTimerString($start, $end) {
    $total = $end - $start;
    return '<h4>Total Execution Time</h4><div>'.$total.' s</div>';
  }
  /*
   * Test all status checks and return SCOM status string
   */
  function insertStatusString($status_array) {
    foreach($status_array as $status) {
      if(!is_numeric($status)) return STATUS_ERR;
    }
    return STATUS_OK;
  }
  /*
   * Sends an email to the test address
   */
  function sendEmail() {
      $test_address = "alice828ubc@gmail.com";
      $params['subject'] = t('Greetings!');
      $params['body'] = array(t('Greetings! If you receive this message it means your site is capable of using SMTP to send e-mail.'));
      $account = \Drupal::currentUser();
      \Drupal::service('plugin.manager.mail')->mail('smtp', 'smtp-test', $test_address, 'en', $params);
      drupal_set_message(t('A test e-mail has been sent to @email via SMTP. You may want to check the log for any error messages.', ['@email' => $test_address]));
  }
  /*
   * Performs health checks and returns page html
   */
  public function content() {
    # do not track this script via New Relic
  	if(extension_loaded('newrelic')) {
      newrelic_ignore_transaction();
      newrelic_disable_autorum();
    }
    $start = microtime();
    # we never want to cache the results of the check
    //drupal_page_is_cacheable(FALSE);
    $build['#cache']['max-age'] = 0;
    $db_status = $this->getDBStatus();
    $files_status = $this->getFilesStatus();
    $theme_status = $this->getThemeStatus();
    # sends a test email if arg == 1
    if($_GET["email"] == 1) {
      $this->sendEmail();
    } else {
      drupal_set_message(t('A test email has not been sent. Invalid argument.'), 'error');
    }
    # do other types of status checks here
    $html = '<div id="statuses">';
    $html .= $this->setDBStatusHTML($db_status);
    $html .= $this->setFileStatusHTML($files_status);
    $html .= $this->setThemeStatus($theme_status);
    $html .= '</div>';
    $end = microtime();
    $html .= $this->insertTimerString($start, $end);
    $html .= $this->insertStatusString(array($db_status, $files_status, $theme_status));
    return array(
      '#markup' => $html
    );
  }
  /*
   * Displays which host the site is being served from
   */
  public function host() {
    $html = '';
    if (getenv('BASE_URL') != FALSE) {
      $html = "<h3>Hosted on Platform.sh</h3>";
    }
    else {
      $html = "<h3>Hosted at UBC</h3>";
    }
    return array(
      '#markup' => $html
    );
  }
}
