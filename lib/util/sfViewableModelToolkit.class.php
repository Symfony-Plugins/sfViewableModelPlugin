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
    if (!self::isModelObject($object))
    {
      throw new InvalidArgumentException('The supplied argument is not a model object.');
    }

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
          return $value instanceof BaseObject;
        case 'doctrine':
          return $value instanceof Doctrine_Record;
        default:
          throw new LogicException('ORM is neither Propel nor Doctrine. Please connect to "sf_viewable_model_plugin.is_model_object" and add logic for your ORM.');
      }
    }
  }

  /**
   * Returns an array of all model classes.
   * 
   * @return array
   */
  static public function getAllModelClasses()
  {
    $event = sfProjectConfiguration::getActive()->getEventDispatcher()->notifyUntil(new sfEvent(__CLASS__, 'sf_viewable_model_plugin.get_all_model_classes'));
    if ($event->isProcessed())
    {
      return $event->getReturnValue();
    }

    switch (sfConfig::get('sf_orm'))
    {
      case 'propel':
        return sfViewableModelPropelBehavior::getAllModels();
      case 'doctrine':
        // todo
        return array();
      default:
        throw new LogicException('ORM is neither Propel nor Doctrine. Please connect to "sf_viewable_model_plugin.get_all_model_classes" and add logic for your ORM.');
    }
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
        // todo
        break;
      default:
        throw new LogicException('ORM is neither Propel nor Doctrine. Please connect to "sf_viewable_model_plugin.extend_model" and add logic for your ORM.');
    }
  }
}
