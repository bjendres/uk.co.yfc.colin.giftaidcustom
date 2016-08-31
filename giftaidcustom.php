<?php
/*-------------------------------------------------------+
| Youth for Christ GiftAid Customisations                |
| Copyright (C) 2016 SYSTOPIA                            |
| Author: B. Endres endres@systopia.de                   |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL v3 license. You can redistribute it and/or  |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

require_once 'giftaidcustom.civix.php';

function giftaidcustom_civicrm_giftAidEligible(&$isEligible, $contactId, $date, $contributionId) {
  // we will only further restrict eligibility, if not eligible to begin with we do nothing
  if ($isEligible) {
    // LOAD contribution
    $contribution_query = "SELECT payment_instrument_id, total_amount, receive_date FROM civicrm_contribution WHERE id = %1";
    $contribution_spec = array(1 => array($contributionId, 'Integer'));
    $contribution_data = CRM_Core_DAO::executeQuery($contribution_query, $contribution_spec);
    $contribution_data->fetch();

    // exclude Stewardship payments
    if ($contribution_data->payment_instrument_id == 18) {
      $isEligible = FALSE;
      CRM_Civigiftaid_Utils_Rejection::setRejectionReason($contributionId, "Stewardship payments are excluded");
      return;
    }

    // HRMC seems to reject amounts of less that 1ct
    if ($contribution_data->total_amount < 0.01) {
      $isEligible = FALSE;
      CRM_Civigiftaid_Utils_Rejection::setRejectionReason($contributionId, "Donation is less than 1ct");
      return;
    }

    // LOAD contact
    $contact_query = "SELECT contact_type, first_name, last_name, is_deleted FROM civicrm_contact WHERE id = %1";
    $contact_spec = array(1 => array($contactId, 'Integer'));
    $contact_data = CRM_Core_DAO::executeQuery($contact_query, $contact_spec);
    $contact_data->fetch();

    // HRMC seems to only accept names with 1-50 characters
    $attributes = array('first_name', 'last_name');
    foreach ($attributes as $attribute) {
      if (empty($contact_data->$attribute) || !(preg_match('#^[A-Z \'.-]{1,50}$#i', $contact_data->$attribute))) {
        $isEligible = FALSE;
        CRM_Civigiftaid_Utils_Rejection::setRejectionReason($contributionId, "Name (length) invalid");
        return;
      }
    }
    
    // check if contact is deleted
    if (!empty($contact_data->is_deleted)) {
      $isEligible = FALSE;
      CRM_Civigiftaid_Utils_Rejection::setRejectionReason($contributionId, "Contact is deleted");
      return;
    }

    
    // LOAD GA declaration
    $declaration = CRM_Civigiftaid_Utils_GiftAid::getDeclaration($contactId, $contribution_data->receive_date, NULL);

    // check address line
    if (empty($declaration['address'])) {
      $isEligible = FALSE;
      CRM_Civigiftaid_Utils_Rejection::setRejectionReason($contributionId, "Empty address line");
      return;      
    }

    // check post code (copied from uk.co.vedaconsulting.module.giftaidonline/govtalk/HmrcGiftAid.php)
    $cleanPostcode = preg_replace("/[^A-Za-z0-9]/", '', $declaration['post_code']);
    $cleanPostcode = strtoupper($cleanPostcode);
    $postcode = substr($cleanPostcode, 0, -3) . " " . substr($cleanPostcode, -3);
    $postcode = strtoupper(str_replace(' ','', $postcode));
    if (  preg_match("/^[A-Z]{1,2}[0-9]{2,3}[A-Z]{2}$/", $postcode) 
       || preg_match("/^[A-Z]{1,2}[0-9]{1}[A-Z]{1}[0-9]{1}[A-Z]{2}$/",$postcode) 
       || preg_match("/^GIR0[A-Z]{2}$/", $postcode)) {
      // THEN: this is a valid postcode
    } else {
      $isEligible = FALSE;
      CRM_Civigiftaid_Utils_Rejection::setRejectionReason($contributionId, "Invalid postal code");
      return;      
    }
  }
}


/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function giftaidcustom_civicrm_config(&$config) {
  _giftaidcustom_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @param array $files
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function giftaidcustom_civicrm_xmlMenu(&$files) {
  _giftaidcustom_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function giftaidcustom_civicrm_install() {
  _giftaidcustom_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function giftaidcustom_civicrm_uninstall() {
  _giftaidcustom_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function giftaidcustom_civicrm_enable() {
  _giftaidcustom_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function giftaidcustom_civicrm_disable() {
  _giftaidcustom_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed
 *   Based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function giftaidcustom_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _giftaidcustom_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function giftaidcustom_civicrm_managed(&$entities) {
  _giftaidcustom_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * @param array $caseTypes
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function giftaidcustom_civicrm_caseTypes(&$caseTypes) {
  _giftaidcustom_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function giftaidcustom_civicrm_angularModules(&$angularModules) {
_giftaidcustom_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function giftaidcustom_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _giftaidcustom_civix_civicrm_alterSettingsFolders($metaDataFolders);
}
