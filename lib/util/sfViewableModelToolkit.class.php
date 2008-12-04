<?php

/**
 * Plugin utility methods.
 * 
 * @package     sfViewableModelPlugin
 * @subpackage  util
 * @author      Kris Wallsmith <kris.wallsmith@symfony-project.com>
 * @version     SVN: $Id$
 */
class sfViewableModelToolkit
{
  /**
   * Returns the object's primary key.
   * 
   * @param   mixed $object
   * 
   * @return  mixed
   */
  static public function getPrimaryKey($object)
  {
    $event = sfProjectConfiguration::getActive()->getEventDispatcher()->notifyUntil(new sfEvent($object, 'sf_viewable_model_plugin.get_primary_key'));
    if ($event->isProcessed())
    {
      return $event->getReturnValue();
    }

    switch (sfConfig::get('sf_orm'))
    {
      case 'propel':
        return $object->getPrimaryKey();
      case 'doctrine':
        return $object->identifier();
      default:
        throw new LogicException('ORM is neither Propel nor Doctrine. Please connect to "sf_viewable_model_plugin.get_primary_key" and add logic for your ORM.');
    }
  }

  /**
   * Returns a flattened array of model objects from the supplied array.
   * 
   * @param   array $values
   * 
   * @return  array
   */
  static public function filterModelObjects(array $values)
  {
    $models = array();

    foreach ($values as $value)
    {
      if (is_array($value))
      {
        $models = array_merge($models, self::filterModelObjects($value));
      }
      else if (is_object($value) && self::isModelObject($value))
      {
        $models[] = $value;
      }
    }

    return $models;
  }

  /**
   * Returns true if the supplied argument is a model object.
   * 
   * @param   mixed $value
   * 
   * @return  boolean
   */
  static public function isModelObject($value)
  {
    $event = sfProjectConfiguration::getActive()->getEventDispatcher()->notifyUntil(new sfEvent($value, 'sf_viewable_model_plugin.is_model_object'));
    if ($event->isProcessed())
    {
      return $event->getReturnValue();
    }

    if (is_object($value))
    {
      switch (sfConfig::get('sf_orm'))
      {
        case 'propel':
          $ret = $value instanceof BaseObject;
          break;
        case 'doctrine':
          $ret = $value instanceof Doctrine_Record;
          break;
        default:
          throw new LogicException('ORM is neither Propel nor Doctrine. Please connect to "sf_viewable_model_plugin.is_model_object" and add logic for your ORM.');
      }
    }

    if ($ret)
    {
      self::extendModel($value);
    }

    return $ret;
  }

  /**
   * Extends the supplied model with an ORM behavior.
   * 
   * @param string|object $model
   */
  static public function extendModel($model)
  {
    if (is_object($model))
    {
      $model = get_class($model);
    }

    $event = sfProjectConfiguration::getActive()->getEventDispatcher()->notifyUntil(new sfEvent($model, 'sf_viewable_model_plugin.extend_model'));
    if ($event->isProcessed())
    {
      return;
    }

    switch (sfConfig::get('sf_orm'))
    {
      case 'propel':
        sfViewableModelPropelBehavior::extendModel($model);
        break;
      case 'doctrine':
        throw new Exception('Doctrine version not implemented.');
        break;
      default:
        throw new LogicException('ORM is neither Propel nor Doctrine. Please connect to "sf_viewable_model_plugin.extend_model" and add logic for your ORM.');
    }
  }
}
