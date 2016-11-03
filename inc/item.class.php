<?php
/*
 * @version $Id$
 LICENSE

  This file is part of the openvas plugin.

 OpenVAS plugin is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 openvas plugin is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI; along with openvas. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 @package   openvas
 @author    Teclib'
 @copyright Copyright (c) 2016 Teclib'
 @license   GPLv2+
            http://www.gnu.org/licenses/gpl.txt
 @link      https://github.com/pluginsglpi/openvas
 @link      http://www.glpi-project.org/
 @link      http://www.teclib-edition.com/
 @since     2016
 ---------------------------------------------------------------------- */

if (!defined('GLPI_ROOT')){
   die("Sorry. You can't access directly to this file");
}

class PluginOpenvasItem extends CommonDBTM {
   public $dohistory       = true;

   static $rightname     = 'config';
   static $host_matching = [];

   public static function getTypeName($nb = 0) {
      return __("Openvas", 'openvas');
   }

   function post_updateItem($history = 1) {
      if (isset($this->oldvalues) && isset($this->oldvalues['openvas_id'])) {
         self::updateItemFromOpenvas($this->getID());
      }
   }

   /**
    * @see CommonGLPI::getTabNameForItem()
   **/
   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

      $itemtype = $item->getType();

      // can exists for template
      if ($itemtype::canView()) {
         $nb = countElementsInTable('glpi_plugin_openvas_items',
                                    [ 'itemtype' => $item->getType(),
                                      'items_id' => $item->getID()
                                    ]);
         return self::createTabEntry(self::getTypeName(Session::getPluralNumber()), $nb);
      }
   }


   /**
    * @param $item            CommonGLPI object
    * @param $tabnum          (default 1)
    * @param $withtemplate    (default 0)
    */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
      $openvas_item = new self();
      $openvas_item->getFromDBForItem($item->getType(), $item->getID());

      self::showForItem($item, $openvas_item);
      self::showTasksForATarget($item, $openvas_item);
      return true;
   }

   function getFromDBForItem($itemtype, $items_id) {
      global $DB;

      $iterator = $DB->request('glpi_plugin_openvas_items',
                               [ 'itemtype' => $itemtype, 'items_id' => $items_id,
                                 'FIELDS' => [ 'id' ]
                               ]);
      if ($result = $iterator->next()) {
         $this->getFromDB($result['id']);
         return true;
      } else {
         $this->getEmpty();
         return false;
      }
   }

   public static function showForItem(CommonDBTM $item, PluginOpenvasItem $openvas_item) {
      global $CFG_GLPI;
      if (isset($openvas_item->fields['id'])) {
         $id = $openvas_item->getID();
      } else {
         $id = 0;
      }

      $form_url = $openvas_item->getFormURL().'?id='.$id.'&itemtype='
                    .$item->getType().'&items_id='.$item->getID();
      $options = array('candel' => false,
                       'formtitle'   => __("OpenVAS", "openvas"),
                       'target' => $form_url, 'colspan' => 4);
      $openvas_item->showFormHeader($options);

      echo "<tr class='tab_bg_1' align='center'>";
      echo "<td>" . __("OpenVAS Target", "openvas") . "</td>";
      echo "<td>";
      PluginOpenvasOmp::dropdownTargets('openvas_id', $openvas_item->fields['openvas_id']);
      if ($openvas_item->fields['openvas_id']) {
         $link = PluginOpenvasConfig::getConsoleURL();
         $link.= "?cmd=get_target&target_id=".$openvas_item->fields['openvas_id'];
         echo "&nbsp;<a href='$link' target='_blank'>";
         echo "<img src='".$CFG_GLPI["root_doc"]."/pics/web.png' class='middle' alt=\""
            .__('View in OpenVAS', 'openvas')."\" title=\""
            .__('View in OpenVAS', 'openvas')."\" >";
         echo "</a></td>";
      }

      $openvas_item->showFormButtons($options);

      echo "<br/>";
      if ($openvas_item->fields['openvas_id']) {
         echo "<form name='formtasks' method='post' action='$form_url&refresh' enctype=\"multipart/form-data\">";

         echo "<input type='hidden' name='id' value='".$openvas_item->fields['id']."'>";

         echo "<div class='spaced' id='tabsbody'>";
         echo "<table class='tab_cadre_fixe' id='taskformtable'>";
         echo "<th colspan='4'>".__('Target Infos', 'openvas')."</th></tr>";

         echo "<tr class='tab_bg_1' align='center'>";
         echo "<td>" . __("Severity", "openvas") . "</td>";
         echo "<td>";
         if ($openvas_item->fields['openvas_severity'] >= 0) {
            echo $openvas_item->fields['openvas_severity'];
         } else {
            echo __('Error');
         }
         echo "<td>" . __("Date of last scan", "openvas") . "</td>";
         echo "<td>";
         echo Html::convDateTime($openvas_item->fields['openvas_date_last_scan']);
         echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1' align='center'>";
         echo "<td>" . __("Target UUID", "openvas") . "</td>";
         echo "<td>";
         echo $openvas_item->fields['openvas_id'];
         echo "</td>";
         echo "<td>";
         if (PluginOpenvasOmp::ping()) {
            echo Html::submit( __('Synchronize'),
                              array('name'  => 'refresh',
                                    'image' => $CFG_GLPI["root_doc"].'/pics/web.png'));
         }
         echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1' align='center'>";
         echo "<td>" . __("Name") . "</td>";
         echo "<td>";
         echo $openvas_item->fields['openvas_name'];
         echo "</td>";
         echo "<td>" . __("Comment") . "</td>";
         echo "<td>".$openvas_item->fields['openvas_comment']."</td>";
         echo "</tr>";

         echo "</table>";

         $tasks = PluginOpenvasOmp::getTasksForATarget($openvas_item->fields['openvas_id']);
         if (is_array($tasks) && !empty($tasks)) {
            echo "<table class='tab_cadre_fixe' id='taskformtable'>";
            echo "<tr class='tab_bg_1' align='center'>";
            echo "<th colspan='4'>".__('OpenVAS tasks', 'openvas')."</th></tr>";
            echo "<tr class='tab_bg_1' align='center'>";
            echo "<th>".__('Name')."</th><th>"
               .__('State')."</th><th>"
               .__('Severity', 'openvas')."</th><th>"
               .__("Date last scan", 'openvas')."</th></tr>";
            foreach ($tasks as $task_id => $task) {
               echo "<tr class='tab_bg_1' align='center'>";
               $link = PluginOpenvasConfig::getConsoleURL();
               $link.= "?cmd=get_task&task_id=".$task_id;
               echo "<td><a href='$link' target='_blank'>".$task['name']."</a></td>";
               echo "<td>".$task['status']."</td>";
               echo "<td>".$task['severity']."</td>";
               echo "<td>".$task['date_last_scan']."</td>";
               echo "</tr>";
            }
            echo "</table>";
         }
         echo "</div>";
         Html::closeForm();
      }
   }

   function getFromDBByID($itemtype, $items_id) {
      global $DB;

      $iterator = $DB->request('glpi_plugin_openvas_items',
                               [ 'AND'   => [ 'itemtype' => $itemtype,
                                              'items_id' => $items_id
                                            ],
                                 'LIMIT' => 1
                              ]);
      if (!$iterator->numrows()) {
         return false;
      } else {
         $this->fields = $iterator->next();
         return true;
      }
   }

   /**
   * Display informations about OpenVAS
   *
   * @param $item the CommonDBTM item
   */
   static function showInfo(CommonDBTM $item) {
      global $CFG_GLPI;

      $detail = new self();
      if (!$detail->getFromDBByID(get_class($item), $item->getID())) {
         return true;
      }

      echo '<table class="tab_glpi" width="100%">';
      echo '<tr>';
      echo '<th colspan="2">'.__('OpenVAS', 'openvas').'</th>';
      echo '</tr>';

      echo '<tr class="tab_bg_1">';
      echo '<td>';
      echo __('Severity', 'openvas');
      echo '</td>';
      echo '<td>';
      echo $detail->fields['openvas_severity'];
      echo '</td>';
      echo '</tr>';

      echo "<tr class='tab_bg_1'>";
      echo "<td>" . __("Date last scan", 'openvas') . "</td>";
      echo "<td>";
      echo Html::convDateTime($detail->fields['openvas_date_last_scan']);
      echo "</td>";
      echo '</tr>';

      echo '</table>';
   }


   /**
   * Update device informations in GLPi by directly requesting OpenVAS
   *
   * @since 1.0
   * @param $target_id the target UUID in OpenVAS
   * @return boolean the update status
   */
   public static function updateItemFromOpenvas($openvas_line_id) {
      $item = new PluginOpenvasItem();
      $item->getFromDB($openvas_line_id);

      //Get the target
      $target = PluginOpenvasOmp::getOneTargetsDetail($item->fields['openvas_id']);

      //If no target, do not go further
      if (is_array($target) && !empty($target)) {
         //Sync target infos
         $tmp = [ 'openvas_name'    => $target['name'],
                  'openvas_host'    => $target['host'],
                  'openvas_comment' => $target['comment']
               ];

         self::updateTaskInfosForTarget($item->fields['openvas_id'], $openvas_line_id);
         return true;
      } else {
         return false;
      }
   }

   public static function showTasksForATarget(CommonDBTM $item, PluginOpenvasItem $openvas_item) {
      if (isset($openvas_item->fields['id'])) {
         $tasks = PluginOpenvasOmp::getTasksForATarget($openvas_item->fields['openvas_id']);
      }
   }

   /**
   * Try to get an OpenVAS target by it's host
   * @since 1.0
   * @param $host the host as provided by OpenVAS (in general an IP address)
   * @return an array representing a PluginOpenvasItem or false if none found
   */
   public static function getItemByHost($host) {
      global $DB;

      //If host is already in the cache
      if (isset(self::$host_matching[$host])) {
         return self::$host_matching[$host];
      }

      //First: check if the host provided is already associated with an asset
      $iterator = $DB->request('glpi_plugin_openvas_items',
                               [ 'FIELDS' => [ 'itemtype', 'items_id'],
                                 'OR'     => [ 'openvas_host' => $host, 'openvas_name' => $host]
                               ]);
      if ($iterator->numrows()) {
         $tmp = $iterator->next();
         self::$host_matching[$host] = [ 'itemtype' => $tmp['itemtype'],
                                         'items_id' => $tmp['items_id']
                                       ];
         return self::$host_matching[$host];
      } else {
         //Second step: check if the host refers to an IP address
         $iterator_ip = $DB->request('glpi_ipaddresses', [ 'name' => $host] );
         if ($iterator_ip->numrows()) {
            $tmp = $iterator_ip->next();
            self::$host_matching[$host] = [ 'itemtype' => $tmp['mainitemtype'],
                                            'items_id' => $tmp['mainitems_id']
                                          ];
            return self::$host_matching[$host];
         }
         return false;
      }
   }

   /**
   * Import or update data coming from OpenVAS
   * @since 1.0
   */
   static function cronOpenvasSynchronize($task) {
      global $DB, $CFG_GLPI;

      $item = new self();
      //Total of export lines
      $index = 0;

      $response = PluginOpenvasOmp::getTargets();
      foreach ($response->target as $target) {
         $tmp = array();

         //Do not process target without host,
         //or 127.0.0.1 or localhost (to large to match a specific asset)
         if (!isset($target->hosts)
            || $target->hosts->__toString() == '127.0.0.1'
               || $target->hosts->__toString() == 'localhost') {
            continue;
         }

         //Get openvas UUID
         $openvas_id = $target->attributes()->id->__toString();

         $tmp = [ 'openvas_host'    => $target->hosts->__toString(),
                  'openvas_name'    => $target->name->__toString(),
                  'openvas_id'      => $target->attributes()->id->__toString(),
                  'openvas_comment' => $target->comment->__toString()
                ];

         //Check if the host is already linked to a GLPi asset
         $iterator = $DB->request('glpi_plugin_openvas_items', ['openvas_id' => $openvas_id]);
         if (!$iterator->numrows()) {
            //Not linked: check if a link could be done
            if ($asset = self::getItemByHost($tmp['host'])) {
               //Link the host to the asset
               $tmp['itemtype'] = $asset['itemtype'];
               $tmp['items_id'] = $asset['items_id'];
               if ($tmp['id'] = $item->add($tmp)) {
                  $index++;
               }
            } else {
               foreach ($CFG_GLPI['networkport_types'] as $itemtype) {
                  $table = getTableForItemtype($itemtype);
                  if (FieldExists($table, 'domains_id')) {
                     $concat    = "CONCAT_WS('.', `$table`.`name`, `glpi_domains`.`name`)";
                     $left_join = "LEFT JOIN `glpi_domains`
                                      ON `glpi_domains`.`id`=`$table`.`domains_id`";
                  } else {
                     $concat    = "`$table`.`name`";
                     $left_join = "";
                  }
                  $query = "SELECT `$table`.`id`, $concat AS `fqdn`
                            FROM `$table` $left_join
                            HAVING `fqdn`='".$tmp['openvas_host']."'";
                  $iterator_fqdn = $DB->request($query);
                  if ($iterator_fqdn->numrows()) {
                     $asset = $iterator_fqdn->next();
                     $tmp['itemtype'] = $itemtype;
                     $tmp['items_id'] = $asset['id'];
                     if ($tmp['id'] = $item->add($tmp)) {
                        $index++;
                     }
                     //Host found, exit loop
                     if ($tmp['id']) {
                        break;
                     }
                  }
               }
            }
         } else {
            //The host was already linked to an asset: update the line in DB
            $current = $iterator->next();
            $tmp['id'] = $current['id'];
            if ($item->update($tmp)) {
               $index++;
            }
         }

         //If the host is linked to an asset: update last task infos
         if (isset($tmp['id'])) {
            self::updateTaskInfosForTarget($tmp['openvas_id'], $tmp['id']);
         }
      }

      $task->addVolume($index);
      return true;
   }

   static function updateTaskInfosForTarget($openvas_id, $line_id) {
      //Get tasks for this target
      $ovtasks = PluginOpenvasOmp::getTasksForATarget($openvas_id);
      if (is_array($ovtasks) && !empty($ovtasks)) {
         $item = new self();
         //Get the last task
         $ovtask = array_pop($ovtasks);
         $tmp    = [ 'openvas_severity'       => $ovtask['severity'],
                     'openvas_date_last_scan' => $ovtask['date_last_scan'],
                     'id'                     => $line_id
                 ];
         $item->update($tmp);
      }
   }
   /**
   * Clean informations that are too old, and not relevant anymore
   * @since 1.0
   * @return the number of targets deleted
   */
   static function cronOpenvasClean($task) {
      global $DB;

      $config = PluginOpenvasConfig::getInstance();
      $item   = new self();

      $index = 0;

      //TODO to replace by a non SQL query when dbiterator will be able to handle the query
      $query = "SELECT `id`
                FROM `glpi_plugin_openvas_items`
                WHERE `date_mod` < DATE_ADD(CURDATE(), INTERVAL -".$config->fields['retention_delay']." DAY)";
      foreach ($DB->request($query) as $target) {
         if ($item->delete($target, true)) {
            $index++;
         }
      }
      $task->addVolume($index);
      return true;
   }

   static function cronInfo($name) {
      return array('description' => __("OpenVAS connector synchronization", "openvas"));
   }

   //----------------- Install & uninstall -------------------//
   public static function install(Migration $migration) {
      global $DB;

      //This class is available since version 1.3.0
      if (!TableExists("glpi_plugin_openvas_items")) {
         $migration->displayMessage("Install glpi_plugin_openvas_items");

         $config = new self();

         //Install
         $query = "CREATE TABLE `glpi_plugin_openvas_items` (
                     `id` int(11) NOT NULL auto_increment,
                     `name` varchar(255) character set utf8 collate utf8_unicode_ci NOT NULL,
                     `itemtype` varchar(255) character set utf8 collate utf8_unicode_ci NOT NULL,
                     `items_id` int(11) NOT NULL DEFAULT '0',
                     `openvas_id` varchar(255) character set utf8 collate utf8_unicode_ci NOT NULL,
                     `openvas_name` varchar(255) character set utf8 collate utf8_unicode_ci NOT NULL,
                     `openvas_host` varchar(255) character set utf8 collate utf8_unicode_ci NOT NULL,
                     `openvas_comment` text COLLATE utf8_unicode_ci,
                     `openvas_severity` float(11) NOT NULL DEFAULT '0',
                     `openvas_date_last_scan` varchar(255) character set utf8 collate utf8_unicode_ci NOT NULL,
                     `date_creation` datetime DEFAULT NULL,
                     `date_mod` datetime DEFAULT NULL,
                     PRIMARY KEY  (`id`),
                     KEY `name` (`name`),
                     KEY `item` (`itemtype`,`items_id`),
                     KEY `openvas_id` (`openvas_id`),
                     KEY `openvas_name` (`openvas_name`),
                     KEY `openvas_host` (`openvas_host`),
                     KEY `openvas_severity` (`openvas_severity`),
                     KEY `openvas_date_last_scan` (`openvas_date_last_scan`),
                     KEY `date_creation` (`date_creation`),
                     KEY `date_mod` (`date_mod`)
                  ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
         $DB->query($query) or die ($DB->error());
      }

      $cron = new CronTask;
      if (!$cron->getFromDBbyName(__CLASS__, 'openvasSynchronize')) {
         CronTask::Register(__CLASS__, 'openvasSynchronize', DAY_TIMESTAMP,
                            array('param' => 24, 'mode' => CronTask::MODE_EXTERNAL));
      }
      if (!$cron->getFromDBbyName(__CLASS__, 'openvasClean')) {
         CronTask::Register(__CLASS__, 'openvasClean', DAY_TIMESTAMP,
                            array('param' => 24, 'mode' => CronTask::MODE_EXTERNAL));
      }
   }

   public static function uninstall() {
      global $DB;
      $DB->query("DROP TABLE IF EXISTS `glpi_plugin_openvas_items`");
   }
}
