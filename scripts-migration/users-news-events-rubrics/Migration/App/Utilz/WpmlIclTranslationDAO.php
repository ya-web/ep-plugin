<?php

namespace Migration\App\Utilz;

use \Migration\Api\DatasourceManager;
use \Migration\App\Config\DbNamesEnum;
use \Migration\Api\MigrationException;
use \Exception;

/**
 * Deletes all posts (along with metas and slug histories)
 * corresponding to previously imported news and events.
 */
class WpmlIclTranslationDAO {

  // WP table prefix for target DB.
  private $wpTablePrefix;
  // Connection (PDO) to WP target DB.
  private $wpDbConnection;

  public function __construct() {
    $dsm = DatasourceManager::getInstance();
    $this->wpTablePrefix    = $dsm->getTablePrefix(DbNamesEnum::Wp);
    $this->wpDbConnection   = $dsm->getConnection(DbNamesEnum::Wp);
  }

  public function create($elemType, $elemId, $trGrId, $langCode, $srcLangCode) {
    $wpMlInsertLanguageQuery = 'INSERT INTO icl_translations (element_type, element_id, trid, language_code, source_language_code) VALUES ' ;
  	$wpMlInsertLanguageQuery .= ' (' . $elemType . ', ' . $elemId . ', ' . $trGrId . ', ' . $langCode . ', ' . $srcLangCode .');';
    try {
      $this->wpDbConnection->exec($wpMlInsertLanguageQuery);
    } catch(Exception $e) {
      echo "-- ECHEC " . basename(__FILE__) . ':' . __FUNCTION__ . " REQUÊTE: [$wpMlInsertLanguageQuery]" . PHP_EOL;
      throw new MigrationException($e, $wpMlInsertLanguageQuery, basename(__FILE__) . ':' . __FUNCTION__);
    }
  }

  public function getMaxTranslationGroupId() {
    // As no concurrent INSERT query is being executed into the icl_translations
    // tables during migration, this is OK
    $lastTranslationGroupIdQuery = "SELECT MAX(trid) as maxtrid FROM icl_translations;";
    $q = $this->wpDbConnection->query($lastTranslationGroupIdQuery);
    $result = $q->fetch();

    return $result['maxtrid'];
  }

}
