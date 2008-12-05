<?php

/**
 * sfViewableModelPlugin configuration.
 * 
 * @package     sfViewableModelPlugin
 * @subpackage  config
 * @author      Kris Wallsmith
 * @version     SVN: $Id$
 */
class sfViewableModelPluginConfiguration extends sfPluginConfiguration
{
  protected
    $viewCacheManager = null;

  /**
   * @see sfPluginConfiguration
   */
  public function initialize()
  {
    $this->dispatcher->connect('context.load_factories', array($this, 'listenForContextLoadFactories'));

    switch (sfConfig::get('sf_orm'))
    {
      case 'propel':
        $this->initializePropelBehavior();
        break;
      case 'doctrine':
        // todo
        break;
    }
  }

  /**
   * Listens for the 'context.load_factories' event.
   * 
   * @param sfEvent $event
   */
  public function listenForContextLoadFactories(sfEvent $event)
  {
    $this->viewCacheManager = $event->getSubject()->getViewCacheManager();

    if ($this->viewCacheManager instanceof sfViewableModelViewCacheManager)
    {
      $this->dispatcher->connect('template.filter_parameters', array($this->viewCacheManager, 'filterTemplateParameters'));
    }
  }

  /**
   * Initializes Propel behavior.
   */
  protected function initializePropelBehavior()
  {
    sfPropelBehavior::registerMethods('viewable', array(
      array('sfViewableModelPropelBehavior', 'removeFromCache'),
      array('sfViewableModelPropelBehavior', 'getRelatedViewableModels'),
    ));

    sfPropelBehavior::registerHooks('viewable', array(
      ':save:pre'   => array('sfViewableModelPropelBehavior', 'preSave'),
      ':delete:pre' => array('sfViewableModelPropelBehavior', 'preDelete'),
    ));
  }
}
