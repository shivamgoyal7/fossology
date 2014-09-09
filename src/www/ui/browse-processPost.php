<?php
/***********************************************************
 * Copyright (C) 2014 Siemens AG
 * Author: J.Najjar
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/
use Fossology\Lib\Dao\LicenseDao;
use Fossology\Lib\Dao\UploadDao;
use Fossology\Lib\Dao\UserDao;
use Fossology\Lib\Data\DatabaseEnum;
use Fossology\Lib\Db\DbManager;
use Fossology\Lib\Dao\ClearingDao;
use Fossology\Lib\Data\LicenseRef;
use Fossology\Lib\Data\ClearingDecWithLicenses;
use Fossology\Lib\View\HighlightRenderer;
use Fossology\Lib\Util\ChangeLicenseUtility;
use Fossology\Lib\Util\LicenseOverviewPrinter;

define("TITLE_browseProcessPost", _("Private: Browse post"));

class browseProcessPost extends FO_Plugin
{


  /** @var  UploadDao $uploadDao */
  private $uploadDao;

  /** @var  UserDao $userDao */
  private $userDao;


  /** @var  DbManager dbManager */
  private $dbManager;

  function __construct()
  {
    $this->Name = "browse-processPost";
    $this->Title = TITLE_browseProcessPost;
    $this->Version = "1.0";
    $this->Dependency = array();
    $this->DBaccess = PLUGIN_DB_WRITE;
    $this->NoHTML = 1;
    $this->LoginFlag = 0;
    $this->NoMenu = 0;

    parent::__construct();
    global $container;
    $this->uploadDao = $container->get('dao.upload');
    $this->userDao = $container->get('dao.user');

    $this->dbManager = $container->get('db.manager');
  }

  private function moveUploadBeyond($moveUpload, $beyondUpload)
  {

    $this->dbManager->prepare($stmt=__METHOD__.'.get.single.Upload',
      $sql='SELECT upload_pk,priority FROM upload WHERE upload_pk=$1');
    $movePoint = $this->dbManager->getSingleRow($sql,array($moveUpload),$stmt);
    $beyondPoint = $this->dbManager->getSingleRow($sql,array($beyondUpload),$stmt);
    if ($movePoint['priority'] > $beyondPoint['priority'])
    {
      $farPoint = $this->dbManager->getSingleRow("SELECT priority FROM upload WHERE priority<$1 ORDER BY priority DESC LIMIT 1", array($beyondPoint['priority']), 'get.upload.with.lower.priority');
    }
    else
    {
      $farPoint = $this->dbManager->getSingleRow("SELECT priority FROM upload WHERE priority>$1 ORDER BY priority ASC LIMIT 1", array($beyondPoint['priority']), 'get.upload.with.higher.priority');
    }
    if (false !== $farPoint)
    {
      $newPriority = ($farPoint['priority'] + $beyondPoint['priority'] )/2;
    }
    else if ($movePoint['priority'] > $beyondPoint['priority'])
    {
      $newPriority = $beyondPoint['priority'] - 0.5;
    }
    else
    {
      $newPriority = $beyondPoint['priority'] + 0.5;
    }
    $this->dbManager->getSingleRow('UPDATE upload SET priority=$1 WHERE upload_pk=$2',array($newPriority,$moveUpload),'update.priority');
  }

  private function getOrderString(){

    $columNamesInDatabase=array('upload_filename', 'status_fk', 'UNUSED', 'assignee','upload_ts' ,'priority');

    $orderArray=array();
    for($i=0; $i < $_GET['iSortingCols']; $i++) {

      $whichCol= 'iSortCol_'.$i;
      $colNumber=$_GET[$whichCol];

      $isSortable = $_GET['bSortable_'.$i];

      if($isSortable !== "true") continue;

      $name = $columNamesInDatabase[$colNumber];

      $whichDir = 'sSortDir_'.$i;
      $order = $_GET[$whichDir];
      $orderArray[] = $name." ".$order;
    }

    $orderString = "ORDER BY ";
    $orderString .= implode(", ", $orderArray);

    return $orderString;
  }



  private function ShowFolderGetTableData($Folder, $Show)
  {

    /* Browse-Pfile menu */
    $MenuPfile = menu_find("Browse-Pfile", $MenuDepth);

    /* Browse-Pfile menu without the compare menu item */
    $MenuPfileNoCompare = menu_remove($MenuPfile, "Compare");

    $Uri = Traceback_uri() . "?mod=browse";

    $output = array();
    /* Get list of uploads in this folder */


    $orderString = $this->getOrderString();
    $searchString = $this->getSearchString();
    $stmt = __METHOD__."getFolderContents".$orderString.$searchString;
    $unorderedQuerry = "FROM upload
        INNER JOIN uploadtree ON upload_fk = upload_pk
        AND upload.pfile_fk = uploadtree.pfile_fk
        AND parent IS NULL
        AND lft IS NOT NULL
        WHERE upload_pk IN
        (SELECT child_id FROM foldercontents WHERE foldercontents_mode & 2 != 0 AND parent_fk = $1 ) ";

    $this->dbManager->prepare($stmt,"SELECT * $unorderedQuerry
        $searchString
        $orderString
        OFFSET $2 LIMIT $3
        ");
    $offset = $_GET['iDisplayStart'];
    $limit = $_GET['iDisplayLength'];
    $result = $this->dbManager->execute($stmt,array($Folder, $offset, $limit));


    $iTotalDisplayRecordsRow=$this->dbManager->getSingleRow("SELECT count(*) $unorderedQuerry $searchString",array($Folder),__METHOD__."count");
    $iTotalDisplayRecords=$iTotalDisplayRecordsRow['count'];

    $iTotalRecordsRow=$this->dbManager->getSingleRow("SELECT count(*) $unorderedQuerry ",array($Folder),__METHOD__."count");
    $iTotalRecords=$iTotalRecordsRow['count'];


    $statusTypes = $this->uploadDao->getStatusTypes();
    $users = $this->userDao->getUserChoices();

    $rowCounter = 0;
    while ($Row = pg_fetch_assoc($result)) {
      if (empty($Row['upload_pk'])) {
        continue;
      }
      $rowCounter++;
      $Desc = htmlentities($Row['upload_desc']);
      $UploadPk = $Row['upload_pk'];

      /* check permission on upload */
      $UploadPerm = GetUploadPerm($UploadPk);
      if ($UploadPerm < PERM_READ) continue;

      $Name = $Row['ufile_name'];
      if (empty($Name)) {
        $Name = $Row['upload_filename'];
      }

      /* If UploadtreePk is not an artifact, then use it as the root.
       Else get the first non artifact under it.
       */
      if (Isartifact($Row['ufile_mode']))
        $UploadtreePk = DirGetNonArtifact($Row['uploadtree_pk']);
      else
        $UploadtreePk = $Row['uploadtree_pk'];

      $nameColumn = "";
      if (IsContainer($Row['ufile_mode'])) {
        $nameColumn .= "<a href='$Uri&upload=$UploadPk&folder=$Folder&item=$UploadtreePk&show=$Show'>";
        $nameColumn .= "<b>" . $Name . "</b>";
        $nameColumn .= "</a>";
      }
      else {
        $nameColumn .= "<b>" . $Name . "</b>";
      }
      $nameColumn.= "<br>";
      if (!empty($Desc))
        $nameColumn.= "<i>" . $Desc . "</i><br>";
      $Upload = $Row['upload_pk'];
      $Parm = "upload=$Upload&show=$Show&item=" . $Row['uploadtree_pk'];
      if (Iscontainer($Row['ufile_mode']))
        $nameColumn.= menu_to_1list($MenuPfile, $Parm, " ", " ", 1, $UploadPk);
      else
        $nameColumn.= menu_to_1list($MenuPfileNoCompare, $Parm, " ", " ", 1, $UploadPk);

      /* Job queue link */
      $text = _("History");
      $dateCol="";
      if (plugin_find_id('showjobs') >= 0) {
        $nameColumn .= "[<a href='" . Traceback_uri() . "?mod=showjobs&upload=$UploadPk'>$text</a>]";
        $dateCol = substr($Row['upload_ts'], 0, 19);
      }
      $pairIdPrio = array(intval($Row['upload_pk']), floatval($Row['priority']));
      $currentStatus = DatabaseEnum::createDatabaseEnumSelect("StatusOf_$rowCounter", $statusTypes, $Row['status_fk'], "changeTableEntry", intval($Row['upload_pk']).", 'status_fk'" );
      $currentAssignee = UserDao::createSelectUsers("AssignedTo_$rowCounter", $users, $Row['assignee'], "changeTableEntry", intval($Row['upload_pk']).", 'assignee'" );
      $output[]= array($nameColumn, $currentStatus, "reject" , $currentAssignee, $dateCol, $pairIdPrio );
    }
    pg_free_result($result);
    return array($output, $iTotalRecords, $iTotalDisplayRecords);
  }

  /**
   * \brief Display the loaded menu and plugins.
   */
  function Output()
  {
    if ($this->State != PLUGIN_STATE_READY) {
      return;
    }

    $columnName = GetParm('columnName', PARM_STRING);
    $uploadId  = GetParm('uploadId', PARM_INTEGER);
    $value   = GetParm('value', PARM_INTEGER);
    $moveUpload = GetParm("move", PARM_INTEGER);
    $beyondUpload = GetParm("beyond", PARM_INTEGER);

    if(!empty($columnName) and !empty($uploadId) and !empty($value)) {
        $this->updateTable ($columnName,$uploadId,$value);
    }
    else if (!empty($moveUpload) && !empty($beyondUpload))
    {
      $this->moveUploadBeyond($moveUpload, $beyondUpload);
    }
    else {
      header('Content-type: text/json');
          list($aaData, $iTotalRecords, $iTotalDisplayRecords) =$this->ShowFolderGetTableData($_GET['folder'] , $_GET['show']);
          print(json_encode(array(
                                    'sEcho' => intval($_GET['sEcho']),
                                    'aaData' =>$aaData,
                                    'iTotalRecords' =>$iTotalRecords,
                                    'iTotalDisplayRecords' => $iTotalDisplayRecords
                                 )

                            )
               );
    }
  }

  private function updateTable($columnName, $uploadId, $value)
  {
        $stmt = __METHOD__."_update_".$columnName;
        $sql = "update upload SET ".$columnName."=$1 where upload_pk=$2";
        $this->dbManager->getSingleRow($sql,array($value, $uploadId),$stmt);
  }

  private function getSearchString()
  {
    $search="";

    $searchPattern = GetParm('sSearch', PARM_STRING);

      if(!empty($searchPattern)) {
//        $search.= " and upload_filename like '%$searchPattern%'";
        $searchPattern = strtolower($searchPattern);
        $search.= " and lower(upload_filename) like '%$searchPattern%'";
      }

    return $search;
  }


}

$NewPlugin = new browseProcessPost;
$NewPlugin->Initialize();

