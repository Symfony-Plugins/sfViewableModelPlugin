<?php

/**
 * Propel behavior.
 * 
 * @package     sfViewableModelPlugin
 * @subpackage  addon
 * @author      Kris Wallsmith <kris.wallsmith@symfony-project.com>
 * @version     SVN: $Id$
 */
class sfViewableModelPropelBehavior
{
  /**
   * Extends a model with this behavior.
   * 
   * @param string $model
   */
  static function extendModel($model)
  {
    if (!is_subclass_of($model, 'BaseObject'))
    {
      throw new InvalidArgumentException(sprintf('The class "%s" is not a subclass of BaseObject.', $model));
    }

    try
    {
      sfPropelBehavior::add($model, array('viewable'));
    }
    catch (Exception $e)
    {
      // behavior has already been added
    }
  }

  /**
   * Removes the supplied object from the cache.
   * 
   * Available options:
   * 
   *  * bubble: If true all "parent" objects will also be removed from the cache (true by default)
   * 
   * @param   BaseObject $object
   * @param   array      $options
   * 
   * @return  boolean
   */
  static public function removeFromCache(BaseObject $object, array $options = array())
  {
    static $removed = array();

    $cacheManager = sfContext::getInstance()->getViewCacheManager();
    if (!$cacheManager instanceof sfViewableModelViewCacheManager || in_array($key = $cacheManager->getModelKey($object), $removed))
    {
      return;
    }
    else
    {
      // mark this object as removed for the duration of the request
      $removed[] = $key;
    }

    $options = array_merge(array(
      'bubble' => true,
    ), $options);

    // remove this model from the view cache
    $cacheManager->removeModel($object);

    if ($options['bubble'])
    {
      try
      {
        $relatedModels = $object->getRelatedViewableModels();
      }
      catch (Exception $e)
      {
        $relatedModels = self::getRelatedViewableModels($object);
      }

      foreach ($relatedModels as $related)
      {
        try
        {
          $related->removeFromCache($options);
        }
        catch (Exception $e)
        {
          self::removeFromCache($related, $options);
        }
      }
    }
  }

  /**
   * Returns an array of viewable models that should be removed from the cache when the supplied object changes.
   * 
   * @param   BaseObject $object
   * 
   * @return  array
   */
  static public function getRelatedViewableModels(BaseObject $object)
  {
    $related = array();

    $peer = constant(get_class($object).'::PEER');
    $tableMap = call_user_func(array($peer, 'getTableMap'));

    foreach ($tableMap->getColumns() as $columnMap)
    {
      if ($columnMap->isForeignKey() && !is_null($value = $object->getByName($columnMap->getPhpName())))
      {
        $refTableMap  = $tableMap->getDatabaseMap()->getTable($columnMap->getRelatedTableName());
        $refColumnMap = $refTableMap->getColumn($columnMap->getRelatedColumnName());
        $refClass     = $refTableMap->getPhpName();
        $refPeer      = constant($refClass.'::PEER');

        // not sure what the method is called
        try
        {
          $refObject = call_user_func(array($object, 'get'.$refClass));
        }
        catch (Exception $e)
        {
          try
          {
            $refObject = call_user_func(array($object, 'get'.$refClass.'RelatedBy'.$columnMap->getPhpName()));
          }
          catch (Exception $e)
          {
          }
        }

        if (!$refObject)
        {
          $c = new Criteria();
          $c->add($refColumnMap->getFullyQualifiedName(), $value);
          $refObject = call_user_func(array($refPeer, 'doSelectOne'), $c);
        }

        if ($refObject instanceof BaseObject)
        {
          $related[] = $refObject;
        }
        else
        {
          throw new LogicException(sprintf('Unable to retrieve object referenced by "%s".', $columnMap->getFullyQualifiedName()));
        }
      }
    }

    return $related;
  }

  /**
   * Clears cached views that include the supplied object.
   * 
   * @param BaseObject  $object
   * @param PropelPDO   $con
   */
  static public function preSave(BaseObject $object, PropelPDO $con = null)
  {
    if ($object->isModified())
    {
      $object->removeFromCache();
    }
  }

  /**
   * Clears cached views that include the supplied object.
   * 
   * @param BaseObject  $object
   * @param PropelPDO   $con
   */
  static public function preDelete(BaseObject $object, PropelPDO $con = null)
  {
    $object->removeFromCache();
  }
}
