<?php
/**
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * @package    apostrophePlugin
 * @subpackage    model
 * @author     P'unk Avenue <apostrophe@punkave.com>
 */
class PluginaMediaItemTable extends Doctrine_Table
{

  /**
   * DOCUMENT ME
   * @return mixed
   */
  public function getLuceneIndex()
  {
    return aZendSearch::getLuceneIndex($this);
  }

  /**
   * DOCUMENT ME
   * @return mixed
   */
  public function getLuceneIndexFile()
  {
    return aZendSearch::getLuceneIndexFile($this);
  }

  /**
   * DOCUMENT ME
   * @param mixed $luceneQuery
   * @return mixed
   */
  public function searchLucene($luceneQuery)
  {
    return aZendSearch::searchLucene($this, $luceneQuery);
  }

  /**
   * DOCUMENT ME
   * @return mixed
   */
  public function rebuildLuceneIndex()
  {
    return aZendSearch::rebuildLuceneIndex($this);
  }

  /**
   * DOCUMENT ME
   * @return mixed
   */
  public function optimizeLuceneIndex()
  {
    return aZendSearch::optimizeLuceneIndex($this);
  }

  /**
   * DOCUMENT ME
   * @param Doctrine_Query $q
   * @param mixed $luceneQuery
   * @return mixed
   */
  public function addSearchQuery(Doctrine_Query $q = null, $luceneQuery)
  {
    if ($q)
    {
      $q->addSelect($q->getRootAlias() . '.*');
    }
    return aZendSearch::addSearchQuery($this, $q, $luceneQuery);
  }

  /**
   * Returns the folder in the filesystem to which media items should be stored
   */
  static public function getDirectory()
  {
    return aFiles::getUploadFolder('media_items');
  }
  
  /**
   * Returns the URL where media repository contents are found
   */
  static public function getUrl($options = array())
  {
    $absolute = isset($options['absolute']) && $options['absolute'];
    $result = sfConfig::get('app_aMedia_static_url', sfConfig::get('app_a_static_url', '') . '/uploads/media_items');
    return $result;
  }

  /**
   * Returns items for all ids in $ids. If an item does not exist,
   * that item is not returned; this is not considered an error.
   * You can easily compare count($result) to count($ids).
   * @param mixed $ids
   * @return mixed
   */
  static public function retrieveByIds($ids)
  {
    if (!count($ids))
    {
      // WHERE freaks out over empty lists. We don't.
      return array();
    }
    if (count($ids) == 1)
    {
      if (!$ids[0])
      {
        // preg_split and its ilk return a one-element array
        // with an empty string in it when passed an empty string.
        // Tolerate this.
        return array();
      }
    }
    $q = Doctrine_Query::create()->
      select('m.*')->
      from('aMediaItem m')->
      whereIn("m.id", $ids);
    aDoctrine::orderByList($q, $ids);
    return $q->execute();
  }

  /**
   * Returns a query matching media items satisfying the specified parameters, all of which
   * are optional:
   * 
   * tag
   * search
   * type (video, image, etc)
   * user (a username, to determine access rights)
   * aspect-width and aspect-height (returns only images with the specified aspect ratio)
   * minimum-width
   * minimum-height
   * width
   * height
   * ids
   * downloadable
   * embeddable
   * 
   * Parameters are passed safely via wildcards so it should be OK to pass unsanitized
   * external API inputs to this method.
   * 
   * 'ids' is an array of item IDs. If it is present, only items with one of those IDs are
   * potentially returned.
   * 
   * If 'search' is present, results are returned in descending order by match quality.
   * Otherwise, if 'ids' is present, results are returned in that order. Otherwise,
   * results are returned newest first.
   * @param mixed $params
   * @return mixed
   */
  static public function getBrowseQuery($params)
  {
    $query = Doctrine_Query::create();
    // We can't use an alias because that is incompatible with getObjectTaggedWithQuery
    $query->from('aMediaItem');
    if (isset($params['ids']))
    {
      $query->select('aMediaItem.*, c.*');
      aDoctrine::orderByList($query, $params['ids']);
      $query->andWhereIn("aMediaItem.id", $params['ids']);
    }
    // New: at least one of the specified tags must be present. This is kind of a pain to check for because
    // tags can be specified as arrays or a comma separated string
    if (isset($params['allowed_tags']) && (strlen($params['allowed_tags']) || (is_array($params['allowed_tags']) && count($params['allowed_tags']))))
    {
      $query = TagTable::getObjectTaggedWithQuery(
        'aMediaItem', $params['allowed_tags'], $query, array('nb_common_tags' => 1));
    }
    elseif (isset($params['tag']))
    {
      $query = TagTable::getObjectTaggedWithQuery(
        'aMediaItem', $params['tag'], $query);
    }
    if (isset($params['type']))
    {
      // Supports metatypes like _downloadable
      $types = array();
      $typeInfos = aMediaTools::getTypeInfos($params['type']);
      foreach ($typeInfos as $name => $info)
      {
        $types[] = $name;
      }
      if (count($types))
      {
        $query->andWhereIn("aMediaItem.type", $types);
      }
      else
      {
        $query->andWhere("0 <> 0");
      }
    }
    if (isset($params['allowed_categories']))
    {
      if (!count($params['allowed_categories']))
      {
        $query->andWhere('0 <> 0');
      }
      else
      {
        $query->innerJoin('aMediaItem.Categories mc1 WITH mc1.id IN (' . implode(',', aArray::getIds($params['allowed_categories'])) . ')');
      }
    }
    if (isset($params['category']))
    {
      $query->innerJoin('aMediaItem.Categories mc2 WITH mc2.slug = ?', array($params['category']));
    }
    if (isset($params['search']))
    {
      $query = Doctrine::getTable('aMediaItem')->addSearchQuery($query, $params['search']);
    }
    elseif (isset($params['ids']))
    {
      // orderBy added by aDoctrine::orderByIds
    }
    else
    {
      // Reverse chrono order if we're not ordering them by search relevance
      $query->orderBy('aMediaItem.id desc');
    }
    if (!sfContext::getInstance()->getUser()->hasCredential(sfConfig::get('app_a_view_locked_sufficient_credentials', 'view_locked')))
    {
      $query->andWhere('aMediaItem.view_is_secure = false');
    }
    if (isset($params['aspect-width']) && isset($params['aspect-height']))
    {
      $query->andWhere('(aMediaItem.width * ? / ?) = aMediaItem.height', array($params['aspect-height'] + 0, $params['aspect-width'] + 0));
    }
    if (isset($params['minimum-width']))
    {
      $query->andWhere('aMediaItem.width >= ?', array($params['minimum-width'] + 0));
    }
    if (isset($params['minimum-height']))
    {
      $query->andWhere('aMediaItem.height >= ?', array($params['minimum-height'] + 0));
    }
    if (isset($params['width']))
    {
      $query->andWhere('aMediaItem.width = ?', array($params['width'] + 0));
    }
    if (isset($params['height']))
    {
      $query->andWhere('aMediaItem.height = ?', array($params['height'] + 0));
    }
    // No crops in the browser please
    $query->andWhere("aMediaItem.slug NOT LIKE '%.%'");
    $query->leftJoin("aMediaItem.Categories c");
    
    return $query;
  }

  /**
   * DOCUMENT ME
   * @return mixed
   */
  static public function getAllTagNameForUserWithCount()
  {
    // Retrieves only tags relating to media items this user is allowed to see
    $q = NULL;
    if (!sfContext::getInstance()->getUser()->hasCredential(sfConfig::get('app_a_view_locked_sufficient_credentials', 'view_locked')))
    {
      $q = Doctrine_Query::create()->from('Tagging tg, tg.Tag t, aMediaItem m');
      // If you're not logged in, you shouldn't see tags relating to secured stuff
      // Always IS FALSE, never = FALSE
      $q->andWhere('m.id = tg.taggable_id AND ((m.view_is_secure IS NULL) OR (m.view_is_secure IS  FALSE))');
    }
    return TagTable::getAllTagNameWithCount($q, 
      array("model" => "aMediaItem"));
  }

  /**
   * Retrieves media items matching the supplied array of ids, in the same order as the ids
   * (a simple whereIn does not do this). We must use an explicit select when using
   * aDoctrine::orderByList.
   * @param mixed $ids
   * @return mixed
   */
  public function findByIdsInOrder($ids)
  {
    if (empty($ids))
    {
      // Doctrine doesn't generate any clause at all for WHERE IN if an array if false. This is a bug, but
      // it doesn't seem to be getting fixed at the Doctrine level
      return Doctrine::getTable('aMediaItem')->createQuery('m')->select('m.*')->where('1 = 0');
    }
    $q = Doctrine::getTable('aMediaItem')->createQuery('m')->select('m.*')->whereIn('m.id', $ids);
    // Don't forget to put them in order!
    return aDoctrine::orderByList($q, $ids)->execute();
  }

  /**
   * DOCUMENT ME
   * @return mixed
   */
  public function getCountByCategory()
  {
    $raw = Doctrine::getTable('aCategory')->createQuery('c')->innerJoin('c.aMediaItemToCategory mtc')->select('c.name, c.slug,  count(mtc.media_item_id) as num')->groupBy('mtc.category_id')->orderBy('c.name ASC')->execute(array(), Doctrine::HYDRATE_ARRAY);
    $results = array();
    foreach ($raw as $info)
    {
      $results[$info['id']] = array('name' => $info['name'], 'slug' => $info['slug'], 'count' => $info['num']);
    }
    return $results;
  }

  /**
   * Column in category table that determines whether these are allowed in the category
   * @return mixed
   */
  public function getCategoryColumn()
  {
    return 'media_items';
  }
}
